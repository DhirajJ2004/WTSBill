<?php

declare(strict_types=1);

use WTSBill\Controllers\AccountingController;
use WTSBill\Controllers\AuthController;
use WTSBill\Controllers\BranchController;
use WTSBill\Controllers\CustomerController;
use WTSBill\Controllers\DashboardController;
use WTSBill\Controllers\ExpenseController;
use WTSBill\Controllers\InventoryController;
use WTSBill\Controllers\PaymentController;
use WTSBill\Controllers\ProductController;
use WTSBill\Controllers\PurchaseController;
use WTSBill\Controllers\ReportController;
use WTSBill\Controllers\RoleController;
use WTSBill\Controllers\SalesController;
use WTSBill\Controllers\SettingsController;
use WTSBill\Controllers\SupplierController;
use WTSBill\Controllers\UserController;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Middleware\AuthenticationMiddleware;
use WTSBill\Middleware\AuthorizationMiddleware;
use WTSBill\Middleware\CsrfMiddleware;

/**
 * WTSBill ERP — Core Web Routes
 * Strictly enforces Authentication, CSRF, and Gate Authorization Middleware per role.
 *
 * @var Router $router
 */

$csrf = new CsrfMiddleware();
$auth = new AuthenticationMiddleware();
$can = fn(string|array $perm) => new AuthorizationMiddleware($perm);

// 1. Root Welcome / Role-Specific Landing Dashboard
$dashboardController = new DashboardController();
$router->get('/', [$dashboardController, 'index']);
$router->get('/dashboard', [$dashboardController, 'index'], [$auth, $can(['dashboard.view', 'dashboard.payments.view', 'dashboard.inventory.view'])]);

// 2. Authentication & Guest Routes
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

// 4. Customers & CRM (Admin / Sales)
$customerController = new CustomerController();
$router->get('/customers', [$customerController, 'index'], [$auth, $can('customers.view')]);
$router->get('/customers/create', [$customerController, 'createForm'], [$auth, $can('customers.create')]);
$router->post('/customers/create', [$customerController, 'store'], [$auth, $csrf, $can('customers.create')]);
$router->post('/customers', [$customerController, 'store'], [$auth, $csrf, $can('customers.create')]);
$router->get('/customers/{id}', [$customerController, 'show'], [$auth, $can('customers.view')]);
$router->post('/customers/{id}/update', [$customerController, 'update'], [$auth, $csrf, $can('customers.edit')]);
$router->post('/customers/{id}/delete', [$customerController, 'delete'], [$auth, $csrf, $can('customers.delete')]);
$router->delete('/customers/{id}', [$customerController, 'delete'], [$auth, $csrf, $can('customers.delete')]);

// 5. Suppliers Master (Admin / Purchases)
$supplierController = new SupplierController();
$router->get('/suppliers', [$supplierController, 'index'], [$auth, $can('suppliers.view')]);
$router->post('/suppliers', [$supplierController, 'store'], [$auth, $csrf, $can('suppliers.create')]);
$router->get('/suppliers/{id}', [$supplierController, 'show'], [$auth, $can('suppliers.view')]);
$router->post('/suppliers/{id}/update', [$supplierController, 'update'], [$auth, $csrf, $can('suppliers.edit')]);

// 6. Product Catalog & Inventory (Inventory Manager / Admin)
$productController = new ProductController();
$router->get('/products', [$productController, 'index'], [$auth, $can('items.view')]);
$router->post('/products', [$productController, 'store'], [$auth, $csrf, $can('items.create')]);
$router->get('/products/{id}', [$productController, 'show'], [$auth, $can('items.view')]);
$router->post('/products/{id}/update', [$productController, 'update'], [$auth, $csrf, $can('items.edit')]);
$router->post('/products/{id}/delete', [$productController, 'delete'], [$auth, $csrf, $can('items.delete')]);

