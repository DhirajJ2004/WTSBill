<?php

namespace App\Payments\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CustomerAdvance;
use App\Models\CustomerCredit;
use App\Models\BankTransaction;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Payments\Allocations\PaymentAllocationService;
use App\Services\AccountingEventService;
use App\Services\DocumentNumberingService;
use App\Services\JournalService;
use App\Services\AccountService;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class PaymentService
{
    /**
     * Record customer payment with multi-invoice allocation and accounting integration.
     */
    public static function recordCustomerPayment(int $companyId, array $data, ?string $userName = 'System'): Payment
    {
        $customerId = intval($data['customer_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return DB::transaction(function () use ($companyId, $customerId, $amount, $data, $userName) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

            $paymentNumber = $data['payment_number'] ?? ('REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
            $receiptNumber = $data['receipt_number'] ?? ('RCP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
            $paymentMode = strtoupper($data['payment_mode'] ?? 'BANK_TRANSFER');
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $branchId = $data['branch_id'] ?? null;
            $bankAccountId = !empty($data['bank_account_id']) ? intval($data['bank_account_id']) : null;

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $data['financial_year'] ?? '2026-27',
                'payment_number' => $paymentNumber,
                'receipt_number' => $receiptNumber,
                'payment_type' => 'RECEIPT',
                'payment_date' => $paymentDate,
                'party_type' => 'CUSTOMER',
                'party_id' => $customer->id,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => 'INR',
                'payment_mode' => $paymentMode,
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $bankAccountId,
                'reference_number' => $data['reference_number'] ?? ($data['transaction_reference'] ?? null),
                'transaction_reference' => $data['reference_number'] ?? ($data['transaction_reference'] ?? null),
                'notes' => $data['notes'] ?? null,
                'status' => 'POSTED',
                'created_by' => $data['created_by'] ?? 1,
            ]);

            // 1. Process Invoices Allocation
            $allocations = $data['allocations'] ?? [];
            if (!empty($allocations)) {
                PaymentAllocationService::allocateCustomerPayment($payment, $allocations, $userName);
            } elseif (!empty($data['auto_allocate'])) {
                PaymentAllocationService::autoAllocate($payment, $userName);
            }

            $payment->refresh();

            // 2. Post Double-Entry Accounting
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            // 3. Record Bank Transaction if bank account linked
            if ($bankAccountId) {
                $bankAcc = BankAccount::where('company_id', $companyId)->find($bankAccountId);
                if ($bankAcc) {
                    $newBalance = round($bankAcc->current_balance + $amount, 2);
                    $bankAcc->update(['current_balance' => $newBalance]);

                    BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'bank_account_id' => $bankAccountId,
                        'transaction_date' => $paymentDate,
                        'type' => 'CREDIT',
                        'transaction_type' => 'PAYMENT_RECEIVED',
                        'reference_number' => $payment->reference_number ?: $payment->payment_number,
                        'description' => "Customer receipt from {$customer->name} (Receipt #{$payment->receipt_number})",
                        'amount' => $amount,
                        'debit_credit' => 'CREDIT',
                        'balance_after' => $newBalance,
                        'source' => 'CUSTOMER_PAYMENT',
                        'source_id' => $payment->id,
                        'is_reconciled' => false,
                        'reconciliation_status' => 'UNRECONCILED',
                    ]);
                }
            }

            return $payment->load(['allocations.invoice', 'customer', 'refunds']);
        });
    }

    /**
     * Record supplier payment.
     */
    public static function recordSupplierPayment(int $companyId, array $data, ?string $userName = 'System'): Payment
    {
        $supplierId = intval($data['supplier_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return DB::transaction(function () use ($companyId, $supplierId, $amount, $data, $userName) {
            $supplier = Supplier::where('company_id', $companyId)->findOrFail($supplierId);

            $paymentNumber = $data['payment_number'] ?? ('PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
            $paymentMode = strtoupper($data['payment_mode'] ?? 'BANK_TRANSFER');
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $branchId = $data['branch_id'] ?? null;
            $bankAccountId = !empty($data['bank_account_id']) ? intval($data['bank_account_id']) : null;

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $data['financial_year'] ?? '2026-27',
                'payment_number' => $paymentNumber,
                'payment_type' => 'PAYMENT',
                'payment_date' => $paymentDate,
                'party_type' => 'SUPPLIER',
                'party_id' => $supplier->id,
                'amount' => $amount,
                'allocated_amount' => $amount,
                'unallocated_amount' => 0.00,
                'currency' => 'INR',
                'payment_mode' => $paymentMode,
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $bankAccountId,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_reference' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'POSTED',
                'created_by' => $data['created_by'] ?? 1,
            ]);

            // Post Accounting
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            // Record Bank Transaction if bank account linked
            if ($bankAccountId) {
                $bankAcc = BankAccount::where('company_id', $companyId)->find($bankAccountId);
                if ($bankAcc) {
                    $newBalance = round($bankAcc->current_balance - $amount, 2);
                    $bankAcc->update(['current_balance' => $newBalance]);

                    BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'bank_account_id' => $bankAccountId,
                        'transaction_date' => $paymentDate,
                        'type' => 'DEBIT',
                        'transaction_type' => 'PAYMENT_MADE',
                        'reference_number' => $payment->reference_number ?: $payment->payment_number,
                        'description' => "Supplier payment to {$supplier->name} (Ref #{$payment->payment_number})",
                        'amount' => $amount,
                        'debit_credit' => 'DEBIT',
                        'balance_after' => $newBalance,
                        'source' => 'SUPPLIER_PAYMENT',
                        'source_id' => $payment->id,
                        'is_reconciled' => false,
                        'reconciliation_status' => 'UNRECONCILED',
                    ]);
                }
            }

            return $payment->load('supplier');
        });
    }

    /**
     * Record customer advance payment without invoice.
     */
    public static function recordCustomerAdvance(int $companyId, array $data, ?string $userName = 'System'): CustomerAdvance
    {
        $customerId = intval($data['customer_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Advance amount must be greater than zero.");
        }

        return DB::transaction(function () use ($companyId, $customerId, $amount, $data, $userName) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

            $payment = self::recordCustomerPayment($companyId, array_merge($data, [
                'customer_id' => $customerId,
                'amount' => $amount,
                'allocations' => [], // Unallocated advance
                'notes' => $data['notes'] ?? 'Customer Advance Payment',
            ]), $userName);

            $advance = CustomerAdvance::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'customer_id' => $customerId,
                'payment_id' => $payment->id,
                'advance_date' => $payment->payment_date,
                'total_amount' => $amount,
                'allocated_amount' => 0.00,
                'remaining_amount' => $amount,
                'status' => 'ACTIVE',
                'notes' => $payment->notes,
            ]);

            // Also record in customer credits
            CustomerCredit::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'customer_id' => $customerId,
                'credit_type' => 'ADVANCE',
                'source_id' => $advance->id,
                'amount' => $amount,
                'applied_amount' => 0.00,
                'remaining_amount' => $amount,
                'status' => 'ACTIVE',
            ]);

            return $advance->load('customer', 'payment');
        });
    }

    /**
     * Apply existing advance / credit to one or more invoices.
     */
    public static function applyCreditToInvoices(int $companyId, int $customerId, int $creditId, array $allocations, ?string $userName = 'System'): array
    {
        return DB::transaction(function () use ($companyId, $customerId, $creditId, $allocations, $userName) {
            $credit = CustomerCredit::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->findOrFail($creditId);

            if ($credit->remaining_amount <= 0.001 || $credit->status !== 'ACTIVE') {
                throw new RuntimeException("Credit #{$creditId} has zero remaining balance.");
            }

            $totalApplied = 0.0;
            $appliedInvoices = [];

            foreach ($allocations as $alloc) {
                $invId = intval($alloc['invoice_id']);
                $applyAmt = round(floatval($alloc['amount']), 2);
                if ($applyAmt <= 0) continue;

                $invoice = Invoice::where('company_id', $companyId)->lockForUpdate()->findOrFail($invId);
                $due = floatval($invoice->amount_due ?? ($invoice->grand_total - ($invoice->amount_paid ?? 0)));

                if ($applyAmt > ($due + 0.001)) {
                    throw new RuntimeException("Applied amount (₹{$applyAmt}) exceeds invoice #{$invoice->invoice_number} due balance (₹{$due}).");
                }
                if (($totalApplied + $applyAmt) > ($credit->remaining_amount + 0.001)) {
                    throw new RuntimeException("Total applied amount exceeds remaining credit balance (₹{$credit->remaining_amount}).");
                }

                $newPaid = round(($invoice->amount_paid ?? 0) + $applyAmt, 2);
                $newDue = max(0.00, round($invoice->grand_total - $newPaid, 2));
                $newStatus = ($newDue <= 0.001) ? 'PAID' : 'PARTIALLY_PAID';

                $invoice->update([
                    'amount_paid' => $newPaid,
                    'amount_due' => $newDue,
                    'status' => $newStatus,
                ]);

                $totalApplied += $applyAmt;
                $appliedInvoices[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount_applied' => $applyAmt,
                    'new_due' => $newDue,
                ];
            }

            $credit->applied_amount = round($credit->applied_amount + $totalApplied, 2);
            $credit->remaining_amount = max(0.00, round($credit->amount - $credit->applied_amount, 2));
            if ($credit->remaining_amount <= 0.001) {
                $credit->status = 'FULLY_APPLIED';
            }
            $credit->save();

            // Post Accounting: Dr Customer Advance Liability, Cr Accounts Receivable
            $advAcc = AccountService::getMappedAccount($companyId, 'CUSTOMER_ADVANCE');
            $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');

            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => date('Y-m-d'),
                'entry_type' => 'MANUAL',
                'status' => 'POSTED',
                'description' => "Application of Customer Credit #{$credit->id} to Invoices",
                'narration' => "Credit application for Customer #{$customerId}",
                'lines' => [
                    [
                        'account_id' => $advAcc->id,
                        'debit' => $totalApplied,
                        'credit' => 0.00,
                        'narration' => 'Reduction in Customer Advance Liability',
                        'party_type' => 'CUSTOMER',
                        'party_id' => $customerId,
                    ],
                    [
                        'account_id' => $recAcc->id,
                        'debit' => 0.00,
                        'credit' => $totalApplied,
                        'narration' => 'Credit applied to Accounts Receivable',
                        'party_type' => 'CUSTOMER',
                        'party_id' => $customerId,
                    ],
                ],
            ], $userName);

            return [
                'applied_total' => $totalApplied,
                'remaining_credit' => $credit->remaining_amount,
                'invoices' => $appliedInvoices,
            ];
        });
    }

    /**
     * Get payments overview & liquid funds summary.
     */
    public static function getPaymentsOverview(int $companyId): array
    {
        $today = date('Y-m-d');

        $receivedToday = Payment::where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $paidToday = Payment::where('company_id', $companyId)
            ->where('payment_type', 'PAYMENT')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $unallocatedTotal = Payment::where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->sum('unallocated_amount');

        $bankAccounts = BankAccount::where('company_id', $companyId)->where('is_active', true)->get();
        $totalBankFunds = $bankAccounts->sum('current_balance');

        return [
            'received_today' => floatval($receivedToday),
            'paid_today' => floatval($paidToday),
            'unallocated_total' => floatval($unallocatedTotal),
            'total_bank_funds' => floatval($totalBankFunds),
            'bank_accounts' => $bankAccounts,
        ];
    }
}
