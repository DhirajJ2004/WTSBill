<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class ReceivableService
{
    /**
     * Get aggregate customer receivables list.
     */
    public static function getCustomerReceivables(int $companyId, ?int $branchId = null): array
    {
        $customers = Customer::where('company_id', $companyId)->get();
        $results = [];

        foreach ($customers as $cust) {
            $invoicesQuery = Invoice::where('company_id', $companyId)
                ->where('customer_id', $cust->id)
                ->where('status', '!=', 'CANCELLED')
                ->where('status', '!=', 'DRAFT');

            if ($branchId) {
                $invoicesQuery->where('branch_id', $branchId);
            }

            $invoices = $invoicesQuery->get();
            $totalSales = 0.0;
            $totalPaid = 0.0;
            $totalDue = 0.0;
            $overdueAmount = 0.0;
            $today = date('Y-m-d');

            foreach ($invoices as $inv) {
                $totalSales += floatval($inv->grand_total);
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount'));
                $cn = floatval(CreditNote::where('company_id', $companyId)->where('invoice_id', $inv->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                $due = max(0, round(floatval($inv->grand_total) - $paid - $cn, 2));

                $totalPaid += $paid;
                $totalDue += $due;

                if ($due > 0 && $inv->due_date && $inv->due_date < $today) {
                    $overdueAmount += $due;
                }
            }

            // Unallocated customer advances
            $advances = floatval(
                Payment::where('company_id', $companyId)
                    ->where('party_type', 'CUSTOMER')
                    ->where('party_id', $cust->id)
                    ->where('payment_type', 'RECEIPT')
                    ->where('status', 'POSTED')
                    ->sum('unallocated_amount')
            );

            if ($totalDue > 0 || $advances > 0 || $totalSales > 0) {
                $results[] = [
                    'customer_id' => $cust->id,
                    'customer_name' => $cust->name,
                    'phone' => $cust->phone,
                    'email' => $cust->email,
                    'total_invoiced' => round($totalSales, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_due' => round($totalDue, 2),
                    'unallocated_advance' => round($advances, 2),
                    'net_receivable' => round(max(0, $totalDue - $advances), 2),
                    'overdue_amount' => round($overdueAmount, 2),
                    'invoice_count' => count($invoices),
                ];
            }
        }

        return $results;
    }

    /**
     * Get list of individual outstanding invoices.
     */
    public static function getOutstandingInvoices(int $companyId, ?int $customerId = null, ?int $branchId = null): array
    {
        $query = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->with('customer');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $invoices = $query->orderBy('invoice_date', 'asc')->get();
        $results = [];
        $today = date('Y-m-d');

        foreach ($invoices as $inv) {
            $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount'));
            $cn = floatval(CreditNote::where('company_id', $companyId)->where('invoice_id', $inv->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
            $due = max(0, round(floatval($inv->grand_total) - $paid - $cn, 2));

            if ($due > 0.001) {
                $daysOverdue = 0;
                $isOverdue = false;
                if ($inv->due_date && $inv->due_date < $today) {
                    $daysOverdue = intval((strtotime($today) - strtotime($inv->due_date)) / 86400);
                    $isOverdue = true;
                }

                $results[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->invoice_date,
                    'due_date' => $inv->due_date,
                    'customer_id' => $inv->customer_id,
                    'customer_name' => $inv->customer ? $inv->customer->name : 'Walk-in',
                    'grand_total' => floatval($inv->grand_total),
                    'amount_paid' => $paid,
                    'credit_note_amount' => $cn,
                    'amount_due' => $due,
                    'is_overdue' => $isOverdue,
                    'days_overdue' => $daysOverdue,
                    'status' => ($paid > 0) ? 'PARTIALLY_PAID' : ($isOverdue ? 'OVERDUE' : 'UNPAID'),
                ];
            }
        }

        return $results;
    }

    /**
     * Get receivables aging analysis breakdown.
     */
    public static function getReceivablesAging(int $companyId, ?int $branchId = null): array
    {
        $invoices = self::getOutstandingInvoices($companyId, null, $branchId);

        $buckets = [
            'current' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
            'total' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $due = floatval($inv['amount_due']);
            $days = intval($inv['days_overdue']);

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
            'items' => $invoices,
        ];
    }
}

