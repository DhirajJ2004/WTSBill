<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\ExtendedAccountingService;

/**
 * Feature Test Suite for Phase 7: Complete Double-Entry Accounting Reports.
 * Verifies Trial Balance, double-entry mathematical balancing, CSV export,
 * Cash Book ledger, Bank Book register, and Cash Flow Statement.
 */
class ExtendedAccountingReportsTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();

        // Load web routes
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';

        // 1. ACC-01: Guest redirect
        Session::destroy();
        Session::start();
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/trial-balance']);
        $guestRes = $router->dispatch($guestReq);
        $assert('ACC-01', 'Unauthenticated request to /reports/trial-balance redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && $guestRes->getHeader('Location') === '/login'
        );

        // Authenticate as Admin
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $accountingService = new ExtendedAccountingService();

        // 2. ACC-02: Trial Balance HTML View
        $tbReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/trial-balance']);
        $tbRes = $router->dispatch($tbReq);
        $tbBody = $tbRes->getBody();
        $assert('ACC-02', 'GET /reports/trial-balance returns HTTP 200 and renders statement groups and balance indicator',
            $tbRes->getStatusCode() === 200 &&
            str_contains($tbBody, 'Trial Balance Statement') &&
            str_contains($tbBody, 'Assets') &&
            str_contains($tbBody, 'Liabilities')
        );

        // 3. ACC-03: Trial balance accounting equation strictly holds (total_debits == total_credits)
        $tbData = $accountingService->getTrialBalance($companyId);
        $summary = $tbData['summary'] ?? [];
        $totalDebits = (float)($summary['total_debits'] ?? 0);
        $totalCredits = (float)($summary['total_credits'] ?? 0);
        $isBalanced = (bool)($summary['is_balanced'] ?? false);
        $assert('ACC-03', 'Trial balance mathematical integrity holds: total_debits == total_credits and is_balanced is true',
            $isBalanced && abs($totalDebits - $totalCredits) < 0.01 && $totalDebits > 0
        );

        // 4. ACC-04: Trial Balance JSON API
        $apiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/reports/trial-balance']);
        $apiRes = $router->dispatch($apiReq);
        $apiJson = json_decode($apiRes->getBody(), true);
        $assert('ACC-04', 'GET /api/reports/trial-balance returns structured JSON with success flag and accounting data',
            $apiRes->getStatusCode() === 200 &&
            isset($apiJson['success']) &&
            $apiJson['success'] === true &&
            isset($apiJson['data']['summary']['total_debits'])
        );

        // 5. ACC-05: Trial Balance CSV Export
        $exportReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/trial-balance/export']);
        $exportRes = $router->dispatch($exportReq);
        $csvContent = $exportRes->getBody();
        $assert('ACC-05', 'GET /reports/trial-balance/export returns text/csv stream with totals row and account rows',
            $exportRes->getStatusCode() === 200 &&
            str_contains($exportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'Account Code') &&
            str_contains($csvContent, 'Debit Balance') &&
            str_contains($csvContent, 'TOTAL')
        );

        // 6. ACC-06: Cash Book Ledger View
        $cbReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/cash-book']);
        $cbRes = $router->dispatch($cbReq);
        $cbBody = $cbRes->getBody();
        $assert('ACC-06', 'GET /accounting/cash-book returns HTTP 200 with Cash Book ledger cards and table',
            $cbRes->getStatusCode() === 200 &&
            str_contains($cbBody, 'Cash Book Register') &&
            str_contains($cbBody, 'Cash Receipts') &&
            str_contains($cbBody, 'Cash Disbursements')
        );

        // 7. ACC-07: Cash Book Running Balance Verification
        $cbData = $accountingService->getCashBook($companyId);
        $openingCash = (float)($cbData['opening_balance'] ?? 0);
        $receipts = (float)($cbData['total_receipts'] ?? 0);
        $payments = (float)($cbData['total_payments'] ?? 0);
        $closingCash = (float)($cbData['closing_balance'] ?? 0);
        $expectedClosing = round($openingCash + $receipts - $payments, 2);
        $assert('ACC-07', 'Cash Book running balance is mathematically consistent (Opening + Receipts - Payments == Closing)',
            abs($expectedClosing - $closingCash) < 0.01
        );

        // 8. ACC-08: Bank Book Register View
        $bbReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/bank-book']);
        $bbRes = $router->dispatch($bbReq);
        $bbBody = $bbRes->getBody();
        $assert('ACC-08', 'GET /accounting/bank-book returns HTTP 200 with bank accounts tab and transaction register',
            $bbRes->getStatusCode() === 200 &&
            str_contains($bbBody, 'Bank Book Register') &&
            str_contains($bbBody, 'Bank Deposits') &&
            str_contains($bbBody, 'Withdrawals')
        );

        // 9. ACC-09: Cash Flow Statement View
        $cfReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/cash-flow']);
        $cfRes = $router->dispatch($cfReq);
        $cfBody = $cfRes->getBody();
        $assert('ACC-09', 'GET /reports/cash-flow returns HTTP 200 with Operating, Investing, and Financing sections',
            $cfRes->getStatusCode() === 200 &&
            str_contains($cfBody, 'Cash Flow Statement') &&
            str_contains($cfBody, 'CASH FLOW FROM OPERATING ACTIVITIES') &&
            str_contains($cfBody, 'CASH FLOW FROM INVESTING ACTIVITIES') &&
            str_contains($cfBody, 'CASH FLOW FROM FINANCING ACTIVITIES')
        );

        // 10. ACC-10: Cash Flow Statement Reconciliation Equation
        $cfData = $accountingService->getCashFlowStatement($companyId);
        $cfSummary = $cfData['summary'] ?? [];
        $begBalance = (float)($cfSummary['beginning_balance'] ?? 0);
        $netChange = (float)($cfSummary['net_cash_change'] ?? 0);
        $endBalance = (float)($cfSummary['ending_balance'] ?? 0);
        $expectedEnd = round($begBalance + $netChange, 2);
        $assert('ACC-10', 'Cash Flow reconciliation holds: Beginning Cash/Bank + Net Change == Ending Cash/Bank',
            abs($expectedEnd - $endBalance) < 0.01
        );
    }
}

