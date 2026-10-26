<?php

declare(strict_types=1);

use WTSBill\Controllers\AuthController;
use WTSBill\Core\Config;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Middleware\CsrfMiddleware;

/**
 * WTSBill ERP — Core Web Routes
 *
 * @var Router $router
 */

$csrf = new CsrfMiddleware();

// 1. Root welcome / status dashboard
$router->get('/', function (Request $req) {
    $authService = new \WTSBill\Services\AuthService();
    if ($authService->check()) {
        $companyId = (int)(\WTSBill\Core\Session::getCompanyId() ?? 1);
        $branchId = (int)(\WTSBill\Core\Session::getBranchId() ?? 1);

        $salesMetrics = \WTSBill\Core\Database::selectOne(
            "SELECT COUNT(*) AS total_invoices, 
                    COALESCE(SUM(grand_total), 0) AS total_sales,
                    COALESCE(SUM(total_tax), 0) AS total_tax,
                    COALESCE(SUM(amount_due), 0) AS receivables
             FROM `invoices` 
             WHERE company_id = ? AND branch_id = ?",
            [$companyId, $branchId]
        );

        $purchaseMetrics = \WTSBill\Core\Database::selectOne(
            "SELECT COALESCE(SUM(amount_due), 0) AS payables
             FROM `purchases` 
             WHERE company_id = ? AND branch_id = ?",
            [$companyId, $branchId]
        );

        $recentInvoices = \WTSBill\Core\Database::select(
            "SELECT id, invoice_number, customer_name, invoice_date, grand_total, status 
             FROM `invoices` 
             WHERE company_id = ? AND branch_id = ?
             ORDER BY id DESC LIMIT 5",
            [$companyId, $branchId]
        );

        $html = \WTSBill\Core\View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'total_sales' => (float)($salesMetrics['total_sales'] ?? 0),
                'total_tax' => (float)($salesMetrics['total_tax'] ?? 0),
                'receivables' => (float)($salesMetrics['receivables'] ?? 0),
                'payables' => (float)($purchaseMetrics['payables'] ?? 0),
            ],
            'recentInvoices' => $recentInvoices,
        ]);
        return Response::html($html);
    }

    return Response::html(
        '<!DOCTYPE html><html><head><title>' . e(Config::get('app.name', 'WTSBill ERP')) . '</title>' .
        '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
        '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);text-align:center;max-width:480px;border:1px solid #334155;}' .
        'h1{color:#38bdf8;margin-bottom:0.5rem;font-size:1.75rem;}p{color:#94a3b8;line-height:1.6;font-size:0.95rem;}' .
        '.badge{display:inline-block;padding:0.25rem 0.75rem;background:#0369a1;color:#e0f2fe;border-radius:9999px;font-size:0.75rem;font-weight:600;margin-bottom:1rem;}' .
        'a{color:#38bdf8;text-decoration:none;font-weight:500;margin:0 0.5rem;}</style></head><body>' .
        '<div class="card"><span class="badge">NATIVE PHP 8+ ENGINE</span>' .
        '<h1>' . e(Config::get('app.name', 'WTSBill ERP')) . '</h1>' .
        '<p>High-performance Enterprise Billing, GST Accounting & Multi-Tenant Inventory Rebuild.</p>' .
        '<p style="margin-top:1.25rem;"><a href="/login">Login Portal &rarr;</a> | <a href="/api/health">Check /api/health &rarr;</a></p>' .
        '</div></body></html>'
    );
});

// 2. Authentication Routes
$authController = new AuthController();

$router->get('/login', [$authController, 'loginForm']);
$router->get('/login.php', fn() => Response::redirect('/login'));
$router->post('/login', [$authController, 'login'], [$csrf]);
$router->post('/logout', [$authController, 'logout'], [$csrf]);

// 3. Password Reset Routes
$router->get('/forgot-password', [$authController, 'forgotPasswordForm']);
$router->post('/forgot-password', [$authController, 'sendResetLink'], [$csrf]);
$router->get('/reset-password', [$authController, 'resetPasswordForm']);
$router->post('/reset-password', [$authController, 'resetPassword'], [$csrf]);

