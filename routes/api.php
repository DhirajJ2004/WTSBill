<?php

declare(strict_types=1);

use WTSBill\Controllers\AuthController;
use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Middleware\AuthenticationMiddleware;

/**
 * WTSBill ERP — Core REST API Routes
 *
 * @var Router $router
 */

// 1. System health check endpoint
$router->get('/api/health', function (Request $req) {
    try {
        $pdo = Database::pdo();
        $dbStatus = 'connected';
    } catch (\Throwable $e) {
        $dbStatus = 'disconnected: ' . $e->getMessage();
    }

    return Response::json([
        'status' => 'ok',
        'app' => Config::get('app.name', 'WTSBill ERP'),
        'environment' => Config::get('app.env', 'development'),
        'database' => $dbStatus,
        'php_version' => PHP_VERSION,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
});

// 2. Authentication API Endpoints
$authController = new AuthController();
$authMiddleware = new AuthenticationMiddleware();

$router->post('/api/v1/auth/login', [$authController, 'login']);
$router->post('/api/v1/auth/logout', [$authController, 'logout'], [$authMiddleware]);

$router->get('/api/v1/auth/me', function (Request $req) {
    $service = new \WTSBill\Services\AuthService();
    $user = $service->user();
    if (!$user) {
        return Response::error('Unauthenticated.', 401);
    }
    unset($user['password']);
    return Response::success(['user' => $user], 'Profile retrieved.');
}, [$authMiddleware]);

// 3. Products Catalog API
$productController = new \WTSBill\Controllers\ProductController();
$can = fn(string|array $perm) => new \WTSBill\Middleware\AuthorizationMiddleware($perm);

$router->get('/api/v1/products', function (Request $req) use ($productController) {
    $service = new \WTSBill\Services\ProductService();
    $catalog = $service->getCatalog((string)($req->query('search') ?? ''));
    return Response::success(['products' => $catalog, 'total' => count($catalog)]);
}, [$authMiddleware, $can('items.view')]);

$router->post('/api/v1/products', [$productController, 'store'], [$authMiddleware, $can('items.create')]);
$router->get('/api/v1/products/{id}', [$productController, 'show'], [$authMiddleware, $can('items.view')]);
$router->post('/api/v1/products/{id}/update', [$productController, 'update'], [$authMiddleware, $can('items.edit')]);
$router->post('/api/v1/products/{id}/delete', [$productController, 'delete'], [$authMiddleware, $can('items.delete')]);
$router->delete('/api/v1/products/{id}', [$productController, 'delete'], [$authMiddleware, $can('items.delete')]);

// 4. Categories Product Filter API
$salesController = new \WTSBill\Controllers\SalesController();
$router->get('/api/v1/categories/{id}/products', [$salesController, 'categoryProductsApi'], [$authMiddleware, $can(['invoices.create', 'invoices.edit', 'invoices.view', 'items.view'])]);

// 5. Category Management API
$inventoryController = new \WTSBill\Controllers\InventoryController();
$router->get('/api/v1/categories/{id}', [$inventoryController, 'categoryShow'], [$authMiddleware, $can('categories.view')]);
$router->post('/api/v1/categories/{id}/update', [$inventoryController, 'categoryUpdate'], [$authMiddleware, $can('categories.edit')]);
$router->post('/api/v1/categories/{id}/delete', [$inventoryController, 'categoryDelete'], [$authMiddleware, $can('categories.delete')]);
$router->delete('/api/v1/categories/{id}', [$inventoryController, 'categoryDelete'], [$authMiddleware, $can('categories.delete')]);
$router->post('/api/v1/categories/{id}/toggle-status', [$inventoryController, 'categoryToggleStatus'], [$authMiddleware, $can('categories.edit')]);

// 6. Payments & Verification API
$paymentController = new \WTSBill\Controllers\PaymentController();
$router->post('/api/v1/payments/{id}/verify', [$paymentController, 'verifyPayment'], [$authMiddleware, $can('payments.create')]);
$router->post('/api/v1/invoices/{id}/payments', [$salesController, 'recordPayment'], [$authMiddleware, $can('payments.create')]);
$router->post('/api/v1/invoices/{id}/verify-payment', [$salesController, 'verifyPayment'], [$authMiddleware, $can('payments.create')]);



