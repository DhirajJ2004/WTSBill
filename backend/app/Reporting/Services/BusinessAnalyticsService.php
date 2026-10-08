<?php

namespace App\Reporting\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\Expense;
use App\Services\ProfitLossService;
use App\Services\AgingService;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Aggregations\ReportAggregationService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class BusinessAnalyticsService
{
    /**
     * Executive Business Performance
     */
    public static function getBusinessPerformance(int $companyId, array $filters = []): array
    {
        $currentPeriod = ReportFilterService::resolveDateRange($filters);
        $prevPeriod = ReportFilterService::resolveComparisonPeriod($currentPeriod['start_date'], $currentPeriod['end_date']);

        // Current period sales & purchases
        $curSales = floatval(Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $currentPeriod['start_date'])
            ->whereDate('invoice_date', '<=', $currentPeriod['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $prevSales = floatval(Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $prevPeriod['start_date'])
            ->whereDate('invoice_date', '<=', $prevPeriod['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $curPurchases = floatval(Purchase::where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $currentPeriod['start_date'])
            ->whereDate('purchase_date', '<=', $currentPeriod['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $prevPurchases = floatval(Purchase::where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $prevPeriod['start_date'])
            ->whereDate('purchase_date', '<=', $prevPeriod['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $curPnl = ProfitLossService::getProfitAndLoss($companyId, $currentPeriod['start_date'], $currentPeriod['end_date']);
        $prevPnl = ProfitLossService::getProfitAndLoss($companyId, $prevPeriod['start_date'], $prevPeriod['end_date']);

        $curNetProfit = floatval($curPnl['net_profit']);
        $prevNetProfit = floatval($prevPnl['net_profit']);

        $curExpenses = floatval($curPnl['operating_expenses']['total_expenses'] ?? 0);
        $prevExpenses = floatval($prevPnl['operating_expenses']['total_expenses'] ?? 0);

        $salesGrowth = ReportAggregationService::calculateGrowth($curSales, $prevSales);
        $purchaseGrowth = ReportAggregationService::calculateGrowth($curPurchases, $prevPurchases);
        $profitGrowth = ReportAggregationService::calculateGrowth($curNetProfit, $prevNetProfit);
        $expenseGrowth = ReportAggregationService::calculateGrowth($curExpenses, $prevExpenses);

        $kpis = [
            'revenue' => [
                'current' => $curSales,
                'previous' => $prevSales,
                'growth_pct' => $salesGrowth['percentage'],
                'difference' => $salesGrowth['difference'],
                'trend' => $salesGrowth['trend'],
            ],
            'purchases' => [
                'current' => $curPurchases,
                'previous' => $prevPurchases,
                'growth_pct' => $purchaseGrowth['percentage'],
                'difference' => $purchaseGrowth['difference'],
                'trend' => $purchaseGrowth['trend'],
            ],
            'expenses' => [
                'current' => $curExpenses,
                'previous' => $prevExpenses,
                'growth_pct' => $expenseGrowth['percentage'],
                'difference' => $expenseGrowth['difference'],
                'trend' => $expenseGrowth['trend'],
            ],
            'net_profit' => [
                'current' => $curNetProfit,
                'previous' => $prevNetProfit,
                'growth_pct' => $profitGrowth['percentage'],
                'difference' => $profitGrowth['difference'],
                'trend' => $profitGrowth['trend'],
            ],
        ];

        $chart = ReportChartService::buildBarChart(
            'Performance Comparison: ' . $currentPeriod['label'] . ' vs ' . $prevPeriod['label'],
            ['Revenue', 'Purchases', 'Operating Expenses', 'Net Profit'],
            [
                ['name' => 'Current Period (₹)', 'data' => [$curSales, $curPurchases, $curExpenses, $curNetProfit], 'color' => '#2563eb'],
                ['name' => 'Previous Period (₹)', 'data' => [$prevSales, $prevPurchases, $prevExpenses, $prevNetProfit], 'color' => '#94a3b8'],
            ]
        );

        $rows = [
            ['metric' => 'Gross Sales Revenue', 'current' => $curSales, 'previous' => $prevSales, 'variance' => $salesGrowth['difference'], 'growth' => $salesGrowth['percentage'] . '%'],
            ['metric' => 'Procurement Spend', 'current' => $curPurchases, 'previous' => $prevPurchases, 'variance' => $purchaseGrowth['difference'], 'growth' => $purchaseGrowth['percentage'] . '%'],
            ['metric' => 'Operating Overheads', 'current' => $curExpenses, 'previous' => $prevExpenses, 'variance' => $expenseGrowth['difference'], 'growth' => $expenseGrowth['percentage'] . '%'],
            ['metric' => 'Net Operating Profit', 'current' => $curNetProfit, 'previous' => $prevNetProfit, 'variance' => $profitGrowth['difference'], 'growth' => $profitGrowth['percentage'] . '%'],
        ];

        return [
            'period' => $currentPeriod,
            'comparison_period' => $prevPeriod,
            'summary_kpis' => $kpis,
            'charts' => ['performance_comparison' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'metric', 'label' => 'Key Performance Indicator', 'type' => 'text'],
                ['key' => 'current', 'label' => 'Current Period (₹)', 'type' => 'currency'],
                ['key' => 'previous', 'label' => 'Previous Period (₹)', 'type' => 'currency'],
                ['key' => 'variance', 'label' => 'Variance (₹)', 'type' => 'currency'],
                ['key' => 'growth', 'label' => 'Change %', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Month-on-Month 12 Month Comparative Trend
     */
    public static function getMonthOnMonthTrend(int $companyId, array $filters = []): array
    {
        $now = Carbon::now();
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $mStart = $now->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();
            $months[] = [
                'label' => $mStart->format('M Y'),
                'start' => $mStart->toDateString(),
                'end' => $mEnd->toDateString(),
            ];
        }

        $salesData = [];
        $purchaseData = [];
        $profitData = [];
        $rows = [];

        foreach ($months as $m) {
            $s = floatval(Invoice::where('company_id', $companyId)
                ->whereDate('invoice_date', '>=', $m['start'])
                ->whereDate('invoice_date', '<=', $m['end'])
                ->where('status', '!=', 'CANCELLED')
                ->sum('grand_total'));

            $p = floatval(Purchase::where('company_id', $companyId)
                ->whereDate('purchase_date', '>=', $m['start'])
                ->whereDate('purchase_date', '<=', $m['end'])
                ->where('status', '!=', 'CANCELLED')
                ->sum('grand_total'));

            $pnl = ProfitLossService::getProfitAndLoss($companyId, $m['start'], $m['end']);
            $np = floatval($pnl['net_profit']);

            $salesData[] = $s;
            $purchaseData[] = $p;
            $profitData[] = $np;

            $rows[] = [
                'month' => $m['label'],
                'sales' => $s,
                'purchases' => $p,
                'net_profit' => $np,
                'margin_pct' => $s > 0 ? round(($np / $s) * 100, 1) . '%' : '0%',
            ];
        }

        $chart = ReportChartService::buildLineChart(
            '12-Month Financial Performance Trend',
            array_column($months, 'label'),
            [
                ['name' => 'Sales Revenue (₹)', 'data' => $salesData, 'color' => '#2563eb'],
                ['name' => 'Purchases (₹)', 'data' => $purchaseData, 'color' => '#f59e0b'],
                ['name' => 'Net Profit (₹)', 'data' => $profitData, 'color' => '#10b981'],
            ]
        );

        return [
            'charts' => ['mom_trend' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'month', 'label' => 'Month', 'type' => 'text'],
                ['key' => 'sales', 'label' => 'Sales (₹)', 'type' => 'currency'],
                ['key' => 'purchases', 'label' => 'Purchases (₹)', 'type' => 'currency'],
                ['key' => 'net_profit', 'label' => 'Net Profit (₹)', 'type' => 'currency'],
                ['key' => 'margin_pct', 'label' => 'Profit Margin', 'type' => 'text'],
            ],
        ];
    }

    /**
     * Profitability Analysis by Product
     */
    public static function getProfitability(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with(['invoice', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
            })->get();

        $prodMap = [];
        foreach ($items as $it) {
            $pId = $it->product_id ?: 0;
            $name = $it->item_name ?: ($it->product?->name ?: 'Item #' . $pId);
            $costPrice = floatval($it->product?->purchase_price ?: ($it->product?->cost_price ?? 0));
            $qty = floatval($it->quantity);
            $rev = floatval($it->taxable_value);
            $cost = $qty * $costPrice;
            $profit = $rev - $cost;

            if (!isset($prodMap[$pId])) {
                $prodMap[$pId] = [
                    'product_id' => $pId,
                    'product_name' => $name,
                    'quantity_sold' => 0.0,
                    'revenue' => 0.0,
                    'cogs' => 0.0,
                    'gross_profit' => 0.0,
                ];
            }

            $prodMap[$pId]['quantity_sold'] += $qty;
            $prodMap[$pId]['revenue'] += $rev;
            $prodMap[$pId]['cogs'] += $cost;
            $prodMap[$pId]['gross_profit'] += $profit;
        }

        $rows = array_values($prodMap);
        foreach ($rows as &$r) {
            $r['margin_percentage'] = $r['revenue'] > 0 ? round(($r['gross_profit'] / $r['revenue']) * 100, 2) : 0.0;
        }

        usort($rows, fn($a, $b) => $b['gross_profit'] <=> $a['gross_profit']);

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'quantity_sold', 'label' => 'Units Sold', 'type' => 'number'],
                ['key' => 'revenue', 'label' => 'Revenue (₹)', 'type' => 'currency'],
                ['key' => 'cogs', 'label' => 'Cost of Goods (₹)', 'type' => 'currency'],
                ['key' => 'gross_profit', 'label' => 'Gross Margin (₹)', 'type' => 'currency'],
                ['key' => 'margin_percentage', 'label' => 'Margin %', 'type' => 'number'],
            ],
        ];
    }
}
