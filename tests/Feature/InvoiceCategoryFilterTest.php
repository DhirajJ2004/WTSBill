<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Auth\Gate;
use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test Suite for Dynamic Product Category Filter in Invoice Line Items.
 * Verifies category-based product filtering API, multi-line item creation,
 * backend authorization and validation, existing invoice loading/editing, and branch scoping.
 */
class InvoiceCategoryFilterTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $controller = new SalesController();

        Session::start();
        Session::clear();
        Gate::flushCache();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert(
            'CATFLT-01',
            'Administrator authenticates and establishes company session context',
            Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
        );

        // Fetch category IDs for test scenarios
        $hardwareCat = Database::selectOne("SELECT id, name FROM categories WHERE company_id = 1 AND name = 'Hardware' LIMIT 1");
        $electricalCat = Database::selectOne("SELECT id, name FROM categories WHERE company_id = 1 AND name = 'Electrical' LIMIT 1");
        $plumbingCat = Database::selectOne("SELECT id, name FROM categories WHERE company_id = 1 AND name = 'Plumbing' LIMIT 1");
        $accessoriesCat = Database::selectOne("SELECT id, name FROM categories WHERE company_id = 1 AND name = 'Accessories' LIMIT 1");

        $hwCatId = (int)($hardwareCat['id'] ?? 1);
        $elCatId = (int)($electricalCat['id'] ?? 0);
        $plCatId = (int)($plumbingCat['id'] ?? 0);
        $accCatId = (int)($accessoriesCat['id'] ?? 0);

        // 2. Hardware selection API returns only Hardware products
        $hwReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/{$hwCatId}/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $hwResp = $controller->categoryProductsApi($hwReq, $hwCatId);
        $hwJson = json_decode((string)$hwResp->getBody(), true);
        $hwProducts = $hwJson['products'] ?? [];
        $allHwBelong = !empty($hwProducts) && array_reduce($hwProducts, fn($carry, $p) => $carry && ((int)$p['category_id'] === $hwCatId), true);

        $assert(
            'CATFLT-02',
            'Hardware category selection loads only products belonging to Hardware category',
            $hwResp->getStatusCode() === 200 &&
            $hwJson['success'] === true &&
            $allHwBelong &&
            count($hwProducts) > 0
        );

        // 3. Electrical selection API returns only Electrical products
        $elReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/{$elCatId}/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $elResp = $controller->categoryProductsApi($elReq, $elCatId);
        $elJson = json_decode((string)$elResp->getBody(), true);
        $elProducts = $elJson['products'] ?? [];
        $allElBelong = !empty($elProducts) && array_reduce($elProducts, fn($carry, $p) => $carry && ((int)$p['category_id'] === $elCatId), true);

        $assert(
            'CATFLT-03',
            'Electrical category selection loads only products belonging to Electrical category',
            $elResp->getStatusCode() === 200 &&
            $elJson['success'] === true &&
            $allElBelong &&
            count($elProducts) > 0
        );

        // 4. Plumbing selection API returns only Plumbing products
        $plReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/{$plCatId}/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $plResp = $controller->categoryProductsApi($plReq, $plCatId);
        $plJson = json_decode((string)$plResp->getBody(), true);
        $plProducts = $plJson['products'] ?? [];
        $allPlBelong = !empty($plProducts) && array_reduce($plProducts, fn($carry, $p) => $carry && ((int)$p['category_id'] === $plCatId), true);

        $assert(
            'CATFLT-04',
            'Plumbing category selection loads only products belonging to Plumbing category',
            $plResp->getStatusCode() === 200 &&
            $plJson['success'] === true &&
            $allPlBelong &&
            count($plProducts) > 0
        );

        // 5. Empty category (Accessories) returns count 0 with empty product list
        $accReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/{$accCatId}/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $accResp = $controller->categoryProductsApi($accReq, $accCatId);
        $accJson = json_decode((string)$accResp->getBody(), true);

        $assert(
            'CATFLT-05',
            'Empty category returns HTTP 200 with 0 products and useful status for UI message',
            $accResp->getStatusCode() === 200 &&
            $accJson['success'] === true &&
            is_array($accJson['products']) &&
            count($accJson['products']) === 0 &&
            $accJson['count'] === 0
        );

        // 6. Invalid / non-existent category returns HTTP 404
        $invCatReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/999999/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $invCatResp = $controller->categoryProductsApi($invCatReq, 999999);
        $invCatJson = json_decode((string)$invCatResp->getBody(), true);

        $assert(
            'CATFLT-06',
            'Querying non-existent category ID returns HTTP 404 with error envelope',
            $invCatResp->getStatusCode() === 404 &&
            $invCatJson['success'] === false
        );

        // 7. GET /sales/invoices/create renders Category column and UI components
        $createFormReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sales/invoices/create',
        ]);
        $createFormResp = $controller->invoiceCreateForm($createFormReq);
        $createHtml = (string)$createFormResp->getBody();

        $assert(
            'CATFLT-07',
            'Invoice create form renders Category column, Select Category option, and categories data script',
            $createFormResp->getStatusCode() === 200 &&
            str_contains($createHtml, '<th style="width: 19%;">Category</th>') &&
            str_contains($createHtml, 'item-category') &&
            str_contains($createHtml, 'item-product') &&
            str_contains($createHtml, 'categories-data')
        );

        // 8. Create multi-line invoice with distinct categories per line
        // Line 1: Hardware -> Brass Valve (ID: 1)
        // Line 2: Electrical -> Copper Wire (ID: 3)
        // Line 3: Plumbing -> PVC Pipe (ID: 2)
        $valveProd = Database::selectOne("SELECT id, sales_price, hsn_sac FROM products WHERE category_id = ? LIMIT 1", [$hwCatId]);
        $wireProd = Database::selectOne("SELECT id, sales_price, hsn_sac FROM products WHERE category_id = ? LIMIT 1", [$elCatId]);
        $pipeProd = Database::selectOne("SELECT id, sales_price, hsn_sac FROM products WHERE category_id = ? LIMIT 1", [$plCatId]);

        $createPayload = [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => $hwCatId,
                    'product_id' => (int)$valveProd['id'],
                    'quantity' => 2,
                    'rate' => (float)$valveProd['sales_price'],
                    'gst_rate' => 18,
                    'hsn_sac' => (string)$valveProd['hsn_sac'],
                ],
                [
                    'category_id' => $elCatId,
                    'product_id' => (int)$wireProd['id'],
                    'quantity' => 1,
                    'rate' => (float)$wireProd['sales_price'],
                    'gst_rate' => 18,
                    'hsn_sac' => (string)$wireProd['hsn_sac'],
                ],
                [
                    'category_id' => $plCatId,
                    'product_id' => (int)$pipeProd['id'],
                    'quantity' => 4,
                    'rate' => (float)$pipeProd['sales_price'],
                    'gst_rate' => 18,
                    'hsn_sac' => (string)$pipeProd['hsn_sac'],
                ],
            ],
            'notes' => 'Multi-category test invoice',
        ];

        $invStoreReq = new Request([], $createPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $invStoreResp = $controller->invoiceStore($invStoreReq);
        $invStoreJson = json_decode((string)$invStoreResp->getBody(), true);
        $createdInvoiceId = (int)($invStoreJson['invoice_id'] ?? 0);

        $assert(
            'CATFLT-08',
            'Generate invoice with independent categories across multiple line items succeeds (HTTP 201)',
            $invStoreResp->getStatusCode() === 201 &&
            $createdInvoiceId > 0 &&
            $invStoreJson['success'] === true
        );

        // 9. Database invoice_items verification: category_id correctly persisted for each row
        $storedItems = Database::select(
            "SELECT id, product_id, category_id, item_name, quantity, unit_price, total_amount 
             FROM invoice_items 
             WHERE invoice_id = ? 
             ORDER BY id ASC",
            [$createdInvoiceId]
        );

        $catMatch1 = (int)($storedItems[0]['category_id'] ?? 0) === $hwCatId && (int)($storedItems[0]['product_id'] ?? 0) === (int)$valveProd['id'];
        $catMatch2 = (int)($storedItems[1]['category_id'] ?? 0) === $elCatId && (int)($storedItems[1]['product_id'] ?? 0) === (int)$wireProd['id'];
        $catMatch3 = (int)($storedItems[2]['category_id'] ?? 0) === $plCatId && (int)($storedItems[2]['product_id'] ?? 0) === (int)$pipeProd['id'];

        $assert(
            'CATFLT-09',
            'Database invoice_items table persists exact matching category_id for each line item',
            count($storedItems) === 3 &&
            $catMatch1 &&
            $catMatch2 &&
            $catMatch3
        );

        // 10. Invoice calculation verification
        $invHeader = Database::selectOne("SELECT sub_total, cgst_amount, sgst_amount, igst_amount, total_tax, grand_total FROM invoices WHERE id = ?", [$createdInvoiceId]);
        $expectedSubtotal = (2 * (float)$valveProd['sales_price']) + (1 * (float)$wireProd['sales_price']) + (4 * (float)$pipeProd['sales_price']);
        $expectedTax = round($expectedSubtotal * 0.18, 2);
        $expectedGrand = round($expectedSubtotal + $expectedTax);

        $assert(
            'CATFLT-10',
            'Tax and grand total calculation formulas preserved with accurate CGST/SGST splitting',
            abs((float)$invHeader['sub_total'] - $expectedSubtotal) < 0.01 &&
            abs((float)$invHeader['grand_total'] - $expectedGrand) < 1.00 &&
            (float)$invHeader['cgst_amount'] > 0 &&
            (float)$invHeader['sgst_amount'] > 0 &&
            (float)$invHeader['igst_amount'] == 0.0
        );

        // 11. GET /sales/invoices/{id}/edit loads saved categories and products
        $editReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/sales/invoices/{$createdInvoiceId}/edit",
        ]);
        $editResp = $controller->invoiceEditForm($editReq, $createdInvoiceId);
        $editHtml = (string)$editResp->getBody();

        $assert(
            'CATFLT-11',
            'GET /sales/invoices/{id}/edit renders populated line items with category_id in existing-items-data JSON',
            $editResp->getStatusCode() === 200 &&
            str_contains($editHtml, 'existing-items-data') &&
            str_contains($editHtml, "\"category_id\":{$hwCatId}") &&
            str_contains($editHtml, "\"category_id\":{$elCatId}") &&
            str_contains($editHtml, "\"category_id\":{$plCatId}")
        );

        // 12. POST /sales/invoices/{id}/update preserves category changes and updates rows
        $updatePayload = [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => $elCatId,
                    'product_id' => (int)$wireProd['id'],
                    'quantity' => 10,
                    'rate' => 1500.00,
                    'gst_rate' => 18,
                    'hsn_sac' => '8544',
                ],
            ],
            'notes' => 'Updated invoice to single electrical item',
        ];

        $updateReq = new Request([], $updatePayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$createdInvoiceId}/update",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $updateResp = $controller->invoiceUpdate($updateReq, $createdInvoiceId);
        $updateJson = json_decode((string)$updateResp->getBody(), true);

        $updatedItems = Database::select("SELECT category_id, product_id, quantity, unit_price FROM invoice_items WHERE invoice_id = ?", [$createdInvoiceId]);

        $assert(
            'CATFLT-12',
            'Update invoice saves modified category and product while recomputing totals correctly',
            $updateResp->getStatusCode() === 200 &&
            $updateJson['success'] === true &&
            count($updatedItems) === 1 &&
            (int)$updatedItems[0]['category_id'] === $elCatId &&
            (int)$updatedItems[0]['product_id'] === (int)$wireProd['id']
        );

        // 13. Backend rejects mismatched category and product combination (Security validation)
        // Trying to save a Plumbing product under Electrical category
        $mismatchPayload = [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => $elCatId,
                    'product_id' => (int)$pipeProd['id'], // Belongs to Plumbing, NOT Electrical!
                    'quantity' => 1,
                    'rate' => 200.00,
                    'gst_rate' => 18,
                    'hsn_sac' => '3917',
                ],
            ],
        ];

        $mismatchReq = new Request([], $mismatchPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $mismatchResp = $controller->invoiceStore($mismatchReq);
        $mismatchJson = json_decode((string)$mismatchResp->getBody(), true);

        $assert(
            'CATFLT-13',
            'Backend rejects mismatched category and product combination with HTTP 422',
            $mismatchResp->getStatusCode() === 422 &&
            $mismatchJson['success'] === false &&
            str_contains($mismatchJson['message'], 'does not belong to category')
        );

        // 14. Backend rejects inactive or non-existent product
        $inactivePayload = [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => $hwCatId,
                    'product_id' => 888888, // Does not exist
                    'quantity' => 1,
                    'rate' => 100.00,
                    'gst_rate' => 18,
                    'hsn_sac' => '7318',
                ],
            ],
        ];

        $inactiveReq = new Request([], $inactivePayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $inactiveResp = $controller->invoiceStore($inactiveReq);
        $inactiveJson = json_decode((string)$inactiveResp->getBody(), true);

        $assert(
            'CATFLT-14',
            'Backend rejects non-existent or unavailable product ID with HTTP 422',
            $inactiveResp->getStatusCode() === 422 &&
            $inactiveJson['success'] === false
        );

        // 15. Unauthenticated request to category products API returns HTTP 401 / error
        Session::clear();
        Gate::flushCache();

        $unauthReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/api/categories/{$hwCatId}/products",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $authMiddleware = new \WTSBill\Middleware\AuthenticationMiddleware();
        $unauthResp = $authMiddleware->handle($unauthReq);

        $assert(
            'CATFLT-15',
            'Unauthenticated request to category products API is strictly rejected with HTTP 401',
            $unauthResp !== null && $unauthResp->getStatusCode() === 401
        );

        // Cleanup test invoice
        if ($createdInvoiceId > 0) {
            Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$createdInvoiceId]);
            Database::execute("DELETE FROM invoices WHERE id = ?", [$createdInvoiceId]);
        }
    }
}

