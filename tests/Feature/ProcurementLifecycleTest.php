<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\PurchaseOrderService;

/**
 * Feature Test Suite for Phase 5: Procurement Lifecycle (Purchase Orders, Goods Receipts & Returns).
 * Verifies PO creation (PO-YYYY-XXXX), approval, GRN goods receipt into warehouse stock,
 * conversion to Inward Purchase Bill (PB-YYYY-XXXX), duplicate conversion blocking,
 * and Debit Notes (DN-YYYY-XXXX) liability reversals.
 */
class ProcurementLifecycleTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Request Protection
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/orders']);
        $guestRes = $router->dispatch($guestReq);
        $assert('PO-01', 'Unauthenticated request to /purchases/orders redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login')
        );

        // Authenticate as Administrator
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csrfToken = Session::getCsrfToken();

        // 2. GET /purchases/orders list view
        $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/purchases/orders']);
        $indexRes = $router->dispatch($indexReq);
        $indexHtml = $indexRes->getContent();

        $assert('PO-02', 'GET /purchases/orders returns HTTP 200 with Status Pills bar and PO listings',
            $indexRes->getStatusCode() === 200 &&
            str_contains($indexHtml, 'Purchase Orders') &&
            str_contains($indexHtml, 'All Orders') &&
            str_contains($indexHtml, 'Pending') &&
            str_contains($indexHtml, 'Received')
        );

        // 3. POST /purchases/orders/create creates Purchase Order
        $supp = Database::selectOne("SELECT id, name FROM suppliers WHERE company_id = ? AND deleted_at IS NULL LIMIT 1", [$companyId]);
        $supplierId = $supp ? (int)$supp['id'] : 1;

        $prod = Database::selectOne("SELECT id, name, COALESCE(purchase_price, 250) AS price FROM products WHERE company_id = ? AND is_active = 1 LIMIT 1", [$companyId]);
        $productId = $prod ? (int)$prod['id'] : 1;

        $poPayload = [
            '_csrf_token' => $csrfToken,
            'supplier_id' => $supplierId,
            'branch_id' => 1,
            'po_date' => date('Y-m-d'),
            'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
            'reference_no' => 'REQ-PO-' . rand(100, 999),
            'status' => 'PENDING',
            'notes' => 'Test procurement order for raw materials',
            'terms' => 'Standard commercial payment terms 30 days',
            'items' => [
                [
                    'product_id' => $productId,
                    'item_name' => $prod['name'] ?? 'Raw Material Test Item',
                    'quantity' => 20.0,
                    'unit_price' => 250.00,
                    'gst_rate' => 18,
                    'unit' => 'PCS',
                ]
            ],
        ];

        $createReq = new Request([], $poPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/purchases/orders/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $createRes = $router->dispatch($createReq);
        $createData = json_decode($createRes->getContent(), true);
        $poId = (int)($createData['id'] ?? 0);

        $dbPo = Database::selectOne("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?", [$poId, $companyId]);
        $dbPoItems = Database::select("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?", [$poId]);

        $assert('PO-03', 'POST /purchases/orders/create creates Purchase Order with PO-YYYY-XXXX sequence and tax calculations',
            $createRes->getStatusCode() === 201 &&
            $poId > 0 &&
            $dbPo !== null &&
            str_starts_with((string)$dbPo['po_number'], 'PO-' . date('Y') . '-') &&
            (float)$dbPo['grand_total'] === 5900.00 &&
            count($dbPoItems) === 1 &&
            (float)$dbPoItems[0]['taxable_value'] === 5000.00
        );

        // 4. GET /purchases/orders/{id} details view
        $showReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/purchases/orders/{$poId}"]);
        $showRes = $router->dispatch($showReq);
        $showHtml = $showRes->getContent();

        $assert('PO-04', 'GET /purchases/orders/{id} renders order details, supplier metadata, and ordered line items',
            $showRes->getStatusCode() === 200 &&
            str_contains($showHtml, $dbPo['po_number']) &&
            str_contains($showHtml, 'Ordered Items &amp; Fulfillment Progress') &&
            str_contains($showHtml, 'Supplier Details')
        );

        // 5. GET /purchases/orders/{id}/print printable layout
        $printReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/purchases/orders/{$poId}/print"]);
        $printRes = $router->dispatch($printReq);
        $printHtml = $printRes->getContent();

        $assert('PO-05', 'GET /purchases/orders/{id}/print renders clean printable Purchase Order document with signatory',
            $printRes->getStatusCode() === 200 &&
            str_contains($printHtml, 'PURCHASE ORDER') &&
            str_contains($printHtml, $dbPo['po_number']) &&
            str_contains($printHtml, 'Authorized Signatory')
        );

        // 6. POST /purchases/orders/{id}/approve transitions status to APPROVED
        $approveReq = new Request([], ['_csrf_token' => $csrfToken], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/purchases/orders/{$poId}/approve",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $approveRes = $router->dispatch($approveReq);
        $afterApprovePo = Database::selectOne("SELECT status FROM purchase_orders WHERE id = ?", [$poId]);

        $assert('PO-06', 'POST /purchases/orders/{id}/approve transitions order status from PENDING to APPROVED',
            $approveRes->getStatusCode() === 200 &&
            $afterApprovePo['status'] === 'APPROVED'
        );

        // 7. POST /purchases/orders/{id}/receive-goods records GRN and updates warehouse stock
        $poItemId = (int)$dbPoItems[0]['id'];
        $beforeBalance = Database::selectOne(
            "SELECT available_quantity FROM stock_balances WHERE company_id = ? AND warehouse_id = 1 AND product_id = ?",
            [$companyId, $productId]
        );
        $initialStock = (float)($beforeBalance['available_quantity'] ?? 0);

        $grnPayload = [
            '_csrf_token' => $csrfToken,
            'warehouse_id' => 1,
            'receipts' => [
                $poItemId => 20.0,
            ],
        ];

        $grnReq = new Request([], $grnPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/purchases/orders/{$poId}/receive-goods",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $grnRes = $router->dispatch($grnReq);

        $afterGrnPo = Database::selectOne("SELECT status FROM purchase_orders WHERE id = ?", [$poId]);
        $afterGrnItem = Database::selectOne("SELECT received_quantity FROM purchase_order_items WHERE id = ?", [$poItemId]);
        $afterBalance = Database::selectOne(
            "SELECT available_quantity FROM stock_balances WHERE company_id = ? AND warehouse_id = 1 AND product_id = ?",
            [$companyId, $productId]
        );
        $newStock = (float)($afterBalance['available_quantity'] ?? 0);

        $assert('PO-07', 'POST /purchases/orders/{id}/receive-goods (GRN) records received quantity, credits warehouse stock, and updates status to RECEIVED',
            $grnRes->getStatusCode() === 200 &&
            $afterGrnPo['status'] === 'RECEIVED' &&
            (float)$afterGrnItem['received_quantity'] === 20.0 &&
            $newStock === ($initialStock + 20.0)
        );

        // 8. POST /purchases/orders/{id}/convert-to-bill creates Inward Purchase Bill
        $billPayload = [
            '_csrf_token' => $csrfToken,
            'purchase_date' => date('Y-m-d'),
            'vendor_invoice_number' => 'VINV-TEST-9901',
            'warehouse_id' => 1,
        ];

        $convertReq = new Request([], $billPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/purchases/orders/{$poId}/convert-to-bill",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $convertRes = $router->dispatch($convertReq);
        $convertData = json_decode($convertRes->getContent(), true);
        $billId = (int)($convertData['bill_id'] ?? 0);

        $dbBill = Database::selectOne("SELECT * FROM purchases WHERE id = ? AND company_id = ?", [$billId, $companyId]);
        $dbBillItems = Database::select("SELECT * FROM purchase_items WHERE purchase_id = ?", [$billId]);

        $assert('PO-08', 'POST /purchases/orders/{id}/convert-to-bill converts PO into Inward Purchase Bill in purchases table',
            $convertRes->getStatusCode() === 201 &&
            $billId > 0 &&
            $dbBill !== null &&
            (int)$dbBill['purchase_order_id'] === $poId &&
            str_starts_with((string)$dbBill['purchase_number'], 'PB-' . date('Y') . '-') &&
            (float)$dbBill['grand_total'] === 5900.00 &&
            count($dbBillItems) === 1
        );

        // 9. Prevent duplicate conversion
        $dupConvertReq = new Request([], $billPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/purchases/orders/{$poId}/convert-to-bill",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $dupConvertRes = $router->dispatch($dupConvertReq);

        $assert('PO-09', 'Duplicate conversion of same PO to purchase bill is strictly blocked with HTTP 422',
            $dupConvertRes->getStatusCode() === 422 &&
            str_contains($dupConvertRes->getContent(), 'already been converted')
        );

        // 10. POST /purchases/debit-notes/create issues Debit Note
        $suppBefore = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$supplierId]);
        $suppBalBefore = (float)($suppBefore['current_balance'] ?? 0);

        $dnPayload = [
            '_csrf_token' => $csrfToken,
            'supplier_id' => $supplierId,
            'purchase_id' => $billId,
            'debit_note_date' => date('Y-m-d'),
            'amount' => 590.00,
            'reason' => 'Goods Damaged / Defective in Transit',
            'notes' => 'Damaged outer casing returned to vendor',
        ];

        $dnReq = new Request([], $dnPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/purchases/debit-notes/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $dnRes = $router->dispatch($dnReq);
        $dnData = json_decode($dnRes->getContent(), true);
        $dnId = (int)($dnData['id'] ?? 0);

        $dbDn = Database::selectOne("SELECT * FROM debit_notes WHERE id = ? AND company_id = ?", [$dnId, $companyId]);
        $suppAfter = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$supplierId]);
        $suppBalAfter = (float)($suppAfter['current_balance'] ?? 0);

        $assert('PO-10', 'POST /purchases/debit-notes/create issues Debit Note DN-YYYY-XXXX and reduces supplier liability balance',
            $dnRes->getStatusCode() === 201 &&
            $dnId > 0 &&
            $dbDn !== null &&
            str_starts_with((string)$dbDn['debit_note_number'], 'DN-' . date('Y') . '-') &&
            (float)$dbDn['amount'] === 590.00 &&
            $suppBalAfter < $suppBalBefore
        );

        // Clean up test records
        Database::delete('debit_notes', 'id = ?', [$dnId]);
        Database::delete('purchase_items', 'purchase_id = ?', [$billId]);
        Database::delete('purchases', 'id = ?', [$billId]);
        Database::delete('purchase_order_items', 'purchase_order_id = ?', [$poId]);
        Database::delete('purchase_orders', 'id = ?', [$poId]);
        if ($beforeBalance) {
            Database::update('stock_balances', ['available_quantity' => $initialStock, 'quantity' => $initialStock], 'company_id = ? AND warehouse_id = 1 AND product_id = ?', [$companyId, $productId]);
        }
    }
}
