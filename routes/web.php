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
use WTSBill\Controllers\QuotationController;
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
$router->get('/customers/{id}/profile', [$customerController, 'profile'], [$auth, $can('customers.view')]);
$router->get('/customers/{id}/statement', [$customerController, 'profile'], [$auth, $can('customers.view')]);
$router->get('/customers/{id}/statement/csv', [$customerController, 'statementCsv'], [$auth, $can('customers.view')]);
$router->post('/customers/{id}/update', [$customerController, 'update'], [$auth, $csrf, $can('customers.edit')]);
$router->post('/customers/{id}/delete', [$customerController, 'delete'], [$auth, $csrf, $can('customers.delete')]);
$router->delete('/customers/{id}', [$customerController, 'delete'], [$auth, $csrf, $can('customers.delete')]);

// 5. Suppliers Master (Admin / Purchases)
$supplierController = new SupplierController();
$router->get('/suppliers', [$supplierController, 'index'], [$auth, $can('suppliers.view')]);
$router->post('/suppliers', [$supplierController, 'store'], [$auth, $csrf, $can('suppliers.create')]);
$router->get('/suppliers/{id}', [$supplierController, 'show'], [$auth, $can('suppliers.view')]);
$router->post('/suppliers/{id}/update', [$supplierController, 'update'], [$auth, $csrf, $can('suppliers.edit')]);
$router->post('/suppliers/{id}/delete', [$supplierController, 'delete'], [$auth, $csrf, $can('suppliers.delete')]);
$router->get('/suppliers/{id}/delete', [$supplierController, 'delete'], [$auth, $can('suppliers.delete')]);
$router->delete('/suppliers/{id}', [$supplierController, 'delete'], [$auth, $csrf, $can('suppliers.delete')]);

// 6. Product Catalog & Inventory (Inventory Manager / Admin)
$productController = new ProductController();
$router->get('/products', [$productController, 'index'], [$auth, $can('items.view')]);
$router->post('/products', [$productController, 'store'], [$auth, $csrf, $can('items.create')]);
$router->get('/products/{id}', [$productController, 'show'], [$auth, $can('items.view')]);
$router->post('/products/{id}/update', [$productController, 'update'], [$auth, $csrf, $can('items.edit')]);
$router->post('/products/{id}/delete', [$productController, 'delete'], [$auth, $csrf, $can('items.delete')]);
$router->get('/products/{id}/delete', [$productController, 'delete'], [$auth, $can('items.delete')]);
$router->delete('/products/{id}', [$productController, 'delete'], [$auth, $csrf, $can('items.delete')]);

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
$router->get('/api/categories/{id}/products', [$salesController, 'categoryProductsApi'], [$auth, $can(['invoices.create', 'invoices.edit', 'invoices.view', 'items.view'])]);
$router->get('/api/categories/products', [$salesController, 'categoryProductsApi'], [$auth, $can(['invoices.create', 'invoices.edit', 'invoices.view', 'items.view'])]);
$router->post('/sales/invoices/{id}/payments', [$salesController, 'recordPayment'], [$auth, $csrf, $can('payments.create')]);
$router->post('/sales/invoices/{id}/verify-payment', [$salesController, 'verifyPayment'], [$auth, $csrf, $can('payments.create')]);
$router->get('/sales/invoices/{id}/payments', [$salesController, 'invoicePayments'], [$auth, $can('payments.view')]);

