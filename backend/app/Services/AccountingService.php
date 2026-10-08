<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Branch;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\TrialBalanceService;
use App\Services\ProfitLossService;
use App\Services\BalanceSheetService;
use App\Services\LedgerService;
use App\Services\ReceivableService;
use App\Services\PayableService;
use App\Services\PeriodService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class AccountingService
{
    /**
     * CORE INVARIANT: Centralized transactional journal posting engine.
     * Enforces SUM(Debit) === SUM(Credit) with zero delta tolerance.
     *
     * @param array $data
     * @param int $companyId
     * @param int|null $branchId
     * @param string $userName
     * @return JournalEntry
     * @throws \InvalidArgumentException
     */
    public static function postJournal(
        array $data,
        int $companyId,
        ?int $branchId = null,
        string $userName = 'Admin'
    ): JournalEntry {
        $data['company_id'] = $companyId;
        if ($branchId) {
            $data['branch_id'] = $branchId;
        }

        // 1. Period Locking check
        $entryDate = $data['entry_date'] ?? date('Y-m-d');
        PeriodService::assertNotLocked($companyId, $entryDate);

        // 2. Validate Lines Structure
        $lines = $data['lines'] ?? [];
        if (!is_array($lines) || count($lines) < 2) {
            throw new \InvalidArgumentException("Double-entry accounting requires at least 2 journal lines. Provided: " . (is_array($lines) ? count($lines) : 0));
        }

        AccountService::ensureDefaultAccounts($companyId);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $validatedLines = [];

        foreach ($lines as $idx => $line) {
            $accountId = intval($line['account_id'] ?? 0);
            $account = ChartOfAccount::where('company_id', $companyId)->find($accountId);

            if (!$account) {
                throw new \InvalidArgumentException("Journal line #{$idx} references invalid or non-existent Account ID: {$accountId}.");
            }
            if (!$account->is_active) {
                throw new \InvalidArgumentException("Account '{$account->account_name}' ({$account->account_code}) is inactive and cannot accept postings.");
            }

            $debit = round(floatval($line['debit'] ?? 0), 2);
            $credit = round(floatval($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw new \InvalidArgumentException("Journal line #{$idx} cannot have negative values (Debit: {$debit}, Credit: {$credit}).");
            }
            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException("Journal line #{$idx} cannot have both Debit and Credit amounts simultaneously.");
            }
            if ($debit == 0 && $credit == 0) {
                continue; // Skip neutral lines
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $validatedLines[] = [
                'company_id' => $companyId,
                'branch_id' => $branchId ?: ($account->branch_id ?? 1),
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? ($data['description'] ?? "Entry for {$account->account_name}"),
                'party_type' => $line['party_type'] ?? null,
                'party_id' => !empty($line['party_id']) ? intval($line['party_id']) : null,
                'product_id' => !empty($line['product_id']) ? intval($line['product_id']) : null,
                'cost_center' => $line['cost_center'] ?? null,
                'tax_category' => $line['tax_category'] ?? null,
            ];
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        // 3. CORE INVARIANT: Strict Mathematical Balancing
        if (abs($totalDebit - $totalCredit) > 0.001) {
            $delta = round(abs($totalDebit - $totalCredit), 2);
            throw new \InvalidArgumentException(
                "UNBALANCED JOURNAL REJECTED: Total Debit (₹{$totalDebit}) must exactly equal Total Credit (₹{$totalCredit}). Discrepancy: ₹{$delta}."
            );
        }

        if (count($validatedLines) < 2) {
            throw new \InvalidArgumentException("Double-entry accounting requires at least 2 non-zero posting lines.");
        }

        $data['lines'] = $validatedLines;
        return JournalService::createJournalEntry($data, $userName);
    }

    /**
     * Get Chart of Accounts hierarchy for a company.
     */
    public static function getChartOfAccounts(int $companyId, array $filters = []): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $query = ChartOfAccount::where('company_id', $companyId);

        if (!empty($filters['account_type'])) {
            $query->where('account_type', strtoupper($filters['account_type']));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('account_code', 'LIKE', $term)
                  ->orWhere('account_name', 'LIKE', $term)
                  ->orWhere('account_subtype', 'LIKE', $term);
            });
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool)$filters['is_active']);
        }

        return $query->orderBy('account_code', 'asc')->get()->toArray();
    }

    /**
     * General Ledger statement for an account or entire company.
     */
    public static function getGeneralLedger(
        int $companyId,
        ?int $accountId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        if ($accountId) {
            return LedgerService::getAccountLedger($companyId, $accountId, $fromDate, $toDate, $branchId);
        }
        return LedgerService::getGeneralLedger($companyId, $fromDate, $toDate, $branchId);
    }

    /**
     * Party (Customer / Supplier) Ledger Statement.
     */
    public static function getPartyLedger(
        int $companyId,
        string $partyType,
        int $partyId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        return LedgerService::getPartyLedger($companyId, strtoupper($partyType), $partyId, $fromDate, $toDate);
    }

    /**
     * Cash Book: Filtered view of all Cash Account debits and credits.
     */
    public static function getCashBook(
        int $companyId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        $cashAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
        return LedgerService::getAccountLedger($companyId, $cashAcc->id, $fromDate, $toDate, $branchId);
    }

    /**
     * Bank Book: Filtered view of all Bank Account debits and credits.
     */
    public static function getBankBook(
        int $companyId,
        ?int $accountId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        if (!$accountId) {
            $bankAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');
            $accountId = $bankAcc ? $bankAcc->id : null;
        }
        return LedgerService::getAccountLedger($companyId, $accountId, $fromDate, $toDate, $branchId);
    }

    /**
     * Accounts Receivable Summary & Customer Outstandings.
     */
    public static function getReceivables(int $companyId, ?int $branchId = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $receivables = ReceivableService::getCustomerReceivables($companyId, $branchId);
        $totalDue = 0.0;
        foreach ($receivables as $r) {
            $totalDue += floatval($r['total_due'] ?? 0);
        }
        return [
            'total_receivable' => round($totalDue, 2),
            'customers' => $receivables,
        ];
    }

    /**
     * Accounts Payable Summary & Supplier Payables.
     */
    public static function getPayables(int $companyId, ?int $branchId = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $payables = PayableService::getSupplierPayables($companyId, $branchId);
        $totalDue = 0.0;
        foreach ($payables as $p) {
            $totalDue += floatval($p['total_due'] ?? 0);
        }
        return [
            'total_payable' => round($totalDue, 2),
            'suppliers' => $payables,
        ];
    }

    /**
     * Trial Balance Report (Mathematical verification: Debit Sum === Credit Sum).
     */
    public static function getTrialBalance(
        int $companyId,
        ?string $asOfDate = null,
        ?int $branchId = null,
        ?string $financialYear = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        return TrialBalanceService::getTrialBalance($companyId, $asOfDate, $branchId, $financialYear);
    }

    /**
     * Profit and Loss Statement.
     */
    public static function getProfitAndLoss(
        int $companyId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null,
        ?string $financialYear = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        return ProfitLossService::getProfitAndLoss($companyId, $fromDate, $toDate, $branchId, $financialYear);
    }

    /**
     * Balance Sheet Statement (Assets === Liabilities + Equity).
     */
    public static function getBalanceSheet(
        int $companyId,
        ?string $asOfDate = null,
        ?int $branchId = null,
        ?string $financialYear = null
    ): array {
        AccountService::ensureDefaultAccounts($companyId);
        return BalanceSheetService::getBalanceSheet($companyId, $asOfDate, $branchId, $financialYear);
    }

    /**
     * Complete Ledger Integrity & Invariant Audit.
     * Scans every posted journal entry in the database and verifies zero unbalanced journals.
     *
     * @param int|null $companyId Null for entire database global audit, or specific company ID
     * @return array
     */
    public static function auditLedgerIntegrity(?int $companyId = null): array
    {
        if ($companyId) {
            AccountService::ensureDefaultAccounts($companyId);
        }

        // 1. Audit individual journal entries for balancing
        $query = DB::table('journal_entries as j')
            ->join('journal_lines as l', 'j.id', '=', 'l.journal_entry_id')
            ->whereIn('j.status', ['POSTED', 'REVERSED']);

        if ($companyId) {
            $query->where('j.company_id', $companyId);
        }

        $unbalancedJournals = $query->selectRaw('
                j.id,
                j.company_id,
                j.journal_number,
                j.entry_type,
                j.entry_date,
                j.status,
                ROUND(SUM(l.debit), 2) as sum_debit,
                ROUND(SUM(l.credit), 2) as sum_credit,
                ROUND(ABS(SUM(l.debit) - SUM(l.credit)), 2) as discrepancy
            ')
            ->groupBy('j.id', 'j.company_id', 'j.journal_number', 'j.entry_type', 'j.entry_date', 'j.status')
            ->having('discrepancy', '>', 0.001)
            ->get();

        // 2. Global totals
        $totQuery = DB::table('journal_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->whereIn('j.status', ['POSTED', 'REVERSED']);

        if ($companyId) {
            $totQuery->where('j.company_id', $companyId);
        }

        $globalTotals = $totQuery->selectRaw('
                COUNT(DISTINCT j.id) as total_journals,
                COUNT(l.id) as total_lines,
                ROUND(SUM(l.debit), 2) as total_debits,
                ROUND(SUM(l.credit), 2) as total_credits,
                ROUND(ABS(SUM(l.debit) - SUM(l.credit)), 2) as global_discrepancy
            ')
            ->first();

        // 3. Trial balance verification (for active company if set)
        $tb = $companyId ? TrialBalanceService::getTrialBalance($companyId) : ['is_balanced' => true, 'difference' => 0.0];

        // 4. Balance sheet equation verification (for active company if set)
        $bs = $companyId ? BalanceSheetService::getBalanceSheet($companyId) : ['is_balanced' => true, 'difference' => 0.0];

        $isStrictlyBalanced = (
            count($unbalancedJournals) === 0 &&
            floatval($globalTotals->global_discrepancy ?? 0) < 0.001 &&
            $tb['is_balanced']
        );

        return [
            'company_id' => $companyId,
            'is_strictly_balanced' => $isStrictlyBalanced,
            'total_journals_audited' => intval($globalTotals->total_journals ?? 0),
            'total_lines_audited' => intval($globalTotals->total_lines ?? 0),
            'total_debits' => floatval($globalTotals->total_debits ?? 0),
            'total_credits' => floatval($globalTotals->total_credits ?? 0),
            'global_discrepancy' => floatval($globalTotals->global_discrepancy ?? 0),
            'unbalanced_journals_count' => count($unbalancedJournals),
            'unbalanced_journals' => $unbalancedJournals->toArray(),
            'trial_balance_balanced' => $tb['is_balanced'],
            'trial_balance_difference' => $tb['difference'],
            'balance_sheet_balanced' => $bs['is_balanced'] ?? true,
            'balance_sheet_difference' => $bs['difference'] ?? 0.0,
        ];
    }
}
