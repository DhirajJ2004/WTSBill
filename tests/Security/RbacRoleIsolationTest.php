<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuthService;

/**
 * Enterprise RBAC Role Isolation & Server-Side Gate Enforcement Test Suite.
 * Validates strict role-based sidebar visibility, default-deny server-side gating,
 * dashboard specialization, and session boundary isolation without permission leakage.
 */
class RbacRoleIsolationTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';
        require ROOT_PATH . '/routes/api.php';

        // =========================================================================
        // PART 1: ACCOUNTANT ROLE ACCESS VERIFICATION
        // =========================================================================
        Session::start();
        Session::clear();
        Gate::flushCache();

        // 1a. Authenticate as Accountant (Priya Sharma - role_id 2 / accountant)
        $accountant = Database::selectOne("SELECT * FROM users WHERE email = 'priya.s@wtsbill.in' AND deleted_at IS NULL");
        if (!$accountant) {
            $accountant = Database::selectOne("SELECT * FROM users WHERE role_id = 2 AND deleted_at IS NULL");
        }
        $authService->authenticate($accountant['email'], 'password123');
        Gate::flushCache();

        $assert('RBAC-ACCT-01', 'Accountant successfully logs in and session establishes role accountant',
            Gate::role() === 'accountant' && Gate::user()['id'] === (int)$accountant['id']);

        // 1b. Render Layout & Sidebar as Accountant
        $acctAppHtml = View::renderWithLayout('dashboard/payments', [
            'title' => 'Payments Dashboard',
            'currentUri' => '/',
            'metrics' => ['receivables' => 10000, 'payables' => 5000, 'total_received' => 8000, 'total_paid' => 3000],
            'recentPayments' => [],
            'overdueInvoices' => [],
        ], 'layouts/app');

        // Verify Allowed Tabs in Sidebar
        $assert('RBAC-ACCT-02', 'Accountant sidebar renders Dashboard link', str_contains($acctAppHtml, 'Dashboard'));
        $assert('RBAC-ACCT-03', 'Accountant sidebar renders Tax Invoices tab', str_contains($acctAppHtml, 'Tax Invoices'));
        $assert('RBAC-ACCT-04', 'Accountant sidebar renders Quotations tab', str_contains($acctAppHtml, 'href="/sales/quotations"') && str_contains($acctAppHtml, 'Quotations'));
        $assert('RBAC-ACCT-05', 'Accountant sidebar renders Expenses tab', str_contains($acctAppHtml, 'href="/expenses"'));
        $assert('RBAC-ACCT-06', 'Accountant sidebar renders Payments & Receipts tab', str_contains($acctAppHtml, 'href="/payments"'));

        // Verify Hidden Tabs in Sidebar
        $assert('RBAC-ACCT-07', 'Accountant sidebar strictly hides Customers tab', !str_contains($acctAppHtml, 'href="/customers"'));
        $assert('RBAC-ACCT-08', 'Accountant sidebar strictly hides Suppliers tab', !str_contains($acctAppHtml, 'href="/suppliers"'));
        $assert('RBAC-ACCT-09', 'Accountant sidebar strictly hides Manage Inventory tab', !str_contains($acctAppHtml, 'href="/inventory/stock"'));
        $assert('RBAC-ACCT-10', 'Accountant sidebar strictly hides Items & Catalog tab', !str_contains($acctAppHtml, 'href="/products"'));
        $assert('RBAC-ACCT-11', 'Accountant sidebar strictly hides Categories tab', !str_contains($acctAppHtml, 'href="/categories"'));
        $assert('RBAC-ACCT-12', 'Accountant sidebar strictly hides Delivery Challans tab', !str_contains($acctAppHtml, 'href="/sales/challans"'));
        $assert('RBAC-ACCT-13', 'Accountant sidebar strictly hides Purchase Bills tab', !str_contains($acctAppHtml, 'href="/purchases/bills"'));
        $assert('RBAC-ACCT-14', 'Accountant sidebar strictly hides Chart of Accounts tab', !str_contains($acctAppHtml, 'href="/accounts"'));
        $assert('RBAC-ACCT-15', 'Accountant sidebar strictly hides Reports (Balance Sheet etc.)', !str_contains($acctAppHtml, 'href="/reports/balance-sheet"'));
        $assert('RBAC-ACCT-16', 'Accountant sidebar strictly hides Users tab', !str_contains($acctAppHtml, 'href="/users"'));
        $assert('RBAC-ACCT-17', 'Accountant sidebar strictly hides Roles & Permissions tab', !str_contains($acctAppHtml, 'href="/roles"'));
        $assert('RBAC-ACCT-18', 'Accountant sidebar strictly hides Audit Log tab', !str_contains($acctAppHtml, 'href="/settings/audit-logs"'));
        $assert('RBAC-ACCT-19', 'Accountant sidebar strictly hides System Settings tab', !str_contains($acctAppHtml, 'href="/settings"'));

        // 1c. Server-Side 403 Enforcement: Direct Typing of Forbidden URLs returns 403
        $forbiddenForAccountant = [
            '/customers',
            '/customers/create',
            '/suppliers',
            '/products',
            '/inventory/stock',
            '/categories',
            '/sales/challans',
            '/purchases/bills',
            '/accounts',
            '/reports/profit-loss',
            '/reports/balance-sheet',
            '/reports/gst',
            '/users',
            '/roles',
            '/settings',
            '/settings/audit-logs',
        ];

        $acctAllBlocked = true;
        foreach ($forbiddenForAccountant as $url) {
            $req = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $url,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $res = $router->dispatch($req);
            if ($res->getStatusCode() !== 403) {
                $acctAllBlocked = false;
                break;
            }
        }
        $assert('RBAC-ACCT-20', 'Direct URL visits to all forbidden pages return HTTP 403 Forbidden for Accountant', $acctAllBlocked);

        // 1d. Accountant Dashboard Specialization
        $dashReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
        $dashRes = $router->dispatch($dashReq);
        $dashBody = $dashRes->getBody();
        $assert('RBAC-ACCT-21', 'Accountant /dashboard renders Payments & Financial Operations view',
            str_contains($dashBody, 'Payments & Financial Operations') && str_contains($dashBody, 'Accounts Receivable'));
        $assert('RBAC-ACCT-22', 'Accountant /dashboard contains ZERO inventory or warehouse widgets',
            !str_contains($dashBody, 'Warehouse & Inventory Operations') && !str_contains($dashBody, 'Critical Stock Attention Needed'));

        // 1e. Accountant Action Permissions (No delete on invoices)
        $assert('RBAC-ACCT-23', 'Accountant has invoices.view, invoices.create, invoices.edit, invoices.print',
            Gate::can('invoices.view') && Gate::can('invoices.create') && Gate::can('invoices.edit') && Gate::can('invoices.print'));
        $assert('RBAC-ACCT-24', 'Accountant has invoices.delete permission', Gate::can('invoices.delete'));
        $assert('RBAC-ACCT-25', 'Accountant has payments.view, payments.create, payments.print, payments.delete',
            Gate::can('payments.view') && Gate::can('payments.create') && Gate::can('payments.print') && Gate::can('payments.delete'));

        // Verify Delete button renders on Payments & Receipts for Accountant
        $paymentsReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/payments']);
        $paymentsRes = $router->dispatch($paymentsReq);
        $paymentsHtml = $paymentsRes->getBody();
        $assert('RBAC-ACCT-28', 'Payments & Receipts table renders Delete button for Accountant',
            str_contains($paymentsHtml, 'action="/payments/') && str_contains($paymentsHtml, 'Delete'));

        // 1f. Accountant Invoice Action Button Rendering (Delete button visible)
        $invoicesReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/invoices']);
        $invoicesRes = $router->dispatch($invoicesReq);
        $invoicesHtml = $invoicesRes->getBody();
        $assert('RBAC-ACCT-26', 'Tax Invoices view renders Delete button for Accountant',
            str_contains($invoicesHtml, 'inv-modal-delete-btn') || str_contains($invoicesHtml, 'window.deleteInvoice'));


        // =========================================================================
        // PART 2: SESSION LOGOUT & CLEAN TRANSITION (ZERO STATE LEAKAGE)
        // =========================================================================
        $authService->logout();
        Gate::flushCache();

        $assert('RBAC-LEAK-01', 'Logout clears session and flushes Gate cache',
            Session::getUserId() === null && Gate::user() === null && Gate::role() === 'guest');


        // =========================================================================
        // PART 3: INVENTORY MANAGER ROLE ACCESS VERIFICATION
        // =========================================================================
        $invMgr = Database::selectOne("SELECT * FROM users WHERE email = 'suresh.p@wtsbill.in' AND deleted_at IS NULL");
        if (!$invMgr) {
            $invMgr = Database::selectOne("SELECT * FROM users WHERE role_id = 4 AND deleted_at IS NULL");
        }
        $authService->authenticate($invMgr['email'], 'password123');
        Gate::flushCache();

        $assert('RBAC-INVM-01', 'Inventory Manager logs in and session establishes role inventory_manager',
            Gate::role() === 'inventory_manager' && Gate::user()['id'] === (int)$invMgr['id']);

        // 3b. Render Layout & Sidebar as Inventory Manager
        $invAppHtml = View::renderWithLayout('dashboard/inventory', [
            'title' => 'Inventory Dashboard',
            'currentUri' => '/',
            'metrics' => ['total_units' => 500, 'total_valuation' => 125000, 'low_stock_count' => 2, 'out_of_stock_count' => 0, 'adjustments_this_month' => 4],
            'criticalItems' => [],
            'recentAdjustments' => [],
        ], 'layouts/app');

        // Verify Allowed Tabs in Sidebar
        $assert('RBAC-INVM-02', 'Inventory Manager sidebar renders Dashboard link', str_contains($invAppHtml, 'Dashboard'));
        $assert('RBAC-INVM-03', 'Inventory Manager sidebar renders Manage Inventory tab', str_contains($invAppHtml, 'href="/inventory/stock"'));
        $assert('RBAC-INVM-04', 'Inventory Manager sidebar renders Stock Management (In-Out) tab', str_contains($invAppHtml, 'href="/inventory/stock?action=adjust"') || str_contains($invAppHtml, 'Stock Management'));
        $assert('RBAC-INVM-05', 'Inventory Manager sidebar renders Stock Adjustments tab', str_contains($invAppHtml, 'href="/inventory/stock?tab=history"') || str_contains($invAppHtml, 'Stock Adjustments'));
        $assert('RBAC-INVM-06', 'Inventory Manager sidebar renders Items & Catalog tab', str_contains($invAppHtml, 'href="/products"'));
        $assert('RBAC-INVM-07', 'Inventory Manager sidebar renders Categories tab', str_contains($invAppHtml, 'href="/categories"'));

        // Verify Hidden Tabs in Sidebar
        $assert('RBAC-INVM-08', 'Inventory Manager sidebar strictly hides Tax Invoices tab', !str_contains($invAppHtml, 'href="/sales/invoices"'));
        $assert('RBAC-INVM-09', 'Inventory Manager sidebar strictly hides Quotations tab', !str_contains($invAppHtml, 'href="/sales/quotations"'));
        $assert('RBAC-INVM-10', 'Inventory Manager sidebar strictly hides Delivery Challans tab', !str_contains($invAppHtml, 'href="/sales/challans"'));
        $assert('RBAC-INVM-11', 'Inventory Manager sidebar strictly hides Purchase Bills tab', !str_contains($invAppHtml, 'href="/purchases/bills"'));
        $assert('RBAC-INVM-12', 'Inventory Manager sidebar strictly hides Expenses tab', !str_contains($invAppHtml, 'href="/expenses"'));
        $assert('RBAC-INVM-13', 'Inventory Manager sidebar strictly hides Payments & Receipts tab', !str_contains($invAppHtml, 'href="/payments"'));
        $assert('RBAC-INVM-14', 'Inventory Manager sidebar strictly hides Customers tab', !str_contains($invAppHtml, 'href="/customers"'));
        $assert('RBAC-INVM-15', 'Inventory Manager sidebar strictly hides Suppliers tab', !str_contains($invAppHtml, 'href="/suppliers"'));
        $assert('RBAC-INVM-16', 'Inventory Manager sidebar strictly hides Chart of Accounts tab', !str_contains($invAppHtml, 'href="/accounts"'));
        $assert('RBAC-INVM-17', 'Inventory Manager sidebar strictly hides Reports tab', !str_contains($invAppHtml, 'href="/reports/profit-loss"'));
        $assert('RBAC-INVM-18', 'Inventory Manager sidebar strictly hides Users tab', !str_contains($invAppHtml, 'href="/users"'));
        $assert('RBAC-INVM-19', 'Inventory Manager sidebar strictly hides Roles & Permissions tab', !str_contains($invAppHtml, 'href="/roles"'));
        $assert('RBAC-INVM-20', 'Inventory Manager sidebar strictly hides Audit Log tab', !str_contains($invAppHtml, 'href="/settings/audit-logs"'));
        $assert('RBAC-INVM-21', 'Inventory Manager sidebar strictly hides Settings tab', !str_contains($invAppHtml, 'href="/settings"'));

        // 3c. Server-Side 403 Enforcement: Direct Typing of Forbidden URLs returns 403
        $forbiddenForInvMgr = [
            '/sales/invoices',
            '/sales/invoices/create',
            '/sales/quotations',
            '/sales/challans',
            '/purchases/bills',
            '/expenses',
            '/payments',
            '/customers',
            '/suppliers',
            '/accounts',
            '/reports/profit-loss',
            '/reports/balance-sheet',
            '/reports/gst',
            '/users',
            '/roles',
            '/settings',
            '/settings/audit-logs',
        ];

        $invAllBlocked = true;
        foreach ($forbiddenForInvMgr as $url) {
            $req = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $url,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $res = $router->dispatch($req);
            if ($res->getStatusCode() !== 403) {
                $invAllBlocked = false;
                break;
            }
        }
        $assert('RBAC-INVM-22', 'Direct URL visits to all forbidden pages return HTTP 403 Forbidden for Inventory Manager', $invAllBlocked);

        // 3d. Inventory Dashboard Specialization
        $invDashReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
        $invDashRes = $router->dispatch($invDashReq);
        $invDashBody = $invDashRes->getBody();
        $assert('RBAC-INVM-23', 'Inventory Manager /dashboard renders Warehouse & Inventory view',
            str_contains($invDashBody, 'Warehouse & Inventory Operations') && str_contains($invDashBody, 'Total Stocked Units'));
        $assert('RBAC-INVM-24', 'Inventory Manager /dashboard contains ZERO sales, receivables, or P&L widgets',
            !str_contains($invDashBody, 'Payments & Financial Operations') && !str_contains($invDashBody, 'Total Sales') && !str_contains($invDashBody, 'Accounts Receivable'));

        // 3e. Inventory Manager Action Permissions (Can edit and delete stock adjustments as requested)
        $assert('RBAC-INVM-25', 'Inventory Manager has inventory.adjustment.edit and inventory.adjustment.delete permissions',
            Gate::can('inventory.adjustment.edit') && Gate::can('inventory.adjustment.delete'));
        $assert('RBAC-INVM-26', 'Inventory Manager has items.create, items.edit, items.delete and categories CRUD',
            Gate::can('items.create') && Gate::can('items.delete') && Gate::can('categories.create') && Gate::can('categories.delete'));
        $assert('RBAC-INVM-27', 'Inventory Manager is strictly DENIED financial payments.create and invoices.create',
            !Gate::can('payments.create') && !Gate::can('invoices.create') && !Gate::can('invoices.delete'));

        // Attempting to delete an invoice directly returns 403 with permission message, not database error
        $invDelReq = new Request([], ['_csrf_token' => Session::csrfToken()], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/164/delete',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $invDelRes = $router->dispatch($invDelReq);
        $invDelJson = json_decode($invDelRes->getBody(), true);
        $assert('RBAC-INVM-30', 'Inventory Manager invoice delete is rejected with 403 Forbidden and accurate permission error',
            $invDelRes->getStatusCode() === 403 &&
            str_contains($invDelJson['message'] ?? '', 'invoices.delete') &&
            !str_contains($invDelJson['message'] ?? '', 'database error'));

        // 3f. Stock Adjustment Route Dispatching (No 404 Not Found)
        $adjDelReq = new Request([], ['_csrf_token' => Session::csrfToken()], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/stock/adjustments/161/delete',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $adjDelRes = $router->dispatch($adjDelReq);
        $assert('RBAC-INVM-28', 'POST /inventory/stock/adjustments/{id}/delete route is registered and does not 404',
            $adjDelRes->getStatusCode() !== 404);

        $adjUpdReq = new Request([], ['_csrf_token' => Session::csrfToken(), 'reason' => 'Test Reason'], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/stock/adjustments/161/update',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $adjUpdRes = $router->dispatch($adjUpdReq);
        $assert('RBAC-INVM-29', 'POST /inventory/stock/adjustments/{id}/update route is registered and does not 404',
            $adjUpdRes->getStatusCode() !== 404);


        // =========================================================================
        // PART 4: SESSION LOGOUT & CLEAN TRANSITION
        // =========================================================================
        $authService->logout();
        Gate::flushCache();

        $assert('RBAC-LEAK-02', 'Logout clears Inventory Manager session with zero leftover permissions',
            Gate::role() === 'guest' && !Gate::can('inventory.view'));


        // =========================================================================
        // PART 5: ADMINISTRATOR FULL ACCESS VERIFICATION
        // =========================================================================
        $admin = Database::selectOne("SELECT * FROM users WHERE email = 'anil.d@wtsbill.in' AND deleted_at IS NULL");
        $authService->authenticate($admin['email'], 'password123');
        Gate::flushCache();

        $assert('RBAC-ADM-01', 'Administrator logs in and session establishes role admin',
            Gate::role() === 'admin' && Gate::user()['id'] === (int)$admin['id']);

        // 5b. Administrator Sees Everything in Sidebar
        $adminAppHtml = View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
            'metrics' => ['total_sales' => 150000, 'total_tax' => 27000, 'receivables' => 30000, 'payables' => 15000],
            'recentInvoices' => [],
        ], 'layouts/app');

        $assert('RBAC-ADM-02', 'Administrator sidebar renders Users & Permissions tab', str_contains($adminAppHtml, 'href="/users"'));
        $assert('RBAC-ADM-03', 'Administrator sidebar renders Roles & Permissions tab', str_contains($adminAppHtml, 'href="/roles"'));
        $assert('RBAC-ADM-04', 'Administrator sidebar renders Audit Log tab', str_contains($adminAppHtml, 'href="/settings/audit-logs"'));
        $assert('RBAC-ADM-05', 'Administrator sidebar renders System Settings tab', str_contains($adminAppHtml, 'href="/settings"'));

        // 5c. Administrator Has Unrestricted Access to All Pages
        $adminCoreUrls = ['/customers', '/products', '/sales/invoices', '/purchases/bills', '/expenses', '/payments', '/inventory/stock', '/accounts', '/reports/balance-sheet', '/users', '/roles', '/settings'];
        $adminAllAllowed = true;
        foreach ($adminCoreUrls as $url) {
            $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $url]);
            $res = $router->dispatch($req);
            if ($res->getStatusCode() !== 200) {
                $adminAllAllowed = false;
                break;
            }
        }
        $assert('RBAC-ADM-06', 'Administrator accesses all ERP modules with HTTP 200 OK', $adminAllAllowed);


        // =========================================================================
        // PART 6: SECURITY AUDIT TRAIL LOGGING VERIFICATION
        // =========================================================================
        // Verify that denied attempt was logged into audit_logs
        $deniedLog = Database::selectOne(
            "SELECT id, action, entity_label, status, user_role, notes 
             FROM audit_logs 
             WHERE UPPER(action) = 'PERMISSION_DENIED' 
             ORDER BY id DESC LIMIT 1"
        );
        $assert('RBAC-AUDIT-01', 'Security audit log recorded denied access attempts with action permission_denied',
            $deniedLog !== null && strcasecmp($deniedLog['action'], 'permission_denied') === 0 && strcasecmp($deniedLog['status'], 'denied') === 0);

        // Clean up session
        $authService->logout();
        Gate::flushCache();
    }
}
