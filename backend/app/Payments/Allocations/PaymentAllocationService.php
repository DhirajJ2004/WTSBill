<?php

namespace App\Payments\Allocations;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CustomerCredit;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

class PaymentAllocationService
{
    /**
     * Normalize allocations input into structured array
     */
    protected static function normalizeAllocations(array $allocations, string $idKey = 'invoice_id'): array
    {
        $normalized = [];
        foreach ($allocations as $key => $value) {
            if (is_array($value)) {
                $docId = intval($value[$idKey] ?? ($value['document_id'] ?? ($value['invoice_id'] ?? ($value['purchase_id'] ?? $key))));
                $amt = round(floatval($value['amount'] ?? 0), 2);
                $normalized[] = ['document_id' => $docId, 'amount' => $amt];
            } else {
                $docId = intval($key);
                $amt = round(floatval($value), 2);
                $normalized[] = ['document_id' => $docId, 'amount' => $amt];
            }
        }
        return $normalized;
    }

    /**
     * Allocate payment across one or multiple customer invoices.
     *
     * @param Payment $payment
     * @param array $allocations Array of ['invoice_id' => int, 'amount' => float] or [$invoiceId => $amount]
     * @param string|null $userName
     * @return array
     */
    public static function allocateCustomerPayment(Payment $payment, array $allocations, ?string $userName = 'System'): array
    {
        $companyId = $payment->company_id;
        $items = self::normalizeAllocations($allocations, 'invoice_id');

        return DB::transaction(function () use ($payment, $items, $companyId, $userName) {
            $totalToAllocate = 0.0;
            foreach ($items as $alloc) {
                $amt = round(floatval($alloc['amount'] ?? 0), 2);
                if ($amt < 0) {
                    throw new InvalidArgumentException("Allocation amount cannot be negative: ₹{$amt}");
                }
                $totalToAllocate += $amt;
            }

            // Invariant: Total allocations cannot exceed payment amount
            $alreadyAllocated = floatval($payment->allocated_amount ?? 0);
            $availableInPayment = round($payment->amount - $alreadyAllocated, 2);

            if ($totalToAllocate > ($availableInPayment + 0.001)) {
                throw new InvalidArgumentException("Total allocations (₹{$totalToAllocate}) exceed remaining payment funds (₹{$availableInPayment}).");
            }

            $createdAllocations = [];

            foreach ($items as $alloc) {
                $invoiceId = intval($alloc['document_id']);
                $allocatedAmount = round(floatval($alloc['amount']), 2);
                if ($allocatedAmount <= 0) continue;

                // Concurrency-safe row lock
                $invoice = Invoice::where('company_id', $companyId)->lockForUpdate()->findOrFail($invoiceId);

                $outstanding = round(floatval($invoice->amount_due ?? ($invoice->grand_total - ($invoice->amount_paid ?? 0))), 2);
                if ($outstanding <= 0.001) {
                    throw new RuntimeException("Invoice #{$invoice->invoice_number} is already fully paid.");
                }

                // Invariant: Cannot allocate more than invoice outstanding
                if ($allocatedAmount > ($outstanding + 0.001)) {
                    throw new RuntimeException("Allocation amount (₹{$allocatedAmount}) exceeds invoice outstanding balance (₹{$outstanding}).");
                }

                // Record allocation
                $pa = PaymentAllocation::create([
                    'company_id' => $companyId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'document_type' => 'INVOICE',
                    'document_id' => $invoice->id,
                    'allocated_amount' => $allocatedAmount,
                    'allocation_date' => date('Y-m-d'),
                ]);

                // Update Invoice
                $newPaid = round(($invoice->amount_paid ?? 0) + $allocatedAmount, 2);
                $newDue = max(0.00, round($invoice->grand_total - $newPaid, 2));
                $newStatus = ($newDue <= 0.001) ? 'PAID' : 'PARTIALLY_PAID';

                $invoice->update([
                    'amount_paid' => $newPaid,
                    'amount_due' => $newDue,
                    'status' => $newStatus,
                ]);

                $createdAllocations[] = $pa;
            }

            // Update Payment allocated and unallocated balances
            $newPaymentAllocated = round($alreadyAllocated + $totalToAllocate, 2);
            $newUnallocated = max(0.00, round($payment->amount - $newPaymentAllocated, 2));

            $payment->update([
                'allocated_amount' => $newPaymentAllocated,
                'unallocated_amount' => $newUnallocated,
            ]);

            // If there is unallocated amount and it is customer payment, record customer credit
            if ($newUnallocated > 0.001 && $payment->party_id && $payment->party_type === 'CUSTOMER') {
                CustomerCredit::firstOrCreate(
                    ['company_id' => $companyId, 'customer_id' => $payment->party_id, 'source_id' => $payment->id, 'credit_type' => 'OVERPAYMENT'],
                    [
                        'amount' => $newUnallocated,
                        'applied_amount' => 0.00,
                        'remaining_amount' => $newUnallocated,
                        'status' => 'ACTIVE',
                    ]
                );
            }

            return [
                'total_allocated' => $totalToAllocate,
                'unallocated_remaining' => $newUnallocated,
                'allocations' => $createdAllocations,
            ];
        });
    }