// 8. Sales Quotations & Orders (Accountant / Admin / Sales)
$quotationController = new QuotationController();
$router->get('/sales/quotations', [$quotationController, 'index'], [$auth, $can('quotations.view')]);
$router->get('/sales/quotations/create', [$quotationController, 'createForm'], [$auth, $can('quotations.create')]);
$router->post('/sales/quotations/create', [$quotationController, 'store'], [$auth, $csrf, $can('quotations.create')]);
$router->post('/sales/quotations', [$quotationController, 'store'], [$auth, $csrf, $can('quotations.create')]);
$router->get('/sales/quotations/{id}', [$quotationController, 'show'], [$auth, $can(['quotations.print', 'quotations.view'])]);
$router->get('/sales/quotations/{id}/edit', [$quotationController, 'editForm'], [$auth, $can('quotations.edit')]);
$router->post('/sales/quotations/{id}/update', [$quotationController, 'update'], [$auth, $csrf, $can('quotations.edit')]);
$router->post('/sales/quotations/{id}/convert', [$quotationController, 'convertToInvoice'], [$auth, $csrf, $can(['quotations.edit', 'invoices.create'])]);
$router->post('/sales/quotations/{id}/duplicate', [$quotationController, 'duplicate'], [$auth, $csrf, $can('quotations.create')]);
$router->post('/sales/quotations/{id}/status', [$quotationController, 'updateStatus'], [$auth, $csrf, $can('quotations.edit')]);
$router->post('/sales/quotations/{id}/delete', [$quotationController, 'delete'], [$auth, $csrf, $can('quotations.delete')]);
$router->get('/sales/quotations/{id}/delete', [$quotationController, 'delete'], [$auth, $can('quotations.delete')]);
$router->delete('/sales/quotations/{id}', [$quotationController, 'delete'], [$auth, $csrf, $can('quotations.delete')]);
$router->get('/sales/quotations/{id}/print', [$quotationController, 'print'], [$auth, $can('quotations.print')]);

