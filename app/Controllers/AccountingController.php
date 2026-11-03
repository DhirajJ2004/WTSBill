<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuditLogger;

/**
 * Accounting Controller.
 * Manages Chart of Accounts, general ledger master, and double-entry balanced journal entries.
 */
class AccountingController
{
    /**
     * Chart of accounts view.
     */
    public function accountsIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;

        $accounts = Database::select(
            "SELECT id, account_code, account_name, account_type, current_balance, nature, is_active, is_system_account, description 
             FROM chart_of_accounts 
             WHERE company_id = ? AND deleted_at IS NULL 
             ORDER BY account_code ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('accounting/accounts', [
            'title' => 'Chart of Accounts',
            'currentUri' => '/accounting/accounts',
            'accounts' => $accounts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new ledger account in chart of accounts.
     */
    public function accountStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $name = trim((string)($payload['account_name'] ?? ''));
        $code = trim((string)($payload['account_code'] ?? ''));
        $type = strtoupper(trim((string)($payload['account_type'] ?? 'ASSET')));
        $openingBalance = (float)($payload['opening_balance'] ?? 0);
        $desc = trim((string)($payload['description'] ?? ''));

        if (empty($name)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Account name is required.'], 422);
            }
            Session::flash('error', 'Account name is required.');
            return Response::redirect('/accounting/accounts');
        }

        if (empty($code)) {
            $prefix = match($type) {
                'ASSET' => '1',
                'LIABILITY' => '2',
                'EQUITY' => '3',
                'INCOME', 'REVENUE' => '4',
                default => '5'
            };
            $code = $prefix . rand(1000, 9999);
        }

        $nature = in_array($type, ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT';

        try {
            $id = Database::insert('chart_of_accounts', [
                'company_id' => $companyId,
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'nature' => $nature,
                'opening_balance' => $openingBalance,
                'opening_balance_type' => $nature,
                'current_balance' => $openingBalance,
                'description' => $desc ?: null,
                'is_system_account' => 0,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Ledger account '{$name}' created successfully.",
                ], 201);
            }

            Session::flash('success', "Ledger account '{$name}' created successfully.");
            return Response::redirect('/accounting/accounts');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create account: ' . $e->getMessage());
            return Response::redirect('/accounting/accounts');
        }
    }

    /**
     * Show single account record for edit modal / inspection.
     * Route: GET /accounts/{id}
     */
    public function accountShow(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $accountId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->param('id') ?? $req->query('id') ?? 0)));

        $account = Database::selectOne(
            "SELECT id, account_code, account_name, account_type, current_balance, opening_balance, nature, description, is_system_account, is_active 
             FROM chart_of_accounts 
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$accountId, $companyId]
        );

        if (!$account) {
            return Response::json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        return Response::json([
            'success' => true,
            'account' => $account,
        ]);
    }

    /**
     * Update existing ledger account in chart of accounts.
     * Route: POST /accounts/{id}/update
     */
    public function accountUpdate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('accounting.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $accountId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM chart_of_accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$accountId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Account not found.'], 404);
            }
            Session::flash('error', 'Account not found.');
            return Response::redirect('/accounting/accounts');
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $name = trim((string)($payload['account_name'] ?? ''));
        $code = trim((string)($payload['account_code'] ?? ''));
        $type = strtoupper(trim((string)($payload['account_type'] ?? '')));
        $desc = trim((string)($payload['description'] ?? ''));
        $isActive = isset($payload['is_active']) ? (int)(bool)$payload['is_active'] : 1;

        if (empty($name)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Account name is required.'], 422);
            }
            Session::flash('error', 'Account name is required.');
            return Response::redirect('/accounting/accounts');
        }

        // If code changed, check uniqueness
        if (!empty($code) && $code !== $existing['account_code']) {
            $duplicate = Database::selectOne(
                "SELECT id FROM chart_of_accounts WHERE company_id = ? AND account_code = ? AND id != ? AND deleted_at IS NULL",
                [$companyId, $code, $accountId]
            );
            if ($duplicate) {
                if ($this->expectsJson($req)) {
                    return Response::json(['success' => false, 'message' => "An account with code '{$code}' already exists."], 422);
                }
                Session::flash('error', "An account with code '{$code}' already exists.");
                return Response::redirect('/accounting/accounts');
            }
        }

        $finalCode = !empty($code) ? $code : $existing['account_code'];
        // System accounts cannot change their classification type or code to maintain double-entry integrity
        $finalType = ((int)$existing['is_system_account'] === 1) ? $existing['account_type'] : (!empty($type) ? $type : $existing['account_type']);
        $nature = in_array($finalType, ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT';

        try {
            Database::update(
                'chart_of_accounts',
                [
                    'account_name' => $name,
                    'account_code' => ((int)$existing['is_system_account'] === 1) ? $existing['account_code'] : $finalCode,
                    'account_type' => $finalType,
                    'nature' => $nature,
                    'description' => $desc ?: null,
                    'is_active' => $isActive,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$accountId, $companyId]
            );

            AuditLogger::log(
                action: 'update',
                module: 'accounting',
                entityType: 'ACCOUNT',
                entityId: $accountId,
                entityLabel: $name,
                oldValues: [
                    'account_name' => $existing['account_name'],
                    'account_code' => $existing['account_code'],
                ],
                newValues: [
                    'account_name' => $name,
                    'account_code' => $finalCode,
                ],
                status: 'success',
                notes: "Updated ledger account '{$name}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Ledger account '{$name}' updated successfully.",
                    'account' => [
                        'id' => $accountId,
                        'account_name' => $name,
                        'account_code' => $finalCode,
                        'account_type' => $finalType,
                        'nature' => $nature,
                    ],
                ]);
            }

            Session::flash('success', "Ledger account '{$name}' updated successfully.");
            return Response::redirect('/accounting/accounts');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to update account: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to update account: ' . $e->getMessage());
            return Response::redirect('/accounting/accounts');
        }
    }

    /**
     * Delete ledger account with system and transaction safeguards.
     * Route: POST /accounts/{id}/delete, DELETE /accounts/{id}
     */
    public function accountDelete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('accounting.delete', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $accountId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? $req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM chart_of_accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$accountId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Account not found.'], 404);
            }
            Session::flash('error', 'Account not found.');
            return Response::redirect('/accounting/accounts');
        }

        // Safeguard 1: Core system accounts cannot be deleted
        if ((int)$existing['is_system_account'] === 1) {
            $msg = "System account '{$existing['account_name']}' is a core system ledger account and cannot be deleted.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/accounting/accounts');
        }

        // Safeguard 2: Accounts with journal entries cannot be deleted
        $journalLines = Database::selectOne(
            "SELECT COUNT(*) as cnt FROM journal_entry_lines WHERE account_id = ? AND company_id = ?",
            [$accountId, $companyId]
        );
        $lineCount = (int)($journalLines['cnt'] ?? 0);

        if ($lineCount > 0) {
            $msg = "Cannot delete account '{$existing['account_name']}' because it has {$lineCount} recorded journal entry transaction(s). You can mark it as Inactive instead.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/accounting/accounts');
        }

        try {
            Database::execute("DELETE FROM chart_of_accounts WHERE id = ? AND company_id = ?", [$accountId, $companyId]);

            AuditLogger::log(
                action: 'delete',
                module: 'accounting',
                entityType: 'ACCOUNT',
                entityId: $accountId,
                entityLabel: $existing['account_name'],
                oldValues: [
                    'account_name' => $existing['account_name'],
                    'account_code' => $existing['account_code'],
                ],
                status: 'success',
                notes: "Deleted general ledger account '{$existing['account_name']}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Ledger account '{$existing['account_name']}' deleted successfully.",
                    'account_id' => $accountId,
                ]);
            }

            Session::flash('success', "Ledger account '{$existing['account_name']}' deleted successfully.");
            return Response::redirect('/accounting/accounts');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to delete account: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to delete account: ' . $e->getMessage());
            return Response::redirect('/accounting/accounts');
        }
    }

    /**
     * Journal entries register view.
     */
    public function journalsIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;

        $journalEntries = Database::select(
            "SELECT id, entry_number, entry_date, narration, total_debit, total_credit, status 
             FROM journal_entries 
             WHERE company_id = ? 
             ORDER BY id DESC",
            [$companyId]
        );

        $accounts = Database::select(
            "SELECT id, account_code, account_name, account_type FROM chart_of_accounts WHERE company_id = ? AND is_active = 1 ORDER BY account_code ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('accounting/journals', [
            'title' => 'Journal Entries',
            'currentUri' => '/accounting/journals',
            'journalEntries' => $journalEntries,
            'accounts' => $accounts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Record balanced double-entry journal entry (&sum; Debit == &sum; Credit).
     */
    public function journalStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $debitAccId = (int)($payload['debit_account_id'] ?? 0);
        $creditAccId = (int)($payload['credit_account_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? 0);
        $entryDate = trim((string)($payload['entry_date'] ?? date('Y-m-d')));
        $narration = trim((string)($payload['narration'] ?? ''));

        if ($debitAccId <= 0 || $creditAccId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select both debit and credit accounts.'], 422);
            }
            Session::flash('error', 'Please select both debit and credit accounts.');
            return Response::redirect('/accounting/journals');
        }

        if ($debitAccId === $creditAccId) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Debit and Credit accounts must be distinct.'], 422);
            }
            Session::flash('error', 'Debit and Credit accounts must be distinct.');
            return Response::redirect('/accounting/journals');
        }

        if ($amount <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Journal amount must be greater than zero.'], 422);
            }
            Session::flash('error', 'Journal amount must be greater than zero.');
            return Response::redirect('/accounting/journals');
        }

        $entryNumber = 'JE-' . date('Y') . '-' . rand(1000, 9999);

        try {
            Database::transaction(function() use (
                $companyId, $debitAccId, $creditAccId, $amount, $entryDate, $narration, $entryNumber
            ) {
                $jeId = (int)Database::insert('journal_entries', [
                    'company_id' => $companyId,
                    'entry_number' => $entryNumber,
                    'entry_date' => $entryDate,
                    'narration' => $narration ?: 'Double-entry general journal posting',
                    'total_debit' => $amount,
                    'total_credit' => $amount,
                    'status' => 'POSTED',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Insert debit line
                Database::insert('journal_entry_lines', [
                    'company_id' => $companyId,
                    'journal_entry_id' => $jeId,
                    'account_id' => $debitAccId,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'description' => 'Debit: ' . $narration,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Insert credit line
                Database::insert('journal_entry_lines', [
                    'company_id' => $companyId,
                    'journal_entry_id' => $jeId,
                    'account_id' => $creditAccId,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'description' => 'Credit: ' . $narration,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Balanced journal entry '{$entryNumber}' posted successfully.",
                ], 201);
            }

            Session::flash('success', "Balanced journal entry '{$entryNumber}' posted successfully.");
            return Response::redirect('/accounting/journals');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to post journal entry: ' . $e->getMessage());
            return Response::redirect('/accounting/journals');
        }
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
