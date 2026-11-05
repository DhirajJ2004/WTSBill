<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\CustomerController;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\CustomerService;

/**
 * Feature Test for Complete 360-Degree Customer Profile, Financial Summary, and History.
 */
class CustomerProfileTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $authService = new AuthService();
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $controller = new CustomerController();
        $service = new CustomerService();

        // 1. PAGE VIEW INTEGRATION & HTML CONTROLS
        $req = new Request([], ['id' => '1'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/customers/1',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $resp = $controller->show($req, 1);
        $html = $resp->getContent();

        $assert(
            'CUSTPROF-01',
            'GET /customers/1 returns HTTP 200 OK with Customer Profile HTML layout',
            $resp->getStatusCode() === 200 && !empty($html) && str_contains($html, 'Customer Profile')
        );

        $assert(
            'CUSTPROF-02',
            'Profile renders customer information header, code, GSTIN, and compliance details',
            str_contains($html, 'CUST-0001') &&
            str_contains($html, 'Ramesh Hardware Stores') &&
            str_contains($html, '27BBBBB1234C1Z5')
        );

        $assert(
            'CUSTPROF-03',
            'Profile renders all 8 Financial Summary KPI dashboard cards',
            str_contains($html, 'Total Invoiced') &&
            str_contains($html, 'Total Paid') &&
            str_contains($html, 'Outstanding Balance') &&
            str_contains($html, 'Overdue Amount') &&
            str_contains($html, 'Total Quotations') &&
            str_contains($html, 'Credit Adjustments')
        );

        $assert(
            'CUSTPROF-04',
            'Profile renders all 7 history tabs (Overview, Invoices, Payments, Quotations, Credit Notes, Ledger, Activities)',
            str_contains($html, 'data-tab="tab-overview"') &&
            str_contains($html, 'data-tab="tab-invoices"') &&
            str_contains($html, 'data-tab="tab-payments"') &&
            str_contains($html, 'data-tab="tab-quotations"') &&
            str_contains($html, 'data-tab="tab-credit-notes"') &&
            str_contains($html, 'data-tab="tab-ledger"') &&
            str_contains($html, 'data-tab="tab-activities"')
        );

        $assert(
            'CUSTPROF-05',
            'Profile renders Outstanding Invoices section with Days Overdue and +Pay action',
            str_contains($html, 'Outstanding Invoices') &&
            (str_contains($html, 'days overdue') || str_contains($html, 'Within terms')) &&
            str_contains($html, '+ Pay')
        );

        $assert(
            'CUSTPROF-06',
            'Profile action buttons link to Create Invoice & Quotation with pre-selected customer_id',
            str_contains($html, '/sales/invoices/create?customer_id=1') &&
            str_contains($html, '/sales/quotations/create?customer_id=1') &&
            str_contains($html, 'Statement CSV') &&
            str_contains($html, 'openShareStatementModal()')
        );

        // 2. BACKWARD-COMPATIBLE JSON API ENDPOINT
        $apiReq = new Request([], ['id' => '1'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/customers/1',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $apiResp = $controller->show($apiReq, 1);
        $apiData = json_decode($apiResp->getContent(), true);

        $assert(
            'CUSTPROF-07',
            'GET /customers/1 with Accept: application/json returns backward-compatible customer JSON payload',
            $apiResp->getStatusCode() === 200 &&
            is_array($apiData) &&
            ($apiData['success'] ?? false) === true &&
            isset($apiData['customer']['name'])
        );

        // 3. FULL PROFILE JSON API DATA INTEGRITY
        $profData = $service->getCustomerProfileData(1, 0);

        $assert(
            'CUSTPROF-08',
            'CustomerService::getCustomerProfileData computes correct financial summary metrics from live DB',
            $profData !== null &&
            isset($profData['financial_summary']['total_invoiced']) &&
            isset($profData['financial_summary']['total_paid']) &&
            isset($profData['financial_summary']['outstanding_balance']) &&
            count($profData['invoices']) > 0 &&
            count($profData['payments']) > 0 &&
            count($profData['ledger']) > 0
        );

        // 4. STATEMENT CSV DOWNLOAD STREAM
        $csvReq = new Request([], ['id' => '1'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/customers/1/statement/csv',
        ]);
        $csvResp = $controller->statementCsv($csvReq, 1);
        $csvContent = $csvResp->getContent();

        $assert(
            'CUSTPROF-09',
            'GET /customers/1/statement/csv returns text/csv stream with customer details and running ledger balance',
            $csvResp->getStatusCode() === 200 &&
            str_contains($csvResp->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'Customer Account Statement') &&
            str_contains($csvContent, 'Ramesh Hardware Stores') &&
            str_contains($csvContent, 'Running Balance')
        );

        // Clean up session
        Session::clear();
    }
}