    /**
     * Allocate supplier payment across one or multiple purchase bills.
     */
    public static function allocateSupplierPayment(Payment $payment, array $allocations, ?string $userName = 'System'): array
    {
        $companyId = $payment->company_id;
        $items = self::normalizeAllocations($allocations, 'purchase_id');

        return DB::transaction(function () use ($payment, $items, $companyId, $userName) {
            $totalToAllocate = 0.0;
            foreach ($items as $alloc) {
                $amt = round(floatval($alloc['amount'] ?? 0), 2);
                if ($amt < 0) {
                    throw new InvalidArgumentException("Allocation amount cannot be negative: ₹{$amt}");
                }
                $totalToAllocate += $amt;
            }

            $alreadyAllocated = floatval($payment->allocated_amount ?? 0);
            $availableInPayment = round($payment->amount - $alreadyAllocated, 2);

            if ($totalToAllocate > ($availableInPayment + 0.001)) {
                throw new InvalidArgumentException("Total allocations (₹{$totalToAllocate}) exceed remaining payment funds (₹{$availableInPayment}).");
            }

            $createdAllocations = [];

            foreach ($items as $alloc) {
                $purchaseId = intval($alloc['document_id']);
                $allocatedAmount = round(floatval($alloc['amount']), 2);
                if ($allocatedAmount <= 0) continue;

                $purchase = Purchase::where('company_id', $companyId)->lockForUpdate()->findOrFail($purchaseId);

                $outstanding = round(floatval($purchase->amount_due ?? ($purchase->grand_total - ($purchase->amount_paid ?? 0))), 2);
                if ($outstanding <= 0.001) {
                    throw new RuntimeException("Purchase #{$purchase->purchase_number} is already fully paid.");
                }

                if ($allocatedAmount > ($outstanding + 0.001)) {
                    throw new RuntimeException("Allocation amount (₹{$allocatedAmount}) exceeds purchase outstanding balance (₹{$outstanding}).");
                }

                $pa = PaymentAllocation::create([
                    'company_id' => $companyId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $purchase->id,
                    'document_type' => 'PURCHASE',
                    'document_id' => $purchase->id,
                    'allocated_amount' => $allocatedAmount,
                    'allocation_date' => date('Y-m-d'),
                ]);

                $newPaid = round(($purchase->amount_paid ?? 0) + $allocatedAmount, 2);
                $newDue = max(0.00, round($purchase->grand_total - $newPaid, 2));
                $newStatus = ($newDue <= 0.001) ? 'PAID' : 'PARTIALLY_PAID';

                $purchase->update([
                    'amount_paid' => $newPaid,
                    'amount_due' => $newDue,
                    'status' => $newStatus,
                ]);

                $createdAllocations[] = $pa;
            }

            $newPaymentAllocated = round($alreadyAllocated + $totalToAllocate, 2);
            $newUnallocated = max(0.00, round($payment->amount - $newPaymentAllocated, 2));

            $payment->update([
                'allocated_amount' => $newPaymentAllocated,
                'unallocated_amount' => $newUnallocated,
            ]);

            return [
                'total_allocated' => $totalToAllocate,
                'unallocated_remaining' => $newUnallocated,
                'allocations' => $createdAllocations,
            ];
        });
    }

    /**
     * Auto-allocate payment amount to oldest unpaid customer invoices first (FIFO).
     */
    public static function autoAllocate(Payment $payment, ?string $userName = 'System'): array
    {
        $companyId = $payment->company_id;
        $customerId = $payment->party_id;

        $unallocated = floatval($payment->unallocated_amount ?: ($payment->amount - ($payment->allocated_amount ?? 0)));
        if ($unallocated <= 0.001) {
            return ['allocated' => 0, 'allocations' => []];
        }

        $openInvoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['PAID', 'CANCELLED'])
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $allocationsToApply = [];
        $remainingFunds = $unallocated;

        foreach ($openInvoices as $inv) {
            if ($remainingFunds <= 0.001) break;
            $due = floatval($inv->amount_due ?? ($inv->grand_total - ($inv->amount_paid ?? 0)));
            if ($due <= 0.001) continue;

            $allocAmt = min($remainingFunds, $due);
            $allocationsToApply[] = [
                'invoice_id' => $inv->id,
                'amount' => $allocAmt,
            ];
            $remainingFunds -= $allocAmt;
        }

        if (empty($allocationsToApply)) {
            return ['allocated' => 0, 'allocations' => []];
        }

        return self::allocateCustomerPayment($payment, $allocationsToApply, $userName);
    }
}
