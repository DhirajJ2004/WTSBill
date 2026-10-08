<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\PaymentRepository;
use App\Validators\PaymentValidator;
use App\Services\AccountingEventService;
use App\Services\AccountService;
use App\Services\AuditLogService;
use App\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class PaymentService
{
    /**
     * Atomically record a Customer Receipt (Decreases AR, Increases Cash/Bank).
     */
    public static function recordCustomerReceipt(
        array $input,
        int $companyId,
        ?int $branchId = null,
        mixed $authUser = 'Admin'
    ): array {
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');
        $userId = (is_object($authUser) && isset($authUser->id)) ? (int)$authUser->id : null;

        $input['payment_type'] = 'RECEIPT';
        $input['party_type'] = 'CUSTOMER';

        $errors = PaymentValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
        if (!$company) {
            return ['success' => false, 'message' => 'Active company context is invalid.'];
        }

        if (!$branchId) {
            $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch ? $branch->id : 1;
        }

        $customerId = isset($input['party_id']) ? (int)$input['party_id'] : (int)($input['customer_id'] ?? 0);
        $customer = Customer::withoutGlobalScopes()
            ->where('id', $customerId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            return ['success' => false, 'message' => 'Selected customer is invalid or unauthorized.'];
        }

        $amount = round(floatval($input['amount']), 2);
        $paymentDate = !empty($input['payment_date']) ? substr(trim($input['payment_date']), 0, 10) : date('Y-m-d');
        $paymentMode = strtoupper($input['payment_mode'] ?? 'CASH');

        return DB::transaction(function () use (
            $companyId, $branchId, $customer, $amount, $paymentDate, $paymentMode,
            $input, $userName, $userId
        ) {
            $paymentNumber = PaymentRepository::generateNextPaymentNumber($companyId, 'RECEIPT');
            $receiptNumber = $input['receipt_number'] ?? $paymentNumber;

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'receipt_number' => $receiptNumber,
                'payment_type' => 'RECEIPT',
                'party_type' => 'CUSTOMER',
                'party_id' => $customer->id,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => 'INR',
                'payment_mode' => $paymentMode,
                'payment_date' => $paymentDate,
                'financial_year' => $input['financial_year'] ?? '2026-27',
                'reference_number' => $input['reference_number'] ?? null,
                'transaction_reference' => $input['transaction_reference'] ?? ($input['utr'] ?? null),
                'bank_reference' => $input['bank_reference'] ?? null,
                'utr' => $input['utr'] ?? null,
                'cheque_number' => $input['cheque_number'] ?? null,
                'cheque_date' => !empty($input['cheque_date']) ? substr(trim($input['cheque_date']), 0, 10) : null,
                'cheque_bank' => $input['cheque_bank'] ?? null,
                'cheque_status' => ($paymentMode === 'CHEQUE') ? ($input['cheque_status'] ?? 'RECEIVED') : 'CLEARED',
                'status' => 'POSTED',
                'notes' => $input['notes'] ?? '',
                'created_by' => $userName ?: 'Admin',
            ]);

            // Optional invoice allocation
            if (!empty($input['invoice_id'])) {
                $invoice = Invoice::withoutGlobalScopes()
                    ->where('id', (int)$input['invoice_id'])
                    ->where('company_id', $companyId)
                    ->first();

                if ($invoice) {
                    $allocAmt = min($amount, (float)$invoice->amount_due);
                    if ($allocAmt > 0) {
                        PaymentAllocation::create([
                            'company_id' => $companyId,
                            'payment_id' => $payment->id,
                            'document_type' => 'INVOICE',
                            'document_id' => $invoice->id,
                            'allocated_amount' => $allocAmt,
                            'allocated_date' => $paymentDate,
                        ]);

                        $newDue = max(0.0, round((float)$invoice->amount_due - $allocAmt, 2));
                        $newPaid = round((float)$invoice->amount_paid + $allocAmt, 2);
                        $invoice->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'payment_status' => ($newDue <= 0) ? 'PAID' : 'PARTIALLY_PAID',
                        ]);

                        $payment->update([
                            'allocated_amount' => $allocAmt,
                            'unallocated_amount' => max(0.0, round($amount - $allocAmt, 2)),
                        ]);
                    }
                }
            }

            // Customer Accounts Receivable balance adjustment
            $newCustBalance = max(0.0, round((float)$customer->current_balance - $amount, 2));
            $customer->update(['current_balance' => $newCustBalance]);

            // Double-Entry Accounting Journal
            AccountService::ensureDefaultAccounts($companyId);
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            AuditLogService::log(
                $companyId,
                $userName,
                'RECEIPT_CREATE',
                'Payment',
                $payment->id,
                "Recorded Customer Receipt #{$paymentNumber} of ₹{$amount} from Customer: {$customer->name}"
            );

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_number' => $paymentNumber,
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'payment' => $payment->load(['customer']),
                'message' => "Receipt #{$paymentNumber} recorded successfully."
            ];
        });
    }

    /**
     * Atomically record a Supplier Payment (Decreases AP, Decreases Cash/Bank).
     */
    public static function recordSupplierPayment(
        array $input,
        int $companyId,
        ?int $branchId = null,
        mixed $authUser = 'Admin'
    ): array {
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');
        $userId = (is_object($authUser) && isset($authUser->id)) ? (int)$authUser->id : null;

        $input['payment_type'] = 'PAYMENT';
        $input['party_type'] = 'SUPPLIER';

        $errors = PaymentValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
        if (!$company) {
            return ['success' => false, 'message' => 'Active company context is invalid.'];
        }

        if (!$branchId) {
            $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch ? $branch->id : 1;
        }

        $supplierId = isset($input['party_id']) ? (int)$input['party_id'] : (int)($input['supplier_id'] ?? 0);
        $supplier = Supplier::withoutGlobalScopes()
            ->where('id', $supplierId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$supplier) {
            return ['success' => false, 'message' => 'Selected supplier is invalid or unauthorized.'];
        }

        $amount = round(floatval($input['amount']), 2);
        $paymentDate = !empty($input['payment_date']) ? substr(trim($input['payment_date']), 0, 10) : date('Y-m-d');
        $paymentMode = strtoupper($input['payment_mode'] ?? 'BANK_TRANSFER');

        return DB::transaction(function () use (
            $companyId, $branchId, $supplier, $amount, $paymentDate, $paymentMode,
            $input, $userName, $userId
        ) {
            $paymentNumber = PaymentRepository::generateNextPaymentNumber($companyId, 'PAYMENT');

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'payment_type' => 'PAYMENT',
                'party_type' => 'SUPPLIER',
                'party_id' => $supplier->id,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => 'INR',
                'payment_mode' => $paymentMode,
                'payment_date' => $paymentDate,
                'financial_year' => $input['financial_year'] ?? '2026-27',
                'reference_number' => $input['reference_number'] ?? null,
                'transaction_reference' => $input['transaction_reference'] ?? ($input['utr'] ?? null),
                'bank_reference' => $input['bank_reference'] ?? null,
                'utr' => $input['utr'] ?? null,
                'cheque_number' => $input['cheque_number'] ?? null,
                'cheque_date' => !empty($input['cheque_date']) ? substr(trim($input['cheque_date']), 0, 10) : null,
                'cheque_bank' => $input['cheque_bank'] ?? null,
                'cheque_status' => ($paymentMode === 'CHEQUE') ? ($input['cheque_status'] ?? 'DEPOSITED') : 'CLEARED',
                'status' => 'POSTED',
                'notes' => $input['notes'] ?? '',
                'created_by' => $userName ?: 'Admin',
            ]);

            // Optional purchase bill allocation
            if (!empty($input['purchase_id'])) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->where('id', (int)$input['purchase_id'])
                    ->where('company_id', $companyId)
                    ->first();

                if ($purchase) {
                    $allocAmt = min($amount, (float)$purchase->amount_due);
                    if ($allocAmt > 0) {
                        PaymentAllocation::create([
                            'company_id' => $companyId,
                            'payment_id' => $payment->id,
                            'document_type' => 'PURCHASE',
                            'document_id' => $purchase->id,
                            'allocated_amount' => $allocAmt,
                            'allocated_date' => $paymentDate,
                        ]);

                        $newDue = max(0.0, round((float)$purchase->amount_due - $allocAmt, 2));
                        $newPaid = round((float)$purchase->amount_paid + $allocAmt, 2);
                        $purchase->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'payment_status' => ($newDue <= 0) ? 'PAID' : 'PARTIALLY_PAID',
                        ]);

                        $payment->update([
                            'allocated_amount' => $allocAmt,
                            'unallocated_amount' => max(0.0, round($amount - $allocAmt, 2)),
                        ]);
                    }
                }
            }

            // Supplier Accounts Payable balance adjustment
            $newSuppBalance = max(0.0, round((float)$supplier->current_balance - $amount, 2));
            $supplier->update(['current_balance' => $newSuppBalance]);

            // Double-Entry Accounting Journal
            AccountService::ensureDefaultAccounts($companyId);
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            AuditLogService::log(
                $companyId,
                $userName,
                'PAYMENT_CREATE',
                'Payment',
                $payment->id,
                "Disbursed Supplier Payment #{$paymentNumber} of ₹{$amount} to Supplier: {$supplier->name}"
            );

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_number' => $paymentNumber,
                'amount' => $amount,
                'payment' => $payment->load(['supplier']),
                'message' => "Payment #{$paymentNumber} recorded successfully."
            ];
        });
    }

    /**
     * Void a Payment / Receipt (Reverses party balance and accounting journal).
     */
    public static function voidPayment(int $id, int $companyId, string $userName = 'Admin', string $reason = 'Cancelled'): array
    {
        return DB::transaction(function () use ($id, $companyId, $userName, $reason) {
            $payment = Payment::withoutGlobalScopes()
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return ['success' => false, 'message' => "Payment #{$id} not found or unauthorized."];
            }

            if ($payment->status === 'CANCELLED') {
                return ['success' => false, 'message' => "Payment #{$payment->payment_number} is already cancelled."];
            }

            // Reverse party balance
            $amount = (float)$payment->amount;
            if ($payment->party_type === 'CUSTOMER') {
                $customer = Customer::withoutGlobalScopes()->where('id', $payment->party_id)->where('company_id', $companyId)->first();
                if ($customer) {
                    $customer->update(['current_balance' => round((float)$customer->current_balance + $amount, 2)]);
                }
            } else {
                $supplier = Supplier::withoutGlobalScopes()->where('id', $payment->party_id)->where('company_id', $companyId)->first();
                if ($supplier) {
                    $supplier->update(['current_balance' => round((float)$supplier->current_balance + $amount, 2)]);
                }
            }

            // Reverse invoice allocations if any
            $allocations = PaymentAllocation::where('payment_id', $payment->id)->where('company_id', $companyId)->get();
            foreach ($allocations as $alloc) {
                if ($alloc->document_type === 'INVOICE') {
                    $invoice = Invoice::withoutGlobalScopes()->where('id', $alloc->document_id)->where('company_id', $companyId)->first();
                    if ($invoice) {
                        $newPaid = max(0.0, round((float)$invoice->amount_paid - (float)$alloc->allocated_amount, 2));
                        $newDue = round((float)$invoice->grand_total - $newPaid, 2);
                        $invoice->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'payment_status' => ($newPaid <= 0) ? 'UNPAID' : 'PARTIALLY_PAID',
                        ]);
                    }
                } elseif ($alloc->document_type === 'PURCHASE') {
                    $purchase = Purchase::withoutGlobalScopes()->where('id', $alloc->document_id)->where('company_id', $companyId)->first();
                    if ($purchase) {
                        $newPaid = max(0.0, round((float)$purchase->amount_paid - (float)$alloc->allocated_amount, 2));
                        $newDue = round((float)$purchase->grand_total - $newPaid, 2);
                        $purchase->update([
                            'amount_paid' => $newPaid,
                            'amount_due' => $newDue,
                            'payment_status' => ($newPaid <= 0) ? 'UNPAID' : 'PARTIALLY_PAID',
                        ]);
                    }
                }
                $alloc->delete();
            }

            // Cancel Accounting Journal Entry
            try {
                $origJournal = \App\Models\JournalEntry::where('company_id', $companyId)
                    ->where('reference_type', 'PAYMENT')
                    ->where('reference_id', (string)$payment->id)
                    ->where('status', 'POSTED')
                    ->first();
                if ($origJournal) {
                    $origJournal->update(['status' => 'CANCELLED']);
                }
            } catch (\Throwable $e) {}

            $payment->update([
                'status' => 'CANCELLED',
                'notes' => trim($payment->notes . " [VOIDED: {$reason}]"),
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'PAYMENT_VOID',
                'Payment',
                $id,
                "Voided Payment #{$payment->payment_number}. Reason: {$reason}"
            );

            return ['success' => true, 'message' => "Payment #{$payment->payment_number} voided successfully."];
        });
    }
}
