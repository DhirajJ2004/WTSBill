<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\JournalEntry;
use Illuminate\Database\Capsule\Manager as DB;

class LedgerService
{
    /**
     * Get detailed General Ledger for a specific account with running balances.
     */
    public static function getAccountLedger(int $companyId, int $accountId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null, ?string $financialYear = null): array
    {
        $account = ChartOfAccount::where('company_id', $companyId)->findOrFail($accountId);
        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');
        $nature = $account->getNormalNature(); // DEBIT or CREDIT

        // 1. Calculate opening balance before $fromDate
        $priorLinesQuery = JournalLine::where('journal_lines.company_id', $companyId)
            ->where('journal_lines.account_id', $accountId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->where('journal_entries.entry_date', '<', $fromDate);

        if ($branchId) {
            $priorLinesQuery->where('journal_lines.branch_id', $branchId);
        }

        $priorDebit = floatval($priorLinesQuery->sum('journal_lines.debit'));
        $priorCredit = floatval($priorLinesQuery->sum('journal_lines.credit'));

        $initialOpening = floatval($account->opening_balance);
        $initialType = strtoupper($account->opening_balance_type ?: 'DEBIT');

        if ($nature === 'DEBIT') {
            $baseOpening = ($initialType === 'DEBIT') ? $initialOpening : -$initialOpening;
            $openingBalance = round($baseOpening + $priorDebit - $priorCredit, 2);
        } else {
            $baseOpening = ($initialType === 'CREDIT') ? $initialOpening : -$initialOpening;
            $openingBalance = round($baseOpening + $priorCredit - $priorDebit, 2);
        }

        // 2. Fetch period journal lines
        $query = JournalLine::where('journal_lines.company_id', $companyId)
            ->where('journal_lines.account_id', $accountId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->select(
                'journal_lines.*',
                'journal_entries.journal_number',
                'journal_entries.entry_date',
                'journal_entries.entry_type',
                'journal_entries.reference_type',
                'journal_entries.reference_id',
                'journal_entries.description as journal_description'
            );

        if ($branchId) {
            $query->where('journal_lines.branch_id', $branchId);
        }

        $lines = $query->orderBy('journal_entries.entry_date', 'asc')
            ->orderBy('journal_lines.id', 'asc')
            ->get();

        $runningBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $entries = [];

        foreach ($lines as $line) {
            $deb = floatval($line->debit);
            $cred = floatval($line->credit);

            if ($nature === 'DEBIT') {
                $runningBalance = round($runningBalance + $deb - $cred, 2);
            } else {
                $runningBalance = round($runningBalance + $cred - $deb, 2);
            }

            $totalDebit += $deb;
            $totalCredit += $cred;

            $entries[] = [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'journal_number' => $line->journal_number,
                'date' => $line->entry_date,
                'entry_type' => $line->entry_type,
                'reference_type' => $line->reference_type,
                'reference_id' => $line->reference_id,
                'description' => $line->description ?: $line->journal_description,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'debit' => $deb,
                'credit' => $cred,
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'account' => $account,
            'nature' => $nature,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'transactions' => $entries,
        ];
    }

    /**
     * Get General Ledger summary across all active accounts.
     */
    public static function getGeneralLedger(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null, ?string $financialYear = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code', 'asc')
            ->get();

        $results = [];
        foreach ($accounts as $acc) {
            $ledger = static::getAccountLedger($companyId, $acc->id, $fromDate, $toDate, $branchId, $financialYear);
            if (count($ledger['transactions']) > 0 || abs($ledger['opening_balance']) > 0.001) {
                $results[] = $ledger;
            }
        }

        return $results;
    }
}
