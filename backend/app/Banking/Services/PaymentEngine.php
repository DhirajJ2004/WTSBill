<?php

namespace App\Banking\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CustomerAdvance;
use App\Models\CustomerCredit;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\AuditLog;
use App\Payments\Allocations\PaymentAllocationService;
use App\Services\AccountingEventService;
use App\Services\BankTransferService;
use App\Services\GSTLedgerService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

use App\Services\DocumentNumberService;

class PaymentEngine
{
    /**
     * Generate unique payment / receipt number using DocumentNumberService
     */
    public static function generatePaymentNumber(int $companyId, string $prefix = 'REC', ?int $branchId = null, string $fy = '2026-27'): string
    {
        $docType = ($prefix === 'PAY' || $prefix === 'SUPPLIER_PAYMENT') ? 'PAYMENT' : 'RECEIPT';
        return DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, $docType);
    }

    /**
     * Process Customer Receipt
     */
    public static function processCustomerReceipt(int $companyId, array $data, ?string $userName = 'System'): Payment
    {
        $customerId = intval($data['customer_id'] ?? ($data['party_id'] ?? 0));
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        $paymentDate = $data['payment_date'] ?? ($data['date'] ?? date('Y-m-d'));
        // Check Period Lock
        GSTLedgerService::assertPeriodNotLocked($companyId, $paymentDate);

        $paymentMode = strtoupper($data['payment_mode'] ?? ($data['payment_method'] ?? 'BANK_TRANSFER'));
        // Normalize mode names
        if ($paymentMode === 'BANK TRANSFER') $paymentMode = 'BANK_TRANSFER';
        if ($paymentMode === 'CREDIT CARD' || $paymentMode === 'DEBIT CARD') $paymentMode = 'CARD';

        return DB::transaction(function () use ($companyId, $customerId, $amount, $paymentDate, $paymentMode, $data, $userName) {
            $invoiceId = intval($data['invoice_id'] ?? 0);
            $invoice = null;

            if ($invoiceId > 0) {
                $invoice = Invoice::where('company_id', $companyId)->lockForUpdate()->findOrFail($invoiceId);
                if ($customerId > 0 && (int)$invoice->customer_id !== $customerId) {
                    throw new InvalidArgumentException("Selected invoice belongs to a different customer.");
                }
                $customerId = (int)$invoice->customer_id;

                $outstanding = round(floatval($invoice->amount_due ?? ($invoice->grand_total - ($invoice->amount_paid ?? 0))), 2);
                if ($outstanding <= 0.001) {
                    throw new InvalidArgumentException("Invoice #{$invoice->invoice_number} is already fully paid.");
                }

                // Invariant: Prevent overpayment unless explicitly supported
                if ($amount > ($outstanding + 0.001)) {
                    throw new InvalidArgumentException("Payment amount (₹" . number_format($amount, 2) . ") exceeds invoice outstanding balance (₹" . number_format($outstanding, 2) . "). Overpayment is not permitted.");
                }

                if (empty($data['allocations'])) {
                    $data['allocations'] = [
                        ['invoice_id' => $invoice->id, 'amount' => $amount]
                    ];
                }
            }

            if ($customerId <= 0) {
                throw new InvalidArgumentException("Customer selection is required to record a receipt.");
            }

            $customer = Customer::where('company_id', $companyId)->lockForUpdate()->findOrFail($customerId);

            $bankAccountId = !empty($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
            if (!$bankAccountId && in_array($paymentMode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])) {
                $defaultBank = BankAccount::where('company_id', $companyId)->where('is_active', 1)->first();
                if ($defaultBank) {
                    $bankAccountId = $defaultBank->id;
                }
            }

            if ($bankAccountId) {
                $bankAcc = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);
                if (isset($bankAcc->is_active) && !$bankAcc->is_active) {
                    throw new InvalidArgumentException("Selected bank account is INACTIVE and cannot accept new payments.");
                }
            }

            $validModes = ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'CASH', 'ONLINE', 'WALLET', 'NEFT', 'RTGS', 'IMPS'];
            if (!in_array($paymentMode, $validModes, true)) {
                throw new InvalidArgumentException("Invalid payment mode '{$paymentMode}'. Supported modes: " . implode(', ', ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'CASH']));
            }

            $branchId = $data['branch_id'] ?? null;
            $fy = $data['financial_year'] ?? '2026-27';

            $paymentNumber = $data['payment_number'] ?? DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'RECEIPT');
            $receiptNumber = $data['receipt_number'] ?? $paymentNumber;
            $reference = trim((string)($data['reference_number'] ?? ($data['reference'] ?? ($data['reference_no'] ?? ($data['utr'] ?? '')))));
            $utr = trim((string)($data['utr'] ?? $reference));

            // Check duplicate reference
            if ($reference !== '') {
                $existingRef = Payment::where('company_id', $companyId)
                    ->where('status', '!=', 'CANCELLED')
                    ->where(function ($q) use ($reference) {
                        $q->where('reference_number', $reference)
                          ->orWhere('transaction_reference', $reference)
                          ->orWhere('utr', $reference);
                    })
                    ->first();
                if ($existingRef) {
                    throw new InvalidArgumentException("Duplicate payment reference '{$reference}' already recorded on payment #{$existingRef->payment_number}.");
                }
            }

            // Check high-value approval threshold if configured
            $status = 'POSTED';
            $requiresApproval = !empty($data['requires_approval']);
            if ($requiresApproval) {
                $status = 'PENDING_APPROVAL';
            }

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'payment_number' => $paymentNumber,
                'receipt_number' => $receiptNumber,
                'payment_type' => 'RECEIPT',
                'payment_date' => $paymentDate,
                'party_type' => 'CUSTOMER',
                'party_id' => $customer->id,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => $data['currency'] ?? 'INR',
                'payment_mode' => $paymentMode,
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $bankAccountId,
                'reference_number' => $reference ?: null,
                'transaction_reference' => $reference ?: null,
                'reference_no' => $reference ?: null,
                'utr' => $utr ?: null,
                'cheque_number' => $data['cheque_number'] ?? ($data['cheque_no'] ?? null),
                'cheque_date' => $data['cheque_date'] ?? null,
                'cheque_bank' => $data['cheque_bank'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'created_by' => $data['created_by'] ?? 1,
            ]);

            if ($status === 'POSTED') {
                // 1. Process Invoices Allocation
                $allocations = $data['allocations'] ?? [];
                if (!empty($allocations)) {
                    PaymentAllocationService::allocateCustomerPayment($payment, $allocations, $userName);
                } elseif (!empty($data['auto_allocate'])) {
                    PaymentAllocationService::autoAllocate($payment, $userName);
                }

                $payment->refresh();

                // 2. Update Customer Balance (Receivable decreases)
                $customer->current_balance = max(0.00, round(floatval($customer->current_balance) - $amount, 2));
                $customer->save();

                // 3. Post Double-Entry Accounting
                AccountingEventService::recordPaymentAccounting($payment, $userName);

                // 4. Update Bank Account & Bank Transaction Ledger
                if ($bankAccountId && $paymentMode !== 'CHEQUE') {
                    $bankAcc = BankAccount::where('company_id', $companyId)->find($bankAccountId);
                    if ($bankAcc) {
                        $bankAcc->current_balance = round($bankAcc->current_balance + $amount, 2);
                        $bankAcc->save();

                        BankTransaction::create([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'bank_account_id' => $bankAccountId,
                            'transaction_date' => $paymentDate,
                            'type' => 'CREDIT',
                            'debit_credit' => 'CREDIT',
                            'transaction_type' => 'PAYMENT_RECEIVED',
                            'reference_number' => $payment->reference_number ?: $payment->payment_number,
                            'description' => "Customer receipt from {$customer->name} (Receipt #{$payment->payment_number})",
                            'amount' => $amount,
                            'balance_after' => $bankAcc->current_balance,
                            'reconciliation_status' => 'UNRECONCILED',
                            'source' => 'CUSTOMER_PAYMENT',
                            'source_id' => $payment->id,
                        ]);
                    }
                }
            }

            AuditLog::create([
                'company_id' => $companyId,
                'entity_type' => 'PAYMENT',
                'entity_id' => $payment->id,
                'action' => 'CUSTOMER_RECEIPT_CREATED',
                'user_name' => $userName,
                'ip_address' => '127.0.0.1',
                'description' => "Processed customer receipt #{$payment->payment_number} for ₹" . number_format($amount, 2) . " from {$customer->name} via {$paymentMode}",
            ]);

            return $payment;
        });
    }

    /**
     * Process Supplier Payment
     */
    public static function processSupplierPayment(int $companyId, array $data, ?string $userName = 'System'): Payment
    {
        $supplierId = intval($data['supplier_id'] ?? ($data['party_id'] ?? 0));
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        $paymentDate = $data['payment_date'] ?? ($data['date'] ?? date('Y-m-d'));
        GSTLedgerService::assertPeriodNotLocked($companyId, $paymentDate);

        $paymentMode = strtoupper($data['payment_mode'] ?? ($data['payment_method'] ?? 'BANK_TRANSFER'));
        if ($paymentMode === 'BANK TRANSFER') $paymentMode = 'BANK_TRANSFER';
        if ($paymentMode === 'CREDIT CARD' || $paymentMode === 'DEBIT CARD') $paymentMode = 'CARD';

        return DB::transaction(function () use ($companyId, $supplierId, $amount, $paymentDate, $paymentMode, $data, $userName) {
            $purchaseId = intval($data['purchase_id'] ?? 0);
            if ($purchaseId > 0) {
                $purchase = Purchase::where('company_id', $companyId)->lockForUpdate()->findOrFail($purchaseId);
                if ($supplierId > 0 && (int)$purchase->supplier_id !== $supplierId) {
                    throw new InvalidArgumentException("Selected bill belongs to a different supplier.");
                }
                $supplierId = (int)$purchase->supplier_id;
                $outstanding = round(floatval($purchase->amount_due ?? ($purchase->grand_total - ($purchase->amount_paid ?? 0))), 2);
                if ($outstanding <= 0.001) {
                    throw new InvalidArgumentException("Bill #{$purchase->purchase_number} is already fully paid.");
                }
                if ($amount > ($outstanding + 0.001)) {
                    throw new InvalidArgumentException("Payment amount (₹" . number_format($amount, 2) . ") exceeds bill outstanding balance (₹" . number_format($outstanding, 2) . "). Overpayment is not permitted.");
                }
                if (empty($data['allocations'])) {
                    $data['allocations'] = [
                        ['purchase_id' => $purchase->id, 'amount' => $amount]
                    ];
                }
            }

            if ($supplierId <= 0) {
                throw new InvalidArgumentException("Supplier selection is required to record a payment.");
            }

            $supplier = Supplier::where('company_id', $companyId)->lockForUpdate()->findOrFail($supplierId);

            $bankAccountId = !empty($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
            if (!$bankAccountId && in_array($paymentMode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])) {
                $defaultBank = BankAccount::where('company_id', $companyId)->where('is_active', 1)->first();
                if ($defaultBank) {
                    $bankAccountId = $defaultBank->id;
                }
            }

            if ($bankAccountId) {
                $bankAcc = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);
                if (isset($bankAcc->is_active) && !$bankAcc->is_active) {
                    throw new InvalidArgumentException("Selected bank account is INACTIVE and cannot be used for payments.");
                }
            }

            $validModes = ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'CASH', 'ONLINE', 'WALLET', 'NEFT', 'RTGS', 'IMPS'];
            if (!in_array($paymentMode, $validModes, true)) {
                throw new InvalidArgumentException("Invalid payment mode '{$paymentMode}'. Supported modes: " . implode(', ', ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'CASH']));
            }

            $branchId = $data['branch_id'] ?? null;
            $fy = $data['financial_year'] ?? '2026-27';

            $paymentNumber = $data['payment_number'] ?? DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'PAYMENT');
            $reference = trim((string)($data['reference_number'] ?? ($data['reference'] ?? ($data['reference_no'] ?? ($data['utr'] ?? '')))));
            $utr = trim((string)($data['utr'] ?? $reference));

            // Check duplicate reference
            if ($reference !== '') {
                $existingRef = Payment::where('company_id', $companyId)
                    ->where('status', '!=', 'CANCELLED')
                    ->where(function ($q) use ($reference) {
                        $q->where('reference_number', $reference)
                          ->orWhere('transaction_reference', $reference)
                          ->orWhere('utr', $reference);
                    })
                    ->first();
                if ($existingRef) {
                    throw new InvalidArgumentException("Duplicate payment reference '{$reference}' already recorded on payment #{$existingRef->payment_number}.");
                }
            }

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'payment_number' => $paymentNumber,
                'payment_type' => 'PAYMENT',
                'payment_date' => $paymentDate,
                'party_type' => 'SUPPLIER',
                'party_id' => $supplier->id,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => $data['currency'] ?? 'INR',
                'payment_mode' => $paymentMode,
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $bankAccountId,
                'reference_number' => $reference ?: null,
                'transaction_reference' => $reference ?: null,
                'reference_no' => $reference ?: null,
                'utr' => $utr ?: null,
                'cheque_number' => $data['cheque_number'] ?? ($data['cheque_no'] ?? null),
                'cheque_date' => $data['cheque_date'] ?? null,
                'cheque_bank' => $data['cheque_bank'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'POSTED',
                'created_by' => $data['created_by'] ?? 1,
            ]);

            // 1. Process Purchases Allocation
            $allocations = $data['allocations'] ?? [];
            if (!empty($allocations)) {
                PaymentAllocationService::allocateSupplierPayment($payment, $allocations, $userName);
            }

            $payment->refresh();

            // 2. Update Supplier Balance (Payable decreases)
            $supplier->current_balance = max(0.00, round(floatval($supplier->current_balance) - $amount, 2));
            $supplier->save();

            // 3. Post Double-Entry Accounting
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            // 4. Update Bank Account & Bank Transaction
            if ($bankAccountId && $paymentMode !== 'CHEQUE') {
                $bankAcc = BankAccount::where('company_id', $companyId)->find($bankAccountId);
                if ($bankAcc) {
                    $bankAcc->current_balance = round($bankAcc->current_balance - $amount, 2);
                    $bankAcc->save();

                    BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'bank_account_id' => $bankAccountId,
                        'transaction_date' => $paymentDate,
                        'type' => 'DEBIT',
                        'debit_credit' => 'DEBIT',
                        'transaction_type' => 'PAYMENT_MADE',
                        'reference_number' => $payment->reference_number ?: $payment->payment_number,
                        'description' => "Supplier payment to {$supplier->name} (Payment #{$payment->payment_number})",
                        'amount' => $amount,
                        'balance_after' => $bankAcc->current_balance,
                        'reconciliation_status' => 'UNRECONCILED',
                        'source' => 'SUPPLIER_PAYMENT',
                        'source_id' => $payment->id,
                    ]);
                }
            }

            AuditLog::create([
                'company_id' => $companyId,
                'entity_type' => 'PAYMENT',
                'entity_id' => $payment->id,
                'action' => 'SUPPLIER_PAYMENT_CREATED',
                'user_name' => $userName,
                'ip_address' => '127.0.0.1',
                'description' => "Processed supplier payment #{$payment->payment_number} for ₹" . number_format($amount, 2) . " to {$supplier->name} via {$paymentMode}",
            ]);

            return $payment;
        });
    }

    /**
     * Process Customer Advance Payment
     */
    public static function processCustomerAdvance(int $companyId, array $data, ?string $userName = 'System'): CustomerAdvance
    {
        $customerId = intval($data['customer_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Advance amount must be greater than zero.");
        }

        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        GSTLedgerService::assertPeriodNotLocked($companyId, $paymentDate);

        return DB::transaction(function () use ($companyId, $customerId, $amount, $paymentDate, $data, $userName) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

            $payment = self::processCustomerReceipt($companyId, array_merge($data, [
                'customer_id' => $customerId,
                'amount' => $amount,
                'allocations' => [],
                'notes' => $data['notes'] ?? 'Customer Advance Payment',
            ]), $userName);

            $advance = CustomerAdvance::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'advance_date' => $paymentDate,
                'total_amount' => $amount,
                'allocated_amount' => 0.00,
                'remaining_amount' => $amount,
                'status' => 'ACTIVE',
                'notes' => $payment->notes,
            ]);

            // Create customer credit record
            CustomerCredit::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'customer_id' => $customer->id,
                'credit_type' => 'ADVANCE',
                'source_id' => $advance->id,
                'amount' => $amount,
                'applied_amount' => 0.00,
                'remaining_amount' => $amount,
                'credit_date' => $paymentDate,
                'expiry_date' => date('Y-m-d', strtotime('+1 year')),
                'notes' => "Advance collection #{$payment->payment_number}",
                'status' => 'ACTIVE',
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'entity_type' => 'CUSTOMER_ADVANCE',
                'entity_id' => $advance->id,
                'action' => 'ADVANCE_RECORDED',
                'user_name' => $userName,
                'ip_address' => '127.0.0.1',
                'description' => "Customer advance of ₹{$amount} recorded for {$customer->name}",
            ]);

            return $advance;
        });
    }

    /**
     * Process Payment Reversal
     */
    public static function processPaymentReversal(int $companyId, int $paymentId, string $reason, ?string $userName = 'System'): Payment
    {
        return DB::transaction(function () use ($companyId, $paymentId, $reason, $userName) {
            $payment = Payment::where('company_id', $companyId)->findOrFail($paymentId);

            if (in_array($payment->status, ['REVERSED', 'CANCELLED'])) {
                throw new InvalidArgumentException("Payment #{$payment->payment_number} has already been reversed or cancelled.");
            }

            GSTLedgerService::assertPeriodNotLocked($companyId, $payment->payment_date);

            // 1. Rollback All Invoices / Purchases allocations
            foreach ($payment->allocations as $alloc) {
                if ($alloc->document_type === 'INVOICE' || empty($alloc->document_type)) {
                    $invId = $alloc->invoice_id ?: $alloc->document_id;
                    $invoice = Invoice::where('company_id', $companyId)->find($invId);
                    if ($invoice) {
                        $newPaid = max(0, round($invoice->amount_paid - $alloc->allocated_amount, 2));
                        $newDue = round($invoice->grand_total - $newPaid, 2);
                        $newStatus = $newPaid <= 0.001 ? 'POSTED' : 'PARTIALLY_PAID';
                        $invoice->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'status' => $newStatus,
                        ]);
                    }
                } elseif ($alloc->document_type === 'PURCHASE') {
                    $purchase = Purchase::where('company_id', $companyId)->find($alloc->document_id ?: $alloc->invoice_id);
                    if ($purchase) {
                        $newPaid = max(0, round(($purchase->amount_paid ?? 0) - $alloc->allocated_amount, 2));
                        $newDue = round($purchase->grand_total - $newPaid, 2);
                        $newStatus = $newPaid <= 0.001 ? 'POSTED' : 'PARTIALLY_PAID';
                        $purchase->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'status' => $newStatus,
                        ]);
                    }
                }
                $alloc->delete();
            }

            // 2. Adjust Bank Account balance if linked
            if ($payment->bank_account_id) {
                $bankAcc = BankAccount::where('company_id', $companyId)->find($payment->bank_account_id);
                if ($bankAcc) {
                    if ($payment->payment_type === 'RECEIPT') {
                        $bankAcc->current_balance = round($bankAcc->current_balance - $payment->amount, 2);
                    } else {
                        $bankAcc->current_balance = round($bankAcc->current_balance + $payment->amount, 2);
                    }
                    $bankAcc->save();

                    BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $payment->branch_id,
                        'bank_account_id' => $payment->bank_account_id,
                        'transaction_date' => date('Y-m-d'),
                        'type' => $payment->payment_type === 'RECEIPT' ? 'DEBIT' : 'CREDIT',
                        'debit_credit' => $payment->payment_type === 'RECEIPT' ? 'DEBIT' : 'CREDIT',
                        'transaction_type' => 'REVERSAL',
                        'reference_number' => "REV-{$payment->payment_number}",
                        'description' => "Reversal of payment #{$payment->payment_number}: {$reason}",
                        'amount' => $payment->amount,
                        'balance_after' => $bankAcc->current_balance,
                        'reconciliation_status' => 'UNRECONCILED',
                        'source' => 'MANUAL',
                        'source_id' => $payment->id,
                    ]);
                }
            }

            // 3. Mark Payment Status REVERSED
            $payment->update([
                'status' => 'REVERSED',
                'allocated_amount' => 0.00,
                'unallocated_amount' => 0.00,
                'notes' => ($payment->notes ? $payment->notes . " | " : "") . "REVERSED on " . date('Y-m-d H:i:s') . " by {$userName}. Reason: {$reason}",
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'entity_type' => 'PAYMENT',
                'entity_id' => $payment->id,
                'action' => 'PAYMENT_REVERSED',
                'user_name' => $userName,
                'ip_address' => '127.0.0.1',
                'description' => "Reversed payment #{$payment->payment_number} of ₹{$payment->amount}. Reason: {$reason}",
            ]);

            return $payment;
        });
    }

    /**
     * Process Payment Refund
     */
    public static function processPaymentRefund(
        int $companyId,
        int $paymentId,
        float $refundAmount,
        string $reason,
        string $paymentMode = 'BANK_TRANSFER',
        ?string $userName = 'System'
    ): PaymentRefund {
        $refundAmount = round($refundAmount, 2);
        if ($refundAmount <= 0.001) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        return DB::transaction(function () use ($companyId, $paymentId, $refundAmount, $reason, $paymentMode, $userName) {
            $payment = Payment::where('company_id', $companyId)->findOrFail($paymentId);

            if ($payment->status === 'REVERSED' || $payment->status === 'CANCELLED') {
                throw new InvalidArgumentException("Cannot refund a reversed or cancelled payment.");
            }

            $currentRefunded = PaymentRefund::where('payment_id', $paymentId)->sum('amount');
            $maxRefundable = round($payment->amount - $currentRefunded, 2);

            if ($refundAmount > $maxRefundable + 0.001) {
                throw new InvalidArgumentException("Refund amount ₹{$refundAmount} exceeds maximum refundable balance of ₹{$maxRefundable}.");
            }

            $refundNumber = 'REF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $refund = PaymentRefund::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'financial_year' => $payment->financial_year ?? '2026-27',
                'payment_id' => $payment->id,
                'party_type' => $payment->party_type,
                'party_id' => $payment->party_id,
                'refund_number' => $refundNumber,
                'amount' => $refundAmount,
                'refund_date' => date('Y-m-d'),
                'payment_mode' => strtoupper($paymentMode),
                'refund_mode' => strtoupper($paymentMode),
                'bank_account_id' => $payment->bank_account_id,
                'reason' => $reason,
                'status' => 'COMPLETED',
                'created_by' => \App\Http\Middleware\AuthMiddleware::getUser()?->id ?? $payment->created_by,
            ]);

            // Adjust unallocated amount
            $payment->unallocated_amount = max(0, round($payment->unallocated_amount - $refundAmount, 2));
            $newTotalRefunded = round($currentRefunded + $refundAmount, 2);
            if ($newTotalRefunded >= $payment->amount - 0.01) {
                $payment->status = 'REFUNDED';
            } else {
                $payment->status = 'PARTIALLY_REFUNDED';
            }
            $payment->save();

            // Adjust bank balance if linked
            if ($payment->bank_account_id) {
                $bankAcc = BankAccount::where('company_id', $companyId)->find($payment->bank_account_id);
                if ($bankAcc) {
                    $bankAcc->current_balance = round($bankAcc->current_balance - $refundAmount, 2);
                    $bankAcc->save();

                    BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $payment->branch_id,
                        'bank_account_id' => $payment->bank_account_id,
                        'transaction_date' => date('Y-m-d'),
                        'type' => 'DEBIT',
                        'debit_credit' => 'DEBIT',
                        'transaction_type' => 'REFUND',
                        'reference_number' => $refundNumber,
                        'description' => "Refund against payment #{$payment->payment_number}: {$reason}",
                        'amount' => $refundAmount,
                        'balance_after' => $bankAcc->current_balance,
                        'reconciliation_status' => 'UNRECONCILED',
                        'source' => 'MANUAL',
                        'source_id' => $refund->id,
                    ]);
                }
            }

            AuditLog::create([
                'company_id' => $companyId,
                'entity_type' => 'PAYMENT_REFUND',
                'entity_id' => $refund->id,
                'action' => 'PAYMENT_REFUNDED',
                'user_name' => $userName,
                'ip_address' => '127.0.0.1',
                'description' => "Refunded ₹{$refundAmount} for payment #{$payment->payment_number}. Refund #{$refundNumber}",
            ]);

            return $refund;
        });
    }

    /**
     * Process Internal Bank/Cash Transfer
     */
    public static function processInternalTransfer(int $companyId, array $data, ?string $userName = 'System'): array
    {
        return BankTransferService::createTransfer($companyId, $data, $userName);
    }
}
