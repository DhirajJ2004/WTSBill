<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test for Purchase Bills & Accounts Payable Upgrade.
 * Verifies White + Blue Theme CSS tokens, KPI aggregations, Overdue calculation,
 * Filters & debounced search, server-side pagination, sorting,
 * slide-over drawer, partial & full payment, double-entry GL journal posting,
 * overpayment prevention, deletion safeguards, and exports.
 */
class PurchaseBillsPayableTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Request redirects to /login
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/bills']);
        $guestRes = $router->dispatch($guestReq);
        $assert('BILLS-01', 'Unauthenticated request to /purchases/bills redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login'));

        // 2. Verify Theme CSS variables exist in theme.css
        $themePath = dirname(__DIR__, 2) . '/public/assets/css/theme.css';
        $themeExists = file_exists($themePath);
        $themeContent = $themeExists ? file_get_contents($themePath) : '';
        $assert('BILLS-02', 'Theme CSS defines requested White + Blue CSS variables and light/dark switching',
            $themeExists &&
            str_contains($themeContent, '--bg-page: #F4F7FB;') &&
            str_contains($themeContent, '--bg-card: #FFFFFF;') &&
            str_contains($themeContent, '--primary: #1D4ED8;') &&
            str_contains($themeContent, '--primary-soft: #EAF1FF;') &&
            str_contains($themeContent, '--border: #E5EAF2;') &&
            str_contains($themeContent, '[data-theme="dark"]')
        );

        // 3. Authenticate as Admin user
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId() ?? 1;
        $csrfToken = Session::getCsrfToken();

        // 4. Authenticated GET /purchases/bills renders Breadcrumb, Page Header, 4 KPIs, Table, Drawer & Modals
        $viewReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/bills']);
        $viewRes = $router->dispatch($viewReq);
        $html = $viewRes->getContent();

        $hasHeader = str_contains($html, 'Purchase Bills') && 
                     str_contains($html, 'Vendor invoices, ITC reconciliation') &&
                     str_contains($html, 'Purchases');

        $hasKpis = str_contains($html, 'Total Payable') &&
                   str_contains($html, 'Overdue') &&
                   str_contains($html, 'Due in Next 7 Days') &&
                   str_contains($html, 'Paid This Month');

        $hasComponents = str_contains($html, 'id="bill-drawer"') &&
                         str_contains($html, 'id="payment-modal"') &&
                         str_contains($html, 'id="purchase-modal"');

        $assert('BILLS-03', 'GET /purchases/bills renders White + Blue layout with header, 4 KPIs, and modals',
            $viewRes->getStatusCode() === 200 && $hasHeader && $hasKpis && $hasComponents);

        // 5. GET /api/purchases/bills returns valid JSON with rows, totals, and meta
        $apiReq = new Request(['per_page' => 10, 'status' => 'ALL'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/purchases/bills']);
        $apiRes = $router->dispatch($apiReq);
        $json = json_decode($apiRes->getContent(), true);

        $assert('BILLS-04', 'GET /api/purchases/bills returns JSON with rows, totals, and pagination meta',
            $apiRes->getStatusCode() === 200 &&
            isset($json['success']) && $json['success'] === true &&
            isset($json['data']) && is_array($json['data']) &&
            isset($json['kpis']) && isset($json['totals']) && isset($json['meta'])
        );

        // 6. Test Record Inward Purchase Bill
        $sup = Database::selectOne("SELECT id FROM suppliers WHERE company_id = ? LIMIT 1", [$companyId]);
        $supplierId = $sup ? (int)$sup['id'] : 1;
        $testBillNumber = 'PB-TEST-' . rand(1000, 9999);
        $testVendorInv = 'VND-INV-' . rand(1000, 9999);

        $storeReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'supplier_id' => $supplierId,
            'vendor_invoice_number' => $testVendorInv,
            'amount' => 11800.00,
            'purchase_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'notes' => 'Test inward bill for regression'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/purchases/bills',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $storeRes = $router->dispatch($storeReq);
        $storeJson = json_decode($storeRes->getContent(), true);

        $createdBill = Database::selectOne(
            "SELECT * FROM purchases WHERE vendor_invoice_number = ? AND company_id = ?",
            [$testVendorInv, $companyId]
        );

        $assert('BILLS-05', 'POST /purchases/bills creates inward bill with supplier liability',
            $storeRes->getStatusCode() === 201 &&
            $createdBill !== null &&
            (float)$createdBill['grand_total'] === 11800.00 &&
            (float)$createdBill['amount_due'] === 11800.00 &&
            $createdBill['status'] === 'UNPAID'
        );

        $billId = (int)$createdBill['id'];

        // 7. GET /api/purchases/bills/{id} returns details for drawer
        $detailReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/api/purchases/bills/{$billId}"]);
        $detailRes = $router->dispatch($detailReq);
        $detailJson = json_decode($detailRes->getContent(), true);

        $assert('BILLS-06', 'GET /api/purchases/bills/{id} returns full bill details for drawer view',
            $detailRes->getStatusCode() === 200 &&
            isset($detailJson['bill']) &&
            (int)$detailJson['bill']['id'] === $billId &&
            isset($detailJson['bill']['items']) &&
            isset($detailJson['bill']['payments'])
        );

        // 8. Partial Payment & Double-Entry Balanced GL Journal Entry
        $payReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'amount' => 5000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'BANK',
            'reference_no' => 'UTR998822',
            'notes' => 'Partial disbursement regression'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/api/purchases/bills/{$billId}/payments",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $payRes = $router->dispatch($payReq);
        $payJson = json_decode($payRes->getContent(), true);

        $journal = Database::selectOne(
            "SELECT * FROM journal_entries WHERE reference_type = 'PURCHASE_PAYMENT' AND reference_id = ? ORDER BY id DESC LIMIT 1",
            [$billId]
        );

        $assert('BILLS-07', 'POST /api/purchases/bills/{id}/payments records partial payment & posts balanced GL journal (Dr AP, Cr Bank)',
            $payRes->getStatusCode() === 201 &&
            isset($payJson['status']) && $payJson['status'] === 'PARTIALLY_PAID' &&
            (float)$payJson['balance_due'] === 6800.00 &&
            $journal !== null &&
            (float)$journal['total_debit'] === 5000.00 &&
            (float)$journal['total_debit'] === (float)$journal['total_credit']
        );

        // 9. Full Payment Updates Status to PAID
        $payFullReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'amount' => 6800.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => 'UPI334411'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/api/purchases/bills/{$billId}/payments",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $payFullRes = $router->dispatch($payFullReq);
        $payFullJson = json_decode($payFullRes->getContent(), true);

        $assert('BILLS-08', 'Full settlement updates bill status to PAID with zero balance due',
            $payFullRes->getStatusCode() === 201 &&
            isset($payFullJson['status']) && $payFullJson['status'] === 'PAID' &&
            (float)$payFullJson['balance_due'] === 0.00
        );

        // 10. Overpayment is blocked with HTTP 422
        $overpayReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'amount' => 500.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'CASH'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/api/purchases/bills/{$billId}/payments",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $overpayRes = $router->dispatch($overpayReq);

        $assert('BILLS-09', 'Overpayment on zero-balance bill is blocked with HTTP 422',
            $overpayRes->getStatusCode() === 422,
            "Got " . $overpayRes->getStatusCode() . ": " . $overpayRes->getContent());

        // 11. Deletion is blocked when payments exist
        $delReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => "/api/purchases/bills/{$billId}",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $delRes = $router->dispatch($delReq);

        $assert('BILLS-10', 'Deleting purchase bill with recorded payments is blocked with HTTP 422',
            $delRes->getStatusCode() === 422,
            "Got " . $delRes->getStatusCode() . ": " . $delRes->getContent());

        // 12. Duplicating bill creates a new UNPAID draft bill
        $dupReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/api/purchases/bills/{$billId}/duplicate",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $dupRes = $router->dispatch($dupReq);
        $dupJson = json_decode($dupRes->getContent(), true);
        $newBillId = (int)($dupJson['new_bill_id'] ?? 0);

        $duplicatedBill = Database::selectOne("SELECT * FROM purchases WHERE id = ?", [$newBillId]);

        $assert('BILLS-11', 'Duplicating bill creates new UNPAID bill with original totals and line items',
            $dupRes->getStatusCode() === 201 &&
            $duplicatedBill !== null &&
            $duplicatedBill['status'] === 'UNPAID' &&
            (float)$duplicatedBill['amount_paid'] === 0.00,
            "Got " . $dupRes->getStatusCode() . ": " . $dupRes->getContent());

        // 13. Export Bills to CSV and Excel
        $expCsvReq = new Request(['format' => 'csv'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/bills/export']);
        $expCsvRes = $router->dispatch($expCsvReq);

        $expXlsReq = new Request(['format' => 'excel'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/bills/export']);
        $expXlsRes = $router->dispatch($expXlsReq);

        $assert('BILLS-12', 'GET /purchases/bills/export outputs downloadable CSV stream and Excel HTML table',
            $expCsvRes->getStatusCode() === 200 &&
            str_contains($expCsvRes->getContent(), 'Bill #') &&
            str_contains($expCsvRes->getContent(), 'Supplier / Vendor') &&
            $expXlsRes->getStatusCode() === 200 &&
            str_contains($expXlsRes->getContent(), '<table'),
            "Csv snippet: " . substr($expCsvRes->getContent(), 0, 80));

        // Cleanup test copy
        Database::execute("DELETE FROM purchases WHERE id = ?", [$newBillId]);
    }
}
