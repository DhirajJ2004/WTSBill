<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\ChartOfAccountController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\SupplierGroupController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\GSTController;
use App\Http\Controllers\Api\BankingController;
use App\Http\Controllers\Api\ChequeController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\DeliveryChallanController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\RecurringInvoiceController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\DebitNoteController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\ReportEngineController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationSettingController;
use App\Http\Controllers\Api\PaymentLinkController;

function dispatch_route(string $uri, string $method)
{
    // Apply secure configured CORS headers (No wildcard '*')
    if (function_exists('apply_cors_headers')) {
        apply_cors_headers(true);
    }

    $path = parse_url($uri, PHP_URL_PATH);
    if (strpos($path, '/WTSBill') === 0) {
        $path = substr($path, strlen('/WTSBill'));
    }
    if (strpos($path, '/api/v1/') === 0 || $path === '/api/v1') {
        // Already v1
    } elseif (strpos($path, '/api/') === 0 || $path === '/api') {
        // Backward-compatible rewrite: /api/* -> /api/v1/*
        $path = '/api/v1' . substr($path, strlen('/api'));
    }
    $path = rtrim($path, '/');

    // 1. Auth Endpoints
    if ($path === '/api/v1/auth/login' && $method === 'POST') {
        return (new AuthController())->login();
    }
    if ($path === '/api/v1/auth/logout' && $method === 'POST') {
        return (new AuthController())->logout();
    }
    if ($path === '/api/v1/auth/register' && $method === 'POST') {
        return (new AuthController())->register();
    }
    if ($path === '/api/v1/auth/forgot-password' && $method === 'POST') {
        return (new AuthController())->forgotPassword();
    }
    if ($path === '/api/v1/auth/reset-password' && $method === 'POST') {
        return (new AuthController())->resetPassword();
    }
    if ($path === '/api/v1/auth/change-password' && $method === 'POST') {
        return (new AuthController())->changePassword();
    }
    if ($path === '/api/v1/auth/me' && $method === 'GET') {
        return (new AuthController())->me();
    }
    if ($path === '/api/v1/auth/company' && $method === 'GET') {
        return (new AuthController())->company();
    }
    if ($path === '/api/v1/auth/companies' && $method === 'GET') {
        return (new AuthController())->listCompanies();
    }
    if ($path === '/api/v1/auth/companies/select' && $method === 'POST') {
        return (new AuthController())->selectCompany();
    }
    if ($path === '/api/v1/auth/branches' && $method === 'GET') {
        return (new AuthController())->listBranches();
    }

    // 2. Users Management
    if ($path === '/api/v1/users' && $method === 'GET') {
        return (new UserController())->index();
    }
    if ($path === '/api/v1/users' && $method === 'POST') {
        return (new UserController())->store();
    }
    if (preg_match('#^/api/v1/users/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new UserController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            return (new UserController())->update($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/users/(\d+)/role$#', $path, $matches) && ($method === 'PUT' || $method === 'POST' || $method === 'PATCH')) {
        return (new UserController())->changeRole($matches[1]);
    }

    // 3. Dashboard
    if ($path === '/api/v1/dashboard/metrics' && $method === 'GET') {
        return (new DashboardController())->getMetrics();
    }

    // 4. Products CRUD & Price Lists
    if ($path === '/api/v1/products' && $method === 'GET') {
        return (new ProductController())->index();
    }
    if ($path === '/api/v1/products' && $method === 'POST') {
        return (new ProductController())->store();
    }
    if ($path === '/api/v1/products/bulk' && $method === 'POST') {
        return (new ProductController())->bulkAction();
    }
    if (preg_match('#^/api/v1/products/(\d+)/price$#', $path, $matches) && $method === 'GET') {
        return (new ProductController())->resolvePrice($matches[1]);
    }
    if (preg_match('#^/api/v1/products/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new ProductController())->show($matches[1]);
    }
    if (preg_match('#^/api/v1/products/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new ProductController())->update($matches[1]);
    }
    if (preg_match('#^/api/v1/products/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        return (new ProductController())->destroy($matches[1]);
    }

    // 5. Product CSV Import
    if ($path === '/api/v1/products/import/preview' && $method === 'POST') {
        return (new ProductImportController())->preview();
    }
    if ($path === '/api/v1/products/import' && $method === 'POST') {
        return (new ProductImportController())->import();
    }

    // 6. Multi-Warehouse Inventory & Movements
    if ($path === '/api/v1/inventory/warehouses' && $method === 'GET') {
        return (new InventoryController())->getWarehouses();
    }
    if ($path === '/api/v1/inventory/warehouses' && $method === 'POST') {
        return (new InventoryController())->createWarehouse();
    }
    if ($path === '/api/v1/inventory/summary' && $method === 'GET') {
        return (new InventoryController())->getStockSummary();
    }
    if ($path === '/api/v1/inventory/balances' && $method === 'GET') {
        return (new InventoryController())->getStockBalances();
    }
    if ($path === '/api/v1/inventory/adjustments' && $method === 'GET') {
        return (new InventoryController())->getAdjustments();
    }
    if ($path === '/api/v1/inventory/adjustments' && $method === 'POST') {
        return (new InventoryController())->createStockAdjustment();
    }
    if ($path === '/api/v1/inventory/transfers' && $method === 'GET') {
        return (new InventoryController())->getTransfers();
    }
    if ($path === '/api/v1/inventory/transfers' && $method === 'POST') {
        return (new InventoryController())->createTransfer();
    }
    if (preg_match('#^/api/v1/inventory/transfers/(\d+)/receive$#', $path, $matches) && $method === 'POST') {
        return (new InventoryController())->receiveTransfer($matches[1]);
    }
    if (preg_match('#^/api/v1/inventory/transfers/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new InventoryController())->cancelTransfer($matches[1]);
    }
    if ($path === '/api/v1/inventory/counts' && $method === 'GET') {
        return (new InventoryController())->getStockCounts();
    }
    if ($path === '/api/v1/inventory/counts' && $method === 'POST') {
        return (new InventoryController())->createStockCount();
    }
    if (preg_match('#^/api/v1/inventory/counts/(\d+)/reconcile$#', $path, $matches) && $method === 'POST') {
        return (new InventoryController())->reconcileStockCount($matches[1]);
    }
    if ($path === '/api/v1/inventory/batches' && $method === 'GET') {
        return (new InventoryController())->getBatches();
    }
    if ($path === '/api/v1/inventory/batches' && $method === 'POST') {
        return (new InventoryController())->createBatch();
    }
    if ($path === '/api/v1/inventory/batches/fefo' && $method === 'GET') {
        return (new InventoryController())->getFEFOBatches();
    }
    if ($path === '/api/v1/inventory/serials' && $method === 'GET') {
        return (new InventoryController())->getSerialNumbers();
    }
    if ($path === '/api/v1/inventory/serials' && $method === 'POST') {
        return (new InventoryController())->registerSerialNumber();
    }
    if (preg_match('#^/api/v1/inventory/serials/trace/([^/]+)$#', $path, $matches) && $method === 'GET') {
        return (new InventoryController())->traceSerialNumber(urldecode($matches[1]));
    }
    if ($path === '/api/v1/inventory/low-stock' && $method === 'GET') {
        return (new InventoryController())->getLowStockAlerts();
    }
    if ($path === '/api/v1/inventory/expiring-batches' && $method === 'GET') {
        return (new InventoryController())->getExpiringBatches();
    }
    if ($path === '/api/v1/inventory/valuation' && $method === 'GET') {
        return (new InventoryController())->getInventoryValuation();
    }
    if ($path === '/api/v1/inventory/movements' && $method === 'GET') {
        return (new InventoryController())->getStockMovements();
    }
    if ($path === '/api/v1/inventory/recalculate' && $method === 'POST') {
        return (new InventoryController())->recalculateInventory();
    }
    if ($path === '/api/v1/inventory/import-opening' && $method === 'POST') {
        return (new InventoryController())->importOpeningStock();
    }

    // 7. Customers & Suppliers
    if ($path === '/api/v1/customers' && $method === 'GET') {
        return (new CustomerController())->index();
    }
    if ($path === '/api/v1/customers' && $method === 'POST') {
        return (new CustomerController())->store();
    }
    if (preg_match('#^/api/v1/customers/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new CustomerController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new CustomerController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new CustomerController())->destroy($matches[1]);
        }
    }

    if ($path === '/api/v1/suppliers' && $method === 'GET') {
        return (new SupplierController())->index();
    }
    if ($path === '/api/v1/suppliers' && $method === 'POST') {
        return (new SupplierController())->store();
    }
    if (preg_match('#^/api/v1/suppliers/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new SupplierController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new SupplierController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new SupplierController())->destroy($matches[1]);
        }
    }

    // 7b. Master Data Foundation (Categories, Units, Taxes, Accounts, Groups, Tags)
    if ($path === '/api/v1/categories' && $method === 'GET') {
        return (new CategoryController())->index();
    }
    if ($path === '/api/v1/categories' && $method === 'POST') {
        return (new CategoryController())->store();
    }
    if (preg_match('#^/api/v1/categories/(\d+)$#', $path, $matches)) {
        if ($method === 'PUT' || $method === 'POST') {
            return (new CategoryController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new CategoryController())->destroy($matches[1]);
        }
    }

    if ($path === '/api/v1/units' && $method === 'GET') {
        return (new UnitController())->index();
    }
    if ($path === '/api/v1/units' && $method === 'POST') {
        return (new UnitController())->store();
    }
    if (preg_match('#^/api/v1/units/(\d+)$#', $path, $matches)) {
        if ($method === 'PUT' || $method === 'POST') {
            return (new UnitController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new UnitController())->destroy($matches[1]);
        }
    }
    if ($path === '/api/v1/unit-conversions' && $method === 'GET') {
        return (new UnitController())->indexConversions();
    }
    if ($path === '/api/v1/unit-conversions' && $method === 'POST') {
        return (new UnitController())->storeConversion();
    }

    if ($path === '/api/v1/taxes' && $method === 'GET') {
        return (new TaxRateController())->index();
    }
    if ($path === '/api/v1/taxes' && $method === 'POST') {
        return (new TaxRateController())->store();
    }
    if (preg_match('#^/api/v1/taxes/(\d+)$#', $path, $matches)) {
        if ($method === 'PUT' || $method === 'POST') {
            return (new TaxRateController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new TaxRateController())->destroy($matches[1]);
        }
    }

    if ($path === '/api/v1/accounts' && $method === 'GET') {
        return (new ChartOfAccountController())->index();
    }
    if ($path === '/api/v1/accounts' && $method === 'POST') {
        return (new ChartOfAccountController())->store();
    }
    if (preg_match('#^/api/v1/accounts/(\d+)$#', $path, $matches)) {
        if ($method === 'PUT' || $method === 'POST') {
            return (new ChartOfAccountController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new ChartOfAccountController())->destroy($matches[1]);
        }
    }

    if ($path === '/api/v1/customer-groups' && $method === 'GET') {
        return (new CustomerGroupController())->index();
    }
    if ($path === '/api/v1/customer-groups' && $method === 'POST') {
        return (new CustomerGroupController())->store();
    }

    if ($path === '/api/v1/supplier-groups' && $method === 'GET') {
        return (new SupplierGroupController())->index();
    }
    if ($path === '/api/v1/supplier-groups' && $method === 'POST') {
        return (new SupplierGroupController())->store();
    }

    if ($path === '/api/v1/tags' && $method === 'GET') {
        return (new TagController())->index();
    }
    if ($path === '/api/v1/tags' && $method === 'POST') {
        return (new TagController())->store();
    }

    // 8. Invoices
    if ($path === '/api/v1/invoices' && $method === 'GET') {
        return (new InvoiceController())->index();
    }
    if ($path === '/api/v1/invoices' && $method === 'POST') {
        return (new InvoiceController())->store();
    }
    if (preg_match('#^/api/v1/invoices/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new InvoiceController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            return (new InvoiceController())->update($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/invoices/(\d+)/post$#', $path, $matches) && $method === 'POST') {
        return (new InvoiceController())->post($matches[1]);
    }
    if (preg_match('#^/api/v1/invoices/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new InvoiceController())->cancel($matches[1]);
    }
    if (preg_match('#^/api/v1/invoices/(\d+)/duplicate$#', $path, $matches) && $method === 'POST') {
        return (new InvoiceController())->duplicate($matches[1]);
    }

    // 8a. Quotations & Estimates
    if ($path === '/api/v1/quotations' && $method === 'GET') {
        return (new QuotationController())->index();
    }
    if ($path === '/api/v1/quotations' && $method === 'POST') {
        return (new QuotationController())->store();
    }
    if (preg_match('#^/api/v1/quotations/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new QuotationController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new QuotationController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new QuotationController())->destroy($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/quotations/(\d+)/convert$#', $path, $matches) && $method === 'POST') {
        return (new QuotationController())->convert($matches[1]);
    }

    // 8b. Sales Orders
    if ($path === '/api/v1/sales-orders' && $method === 'GET') {
        return (new SalesOrderController())->index();
    }
    if ($path === '/api/v1/sales-orders' && $method === 'POST') {
        return (new SalesOrderController())->store();
    }
    if (preg_match('#^/api/v1/sales-orders/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new SalesOrderController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new SalesOrderController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new SalesOrderController())->destroy($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/sales-orders/(\d+)/convert$#', $path, $matches) && $method === 'POST') {
        return (new SalesOrderController())->convert($matches[1]);
    }

    // 8c. Delivery Challans
    if ($path === '/api/v1/delivery-challans' && $method === 'GET') {
        return (new DeliveryChallanController())->index();
    }
    if ($path === '/api/v1/delivery-challans' && $method === 'POST') {
        return (new DeliveryChallanController())->store();
    }
    if (preg_match('#^/api/v1/delivery-challans/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new DeliveryChallanController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new DeliveryChallanController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new DeliveryChallanController())->destroy($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/delivery-challans/(\d+)/convert$#', $path, $matches) && $method === 'POST') {
        return (new DeliveryChallanController())->convert($matches[1]);
    }

    // 8d. Credit Notes
    if ($path === '/api/v1/credit-notes' && $method === 'GET') {
        return (new CreditNoteController())->index();
    }
    if ($path === '/api/v1/credit-notes' && $method === 'POST') {
        return (new CreditNoteController())->store();
    }
    if (preg_match('#^/api/v1/credit-notes/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new CreditNoteController())->show($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new CreditNoteController())->destroy($matches[1]);
        }
    }

    // 8e. Sales Returns
    if ($path === '/api/v1/sales-returns' && $method === 'GET') {
        return (new SalesReturnController())->index();
    }
    if ($path === '/api/v1/sales-returns' && $method === 'POST') {
        return (new SalesReturnController())->store();
    }
    if (preg_match('#^/api/v1/sales-returns/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new SalesReturnController())->show($matches[1]);
    }

    // 8f. Recurring Invoices
    if ($path === '/api/v1/recurring-invoices' && $method === 'GET') {
        return (new RecurringInvoiceController())->index();
    }
    if ($path === '/api/v1/recurring-invoices' && $method === 'POST') {
        return (new RecurringInvoiceController())->store();
    }
    if (preg_match('#^/api/v1/recurring-invoices/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new RecurringInvoiceController())->show($matches[1]);
        }
        if ($method === 'PUT' || $method === 'POST') {
            return (new RecurringInvoiceController())->update($matches[1]);
        }
        if ($method === 'DELETE') {
            return (new RecurringInvoiceController())->destroy($matches[1]);
        }
    }

    // 9. Purchases
    if ($path === '/api/v1/purchases' && $method === 'GET') {
        return (new PurchaseController())->index();
    }
    if ($path === '/api/v1/purchases' && $method === 'POST') {
        return (new PurchaseController())->store();
    }
    if (preg_match('#^/api/v1/purchases/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new PurchaseController())->show($matches[1]);
    }
    if (preg_match('#^/api/v1/purchases/(\d+)/post$#', $path, $matches) && $method === 'POST') {
        return (new PurchaseController())->post($matches[1]);
    }
    if (preg_match('#^/api/v1/purchases/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new PurchaseController())->cancel($matches[1]);
    }
    if (preg_match('#^/api/v1/purchases/(\d+)/duplicate$#', $path, $matches) && $method === 'POST') {
        return (new PurchaseController())->duplicate($matches[1]);
    }

    // 9a. Purchase Orders
    if ($path === '/api/v1/purchase-orders' && $method === 'GET') {
        return (new PurchaseOrderController())->index();
    }
    if ($path === '/api/v1/purchase-orders' && $method === 'POST') {
        return (new PurchaseOrderController())->store();
    }
    if (preg_match('#^/api/v1/purchase-orders/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') {
            return (new PurchaseOrderController())->show($matches[1]);
        }
    }
    if (preg_match('#^/api/v1/purchase-orders/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new PurchaseOrderController())->cancel($matches[1]);
    }
    if (preg_match('#^/api/v1/purchase-orders/(\d+)/convert$#', $path, $matches) && $method === 'POST') {
        return (new PurchaseOrderController())->convert($matches[1]);
    }

    // 9b. Goods Receipts
    if ($path === '/api/v1/goods-receipts' && $method === 'GET') {
        return (new GoodsReceiptController())->index();
    }
    if ($path === '/api/v1/goods-receipts' && $method === 'POST') {
        return (new GoodsReceiptController())->store();
    }
    if (preg_match('#^/api/v1/goods-receipts/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new GoodsReceiptController())->show($matches[1]);
    }

    // 9c. Purchase Returns
    if ($path === '/api/v1/purchase-returns' && $method === 'GET') {
        return (new PurchaseReturnController())->index();
    }
    if ($path === '/api/v1/purchase-returns' && $method === 'POST') {
        return (new PurchaseReturnController())->store();
    }
    if (preg_match('#^/api/v1/purchase-returns/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new PurchaseReturnController())->show($matches[1]);
    }

    // 9d. Debit Notes
    if ($path === '/api/v1/debit-notes' && $method === 'GET') {
        return (new DebitNoteController())->index();
    }
    if ($path === '/api/v1/debit-notes' && $method === 'POST') {
        return (new DebitNoteController())->store();
    }
    if (preg_match('#^/api/v1/debit-notes/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new DebitNoteController())->show($matches[1]);
    }

    // 9e. Supplier Bill Attachments
    if ($path === '/api/v1/supplier-bill-attachments' && $method === 'GET') {
        return (new AttachmentController())->index();
    }
    if ($path === '/api/v1/supplier-bill-attachments' && $method === 'POST') {
        return (new AttachmentController())->store();
    }

    // 10. Expenses
    if ($path === '/api/v1/expenses' && $method === 'GET') {
        return (new ExpenseController())->index();
    }
    if ($path === '/api/v1/expenses' && $method === 'POST') {
        return (new ExpenseController())->store();
    }

    // 11. Reports & Financial Data
    if ($path === '/api/v1/reports/gstr1' && $method === 'GET') {
        return (new ReportController())->gstr1();
    }
    if ($path === '/api/v1/reports/profit-loss' && $method === 'GET') {
        return (new ReportController())->profitAndLoss();
    }
    if ($path === '/api/v1/reports/bank-ledger' && $method === 'GET') {
        return (new ReportController())->bankLedger();
    }

    // 12. CHUNK 6: Payments, Receivables, Payables, Advances, Refunds, Reminders
    if ($path === '/api/v1/payments' && $method === 'GET') {
        return (new PaymentController())->index();
    }
    if ($path === '/api/v1/payments' && $method === 'POST') {
        return (new PaymentController())->store();
    }
    if ($path === '/api/v1/payments/dashboard-summary' && $method === 'GET') {
        return (new PaymentController())->getDashboardSummary();
    }
    if (preg_match('#^/api/v1/payments/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new PaymentController())->show($matches[1]);
    }
    if (preg_match('#^/api/v1/payments/(\d+)/post$#', $path, $matches) && $method === 'POST') {
        return (new PaymentController())->post($matches[1]);
    }
    if (preg_match('#^/api/v1/payments/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new PaymentController())->cancel($matches[1]);
    }
    if (preg_match('#^/api/v1/payments/(\d+)/allocate$#', $path, $matches) && $method === 'POST') {
        return (new PaymentController())->allocate($matches[1]);
    }
    if (preg_match('#^/api/v1/payments/(\d+)/bounce-cheque$#', $path, $matches) && $method === 'POST') {
        return (new PaymentController())->bounceCheque($matches[1]);
    }

    if ($path === '/api/v1/receivables' && $method === 'GET') {
        return (new PaymentController())->getReceivables();
    }
    if ($path === '/api/v1/receivables/invoices' && $method === 'GET') {
        return (new PaymentController())->getOutstandingInvoices();
    }
    if ($path === '/api/v1/aging/receivables' && $method === 'GET') {
        return (new PaymentController())->getReceivablesAging();
    }

    if ($path === '/api/v1/payables' && $method === 'GET') {
        return (new PaymentController())->getPayables();
    }
    if ($path === '/api/v1/payables/bills' && $method === 'GET') {
        return (new PaymentController())->getOutstandingBills();
    }
    if ($path === '/api/v1/aging/payables' && $method === 'GET') {
        return (new PaymentController())->getPayablesAging();
    }

    if ($path === '/api/v1/customer-advances' && $method === 'GET') {
        return (new PaymentController())->getCustomerAdvances();
    }
    if ($path === '/api/v1/supplier-advances' && $method === 'GET') {
        return (new PaymentController())->getSupplierAdvances();
    }

    if (preg_match('#^/api/v1/customer-statements/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new PaymentController())->getCustomerStatement($matches[1]);
    }
    if (preg_match('#^/api/v1/supplier-statements/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new PaymentController())->getSupplierStatement($matches[1]);
    }

    if ($path === '/api/v1/refunds' && $method === 'GET') {
        return (new PaymentController())->getRefunds();
    }
    if ($path === '/api/v1/refunds' && $method === 'POST') {
        return (new PaymentController())->createRefund();
    }

    if ($path === '/api/v1/payment-reminders' && $method === 'GET') {
        return (new PaymentController())->getReminders();
    }
    if ($path === '/api/v1/payment-reminders' && $method === 'POST') {
        return (new PaymentController())->sendReminder();
    }
    if ($path === '/api/v1/payment-reminders/history' && $method === 'GET') {
        return (new PaymentController())->getReminderHistory();
    }

    // 13. CHUNK 7: Double-Entry Accounting Engine
    if ($path === '/api/v1/accounts' && $method === 'GET') {
        return (new AccountingController())->indexAccounts();
    }
    if ($path === '/api/v1/accounts' && $method === 'POST') {
        return (new AccountingController())->storeAccount();
    }
    if ($path === '/api/v1/accounts/tree' && $method === 'GET') {
        return (new AccountingController())->treeAccounts();
    }
    if (preg_match('#^/api/v1/accounts/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new AccountingController())->showAccount($matches[1]);
    }

    if ($path === '/api/v1/journals' && $method === 'GET') {
        return (new AccountingController())->indexJournals();
    }
    if ($path === '/api/v1/journals' && $method === 'POST') {
        return (new AccountingController())->storeJournal();
    }
    if (preg_match('#^/api/v1/journals/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new AccountingController())->showJournal($matches[1]);
    }
    if (preg_match('#^/api/v1/journals/(\d+)/post$#', $path, $matches) && $method === 'POST') {
        return (new AccountingController())->postJournal($matches[1]);
    }
    if (preg_match('#^/api/v1/journals/(\d+)/reverse$#', $path, $matches) && $method === 'POST') {
        return (new AccountingController())->reverseJournal($matches[1]);
    }

    if ($path === '/api/v1/ledger' && $method === 'GET') {
        return (new AccountingController())->getGeneralLedger();
    }
    if (preg_match('#^/api/v1/ledger/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new AccountingController())->getAccountLedger($matches[1]);
    }
    if ($path === '/api/v1/trial-balance' && $method === 'GET') {
        return (new AccountingController())->getTrialBalance();
    }

    if ($path === '/api/v1/reports/profit-loss' && $method === 'GET') {
        return (new AccountingController())->getProfitLoss();
    }
    if ($path === '/api/v1/reports/balance-sheet' && $method === 'GET') {
        return (new AccountingController())->getBalanceSheet();
    }
    if ($path === '/api/v1/reports/cash-flow' && $method === 'GET') {
        return (new AccountingController())->getCashFlow();
    }

    if ($path === '/api/v1/accounting/periods' && $method === 'GET') {
        return (new AccountingController())->getPeriods();
    }
    if ($path === '/api/v1/accounting/periods/lock' && $method === 'POST') {
        return (new AccountingController())->lockPeriod();
    }
    if (preg_match('#^/api/v1/accounting/periods/(\d+)/unlock$#', $path, $matches) && $method === 'POST') {
        return (new AccountingController())->unlockPeriod($matches[1]);
    }
    if ($path === '/api/v1/accounting/reconciliation' && $method === 'GET') {
        return (new AccountingController())->runReconciliation();
    }

    // 14. CHUNK 17: GST COMPLIANCE, TAX ENGINE, E-INVOICE, E-WAY BILL & RECONCILIATION
    if ($path === '/api/v1/gst/configuration' && $method === 'GET') {
        return (new GSTController())->getConfiguration();
    }
    if ($path === '/api/v1/gst/configuration' && ($method === 'PUT' || $method === 'POST')) {
        return (new GSTController())->updateConfiguration();
    }
    if ($path === '/api/v1/gst/gstin/validate' && $method === 'POST') {
        return (new GSTController())->validateGSTIN();
    }
    if ($path === '/api/v1/gst/states' && $method === 'GET') {
        return (new GSTController())->getStateMaster();
    }
    if ($path === '/api/v1/gst/rates' && $method === 'GET') {
        return (new GSTController())->getRates();
    }
    if ($path === '/api/v1/gst/rates' && $method === 'POST') {
        return (new GSTController())->createRate();
    }
    if (preg_match('#^/api/v1/gst/rates/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new GSTController())->updateRate($matches[1]);
    }
    if (preg_match('#^/api/v1/gst/rates/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        return (new GSTController())->deleteRate($matches[1]);
    }
    if ($path === '/api/v1/gst/hsn-sac' && $method === 'GET') {
        return (new GSTController())->getHsnSac();
    }
    if ($path === '/api/v1/gst/hsn-sac' && $method === 'POST') {
        return (new GSTController())->createHsnSac();
    }
    if (preg_match('#^/api/v1/gst/hsn-sac/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new GSTController())->updateHsnSac($matches[1]);
    }
    if ($path === '/api/v1/gst/calculate' && $method === 'POST') {
        return (new GSTController())->calculateTax();
    }
    if ($path === '/api/v1/gst/dashboard-summary' && $method === 'GET') {
        return (new GSTController())->getDashboardSummary();
    }
    if ($path === '/api/v1/gst/register' && $method === 'GET') {
        return (new GSTController())->getRegister();
    }
    if ($path === '/api/v1/gst/gstr1' && $method === 'GET') {
        return (new GSTController())->getGSTR1();
    }
    if ($path === '/api/v1/gst/gstr3b' && $method === 'GET') {
        return (new GSTController())->getGSTR3B();
    }
    if ($path === '/api/v1/gst/hsn-summary' && $method === 'GET') {
        return (new GSTController())->getHsnSummary();
    }
    if ($path === '/api/v1/gst/rate-analysis' && $method === 'GET') {
        return (new GSTController())->getRateAnalysis();
    }
    if ($path === '/api/v1/gst/state-wise' && $method === 'GET') {
        return (new GSTController())->getStateWiseSummary();
    }
    if ($path === '/api/v1/gst/periods' && $method === 'GET') {
        return (new GSTController())->getPeriodLocks();
    }
    if ($path === '/api/v1/gst/periods/lock' && $method === 'POST') {
        return (new GSTController())->lockPeriod();
    }
    if (preg_match('#^/api/v1/gst/periods/(\d+)/reopen$#', $path, $matches) && $method === 'POST') {
        return (new GSTController())->reopenPeriod($matches[1]);
    }
    if ($path === '/api/v1/gst/reconciliation' && $method === 'POST') {
        return (new GSTController())->runReconciliation();
    }
    if (preg_match('#^/api/v1/gst/reconciliation/(\d+)/action$#', $path, $matches) && $method === 'POST') {
        return (new GSTController())->actionReconciliationItem($matches[1]);
    }

    if ($path === '/api/v1/gst/einvoice/eligibility' && $method === 'POST') {
        return (new GSTController())->checkEInvoiceEligibility();
    }
    if ($path === '/api/v1/gst/einvoice/generate' && $method === 'POST') {
        return (new GSTController())->generateEInvoice();
    }
    if (preg_match('#^/api/v1/gst/einvoice/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new GSTController())->cancelEInvoice($matches[1]);
    }

    if ($path === '/api/v1/gst/ewaybill/eligibility' && $method === 'POST') {
        return (new GSTController())->checkEWayBillEligibility();
    }
    if ($path === '/api/v1/gst/ewaybill/generate' && $method === 'POST') {
        return (new GSTController())->generateEWayBill();
    }
    if (preg_match('#^/api/v1/gst/ewaybill/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new GSTController())->cancelEWayBill($matches[1]);
    }

    // 15. CHUNK 9: BANKING, RECONCILIATION, CHEQUES & PAYMENT INFRASTRUCTURE
    if ($path === '/api/v1/banks/overview' && $method === 'GET') {
        return (new BankingController())->getOverview();
    }
    if ($path === '/api/v1/banks' && $method === 'GET') {
        return (new BankingController())->getAccounts();
    }
    if ($path === '/api/v1/banks' && $method === 'POST') {
        return (new BankingController())->storeAccount();
    }
    if (preg_match('#^/api/v1/banks/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new BankingController())->showAccount($matches[1]);
    }
    if (preg_match('#^/api/v1/banks/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new BankingController())->updateAccount($matches[1]);
    }
    if (preg_match('#^/api/v1/banks/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        return (new BankingController())->deleteAccount($matches[1]);
    }
    if (preg_match('#^/api/v1/banks/(\d+)/transactions$#', $path, $matches) && $method === 'GET') {
        return (new BankingController())->getTransactions($matches[1]);
    }
    if (preg_match('#^/api/v1/banks/(\d+)/transactions$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->storeTransaction($matches[1]);
    }
    if (preg_match('#^/api/v1/banks/transactions/(\d+)/exclude$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->excludeTransaction($matches[1]);
    }

    if ($path === '/api/v1/bank-transfers' && $method === 'GET') {
        return (new BankingController())->getTransfers();
    }
    if ($path === '/api/v1/bank-transfers' && $method === 'POST') {
        return (new BankingController())->storeTransfer();
    }

    if ($path === '/api/v1/bank-statements/preview' && $method === 'POST') {
        return (new BankingController())->previewStatement();
    }
    if ($path === '/api/v1/bank-statements/confirm' && $method === 'POST') {
        return (new BankingController())->confirmStatement();
    }

    if ($path === '/api/v1/reconciliation/start' && $method === 'POST') {
        return (new BankingController())->startReconciliation();
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/report$#', $path, $matches) && $method === 'GET') {
        return (new BankingController())->getReconciliationReport($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/suggestions$#', $path, $matches) && $method === 'GET') {
        return (new BankingController())->suggestMatches($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/match$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->matchTransaction($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/unmatch$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->unmatchTransaction($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/create-entry$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->createMissingEntry($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/complete$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->completeReconciliation($matches[1]);
    }
    if (preg_match('#^/api/v1/reconciliation/(\d+)/reopen$#', $path, $matches) && $method === 'POST') {
        return (new BankingController())->reopenReconciliation($matches[1]);
    }

    if ($path === '/api/v1/cheques/register' && $method === 'GET') {
        return (new ChequeController())->getRegister();
    }
    if ($path === '/api/v1/cheques' && $method === 'GET') {
        return (new ChequeController())->index();
    }
    if ($path === '/api/v1/cheques' && $method === 'POST') {
        return (new ChequeController())->store();
    }
    if (preg_match('#^/api/v1/cheques/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new ChequeController())->show($matches[1]);
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/deposit$#', $path, $matches) && $method === 'POST') {
        return (new ChequeController())->deposit($matches[1]);
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/clear$#', $path, $matches) && $method === 'POST') {
        return (new ChequeController())->clear($matches[1]);
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/bounce$#', $path, $matches) && $method === 'POST') {
        return (new ChequeController())->bounce($matches[1]);
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new ChequeController())->cancel($matches[1]);
    }

    if ($path === '/api/v1/payment-links' && $method === 'GET') {
        return (new PaymentGatewayController())->getLinks();
    }
    if ($path === '/api/v1/payment-links' && $method === 'POST') {
        return (new PaymentGatewayController())->createLink();
    }
    if (preg_match('#^/api/v1/payment-links/([^/]+)$#', $path, $matches) && $method === 'GET') {
        return (new PaymentGatewayController())->showLink($matches[1]);
    }
    if (preg_match('#^/api/v1/payment-links/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new PaymentGatewayController())->cancelLink($matches[1]);
    }
    if (preg_match('#^/api/v1/payment-gateway/webhooks/([^/]+)$#', $path, $matches) && $method === 'POST') {
        return (new PaymentGatewayController())->handleWebhook($matches[1]);
    }
    if ($path === '/api/v1/payment-gateway/settlements' && $method === 'GET') {
        return (new PaymentGatewayController())->getSettlements();
    }
    if ($path === '/api/v1/payment-gateway/settlements' && $method === 'POST') {
        return (new PaymentGatewayController())->storeSettlement();
    }

    if ($path === '/api/v1/reports/cash-book' && $method === 'GET') {
        return (new BankingController())->getCashBook();
    }
    if ($path === '/api/v1/reports/bank-book' && $method === 'GET') {
        return (new BankingController())->getBankBook();
    }
    if ($path === '/api/v1/reports/cheque-register' && $method === 'GET') {
        return (new ChequeController())->getRegister();
    }

    // ----------------------------------------------------
    // 10. Document Engine, Templates & Sharing
    // ----------------------------------------------------
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/preview$#', $path, $matches) && $method === 'GET') {
        return (new DocumentController())->preview($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/pdf$#', $path, $matches) && $method === 'GET') {
        return (new DocumentController())->pdf($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/print$#', $path, $matches) && $method === 'GET') {
        return (new DocumentController())->printDoc($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/share$#', $path, $matches) && $method === 'POST') {
        return (new DocumentController())->createShare($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/email$#', $path, $matches) && $method === 'POST') {
        return (new DocumentController())->email($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/([^/]+)/([^/]+)/whatsapp$#', $path, $matches) && $method === 'POST') {
        return (new DocumentController())->whatsapp($matches[1], $matches[2]);
    }
    if (preg_match('#^/api/v1/documents/shared/([^/]+)$#', $path, $matches) && $method === 'GET') {
        return (new DocumentController())->accessShared($matches[1]);
    }
    if (preg_match('#^/api/v1/documents/shared/([^/]+)/revoke$#', $path, $matches) && $method === 'POST') {
        return (new DocumentController())->revokeShare($matches[1]);
    }
    if ($path === '/api/v1/document-templates' && $method === 'GET') {
        return (new DocumentController())->listTemplates();
    }
    if (preg_match('#^/api/v1/document-templates/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new DocumentController())->updateTemplate($matches[1]);
    }
    if ($path === '/api/v1/document-numbering' && $method === 'GET') {
        return (new DocumentController())->listNumbering();
    }
    if ($path === '/api/v1/document-numbering' && $method === 'POST') {
        return (new DocumentController())->saveNumbering();
    }

    // 19. CHUNK 11: REPORTING ENGINE, ANALYTICS & SAVED VIEWS
    if ($path === '/api/v1/reports' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->index(request());
    }
    if (preg_match('#^/api/v1/reports/([^/]+)/data$#', $path, $matches) && ($method === 'GET' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\ReportEngineController())->getReportData(request(), $matches[1]);
    }
    if (preg_match('#^/api/v1/reports/([^/]+)/export$#', $path, $matches) && ($method === 'GET' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\ReportEngineController())->export(request(), $matches[1]);
    }
    if ($path === '/api/v1/reports/saved-views' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->saveView(request());
    }
    if (preg_match('#^/api/v1/reports/saved-views/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->deleteView((int)$matches[1]);
    }

    // 20. CHUNK 12: PRICE LISTS, MULTI-WAREHOUSE & BRANCH MANAGEMENT
    // Branches
    if ($path === '/api/v1/branches' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\BranchController())->index(request());
    }
    if ($path === '/api/v1/branches' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BranchController())->store(request());
    }
    if ($path === '/api/v1/branches/comparison' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\BranchController())->comparison(request());
    }
    if (preg_match('#^/api/v1/branches/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') return (new \App\Http\Controllers\Api\BranchController())->show((int)$matches[1]);
        if ($method === 'PUT' || $method === 'POST') return (new \App\Http\Controllers\Api\BranchController())->update(request(), (int)$matches[1]);
        if ($method === 'DELETE') return (new \App\Http\Controllers\Api\BranchController())->destroy((int)$matches[1]);
    }

    // Warehouses
    if ($path === '/api/v1/warehouses' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\WarehouseController())->index(request());
    }
    if ($path === '/api/v1/warehouses' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\WarehouseController())->store(request());
    }
    if (preg_match('#^/api/v1/warehouses/(\d+)$#', $path, $matches)) {
        if ($method === 'GET') return (new \App\Http\Controllers\Api\WarehouseController())->show((int)$matches[1]);
        if ($method === 'PUT' || $method === 'POST') return (new \App\Http\Controllers\Api\WarehouseController())->update(request(), (int)$matches[1]);
        if ($method === 'DELETE') return (new \App\Http\Controllers\Api\WarehouseController())->destroy((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/warehouses/product/(\d+)/stock$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\WarehouseController())->stockByLocation((int)$matches[1]);
    }

    // Stock Transfers
    if ($path === '/api/v1/stock-transfers' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\StockTransferController())->index(request());
    }
    if ($path === '/api/v1/stock-transfers' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\StockTransferController())->store(request());
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\StockTransferController())->show((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)/approve$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\StockTransferController())->approve((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)/dispatch$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\StockTransferController())->dispatch((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)/receive$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\StockTransferController())->receive(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\StockTransferController())->cancel(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/stock-transfers/(\d+)/print$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\StockTransferController())->printNote((int)$matches[1]);
    }

    // Price Lists & Pricing Engine
    if ($path === '/api/v1/price-lists' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PriceListController())->index(request());
    }
    if ($path === '/api/v1/price-lists' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PriceListController())->store(request());
    }
    if (preg_match('#^/api/v1/price-lists/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PriceListController())->show((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/price-lists/(\d+)/items$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PriceListController())->saveItem(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/price-lists/(\d+)/bulk-update$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PriceListController())->bulkUpdate(request(), (int)$matches[1]);
    }
    if ($path === '/api/v1/prices/calculate' && ($method === 'GET' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\PriceListController())->calculatePrice(request());
    }
    if ($path === '/api/v1/prices/customer-override' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PriceListController())->setCustomerOverride(request());
    }

    // 21. CHUNK 13: NOTIFICATIONS, REMINDERS & RECURRING TRANSACTIONS
    // In-App Notifications
    if ($path === '/api/v1/notifications' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\NotificationController())->index(request());
    }
    if ($path === '/api/v1/notifications/unread' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\NotificationController())->unreadCount();
    }
    if ($path === '/api/v1/notifications/read-all' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\NotificationController())->markAllAsRead();
    }
    if (preg_match('#^/api/v1/notifications/(\d+)/read$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\NotificationController())->markAsRead((int)$matches[1]);
    }

    // Payment Reminders
    if ($path === '/api/v1/reminders/send' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReminderController())->send(request());
    }
    if ($path === '/api/v1/reminders/history' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReminderController())->history(request());
    }
    if ($path === '/api/v1/reminders/overview' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReminderController())->overview(request());
    }

    // Notification Settings & Templates
    if ($path === '/api/v1/notification-settings' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\NotificationSettingController())->getPreferences();
    }
    if ($path === '/api/v1/notification-settings' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\NotificationSettingController())->savePreferences(request());
    }
    if ($path === '/api/v1/notification-templates' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\NotificationSettingController())->listTemplates();
    }
    if (preg_match('#^/api/v1/notification-templates/(\d+)/preview$#', $path, $matches) && ($method === 'GET' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\NotificationSettingController())->previewTemplate((int)$matches[1], request());
    }

    // Recurring Transactions Engine
    if ($path === '/api/v1/recurring' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\RecurringController())->index(request());
    }
    if ($path === '/api/v1/recurring' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->store(request());
    }
    if ($path === '/api/v1/recurring/preview-next' && ($method === 'GET' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\RecurringController())->previewNext(request());
    }
    if (preg_match('#^/api/v1/recurring/(\d+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\RecurringController())->show((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/(\d+)/pause$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->pause((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/(\d+)/resume$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->resume((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->cancel((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/(\d+)/run-now$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->runNow((int)$matches[1], request());
    }

    // ==========================================
    // 22. CHUNK 14: PAYMENTS, BANKING, CHEQUES & PAYMENT GATEWAYS
    // ==========================================
    // Payments Master & Allocations
    if ($path === '/api/v1/payments' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentController())->index(request());
    }
    if ($path === '/api/v1/payments/methods' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentController())->getPaymentMethods();
    }
    if ($path === '/api/v1/payments/advances' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentController())->getAdvances();
    }
    if ($path === '/api/v1/payments/customer' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->recordCustomerPayment(request());
    }
    if ($path === '/api/v1/payments/supplier' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->recordSupplierPayment(request());
    }
    if ($path === '/api/v1/payments/advance' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->recordCustomerAdvance(request());
    }
    if (preg_match('#^/api/v1/payments/(\d+)/reverse$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->reverse((int)$matches[1], request());
    }
    if (preg_match('#^/api/v1/payments/(\d+)/allocate$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->allocate((int)$matches[1], request());
    }
    if (preg_match('#^/api/v1/payments/(\d+)/refund$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentController())->refund((int)$matches[1], request());
    }
    if (preg_match('#^/api/v1/payments/(\d+)/receipt$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentController())->printReceipt((int)$matches[1]);
    }
    if ($path === '/api/v1/payments/overview' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentController())->overview();
    }

    // Payment Links & Public Gateway
    if ($path === '/api/v1/payment-links' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentLinkController())->store(request());
    }
    if (preg_match('#^/api/v1/payment-links/public/([a-zA-Z0-9_\-]+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\PaymentLinkController())->showPublic($matches[1], request());
    }
    if (preg_match('#^/api/v1/payment-links/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\PaymentLinkController())->cancel((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/gateways/([a-zA-Z0-9_\-]+)/webhook$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\GatewayWebhookController())->handle($matches[1], request());
    }

    // Banking & Transfers
    if ($path === '/api/v1/bank-accounts' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\BankAccountController())->index(request());
    }
    if ($path === '/api/v1/bank-accounts' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankAccountController())->store(request());
    }
    if (preg_match('#^/api/v1/bank-accounts/(\d+)/transactions$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\BankAccountController())->transactions((int)$matches[1], request());
    }
    if ($path === '/api/v1/bank-transfers' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankAccountController())->transfer(request());
    }
    if ($path === '/api/v1/cash-deposit' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankAccountController())->cashDeposit(request());
    }
    if ($path === '/api/v1/cash-withdrawal' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankAccountController())->cashWithdrawal(request());
    }

    // Bank Reconciliation
    if ($path === '/api/v1/bank-reconciliations/start' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankReconciliationController())->start(request());
    }
    if ($path === '/api/v1/bank-reconciliations/import' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankReconciliationController())->import(request());
    }
    if ($path === '/api/v1/bank-reconciliations/suggested-matches' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\BankReconciliationController())->suggestedMatches(request());
    }
    if ($path === '/api/v1/bank-reconciliations/match' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankReconciliationController())->match(request());
    }
    if (preg_match('#^/api/v1/bank-reconciliations/(\d+)/complete$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\BankReconciliationController())->complete((int)$matches[1]);
    }

    // Cheques Register
    if ($path === '/api/v1/cheques' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ChequeController())->index(request());
    }
    if ($path === '/api/v1/cheques/receive' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ChequeController())->receive(request());
    }
    if ($path === '/api/v1/cheques/issue' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ChequeController())->issue(request());
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/deposit$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ChequeController())->deposit((int)$matches[1], request());
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/clear$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ChequeController())->clear((int)$matches[1], request());
    }
    if (preg_match('#^/api/v1/cheques/(\d+)/bounce$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ChequeController())->bounce((int)$matches[1], request());
    }

    // =========================================================================
    // SECTION 23: CHUNK 15 - IMPORT/EXPORT, BACKUP/RESTORE, DATA MIGRATION,
    // COMPREHENSIVE AUDIT TRAIL & SYSTEM ADMINISTRATION
    // =========================================================================

    // 1. Import Center
    if (preg_match('#^/api/v1/imports/templates/([^/]+)/download$#', $path, $matches) && $method === 'GET') {
        return (new \App\Imports\Controllers\ImportController())->downloadTemplateCsv(request(), $matches[1]);
    }
    if (preg_match('#^/api/v1/imports/templates/([^/]+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Imports\Controllers\ImportController())->getTemplate(request(), $matches[1]);
    }
    if ($path === '/api/v1/imports/upload' && $method === 'POST') {
        return (new \App\Imports\Controllers\ImportController())->upload(request());
    }
    if (preg_match('#^/api/v1/imports/(\d+)/preview$#', $path, $matches) && $method === 'POST') {
        return (new \App\Imports\Controllers\ImportController())->preview(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/imports/(\d+)/confirm$#', $path, $matches) && $method === 'POST') {
        return (new \App\Imports\Controllers\ImportController())->confirm(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/imports/(\d+)/error-report$#', $path, $matches) && $method === 'GET') {
        return (new \App\Imports\Controllers\ImportController())->downloadErrorReport(request(), (int)$matches[1]);
    }
    if ($path === '/api/v1/imports/history' && $method === 'GET') {
        return (new \App\Imports\Controllers\ImportController())->history(request());
    }

    // 2. Export Center
    if ($path === '/api/v1/exports' && $method === 'POST') {
        return (new \App\Exports\Controllers\ExportController())->export(request());
    }
    if (preg_match('#^/api/v1/exports/download/([^/]+)$#', $path, $matches) && $method === 'GET') {
        return (new \App\Exports\Controllers\ExportController())->download(request(), $matches[1]);
    }
    if ($path === '/api/v1/exports/history' && $method === 'GET') {
        return (new \App\Exports\Controllers\ExportController())->history(request());
    }

    // 3. Backup & Restore (Danger Zone)
    if ($path === '/api/v1/backups' && $method === 'GET') {
        return (new \App\Backups\Controllers\BackupController())->index(request());
    }
    if ($path === '/api/v1/backups' && $method === 'POST') {
        return (new \App\Backups\Controllers\BackupController())->create(request());
    }
    if (preg_match('#^/api/v1/backups/(\d+)/verify$#', $path, $matches) && $method === 'GET') {
        return (new \App\Backups\Controllers\BackupController())->verify(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/backups/(\d+)/validate-restore$#', $path, $matches) && $method === 'GET') {
        return (new \App\Backups\Controllers\BackupController())->validateRestore(request(), (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/backups/(\d+)/restore$#', $path, $matches) && $method === 'POST') {
        return (new \App\Backups\Controllers\BackupController())->restore(request(), (int)$matches[1]);
    }

    // 4. Data Migration Framework
    if ($path === '/api/v1/migrations/profiles' && $method === 'GET') {
        return (new \App\Migrations\Controllers\MigrationController())->getProfiles(request());
    }
    if ($path === '/api/v1/migrations/profiles' && $method === 'POST') {
        return (new \App\Migrations\Controllers\MigrationController())->saveProfile(request());
    }
    if ($path === '/api/v1/migrations/history' && $method === 'GET') {
        return (new \App\Migrations\Controllers\MigrationController())->getMigrations(request());
    }

    // 5. Comprehensive Audit Trail
    if ($path === '/api/v1/audit/logs' && $method === 'GET') {
        return (new \App\Audit\Controllers\AuditController())->index(request());
    }

    // 6. System Administration & Diagnostics
    if ($path === '/api/v1/system/health' && $method === 'GET') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->health(request());
    }
    if ($path === '/api/v1/system/integrity-check' && $method === 'GET') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->runIntegrityCheck(request());
    }
    if ($path === '/api/v1/system/numbering' && $method === 'GET') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->getNumberingConfigs(request());
    }
    if (preg_match('#^/api/v1/system/numbering/([^/]+)$#', $path, $matches) && $method === 'POST') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->updateNumberingConfig(request(), $matches[1]);
    }
    if ($path === '/api/v1/system/sessions' && $method === 'GET') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->listSessions(request());
    }
    if (preg_match('#^/api/v1/system/sessions/(\d+)/revoke$#', $path, $matches) && $method === 'POST') {
        return (new \App\SystemAdmin\Controllers\SystemAdminController())->revokeSession(request(), (int)$matches[1]);
    }

    // =========================================================================
    // SECTION 24: CHUNK 16 - REPORTING & ANALYTICS
    // =========================================================================
    if ($path === '/api/v1/reports' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->index();
    }
    if ($path === '/api/v1/reports/saved-views' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->saveView();
    }
    if (preg_match('#^/api/v1/reports/saved-views/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->deleteView((int)$matches[1]);
    }
    if ($path === '/api/v1/reports/schedules' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->listSchedules();
    }
    if ($path === '/api/v1/reports/schedules' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->createSchedule();
    }
    if ($path === '/api/v1/dashboard/management' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->getDashboard();
    }
    if (preg_match('#^/api/v1/reports/([^/]+)/data$#', $path, $matches) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReportEngineController())->getReportData($matches[1]);
    }
    // =========================================================================
    // SECTION 25: CHUNK 19 - AUTOMATION, NOTIFICATIONS, REMINDERS & RECURRING
    // =========================================================================
    // 25.1 Automation
    if ($path === '/api/v1/automation/metrics' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\AutomationController())->getMetrics();
    }
    if ($path === '/api/v1/automation/rules' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\AutomationController())->getRules();
    }
    if ($path === '/api/v1/automation/rules' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\AutomationController())->createRule();
    }
    if (preg_match('#^/api/v1/automation/rules/(\d+)$#', $path, $matches) && ($method === 'PUT' || $method === 'POST')) {
        return (new \App\Http\Controllers\Api\AutomationController())->updateRule((int)$matches[1]);
    }
    if ($path === '/api/v1/automation/events/publish' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\AutomationController())->publishEvent();
    }
    if ($path === '/api/v1/automation/logs' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\AutomationController())->getLogs();
    }

    // 25.2 Notifications
    if ($path === '/api/v1/notifications' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\NotificationController())->getNotifications();
    }
    if (preg_match('#^/api/v1/notifications/(\d+)/read$#', $path, $matches) && ($method === 'POST' || $method === 'PUT')) {
        return (new \App\Http\Controllers\Api\NotificationController())->markAsRead((int)$matches[1]);
    }
    if ($path === '/api/v1/notifications/mark-all-read' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\NotificationController())->markAllAsRead();
    }

    // 25.3 Reminders
    if ($path === '/api/v1/reminders' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\ReminderController())->getReminders();
    }
    if ($path === '/api/v1/reminders' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReminderController())->createReminder();
    }
    if ($path === '/api/v1/reminders/process-due' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReminderController())->processDue();
    }
    if (preg_match('#^/api/v1/reminders/(\d+)/dismiss$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\ReminderController())->dismiss((int)$matches[1]);
    }

    // 25.4 Recurring Transactions
    if ($path === '/api/v1/recurring/templates' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\RecurringController())->getTemplates();
    }
    if ($path === '/api/v1/recurring/templates' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->createTemplate();
    }
    if ($path === '/api/v1/recurring/run-due' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->runDue();
    }
    if (preg_match('#^/api/v1/recurring/templates/(\d+)/pause$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->pause((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/templates/(\d+)/resume$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->resume((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/recurring/templates/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\RecurringController())->cancel((int)$matches[1]);
    }
    if ($path === '/api/v1/recurring/history' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\RecurringController())->getHistory();
    }

    // 25.5 Communication Engine (Email, WhatsApp, Queue)
    if ($path === '/api/v1/communication/messages' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\CommunicationController())->getMessages();
    }
    if ($path === '/api/v1/communication/send-email' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->sendEmail();
    }
    if ($path === '/api/v1/communication/send-whatsapp' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->sendWhatsApp();
    }
    if ($path === '/api/v1/communication/queue/process' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->processQueue();
    }
    if (preg_match('#^/api/v1/communication/messages/(\d+)/retry$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->retry((int)$matches[1]);
    }
    if (preg_match('#^/api/v1/communication/messages/(\d+)/resend$#', $path, $matches) && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->resend((int)$matches[1]);
    }
    if ($path === '/api/v1/communication/templates' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\CommunicationController())->getTemplates();
    }
    if ($path === '/api/v1/communication/templates' && $method === 'POST') {
        return (new \App\Http\Controllers\Api\CommunicationController())->createTemplate();
    }

    return response_json(['status' => 'error', 'message' => 'API Route not found: ' . $path], 404);
}

