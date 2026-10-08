<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class ProfitLossService
{
    /**
     * Generate authentic Profit & Loss Statement from posted General Ledger lines.
     */
    public static function getProfitAndLoss(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null, ?string $financialYear = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');

        // Pre-query all journal line sums in date range grouped by account_id
        $baseLinesQuery = JournalLine::where('journal_lines.company_id', $companyId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate]);

        if ($branchId) {
            $baseLinesQuery->where('journal_lines.branch_id', $branchId);
        }

        $lineSumsByAccount = $baseLinesQuery->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as deb, SUM(journal_lines.credit) as cred')
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        // Helper to sum net activity for accounts of a given type/subtype
        $getAccountNet = function (array $subtypes, string $normalNature = 'CREDIT') use ($companyId, $lineSumsByAccount) {
            $accounts = ChartOfAccount::where('company_id', $companyId)
                ->where(function ($q) use ($subtypes) {
                    $q->whereIn('account_subtype', $subtypes)
                      ->orWhereIn('account_type', $subtypes);
                })
                ->get();

            $total = 0.0;
            $items = [];

            foreach ($accounts as $acc) {
                $sums = $lineSumsByAccount->get($acc->id);
                $deb = floatval($sums ? $sums->deb : 0);
                $cred = floatval($sums ? $sums->cred : 0);

                $net = ($normalNature === 'CREDIT') ? round($cred - $deb, 2) : round($deb - $cred, 2);

                if (abs($net) > 0.001) {
                    $items[] = [
                        'account_id' => $acc->id,
                        'account_code' => $acc->account_code,
                        'account_name' => $acc->account_name,
                        'amount' => $net,
                    ];
                    $total += $net;
                }
            }

            return ['total' => round($total, 2), 'items' => $items];
        };

        // 1. REVENUE
        $salesRevenue = $getAccountNet(['SALES', 'SERVICE_INCOME'], 'CREDIT');
        $salesReturns = $getAccountNet(['SALES_RETURN'], 'DEBIT');
        $salesDiscounts = $getAccountNet(['SALES_DISCOUNT'], 'DEBIT');
        $netRevenue = round($salesRevenue['total'] - $salesReturns['total'] - $salesDiscounts['total'], 2);

        // 2. COST OF GOODS SOLD (Uses COGS from double-entry accounting; does not treat all purchases as COGS)
        $cogs = $getAccountNet(['COGS'], 'DEBIT');
        $grossProfit = round($netRevenue - $cogs['total'], 2);

        // 3. OPERATING EXPENSES
        $operatingExpenses = $getAccountNet([
            'OPERATING_EXPENSES', 'RENT', 'SALARY', 'UTILITIES', 'TRANSPORTATION', 'BANK_CHARGES', 'ADMIN_EXPENSES', 'INDIRECT_EXPENSE'
        ], 'DEBIT');

        $operatingProfit = round($grossProfit - $operatingExpenses['total'], 2);

        // 4. OTHER INCOME & EXPENSES
        $otherIncome = $getAccountNet(['OTHER_INCOME'], 'CREDIT');
        $otherExpenses = $getAccountNet(['OTHER_EXPENSES'], 'DEBIT');

        $netProfit = round($operatingProfit + $otherIncome['total'] - $otherExpenses['total'], 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'financial_year' => $financialYear ?: '2026-27',
            'revenue' => [
                'gross_sales' => $salesRevenue['total'],
                'sales_returns' => $salesReturns['total'],
                'sales_discounts' => $salesDiscounts['total'],
                'net_revenue' => $netRevenue,
                'breakdown' => $salesRevenue['items'],
            ],
            'cogs' => [
                'total_cogs' => $cogs['total'],
                'breakdown' => $cogs['items'],
            ],
            'gross_profit' => $grossProfit,
            'operating_expenses' => [
                'total_expenses' => $operatingExpenses['total'],
                'breakdown' => $operatingExpenses['items'],
            ],
            'operating_profit' => $operatingProfit,
            'other_income' => [
                'total' => $otherIncome['total'],
                'breakdown' => $otherIncome['items'],
            ],
            'other_expenses' => [
                'total' => $otherExpenses['total'],
                'breakdown' => $otherExpenses['items'],
            ],
            'net_profit' => $netProfit,
        ];
    }
}