// 4. Authenticated Application Routes
$auth = new \WTSBill\Middleware\AuthenticationMiddleware();

// Dashboard Route Alias
$router->get('/dashboard', function (Request $req) {
    return Response::redirect('/');
}, [$auth]);

// Customers Master
$customerController = new \WTSBill\Controllers\CustomerController();
$router->get('/customers', [$customerController, 'index'], [$auth]);
$router->get('/customers/create', [$customerController, 'createForm'], [$auth]);
$router->post('/customers/create', [$customerController, 'store'], [$auth, $csrf]);
$router->post('/customers', [$customerController, 'store'], [$auth, $csrf]);
$router->get('/customers/{id}', [$customerController, 'show'], [$auth]);
$router->post('/customers/{id}/update', [$customerController, 'update'], [$auth, $csrf]);

// Suppliers Master
$supplierController = new \WTSBill\Controllers\SupplierController();
$router->get('/suppliers', [$supplierController, 'index'], [$auth]);
$router->post('/suppliers', [$supplierController, 'store'], [$auth, $csrf]);
$router->get('/suppliers/{id}', [$supplierController, 'show'], [$auth]);
$router->post('/suppliers/{id}/update', [$supplierController, 'update'], [$auth, $csrf]);


// Product Catalog & Inventory
$productController = new \WTSBill\Controllers\ProductController();
$router->get('/products', [$productController, 'index'], [$auth]);
$router->post('/products', [$productController, 'store'], [$auth, $csrf]);
$router->get('/products/{id}', [$productController, 'show'], [$auth]);
$router->post('/products/{id}/update', [$productController, 'update'], [$auth, $csrf]);
$router->post('/products/{id}/delete', [$productController, 'delete'], [$auth, $csrf]);

// Tax Invoices
$salesController = new \WTSBill\Controllers\SalesController();
$router->get('/sales/invoices', [$salesController, 'invoicesIndex'], [$auth]);
$router->get('/sales/invoices/create', [$salesController, 'invoiceCreateForm'], [$auth]);
$router->post('/sales/invoices/create', [$salesController, 'invoiceStore'], [$auth, $csrf]);
$router->post('/sales/invoices', [$salesController, 'invoiceStore'], [$auth, $csrf]);
$router->get('/sales/invoices/{id}', [$salesController, 'invoiceShow'], [$auth]);


// Users & Roles
$userController = new \WTSBill\Controllers\UserController();
$router->get('/users', [$userController, 'index'], [$auth]);
$router->post('/users', [$userController, 'store'], [$auth, $csrf]);


// System Settings
$router->get('/settings', function (Request $req) {
    $html = \WTSBill\Core\View::renderWithLayout('settings/index', [
        'title' => 'System Settings',
        'currentUri' => '/settings',
    ], 'layouts/app');

    return Response::html($html);
}, [$auth]);

// Inventory Controller (Stock & Categories)
$inventoryController = new \WTSBill\Controllers\InventoryController();

// Stock Adjustments & Warehouse Balances
$router->get('/inventory/stock', [$inventoryController, 'stockIndex'], [$auth]);
$router->post('/inventory/stock/adjust', [$inventoryController, 'stockAdjust'], [$auth, $csrf]);
$router->get('/inventory/stock/export', [$inventoryController, 'stockExport'], [$auth]);
$router->get('/api/inventory/stock', [$inventoryController, 'stockApi'], [$auth]);
$router->post('/api/inventory/adjustments', [$inventoryController, 'stockAdjustmentApi'], [$auth, $csrf]);

// Product Categories
$router->get('/categories', [$inventoryController, 'categoriesIndex'], [$auth]);
$router->post('/categories', [$inventoryController, 'categoryStore'], [$auth, $csrf]);


// Sales Quotations & Orders
$router->get('/sales/quotations', [$salesController, 'quotationsIndex'], [$auth]);
$router->post('/sales/quotations', [$salesController, 'quotationStore'], [$auth, $csrf]);