// 7. Tax Invoices (Accountant / Admin / Sales)
$salesController = new SalesController();
$router->get('/sales/invoices', [$salesController, 'invoicesIndex'], [$auth, $can('invoices.view')]);
$router->get('/sales/invoices/create', [$salesController, 'invoiceCreateForm'], [$auth, $can('invoices.create')]);
$router->post('/sales/invoices/create', [$salesController, 'invoiceStore'], [$auth, $csrf, $can('invoices.create')]);
$router->get('/sales/invoices/{id}', [$salesController, 'invoiceShow'], [$auth, $can('invoices.view')]);
$router->get('/sales/invoices/{id}/edit', [$salesController, 'invoiceEditForm'], [$auth, $can('invoices.edit')]);
$router->post('/sales/invoices/{id}/update', [$salesController, 'invoiceUpdate'], [$auth, $csrf, $can('invoices.edit')]);
$router->post('/sales/invoices/{id}/delete', [$salesController, 'invoiceDelete'], [$auth, $csrf, $can('invoices.delete')]);
$router->get('/sales/invoices/{id}/delete', [$salesController, 'invoiceDelete'], [$auth, $can('invoices.delete')]);
$router->delete('/sales/invoices/{id}', [$salesController, 'invoiceDelete'], [$auth, $csrf, $can('invoices.delete')]);
$router->get('/sales/invoices/{id}/print', [$salesController, 'invoicePrint'], [$auth, $can('invoices.print')]);

// 8. Sales Quotations & Orders (Accountant / Admin / Sales)
$router->get('/sales/quotations', [$salesController, 'quotationsIndex'], [$auth, $can('quotations.view')]);
$router->post('/sales/quotations', [$salesController, 'quotationStore'], [$auth, $csrf, $can('quotations.create')]);
$router->get('/sales/quotations/{id}/print', [$salesController, 'quotationPrint'], [$auth, $can('quotations.print')]);
$router->get('/sales/quotations/{id}', [$salesController, 'quotationPrint'], [$auth, $can(['quotations.print', 'quotations.view'])]);

// 9. Delivery Challans (Admin / Sales)
$router->get('/sales/challans', [$salesController, 'challansIndex'], [$auth, $can('challans.view')]);
$router->post('/sales/challans', [$salesController, 'challanStore'], [$auth, $csrf, $can('challans.create')]);

// 10. Purchases & Accounts Payable (Admin / Purchase Manager)
$purchaseController = new PurchaseController();
$router->get('/purchases/bills', [$purchaseController, 'billsIndex'], [$auth, $can('bills.view')]);
$router->post('/purchases/bills', [$purchaseController, 'billStore'], [$auth, $csrf, $can('bills.create')]);
$router->get('/purchases/bills/export', [$purchaseController, 'billExport'], [$auth, $can('bills.export')]);
$router->get('/api/purchases/bills', [$purchaseController, 'billsApi'], [$auth, $can('bills.view')]);
$router->get('/api/purchases/bills/{id}', [$purchaseController, 'billDetailsApi'], [$auth, $can('bills.view')]);
$router->post('/api/purchases/bills/{id}/payments', [$purchaseController, 'recordPaymentApi'], [$auth, $csrf, $can('bills.edit')]);
$router->post('/api/purchases/bills/{id}/duplicate', [$purchaseController, 'billDuplicateApi'], [$auth, $csrf, $can('bills.create')]);
$router->delete('/api/purchases/bills/{id}', [$purchaseController, 'billDeleteApi'], [$auth, $csrf, $can('bills.delete')]);
$router->post('/api/purchases/bills/{id}/delete', [$purchaseController, 'billDeleteApi'], [$auth, $csrf, $can('bills.delete')]);

// 11. Expenses (Accountant / Admin)
$expenseController = new ExpenseController();
$router->get('/expenses', [$expenseController, 'index'], [$auth, $can('expenses.view')]);
$router->post('/expenses', [$expenseController, 'store'], [$auth, $csrf, $can('expenses.create')]);
$router->post('/expenses/{id}/update', [$expenseController, 'update'], [$auth, $csrf, $can('expenses.edit')]);
$router->post('/expenses/{id}/delete', [$expenseController, 'delete'], [$auth, $csrf, $can('expenses.delete')]);

