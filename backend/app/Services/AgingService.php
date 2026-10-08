<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\PaymentAllocation;

class AgingService
{
    /**
     * Get Customer Accounts Receivable Aging Report.
     */
    public static function getReceivablesAging(int $companyId, ?int $branchId = null): array
    {
        $today = date('Y-m-d');
        $customers = Customer::where('company_id', $companyId)->get();

        $agingRows = [];
        $totals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_91_180' => 0.0,
            'days_180_plus' => 0.0,
            'total_outstanding' => 0.0,
        ];

        foreach ($customers as $cust) {
            $invoicesQuery = Invoice::where('company_id', $companyId)
                ->where('customer_id', $cust->id)
                ->where('status', '!=', 'CANCELLED')
                ->where('status', '!=', 'DRAFT');

            if ($branchId) {
                $invoicesQuery->where('branch_id', $branchId);
            }

            $invoices = $invoicesQuery->get();

            $current = 0.0;
            $days1_30 = 0.0;
            $days31_60 = 0.0;
            $days61_90 = 0.0;
            $days91_180 = 0.0;
            $days180_plus = 0.0;
            $custTotal = 0.0;

            foreach ($invoices as $inv) {
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount'));
                $cn = floatval(CreditNote::where('company_id', $companyId)->where('invoice_id', $inv->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                $due = max(0, round(floatval($inv->grand_total) - $paid - $cn, 2));

                if ($due <= 0.001) continue;

                $custTotal += $due;
                $dueDate = $inv->due_date ?: $inv->invoice_date;

                if ($dueDate >= $today) {
                    $current += $due;
                } else {
                    $days = intval((strtotime($today) - strtotime($dueDate)) / 86400);
                    if ($days <= 30) {
                        $days1_30 += $due;
                    } elseif ($days <= 60) {
                        $days31_60 += $due;
                    } elseif ($days <= 90) {
                        $days61_90 += $due;
                    } elseif ($days <= 180) {
                        $days91_180 += $due;
                    } else {
                        $days180_plus += $due;
                    }
                }
            }

            if ($custTotal > 0) {
                $row = [
                    'customer_id' => $cust->id,
                    'customer_name' => $cust->name,
                    'phone' => $cust->phone,
                    'current' => round($current, 2),
                    'days_1_30' => round($days1_30, 2),
                    'days_31_60' => round($days31_60, 2),
                    'days_61_90' => round($days61_90, 2),
                    'days_91_180' => round($days91_180, 2),
                    'days_180_plus' => round($days180_plus, 2),
                    'total_outstanding' => round($custTotal, 2),
                ];

                $agingRows[] = $row;

                $totals['current'] += $current;
                $totals['days_1_30'] += $days1_30;
                $totals['days_31_60'] += $days31_60;
                $totals['days_61_90'] += $days61_90;
                $totals['days_91_180'] += $days91_180;
                $totals['days_180_plus'] += $days180_plus;
                $totals['total_outstanding'] += $custTotal;
            }
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'rows' => $agingRows,
            'summary' => $totals,
        ];
    }

    /**
     * Get Supplier Accounts Payable Aging Report.
     */
    public static function getPayablesAging(int $companyId, ?int $branchId = null): array
    {
        $today = date('Y-m-d');
        $suppliers = Supplier::where('company_id', $companyId)->get();

        $agingRows = [];
        $totals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_91_180' => 0.0,
            'days_180_plus' => 0.0,
            'total_outstanding' => 0.0,
        ];

        foreach ($suppliers as $sup) {
            $purchasesQuery = Purchase::where('company_id', $companyId)
                ->where('supplier_id', $sup->id)
                ->where('status', '!=', 'CANCELLED')
                ->where('status', '!=', 'DRAFT');

            if ($branchId) {
                $purchasesQuery->where('branch_id', $branchId);
            }

            $purchases = $purchasesQuery->get();

            $current = 0.0;
            $days1_30 = 0.0;
            $days31_60 = 0.0;
            $days61_90 = 0.0;
            $days91_180 = 0.0;
            $days180_plus = 0.0;
            $supTotal = 0.0;

            foreach ($purchases as $pur) {
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'PURCHASE_INVOICE')->where('document_id', $pur->id)->sum('allocated_amount'));
                $dn = floatval(DebitNote::where('company_id', $companyId)->where('purchase_id', $pur->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
                $due = max(0, round(floatval($pur->grand_total) - $paid - $dn, 2));

                if ($due <= 0.001) continue;

                $supTotal += $due;
                $dueDate = $pur->due_date ?: $pur->purchase_date;

                if ($dueDate >= $today) {
                    $current += $due;
                } else {
                    $days = intval((strtotime($today) - strtotime($dueDate)) / 86400);
                    if ($days <= 30) {
                        $days1_30 += $due;
                    } elseif ($days <= 60) {
                        $days31_60 += $due;
                    } elseif ($days <= 90) {
                        $days61_90 += $due;
                    } elseif ($days <= 180) {
                        $days91_180 += $due;
                    } else {
                        $days180_plus += $due;
                    }
                }
            }

            if ($supTotal > 0) {
                $row = [
                    'supplier_id' => $sup->id,
                    'supplier_name' => $sup->name,
                    'phone' => $sup->phone,
                    'current' => round($current, 2),
                    'days_1_30' => round($days1_30, 2),
                    'days_31_60' => round($days31_60, 2),
                    'days_61_90' => round($days61_90, 2),
                    'days_91_180' => round($days91_180, 2),
                    'days_180_plus' => round($days180_plus, 2),
                    'total_outstanding' => round($supTotal, 2),
                ];

                $agingRows[] = $row;

                $totals['current'] += $current;
                $totals['days_1_30'] += $days1_30;
                $totals['days_31_60'] += $days31_60;
                $totals['days_61_90'] += $days61_90;
                $totals['days_91_180'] += $days91_180;
                $totals['days_180_plus'] += $days180_plus;
                $totals['total_outstanding'] += $supTotal;
            }
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'rows' => $agingRows,
            'summary' => $totals,
        ];
    }
}
