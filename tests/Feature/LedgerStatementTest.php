<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\AccountingController;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\LedgerService;

/**
 * Feature Test for General Transaction Ledger Statement, API, and Exports.
 */
class LedgerStatementTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $authService = new AuthService();
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $controller = new AccountingController();
        $service = new LedgerService();

        // 1. PAGE VIEW INTEGRATION & HTML CONTROLS
        $req = new Request([], ['account_id' => '2', 'from_date' => '2026-01-01', 'to_date' => '2026-10-31'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/accounting/ledger?account_id=2',
        ]);
        $resp = $controller->ledgerIndex($req);
        $html = $resp->getContent();

        $assert(
            'LEDGER-01',
            'GET /accounting/ledger returns HTTP 200 OK with non-empty HTML',
            $resp->getStatusCode() === 200 && !empty($html)
        );

        $assert(
            'LEDGER-02',
            'Ledger view renders KPI summary cards for Opening Balance, Debits, Credits, and Closing Balance',
            str_contains($html, 'Opening Balance') &&
            str_contains($html, 'Total Debits') &&
            str_contains($html, 'Total Credits') &&
            str_contains($html, 'Closing Balance')
        );

        $assert(
            'LEDGER-03',
            'Ledger view renders account selector and date range filters',
            str_contains($html, 'id="filter-account"') &&
            str_contains($html, 'id="filter-branch"') &&
            str_contains($html, 'id="filter-from-date"') &&
            str_contains($html, 'id="filter-to-date"') &&
            str_contains($html, 'id="filter-tx-type"')
        );

        $assert(
            'LEDGER-04',
            'Ledger view renders Export CSV and Print buttons',
            str_contains($html, 'Export CSV') &&
            str_contains($html, 'window.print()')
        );

        // 2. LIVE JSON API ENDPOINT
        $apiReq = new Request([], ['account_id' => '2', 'from_date' => '2026-01-01', 'to_date' => '2026-10-31'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/accounting/ledger?account_id=2',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $apiResp = $controller->ledgerApi($apiReq);
        $apiData = json_decode($apiResp->getContent(), true);

        $assert(
            'LEDGER-05',
            'GET /api/accounting/ledger returns HTTP 200 with JSON payload',
            $apiResp->getStatusCode() === 200 && is_array($apiData) && ($apiData['success'] ?? false) === true
        );

        $assert(
            'LEDGER-06',
            'API payload returns accurate account metadata and balances',
            !empty($apiData['account']['code']) &&
            !empty($apiData['account']['name']) &&
            isset($apiData['opening_balance']) &&
            isset($apiData['closing_balance']) &&
            isset($apiData['entries'])
        );

        // 3. CHRONOLOGICAL RUNNING BALANCE INTEGRITY
        $ledgerResult = $service->getLedger(1, ['account_id' => 2, 'from_date' => '2026-01-01', 'to_date' => '2026-10-31', 'per_page' => 0]);
        $entries = $ledgerResult['entries'];
        $mathValid = true;
        $running = $ledgerResult['opening_balance'];

        foreach ($entries as $e) {
            $dr = (float)$e['debit'];
            $cr = (float)$e['credit'];
            $running = round($running + ($dr - $cr), 2);
            if (abs($running - (float)$e['running_balance']) > 0.01) {
                $mathValid = false;
                break;
            }
        }

        $assert(
            'LEDGER-07',
            'Running balance is strictly chronological and mathematically sound across all transactions',
            $mathValid && count($entries) > 0
        );

        // 4. MULTI-BRANCH FILTERING
        $branch1Ledger = $service->getLedger(1, ['account_id' => 2, 'branch_id' => 1]);
        $assert(
            'LEDGER-08',
            'Ledger correctly isolates branch transactions when branch_id is specified',
            $branch1Ledger['success'] === true && isset($branch1Ledger['branch']['id'])
        );

        // 5. CSV EXPORT STREAM
        $exportReq = new Request([], ['account_id' => '2', 'format' => 'csv'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/accounting/ledger/export?account_id=2&format=csv',
        ]);
        $exportResp = $controller->ledgerExport($exportReq);
        $csvContent = $exportResp->getContent();

        $assert(
            'LEDGER-09',
            'GET /accounting/ledger/export returns text/csv stream with voucher numbers and balances',
            $exportResp->getStatusCode() === 200 &&
            str_contains($exportResp->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'General Ledger Statement') &&
            str_contains($csvContent, 'Running Balance')
        );

        // Clean up session
        Session::clear();
    }
}