// 12. Payments & Receipts (Accountant / Admin)
$paymentController = new PaymentController();
$router->get('/payments', [$paymentController, 'index'], [$auth, $can('payments.view')]);
$router->post('/payments', [$paymentController, 'store'], [$auth, $csrf, $can('payments.create')]);
$router->get('/payments/{id}/print', [$paymentController, 'printReceipt'], [$auth, $can('payments.print')]);
$router->post('/payments/{id}/delete', [$paymentController, 'delete'], [$auth, $csrf, $can('payments.delete')]);
$router->get('/payments/{id}/delete', [$paymentController, 'delete'], [$auth, $can('payments.delete')]);
$router->delete('/payments/{id}', [$paymentController, 'delete'], [$auth, $csrf, $can('payments.delete')]);

// 13. Inventory Controller (Stock & Categories - Inventory Manager / Admin)
$inventoryController = new InventoryController();
$router->get('/inventory/stock', [$inventoryController, 'stockIndex'], [$auth, $can('inventory.view')]);
$router->get('/inventory/adjustments', fn() => \WTSBill\Core\Response::redirect('/inventory/stock?tab=history'), [$auth, $can('inventory.adjustment.view')]);
$router->get('/inventory/manage', fn() => \WTSBill\Core\Response::redirect('/inventory/stock?action=adjust'), [$auth, $can('inventory.stock.add')]);
$router->post('/inventory/stock/adjust', [$inventoryController, 'stockAdjust'], [$auth, $csrf, $can('inventory.adjustment.create')]);
$router->post('/inventory/stock/adjustments/{id}/update', [$inventoryController, 'adjustmentUpdate'], [$auth, $csrf, $can('inventory.adjustment.edit')]);
$router->post('/inventory/stock/adjustments/update', [$inventoryController, 'adjustmentUpdate'], [$auth, $csrf, $can('inventory.adjustment.edit')]);
$router->post('/inventory/stock/adjustments/{id}/delete', [$inventoryController, 'adjustmentDelete'], [$auth, $csrf, $can('inventory.adjustment.delete')]);
$router->get('/inventory/stock/adjustments/{id}/delete', [$inventoryController, 'adjustmentDelete'], [$auth, $can('inventory.adjustment.delete')]);
$router->delete('/inventory/stock/adjustments/{id}', [$inventoryController, 'adjustmentDelete'], [$auth, $csrf, $can('inventory.adjustment.delete')]);
$router->post('/inventory/stock/adjustments/delete', [$inventoryController, 'adjustmentDelete'], [$auth, $csrf, $can('inventory.adjustment.delete')]);
$router->get('/inventory/stock/export', [$inventoryController, 'stockExport'], [$auth, $can('inventory.export')]);
$router->get('/api/inventory/stock', [$inventoryController, 'stockApi'], [$auth, $can('inventory.view')]);
$router->post('/api/inventory/adjustments', [$inventoryController, 'stockAdjustmentApi'], [$auth, $csrf, $can('inventory.adjustment.create')]);

// Product Categories (Inventory Manager / Admin)
$router->get('/categories', [$inventoryController, 'categoriesIndex'], [$auth, $can('categories.view')]);
$router->post('/categories', [$inventoryController, 'categoryStore'], [$auth, $csrf, $can('categories.create')]);

// 14. Double-Entry Accounting (Admin / Chief Accountant)
$accountingController = new AccountingController();
$router->get('/accounts', [$accountingController, 'accountsIndex'], [$auth, $can('accounting.view')]);
$router->post('/accounts', [$accountingController, 'accountStore'], [$auth, $csrf, $can('accounting.create')]);
$router->get('/accounts/journals', [$accountingController, 'journalsIndex'], [$auth, $can('accounting.create')]);
$router->post('/accounts/journals', [$accountingController, 'journalStore'], [$auth, $csrf, $can('accounting.create')]);
$router->get('/accounts/ledger', function (Request $req) {
    $html = \WTSBill\Core\View::renderWithLayout('accounting/ledger', [
        'title' => 'General Ledger',
        'currentUri' => '/accounts/ledger',
    ], 'layouts/app');
    return Response::html($html);
}, [$auth, $can('accounting.view')]);

