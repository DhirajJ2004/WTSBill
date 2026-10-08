<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class PaymentService
{
    /**
     * Create a new Payment or Receipt record with atomic allocation option.
     */
    public static function createPayment(array $data, ?string $createdByName = 'Admin'): Payment
    {
        return DB::transaction(function () use ($data, $createdByName) {
            $companyId = intval($data['company_id']);
            $branchId = !empty($data['branch_id']) ? intval($data['branch_id']) : null;
            $fy = $data['financial_year'] ?? '2026-27';
            $paymentType = strtoupper(trim($data['payment_type'] ?? 'RECEIPT')); // RECEIPT, PAYMENT
            $partyType = strtoupper(trim($data['party_type'] ?? ($paymentType === 'RECEIPT' ? 'CUSTOMER' : 'SUPPLIER')));
            $partyId = intval($data['party_id']);
            $amount = round(floatval($data['amount']), 2);

            if ($amount <= 0) {
                throw new \InvalidArgumentException("Payment amount must be greater than zero. Provided: {$amount}");
            }

            // Verify party belongs to business
            if ($partyType === 'CUSTOMER') {
                Customer::where('company_id', $companyId)->findOrFail($partyId);
            } else {
                Supplier::where('company_id', $companyId)->findOrFail($partyId);
            }

            // Generate sequential unique numbering
            $numberDocType = ($paymentType === 'RECEIPT') ? 'RECEIPT' : 'SUPPLIER_PAYMENT';
            $paymentNumber = $data['payment_number'] ?? DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, $numberDocType);

            $status = strtoupper($data['status'] ?? 'POSTED'); // DRAFT or POSTED
            $paymentMode = strtoupper($data['payment_mode'] ?? 'CASH');

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'payment_number' => $paymentNumber,
                'payment_type' => $paymentType,
                'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
                'party_type' => $partyType,
                'party_id' => $partyId,
                'amount' => $amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => $data['currency'] ?? 'INR',
                'payment_mode' => $paymentMode,
                'account_id' => !empty($data['account_id']) ? intval($data['account_id']) : null,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'utr' => $data['utr'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'cheque_bank' => $data['cheque_bank'] ?? null,
                'cheque_status' => $data['cheque_status'] ?? ($paymentMode === 'CHEQUE' ? 'RECEIVED' : 'CLEARED'),
                'notes' => $data['notes'] ?? '',
                'status' => $status,
                'created_by' => $createdByName,
            ]);

            // If POSTED, process allocations if supplied or if auto_allocate requested
            if ($status === 'POSTED') {
                if (!empty($data['allocations']) && is_array($data['allocations'])) {
                    PaymentAllocationService::allocatePayment($payment, $data['allocations'], $createdByName);
                } elseif (!empty($data['auto_allocate'])) {
                    PaymentAllocationService::autoAllocateFIFO($payment, $createdByName);
                }

                // Chunk 7: Double-Entry Accounting integration
                AccountingEventService::recordPaymentAccounting($payment, $createdByName);
            }

            AuditLogService::log(
                $companyId,
                $createdByName,
                'PAYMENT_CREATE',
                'Payment',
                $payment->id,
                "Created {$paymentType} #{$payment->payment_number} of ₹{$amount} ({$paymentMode}) for {$partyType} #{$partyId} [Status: {$status}]"
            );

            return $payment->fresh(['allocations', 'customer', 'supplier']);
        });
    }

    /**
     * Post a draft payment.
     */
    public static function postPayment(int $paymentId, ?string $postedByName = 'Admin'): Payment
    {
        return DB::transaction(function () use ($paymentId, $postedByName) {
            $payment = Payment::findOrFail($paymentId);

            if ($payment->status === 'POSTED') {
                return $payment;
            }
            if ($payment->status === 'CANCELLED') {
                throw new \InvalidArgumentException("Cannot post a cancelled payment #{$payment->payment_number}.");
            }

            $payment->update([
                'status' => 'POSTED',
                'updated_by' => $postedByName,
            ]);

            // Chunk 7: Double-Entry Accounting integration
            AccountingEventService::recordPaymentAccounting($payment, $postedByName);

            AuditLogService::log(
                $payment->company_id,
                $postedByName,
                'PAYMENT_POST',
                'Payment',
                $payment->id,
                "Posted Payment #{$payment->payment_number}"
            );

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Cancel/Reverse a posted or draft payment.
     */
    public static function cancelPayment(int $paymentId, string $reason = 'Cancelled by user', ?string $cancelledByName = 'Admin'): Payment
    {
        return DB::transaction(function () use ($paymentId, $reason, $cancelledByName) {
            $payment = Payment::findOrFail($paymentId);

            if ($payment->status === 'CANCELLED') {
                return $payment;
            }

            // Reverse all allocations and restore invoice/bill balances
            PaymentAllocationService::reverseAllocations($payment);

            $payment->update([
                'status' => 'CANCELLED',
                'notes' => trim($payment->notes . " | Cancelled: {$reason}"),
                'updated_by' => $cancelledByName,
            ]);

            AuditLogService::log(
                $payment->company_id,
                $cancelledByName,
                'PAYMENT_CANCEL',
                'Payment',
                $payment->id,
                "Cancelled Payment #{$payment->payment_number}: Reason - {$reason}"
            );

            return $payment->fresh();
        });
    }

    /**
     * Handle Cheque Bounce workflow (reverses allocations, marks cheque as BOUNCED, restores balances).
     */
    public static function bounceCheque(int $paymentId, string $reason = 'Cheque returned unpaid by bank', ?string $userName = 'Admin'): Payment
    {
        return DB::transaction(function () use ($paymentId, $reason, $userName) {
            $payment = Payment::findOrFail($paymentId);

            if ($payment->payment_mode !== 'CHEQUE') {
                throw new \InvalidArgumentException("Payment #{$payment->payment_number} is not a cheque transaction.");
            }

            // Reverse existing allocations so invoices/bills become outstanding again
            PaymentAllocationService::reverseAllocations($payment);

            $payment->update([
                'cheque_status' => 'BOUNCED',
                'notes' => trim($payment->notes . " | Cheque Bounced: {$reason}"),
                'updated_by' => $userName,
            ]);

            AuditLogService::log(
                $payment->company_id,
                $userName,
                'CHEQUE_BOUNCE',
                'Payment',
                $payment->id,
                "Marked Cheque #{$payment->cheque_number} on Payment #{$payment->payment_number} as BOUNCED: {$reason}"
            );

            return $payment->fresh();
        });
    }

    /**
     * Generate Customer Statement with running balance.
     */
    public static function getCustomerStatement(int $companyId, int $customerId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');

        // 1. Calculate opening balance before $fromDate
        $priorInvoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->where('invoice_date', '<', $fromDate)
            ->sum('grand_total');

        $priorReceipts = Payment::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('party_id', $customerId)
            ->where('payment_type', 'RECEIPT')
            ->where('status', 'POSTED')
            ->where('payment_date', '<', $fromDate)
            ->sum('amount');

        $priorCreditNotes = CreditNote::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->where('credit_note_date', '<', $fromDate)
            ->sum('amount');

        $priorRefunds = PaymentRefund::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('party_id', $customerId)
            ->where('status', 'POSTED')
            ->where('refund_date', '<', $fromDate)
            ->sum('amount');

        $openingBalance = round(($priorInvoices + $priorRefunds) - ($priorReceipts + $priorCreditNotes), 2);

        // 2. Fetch period transactions
        $entries = [];

        // Invoices
        $invoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->get();

        foreach ($invoices as $inv) {
            $entries[] = [
                'date' => $inv->invoice_date,
                'type' => 'INVOICE',
                'reference' => $inv->invoice_number,
                'description' => "Tax Invoice #{$inv->invoice_number}",
                'debit' => floatval($inv->grand_total),
                'credit' => 0.00,
                'created_at' => $inv->created_at,
            ];
        }

        // Receipts
        $receipts = Payment::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('party_id', $customerId)
            ->where('payment_type', 'RECEIPT')
            ->where('status', 'POSTED')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->get();

        foreach ($receipts as $rec) {
            $entries[] = [
                'date' => $rec->payment_date,
                'type' => 'RECEIPT',
                'reference' => $rec->payment_number,
                'description' => "Payment Received ({$rec->payment_mode}) - Ref: " . ($rec->transaction_reference ?: $rec->reference_number ?: 'N/A'),
                'debit' => 0.00,
                'credit' => floatval($rec->amount),
                'created_at' => $rec->created_at,
            ];
        }

        // Credit Notes
        $creditNotes = CreditNote::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('credit_note_date', [$fromDate, $toDate])
            ->get();

        foreach ($creditNotes as $cn) {
            $entries[] = [
                'date' => $cn->credit_note_date,
                'type' => 'CREDIT_NOTE',
                'reference' => $cn->credit_note_number,
                'description' => "Credit Note #{$cn->credit_note_number}: {$cn->reason}",
                'debit' => 0.00,
                'credit' => floatval($cn->amount),
                'created_at' => $cn->created_at,
            ];
        }

        // Refunds
        $refunds = PaymentRefund::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('party_id', $customerId)
            ->where('status', 'POSTED')
            ->whereBetween('refund_date', [$fromDate, $toDate])
            ->get();

        foreach ($refunds as $rf) {
            $entries[] = [
                'date' => $rf->refund_date,
                'type' => 'REFUND',
                'reference' => $rf->refund_number,
                'description' => "Refund Issued ({$rf->refund_mode}) - {$rf->reason}",
                'debit' => floatval($rf->amount),
                'credit' => 0.00,
                'created_at' => $rf->created_at,
            ];
        }

        // Sort chronologically
        usort($entries, function ($a, $b) {
            $dateCmp = strcmp($a['date'], $b['date']);
            if ($dateCmp !== 0) return $dateCmp;
            return strcmp((string)$a['created_at'], (string)$b['created_at']);
        });

        // Compute running balances
        $runningBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entries as &$row) {
            $runningBalance = round($runningBalance + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $runningBalance;
            $totalDebit += $row['debit'];
            $totalCredit += $row['credit'];
        }

        return [
            'customer' => $customer,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'transactions' => $entries,
        ];
    }

    /**
     * Generate Supplier Statement with running balance.
     */
    public static function getSupplierStatement(int $companyId, int $supplierId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $supplier = Supplier::where('company_id', $companyId)->findOrFail($supplierId);

        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');

        // Opening balance prior to $fromDate (Credit = Payable to Supplier)
        $priorPurchases = Purchase::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->where('purchase_date', '<', $fromDate)
            ->sum('grand_total');

        $priorPayments = Payment::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('party_id', $supplierId)
            ->where('payment_type', 'PAYMENT')
            ->where('status', 'POSTED')
            ->where('payment_date', '<', $fromDate)
            ->sum('amount');

        $priorDebitNotes = DebitNote::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'CANCELLED')
            ->where('debit_note_date', '<', $fromDate)
            ->sum('amount');

        $priorRefunds = PaymentRefund::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('party_id', $supplierId)
            ->where('status', 'POSTED')
            ->where('refund_date', '<', $fromDate)
            ->sum('amount');

        $openingBalance = round(($priorPurchases + $priorRefunds) - ($priorPayments + $priorDebitNotes), 2);

        $entries = [];

        // Purchases
        $purchases = Purchase::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->whereBetween('purchase_date', [$fromDate, $toDate])
            ->get();

        foreach ($purchases as $pur) {
            $entries[] = [
                'date' => $pur->purchase_date,
                'type' => 'PURCHASE',
                'reference' => $pur->purchase_number,
                'description' => "Purchase Bill #{$pur->purchase_number} (Vendor Inv: {$pur->vendor_invoice_number})",
                'debit' => 0.00,
                'credit' => floatval($pur->grand_total),
                'created_at' => $pur->created_at,
            ];
        }

        // Payments Made
        $payments = Payment::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('party_id', $supplierId)
            ->where('payment_type', 'PAYMENT')
            ->where('status', 'POSTED')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->get();

        foreach ($payments as $pm) {
            $entries[] = [
                'date' => $pm->payment_date,
                'type' => 'PAYMENT',
                'reference' => $pm->payment_number,
                'description' => "Supplier Payment ({$pm->payment_mode}) - Ref: " . ($pm->transaction_reference ?: $pm->reference_number ?: 'N/A'),
                'debit' => floatval($pm->amount),
                'credit' => 0.00,
                'created_at' => $pm->created_at,
            ];
        }

        // Debit Notes
        $debitNotes = DebitNote::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('debit_note_date', [$fromDate, $toDate])
            ->get();

        foreach ($debitNotes as $dn) {
            $entries[] = [
                'date' => $dn->debit_note_date,
                'type' => 'DEBIT_NOTE',
                'reference' => $dn->debit_note_number,
                'description' => "Debit Note #{$dn->debit_note_number}: {$dn->reason}",
                'debit' => floatval($dn->amount),
                'credit' => 0.00,
                'created_at' => $dn->created_at,
            ];
        }

        // Refunds from supplier
        $refunds = PaymentRefund::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('party_id', $supplierId)
            ->where('status', 'POSTED')
            ->whereBetween('refund_date', [$fromDate, $toDate])
            ->get();

        foreach ($refunds as $rf) {
            $entries[] = [
                'date' => $rf->refund_date,
                'type' => 'REFUND',
                'reference' => $rf->refund_number,
                'description' => "Supplier Refund Received ({$rf->refund_mode}) - {$rf->reason}",
                'debit' => 0.00,
                'credit' => floatval($rf->amount),
                'created_at' => $rf->created_at,
            ];
        }

        usort($entries, function ($a, $b) {
            $dateCmp = strcmp($a['date'], $b['date']);
            if ($dateCmp !== 0) return $dateCmp;
            return strcmp((string)$a['created_at'], (string)$b['created_at']);
        });

        $runningBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entries as &$row) {
            $runningBalance = round($runningBalance + $row['credit'] - $row['debit'], 2);
            $row['balance'] = $runningBalance;
            $totalDebit += $row['debit'];
            $totalCredit += $row['credit'];
        }

        return [
            'supplier' => $supplier,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'transactions' => $entries,
        ];
    }

    /**
     * Dashboard Summary KPIs derived from real transaction data.
     */
    public static function getDashboardSummary(int $companyId, ?int $branchId = null): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        // Customer side
        $invoicesQuery = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT');

        if ($branchId) {
            $invoicesQuery->where('branch_id', $branchId);
        }

        $allInvoices = $invoicesQuery->get();
        $totalReceivable = 0.0;
        $overdueReceivable = 0.0;
        $dueSoonReceivable = 0.0;
        $sevenDaysAhead = date('Y-m-d', strtotime('+7 days'));

        foreach ($allInvoices as $inv) {
            $due = floatval($inv->amount_due);
            if ($due > 0.001) {
                $totalReceivable += $due;
                if ($inv->due_date && $inv->due_date < $today) {
                    $overdueReceivable += $due;
                } elseif ($inv->due_date && $inv->due_date <= $sevenDaysAhead) {
                    $dueSoonReceivable += $due;
                }
            }
        }

        $receiptsToday = Payment::where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->where('status', 'POSTED')
            ->where('payment_date', $today)
            ->sum('amount');

        $receiptsMonth = Payment::where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->where('status', 'POSTED')
            ->whereBetween('payment_date', [$monthStart, $today])
            ->sum('amount');

        // Supplier side
        $purchasesQuery = Purchase::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT');

        if ($branchId) {
            $purchasesQuery->where('branch_id', $branchId);
        }

        $allPurchases = $purchasesQuery->get();
        $totalPayable = 0.0;
        $overduePayable = 0.0;
        $dueSoonPayable = 0.0;

        foreach ($allPurchases as $pur) {
            $due = floatval($pur->amount_due);
            if ($due > 0.001) {
                $totalPayable += $due;
                if ($pur->due_date && $pur->due_date < $today) {
                    $overduePayable += $due;
                } elseif ($pur->due_date && $pur->due_date <= $sevenDaysAhead) {
                    $dueSoonPayable += $due;
                }
            }
        }

        $paidToday = Payment::where('company_id', $companyId)
            ->where('payment_type', 'PAYMENT')
            ->where('status', 'POSTED')
            ->where('payment_date', $today)
            ->sum('amount');

        $paidMonth = Payment::where('company_id', $companyId)
            ->where('payment_type', 'PAYMENT')
            ->where('status', 'POSTED')
            ->whereBetween('payment_date', [$monthStart, $today])
            ->sum('amount');

        return [
            'receivables' => [
                'total_receivable' => round($totalReceivable, 2),
                'overdue' => round($overdueReceivable, 2),
                'due_soon' => round($dueSoonReceivable, 2),
                'received_today' => round(floatval($receiptsToday), 2),
                'received_this_month' => round(floatval($receiptsMonth), 2),
            ],
            'payables' => [
                'total_payable' => round($totalPayable, 2),
                'overdue' => round($overduePayable, 2),
                'due_soon' => round($dueSoonPayable, 2),
                'paid_today' => round(floatval($paidToday), 2),
                'paid_this_month' => round(floatval($paidMonth), 2),
            ],
        ];
    }
}
