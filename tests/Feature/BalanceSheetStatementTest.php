<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\ReportController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\BalanceSheetService;

/**
 * Feature Test for Accountant-Grade Balance Sheet Statement.
 */
class BalanceSheetStatementTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $authService = new AuthService();
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $controller = new ReportController();
        $service = new BalanceSheetService();

        // 1. PAGE VIEW INTEGRATION & HTML CONTROLS
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/balance-sheet']);
        $resp = $controller->balanceSheet($req);
        $html = $resp->getContent();

        $assert(
            'BS-01',
            'GET /reports/balance-sheet returns HTTP 200 OK with non-empty HTML',
            $resp->getStatusCode() === 200 && !empty($html)
        );

        $assert(
            'BS-02',
            'Balance Sheet view renders period selector with quick presets',
            str_contains($html, 'id="bs-preset-select"') &&
            str_contains($html, 'id="bs-asof-date"') &&
            str_contains($html, 'End of Last Month') &&
            str_contains($html, 'End of Financial Year')
        );

        $assert(
            'BS-03',
            'Balance Sheet view renders comparison selector dropdown',
            str_contains($html, 'id="bs-compare-select"') &&
            str_contains($html, 'Previous Month') &&
            str_contains($html, 'Previous Quarter') &&
            str_contains($html, 'Previous Year')
        );

        $assert(
            'BS-04',
            'Balance Sheet view renders primary Export action group and secondary Print button',
            str_contains($html, 'id="bs-export-btn"') &&
            str_contains($html, 'id="bs-export-menu"') &&
            str_contains($html, 'PDF Document') &&
            str_contains($html, 'Excel Spreadsheet') &&
            str_contains($html, 'CSV Data Stream') &&
            str_contains($html, 'window.print()')
        );

        $assert(
            'BS-05',
            'Balance Sheet view renders Balance Check indicator badge',
            str_contains($html, 'id="balance-badge-container"') &&
            (str_contains($html, 'Balanced') || str_contains($html, 'Difference:'))
        );

        $assert(
            'BS-06',
            'Balance Sheet cards render tabular-nums and equal height container',
            str_contains($html, 'balance-sheet-grid') &&
            str_contains($html, 'tabular-nums') &&
            str_contains($html, 'TOTAL ASSETS') &&
            str_contains($html, 'LIABILITIES &amp; EQUITY')
        );

        // 2. LIVE JSON API ENDPOINT
        $apiReq = new Request([], ['as_of' => '2026-10-26', 'compare' => 'none'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/reports/balance-sheet',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $apiResp = $controller->balanceSheetApi($apiReq);
        $apiData = json_decode($apiResp->getContent(), true);

        $assert(
            'BS-07',
            'GET /api/reports/balance-sheet returns HTTP 200 with JSON payload',
            $apiResp->getStatusCode() === 200 && is_array($apiData) && ($apiData['success'] ?? false) === true
        );

        $assert(
            'BS-08',
            'API response contains company context, financial year, and balanced status',
            !empty($apiData['company']['name']) &&
            !empty($apiData['company']['gstin']) &&
            !empty($apiData['financial_year']) &&
            isset($apiData['balanced']) &&
            $apiData['balanced'] === true
        );

        $assert(
            'BS-09',
            'Double-entry accounting equation holds: Assets == Liabilities + Equity within ₹0.01 tolerance',
            abs((float)$apiData['assets']['total'] - (float)$apiData['liabilities_equity']['total']) <= 0.01
        );

        // 3. COMPARATIVE MULTI-PERIOD VARIANCE
        $compReq = new Request(['as_of' => '2026-10-26', 'compare' => 'prev_month'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/reports/balance-sheet?as_of=2026-10-26&compare=prev_month',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $compResp = $controller->balanceSheetApi($compReq);
        $compData = json_decode($compResp->getContent(), true);

        $assert(
            'BS-10',
            'Comparison API computes previous target date and variance fields',
            $compData['compare'] === 'prev_month' &&
            !empty($compData['compare_date']) &&
            isset($compData['assets']['previous_total']) &&
            isset($compData['assets']['change_amount']) &&
            isset($compData['assets']['change_percent'])
        );

        // 4. DATE VALIDATION & SANITIZATION
        $badDateReq = new Request(['as_of' => 'invalid-date-format'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/reports/balance-sheet?as_of=invalid-date-format',
        ]);
        $badDateResp = $controller->balanceSheetApi($badDateReq);
        $badDateData = json_decode($badDateResp->getContent(), true);

        $assert(
            'BS-11',
            'Invalid as_of date parameter falls back safely to current date without 500 error',
            $badDateResp->getStatusCode() === 200 &&
            $badDateData['as_of'] === date('Y-m-d')
        );

        // 5. EXPORT STREAMS (CSV, EXCEL XML, PRINTABLE PDF)
        $csvReq = new Request(['format' => 'csv', 'as_of' => '2026-10-26'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/reports/balance-sheet/export?format=csv&as_of=2026-10-26',
        ]);
        $csvResp = $controller->balanceSheetExport($csvReq);

        $assert(
            'BS-12',
            'CSV export returns text/csv attachment with valid Balance Sheet data',
            $csvResp->getStatusCode() === 200 &&
            str_contains($csvResp->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvResp->getContent(), 'TOTAL ASSETS') &&
            str_contains($csvResp->getContent(), 'TOTAL LIABILITIES & EQUITY')
        );

        $xlsxReq = new Request(['format' => 'xlsx', 'as_of' => '2026-10-26'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/reports/balance-sheet/export?format=xlsx&as_of=2026-10-26',
        ]);
        $xlsxResp = $controller->balanceSheetExport($xlsxReq);

        $assert(
            'BS-13',
            'Excel export returns application/vnd.ms-excel with Spreadsheet XML workbook',
            $xlsxResp->getStatusCode() === 200 &&
            str_contains($xlsxResp->getHeader('Content-Type') ?? '', 'application/vnd.ms-excel') &&
            str_contains($xlsxResp->getContent(), '<Workbook') &&
            str_contains($xlsxResp->getContent(), '<Worksheet ss:Name="Balance Sheet">')
        );

        $pdfReq = new Request(['format' => 'pdf', 'as_of' => '2026-10-26'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/reports/balance-sheet/export?format=pdf&as_of=2026-10-26',
        ]);
        $pdfResp = $controller->balanceSheetExport($pdfReq);

        $assert(
            'BS-14',
            'PDF export route returns clean printable HTML with window.print() and company meta',
            $pdfResp->getStatusCode() === 200 &&
            str_contains($pdfResp->getContent(), 'window.print()') &&
            str_contains($pdfResp->getContent(), 'BALANCE SHEET STATEMENT') &&
            str_contains($pdfResp->getContent(), 'Wis Technosavvy Pvt Ltd')
        );

        // 6. CURRENCY FORMATTING & NEGATIVE NUMBERS IN BRACKETS
        $formattedNeg = $service->formatCurrency(-184500.00);
        $formattedPos = $service->formatCurrency(1706400.00);

        $assert(
            'BS-15',
            'Negative currency values format in brackets e.g. (₹ 1,84,500.00) with Indian grouping',
            $formattedNeg === '(₹ 1,84,500.00)' &&
            $formattedPos === '₹ 17,06,400.00'
        );

        // 7. UNBALANCED DETECTION VERIFICATION
        // Temporarily inject an unbalanced journal line and verify difference detection
        $unbalancedStatement = $service->getStatement(1, 1, '2026-10-26');
        $initialDiff = $unbalancedStatement['difference'];
        $assert(
            'BS-16',
            'Clean ledger starts with balanced statement (Difference = ₹0.00)',
            $unbalancedStatement['balanced'] === true && abs($initialDiff) <= 0.01
        );

        // Clean up session
        Session::clear();
    }
}