// 15. Financial & Compliance Reports (Admin Only)
$reportController = new ReportController();
$router->get('/reports/profit-loss', function (Request $req) {
    $html = \WTSBill\Core\View::renderWithLayout('reports/profit-loss', [
        'title' => 'Profit & Loss',
        'currentUri' => '/reports/profit-loss',
    ], 'layouts/app');
    return Response::html($html);
}, [$auth, $can('reports.view')]);

$router->get('/reports/balance-sheet', [$reportController, 'balanceSheet'], [$auth, $can('reports.view')]);
$router->get('/api/reports/balance-sheet', [$reportController, 'balanceSheetApi'], [$auth, $can('reports.view')]);
$router->get('/reports/balance-sheet/export', [$reportController, 'balanceSheetExport'], [$auth, $can('reports.export')]);
$router->get('/reports/gst', [$reportController, 'gstIndex'], [$auth, $can('reports.view')]);
$router->get('/reports/gst/export', [$reportController, 'gstExport'], [$auth, $can('reports.export')]);

// 16. User & Permissions Management (Administrator Only)
$userController = new UserController();
$router->get('/users', [$userController, 'index'], [$auth, $can('users.manage')]);
$router->post('/users', [$userController, 'store'], [$auth, $csrf, $can('users.manage')]);
$router->post('/users/update', [$userController, 'update'], [$auth, $csrf, $can('users.manage')]);
$router->post('/users/toggle-status', [$userController, 'toggleStatus'], [$auth, $csrf, $can('users.manage')]);
$router->post('/users/reset-password', [$userController, 'resetPassword'], [$auth, $csrf, $can('users.manage')]);

// 17. Roles & Permissions Matrix Management (Administrator Only)
$roleController = new RoleController();
$router->get('/roles', [$roleController, 'index'], [$auth, $can('roles.manage')]);
$router->post('/roles', [$roleController, 'store'], [$auth, $csrf, $can('roles.manage')]);
$router->post('/roles/{id}/update', [$roleController, 'update'], [$auth, $csrf, $can('roles.manage')]);
$router->post('/roles/{id}/duplicate', [$roleController, 'duplicate'], [$auth, $csrf, $can('roles.manage')]);
$router->post('/roles/{id}/delete', [$roleController, 'delete'], [$auth, $csrf, $can('roles.manage')]);

// 18. System Settings & Security Audit Trail (Administrator Only)
$settingsController = new SettingsController();
$router->get('/settings', [$settingsController, 'index'], [$auth, $can('settings.view')]);
$router->get('/api/settings/company', [$settingsController, 'getCompany'], [$auth, $can('settings.view')]);
$router->post('/settings/company/update', [$settingsController, 'updateCompany'], [$auth, $csrf, $can('settings.edit')]);
$router->get('/settings/audit-logs', [$settingsController, 'auditLogs'], [$auth, $can('audit.view')]);
$router->get('/api/settings/audit-logs', [$settingsController, 'auditLogs'], [$auth, $can('audit.view')]);
$router->get('/settings/audit-logs/export', [$settingsController, 'exportAuditLogs'], [$auth, $can('audit.export')]);

// 19. Branch Management & Switching
$branchController = new BranchController();
$router->post('/branches/switch', [$branchController, 'switch'], [$auth, $csrf]);
$router->get('/branches/switch', [$branchController, 'switch'], [$auth]);
$router->get('/branches', [$branchController, 'index'], [$auth, $can('settings.view')]);
$router->post('/branches', [$branchController, 'store'], [$auth, $csrf, $can('settings.edit')]);
$router->get('/branches/list', [$branchController, 'list'], [$auth]);
$router->get('/api/branches', [$branchController, 'list'], [$auth]);
$router->get('/branches/{id}', [$branchController, 'show'], [$auth, $can('settings.view')]);
$router->post('/branches/{id}/update', [$branchController, 'update'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/branches/{id}/toggle-status', [$branchController, 'toggleStatus'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/branches/{id}/assign-users', [$branchController, 'assignUsers'], [$auth, $csrf, $can('users.manage')]);
