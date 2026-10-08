<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

class AdvanceService
{
    /**
     * Get Customer Advances (payments with remaining unallocated balance).
     */
    public static function getCustomerAdvances(int $companyId, ?int $customerId = null): array
    {
        $query = Payment::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('payment_type', 'RECEIPT')
            ->where('status', 'POSTED')
            ->where('unallocated_amount', '>', 0)
            ->with('customer');

        if ($customerId) {
            $query->where('party_id', $customerId);
        }

        $advances = $query->orderBy('payment_date', 'asc')->get();

        return $advances->map(function ($p) {
            return [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'payment_date' => $p->payment_date,
                'customer_id' => $p->party_id,
                'customer_name' => $p->customer ? $p->customer->name : 'Walk-in Customer',
                'payment_mode' => $p->payment_mode,
                'total_amount' => floatval($p->amount),
                'allocated_amount' => floatval($p->allocated_amount),
                'unallocated_advance' => floatval($p->unallocated_amount),
                'reference_number' => $p->transaction_reference ?: $p->reference_number,
                'notes' => $p->notes,
            ];
        })->toArray();
    }

    /**
     * Get Supplier Advances (vendor prepayments with remaining unallocated balance).
     */
    public static function getSupplierAdvances(int $companyId, ?int $supplierId = null): array
    {
        $query = Payment::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('payment_type', 'PAYMENT')
            ->where('status', 'POSTED')
            ->where('unallocated_amount', '>', 0)
            ->with('supplier');

        if ($supplierId) {
            $query->where('party_id', $supplierId);
        }

        $advances = $query->orderBy('payment_date', 'asc')->get();

        return $advances->map(function ($p) {
            return [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'payment_date' => $p->payment_date,
                'supplier_id' => $p->party_id,
                'supplier_name' => $p->supplier ? $p->supplier->name : 'Supplier',
                'payment_mode' => $p->payment_mode,
                'total_amount' => floatval($p->amount),
                'allocated_amount' => floatval($p->allocated_amount),
                'unallocated_advance' => floatval($p->unallocated_amount),
                'reference_number' => $p->transaction_reference ?: $p->reference_number,
                'notes' => $p->notes,
            ];
        })->toArray();
    }

    /**
     * Apply unallocated advance payment to specified invoices.
     */
    public static function applyCustomerAdvance(int $paymentId, array $allocations, ?string $createdByName = 'Admin'): array
    {
        $payment = Payment::findOrFail($paymentId);
        return PaymentAllocationService::allocatePayment($payment, $allocations, $createdByName);
    }
}
