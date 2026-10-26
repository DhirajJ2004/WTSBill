<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\ProductService;

/**
 * Feature Test for Product & Inventory Management (Chunk 7 Recovery).
 * Verifies the working product registration modal, schema fields, server validation,
 * duplicate SKU prevention, persistence, editing, and authorization.
 */
class ProductManagementTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Request to /products Redirects to /login
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products']);
        $guestRes = $router->dispatch($guestReq);
        $assert('PROD-01', 'Unauthenticated request to /products redirects to /login (HTTP 302)', 
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login'));

        // 2. Authenticate as Company 1 Admin
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId();
        $csrfToken = Session::getCsrfToken();

        // 3. Authenticated GET /products renders catalog and working modal dialog
        $catalogReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products']);
        $catalogRes = $router->dispatch($catalogReq);
        $content = $catalogRes->getContent();

        $hasModal = str_contains($content, 'id="product-modal"') && str_contains($content, 'id="product-form"');
        $noAlert = !str_contains($content, "alert('Product registration modal opens in next chunk.')");
        $assert('PROD-02', 'GET /products renders working product modal without placeholder alert', 
            $catalogRes->getStatusCode() === 200 && $hasModal && $noAlert);

        // 4. Server Validation Rejection on Missing Required Fields
        $invalidReq = new Request([], [
            'name' => '', // Missing required name
            'sku' => '',  // Missing required SKU
            'sales_price' => -50.00, // Invalid negative price
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/products',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ]);
        $invalidRes = $router->dispatch($invalidReq);
        $invalidJson = json_decode($invalidRes->getContent(), true);

        $assert('PROD-03', 'Submitting empty required fields returns HTTP 422 with validation errors', 
            $invalidRes->getStatusCode() === 422 && !empty($invalidJson['errors']['name']) && !empty($invalidJson['errors']['sku']));

        // 5. Valid Product Registration & Database Persistence
        $testSku = 'TEST-VALVE-' . time();
        $testName = 'Precision Flow Valve ' . rand(100, 999);
        $createdId = 0;

        try {
            $validReq = new Request([], [
                'name' => $testName,
                'sku' => $testSku,
                'category_id' => 1,
                'unit' => 'Pcs',
                'hsn_sac' => '84818090',
                'sales_price' => 2450.00,
                'purchase_price' => 1800.00,
                'tax_rate' => 18,
                'opening_stock' => 50.000,
                'min_stock_alert' => 10,
                'barcode' => 'BAR-' . $testSku,
                'description' => 'Automated test valve registration with opening inventory',
                '_token' => $csrfToken,
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/products',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ]);

            $validRes = $router->dispatch($validReq);
            $validJson = json_decode($validRes->getContent(), true);
            $createdId = $validJson['product']['id'] ?? 0;

            $dbProduct = Database::selectOne(
                "SELECT * FROM products WHERE id = ? AND company_id = ?",
                [$createdId, $companyId]
            );

            $assert('PROD-04', 'Valid product is created via POST /products and persists in database with company_id', 
                $validRes->getStatusCode() === 201 && $dbProduct !== null && $dbProduct['sku'] === $testSku && (float)$dbProduct['sales_price'] === 2450.00);

            // 6. Duplicate SKU Prevention Rule
            $dupReq = new Request([], [
                'name' => 'Duplicate Attempt',
                'sku' => $testSku, // Same SKU
                'sales_price' => 100.00,
                '_token' => $csrfToken,
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/products',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ]);
            $dupRes = $router->dispatch($dupReq);
            $dupJson = json_decode($dupRes->getContent(), true);

            $assert('PROD-05', 'Duplicate SKU registration for same company is rejected with meaningful error', 
                $dupRes->getStatusCode() === 422 && !empty($dupJson['errors']['sku']));

            // 7. Product Retrieval for Edit Modal via GET /products/{id}
            $getReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/products/{$createdId}",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $getRes = $router->dispatch($getReq, ['id' => (string)$createdId]);
            $getJson = json_decode($getRes->getContent(), true);

            $assert('PROD-06', 'Product retrieval endpoint returns accurate JSON data for modal population', 
                $getRes->getStatusCode() === 200 && ($getJson['product']['name'] ?? '') === $testName);

            // 8. Product Update via POST /products/{id}/update
            $updatedName = $testName . ' (Updated Edition)';
            $updateReq = new Request([], [
                'id' => $createdId,
                'name' => $updatedName,
                'sales_price' => 2800.00,
                'tax_rate' => 18,
                '_token' => $csrfToken,
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/products/{$createdId}/update",
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ]);
            $updateRes = $router->dispatch($updateReq, ['id' => (string)$createdId]);
            $updateJson = json_decode($updateRes->getContent(), true);

            $updatedDb = Database::selectOne("SELECT name, sales_price FROM products WHERE id = ?", [$createdId]);
            $assert('PROD-07', 'Product update endpoint modifies record in database accurately', 
                $updateRes->getStatusCode() === 200 && $updatedDb['name'] === $updatedName && (float)$updatedDb['sales_price'] === 2800.00);

            // 9. Catalog Listing displays the created product after page refresh
            $refreshReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products']);
            $refreshRes = $router->dispatch($refreshReq);
            $refreshContent = $refreshRes->getContent();

            $assert('PROD-08', 'Refreshed catalog page contains the newly registered product and updated name', 
                str_contains($refreshContent, $testSku) && str_contains($refreshContent, htmlspecialchars($updatedName, ENT_QUOTES)));
        } finally {
            if ($createdId > 0) {
                Database::statement("DELETE FROM stock_balances WHERE product_id = ?", [$createdId]);
                Database::statement("DELETE FROM products WHERE id = ?", [$createdId]);
            }
            Database::statement("DELETE FROM products WHERE sku LIKE 'TEST-VALVE-%'");
            $authService->logout();
            Session::clear();
        }
    }
}
