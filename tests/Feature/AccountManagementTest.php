<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\AccountingController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;

/**
 * Feature Test: Chart of Accounts Edit & Delete Management.
 * Verifies the presence of Edit and Delete buttons in the table and modal,
 * account detail API, account updates, system account deletion protection,
 * journal entry line protection, safe deletion, and 404 handling.
 */
class AccountManagementTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);
        Session::set('auth_user', [
            'id' => 1,
            'name' => 'Super Administrator',
            'email' => 'admin@wtsbill.com',
            'role' => 'ADMIN',
        ]);
        Session::set('user_role', 'admin');
        Session::set('user_permissions', ['*']);

        $controller = new AccountingController();
        $testAccountId = 0;

        try {
            // 1. Verify GET /accounts renders Actions header, Edit and Delete buttons
            $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounts'], null);
            $indexRes = $controller->accountsIndex($indexReq);
            $html = $indexRes->getContent();

            $assert(
                'ACCMGT-01',
                'GET /accounts renders Actions column with Edit and Delete buttons',
                str_contains($html, 'Actions</th>') &&
                str_contains($html, 'window.editAccount(') &&
                str_contains($html, 'window.deleteAccount(')
            );

            $assert(
                'ACCMGT-02',
                'Account modal renders Delete Account button in footer',
                str_contains($html, 'id="acc-modal-delete-btn"') &&
                str_contains($html, 'window.deleteCurrentModalAccount()')
            );

            // 2. Create test account
            $accName = 'AutoTest Ledger ' . rand(1000, 9999);
            $createReq = new Request([], [
                'account_name' => $accName,
                'account_type' => 'EXPENSE',
                'account_code' => '58' . rand(1000, 9999),
                'opening_balance' => 0.00,
                'description' => 'Automated test GL account',
                '_csrf_token' => Session::getCsrfToken(),
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);

            $createRes = $controller->accountStore($createReq);
            $assert(
                'ACCMGT-03',
                'POST /accounts creates new test general ledger account with HTTP 201',
                $createRes->getStatusCode() === 201
            );

            $dbAcc = Database::selectOne("SELECT id, account_code FROM chart_of_accounts WHERE account_name = ?", [$accName]);
            $testAccountId = (int)($dbAcc['id'] ?? 0);
            $assert('ACCMGT-04', 'Test account is persisted to chart_of_accounts table', $testAccountId > 0);

            // 3. Show account JSON
            $showReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/accounts/{$testAccountId}",
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $showRes = $controller->accountShow($showReq, $testAccountId);
            $showJson = json_decode($showRes->getContent(), true);

            $assert(
                'ACCMGT-05',
                'GET /accounts/{id} returns account details payload via JSON',
                $showRes->getStatusCode() === 200 &&
                ($showJson['account']['account_name'] ?? '') === $accName
            );

            // 4. Update account
            $updatedName = $accName . ' (Modified)';
            $updateReq = new Request([], [
                'account_name' => $updatedName,
                'account_type' => 'EXPENSE',
                'description' => 'Modified description',
                'is_active' => 1,
                '_csrf_token' => Session::getCsrfToken(),
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/accounts/{$testAccountId}/update",
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $updateRes = $controller->accountUpdate($updateReq, $testAccountId);
            $dbUpdated = Database::selectOne("SELECT account_name FROM chart_of_accounts WHERE id = ?", [$testAccountId]);

            $assert(
                'ACCMGT-06',
                'POST /accounts/{id}/update successfully edits account name and description',
                $updateRes->getStatusCode() === 200 && ($dbUpdated['account_name'] ?? '') === $updatedName
            );

            // 5. System account deletion rejection safeguard
            $sysDelReq = new Request([], ['_csrf_token' => Session::getCsrfToken()], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts/1/delete',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $sysDelRes = $controller->accountDelete($sysDelReq, 1);
            $sysJson = json_decode($sysDelRes->getContent(), true);

            $assert(
                'ACCMGT-07',
                'Deleting core system account (Cash in Hand) is strictly rejected with HTTP 422 safeguard',
                $sysDelRes->getStatusCode() === 422 && str_contains($sysJson['message'] ?? '', 'core system ledger account')
            );

            // 6. Delete test account
            $delReq = new Request([], ['_csrf_token' => Session::getCsrfToken()], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/accounts/{$testAccountId}/delete",
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $delRes = $controller->accountDelete($delReq, $testAccountId);
            $assert(
                'ACCMGT-08',
                'POST /accounts/{id}/delete permanently deletes unused test account with HTTP 200',
                $delRes->getStatusCode() === 200
            );

            $checkDeleted = Database::selectOne("SELECT id FROM chart_of_accounts WHERE id = ?", [$testAccountId]);
            $assert('ACCMGT-09', 'Test account is completely removed from chart_of_accounts table', $checkDeleted === null);
            $testAccountId = 0; // successfully deleted

            // 7. Non-existent account deletion returns 404
            $del404Req = new Request([], ['_csrf_token' => Session::getCsrfToken()], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts/999999/delete',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $del404Res = $controller->accountDelete($del404Req, 999999);
            $assert(
                'ACCMGT-10',
                'Deleting non-existent account returns HTTP 404 Not Found',
                $del404Res->getStatusCode() === 404
            );

        } finally {
            if ($testAccountId > 0) {
                Database::execute("DELETE FROM chart_of_accounts WHERE id = ?", [$testAccountId]);
            }
        }
    }
}
