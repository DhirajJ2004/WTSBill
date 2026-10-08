<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use Illuminate\Database\Capsule\Manager as DB;

class TrialBalanceService
{
    /**
     * Generate Trial Balance as of a specific date.
     */
    public static function getTrialBalance(int $companyId, ?string $asOfDate = null, ?int $branchId = null, ?string $financialYear = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $asOfDate = $asOfDate ?: date('Y-m-d');

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code', 'asc')
            ->get();

        // Calculate all posted journal line sums in a single bulk query
        $linesQuery = JournalLine::where('journal_lines.company_id', $companyId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->where('journal_entries.entry_date', '<=', $asOfDate);

        if ($branchId) {
            $linesQuery->where('journal_lines.branch_id', $branchId);
        }

        $sumsByAccount = $linesQuery->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $acc) {
            $nature = $acc->getNormalNature();
            $lineSums = $sumsByAccount->get($acc->id);

            $debSum = floatval($lineSums ? $lineSums->total_debit : 0);
            $credSum = floatval($lineSums ? $lineSums->total_credit : 0);

            $initialOpening = floatval($acc->opening_balance);
            $initialType = strtoupper($acc->opening_balance_type ?: 'DEBIT');

            if ($initialType === 'DEBIT') {
                $debSum += $initialOpening;
            } else {
                $credSum += $initialOpening;
            }

            $netBalance = round($debSum - $credSum, 2);

            $debitAmount = 0.0;
            $creditAmount = 0.0;

            if ($netBalance > 0.001) {
                $debitAmount = $netBalance;
            } elseif ($netBalance < -0.001) {
                $creditAmount = abs($netBalance);
            }

            if ($debitAmount > 0 || $creditAmount > 0) {
                $rows[] = [
                    'account_id' => $acc->id,
                    'account_code' => $acc->account_code,
                    'account_name' => $acc->account_name,
                    'account_type' => $acc->account_type,
                    'account_subtype' => $acc->account_subtype,
                    'nature' => $nature,
                    'debit' => $debitAmount,
                    'credit' => $creditAmount,
                ];

                $totalDebit += $debitAmount;
                $totalCredit += $creditAmount;
            }
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $difference = round(abs($totalDebit - $totalCredit), 2);
        $isBalanced = ($difference <= 0.001);

        return [
            'as_of_date' => $asOfDate,
            'financial_year' => $financialYear ?: '2026-27',
            'is_balanced' => $isBalanced,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'accounts' => $rows,
        ];
    }
}
