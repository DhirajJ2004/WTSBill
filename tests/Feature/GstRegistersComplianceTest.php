<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\GstComplianceService;

/**
 * Feature Test Suite for Phase 8: GST Management, Registers & GSTR-3B Preparation.
 * Verifies Table 3.1 outward liabilities, Table 4 input tax credit, Table 6.1 cash offsets,
 * GST portal JSON payloads, CSV streams, and B2B/B2C sales and purchase registers.
 */
class GstRegistersComplianceTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();

        // Load web routes
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';

        // 1. GST-01: Guest redirect
        Session::destroy();
        Session::start();
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gstr-3b']);
        $guestRes = $router->dispatch($guestReq);
        $assert('GST-01', 'Unauthenticated request to /reports/gstr-3b redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && $guestRes->getHeader('Location') === '/login'
        );

        // Authenticate as Admin
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $gstService = new GstComplianceService();

        // 2. GST-02: GSTR-3B HTML View
        $gstrReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gstr-3b']);
        $gstrRes = $router->dispatch($gstrReq);
        $gstrBody = $gstrRes->getBody();
        $assert('GST-02', 'GET /reports/gstr-3b returns HTTP 200 with Table 3.1 and Table 4 sections',
            $gstrRes->getStatusCode() === 200 &&
            str_contains($gstrBody, 'FORM GSTR-3B Tax Summary') &&
            str_contains($gstrBody, 'Details of Outward Supplies') &&
            str_contains($gstrBody, 'Eligible Input Tax Credit')
        );

        // 3. GST-03: GSTR-3B Statutory Tax Offset Equation
        $gstrData = $gstService->getGstr3bSummary($companyId);
        $outTax = (float)($gstrData['table_3_1']['outward_taxable']['total_tax'] ?? 0);
        $netItc = (float)($gstrData['table_4']['net_itc']['total_tax'] ?? 0);
        $cashPayable = (float)($gstrData['table_6_1']['net_tax_payable']['total'] ?? 0);
        $excessItc = (float)($gstrData['table_6_1']['excess_itc_cforward']['total'] ?? 0);

        // Under GST component isolation (CGST/SGST/IGST), (Outward - ITC) equals (Payable - Excess)
        $equationHolds = abs(($outTax - $netItc) - ($cashPayable - $excessItc)) < 0.05;

        $assert('GST-03', 'GSTR-3B statutory tax offset equation holds: Outward Tax Liability vs Net ITC matches Cash Payable or Excess ITC',
            $equationHolds
        );

        // 4. GST-04: GSTR-3B JSON API
        $apiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/reports/gstr-3b']);
        $apiRes = $router->dispatch($apiReq);
        $apiJson = json_decode($apiRes->getBody(), true);
        $assert('GST-04', 'GET /api/reports/gstr-3b returns structured JSON with success flag and return tables',
            $apiRes->getStatusCode() === 200 &&
            isset($apiJson['success']) &&
            $apiJson['success'] === true &&
            isset($apiJson['data']['table_3_1']) &&
            isset($apiJson['data']['table_4'])
        );

        // 5. GST-05: GSTR-3B Portal JSON Export
        $jsonExportReq = new Request(['format' => 'json'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gstr-3b/export?format=json']);
        $jsonExportRes = $router->dispatch($jsonExportReq);
        $jsonPayload = json_decode($jsonExportRes->getBody(), true);
        $assert('GST-05', 'GET /reports/gstr-3b/export?format=json returns official government schema JSON payload',
            $jsonExportRes->getStatusCode() === 200 &&
            str_contains($jsonExportRes->getHeader('Content-Type') ?? '', 'application/json') &&
            isset($jsonPayload['gstin']) &&
            isset($jsonPayload['ret_period']) &&
            isset($jsonPayload['sec_sum']) &&
            isset($jsonPayload['itc_elg'])
        );

        // 6. GST-06: GSTR-3B CSV Export
        $csvExportReq = new Request(['format' => 'csv'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gstr-3b/export?format=csv']);
        $csvExportRes = $router->dispatch($csvExportReq);
        $csvContent = $csvExportRes->getBody();
        $assert('GST-06', 'GET /reports/gstr-3b/export?format=csv returns text/csv stream with Table 3.1 and 4 references',
            $csvExportRes->getStatusCode() === 200 &&
            str_contains($csvExportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'FORM GSTR-3B RETURN SUMMARY') &&
            str_contains($csvContent, '3.1(a)') &&
            str_contains($csvContent, '4(A)(5)')
        );

        // 7. GST-07: GST Sales Register HTML View
        $srReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gst-sales-register']);
        $srRes = $router->dispatch($srReq);
        $srBody = $srRes->getBody();
        $assert('GST-07', 'GET /reports/gst-sales-register returns HTTP 200 with B2B/B2C columns and tax breakdown',
            $srRes->getStatusCode() === 200 &&
            str_contains($srBody, 'GST Outward Sales Register') &&
            str_contains($srBody, 'Taxable') &&
            str_contains($srBody, 'CGST') &&
            str_contains($srBody, 'SGST')
        );

        // 8. GST-08: GST Sales Register CSV Export
        $srExportReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gst-sales-register/export']);
        $srExportRes = $router->dispatch($srExportReq);
        $srCsv = $srExportRes->getBody();
        $assert('GST-08', 'GET /reports/gst-sales-register/export returns text/csv stream with invoice rows and totals',
            $srExportRes->getStatusCode() === 200 &&
            str_contains($srExportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($srCsv, 'GST OUTWARD SALES REGISTER') &&
            str_contains($srCsv, 'Invoice No') &&
            str_contains($srCsv, 'Customer GSTIN')
        );

        // 9. GST-09: GST Purchase Register HTML View
        $prReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gst-purchase-register']);
        $prRes = $router->dispatch($prReq);
        $prBody = $prRes->getBody();
        $assert('GST-09', 'GET /reports/gst-purchase-register returns HTTP 200 with inward purchase bills and ITC columns',
            $prRes->getStatusCode() === 200 &&
            str_contains($prBody, 'GST Inward Purchase Register') &&
            str_contains($prBody, 'Supplier &amp; GSTIN') &&
            str_contains($prBody, 'Total ITC')
        );

        // 10. GST-10: GST Purchase Register CSV Export
        $prExportReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/gst-purchase-register/export']);
        $prExportRes = $router->dispatch($prExportReq);
        $prCsv = $prExportRes->getBody();
        $assert('GST-10', 'GET /reports/gst-purchase-register/export returns text/csv stream with vendor bills and totals',
            $prExportRes->getStatusCode() === 200 &&
            str_contains($prExportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($prCsv, 'GST INWARD PURCHASE REGISTER') &&
            str_contains($prCsv, 'Bill No') &&
            str_contains($prCsv, 'Supplier GSTIN')
        );
    }
}
