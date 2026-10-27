<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test: Dashboard Invoice View and Route Parity (HTML vs JSON).
 * Verifies that clicking/visiting /sales/invoices/{id} in browser renders HTML,
 * while API/AJAX requests continue returning JSON cleanly without breaking modals.
 */
class DashboardInvoiceViewTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        // 1. Authenticate administrator
        $auth = new AuthService();
        $user = $auth->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);

        $assert(
            'DASHINV-01',
            'Administrator authenticates and establishes session context for dashboard testing',
            $user !== null && Session::get('user_id') > 0
        );

        // Find or create an invoice for verification
        $invoice = Database::selectOne(
            "SELECT id, invoice_number FROM invoices WHERE company_id = ? AND branch_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
            [$companyId, $branchId]
        );

        $createdTestId = null;
        if (!$invoice) {
            $invNum = 'INV-DASH-' . time() . '-' . rand(100, 999);
            $invId = Database::insert('invoices', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => 1,
                'invoice_number' => $invNum,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d'),
                'sub_total' => 1000.00,
                'grand_total' => 1180.00,
                'status' => 'PAID',
            ]);
            $invoiceId = (int)$invId;
            $createdTestId = $invoiceId;
        } else {
            $invoiceId = (int)$invoice['id'];
        }

        try {

        $salesController = new SalesController();

        // 2. Direct browser navigation to /sales/invoices/{id} (Accept: text/html)
        $browserReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}",
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        ]);
        $browserResp = $salesController->invoiceShow($browserReq, $invoiceId);
        $browserHtml = $browserResp->getContent();

        $assert(
            'DASHINV-02',
            'Direct browser visit to /sales/invoices/{id} returns HTML Tax Invoice print template (HTTP 200)',
            $browserResp->getStatusCode() === 200 &&
            !empty($browserHtml) &&
            str_contains($browserHtml, '<!DOCTYPE html>') &&
            str_contains($browserHtml, 'TAX INVOICE') &&
            !str_starts_with(trim($browserHtml), '{"success":')
        );

        // 3. API / AJAX request to /sales/invoices/{id} (Accept: application/json)
        $apiReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $apiResp = $salesController->invoiceShow($apiReq, $invoiceId);
        $apiJson = json_decode((string)$apiResp->getBody(), true);

        $assert(
            'DASHINV-03',
            'AJAX request with Accept: application/json continues returning structured JSON payload',
            $apiResp->getStatusCode() === 200 &&
            is_array($apiJson) &&
            ($apiJson['success'] ?? false) === true &&
            isset($apiJson['invoice']) &&
            (int)$apiJson['invoice']['id'] === $invoiceId
        );

        // 4. Dashboard View includes quick view modal, Print & Edit action triggers
        $dashHtml = \WTSBill\Core\View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'total_sales' => 50000.0,
                'total_tax' => 9000.0,
                'receivables' => 10000.0,
                'payables' => 5000.0,
            ],
            'recentInvoices' => [
                [
                    'id' => $invoiceId,
                    'invoice_number' => 'INV-TEST-001',
                    'customer_name' => 'Acme Corporation',
                    'invoice_date' => date('Y-m-d'),
                    'grand_total' => 1180.00,
                    'status' => 'PAID',
                ],
            ],
        ]);

        $assert(
            'DASHINV-04',
            'Dashboard recent invoices table renders direct View link to /sales/invoices/{id}',
            str_contains($dashHtml, "/sales/invoices/{$invoiceId}") &&
            str_contains($dashHtml, '>View</a>')
        );

        $assert(
            'DASHINV-05',
            'Dashboard view is clean with direct navigation and does NOT contain #dash-invoice-modal',
            !str_contains($dashHtml, 'id="dash-invoice-modal"') &&
            !str_contains($dashHtml, 'window.viewDashboardInvoice')
        );

        // 5. Empty recent invoices state
        $emptyDashHtml = \WTSBill\Core\View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'total_sales' => 0.0,
                'total_tax' => 0.0,
                'receivables' => 0.0,
                'payables' => 0.0,
            ],
            'recentInvoices' => [],
        ]);

        $assert(
            'DASHINV-06',
            'Dashboard with no active invoices displays clean empty state without fake hardcoded rows',
            str_contains($emptyDashHtml, 'No recent invoices found') &&
            !str_contains($emptyDashHtml, 'Tata Steel Limited') &&
            !str_contains($emptyDashHtml, 'Reliance Industries Ltd')
        );
        } finally {
            if ($createdTestId) {
                Database::execute("DELETE FROM invoices WHERE id = ?", [$createdTestId]);
            }
            Session::clear();
        }
    }
}

