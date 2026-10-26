<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Middleware\AuthenticationMiddleware;
use WTSBill\Middleware\AuthorizationMiddleware;
use WTSBill\Middleware\TenantMiddleware;
use WTSBill\Services\AuthService;

/**
 * Access Control and Authorization Security Regression Test (Chunk 19).
 * Verifies Role-Based Access Control (RBAC), privilege escalation prevention,
 * and Insecure Direct Object Reference (IDOR) / cross-tenant boundary security.
 */
class AccessControlTest
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
        $salesOnly = new AuthorizationMiddleware(['SALES_EXECUTIVE'], $authService);
        $tenantGuard = new TenantMiddleware($authService);

        $router->get('/admin/users', fn() => Response::success(['users' => true]), [$adminOnly]);
        $router->get('/accounting/ledger', fn() => Response::success(['ledger' => true]), [$accountantOnly]);
        $router->get('/sales/invoices', fn() => Response::success(['invoices' => true]), [$salesOnly]);
        $router->get('/tenant/data', fn() => Response::success(['tenant' => true]), [$tenantGuard]);

        // 1. Guest Boundary Checks
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users']);
        $guestRes = $router->dispatch($guestReq);
        $assert('AC-01', 'Guest web request is redirected to /login (HTTP 302)', $guestRes->getStatusCode() === 302);

        $guestApiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users', 'HTTP_ACCEPT' => 'application/json']);
        $guestApiRes = $router->dispatch($guestApiReq);
        $assert('AC-02', 'Guest JSON API request returns HTTP 401 Unauthorized', $guestApiRes->getStatusCode() === 401);

        // 2. Admin Universal Access
        $authService->authenticate('anil.d@wtsbill.in', 'password123'); // ADMIN role
        $adminReq1 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users']);
        $adminRes1 = $router->dispatch($adminReq1);
        $adminReq2 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/ledger']);
        $adminRes2 = $router->dispatch($adminReq2);
        $assert('AC-03', 'Admin has universal access to admin and accounting modules (HTTP 200)', 
            $adminRes1->getStatusCode() === 200 && $adminRes2->getStatusCode() === 200);

        // 3. Accountant Role Isolation
        Session::clear();
        $authService->authenticate('priya.s@wtsbill.in', 'password123'); // ACCOUNTANT role
        
        $accReqAllowed = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/ledger']);
        $accResAllowed = $router->dispatch($accReqAllowed);
        $assert('AC-04', 'Accountant has legitimate access to accounting ledger (HTTP 200)', 
            $accResAllowed->getStatusCode() === 200);

        $accReqBlocked = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users']);
        $accResBlocked = $router->dispatch($accReqBlocked);
        $assert('AC-05', 'Accountant is strictly forbidden from admin configuration (HTTP 403)', 
            $accResBlocked->getStatusCode() === 403);

        // 4. Sales Executive Role Isolation
        Session::clear();
        $authService->authenticate('neha.g@wtsbill.in', 'password123'); // SALES_EXECUTIVE role
        
        $salesReqAllowed = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/invoices']);
        $salesResAllowed = $router->dispatch($salesReqAllowed);
        $assert('AC-06', 'Sales Executive has legitimate access to sales invoices (HTTP 200)', 
            $salesResAllowed->getStatusCode() === 200);

        $salesReqBlocked = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounting/ledger']);
        $salesResBlocked = $router->dispatch($salesReqBlocked);
        $assert('AC-07', 'Sales Executive is strictly forbidden from accounting ledger (HTTP 403)', 
            $salesResBlocked->getStatusCode() === 403);

        // 5. Insecure Direct Object Reference (IDOR) & Tenant Boundary
        Session::clear();
        $authService->authenticate('anil.d@wtsbill.in', 'password123'); // Active Company #1
        
        // A. Tampering via Query Parameter (?company_id=2)
        $idorQueryReq = new Request(['company_id' => '2'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tenant/data?company_id=2']);
        $idorQueryRes = $router->dispatch($idorQueryReq);
        $assert('AC-08', 'IDOR attempt via query param company_id=2 is blocked by TenantMiddleware (HTTP 403)', 
            $idorQueryRes->getStatusCode() === 403);

        // B. Tampering via Header (X-Company-Id: 2)
        $idorHeaderReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tenant/data', 'HTTP_X_COMPANY_ID' => '2']);
        $idorHeaderRes = $router->dispatch($idorHeaderReq);
        $assert('AC-09', 'IDOR attempt via X-Company-Id: 2 header is blocked by TenantMiddleware (HTTP 403)', 
            $idorHeaderRes->getStatusCode() === 403);

        // C. Legitimate matching tenant request allowed
        $legitTenantReq = new Request(['company_id' => '1'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tenant/data?company_id=1']);
        $legitTenantRes = $router->dispatch($legitTenantReq);
        $assert('AC-10', 'Legitimate request with matching company_id is allowed through (HTTP 200)', 
            $legitTenantRes->getStatusCode() === 200);

        // Clean up
        Session::clear();
    }
}
