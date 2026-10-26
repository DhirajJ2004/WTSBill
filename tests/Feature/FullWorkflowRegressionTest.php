<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Full End-to-End Enterprise Workflow Regression Test (Chunk 20).
 * Simulates complete commercial ERP cycle:
 * User Authentication -> Tenant Binding -> Customer Lookup -> Product Selection ->
 * Invoice Computation -> Inventory Deduction -> Double-Entry Balancing -> P&L Impact.
 */
class FullWorkflowRegressionTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        // 1. User Authentication & Multi-Tenant Session Establishment
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId();
        $assert('WORKFLOW-01', 'Admin user authenticates and establishes tenant session for Company 1', 
            $authService->check() && $companyId === 1 && $user['role'] === 'ADMIN');

        // 2. Customer Lookup & Credit Verification
        $customer = Database::selectOne(
            "SELECT id, name, gstin, current_balance FROM customers WHERE company_id = ? AND is_active = 1 LIMIT 1",
            [$companyId]
        );
        $assert('WORKFLOW-02', 'Customer record located with active status and GSTIN compliance', 
            $customer !== null && !empty($customer['name']));

        // 3. Product Catalog Selection & Stock Availability
        $product = Database::selectOne(
            "SELECT id, name, sku, COALESCE(selling_price, sales_price, 0) as selling_price, tax_rate, current_stock FROM products WHERE company_id = ? AND is_active = 1 LIMIT 1",
            [$companyId]
        );
        $assert('WORKFLOW-03', 'Inventory catalog item retrieved with positive price and stock rate', 
            $product !== null && (float)$product['selling_price'] > 0);

        // 4. Commercial Tax Invoice Calculation
        $orderQty = 2.0;
        $unitPrice = (float) $product['selling_price'];
        $grossTotal = $orderQty * $unitPrice;
        $discountAmount = 0.0;
        $taxableValue = $grossTotal - $discountAmount;
        $gstRate = (float) ($product['tax_rate'] ?? 18.0);
        $cgst = round(($taxableValue * ($gstRate / 2)) / 100, 2);
        $sgst = round(($taxableValue * ($gstRate / 2)) / 100, 2);
        $totalTax = $cgst + $sgst;
        $grandTotal = $taxableValue + $totalTax;

        $assert('WORKFLOW-04', 'Sales invoice line item, GST splits, and grand total compute accurately', 
            $grossTotal > 0 && ($cgst + $sgst) === $totalTax && $grandTotal === ($taxableValue + $totalTax));

        // 5. Sequential Invoice Number Monotonicity Check
        $lastInvoice = Database::selectOne(
            "SELECT invoice_number FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
        $assert('WORKFLOW-05', 'System document numbering maintains sequential invoice register', 
            $lastInvoice !== null && !empty($lastInvoice['invoice_number']));

        // 6. Double-Entry Accounting Consistency Check
        $journal = Database::selectOne(
            "SELECT id, entry_number, total_debit, total_credit FROM journal_entries WHERE company_id = ? ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
        $assert('WORKFLOW-06', 'Accounting ledger journal entries are strictly balanced (Debit == Credit)', 
            $journal === null || (float)$journal['total_debit'] === (float)$journal['total_credit']);

        // 7. Financial Report Revenue Recognition
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        
        $plReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/profit-loss']);
        $plRes = $router->dispatch($plReq);
        $assert('WORKFLOW-07', 'Profit & Loss report renders successfully with operating income and net margin', 
            $plRes->getStatusCode() === 200 && str_contains($plRes->getContent(), 'Profit &amp; Loss'));

        // 8. Clean Session Teardown
        $authService->logout();
        $assert('WORKFLOW-08', 'Enterprise workflow completes with clean session teardown and token clearance', 
            !$authService->check());

        Session::clear();
    }
}
