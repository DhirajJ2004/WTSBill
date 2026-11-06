<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\SalesOrderService;

/**
 * Feature Test Suite for Phase 3: Sales Order Management Workflow.
 * Verifies Quotation -> Sales Order -> Delivery Challan -> Tax Invoice pipeline,
 * numbering format (SO-YYYY-XXXX), multi-item GST splits, fulfillment status tracking,
 * printable documents, and conversion protection.
 */
class SalesOrderWorkflowTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Protection
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/orders']);
        $guestRes = $router->dispatch($guestReq);
        $assert('SO-01', 'Unauthenticated request to /sales/orders redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login')
        );

        // Authenticate as Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csrfToken = Session::getCsrfToken();

        // 2. GET /sales/orders renders HTML with status pills and actions
        $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/orders']);
        $indexRes = $router->dispatch($indexReq);
        $indexHtml = $indexRes->getContent();

        $assert('SO-02', 'GET /sales/orders returns HTTP 200 with Status Pills bar and order listings',
            $indexRes->getStatusCode() === 200 &&
            str_contains($indexHtml, 'Sales Orders') &&
            str_contains($indexHtml, 'All Orders') &&
            str_contains($indexHtml, 'Pending') &&
            str_contains($indexHtml, 'Confirmed')
        );

        // 3. Create Sales Order directly
        $cust = Database::selectOne("SELECT id FROM customers WHERE company_id = ? AND deleted_at IS NULL LIMIT 1", [$companyId]);
        $customerId = $cust ? (int)$cust['id'] : 1;
        $prod = Database::selectOne("SELECT id, name, COALESCE(selling_price, sales_price, 500) AS price FROM products WHERE company_id = ? AND is_active = 1 LIMIT 1", [$companyId]);
        $productId = $prod ? (int)$prod['id'] : 1;

        $createReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'customer_id' => $customerId,
            'order_date' => date('Y-m-d'),
            'expected_delivery' => date('Y-m-d', strtotime('+10 days')),
            'reference_no' => 'PO-REG-8899',
            'status' => 'CONFIRMED',
            'place_of_supply_state_code' => '27',
            'notes' => 'Direct enterprise regression test order',
            'items' => [
                [
                    'product_id' => $productId,
                    'item_name' => $prod['name'] ?? 'Premium Steel Fastener',
                    'hsn_sac' => '7318',
                    'quantity' => 10,
                    'unit_price' => 200.00,
                    'gst_rate' => 18,
                    'discount_rate' => 0,
                ],
            ],
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/orders/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $createRes = $router->dispatch($createReq);
        $createJson = json_decode($createRes->getContent(), true);

        $orderId = (int)($createJson['order']['id'] ?? 0);
        $dbOrder = Database::selectOne("SELECT * FROM sales_orders WHERE id = ? AND company_id = ?", [$orderId, $companyId]);
        $dbItems = Database::select("SELECT * FROM sales_order_items WHERE sales_order_id = ?", [$orderId]);

        $assert('SO-03', 'POST /sales/orders/create creates Sales Order with SO-YYYY-XXXX sequence and calculated tax splits',
            $createRes->getStatusCode() === 201 &&
            $orderId > 0 &&
            $dbOrder !== null &&
            str_starts_with((string)$dbOrder['order_number'], 'SO-' . date('Y') . '-') &&
            (float)$dbOrder['grand_total'] === 2360.00 &&
            count($dbItems) === 1 &&
            (float)$dbItems[0]['cgst_amount'] === 180.00 &&
            (float)$dbItems[0]['sgst_amount'] === 180.00
        );

        // 4. GET /sales/orders/{id} details view
        $showReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/orders/{$orderId}"]);
        $showRes = $router->dispatch($showReq);
        $showHtml = $showRes->getContent();

        $assert('SO-04', 'GET /sales/orders/{id} renders order details, buyer information, and ordered line items',
            $showRes->getStatusCode() === 200 &&
            str_contains($showHtml, $dbOrder['order_number']) &&
            str_contains($showHtml, 'Ordered Products &amp; Quantities') &&
            str_contains($showHtml, 'Taxable Subtotal') &&
            str_contains($showHtml, 'Generate Tax Invoice')
        );

        // 5. Printable Order Document
        $printReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/orders/{$orderId}/print"]);
        $printRes = $router->dispatch($printReq);
        $printHtml = $printRes->getContent();

        $assert('SO-05', 'GET /sales/orders/{id}/print renders clean printable layout with signatory and terms',
            $printRes->getStatusCode() === 200 &&
            str_contains($printHtml, 'SALES ORDER') &&
            str_contains($printHtml, $dbOrder['order_number']) &&
            str_contains($printHtml, 'Authorized Signatory') &&
            str_contains($printHtml, 'ORDER TOTAL')
        );

        // 6. Quotation to Sales Order Conversion
        // Create an accepted quotation
        $quoteNumber = 'QT-TEST-' . rand(1000, 9999);
        $quoteId = (int)Database::insert('quotations', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'customer_id' => $customerId,
            'quotation_number' => $quoteNumber,
            'quotation_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'sub_total' => 1000.00,
            'total_tax' => 180.00,
            'grand_total' => 1180.00,
            'status' => 'SENT',
            'place_of_supply_state_code' => '27',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Database::insert('quotation_items', [
            'company_id' => $companyId,
            'quotation_id' => $quoteId,
            'product_id' => $productId,
            'item_name' => 'Fastener Quotation Item',
            'hsn_sac' => '7318',
            'quantity' => 5,
            'unit' => 'Pcs',
            'unit_price' => 200.00,
            'taxable_value' => 1000.00,
            'gst_rate' => 18,
            'total_amount' => 1180.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $convReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/quotations/{$quoteId}/convert-to-order",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $convRes = $router->dispatch($convReq);
        $convJson = json_decode($convRes->getContent(), true);

        $convertedOrder = Database::selectOne(
            "SELECT * FROM sales_orders WHERE source_quotation_id = ? AND company_id = ?",
            [$quoteId, $companyId]
        );
        $updatedQuote = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$quoteId]);

        $assert('SO-06', 'POST /sales/quotations/{id}/convert-to-order converts quotation to Sales Order and updates quote status to ACCEPTED',
            $convRes->getStatusCode() === 201 &&
            $convertedOrder !== null &&
            (float)$convertedOrder['grand_total'] === 1180.00 &&
            $updatedQuote['status'] === 'ACCEPTED'
        );

        // 7. Duplicate Conversion Prevention
        $dupReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/quotations/{$quoteId}/convert-to-order",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $dupRes = $router->dispatch($dupReq);

        $assert('SO-07', 'Duplicate conversion of quotation to sales order is strictly prevented (HTTP 422)',
            $dupRes->getStatusCode() === 422
        );

        // 8. Sales Order to Delivery Challan Conversion
        $challanReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/orders/{$orderId}/convert-to-challan",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $challanRes = $router->dispatch($challanReq);
        $challanJson = json_decode($challanRes->getContent(), true);

        $savedChallan = Database::selectOne(
            "SELECT * FROM delivery_challans WHERE sales_order_id = ? AND company_id = ?",
            [$orderId, $companyId]
        );
        $orderAfterChallan = Database::selectOne("SELECT * FROM sales_orders WHERE id = ?", [$orderId]);

        $assert('SO-08', 'POST /sales/orders/{id}/convert-to-challan creates Delivery Challan and updates order status to DISPATCHED',
            $challanRes->getStatusCode() === 201 &&
            $savedChallan !== null &&
            str_starts_with((string)$savedChallan['challan_number'], 'DC-') &&
            $orderAfterChallan['status'] === 'DISPATCHED' &&
            $orderAfterChallan['fulfillment_status'] === 'DISPATCHED'
        );

        // 9. Sales Order to Tax Invoice Conversion
        $invReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/orders/{$orderId}/convert-to-invoice",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $invRes = $router->dispatch($invReq);
        $invJson = json_decode($invRes->getContent(), true);

        $savedInvoice = Database::selectOne(
            "SELECT * FROM invoices WHERE source_sales_order_id = ? AND company_id = ? AND deleted_at IS NULL",
            [$orderId, $companyId]
        );
        $orderAfterInvoice = Database::selectOne("SELECT * FROM sales_orders WHERE id = ?", [$orderId]);

        $assert('SO-09', 'POST /sales/orders/{id}/convert-to-invoice generates confirmed Tax Invoice and marks Sales Order as COMPLETED',
            $invRes->getStatusCode() === 201 &&
            $savedInvoice !== null &&
            (float)$savedInvoice['grand_total'] === 2360.00 &&
            $orderAfterInvoice['status'] === 'COMPLETED' &&
            $orderAfterInvoice['fulfillment_status'] === 'FULFILLED'
        );

        // 10. Deletion Safeguard: Order with active Tax Invoice cannot be deleted
        $delReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/orders/{$orderId}/delete",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $delRes = $router->dispatch($delReq);

        $assert('SO-10', 'Deleting a Sales Order that has already been converted to an active Tax Invoice is blocked (HTTP 422)',
            $delRes->getStatusCode() === 422
        );

        // Clean up converted quote and test order if needed
        if ($convertedOrder) {
            Database::execute("DELETE FROM sales_order_items WHERE sales_order_id = ?", [$convertedOrder['id']]);
            Database::execute("DELETE FROM sales_orders WHERE id = ?", [$convertedOrder['id']]);
        }
        Database::execute("DELETE FROM quotation_items WHERE quotation_id = ?", [$quoteId]);
        Database::execute("DELETE FROM quotations WHERE id = ?", [$quoteId]);
    }
}

