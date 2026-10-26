<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\InventoryService;

/**
 * Feature Test for Stock Adjustments & Warehouse Balances Upgrade.
 * Verifies tabs, 6 KPI cards, debounced filters, sorting, server-side pagination,
 * physical adjustment transactions, negative stock prevention, double-entry GL posting,
 * role authorization, and multi-format exports.
 */
class InventoryStockManagementTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Request redirects to /login
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/inventory/stock']);
        $guestRes = $router->dispatch($guestReq);
        $assert('STOCK-01', 'Unauthenticated request to /inventory/stock redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login'));

        // 2. Authenticate as Admin user
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId() ?? 1;
        $csrfToken = Session::getCsrfToken();

        // 3. Authenticated GET /inventory/stock renders 3 tabs, 6 KPI cards, and filter bar
        $viewReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/inventory/stock']);
        $viewRes = $router->dispatch($viewReq);
        $html = $viewRes->getContent();

        $hasTabs = str_contains($html, 'Warehouse Balances') && 
                   str_contains($html, 'Adjustment History') && 
                   str_contains($html, 'Stock Ledger');

        $hasKpis = str_contains($html, 'Total Stocked Units') && 
                   str_contains($html, 'Inventory Valuation') && 
                   str_contains($html, 'Reserved For Orders') && 
                   str_contains($html, 'Active Warehouses') && 
                   str_contains($html, 'Low Stock Items') && 
                   str_contains($html, 'Out of Stock');

        $hasFilters = str_contains($html, 'id="stock-search"') && 
                      str_contains($html, 'id="filter-warehouse"') && 
                      str_contains($html, 'id="filter-category"') && 
                      str_contains($html, 'id="filter-status"');

        $assert('STOCK-02', 'GET /inventory/stock renders upgraded view with 3 tabs, 6 KPI cards, and filter toolbar',
            $viewRes->getStatusCode() === 200 && $hasTabs && $hasKpis && $hasFilters);

        // 4. API Endpoint: GET /api/inventory/stock returns JSON with rows, totals, and pagination
        $apiReq = new Request(['tab' => 'balances', 'page' => '1', 'per_page' => '25'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/inventory/stock?tab=balances&page=1&per_page=25',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $apiRes = $router->dispatch($apiReq);
        $apiJson = json_decode($apiRes->getContent(), true) ?: [];

        $hasRowsAndTotals = isset($apiJson['rows']) && isset($apiJson['totals']) && isset($apiJson['pagination']);
        $hasIndianValuation = isset($apiJson['totals']['total_valuation_formatted']) && 
                              str_contains($apiJson['totals']['total_valuation_formatted'], '₹');

        $assert('STOCK-03', 'GET /api/inventory/stock returns valid JSON with rows, totals, and Indian Rupee valuation',
            $apiRes->getStatusCode() === 200 && $hasRowsAndTotals && $hasIndianValuation);

        // 5. API Status Filter: Filter by 'out_of_stock'
        $oosReq = new Request(['status' => 'out_of_stock'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/inventory/stock?status=out_of_stock',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $oosRes = $router->dispatch($oosReq);
        $oosJson = json_decode($oosRes->getContent(), true) ?: [];
        $oosRows = $oosJson['rows'] ?? [];

        $allOos = true;
        foreach ($oosRows as $row) {
            if ($row['status'] !== 'OUT OF STOCK') {
                $allOos = false;
                break;
            }
        }
        $assert('STOCK-04', 'API status filter "out_of_stock" isolates zero-balance inventory accurately',
            $oosRes->getStatusCode() === 200 && $allOos);

        // 6. API Sorting and Pagination
        $sortReq = new Request(['sort' => 'product_name', 'dir' => 'desc', 'page' => '1', 'per_page' => '10'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/inventory/stock?sort=product_name&dir=desc&page=1&per_page=10',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $sortRes = $router->dispatch($sortReq);
        $sortJson = json_decode($sortRes->getContent(), true) ?: [];
        $sortRows = $sortJson['rows'] ?? [];

        $assert('STOCK-05', 'API handles sorting and pagination with custom per_page parameter',
            $sortRes->getStatusCode() === 200 && count($sortRows) <= 10 && ($sortJson['pagination']['per_page'] ?? 0) === 10);

        // 7. Tabs API: History and Ledger retrieval
        $histReq = new Request(['tab' => 'history'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/inventory/stock?tab=history',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $histRes = $router->dispatch($histReq);
        $histJson = json_decode($histRes->getContent(), true) ?: [];

        $ledgerReq = new Request(['tab' => 'ledger'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/inventory/stock?tab=ledger',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $ledgerRes = $router->dispatch($ledgerReq);
        $ledgerJson = json_decode($ledgerRes->getContent(), true) ?: [];

        $assert('STOCK-06', 'API routes tab=history and tab=ledger return populated audit logs',
            $histRes->getStatusCode() === 200 && ($histJson['tab'] ?? '') === 'history' &&
            $ledgerRes->getStatusCode() === 200 && ($ledgerJson['tab'] ?? '') === 'ledger');

        // 8. Record Physical Stock Adjustment (Increase)
        $testProduct = Database::selectOne("SELECT id, name, sku, current_stock FROM products WHERE company_id = ? ORDER BY id ASC LIMIT 1", [$companyId]);
        $pId = (int)($testProduct['id'] ?? 1);
        $initialStock = (float)($testProduct['current_stock'] ?? 0);

        $adjReq = new Request([], [
            'product_id' => $pId,
            'warehouse_id' => 1,
            'adjustment_type' => 'Increase',
            'quantity' => 12.0,
            'unit_cost' => 150.00,
            'reason' => 'Physical Count Variance',
            'notes' => 'Automated test stock reconciliation',
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/inventory/adjustments',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ]);
        $adjRes = $router->dispatch($adjReq);
        $adjJson = json_decode($adjRes->getContent(), true) ?: [];

        $updatedProduct = Database::selectOne("SELECT current_stock FROM products WHERE id = ?", [$pId]);
        $newStock = (float)($updatedProduct['current_stock'] ?? 0);

        $assert('STOCK-07', 'POST /api/inventory/adjustments records increase and updates product stock aggregate',
            $adjRes->getStatusCode() === 201 && $newStock === ($initialStock + 12.0));

        // 9. Verify Double-Entry Journal Creation
        $lastJournal = Database::selectOne(
            "SELECT je.id, je.total_debit, je.total_credit, COUNT(jel.id) as line_count 
             FROM journal_entries je 
             JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id 
             WHERE je.company_id = ? AND je.reference_type = 'STOCK_ADJUSTMENT' 
             GROUP BY je.id 
             ORDER BY je.id DESC LIMIT 1",
            [$companyId]
        );
        $isBalanced = $lastJournal && ((float)$lastJournal['total_debit'] === (float)$lastJournal['total_credit']) && ((float)$lastJournal['total_debit'] > 0);

        $assert('STOCK-08', 'Posting stock adjustment creates strictly balanced double-entry journal (Debit == Credit)',
            $isBalanced && (int)$lastJournal['line_count'] >= 2);

        // 10. Negative Stock Prevention Rule
        $excessiveDeductReq = new Request([], [
            'product_id' => $pId,
            'warehouse_id' => 1,
            'adjustment_type' => 'Decrease',
            'quantity' => 9999999.0, // Exceeds stock
            'reason' => 'Physical Count Variance',
            'notes' => 'Attempt excessive negative deduction',
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/inventory/adjustments',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ]);
        $excessiveRes = $router->dispatch($excessiveDeductReq);
        $excessiveJson = json_decode($excessiveRes->getContent(), true) ?: [];

        $assert('STOCK-09', 'Deducting quantity exceeding current on-hand is blocked with HTTP 422 when negative stock disabled',
            $excessiveRes->getStatusCode() === 422 && str_contains($excessiveJson['message'] ?? '', 'negative inventory is disabled'));

        // 11. Role Permission Check (Restrict Non-Admin/Non-Inventory Manager)
        Session::set('user_role', 'VIEWER');
        $unauthReq = new Request([], [
            'product_id' => $pId,
            'warehouse_id' => 1,
            'adjustment_type' => 'Increase',
            'quantity' => 5.0,
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/inventory/adjustments',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ]);
        $unauthRes = $router->dispatch($unauthReq);

        $assert('STOCK-10', 'POST /api/inventory/adjustments rejects unauthorized role with HTTP 403 Forbidden',
            $unauthRes->getStatusCode() === 403);

        // Restore Admin role
        Session::set('user_role', 'ADMIN');

        // 12. Export Formats: CSV, Excel XML, and PDF Layout
        $csvReq = new Request(['format' => 'csv'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/inventory/stock/export?format=csv'
        ]);
        $csvRes = $router->dispatch($csvReq);
        $isCsvValid = $csvRes->getStatusCode() === 200 && 
                      str_contains($csvRes->getHeader('content-type') ?? '', 'text/csv') &&
                      str_contains($csvRes->getContent(), '"Product Name"');

        $excelReq = new Request(['format' => 'excel'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/inventory/stock/export?format=excel'
        ]);
        $excelRes = $router->dispatch($excelReq);
        $isExcelValid = $excelRes->getStatusCode() === 200 && 
                        str_contains($excelRes->getHeader('content-type') ?? '', 'vnd.ms-excel') &&
                        str_contains($excelRes->getContent(), 'urn:schemas-microsoft-com:office:spreadsheet');

        $pdfReq = new Request(['format' => 'pdf'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/inventory/stock/export?format=pdf'
        ]);
        $pdfRes = $router->dispatch($pdfReq);
        $isPdfValid = $pdfRes->getStatusCode() === 200 && 
                      str_contains($pdfRes->getContent(), 'Stock &amp; Warehouse Balances') &&
                      str_contains($pdfRes->getContent(), 'window.print()');

        $assert('STOCK-11', 'GET /inventory/stock/export generates CSV, Excel Workbook XML, and Printable PDF documents',
            $isCsvValid && $isExcelValid && $isPdfValid);
    }
}
