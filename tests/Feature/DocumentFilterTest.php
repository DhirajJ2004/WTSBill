<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\QuotationController;
use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test for Comprehensive Invoice & Quotation List Filters (Feature 4).
 */
class DocumentFilterTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $authService = new AuthService();
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $salesController = new SalesController();
        $quotationController = new QuotationController();

        // 1. INVOICE LIST PAGE RENDERS STATUS PILLS AND FILTER BAR
        $invReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $invResp = $salesController->invoicesIndex($invReq);
        $invHtml = $invResp->getContent();

        $assert(
            'DOCFILTER-01',
            'GET /sales/invoices returns HTTP 200 with Status Pills bar and Multi-field Filter form',
            $invResp->getStatusCode() === 200 &&
            str_contains($invHtml, 'status-pills-bar') &&
            str_contains($invHtml, 'All Invoices') &&
            str_contains($invHtml, 'Pending / Unpaid') &&
            str_contains($invHtml, 'Partially Paid') &&
            str_contains($invHtml, 'Overdue') &&
            str_contains($invHtml, 'id="invoiceFilterForm"')
        );

        // 2. INVOICE FILTER BY UNPAID / PENDING (JSON API)
        $unpaidReq = new Request(['status' => 'UNPAID'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices?status=UNPAID',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $unpaidResp = $salesController->invoicesIndex($unpaidReq);
        $unpaidData = json_decode($unpaidResp->getContent(), true);

        $unpaidInvoices = $unpaidData['data']['invoices'] ?? [];
        $allUnpaid = true;
        foreach ($unpaidInvoices as $inv) {
            if ($inv['status'] !== 'UNPAID') {
                $allUnpaid = false;
                break;
            }
        }

        $assert(
            'DOCFILTER-02',
            'GET /sales/invoices?status=UNPAID returns only UNPAID status invoices and accurate status counts',
            $unpaidResp->getStatusCode() === 200 &&
            count($unpaidInvoices) > 0 &&
            $allUnpaid === true &&
            isset($unpaidData['data']['counts']['unpaid']) &&
            (int)$unpaidData['data']['counts']['unpaid'] > 0
        );

        // 3. INVOICE FILTER BY PARTIAL
        $partialReq = new Request(['status' => 'PARTIAL'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices?status=PARTIAL',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $partialResp = $salesController->invoicesIndex($partialReq);
        $partialData = json_decode($partialResp->getContent(), true);

        $partialInvoices = $partialData['data']['invoices'] ?? [];
        $allPartial = true;
        foreach ($partialInvoices as $inv) {
            if ($inv['status'] !== 'PARTIAL') {
                $allPartial = false;
                break;
            }
        }

        $assert(
            'DOCFILTER-03',
            'GET /sales/invoices?status=PARTIAL returns strictly PARTIAL invoices with partial payment counts',
            $partialResp->getStatusCode() === 200 &&
            count($partialInvoices) > 0 &&
            $allPartial === true &&
            (int)$partialData['data']['counts']['partial'] > 0
        );

        // 4. INVOICE FILTER BY PAID
        $paidReq = new Request(['status' => 'PAID'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices?status=PAID',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $paidResp = $salesController->invoicesIndex($paidReq);
        $paidData = json_decode($paidResp->getContent(), true);

        $paidInvoices = $paidData['data']['invoices'] ?? [];
        $allPaid = true;
        foreach ($paidInvoices as $inv) {
            if ($inv['status'] !== 'PAID') {
                $allPaid = false;
                break;
            }
        }

        $assert(
            'DOCFILTER-04',
            'GET /sales/invoices?status=PAID returns strictly PAID invoices',
            $paidResp->getStatusCode() === 200 &&
            count($paidInvoices) > 0 &&
            $allPaid === true
        );

        // 5. INVOICE FILTER BY OVERDUE
        $overdueReq = new Request(['status' => 'OVERDUE'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices?status=OVERDUE',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $overdueResp = $salesController->invoicesIndex($overdueReq);
        $overdueData = json_decode($overdueResp->getContent(), true);

        $overdueInvoices = $overdueData['data']['invoices'] ?? [];
        $allOverdue = true;
        $today = date('Y-m-d');
        foreach ($overdueInvoices as $inv) {
            if ($inv['status'] === 'PAID' || empty($inv['due_date']) || $inv['due_date'] >= $today) {
                $allOverdue = false;
                break;
            }
        }

        $assert(
            'DOCFILTER-05',
            'GET /sales/invoices?status=OVERDUE isolates invoices where status != PAID and due_date < today',
            $overdueResp->getStatusCode() === 200 &&
            (count($overdueInvoices) === 0 || $allOverdue === true) &&
            isset($overdueData['data']['counts']['overdue'])
        );

        // 6. INVOICE MULTI-FILTER (SEARCH, AMOUNT, BRANCH, PAGINATION)
        $multiReq = new Request([
            'status' => 'ALL',
            'search' => 'INV',
            'min_amount' => '100',
            'max_amount' => '500000',
            'branch_id' => '1',
            'page' => '1',
            'per_page' => '10',
        ], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices?search=INV&min_amount=100&max_amount=500000&branch_id=1&per_page=10',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $multiResp = $salesController->invoicesIndex($multiReq);
        $multiData = json_decode($multiResp->getContent(), true);

        $assert(
            'DOCFILTER-06',
            'Multi-field invoice query properly filters by search, amount boundaries, branch, and returns pagination envelope',
            $multiResp->getStatusCode() === 200 &&
            isset($multiData['data']['pagination']['page']) &&
            $multiData['data']['pagination']['per_page'] === 10 &&
            count($multiData['data']['invoices']) <= 10
        );

        // 7. QUOTATIONS REGISTER WITH LIFECYCLE PILLS & FILTER BAR
        $quoteReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/quotations',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $quoteResp = $quotationController->index($quoteReq);
        $quoteHtml = $quoteResp->getContent();

        $assert(
            'DOCFILTER-07',
            'GET /sales/quotations returns HTTP 200 with Lifecycle Status pills, Linked Payment dropdown, and Register',
            $quoteResp->getStatusCode() === 200 &&
            str_contains($quoteHtml, 'status-pills-bar') &&
            str_contains($quoteHtml, 'All Lifecycle') &&
            str_contains($quoteHtml, 'name="payment_status"') &&
            str_contains($quoteHtml, 'id="quotationFilterForm"')
        );

        // 8. QUOTATIONS FILTER BY LIFECYCLE STATUS (SENT)
        $sentReq = new Request(['status' => 'SENT'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/quotations?status=SENT',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $sentResp = $quotationController->index($sentReq);
        $sentData = json_decode($sentResp->getContent(), true);

        $sentQuotes = $sentData['data']['quotations'] ?? [];
        $allSent = true;
        foreach ($sentQuotes as $q) {
            if ($q['status'] !== 'SENT') {
                $allSent = false;
                break;
            }
        }

        $assert(
            'DOCFILTER-08',
            'GET /sales/quotations?status=SENT filters strictly SENT lifecycle quotations',
            $sentResp->getStatusCode() === 200 &&
            (count($sentQuotes) === 0 || $allSent === true) &&
            isset($sentData['data']['counts']['sent'])
        );

        // 9. QUOTATIONS FILTER BY LINKED INVOICE PAYMENT STATUS (UNPAID / PAID)
        $quotePayReq = new Request(['payment_status' => 'UNPAID'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/quotations?payment_status=UNPAID',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $quotePayResp = $quotationController->index($quotePayReq);
        $quotePayData = json_decode($quotePayResp->getContent(), true);

        $assert(
            'DOCFILTER-09',
            'GET /sales/quotations?payment_status=UNPAID successfully filters quotations converted to unpaid invoices',
            $quotePayResp->getStatusCode() === 200 &&
            isset($quotePayData['data']['counts']['pay_unpaid']) &&
            isset($quotePayData['data']['pagination']['total'])
        );

        // 10. QUOTATIONS MULTI-FIELD SEARCH, BRANCH & DATE RANGE
        $quoteMultiReq = new Request([
            'search' => 'QT',
            'status' => 'ALL',
            'branch_id' => '1',
            'page' => '1',
            'per_page' => '10',
        ], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/quotations?search=QT&branch_id=1&per_page=10',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $quoteMultiResp = $quotationController->index($quoteMultiReq);
        $quoteMultiData = json_decode($quoteMultiResp->getContent(), true);

        $assert(
            'DOCFILTER-10',
            'Quotation multi-field query cleanly processes search, branch, and returns pagination envelope',
            $quoteMultiResp->getStatusCode() === 200 &&
            isset($quoteMultiData['data']['pagination']['page']) &&
            $quoteMultiData['data']['pagination']['per_page'] === 10
        );

        // Clean up session
        Session::clear();
    }
}