// Delivery Challans
$router->get('/sales/challans', [$salesController, 'challansIndex'], [$auth]);
$router->post('/sales/challans', [$salesController, 'challanStore'], [$auth, $csrf]);


// Purchases & Accounts Payable
$purchaseController = new \WTSBill\Controllers\PurchaseController();
$router->get('/purchases/bills', [$purchaseController, 'billsIndex'], [$auth]);
$router->post('/purchases/bills', [$purchaseController, 'billStore'], [$auth, $csrf]);
$router->get('/purchases/bills/export', [$purchaseController, 'billExport'], [$auth]);
$router->get('/api/purchases/bills', [$purchaseController, 'billsApi'], [$auth]);
$router->get('/api/purchases/bills/{id}', [$purchaseController, 'billDetailsApi'], [$auth]);
$router->post('/api/purchases/bills/{id}/payments', [$purchaseController, 'recordPaymentApi'], [$auth, $csrf]);
$router->post('/api/purchases/bills/{id}/duplicate', [$purchaseController, 'billDuplicateApi'], [$auth, $csrf]);
$router->delete('/api/purchases/bills/{id}', [$purchaseController, 'billDeleteApi'], [$auth, $csrf]);
$router->post('/api/purchases/bills/{id}/delete', [$purchaseController, 'billDeleteApi'], [$auth, $csrf]);


// Expenses
$expenseController = new \WTSBill\Controllers\ExpenseController();
$router->get('/expenses', [$expenseController, 'index'], [$auth]);
$router->post('/expenses', [$expenseController, 'store'], [$auth, $csrf]);

// Chart of Accounts & Journals
$accountingController = new \WTSBill\Controllers\AccountingController();
$router->get('/accounts', [$accountingController, 'accountsIndex'], [$auth]);
$router->post('/accounts', [$accountingController, 'accountStore'], [$auth, $csrf]);
$router->get('/accounts/journals', [$accountingController, 'journalsIndex'], [$auth]);
$router->post('/accounts/journals', [$accountingController, 'journalStore'], [$auth, $csrf]);

// General Ledger
$router->get('/accounts/ledger', function (Request $req) {
    $html = \WTSBill\Core\View::renderWithLayout('accounting/ledger', [
        'title' => 'General Ledger',
        'currentUri' => '/accounts/ledger',
    ], 'layouts/app');

    return Response::html($html);
}, [$auth]);

// Profit & Loss Report
$router->get('/reports/profit-loss', function (Request $req) {
    $html = \WTSBill\Core\View::renderWithLayout('reports/profit-loss', [
        'title' => 'Profit & Loss',
        'currentUri' => '/reports/profit-loss',
    ], 'layouts/app');

    return Response::html($html);
}, [$auth]);

// Balance Sheet & GSTR-1 Reports, APIs & Exports
$reportController = new \WTSBill\Controllers\ReportController();
$router->get('/reports/balance-sheet', [$reportController, 'balanceSheet'], [$auth]);
$router->get('/api/reports/balance-sheet', [$reportController, 'balanceSheetApi'], [$auth]);
$router->get('/reports/balance-sheet/export', [$reportController, 'balanceSheetExport'], [$auth]);
$router->get('/reports/gst', [$reportController, 'gstIndex'], [$auth]);
$router->get('/reports/gst/export', [$reportController, 'gstExport'], [$auth]);

// Branch Management & Context Switching
$branchController = new \WTSBill\Controllers\BranchController();
$router->post('/branches/switch', [$branchController, 'switch'], [$auth, $csrf]);
$router->get('/branches/switch', [$branchController, 'switch'], [$auth]);
$router->get('/branches', [$branchController, 'index'], [$auth]);
$router->post('/branches', [$branchController, 'store'], [$auth, $csrf]);
$router->get('/branches/{id}', [$branchController, 'show'], [$auth]);
$router->post('/branches/{id}/update', [$branchController, 'update'], [$auth, $csrf]);
$router->post('/branches/{id}/toggle-status', [$branchController, 'toggleStatus'], [$auth, $csrf]);
$router->post('/branches/{id}/assign-users', [$branchController, 'assignUsers'], [$auth, $csrf]);




