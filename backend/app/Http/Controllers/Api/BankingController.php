<?php

namespace App\Http\Controllers\Api;

use App\Http\Middleware\AuthMiddleware;
use App\Services\BankAccountService;
use App\Services\BankTransactionService;
use App\Services\BankTransferService;
use App\Services\BankStatementService;
use App\Services\BankMatchingService;
use App\Services\BankReconciliationService;
use App\Services\BankingReportService;
use App\Services\PermissionManager;
use Exception;

class BankingController
{
    protected function getAuth()
    {
        $user = AuthMiddleware::getUser();
        $companyId = AuthMiddleware::getCompanyId();
        $branchId = AuthMiddleware::getBranchId();
        return [$user, $companyId, $branchId];
    }

    public function getOverview()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $data = BankAccountService::getBankingOverview($companyId, $branchId);
            return response_json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getAccounts()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $accounts = BankAccountService::getBankAccounts($companyId, $branchId);
            $cashAccounts = BankAccountService::getCashAccounts($companyId);
            return response_json([
                'status' => 'success',
                'data' => [
                    'bank_accounts' => $accounts,
                    'cash_accounts' => $cashAccounts,
                ]
            ]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function storeAccount()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $input['branch_id'] = $input['branch_id'] ?? $branchId;
            $account = BankAccountService::createBankAccount($companyId, $input, $user->name);
            return response_json(['status' => 'success', 'data' => $account], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function showAccount(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $account = BankAccountService::getBankAccountById($companyId, $id);
            if (!$account) {
                return response_json(['status' => 'error', 'message' => 'Bank account not found'], 404);
            }
            return response_json(['status' => 'success', 'data' => $account]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateAccount(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'edit')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $account = BankAccountService::updateBankAccount($companyId, $id, $input, $user->name);
            return response_json(['status' => 'success', 'data' => $account]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteAccount(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'edit')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            BankAccountService::deleteBankAccount($companyId, $id, $user->name);
            return response_json(['status' => 'success', 'message' => 'Bank account deactivated successfully']);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getTransactions(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $filters = $_GET ?? [];
        $filters['bank_account_id'] = $id;

        try {
            $txns = BankTransactionService::getTransactions($companyId, $filters);
            return response_json(['status' => 'success', 'data' => $txns]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function storeTransaction(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        $txnType = strtoupper($input['transaction_type'] ?? 'OTHER');

        try {
            if ($txnType === 'BANK_CHARGE') {
                $txn = BankTransactionService::recordBankCharge(
                    $companyId,
                    $id,
                    floatval($input['amount'] ?? 0),
                    $input['description'] ?? 'Bank charge',
                    $input['reference_number'] ?? null,
                    $input['transaction_date'] ?? null,
                    $user->name
                );
            } elseif ($txnType === 'INTEREST') {
                $txn = BankTransactionService::recordBankInterest(
                    $companyId,
                    $id,
                    floatval($input['amount'] ?? 0),
                    $input['description'] ?? 'Bank interest income',
                    $input['reference_number'] ?? null,
                    $input['transaction_date'] ?? null,
                    $user->name
                );
            } else {
                $txn = BankTransactionService::recordTransaction(
                    companyId: $companyId,
                    bankAccountId: $id,
                    debitCredit: strtoupper($input['type'] ?? ($input['debit_credit'] ?? 'CREDIT')),
                    amount: floatval($input['amount'] ?? 0),
                    transactionType: $txnType,
                    description: $input['description'] ?? 'Transaction',
                    referenceNo: $input['reference_number'] ?? null,
                    transactionDate: $input['transaction_date'] ?? null,
                    branchId: $branchId
                );
            }

            return response_json(['status' => 'success', 'data' => $txn], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function excludeTransaction(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'edit')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        $reason = $input['reason'] ?? 'Excluded by user';

        try {
            $txn = BankTransactionService::excludeTransaction($companyId, $id, $reason, $user->name);
            return response_json(['status' => 'success', 'data' => $txn]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getTransfers()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $transfers = BankTransferService::getTransfers($companyId, $_GET ?? []);
            return response_json(['status' => 'success', 'data' => $transfers]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function storeTransfer()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $transfer = BankTransferService::executeTransfer(
                companyId: $companyId,
                fromType: $input['from_type'] ?? 'BANK',
                fromAccountId: intval($input['from_account_id'] ?? 0),
                toType: $input['to_type'] ?? 'BANK',
                toAccountId: intval($input['to_account_id'] ?? 0),
                amount: floatval($input['amount'] ?? 0),
                transferDate: $input['transfer_date'] ?? null,
                referenceNo: $input['reference_number'] ?? null,
                notes: $input['notes'] ?? null,
                branchId: $branchId,
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $transfer], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function previewStatement()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'import')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $preview = BankStatementService::previewStatement(
                companyId: $companyId,
                bankAccountId: intval($input['bank_account_id'] ?? 0),
                rawRows: $input['rows'] ?? [],
                fileName: $input['file_name'] ?? 'statement.csv',
                columnMapping: $input['column_mapping'] ?? null
            );

            return response_json(['status' => 'success', 'data' => $preview]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function confirmStatement()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'import')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $res = BankStatementService::confirmImport(
                companyId: $companyId,
                bankAccountId: intval($input['bank_account_id'] ?? 0),
                rawRows: $input['rows'] ?? [],
                fileName: $input['file_name'] ?? 'statement.csv',
                columnMapping: $input['column_mapping'] ?? null,
                skipDuplicates: !empty($input['skip_duplicates']),
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $res], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function startReconciliation()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $rec = BankReconciliationService::startReconciliation(
                companyId: $companyId,
                bankAccountId: intval($input['bank_account_id'] ?? 0),
                startDate: $input['start_date'] ?? date('Y-m-01'),
                endDate: $input['end_date'] ?? date('Y-m-t'),
                statementClosingBalance: floatval($input['statement_closing_balance'] ?? 0.0),
                statementOpeningBalance: floatval($input['statement_opening_balance'] ?? 0.0),
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $rec], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function suggestMatches(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $rec = BankReconciliationService::getReconciliationReport($companyId, $id);
            $suggestions = BankMatchingService::findMatches(
                $companyId,
                $rec['bank_account_id'],
                $rec['statement_opening_balance'] ? explode(' to ', $rec['statement_period'])[0] : null,
                $rec['statement_opening_balance'] ? explode(' to ', $rec['statement_period'])[1] : null
            );

            return response_json(['status' => 'success', 'data' => $suggestions]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function matchTransaction(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $match = BankReconciliationService::matchTransaction(
                companyId: $companyId,
                reconciliationId: $id,
                bankTransactionId: intval($input['bank_transaction_id'] ?? 0),
                journalLineId: isset($input['journal_line_id']) ? intval($input['journal_line_id']) : null,
                matchedAmount: isset($input['matched_amount']) ? floatval($input['matched_amount']) : null,
                matchType: $input['match_type'] ?? 'ONE_TO_ONE',
                notes: $input['notes'] ?? null,
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $match], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function unmatchTransaction(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            BankReconciliationService::unmatchTransaction($companyId, $id, intval($input['match_id'] ?? 0), $user->name);
            return response_json(['status' => 'success', 'message' => 'Unmatched transaction successfully']);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function createMissingEntry(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $match = BankReconciliationService::createMissingEntry(
                companyId: $companyId,
                reconciliationId: $id,
                bankTransactionId: intval($input['bank_transaction_id'] ?? 0),
                entryType: $input['entry_type'] ?? 'BANK_CHARGE',
                contraAccountId: isset($input['contra_account_id']) ? intval($input['contra_account_id']) : null,
                description: $input['description'] ?? null,
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $match], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function completeReconciliation(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reconcile')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $rec = BankReconciliationService::completeReconciliation($companyId, $id, $user->name);
            return response_json(['status' => 'success', 'data' => $rec]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function reopenReconciliation(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'reopen_reconciliation')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden - Reopen reconciliation requires authorized privilege.'], 403);
        }

        $input = get_json_input();
        $reason = $input['reason'] ?? 'Reopened for corrections';

        try {
            $rec = BankReconciliationService::reopenReconciliation($companyId, $id, $reason, $user->name);
            return response_json(['status' => 'success', 'data' => $rec]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getCashBook()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'reports', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $start = $_GET['start_date'] ?? null;
        $end = $_GET['end_date'] ?? null;

        try {
            $data = BankingReportService::getCashBook($companyId, $start, $end);
            return response_json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getBankBook()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'reports', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $bankAccountId = isset($_GET['bank_account_id']) ? intval($_GET['bank_account_id']) : null;
        $start = $_GET['start_date'] ?? null;
        $end = $_GET['end_date'] ?? null;

        try {
            $data = BankingReportService::getBankBook($companyId, $bankAccountId, $start, $end);
            return response_json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getReconciliationReport(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $report = BankReconciliationService::getReconciliationReport($companyId, $id);
            return response_json(['status' => 'success', 'data' => $report]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
