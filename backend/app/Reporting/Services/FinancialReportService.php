<?php

namespace App\Reporting\Services;

use App\Services\TrialBalanceService;
use App\Services\ProfitLossService;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\LedgerService;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class FinancialReportService
{
    /**
     * Trial Balance (Strict Double-Entry Single Source of Truth)
     */
    public static function getTrialBalance(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $asOfDate = $dates['end_date'];
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        $tb = TrialBalanceService::getTrialBalance($companyId, $asOfDate, $branchId);

        $chart = ReportChartService::buildBarChart(
            'Trial Balance Debits vs Credits (₹)',
            ['Total Debits', 'Total Credits'],
            [['name' => 'Amount (₹)', 'data' => [$tb['total_debit'], $tb['total_credit']], 'color' => '#1e3a8a']]
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_debit' => $tb['total_debit'],
                'total_credit' => $tb['total_credit'],
                'difference' => $tb['difference'],
                'is_balanced' => $tb['is_balanced'],
                'integrity_warning' => !$tb['is_balanced'] ? "WARNING: Total Debits (₹{$tb['total_debit']}) != Total Credits (₹{$tb['total_credit']}). Difference of ₹{$tb['difference']} detected." : null,
            ],
            'charts' => [
                'debits_vs_credits' => $chart,
            ],
            'rows' => $tb['accounts'],
            'columns' => [
                ['key' => 'account_code', 'label' => 'Account Code', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account Name', 'type' => 'text'],
                ['key' => 'account_type', 'label' => 'Type', 'type' => 'badge'],
                ['key' => 'debit', 'label' => 'Debit (₹)', 'type' => 'currency'],
                ['key' => 'credit', 'label' => 'Credit (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Profit & Loss Statement
     */
    public static function getProfitLoss(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        $pnl = ProfitLossService::getProfitAndLoss($companyId, $dates['start_date'], $dates['end_date'], $branchId);

        $netProfit = floatval($pnl['net_profit']);
        $totalRevenue = floatval($pnl['revenue']['net_revenue'] ?? ($pnl['revenue']['gross_sales'] ?? 0));
        $cogs = floatval($pnl['cogs']['total_cogs'] ?? 0);
        $grossProfit = floatval($pnl['gross_profit'] ?? ($totalRevenue - $cogs));
        $totalExpenses = floatval($pnl['operating_expenses']['total_expenses'] ?? 0);

        // Flatten account lines for report rows
        $rows = [];
        foreach ($pnl['revenue']['breakdown'] ?? [] as $acc) {
            $rows[] = [
                'category' => 'REVENUE',
                'account_name' => $acc['account_name'],
                'account_code' => $acc['account_code'],
                'amount' => floatval($acc['amount']),
            ];
        }
        foreach ($pnl['operating_expenses']['breakdown'] ?? [] as $acc) {
            $rows[] = [
                'category' => 'OPERATING_EXPENSE',
                'account_name' => $acc['account_name'],
                'account_code' => $acc['account_code'],
                'amount' => floatval($acc['amount']),
            ];
        }

        $waterfallChart = ReportChartService::buildBarChart(
            'Income & Expense Structure (₹)',
            ['Total Revenue', 'Operating Expenses', 'Net Profit'],
            [['name' => 'Amount (₹)', 'data' => [$totalRevenue, $totalExpenses, $netProfit], 'color' => '#10b981']]
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_revenue' => $totalRevenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0.0,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'net_margin_percentage' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0.0,
            ],
            'charts' => [
                'pnl_waterfall' => $waterfallChart,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'category', 'label' => 'Section', 'type' => 'badge'],
                ['key' => 'account_code', 'label' => 'Code', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account Name', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Balance Sheet
     */
    public static function getBalanceSheet(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $asOfDate = $dates['end_date'];
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        $bs = BalanceSheetService::getBalanceSheet($companyId, $asOfDate, $branchId);
        $rows = [];

        $addItems = function ($group, $section) use (&$rows) {
            foreach ($group['items'] ?? [] as $acc) {
                $rows[] = [
                    'section' => $section,
                    'account_name' => $acc['account_name'] ?? 'Account',
                    'account_code' => $acc['account_code'] ?? '',
                    'amount' => floatval($acc['amount'] ?? 0),
                ];
            }
        };

        $addItems($bs['assets']['current_assets']['cash'] ?? [], 'CURRENT_ASSETS');
        $addItems($bs['assets']['current_assets']['bank'] ?? [], 'CURRENT_ASSETS');
        $addItems($bs['assets']['current_assets']['receivables'] ?? [], 'CURRENT_ASSETS');
        $addItems($bs['assets']['current_assets']['inventory'] ?? [], 'CURRENT_ASSETS');
        $addItems($bs['liabilities']['current_liabilities']['payables'] ?? [], 'CURRENT_LIABILITIES');
        $addItems($bs['equity']['capital'] ?? [], 'EQUITY');

        $totalAssets = floatval($bs['assets']['total_assets'] ?? 0);
        $totalLiabilities = floatval($bs['liabilities']['total_liabilities'] ?? 0);
        $totalEquity = floatval($bs['equity']['total_equity'] ?? 0);
        $totalLiabAndEquity = floatval($bs['total_liabilities_and_equity'] ?? ($totalLiabilities + $totalEquity));
        $diff = round(abs($totalAssets - $totalLiabAndEquity), 2);
        $isBalanced = ($diff <= 0.05) || !empty($bs['is_balanced']);

        $donut = ReportChartService::buildDonutChart(
            'Capital & Liabilities Composition',
            [
                ['name' => 'Total Liabilities', 'value' => $totalLiabilities],
                ['name' => 'Owner Equity & Retained Earnings', 'value' => $totalEquity],
            ]
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'total_liabilities_and_equity' => $totalLiabAndEquity,
                'difference' => $diff,
                'is_balanced' => $isBalanced,
            ],
            'charts' => [
                'assets_vs_liabilities' => $donut,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'section', 'label' => 'Classification', 'type' => 'badge'],
                ['key' => 'account_code', 'label' => 'Code', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account Name', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Balance (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Cash Flow Statement
     */
    public static function getCashFlow(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        $cf = CashFlowService::getCashFlow($companyId, $dates['start_date'], $dates['end_date'], $branchId);

        $opNet = floatval($cf['operating_activities']['net'] ?? ($cf['operating_activities']['net_amount'] ?? 0));
        $invNet = floatval($cf['investing_activities']['net'] ?? ($cf['investing_activities']['net_amount'] ?? 0));
        $finNet = floatval($cf['financing_activities']['net'] ?? ($cf['financing_activities']['net_amount'] ?? 0));
        $openCash = floatval($cf['opening_cash_balance'] ?? ($cf['opening_cash'] ?? 0));
        $netMov = floatval($cf['net_cash_movement'] ?? ($cf['net_movement'] ?? 0));
        $closeCash = floatval($cf['closing_cash_balance'] ?? ($cf['closing_cash'] ?? 0));

        $chart = ReportChartService::buildBarChart(
            'Cash Flow Activities (₹)',
            ['Operating Activities', 'Investing Activities', 'Financing Activities'],
            [[
                'name' => 'Net Flow (₹)',
                'data' => [$opNet, $invNet, $finNet],
                'color' => '#0284c7'
            ]]
        );

        $rows = array_merge(
            $cf['operating_activities']['items'] ?? [],
            $cf['investing_activities']['items'] ?? [],
            $cf['financing_activities']['items'] ?? []
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'opening_cash' => $openCash,
                'operating_cash_flow' => $opNet,
                'investing_cash_flow' => $invNet,
                'financing_cash_flow' => $finNet,
                'net_cash_movement' => $netMov,
                'closing_cash' => $closeCash,
            ],
            'charts' => [
                'cash_flow_activities' => $chart,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'description', 'label' => 'Activity Description', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Cash Flow (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * General Ledger
     */
    public static function getGeneralLedger(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $accountId = !empty($filters['account_id']) ? intval($filters['account_id']) : 0;

        if ($accountId <= 0) {
            $firstAccount = ChartOfAccount::where('company_id', $companyId)->first();
            $accountId = $firstAccount ? $firstAccount->id : 1;
        }

        $ledger = LedgerService::getAccountLedger($companyId, $accountId, $dates['start_date'], $dates['end_date']);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'account_name' => is_object($ledger['account'] ?? null) ? $ledger['account']->account_name : ($ledger['account_name'] ?? 'General Ledger'),
                'account_code' => is_object($ledger['account'] ?? null) ? $ledger['account']->account_code : '',
                'opening_balance' => floatval($ledger['opening_balance'] ?? 0),
                'total_debit' => floatval($ledger['total_debit'] ?? 0),
                'total_credit' => floatval($ledger['total_credit'] ?? 0),
                'closing_balance' => floatval($ledger['closing_balance'] ?? 0),
            ],
            'rows' => $ledger['transactions'] ?? [],
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'journal_number', 'label' => 'JV / Ref #', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Particulars', 'type' => 'text'],
                ['key' => 'debit', 'label' => 'Debit (₹)', 'type' => 'currency'],
                ['key' => 'credit', 'label' => 'Credit (₹)', 'type' => 'currency'],
                ['key' => 'running_balance', 'label' => 'Running Balance (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Day Book (All Financial & Accounting Activity on Given Date / Range)
     */
    public static function getDayBook(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $lines = JournalLine::with(['journalEntry', 'account'])
            ->whereHas('journalEntry', function ($q) use ($companyId, $dates) {
                $q->where('company_id', $companyId)
                    ->whereDate('entry_date', '>=', $dates['start_date'])
                    ->whereDate('entry_date', '<=', $dates['end_date'])
                    ->whereIn('status', ['POSTED', 'REVERSED']);
            })->get();

        $rows = [];
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($lines as $ln) {
            $dr = floatval($ln->debit);
            $cr = floatval($ln->credit);
            $totalDr += $dr;
            $totalCr += $cr;

            $rows[] = [
                'date' => is_object($ln->journalEntry->entry_date) ? $ln->journalEntry->entry_date->format('Y-m-d') : (string)$ln->journalEntry->entry_date,
                'entry_number' => $ln->journalEntry->journal_number ?: $ln->journalEntry->entry_number,
                'type' => $ln->journalEntry->entry_type ?: 'JOURNAL',
                'account_name' => $ln->account?->account_name ?: 'Account #' . $ln->account_id,
                'description' => $ln->description ?: ($ln->journalEntry->description ?: $ln->journalEntry->narration),
                'debit' => $dr,
                'credit' => $cr,
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_debit' => $totalDr,
                'total_credit' => $totalCr,
                'entries_count' => count($rows),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'entry_number', 'label' => 'Voucher #', 'type' => 'text'],
                ['key' => 'type', 'label' => 'Type', 'type' => 'badge'],
                ['key' => 'account_name', 'label' => 'Particulars', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Narration', 'type' => 'text'],
                ['key' => 'debit', 'label' => 'Debit (₹)', 'type' => 'currency'],
                ['key' => 'credit', 'label' => 'Credit (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Journal Register
     */
    public static function getJournalRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $journals = JournalEntry::with('lines.account')
            ->where('company_id', $companyId)
            ->whereDate('entry_date', '>=', $dates['start_date'])
            ->whereDate('entry_date', '<=', $dates['end_date'])
            ->orderBy('entry_date', 'desc')
            ->get();

        $rows = $journals->map(fn($j) => [
            'id' => $j->id,
            'entry_number' => $j->journal_number ?: $j->entry_number,
            'date' => is_object($j->entry_date) ? $j->entry_date->format('Y-m-d') : (string)$j->entry_date,
            'voucher_type' => $j->entry_type ?: 'JOURNAL',
            'narration' => $j->description ?: ($j->narration ?: '-'),
            'total_amount' => floatval($j->total_debit ?: ($j->total_amount ?: $j->lines->sum('debit'))),
            'status' => $j->status ?: 'POSTED',
        ])->toArray();

        return [
            'period' => $dates,
            'summary_kpis' => [
                'journal_count' => count($rows),
                'total_journal_amount' => array_sum(array_column($rows, 'total_amount')),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'entry_number', 'label' => 'Journal #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'voucher_type', 'label' => 'Voucher Type', 'type' => 'badge'],
                ['key' => 'narration', 'label' => 'Narration', 'type' => 'text'],
                ['key' => 'total_amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }
}
