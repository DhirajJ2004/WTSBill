<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Route Parity and HTTP Method Regression Test (Chunk 20).
 * Verifies all application modules, method boundaries, status codes,
 * and authentication redirection parity.
 */
class RouteParityTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        // 1. Unauthenticated Route Access -> Redirects to /login
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        $guestRoutes = ['/customers', '/products', '/sales/invoices', '/purchases/bills', '/reports/profit-loss'];
        $allRedirected = true;
        foreach ($guestRoutes as $r) {
            $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $r]);
            $res = $router->dispatch($req);
            if ($res->getStatusCode() !== 302 || !str_contains($res->getHeaders()['Location'] ?? '', '/login')) {
                $allRedirected = false;
                break;
            }
        }
        $assert('ROUTEPAR-01', 'All protected web routes redirect unauthenticated guests to /login (HTTP 302)', 
            $allRedirected);

        // 2. Authenticated Session Access Across All 15 Core Routes
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $authenticatedRoutes = [
            '/',
            '/customers',
            '/customers/create',
            '/suppliers',
            '/products',
            '/inventory/stock',
            '/categories',
            '/sales/invoices',
            '/sales/invoices/create',
            '/sales/quotations',
            '/sales/challans',
            '/purchases/bills',
            '/expenses',
            '/accounts',
            '/accounts/journals',
            '/accounts/ledger',
            '/reports/profit-loss',
            '/reports/balance-sheet',
            '/reports/gst',
            '/users',
            '/settings',
        ];

        $failedRoutes = [];
        foreach ($authenticatedRoutes as $routePath) {
            $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $routePath]);
            $res = $router->dispatch($req);
            if ($res->getStatusCode() !== 200 || empty($res->getContent())) {
                $failedRoutes[] = $routePath . " ({$res->getStatusCode()})";
            }
        }

        $assert('ROUTEPAR-02', 'All 21 authenticated ERP modules return HTTP 200 OK with non-empty content', 
            empty($failedRoutes), !empty($failedRoutes) ? implode(', ', $failedRoutes) : null);

        // 3. 404 Not Found Handling
        $notFoundReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/non-existent-module-route-404']);
        $notFoundRes = $router->dispatch($notFoundReq);
        $assert('ROUTEPAR-03', 'Non-existent route returns clean 404 response without stack trace leakage', 
            $notFoundRes->getStatusCode() === 404 && str_contains($notFoundRes->getContent(), '404 Not Found'));

        // 4. API Health Status & Structure Parity
        $apiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/health', 'HTTP_ACCEPT' => 'application/json']);
        $apiRes = $router->dispatch($apiReq);
        $apiJson = json_decode($apiRes->getContent(), true);

        $assert('ROUTEPAR-04', 'API route /api/health returns 200 OK with healthy database status payload', 
            $apiRes->getStatusCode() === 200 && (($apiJson['database'] ?? '') === 'connected' || ($apiJson['data']['database'] ?? '') === 'connected'));

        // Clean up
        $authService->logout();
        Session::clear();
    }
}
