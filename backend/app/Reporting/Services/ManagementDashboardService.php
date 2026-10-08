<?php

namespace App\Reporting\Services;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Services\CashFlowService;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class ManagementDashboardService
{
    /**
     * Executive Management Dashboard
     */
    public static function getManagementDashboard(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $startDate = $dates['start_date'];
        $endDate = $dates['end_date'];

        // Sales & Invoices in Period
        $invoices = Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $grossSales = floatval($invoices->sum('grand_total'));
        $totalSalesTax = floatval($invoices->sum('total_tax'));
        $taxableSales = floatval($invoices->sum(fn($i) => $i->taxable_value ?: $i->subtotal));

        // Purchases in Period
        $purchases = Purchase::where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $startDate)
            ->whereDate('purchase_date', '<=', $endDate)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $grossPurchases = floatval($purchases->sum('grand_total'));
        $totalPurchaseTax = floatval($purchases->sum('total_tax'));
        $taxablePurchases = floatval($purchases->sum(fn($p) => $p->taxable_value ?: $p->subtotal));

        // Gross Profit (Taxable Sales - Taxable Purchases approx or from P&L)
        $grossProfit = $taxableSales - $taxablePurchases;

        // Receivables & Payables (All outstanding as of now)
        $allInvoices = Invoice::where('company_id', $companyId)->where('status', '!=', 'CANCELLED')->get();
        $totalReceivables = floatval($allInvoices->sum('amount_due'));

        $allPurchases = Purchase::where('company_id', $companyId)->where('status', '!=', 'CANCELLED')->get();
        $totalPayables = floatval($allPurchases->sum('amount_due'));

        // Cash & Bank Position
        $cf = CashFlowService::getCashFlow($companyId);
        $cashPosition = floatval($cf['closing_cash_balance'] ?? 0);
        if ($cashPosition <= 0) {
            $cashAccounts = ChartOfAccount::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereIn('account_subtype', ['CASH', 'BANK'])
                      ->orWhere('account_name', 'LIKE', '%Cash%')
                      ->orWhere('account_name', 'LIKE', '%Bank%')
                      ->orWhere('account_code', 'LIKE', '10%');
                })->get();
            $cashPosition = floatval($cashAccounts->sum('opening_balance'));
        }
        if ($cashPosition <= 0) {
            $cashPosition = floatval(BankAccount::where('company_id', $companyId)->sum('current_balance'));
        }
        if ($cashPosition <= 0) {
            $cashPosition = floatval(Payment::where('company_id', $companyId)->where('status', 'COMPLETED')->sum('amount'));
        }

        // Inventory Stock Value
        $products = Product::where('company_id', $companyId)->get();
        $stockValue = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;
        foreach ($products as $p) {
            $stock = floatval($p->current_stock);
            $cost = floatval($p->purchase_price ?: ($p->cost_price ?? 0));
            $stockValue += ($stock * $cost);
            if ($stock <= 0) $outOfStockCount++;
            elseif (floatval($p->min_stock_level ?? 0) > 0 && $stock <= floatval($p->min_stock_level)) $lowStockCount++;
        }

        // Working Capital = Receivables + Inventory - Payables
        $workingCapital = $totalReceivables + $stockValue - $totalPayables;

        // GST Net Liability
        $gstLiability = max(0.0, $totalSalesTax - $totalPurchaseTax);

        // Previous period comparisons for Growth Rates
        $currStart = Carbon::parse($startDate);
        $currEnd = Carbon::parse($endDate);
        $durationDays = $currStart->diffInDays($currEnd) + 1;
        $prevStart = $currStart->copy()->subDays($durationDays)->toDateString();
        $prevEnd = $currStart->copy()->subDay()->toDateString();

        $prevSales = floatval(Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $prevStart)
            ->whereDate('invoice_date', '<=', $prevEnd)
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $salesGrowth = 0.0;
        if ($prevSales > 0) {
            $salesGrowth = round((($grossSales - $prevSales) / $prevSales) * 100, 2);
        } elseif ($grossSales > 0) {
            $salesGrowth = 100.0;
        }

        // Sales vs Purchase Trend Chart (Monthly/Daily)
        $dailySales = [];
        $dailyPurchases = [];
        $period = Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate));
        foreach ($period as $d) {
            $dStr = $d->format('Y-m-d');
            $dailySales[$dStr] = 0.0;
            $dailyPurchases[$dStr] = 0.0;
        }
        foreach ($invoices as $inv) {
            $dStr = is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : substr((string)$inv->invoice_date, 0, 10);
            if (isset($dailySales[$dStr])) $dailySales[$dStr] += floatval($inv->grand_total);
        }
        foreach ($purchases as $pur) {
            $dStr = is_object($pur->purchase_date) ? $pur->purchase_date->format('Y-m-d') : substr((string)$pur->purchase_date, 0, 10);
            if (isset($dailyPurchases[$dStr])) $dailyPurchases[$dStr] += floatval($pur->grand_total);
        }

        $chartLabels = array_map(fn($k) => Carbon::parse($k)->format('d M'), array_keys($dailySales));
        $salesVsPurchaseChart = ReportChartService::buildLineChart(
            'Sales vs Purchases Comparison (₹)',
            $chartLabels,
            [
                ['name' => 'Sales (₹)', 'data' => array_values($dailySales), 'color' => '#2563eb'],
                ['name' => 'Purchases (₹)', 'data' => array_values($dailyPurchases), 'color' => '#f59e0b'],
            ]
        );

        return [
            'period' => $dates,
            'kpis' => [
                'sales' => $grossSales,
                'purchases' => $grossPurchases,
                'gross_profit' => $grossProfit,
                'sales_growth_pct' => $salesGrowth,
                'receivables' => $totalReceivables,
                'payables' => $totalPayables,
                'cash_position' => $cashPosition,
                'working_capital' => $workingCapital,
                'stock_value' => $stockValue,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'gst_liability' => $gstLiability,
            ],
            'charts' => [
                'sales_vs_purchase' => $salesVsPurchaseChart,
            ],
        ];
    }
}
