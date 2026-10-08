<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\DebitNote;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class PayableService
{
    /**
     * Get aggregate supplier payables list.
     */
    public static function getSupplierPayables(int $companyId, ?int $branchId = null): array
    {
        $suppliers = Supplier::where('company_id', $companyId)->get();
        $results = [];

        foreach ($suppliers as $sup) {
            $purchasesQuery = Purchase::where('company_id', $companyId)
                ->where('supplier_id', $sup->id)
                ->where('status', '!=', 'CANCELLED')
                ->where('status', '!=', 'DRAFT');

            if ($branchId) {
                $purchasesQuery->where('branch_id', $branchId);
            }

            $purchases = $purchasesQuery->get();
            $totalBilled = 0.0;
            $totalPaid = 0.0;
            $totalDue = 0.0;
            $overdueAmount = 0.0;
            $today = date('Y-m-d');

            foreach ($purchases as $pur) {
                $totalBilled += floatval($pur->grand_total);
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'PURCHASE_INVOICE')->where('document_id', $pur->id)->sum('allocated_amount'));
                $dn = floatval(DebitNote::where('company_id', $companyId)->where('purchase_id', $pur->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                $due = max(0, round(floatval($pur->grand_total) - $paid - $dn, 2));

                $totalPaid += $paid;
                $totalDue += $due;

                if ($due > 0 && $pur->due_date && $pur->due_date < $today) {
                    $overdueAmount += $due;
                }
            }

            // Unallocated supplier advances
            $advances = floatval(
                Payment::where('company_id', $companyId)
                    ->where('party_type', 'SUPPLIER')
                    ->where('party_id', $sup->id)
                    ->where('payment_type', 'PAYMENT')
                    ->where('status', 'POSTED')
                    ->sum('unallocated_amount')
            );

            if ($totalDue > 0 || $advances > 0 || $totalBilled > 0) {
                $results[] = [
                    'supplier_id' => $sup->id,
                    'supplier_name' => $sup->name,
                    'phone' => $sup->phone,
                    'email' => $sup->email,
                    'total_billed' => round($totalBilled, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_due' => round($totalDue, 2),
                    'unallocated_advance' => round($advances, 2),
                    'net_payable' => round(max(0, $totalDue - $advances), 2),
                    'overdue_amount' => round($overdueAmount, 2),
                    'bill_count' => count($purchases),
                ];
            }
        }

        return $results;
    }

    /**
     * Get list of individual outstanding supplier bills.
     */
    public static function getOutstandingBills(int $companyId, ?int $supplierId = null, ?int $branchId = null): array
    {
        $query = Purchase::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->with('supplier');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $purchases = $query->orderBy('purchase_date', 'asc')->get();
        $results = [];
        $today = date('Y-m-d');

        foreach ($purchases as $pur) {
            $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'PURCHASE_INVOICE')->where('document_id', $pur->id)->sum('allocated_amount'));
            $dn = floatval(DebitNote::where('company_id', $companyId)->where('purchase_id', $pur->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
            $due = max(0, round(floatval($pur->grand_total) - $paid - $dn, 2));

            if ($due > 0.001) {
                $daysOverdue = 0;
                $isOverdue = false;
                if ($pur->due_date && $pur->due_date < $today) {
                    $daysOverdue = intval((strtotime($today) - strtotime($pur->due_date)) / 86400);
                    $isOverdue = true;
                }

                $results[] = [
                    'id' => $pur->id,
                    'purchase_number' => $pur->purchase_number,
                    'vendor_invoice_number' => $pur->vendor_invoice_number,
                    'purchase_date' => $pur->purchase_date,
                    'due_date' => $pur->due_date,
                    'supplier_id' => $pur->supplier_id,
                    'supplier_name' => $pur->supplier ? $pur->supplier->name : 'Supplier',
                    'grand_total' => floatval($pur->grand_total),
                    'amount_paid' => $paid,
                    'debit_note_amount' => $dn,
                    'amount_due' => $due,
                    'is_overdue' => $isOverdue,
                    'days_overdue' => $daysOverdue,
                    'payment_status' => ($paid > 0) ? 'PARTIALLY_PAID' : ($isOverdue ? 'OVERDUE' : 'UNPAID'),
                ];
            }
        }

        return $results;
    }

    /**
     * Get payables aging analysis breakdown.
     */
    public static function getPayablesAging(int $companyId, ?int $branchId = null): array
    {
        $purchases = self::getOutstandingBills($companyId, null, $branchId);

        $buckets = [
            'current' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
            'total' => 0.0,
        ];

        foreach ($purchases as $pur) {
            $due = floatval($pur['amount_due']);
            $days = intval($pur['days_overdue']);

            $buckets['total'] += $due;

            if ($days <= 0) {
                $buckets['current'] += $due;
            } elseif ($days <= 30) {
                $buckets['current'] += $due;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $due;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $due;
            } else {
                $buckets['days_90_plus'] += $due;
            }
        }

        foreach ($buckets as $k => $v) {
            $buckets[$k] = round($v, 2);
        }

        return [
            'summary' => $buckets,
            'items' => $purchases,
        ];
    }
}

