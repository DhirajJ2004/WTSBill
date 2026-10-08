<?php

namespace App\Reporting\Services;

use App\Models\Expense;
use App\Models\Branch;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class ExpenseReportService
{
    /**
     * Expense Summary Report
     */
    public static function getExpenseSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $expenses = Expense::where('company_id', $companyId)
            ->whereDate('expense_date', '>=', $dates['start_date'])
            ->whereDate('expense_date', '<=', $dates['end_date'])
            ->get();

        $totalAmount = floatval($expenses->sum('amount'));
        $totalTax = floatval($expenses->sum('tax_amount'));
        $grandTotal = floatval($expenses->sum('total_amount') ?: ($totalAmount + $totalTax));

        // Group by category
        $catMap = [];
        foreach ($expenses as $ex) {
            $cat = $ex->category ?: 'General Expense';
            $catMap[$cat] = ($catMap[$cat] ?? 0.0) + floatval($ex->total_amount ?: $ex->amount);
        }

        $chartItems = [];
        foreach ($catMap as $cName => $amt) {
            $chartItems[] = ['name' => $cName, 'value' => $amt];
        }

        $donut = ReportChartService::buildDonutChart('Expenses by Category', $chartItems);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'expense_count' => $expenses->count(),
                'net_expense' => $totalAmount,
                'total_tax' => $totalTax,
                'grand_total_expense' => $grandTotal,
            ],
            'charts' => ['expenses_by_category' => $donut],
            'rows' => $expenses->map(fn($ex) => [
                'id' => $ex->id,
                'expense_number' => $ex->expense_number ?: ('EXP-' . $ex->id),
                'date' => is_object($ex->expense_date) ? $ex->expense_date->format('Y-m-d') : (string)$ex->expense_date,
                'category' => $ex->category ?: 'General',
                'title' => $ex->title ?: ($ex->description ?: 'Expense'),
                'payment_mode' => $ex->payment_mode ?: 'CASH',
                'amount' => floatval($ex->amount),
                'tax_amount' => floatval($ex->tax_amount),
                'total_amount' => floatval($ex->total_amount ?: ($ex->amount + ($ex->tax_amount ?? 0))),
            ])->toArray(),
            'columns' => [
                ['key' => 'expense_number', 'label' => 'Expense #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Title/Description', 'type' => 'text'],
                ['key' => 'payment_mode', 'label' => 'Payment Mode', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'tax_amount', 'label' => 'Tax (₹)', 'type' => 'currency'],
                ['key' => 'total_amount', 'label' => 'Total Expense (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Expense Trend & Period Comparison
     */
    public static function getExpenseTrend(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $currStart = Carbon::parse($dates['start_date']);
        $currEnd = Carbon::parse($dates['end_date']);
        $durationDays = $currStart->diffInDays($currEnd) + 1;

        $prevStart = $currStart->copy()->subDays($durationDays);
        $prevEnd = $currStart->copy()->subDay();

        $currTotal = floatval(Expense::where('company_id', $companyId)
            ->whereDate('expense_date', '>=', $currStart->toDateString())
            ->whereDate('expense_date', '<=', $currEnd->toDateString())
            ->sum('amount'));

        $prevTotal = floatval(Expense::where('company_id', $companyId)
            ->whereDate('expense_date', '>=', $prevStart->toDateString())
            ->whereDate('expense_date', '<=', $prevEnd->toDateString())
            ->sum('amount'));

        $growthRate = 0.0;
        if ($prevTotal > 0) {
            $growthRate = round((($currTotal - $prevTotal) / $prevTotal) * 100, 2);
        }

        return [
            'current_period' => ['label' => $dates['label'], 'expense' => $currTotal],
            'previous_period' => ['label' => 'Previous ' . $durationDays . ' Days', 'expense' => $prevTotal],
            'growth_rate_pct' => $growthRate,
            'change_amount' => round($currTotal - $prevTotal, 2),
        ];
    }
}
