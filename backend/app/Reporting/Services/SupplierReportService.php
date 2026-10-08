<?php

namespace App\Reporting\Services;

use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\DebitNote;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class SupplierReportService
{
    /**
     * Supplier Outstanding & Payables Summary
     */
    public static function getSupplierOutstanding(int $companyId, array $filters = []): array
    {
        $suppliers = Supplier::where('company_id', $companyId)->get();
        $now = Carbon::now();

        $rows = [];
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $totalPayable = 0.0;
        $totalOverdue = 0.0;

        foreach ($suppliers as $s) {
            $purchases = Purchase::where('company_id', $companyId)
                ->where('supplier_id', $s->id)
                ->where('status', '!=', 'CANCELLED')
                ->get();

            $billCount = $purchases->count();
            $billed = floatval($purchases->sum('grand_total'));
            $paid = floatval($purchases->sum('amount_paid'));
            $due = floatval($purchases->sum('amount_due'));

            $overdue = floatval($purchases->filter(function ($p) use ($now) {
                if ($p->amount_due <= 0) return false;
                $dueDt = $p->due_date ? Carbon::parse($p->due_date) : Carbon::parse($p->purchase_date);
                return $dueDt->lt($now);
            })->sum('amount_due'));

            if ($due > 0 || $billCount > 0) {
                $totalBilled += $billed;
                $totalPaid += $paid;
                $totalPayable += $due;
                $totalOverdue += $overdue;

                $rows[] = [
                    'supplier_id' => $s->id,
                    'supplier_name' => $s->name,
                    'phone' => $s->phone ?: '-',
                    'gstin' => $s->gstin ?: '-',
                    'bill_count' => $billCount,
                    'total_billed' => $billed,
                    'amount_paid' => $paid,
                    'outstanding_payable' => $due,
                    'overdue_amount' => $overdue,
                ];
            }
        }

        usort($rows, fn($a, $b) => $b['outstanding_payable'] <=> $a['outstanding_payable']);

        return [
            'summary_kpis' => [
                'total_suppliers_with_due' => count(array_filter($rows, fn($r) => $r['outstanding_payable'] > 0)),
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_payable' => $totalPayable,
                'total_overdue' => $totalOverdue,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'bill_count', 'label' => 'Bills', 'type' => 'number'],
                ['key' => 'total_billed', 'label' => 'Total Billed (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'outstanding_payable', 'label' => 'Payable (₹)', 'type' => 'currency'],
                ['key' => 'overdue_amount', 'label' => 'Overdue (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Payable Aging Analysis (5 Aging Buckets)
     */
    public static function getPayableAging(int $companyId, array $filters = []): array
    {
        $now = Carbon::now();
        $purchases = Purchase::with('supplier')
            ->where('company_id', $companyId)
            ->where('amount_due', '>', 0)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $buckets = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        $supplierMap = [];

        foreach ($purchases as $p) {
            $sId = $p->supplier_id ?: 0;
            $sName = $p->supplier?->name ?: ($p->supplier_name ?: 'Supplier #' . $sId);
            $dueDt = $p->due_date ? Carbon::parse($p->due_date) : Carbon::parse($p->purchase_date);
            $daysPast = $now->diffInDays($dueDt, false);

            $amount = floatval($p->amount_due);

            if (!isset($supplierMap[$sId])) {
                $supplierMap[$sId] = [
                    'supplier_id' => $sId,
                    'supplier_name' => $sName,
                    'phone' => $p->supplier?->phone ?: '',
                    'total_due' => 0.0,
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_90_plus' => 0.0,
                ];
            }

            $supplierMap[$sId]['total_due'] += $amount;

            if ($daysPast >= 0) {
                $buckets['current'] += $amount;
                $supplierMap[$sId]['current'] += $amount;
            } else {
                $overdueDays = abs($daysPast);
                if ($overdueDays <= 30) {
                    $buckets['days_1_30'] += $amount;
                    $supplierMap[$sId]['days_1_30'] += $amount;
                } elseif ($overdueDays <= 60) {
                    $buckets['days_31_60'] += $amount;
                    $supplierMap[$sId]['days_31_60'] += $amount;
                } elseif ($overdueDays <= 90) {
                    $buckets['days_61_90'] += $amount;
                    $supplierMap[$sId]['days_61_90'] += $amount;
                } else {
                    $buckets['days_90_plus'] += $amount;
                    $supplierMap[$sId]['days_90_plus'] += $amount;
                }
            }
        }

        $totalAging = array_sum($buckets);

        $chartLabels = ['Not Due (Current)', '1-30 Days Overdue', '31-60 Days Overdue', '61-90 Days Overdue', '90+ Days Overdue'];
        $chartValues = [
            $buckets['current'],
            $buckets['days_1_30'],
            $buckets['days_31_60'],
            $buckets['days_61_90'],
            $buckets['days_90_plus'],
        ];

        $chart = ReportChartService::buildBarChart(
            'Payable Aging Breakdown (₹)',
            $chartLabels,
            [['name' => 'Payable (₹)', 'data' => $chartValues, 'color' => '#dc2626']]
        );

        $rows = array_values($supplierMap);
        usort($rows, fn($a, $b) => $b['total_due'] <=> $a['total_due']);

        return [
            'summary_kpis' => [
                'total_payable' => $totalAging,
                'current_not_due' => $buckets['current'],
                'overdue_1_30' => $buckets['days_1_30'],
                'overdue_31_60' => $buckets['days_31_60'],
                'overdue_61_90' => $buckets['days_61_90'],
                'overdue_90_plus' => $buckets['days_90_plus'],
            ],
            'charts' => ['aging_breakdown' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'supplier_name', 'label' => 'Supplier', 'type' => 'text'],
                ['key' => 'total_due', 'label' => 'Total Due (₹)', 'type' => 'currency'],
                ['key' => 'current', 'label' => 'Current (₹)', 'type' => 'currency'],
                ['key' => 'days_1_30', 'label' => '1-30 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_31_60', 'label' => '31-60 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_61_90', 'label' => '61-90 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_90_plus', 'label' => '90+ Days (₹)', 'type' => 'currency'],
            ],
        ];
    }
}
