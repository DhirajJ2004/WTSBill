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
$router->get('/api/v1/products', function (Request $req) use ($productController) {
    $service = new \WTSBill\Services\ProductService();
    $catalog = $service->getCatalog((string)($req->query('search') ?? ''));
    return Response::success(['products' => $catalog, 'total' => count($catalog)]);
}, [$authMiddleware]);

$router->post('/api/v1/products', [$productController, 'store'], [$authMiddleware]);
$router->get('/api/v1/products/{id}', [$productController, 'show'], [$authMiddleware]);
$router->post('/api/v1/products/{id}/update', [$productController, 'update'], [$authMiddleware]);
$router->post('/api/v1/products/{id}/delete', [$productController, 'delete'], [$authMiddleware]);

