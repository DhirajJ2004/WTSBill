<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

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
            "SELECT id, account_code, account_name, account_type, current_balance, nature, is_active 
             FROM chart_of_accounts 
             WHERE company_id = ? 
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
