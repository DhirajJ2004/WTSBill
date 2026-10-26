<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Middleware\AuthenticationMiddleware;
use WTSBill\Middleware\AuthorizationMiddleware;
use WTSBill\Services\AuthService;

/**
 * Feature Test for Authorization & RBAC Permissions.
 */
class AuthorizationTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        $auth = new AuthenticationMiddleware($authService);
        $adminOnly = new AuthorizationMiddleware(['ADMIN'], $authService);
        $accountantOnly = new AuthorizationMiddleware(['ACCOUNTANT'], $authService);

        // Protected test routes
        $router->get('/dashboard', fn() => Response::success(['page' => 'dashboard']), [$auth]);
        $router->get('/admin/settings', fn() => Response::success(['page' => 'settings']), [$adminOnly]);
        $router->get('/accounting/ledger', fn() => Response::success(['page' => 'ledger']), [$accountantOnly]);

        // 1. Unauthenticated Web request redirected to /login
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard', 'HTTP_ACCEPT' => 'text/html']);
        $guestRes = $router->dispatch($guestReq);
        $assert('AUTHZ-01', 'Guest web request is redirected (HTTP 302)', $guestRes->getStatusCode() === 302);
        $assert('AUTHZ-02', 'Redirect destination is /login', ($guestRes->getHeaders()['Location'] ?? null) === '/login');
        $assert('AUTHZ-03', 'Intended URL stored in session', Session::get('url.intended') === '/dashboard');

        // 2. Unauthenticated API request returns HTTP 401 JSON
        $apiGuestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard', 'HTTP_ACCEPT' => 'application/json']);
        $apiGuestRes = $router->dispatch($apiGuestReq);
        $assert('AUTHZ-04', 'Guest API request returns HTTP 401', $apiGuestRes->getStatusCode() === 401);
        $apiPayload = json_decode($apiGuestRes->getContent(), true);
        $assert('AUTHZ-05', '401 JSON envelope has success=false', $apiPayload['success'] === false);

        // 3. Admin Access: Anil Desai (ADMIN)
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert('AUTHZ-06', 'Admin hasRole(ADMIN) is true', $authService->hasRole('ADMIN') === true);
        $assert('AUTHZ-07', 'Admin has universal hasRole(ACCOUNTANT) access', $authService->hasRole('ACCOUNTANT') === true);

        // Admin accessing Admin route
        $adminSettingsReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/settings']);
        $adminSettingsRes = $router->dispatch($adminSettingsReq);
        $assert('AUTHZ-08', 'Admin accesses admin route (HTTP 200)', $adminSettingsRes->getStatusCode() === 200);

        // Admin accessing Accountant route
        $adminLedgerReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/ledger']);
        $adminLedgerRes = $router->dispatch($adminLedgerReq);
        $assert('AUTHZ-09', 'Admin accesses accountant route (HTTP 200)', $adminLedgerRes->getStatusCode() === 200);

        // 4. Accountant Access: Priya Sharma (ACCOUNTANT)
        $authService->logout();
        $authService->authenticate('priya.s@wtsbill.in', 'password123');
        $assert('AUTHZ-10', 'Accountant hasRole(ACCOUNTANT) is true', $authService->hasRole('ACCOUNTANT') === true);
        $assert('AUTHZ-11', 'Accountant hasRole(ADMIN) is false', $authService->hasRole('ADMIN') === false);

        // Accountant accessing accountant route -> ALLOWED
        $acctLedgerRes = $router->dispatch($adminLedgerReq);
        $assert('AUTHZ-12', 'Accountant accesses accounting route (HTTP 200)', $acctLedgerRes->getStatusCode() === 200);

        // Accountant accessing admin-only route -> FORBIDDEN (403)
        $acctSettingsRes = $router->dispatch($adminSettingsReq);
        $assert('AUTHZ-13', 'Accountant blocked from admin-only route (HTTP 403)', $acctSettingsRes->getStatusCode() === 403);

        // 5. Sales Executive Access: Neha Gupta (SALES_EXECUTIVE)
        $authService->logout();
        $authService->authenticate('neha.g@wtsbill.in', 'password123');
        $assert('AUTHZ-14', 'Sales Executive hasRole(SALES_EXECUTIVE) is true', $authService->hasRole('SALES_EXECUTIVE') === true);

        // Sales executive accessing accounting ledger -> FORBIDDEN (403)
        $salesLedgerRes = $router->dispatch($adminLedgerReq);
        $assert('AUTHZ-15', 'Sales Executive blocked from accounting route (HTTP 403)', $salesLedgerRes->getStatusCode() === 403);

        // Sales executive accessing admin settings -> FORBIDDEN (403)
        $salesSettingsRes = $router->dispatch($adminSettingsReq);
        $assert('AUTHZ-16', 'Sales Executive blocked from admin route (HTTP 403)', $salesSettingsRes->getStatusCode() === 403);

        // Clean up session
        $authService->logout();
    }
}
