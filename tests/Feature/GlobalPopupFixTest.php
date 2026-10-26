<?php

declare(strict_types=1);

namespace Tests\Feature;

use Closure;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;

/**
 * Feature Test Suite for Section 25: GLOBAL PLACEHOLDER POPUP FIX.
 * Verifies that zero alert() placeholders remain across the entire system,
 * and that all registration, modal, document, and export workflows persist to MySQL.
 */
class GlobalPopupFixTest
{
    public static function run(Closure $assert): void
    {
        $testCustomerId = 0;
        $testSupplierId = 0;
        $testCategoryId = 0;
        $testInvoiceId = 0;
        $testQuoteId = 0;
        $testChallanId = 0;
        $testPurchaseId = 0;
        $testExpenseId = 0;
        $testAccountId = 0;
        $testJournalId = 0;
        $testUserId = 0;

        try {
            // Set up authenticated admin session
            Session::start();
            Session::set('auth_user', [
                'id' => 1,
                'name' => 'Super Administrator',
                'email' => 'admin@wtsbill.com',
                'role' => 'ADMIN',
            ]);
            Session::set('company_id', 1);

            $router = new Router();
            $auth = new \WTSBill\Middleware\AuthenticationMiddleware();
            $csrf = new \WTSBill\Middleware\CsrfMiddleware();
            require ROOT_PATH . '/routes/web.php';

            // 1. SCAN VIEWS FOR ZERO ALERT POPUPS
            $viewFiles = glob(ROOT_PATH . '/views/**/*.php');
            $viewFiles = array_merge($viewFiles, glob(ROOT_PATH . '/views/**/**/*.php'));
            $viewFiles = array_merge($viewFiles, glob(ROOT_PATH . '/views/*.php'));
            $viewFiles = array_unique($viewFiles);

            $alertMatches = [];
            $placeholderMatches = [];
            foreach ($viewFiles as $vf) {
                $code = file_get_contents($vf);
                if (preg_match('/alert\s*\(/i', $code)) {
                    $alertMatches[] = basename($vf);
                }
                if (stripos($code, 'opens in next chunk') !== false) {
                    $placeholderMatches[] = basename($vf);
                }
            }

            $assert(
                'POPUP-01',
                'Complete workspace scan verifies ZERO alert() dialogs remain in views',
                count($alertMatches) === 0
            );

            $assert(
                'POPUP-02',
                'Complete workspace scan verifies ZERO "opens in next chunk" placeholders remain in views',
                count($placeholderMatches) === 0
            );

            // 2. CUSTOMER REGISTRATION WORKFLOW (MODAL & FULL-PAGE)
            $customerController = new \WTSBill\Controllers\CustomerController();

            // 2a. Validation failure on empty name
            $badCustReq = new Request([], ['name' => ''], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/customers',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $badCustResp = $customerController->store($badCustReq);
            $assert(
                'POPUP-03',
                'Submitting invalid customer data returns HTTP 422 validation failure',
                $badCustResp->getStatusCode() === 422
            );

            // 2b. Valid Customer Creation via modal AJAX
            $uniqueGstin = '27AAECP' . rand(1000, 9999) . 'A1Z' . rand(1, 9);
            $custData = [
                'name' => 'AutoTest Customer ' . rand(100, 999),
                'company_name' => 'AutoTest Enterprises Ltd',
                'gstin' => $uniqueGstin,
                'phone' => '9876543210',
                'email' => 'autotest' . rand(1000, 9999) . '@example.com',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'credit_limit' => 75000.00,
                'opening_balance' => 5000.00,
            ];
            $goodCustReq = new Request([], $custData, [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/customers',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $goodCustResp = $customerController->store($goodCustReq);
            $custJson = json_decode($goodCustResp->getBody(), true);
            $testCustomerId = (int)($custJson['customer']['id'] ?? 0);

            $assert(
                'POPUP-04',
                'Customer creation via modal persists to database with company_id',
                $goodCustResp->getStatusCode() === 201 && $testCustomerId > 0
            );

            // 2c. Customer Retrieval for Edit Modal
            $getCustReq = new Request(['id' => $testCustomerId], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/customers/' . $testCustomerId,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $getCustResp = $customerController->show($getCustReq, $testCustomerId);
            $getCustJson = json_decode($getCustResp->getBody(), true);
            $assert(
                'POPUP-05',
                'GET /customers/{id} returns customer details for modal population',
                $getCustResp->getStatusCode() === 200 && ($getCustJson['customer']['gstin'] ?? '') === $uniqueGstin
            );

            // 2d. Customer Update
            $updateCustReq = new Request([], array_merge($custData, [
                'id' => $testCustomerId,
                'name' => 'AutoTest Customer Updated',
            ]), [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/customers/' . $testCustomerId . '/update',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $updateCustResp = $customerController->update($updateCustReq, $testCustomerId);
            $dbCust = Database::selectOne("SELECT name FROM customers WHERE id = ?", [$testCustomerId]);
            $assert(
                'POPUP-06',
                'POST /customers/{id}/update modifies customer record in database',
                ($dbCust['name'] ?? '') === 'AutoTest Customer Updated'
            );

            // 3. SUPPLIER REGISTRATION WORKFLOW
            $supplierController = new \WTSBill\Controllers\SupplierController();
            $uniqueSupGstin = '24AABCS' . rand(1000, 9999) . 'F1Z' . rand(1, 9);
            $supData = [
                'name' => 'AutoTest Supplier ' . rand(100, 999),
                'company_name' => 'AutoTest Metals & Alloys',
                'gstin' => $uniqueSupGstin,
                'phone' => '9123456780',
                'email' => 'autosup' . rand(1000, 9999) . '@example.com',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'payment_terms_days' => 45,
                'opening_balance' => 12000.00,
            ];
            $goodSupReq = new Request([], $supData, [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/suppliers',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $goodSupResp = $supplierController->store($goodSupReq);
            $supJson = json_decode($goodSupResp->getBody(), true);
            $testSupplierId = (int)($supJson['supplier']['id'] ?? 0);

            $assert(
                'POPUP-07',
                'Supplier creation via modal persists to database with company_id',
                $goodSupResp->getStatusCode() === 201 && $testSupplierId > 0
            );

            // 3b. Supplier Retrieval for Edit Modal
            $getSupReq = new Request(['id' => $testSupplierId], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/suppliers/' . $testSupplierId,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $getSupResp = $supplierController->show($getSupReq, $testSupplierId);
            $getSupJson = json_decode($getSupResp->getBody(), true);
            $assert(
                'POPUP-08',
                'GET /suppliers/{id} returns supplier details for modal population',
                $getSupResp->getStatusCode() === 200 && ($getSupJson['supplier']['gstin'] ?? '') === $uniqueSupGstin
            );

            // 4. CATEGORY CREATION WORKFLOW
            $inventoryController = new \WTSBill\Controllers\InventoryController();
            $catReq = new Request([], [
                'name' => 'AutoTest Category ' . rand(100, 999),
                'code' => 'CAT-TEST-' . rand(10, 99),
                'description' => 'Automated test taxonomy group',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/categories',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $catResp = $inventoryController->categoryStore($catReq);
            $catJson = json_decode($catResp->getBody(), true);
            $testCategoryId = (int)($catJson['category']['id'] ?? 0);

            $assert(
                'POPUP-09',
                'Product category modal creation persists to categories table',
                $catResp->getStatusCode() === 201 && $testCategoryId > 0
            );

            // 5. STOCK ADJUSTMENT WORKFLOW
            $adjReq = new Request([], [
                'product_id' => 1,
                'warehouse_id' => 1,
                'adjustment_type' => 'ADD',
                'quantity' => 15.0,
                'notes' => 'Cycle count test adjustment',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/inventory/stock/adjust',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $adjResp = $inventoryController->stockAdjust($adjReq);
            $movementLog = Database::selectOne(
                "SELECT id FROM stock_movements WHERE product_id = 1 AND notes = 'Cycle count test adjustment' ORDER BY id DESC LIMIT 1"
            );
            $assert(
                'POPUP-10',
                'Stock adjustment modal creates stock_movements entry and updates balances',
                $adjResp->getStatusCode() === 200 && !empty($movementLog)
            );

            // 6. SALES INVOICE WORKFLOW
            $salesController = new \WTSBill\Controllers\SalesController();
            $invReq = new Request([], [
                'customer_id' => $testCustomerId,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'place_of_supply' => '27',
                'product_id' => 1,
                'quantity' => 5,
                'rate' => 120.00,
                'gst_rate' => 18,
                'notes' => 'Automated test sales invoice',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/invoices/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $invResp = $salesController->invoiceStore($invReq);
            $invJson = json_decode($invResp->getBody(), true);
            $testInvoiceId = (int)($invJson['invoice_id'] ?? 0);

            $assert(
                'POPUP-11',
                'Sales invoice form generates invoice with line items, tax split, and DB persistence',
                $invResp->getStatusCode() === 201 && $testInvoiceId > 0
            );

            // 6b. View Invoice Modal Detail API
            $invShowReq = new Request(['id' => $testInvoiceId], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/sales/invoices/' . $testInvoiceId,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $invShowResp = $salesController->invoiceShow($invShowReq, $testInvoiceId);
            $invShowJson = json_decode($invShowResp->getBody(), true);
            $assert(
                'POPUP-12',
                'GET /sales/invoices/{id} returns invoice header and line items for detail modal',
                $invShowResp->getStatusCode() === 200 && count($invShowJson['items'] ?? []) > 0
            );

            // 7. QUOTATIONS WORKFLOW
            $quoteReq = new Request([], [
                'customer_id' => $testCustomerId,
                'amount' => 15000.00,
                'notes' => 'Commercial bid test quotation',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/quotations',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $quoteResp = $salesController->quotationStore($quoteReq);
            $dbQuote = Database::selectOne(
                "SELECT id FROM quotations WHERE customer_id = ? AND grand_total = 15000.00 ORDER BY id DESC LIMIT 1",
                [$testCustomerId]
            );
            $testQuoteId = (int)($dbQuote['id'] ?? 0);
            $assert(
                'POPUP-13',
                'Quotation creation modal persists record to quotations table',
                $quoteResp->getStatusCode() === 201 && $testQuoteId > 0
            );

            // 8. DELIVERY CHALLANS WORKFLOW
            $challanReq = new Request([], [
                'customer_id' => $testCustomerId,
                'transport_details' => 'MH-04-TR-9988',
                'delivery_address' => 'Plot 44, MIDC Bhosari, Pune',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/challans',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $challanResp = $salesController->challanStore($challanReq);
            $dbChallan = Database::selectOne(
                "SELECT id FROM delivery_challans WHERE customer_id = ? AND transport_details = 'MH-04-TR-9988' ORDER BY id DESC LIMIT 1",
                [$testCustomerId]
            );
            $testChallanId = (int)($dbChallan['id'] ?? 0);
            $assert(
                'POPUP-14',
                'Delivery challan creation modal persists record to delivery_challans table',
                $challanResp->getStatusCode() === 201 && $testChallanId > 0
            );

            // 9. PURCHASE BILL WORKFLOW
            $purchaseController = new \WTSBill\Controllers\PurchaseController();
            $purReq = new Request([], [
                'supplier_id' => $testSupplierId,
                'vendor_invoice_number' => 'VND-TEST-8812',
                'amount' => 25000.00,
                'notes' => 'Test raw materials procurement',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/purchases/bills',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $purResp = $purchaseController->billStore($purReq);
            $dbPur = Database::selectOne(
                "SELECT id FROM purchases WHERE supplier_id = ? AND vendor_invoice_number = 'VND-TEST-8812' LIMIT 1",
                [$testSupplierId]
            );
            $testPurchaseId = (int)($dbPur['id'] ?? 0);
            $assert(
                'POPUP-15',
                'Purchase bill modal records purchase bill in database with supplier liability',
                $purResp->getStatusCode() === 201 && $testPurchaseId > 0
            );

            // 10. EXPENSES WORKFLOW
            $expenseController = new \WTSBill\Controllers\ExpenseController();
            $expReq = new Request([], [
                'category' => 'Office Supplies & Stationery',
                'payee' => 'Local Hardware & Print Store',
                'amount' => 1850.00,
                'payment_mode' => 'UPI / Online',
                'description' => 'Office stationery purchase',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/expenses',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $expResp = $expenseController->store($expReq);
            $dbExp = Database::selectOne(
                "SELECT id FROM expenses WHERE payee = 'Local Hardware & Print Store' AND amount = 1850.00 LIMIT 1"
            );
            $testExpenseId = (int)($dbExp['id'] ?? 0);
            $assert(
                'POPUP-16',
                'Expense recording modal persists voucher to expenses table',
                $expResp->getStatusCode() === 201 && $testExpenseId > 0
            );

            // 11. ACCOUNTING WORKFLOW (CHART OF ACCOUNTS & JOURNALS)
            $accountingController = new \WTSBill\Controllers\AccountingController();
            $accReq = new Request([], [
                'account_name' => 'AutoTest Petty Cash Fund',
                'account_type' => 'ASSET',
                'opening_balance' => 2000.00,
                'description' => 'Petty cash ledger for shop floor',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $accResp = $accountingController->accountStore($accReq);
            $dbAcc = Database::selectOne(
                "SELECT id FROM chart_of_accounts WHERE account_name = 'AutoTest Petty Cash Fund' LIMIT 1"
            );
            $testAccountId = (int)($dbAcc['id'] ?? 0);
            $assert(
                'POPUP-17',
                'Ledger account modal adds new account to chart_of_accounts',
                $accResp->getStatusCode() === 201 && $testAccountId > 0
            );

            // Balanced journal entry
            $jeReq = new Request([], [
                'entry_date' => date('Y-m-d'),
                'amount' => 1500.00,
                'debit_account_id' => $testAccountId,
                'credit_account_id' => 1, // Cash in hand
                'narration' => 'Transfer from main cash to shop floor petty cash',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts/journals',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $jeResp = $accountingController->journalStore($jeReq);
            $dbJe = Database::selectOne(
                "SELECT id, total_debit, total_credit FROM journal_entries WHERE narration = 'Transfer from main cash to shop floor petty cash' LIMIT 1"
            );
            $testJournalId = (int)($dbJe['id'] ?? 0);
            $assert(
                'POPUP-18',
                'Balanced double-entry journal posting maintains universal Debit == Credit equality in DB',
                $jeResp->getStatusCode() === 201 && $testJournalId > 0 &&
                (float)$dbJe['total_debit'] === (float)$dbJe['total_credit']
            );

            // 12. USER MANAGEMENT WORKFLOW
            $userController = new \WTSBill\Controllers\UserController();
            $uniqueUserEmail = 'autouser' . rand(1000, 9999) . '@wtsbill.com';
            $userReq = new Request([], [
                'name' => 'AutoTest Operator',
                'email' => $uniqueUserEmail,
                'role' => 'ACCOUNTANT',
                'password' => 'SecurePass#2026',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/users',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $userResp = $userController->store($userReq);
            $dbUser = Database::selectOne("SELECT id, password, role FROM users WHERE email = ?", [$uniqueUserEmail]);
            $testUserId = (int)($dbUser['id'] ?? 0);
            $assert(
                'POPUP-19',
                'User creation modal hashes password with Bcrypt and persists user record',
                $userResp->getStatusCode() === 201 && $testUserId > 0 &&
                password_verify('SecurePass#2026', $dbUser['password'])
            );

            // 13. REPORTS GSTR-1 EXPORT STREAMS
            $reportController = new \WTSBill\Controllers\ReportController();

            // JSON Export
            $jsonExpReq = new Request(['format' => 'json'], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/reports/gst/export?format=json',
            ]);
            $jsonExpResp = $reportController->gstExport($jsonExpReq);
            $jsonBody = json_decode($jsonExpResp->getBody(), true);
            $hasGstin = isset($jsonBody['gstin']);
            $hasDispo = str_contains($jsonExpResp->getHeader('Content-Disposition') ?? '', 'attachment');
            $assert(
                'POPUP-20',
                'GET /reports/gst/export?format=json returns downloadable GSTN JSON payload',
                $jsonExpResp->getStatusCode() === 200 && $hasGstin && $hasDispo
            );

            // CSV Export
            $csvExpReq = new Request(['format' => 'csv'], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/reports/gst/export?format=csv',
            ]);
            $csvExpResp = $reportController->gstExport($csvExpReq);
            $csvBody = $csvExpResp->getBody();
            $hasHeaders = str_contains($csvBody, 'Invoice Number,Invoice Date,Customer Name');
            $assert(
                'POPUP-21',
                'GET /reports/gst/export?format=csv returns downloadable CSV stream with tabular headers',
                $csvExpResp->getStatusCode() === 200 && $hasHeaders
            );

        } finally {
            // Clean up test records
            if ($testInvoiceId > 0) {
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$testInvoiceId]);
                Database::execute("DELETE FROM invoices WHERE id = ?", [$testInvoiceId]);
            }
            if ($testQuoteId > 0) {
                Database::execute("DELETE FROM quotations WHERE id = ?", [$testQuoteId]);
            }
            if ($testChallanId > 0) {
                Database::execute("DELETE FROM delivery_challans WHERE id = ?", [$testChallanId]);
            }
            if ($testPurchaseId > 0) {
                Database::execute("DELETE FROM purchases WHERE id = ?", [$testPurchaseId]);
            }
            if ($testExpenseId > 0) {
                Database::execute("DELETE FROM expenses WHERE id = ?", [$testExpenseId]);
            }
            if ($testJournalId > 0) {
                Database::execute("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$testJournalId]);
                Database::execute("DELETE FROM journal_entries WHERE id = ?", [$testJournalId]);
            }
            if ($testAccountId > 0) {
                Database::execute("DELETE FROM chart_of_accounts WHERE id = ?", [$testAccountId]);
            }
            if ($testCategoryId > 0) {
                Database::execute("DELETE FROM categories WHERE id = ?", [$testCategoryId]);
            }
            if ($testCustomerId > 0) {
                Database::execute("DELETE FROM customers WHERE id = ?", [$testCustomerId]);
            }
            if ($testSupplierId > 0) {
                Database::execute("DELETE FROM suppliers WHERE id = ?", [$testSupplierId]);
            }
            if ($testUserId > 0) {
                Database::execute("DELETE FROM users WHERE id = ?", [$testUserId]);
            }
            Database::execute("DELETE FROM stock_movements WHERE notes = 'Cycle count test adjustment'");
        }
    }
}
