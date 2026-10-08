<?php

namespace App\Http\Controllers\Api;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\AccountingPeriod;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\LedgerService;
use App\Services\TrialBalanceService;
use App\Services\ProfitLossService;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\PeriodService;
use App\Services\ReconciliationService;
use App\Services\AuditLogService;

class AccountingController
{
    /**
     * List all accounts in Chart of Accounts.
     */
    public function indexAccounts()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;

        AccountService::ensureDefaultAccounts($companyId);

        $query = ChartOfAccount::where('company_id', $companyId)->with(['parent', 'children']);

        if (!empty($_GET['type'])) {
            $query->where('account_type', strtoupper(trim($_GET['type'])));
        }
        if (!empty($_GET['subtype'])) {
            $query->where('account_subtype', strtoupper(trim($_GET['subtype'])));
        }
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('account_name', 'like', "%{$search}%")
                  ->orWhere('account_code', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('account_code', 'asc')->get();

        return response_json(['status' => 'success', 'data' => $accounts]);
    }

    /**
     * Get hierarchical account tree.
     */
    public function treeAccounts()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;

        $tree = AccountService::getAccountTree($companyId);
        return response_json(['status' => 'success', 'data' => $tree]);
    }

    /**
     * Show single account.
     */
    public function showAccount($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $account = ChartOfAccount::where('company_id', $user->current_company_id)
            ->with(['parent', 'children'])
            ->findOrFail(intval($id));

        return response_json(['status' => 'success', 'data' => $account]);
    }

    /**
     * Create custom account in Chart of Accounts.
     */
    public function storeAccount()
    {
        $user = AuthMiddleware::authorize('accounting', 'create');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $code = trim($input['account_code'] ?? '');
        $name = trim($input['account_name'] ?? '');
        $type = strtoupper(trim($input['account_type'] ?? 'ASSET'));
        $subtype = !empty($input['account_subtype']) ? strtoupper(trim($input['account_subtype'])) : null;

        if (empty($code) || empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Account code and name are required.'], 422);
        }

        $exists = ChartOfAccount::where('company_id', $companyId)->where('account_code', $code)->exists();
        if ($exists) {
            return response_json(['status' => 'error', 'message' => "Account code '{$code}' already exists in this business."], 422);
        }

        $account = ChartOfAccount::create([
            'company_id' => $companyId,
            'branch_id' => AuthMiddleware::getBranchId(),
            'parent_account_id' => !empty($input['parent_account_id']) ? intval($input['parent_account_id']) : null,
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => $type,
            'account_subtype' => $subtype,
            'nature' => $input['nature'] ?? (in_array($type, ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT'),
            'opening_balance' => floatval($input['opening_balance'] ?? 0),
            'opening_balance_type' => $input['opening_balance_type'] ?? 'DEBIT',
            'is_system_account' => false,
            'is_active' => true,
            'description' => $input['description'] ?? null,
        ]);

        AuditLogService::log(
            $companyId,
            $user->name,
            'ACCOUNT_CREATE',
            'ChartOfAccount',
            $account->id,
            "Created Account '{$account->account_name}' ({$account->account_code})"
        );

        return response_json(['status' => 'success', 'data' => $account], 201);
    }

    /**
     * List Journal Entries with multi-criteria filtering.
     */
    public function indexJournals()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;

        $query = JournalEntry::where('company_id', $companyId)
            ->with(['lines.account']);

        if (!empty($_GET['entry_type'])) {
            $query->where('entry_type', strtoupper(trim($_GET['entry_type'])));
        }
        if (!empty($_GET['status'])) {
            $query->where('status', strtoupper(trim($_GET['status'])));
        }
        if (!empty($_GET['from_date'])) {
            $query->where('entry_date', '>=', $_GET['from_date']);
        }
        if (!empty($_GET['to_date'])) {
            $query->where('entry_date', '<=', $_GET['to_date']);
        }
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%");
            });
        }

        $journals = $query->orderBy('entry_date', 'desc')->orderBy('id', 'desc')->limit(200)->get();

        return response_json(['status' => 'success', 'data' => $journals]);
    }

    /**
     * Show single journal entry details with all lines.
     */
    public function showJournal($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $journal = JournalEntry::where('company_id', $user->current_company_id)
            ->with(['lines.account', 'originalJournal', 'reversalJournal'])
            ->findOrFail(intval($id));

        return response_json(['status' => 'success', 'data' => $journal]);
    }

    /**
     * Create Manual Double-Entry Journal.
     */
    public function storeJournal()
    {
        $user = AuthMiddleware::authorize('accounting', 'create');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $input['company_id'] = $companyId;
        $input['branch_id'] = AuthMiddleware::getBranchId();
        $input['financial_year'] = AuthMiddleware::getFinancialYear();

        try {
            $journal = JournalService::createJournalEntry($input, $user->name);
            return response_json([
                'status' => 'success',
                'message' => "Journal #{$journal->journal_number} created and posted successfully.",
                'data' => $journal,
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Post a draft journal entry.
     */
    public function postJournal($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'post');
        try {
            $journal = JournalService::postJournalEntry(intval($id), $user->name);
            return response_json([
                'status' => 'success',
                'message' => "Journal #{$journal->journal_number} posted successfully.",
                'data' => $journal,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverseJournal($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'reverse');
        $input = get_json_input();
        $reason = trim($input['reason'] ?? 'Reversed by authorized user');

        try {
            $reversal = JournalService::reverseJournalEntry(intval($id), $reason, $user->name);
            return response_json([
                'status' => 'success',
                'message' => "Journal entry reversed successfully via Reversal #{$reversal->journal_number}.",
                'data' => $reversal,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * General Ledger.
     */
    public function getGeneralLedger()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();
        $financialYear = AuthMiddleware::getFinancialYear();

        $ledger = LedgerService::getGeneralLedger($companyId, $fromDate, $toDate, $branchId, $financialYear);
        return response_json(['status' => 'success', 'data' => $ledger]);
    }

    /**
     * Single Account Ledger.
     */
    public function getAccountLedger($accountId)
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();
        $financialYear = AuthMiddleware::getFinancialYear();

        try {
            $ledger = LedgerService::getAccountLedger($companyId, intval($accountId), $fromDate, $toDate, $branchId, $financialYear);
            return response_json(['status' => 'success', 'data' => $ledger]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Trial Balance.
     */
    public function getTrialBalance()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;
        $asOfDate = $_GET['as_of_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();
        $financialYear = AuthMiddleware::getFinancialYear();

        $tb = TrialBalanceService::getTrialBalance($companyId, $asOfDate, $branchId, $financialYear);
        return response_json(['status' => 'success', 'data' => $tb]);
    }

    /**
     * Profit & Loss.
     */
    public function getProfitLoss()
    {
        $user = AuthMiddleware::authorize('accounting', 'view_financial_data');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();
        $financialYear = AuthMiddleware::getFinancialYear();

        $pnl = ProfitLossService::getProfitAndLoss($companyId, $fromDate, $toDate, $branchId, $financialYear);
        return response_json(['status' => 'success', 'data' => $pnl]);
    }

    /**
     * Balance Sheet.
     */
    public function getBalanceSheet()
    {
        $user = AuthMiddleware::authorize('accounting', 'view_financial_data');
        $companyId = $user->current_company_id;
        $asOfDate = $_GET['as_of_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();
        $financialYear = AuthMiddleware::getFinancialYear();

        $bs = BalanceSheetService::getBalanceSheet($companyId, $asOfDate, $branchId, $financialYear);
        return response_json(['status' => 'success', 'data' => $bs]);
    }

    /**
     * Cash Flow.
     */
    public function getCashFlow()
    {
        $user = AuthMiddleware::authorize('accounting', 'view_financial_data');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();

        $cf = CashFlowService::getCashFlow($companyId, $fromDate, $toDate, $branchId);
        return response_json(['status' => 'success', 'data' => $cf]);
    }

    /**
     * Get Accounting Periods.
     */
    public function getPeriods()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;

        $periods = PeriodService::getPeriods($companyId);
        return response_json(['status' => 'success', 'data' => $periods]);
    }

    /**
     * Lock an accounting period.
     */
    public function lockPeriod()
    {
        $user = AuthMiddleware::authorize('accounting', 'period');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $fy = $input['financial_year'] ?? '2026-27';
        $periodName = $input['period_name'] ?? 'Q1';
        $startDate = $input['start_date'] ?? date('Y-04-01');
        $endDate = $input['end_date'] ?? date('Y-06-30');
        $reason = $input['reason'] ?? 'Financial period audit closed';

        try {
            $period = PeriodService::lockPeriod($companyId, $fy, $periodName, $startDate, $endDate, $reason, $user->name);
            return response_json([
                'status' => 'success',
                'message' => "Accounting period '{$periodName}' locked successfully.",
                'data' => $period,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unlock an accounting period.
     */
    public function unlockPeriod($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'period');
        try {
            $period = PeriodService::unlockPeriod($user->current_company_id, intval($id), $user->name);
            return response_json([
                'status' => 'success',
                'message' => "Accounting period '{$period->period_name}' unlocked.",
                'data' => $period,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Run Double-Entry Reconciliation check.
     */
    public function runReconciliation()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = $user->current_company_id;
        $asOfDate = $_GET['as_of_date'] ?? null;

        $check = ReconciliationService::runReconciliationCheck($companyId, $asOfDate);
        return response_json(['status' => 'success', 'data' => $check]);
    }
}
