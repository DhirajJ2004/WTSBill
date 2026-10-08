<?php

namespace App\Reporting\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\DebitNote;
use App\Models\Branch;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class PurchaseReportService
{
    /**
     * Purchase Summary Report
     */
    public static function getPurchaseSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $q = Purchase::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $dates['start_date'])
            ->whereDate('purchase_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED');

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('supplier_id', $filters['supplier_id']);
        }

        $purchases = $q->get();

        $invoiceCount = $purchases->count();
        $grossPurchases = floatval($purchases->sum(fn($p) => $p->taxable_value ?: $p->subtotal));
        $totalCgst = floatval($purchases->sum('cgst_amount'));
        $totalSgst = floatval($purchases->sum('sgst_amount'));
        $totalIgst = floatval($purchases->sum('igst_amount'));
        $totalTax = floatval($purchases->sum('total_tax') ?: ($totalCgst + $totalSgst + $totalIgst));
        $grandTotal = floatval($purchases->sum('grand_total'));
        $totalPaid = floatval($purchases->sum('amount_paid'));
        $totalDue = floatval($purchases->sum('amount_due'));

        // Debit notes (purchase returns)
        $dnQuery = DebitNote::where('company_id', $companyId)
            ->whereDate('debit_note_date', '>=', $dates['start_date'])
            ->whereDate('debit_note_date', '<=', $dates['end_date']);
        if (!empty($filters['branch_id'])) {
            $dnQuery->where('branch_id', $filters['branch_id']);
        }
        $debitNotes = $dnQuery->get();
        $totalReturns = floatval($debitNotes->sum('grand_total'));
        $netPurchases = $grandTotal - $totalReturns;

        // Daily trend
        $dailyMap = [];
        $period = Carbon::parse($dates['start_date'])->daysUntil(Carbon::parse($dates['end_date']));
        foreach ($period as $d) {
            $dailyMap[$d->format('Y-m-d')] = 0.0;
        }
        foreach ($purchases as $pur) {
            $dStr = is_object($pur->purchase_date) ? $pur->purchase_date->format('Y-m-d') : substr((string)$pur->purchase_date, 0, 10);
            if (isset($dailyMap[$dStr])) {
                $dailyMap[$dStr] += floatval($pur->grand_total);
            }
        }

        $chartLabels = array_map(fn($k) => Carbon::parse($k)->format('d M'), array_keys($dailyMap));
        $chartValues = array_values($dailyMap);

        $trendChart = ReportChartService::buildLineChart(
            'Procurement Trend (' . $dates['label'] . ')',
            $chartLabels,
            [['name' => 'Purchases (₹)', 'data' => $chartValues, 'color' => '#f59e0b']]
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'gross_purchases' => $grossPurchases,
                'total_tax' => $totalTax,
                'cgst' => $totalCgst,
                'sgst' => $totalSgst,
                'igst' => $totalIgst,
                'grand_total' => $grandTotal,
                'purchase_returns' => $totalReturns,
                'net_purchases' => $netPurchases,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
                'purchase_count' => $invoiceCount,
            ],
            'charts' => [
                'purchase_trend' => $trendChart,
            ],
            'rows' => $purchases->map(fn($p) => [
                'id' => $p->id,
                'purchase_number' => $p->purchase_number,
                'vendor_invoice_number' => $p->vendor_invoice_number ?: ($p->bill_number ?: '-'),
                'date' => is_object($p->purchase_date) ? $p->purchase_date->format('Y-m-d') : (string)$p->purchase_date,
                'supplier_name' => $p->supplier?->name ?: ($p->supplier_name ?: 'Vendor'),
                'taxable_value' => floatval($p->taxable_value ?: $p->subtotal),
                'total_tax' => floatval($p->total_tax ?: $p->tax_total),
                'grand_total' => floatval($p->grand_total),
                'amount_paid' => floatval($p->amount_paid),
                'amount_due' => floatval($p->amount_due),
                'status' => $p->status,
            ])->toArray(),
            'columns' => [
                ['key' => 'purchase_number', 'label' => 'Purchase #', 'type' => 'text'],
                ['key' => 'vendor_invoice_number', 'label' => 'Vendor Inv #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'supplier_name', 'label' => 'Supplier', 'type' => 'text'],
                ['key' => 'taxable_value', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'grand_total', 'label' => 'Total (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'amount_due', 'label' => 'Due (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Purchase Register (Auditor Grade)
     */
    public static function getPurchaseRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $q = Purchase::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $dates['start_date'])
            ->whereDate('purchase_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED');

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('supplier_id', $filters['supplier_id']);
        }

        $purchases = $q->orderBy('purchase_date', 'asc')->get();

        $rows = [];
        $totTaxable = 0.0;
        $totCgst = 0.0;
        $totSgst = 0.0;
        $totIgst = 0.0;
        $totGrand = 0.0;
        $totPaid = 0.0;
        $totDue = 0.0;

        foreach ($purchases as $p) {
            $taxable = floatval($p->taxable_value ?: $p->subtotal);
            $cgst = floatval($p->cgst_amount);
            $sgst = floatval($p->sgst_amount);
            $igst = floatval($p->igst_amount);
            $grand = floatval($p->grand_total);
            $paid = floatval($p->amount_paid);
            $due = floatval($p->amount_due);

            $totTaxable += $taxable;
            $totCgst += $cgst;
            $totSgst += $sgst;
            $totIgst += $igst;
            $totGrand += $grand;
            $totPaid += $paid;
            $totDue += $due;

            $rows[] = [
                'id' => $p->id,
                'date' => is_object($p->purchase_date) ? $p->purchase_date->format('Y-m-d') : (string)$p->purchase_date,
                'purchase_number' => $p->purchase_number,
                'supplier_name' => $p->supplier?->name ?: ($p->supplier_name ?: 'Supplier'),
                'gstin' => $p->supplier?->gstin ?: ($p->supplier_gstin ?: '-'),
                'taxable_amount' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'total' => $grand,
                'paid' => $paid,
                'balance' => $due,
                'status' => $p->status,
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'purchase_count' => count($rows),
                'total_taxable' => $totTaxable,
                'total_cgst' => $totCgst,
                'total_sgst' => $totSgst,
                'total_igst' => $totIgst,
                'total_grand' => $totGrand,
                'total_paid' => $totPaid,
                'total_balance' => $totDue,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'purchase_number', 'label' => 'Purchase #', 'type' => 'text'],
                ['key' => 'supplier_name', 'label' => 'Supplier', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'cgst', 'label' => 'CGST (₹)', 'type' => 'currency'],
                ['key' => 'sgst', 'label' => 'SGST (₹)', 'type' => 'currency'],
                ['key' => 'igst', 'label' => 'IGST (₹)', 'type' => 'currency'],
                ['key' => 'total', 'label' => 'Total (₹)', 'type' => 'currency'],
                ['key' => 'paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'balance', 'label' => 'Due (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Purchases by Supplier
     */
    public static function getPurchaseBySupplier(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $purchases = Purchase::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $dates['start_date'])
            ->whereDate('purchase_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $supMap = [];
        foreach ($purchases as $p) {
            $sId = $p->supplier_id ?: 0;
            $sName = $p->supplier?->name ?: ($p->supplier_name ?: 'Supplier #' . $sId);
            $gstin = $p->supplier?->gstin ?: ($p->supplier_gstin ?: '');

            if (!isset($supMap[$sId])) {
                $supMap[$sId] = [
                    'supplier_id' => $sId,
                    'supplier_name' => $sName,
                    'gstin' => $gstin,
                    'bill_count' => 0,
                    'taxable_amount' => 0.0,
                    'gst_amount' => 0.0,
                    'total_amount' => 0.0,
                    'amount_paid' => 0.0,
                    'amount_due' => 0.0,
                ];
            }

            $supMap[$sId]['bill_count']++;
            $supMap[$sId]['taxable_amount'] += floatval($p->taxable_value ?: $p->subtotal);
            $supMap[$sId]['gst_amount'] += floatval($p->total_tax ?: ($p->cgst_amount + $p->sgst_amount + $p->igst_amount));
            $supMap[$sId]['total_amount'] += floatval($p->grand_total);
            $supMap[$sId]['amount_paid'] += floatval($p->amount_paid);
            $supMap[$sId]['amount_due'] += floatval($p->amount_due);
        }

        $rows = array_values($supMap);
        usort($rows, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        $top10 = array_slice($rows, 0, 10);
        $labels = array_column($top10, 'supplier_name');
        $amounts = array_column($top10, 'total_amount');

        $chart = ReportChartService::buildBarChart(
            'Top Suppliers by Procurement (₹)',
            $labels,
            [['name' => 'Purchases (₹)', 'data' => $amounts, 'color' => '#8b5cf6']]
        );

        return [
            'period' => $dates,
            'charts' => ['top_suppliers' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'bill_count', 'label' => 'Bills', 'type' => 'number'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'gst_amount', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'total_amount', 'label' => 'Total Purchases (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'amount_due', 'label' => 'Outstanding (₹)', 'type' => 'currency'],
            ],
        ];
    }
}
