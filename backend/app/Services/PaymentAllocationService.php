<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use Illuminate\Database\Capsule\Manager as DB;

class PaymentAllocationService
{
    /**
     * Allocate payment amount to specific documents with strict business and party validation.
     *
     * @param Payment $payment
     * @param array $allocations Array of ['document_type' => 'INVOICE|PURCHASE_INVOICE', 'document_id' => int, 'amount' => float]
     * @param string|null $createdByName
     * @return array
     */
    public static function allocatePayment(Payment $payment, array $allocations, ?string $createdByName = 'System'): array
    {
        return DB::transaction(function () use ($payment, $allocations, $createdByName) {
            $payment->refresh();

            if ($payment->status === 'CANCELLED') {
                throw new \InvalidArgumentException("Cannot allocate cancelled payment #{$payment->payment_number}.");
            }

            $currentAllocated = floatval(PaymentAllocation::where('payment_id', $payment->id)->sum('allocated_amount'));
            $availableBudget = round(floatval($payment->amount) - $currentAllocated, 2);

            $requestedTotal = 0.0;
            foreach ($allocations as $alloc) {
                $requestedTotal += floatval($alloc['amount'] ?? 0);
            }
            $requestedTotal = round($requestedTotal, 2);

            if ($requestedTotal > $availableBudget + 0.001) {
                throw new \InvalidArgumentException(
                    "Total requested allocation (₹{$requestedTotal}) exceeds available payment balance (₹{$availableBudget})."
                );
            }

            $createdAllocations = [];

            foreach ($allocations as $alloc) {
                $amount = round(floatval($alloc['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                $docType = strtoupper(trim($alloc['document_type'] ?? 'INVOICE'));
                $docId = intval($alloc['document_id'] ?? 0);

                if ($docType === 'INVOICE' || $docType === 'SALES_INVOICE') {
                    $invoice = Invoice::where('company_id', $payment->company_id)->findOrFail($docId);

                    if ($payment->party_type !== 'CUSTOMER' || intval($invoice->customer_id) !== intval($payment->party_id)) {
                        throw new \InvalidArgumentException("Cross-party allocation prohibited: Invoice #{$invoice->invoice_number} does not belong to Customer #{$payment->party_id}.");
                    }

                    if ($invoice->status === 'CANCELLED' || $invoice->status === 'DRAFT') {
                        throw new \InvalidArgumentException("Cannot allocate payment to {$invoice->status} Invoice #{$invoice->invoice_number}.");
                    }

                    // Calculate true outstanding due for invoice
                    $totalAllocatedSoFar = floatval(
                        PaymentAllocation::where('company_id', $payment->company_id)
                            ->where('document_type', 'INVOICE')
                            ->where('document_id', $invoice->id)
                            ->sum('allocated_amount')
                    );

                    $creditNotesTotal = floatval(
                        CreditNote::where('company_id', $payment->company_id)
                            ->where('invoice_id', $invoice->id)
                            ->where('status', '!=', 'CANCELLED')
                            ->sum('amount')
                    );

                    $outstanding = max(0, round(floatval($invoice->grand_total) - $totalAllocatedSoFar - $creditNotesTotal, 2));

                    if ($amount > $outstanding + 0.001) {
                        throw new \InvalidArgumentException(
                            "Allocated amount (₹{$amount}) exceeds Invoice #{$invoice->invoice_number} outstanding balance (₹{$outstanding})."
                        );
                    }

                    $pa = PaymentAllocation::create([
                        'company_id' => $payment->company_id,
                        'branch_id' => $payment->branch_id,
                        'payment_id' => $payment->id,
                        'document_type' => 'INVOICE',
                        'document_id' => $invoice->id,
                        'allocated_amount' => $amount,
                        'allocation_date' => $payment->payment_date ?: date('Y-m-d'),
                        'notes' => $alloc['notes'] ?? "Allocated from Payment #{$payment->payment_number}",
                        'created_by' => $createdByName,
                    ]);

                    // Sync invoice status and paid/due balances
                    static::syncInvoiceBalance($invoice);
                    $createdAllocations[] = $pa;

                } elseif ($docType === 'PURCHASE_INVOICE' || $docType === 'PURCHASE') {
                    $purchase = Purchase::where('company_id', $payment->company_id)->findOrFail($docId);

                    if ($payment->party_type !== 'SUPPLIER' || intval($purchase->supplier_id) !== intval($payment->party_id)) {
                        throw new \InvalidArgumentException("Cross-party allocation prohibited: Purchase Bill #{$purchase->purchase_number} does not belong to Supplier #{$payment->party_id}.");
                    }

                    if ($purchase->status === 'CANCELLED' || $purchase->status === 'DRAFT') {
                        throw new \InvalidArgumentException("Cannot allocate payment to {$purchase->status} Purchase Bill #{$purchase->purchase_number}.");
                    }

                    $totalAllocatedSoFar = floatval(
                        PaymentAllocation::where('company_id', $payment->company_id)
                            ->where('document_type', 'PURCHASE_INVOICE')
                            ->where('document_id', $purchase->id)
                            ->sum('allocated_amount')
                    );

                    $debitNotesTotal = floatval(
                        DebitNote::where('company_id', $payment->company_id)
                            ->where('purchase_id', $purchase->id)
                            ->where('status', '!=', 'CANCELLED')
                            ->sum('amount')
                    );

                    $outstanding = max(0, round(floatval($purchase->grand_total) - $totalAllocatedSoFar - $debitNotesTotal, 2));

                    if ($amount > $outstanding + 0.001) {
                        throw new \InvalidArgumentException(
                            "Allocated amount (₹{$amount}) exceeds Purchase Bill #{$purchase->purchase_number} outstanding balance (₹{$outstanding})."
                        );
                    }

                    $pa = PaymentAllocation::create([
                        'company_id' => $payment->company_id,
                        'branch_id' => $payment->branch_id,
                        'payment_id' => $payment->id,
                        'document_type' => 'PURCHASE_INVOICE',
                        'document_id' => $purchase->id,
                        'allocated_amount' => $amount,
                        'allocation_date' => $payment->payment_date ?: date('Y-m-d'),
                        'notes' => $alloc['notes'] ?? "Allocated from Payment #{$payment->payment_number}",
                        'created_by' => $createdByName,
                    ]);

                    // Sync purchase status and paid/due balances
                    static::syncPurchaseBalance($purchase);
                    $createdAllocations[] = $pa;
                }
            }

            // Sync payment header allocated & unallocated fields
            static::syncPaymentTotals($payment);

            return $createdAllocations;
        });
    }

    /**
     * Auto-allocate payment amount to oldest outstanding documents (FIFO).
     */
    public static function autoAllocateFIFO(Payment $payment, ?string $createdByName = 'System'): array
    {
        return DB::transaction(function () use ($payment, $createdByName) {
            $payment->refresh();
            $available = round(floatval($payment->amount) - floatval($payment->allocated_amount), 2);
            if ($available <= 0) {
                return [];
            }

            $allocations = [];

            if ($payment->party_type === 'CUSTOMER') {
                $invoices = Invoice::where('company_id', $payment->company_id)
                    ->where('customer_id', $payment->party_id)
                    ->where('status', '!=', 'CANCELLED')
                    ->where('status', '!=', 'DRAFT')
                    ->orderBy('invoice_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($invoices as $inv) {
                    if ($available <= 0) break;

                    $allocated = floatval(PaymentAllocation::where('company_id', $payment->company_id)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount'));
                    $cnTotal = floatval(CreditNote::where('company_id', $payment->company_id)->where('invoice_id', $inv->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                    $due = max(0, round(floatval($inv->grand_total) - $allocated - $cnTotal, 2));

                    if ($due > 0) {
                        $take = min($available, $due);
                        $allocations[] = [
                            'document_type' => 'INVOICE',
                            'document_id' => $inv->id,
                            'amount' => $take,
                        ];
                        $available = round($available - $take, 2);
                    }
                }
            } elseif ($payment->party_type === 'SUPPLIER') {
                $purchases = Purchase::where('company_id', $payment->company_id)
                    ->where('supplier_id', $payment->party_id)
                    ->where('status', '!=', 'CANCELLED')
                    ->where('status', '!=', 'DRAFT')
                    ->orderBy('purchase_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($purchases as $pur) {
                    if ($available <= 0) break;

                    $allocated = floatval(PaymentAllocation::where('company_id', $payment->company_id)->where('document_type', 'PURCHASE_INVOICE')->where('document_id', $pur->id)->sum('allocated_amount'));
                    $dnTotal = floatval(DebitNote::where('company_id', $payment->company_id)->where('purchase_id', $pur->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                    $due = max(0, round(floatval($pur->grand_total) - $allocated - $dnTotal, 2));

                    if ($due > 0) {
                        $take = min($available, $due);
                        $allocations[] = [
                            'document_type' => 'PURCHASE_INVOICE',
                            'document_id' => $pur->id,
                            'amount' => $take,
                        ];
                        $available = round($available - $take, 2);
                    }
                }
            }

            if (!empty($allocations)) {
                return static::allocatePayment($payment, $allocations, $createdByName);
            }

            return [];
        });
    }

    /**
     * Reverse and delete all allocations for a given payment.
     */
    public static function reverseAllocations(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();

            $invoicesToSync = [];
            $purchasesToSync = [];

            foreach ($allocations as $pa) {
                if ($pa->document_type === 'INVOICE') {
                    $invoicesToSync[$pa->document_id] = true;
                } elseif ($pa->document_type === 'PURCHASE_INVOICE') {
                    $purchasesToSync[$pa->document_id] = true;
                }
                $pa->delete();
            }

            foreach (array_keys($invoicesToSync) as $invId) {
                $invoice = Invoice::find($invId);
                if ($invoice) static::syncInvoiceBalance($invoice);
            }

            foreach (array_keys($purchasesToSync) as $purId) {
                $purchase = Purchase::find($purId);
                if ($purchase) static::syncPurchaseBalance($purchase);
            }

            static::syncPaymentTotals($payment);
        });
    }

    /**
     * Recalculate and synchronize payment allocation totals and unallocated balance.
     */
    public static function syncPaymentTotals(Payment $payment): void
    {
        $allocated = floatval(PaymentAllocation::where('payment_id', $payment->id)->sum('allocated_amount'));
        $amount = floatval($payment->amount);
        $unallocated = max(0, round($amount - $allocated, 2));

        $payment->update([
            'allocated_amount' => round($allocated, 2),
            'unallocated_amount' => $unallocated,
        ]);
    }

    /**
     * Authoritative Invoice Balance & Payment Status Synchronizer.
     */
    public static function syncInvoiceBalance(Invoice $invoice): void
    {
        $totalPaid = floatval(
            PaymentAllocation::where('company_id', $invoice->company_id)
                ->where('document_type', 'INVOICE')
                ->where('document_id', $invoice->id)
                ->sum('allocated_amount')
        );

        $cnTotal = floatval(
            CreditNote::where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('status', '!=', 'CANCELLED')
                ->sum('amount')
        );

        $grandTotal = floatval($invoice->grand_total);
        $due = max(0, round($grandTotal - $totalPaid - $cnTotal, 2));

        // Determine derived payment status
        $status = $invoice->status;
        if ($invoice->status !== 'DRAFT' && $invoice->status !== 'CANCELLED') {
            if ($due <= 0.001) {
                $status = 'PAID';
            } elseif ($totalPaid > 0) {
                $status = 'PARTIALLY_PAID';
            } else {
                $status = 'POSTED';
            }
        }

        $invoice->update([
            'amount_paid' => round($totalPaid, 2),
            'amount_due' => $due,
            'status' => $status,
        ]);
    }

    /**
     * Authoritative Purchase Balance & Payment Status Synchronizer.
     */
    public static function syncPurchaseBalance(Purchase $purchase): void
    {
        $totalPaid = floatval(
            PaymentAllocation::where('company_id', $purchase->company_id)
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_id', $purchase->id)
                ->sum('allocated_amount')
        );

        $dnTotal = floatval(
            DebitNote::where('company_id', $purchase->company_id)
                ->where('purchase_id', $purchase->id)
                ->where('status', '!=', 'CANCELLED')
                ->sum('amount')
        );

        $grandTotal = floatval($purchase->grand_total);
        $due = max(0, round($grandTotal - $totalPaid - $dnTotal, 2));

        $paymentStatus = 'UNPAID';
        if ($due <= 0.001) {
            $paymentStatus = 'PAID';
        } elseif ($totalPaid > 0) {
            $paymentStatus = 'PARTIALLY_PAID';
        }

        $purchase->update([
            'amount_paid' => round($totalPaid, 2),
            'amount_due' => $due,
            'payment_status' => $paymentStatus,
        ]);
    }
}