// 8b. Document Sharing & Multi-Channel Communication
$commController = new \WTSBill\Controllers\CommunicationController();
$router->post('/sales/invoices/{id}/share', [$commController, 'shareInvoice'], [$auth, $csrf, $can('invoices.view')]);
$router->get('/sales/invoices/{id}/communication-history', [$commController, 'invoiceHistory'], [$auth, $can('invoices.view')]);
$router->get('/sales/invoices/{id}/pdf', [$commController, 'invoicePdf'], [$auth, $can('invoices.view')]);
$router->post('/sales/quotations/{id}/share', [$commController, 'shareQuotation'], [$auth, $csrf, $can('quotations.view')]);
$router->get('/sales/quotations/{id}/communication-history', [$commController, 'quotationHistory'], [$auth, $can('quotations.view')]);
$router->get('/sales/quotations/{id}/pdf', [$commController, 'quotationPdf'], [$auth, $can('quotations.view')]);
$router->post('/settings/communication', [$commController, 'saveSettings'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/api/settings/communication/test-email', [$commController, 'testEmail'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/api/settings/communication/test-whatsapp', [$commController, 'testWhatsApp'], [$auth, $csrf, $can('settings.edit')]);

// 8c. Public Document Access (Direct PDF & Web View via Verified HMAC Token - No Login Required)
$router->get('/doc/invoices/{id}/pdf', [$commController, 'publicInvoicePdf']);
$router->get('/doc/quotations/{id}/pdf', [$commController, 'publicQuotationPdf']);
$router->get('/doc/invoices/{id}', [$commController, 'publicInvoiceView']);
$router->get('/doc/quotations/{id}', [$commController, 'publicQuotationView']);
$router->get('/public/invoices/{id}/pdf', [$commController, 'publicInvoicePdf']);
$router->get('/public/quotations/{id}/pdf', [$commController, 'publicQuotationPdf']);
$router->get('/public/invoices/{id}', [$commController, 'publicInvoiceView']);
$router->get('/public/quotations/{id}', [$commController, 'publicQuotationView']);

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
$router->post('/payments/{id}/verify', [$paymentController, 'verifyPayment'], [$auth, $csrf, $can('payments.create')]);
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
$router->get('/categories/{id}', [$inventoryController, 'categoryShow'], [$auth, $can('categories.view')]);
$router->post('/categories/{id}/update', [$inventoryController, 'categoryUpdate'], [$auth, $csrf, $can('categories.edit')]);
$router->post('/categories/{id}/delete', [$inventoryController, 'categoryDelete'], [$auth, $csrf, $can('categories.delete')]);
$router->get('/categories/{id}/delete', [$inventoryController, 'categoryDelete'], [$auth, $can('categories.delete')]);
$router->delete('/categories/{id}', [$inventoryController, 'categoryDelete'], [$auth, $csrf, $can('categories.delete')]);
$router->post('/categories/{id}/toggle-status', [$inventoryController, 'categoryToggleStatus'], [$auth, $csrf, $can('categories.edit')]);

// 14. Double-Entry Accounting (Admin / Chief Accountant)
$accountingController = new AccountingController();
$router->get('/accounts', [$accountingController, 'accountsIndex'], [$auth, $can('accounting.view')]);
$router->post('/accounts', [$accountingController, 'accountStore'], [$auth, $csrf, $can('accounting.create')]);
$router->get('/accounts/journals', [$accountingController, 'journalsIndex'], [$auth, $can('accounting.create')]);
$router->post('/accounts/journals', [$accountingController, 'journalStore'], [$auth, $csrf, $can('accounting.create')]);
$router->get('/accounts/ledger', [$accountingController, 'ledgerIndex'], [$auth, $can('accounting.view')]);
$router->get('/accounting/ledger', [$accountingController, 'ledgerIndex'], [$auth, $can('accounting.view')]);
$router->get('/api/accounting/ledger', [$accountingController, 'ledgerApi'], [$auth, $can('accounting.view')]);
$router->get('/accounting/ledger/export', [$accountingController, 'ledgerExport'], [$auth, $can('accounting.view')]);
$router->get('/accounts/{id}', [$accountingController, 'accountShow'], [$auth, $can('accounting.view')]);
$router->post('/accounts/{id}/update', [$accountingController, 'accountUpdate'], [$auth, $csrf, $can('accounting.edit')]);
$router->post('/accounts/{id}/delete', [$accountingController, 'accountDelete'], [$auth, $csrf, $can('accounting.delete')]);
$router->get('/accounts/{id}/delete', [$accountingController, 'accountDelete'], [$auth, $can('accounting.delete')]);
$router->delete('/accounts/{id}', [$accountingController, 'accountDelete'], [$auth, $csrf, $can('accounting.delete')]);

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
$router->post('/api/user/theme', [$userController, 'updateTheme'], [$auth]);
$router->post('/user/theme', [$userController, 'updateTheme'], [$auth]);

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

// 18b. Enterprise Backup Management & Disaster Recovery
$backupController = new \WTSBill\Controllers\BackupController();
$router->post('/settings/backups/create', [$backupController, 'create'], [$auth, $csrf, $can('settings.edit')]);
$router->get('/settings/backups/{id}/download', [$backupController, 'download'], [$auth, $can('settings.edit')]);
$router->post('/settings/backups/{id}/restore', [$backupController, 'restore'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/settings/backups/restore', [$backupController, 'restore'], [$auth, $csrf, $can('settings.edit')]);
$router->post('/settings/audit-logs/cleanup', [$backupController, 'cleanupAudit'], [$auth, $csrf, $can('settings.edit')]);
$router->get('/api/settings/backups', [$backupController, 'listBackups'], [$auth, $can('settings.view')]);

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

// 20. Auto-Save In-Flight Form Drafts & Session Inactivity
$draftController = new \WTSBill\Controllers\DraftController();
$router->post('/api/drafts/auto-save', [$draftController, 'autoSave'], [$auth]);
$router->get('/api/drafts/check', [$draftController, 'check'], [$auth]);
$router->post('/api/drafts/discard', [$draftController, 'discard'], [$auth]);
$router->get('/api/session/status', [$draftController, 'sessionStatus'], [$auth]);
$router->post('/api/session/touch', [$draftController, 'touch'], [$auth]);
