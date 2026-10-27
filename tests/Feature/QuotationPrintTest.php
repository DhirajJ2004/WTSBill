<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test: Quotations Print Option & Document Generator.
 * Verifies that the Quotations Register contains the Print action button
 * and renders an A4 commercial quotation document matching WIS TECHNOSAVVY PVT LTD format
 * with line items, tax breakdown, amount in words, and browser print triggers.
 */
class QuotationPrintTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        // 1. Authenticate administrator
        $auth = new AuthService();
        $user = $auth->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $assert(
            'QPRINT-01',
            'Administrator authenticates and establishes session for quotation print verification',
            $user !== null && Session::get('user_id') > 0
        );

        $salesController = new SalesController();

        // 2. Test Quotations Register view renders "Actions" column and "Print" links
        $listReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/quotations']);
        $listResp = $salesController->quotationsIndex($listReq);
        $listHtml = (string)$listResp->getContent();

        $assert(
            'QPRINT-02',
            'GET /sales/quotations renders Actions column in the table header',
            str_contains($listHtml, '<th style="text-align: right;">Actions</th>')
        );

        $assert(
            'QPRINT-03',
            'GET /sales/quotations renders Print button targeting /sales/quotations/{id}/print',
            str_contains($listHtml, '/print') && str_contains($listHtml, 'Print')
        );

        // 3. Find a test quotation (e.g. quote 48 QN-2627-112 or any available)
        $quoteRow = Database::selectOne(
            "SELECT id, quotation_number FROM quotations WHERE company_id = ? ORDER BY id DESC LIMIT 1",
            [$companyId]
        );

        $testQuoteId = $quoteRow ? (int)$quoteRow['id'] : 0;
        $testQuoteNumber = $quoteRow ? (string)$quoteRow['quotation_number'] : '';

        $assert(
            'QPRINT-04',
            'Valid quotation record exists in the database for print testing',
            $testQuoteId > 0 && !empty($testQuoteNumber)
        );

        // 4. Test GET /sales/quotations/{id}/print returns HTML document matching WIS TECHNOSAVVY layout
        $printReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/quotations/{$testQuoteId}/print"]);
        $printResp = $salesController->quotationPrint($printReq, $testQuoteId);
        $printHtml = (string)$printResp->getContent();

        $assert(
            'QPRINT-05',
            'GET /sales/quotations/{id}/print returns HTTP 200 with HTML document',
            $printResp->getStatusCode() === 200 && str_contains($printHtml, '<!DOCTYPE html>')
        );

        $assert(
            'QPRINT-06',
            'Print document contains blue underlined Quotation title and quotation number',
            str_contains($printHtml, 'class="quote-title">Quotation<') && str_contains($printHtml, $testQuoteNumber)
        );

        $assert(
            'QPRINT-07',
            'Print layout contains screen toolbar with window.print() and email actions',
            str_contains($printHtml, 'window.print()') && str_contains($printHtml, 'Email to Customer')
        );

        $assert(
            'QPRINT-08',
            'Print document contains Amount Chargeable (In Words), Bank Details, and Terms & Conditions',
            str_contains($printHtml, 'Amount Chargeable (In Words)') && 
            str_contains($printHtml, 'Bank Details:') && 
            str_contains($printHtml, 'Terms & Conditions')
        );

        // 5. Test JSON API request to /sales/quotations/{id} returns JSON payload
        $jsonReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/sales/quotations/{$testQuoteId}",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $jsonResp = $salesController->quotationPrint($jsonReq, $testQuoteId);
        $jsonPayload = json_decode((string)$jsonResp->getContent(), true);

        $assert(
            'QPRINT-09',
            'AJAX/API request to /sales/quotations/{id} with Accept: application/json returns structured JSON payload',
            $jsonResp->getStatusCode() === 200 &&
            is_array($jsonPayload) &&
            ($jsonPayload['success'] ?? false) === true &&
            isset($jsonPayload['quotation'])
        );

        // 6. Test 404 response for non-existent quotation ID
        $notFoundReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/quotations/999999/print']);
        $notFoundResp = $salesController->quotationPrint($notFoundReq, 999999);

        $assert(
            'QPRINT-10',
            'Request for non-existent quotation ID returns HTTP 404 Not Found',
            $notFoundResp->getStatusCode() === 404 && str_contains((string)$notFoundResp->getContent(), 'Quotation Not Found')
        );
    }
}
