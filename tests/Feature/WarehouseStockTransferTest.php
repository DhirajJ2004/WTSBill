<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\WarehouseService;

/**
 * Feature Test Suite for Phase 4: Advanced Inventory, Warehouses & Stock Transfers.
 * Verifies multi-godown tracking, inter-warehouse logistics workflow (Pending -> Approved -> Dispatched -> Received),
 * stock validation, in-transit accounting, and product velocity reporting.
 */
class WarehouseStockTransferTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Request Protection
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/inventory/warehouses']);
        $guestRes = $router->dispatch($guestReq);
        $assert('WH-01', 'Unauthenticated request to /inventory/warehouses redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login')
        );

        // Authenticate as Administrator
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csrfToken = Session::getCsrfToken();

        // 2. GET /inventory/warehouses lists active facilities and summary stats
        $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/inventory/warehouses']);
        $indexRes = $router->dispatch($indexReq);
        $indexHtml = $indexRes->getContent();

        $assert('WH-02', 'GET /inventory/warehouses returns HTTP 200 with warehouse catalog and summary cards',
            $indexRes->getStatusCode() === 200 &&
            str_contains($indexHtml, 'Warehouses & Godowns') &&
            str_contains($indexHtml, 'Total Warehouses') &&
            str_contains($indexHtml, 'Primary Warehouse')
        );

        // 3. Create Secondary Warehouse
        $uniqueCode = 'WH-TST-' . strtoupper(substr(uniqid(), -4));
        $createWhPayload = [
            '_csrf_token' => $csrfToken,
            'name' => 'Pune Regional Hub ' . substr(uniqid(), -4),
            'code' => $uniqueCode,
            'branch_id' => 2,
            'city' => 'Pune',
            'address' => 'Plot 42, Hadapsar Industrial Area',
            'manager_name' => 'Amit Deshmukh',
            'phone' => '+91 98220 54321',
            'is_primary' => 0,
            'is_active' => 1,
        ];

        $createWhReq = new Request([], $createWhPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/warehouses',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $createWhRes = $router->dispatch($createWhReq);
        $createWhData = json_decode($createWhRes->getContent(), true);

        $newWhId = (int)($createWhData['id'] ?? 0);
        $whRow = Database::selectOne("SELECT * FROM warehouses WHERE id = ? AND company_id = ?", [$newWhId, $companyId]);

        $assert('WH-03', 'POST /inventory/warehouses creates secondary warehouse in warehouses table',
            $createWhRes->getStatusCode() === 201 &&
            $whRow !== null &&
            $whRow['code'] === $uniqueCode &&
            (int)$whRow['branch_id'] === 2
        );

        // 4. GET /inventory/warehouses/{id} renders facility details and stock table
        $showWhReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/inventory/warehouses/' . $newWhId]);
        $showWhRes = $router->dispatch($showWhReq);
        $showWhHtml = $showWhRes->getContent();

        $assert('WH-04', 'GET /inventory/warehouses/{id} returns warehouse details and stock table',
            $showWhRes->getStatusCode() === 200 &&
            str_contains($showWhHtml, $uniqueCode) &&
            str_contains($showWhHtml, 'Pune Regional Hub') &&
            str_contains($showWhHtml, 'Stock Balances in this Facility')
        );

        // Ensure source warehouse 1 has stock for testing
        $product = Database::selectOne("SELECT id, name, current_stock FROM products WHERE company_id = ? AND is_active = 1 LIMIT 1", [$companyId]);
        $productId = (int)$product['id'];

        $sourceBalance = Database::selectOne(
            "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = 1 AND product_id = ?",
            [$companyId, $productId]
        );
        if (!$sourceBalance) {
            Database::insert('stock_balances', [
                'company_id' => $companyId,
                'warehouse_id' => 1,
                'branch_id' => 1,
                'product_id' => $productId,
                'quantity' => 100.0,
                'available_quantity' => 100.0,
                'reserved_quantity' => 0.0,
                'in_transit_quantity' => 0.0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            Database::update(
                'stock_balances',
                ['quantity' => 100.0, 'available_quantity' => 100.0],
                'id = ?',
                [$sourceBalance['id']]
            );
        }

        // 5. Create Transfer Order
        $transferPayload = [
            '_csrf_token' => $csrfToken,
            'from_warehouse_id' => 1,
            'to_warehouse_id' => $newWhId,
            'transfer_date' => date('Y-m-d'),
            'reference_no' => 'GP-TEST-' . rand(100, 999),
            'notes' => 'Urgent branch replenishment',
            'auto_approve' => 1,
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 10.0,
                    'unit' => 'PCS',
                    'notes' => 'Batch A1',
                ]
            ],
        ];

        $transferReq = new Request([], $transferPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/transfers/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $transferRes = $router->dispatch($transferReq);
        $transferData = json_decode($transferRes->getContent(), true);
        $transferId = (int)($transferData['id'] ?? 0);

        $transferRow = Database::selectOne("SELECT * FROM stock_transfers WHERE id = ?", [$transferId]);
        $itemRow = Database::selectOne("SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$transferId]);

        $assert('WH-05', 'POST /inventory/transfers/create generates sequential TR-YYYY-XXXX transfer order and items',
            $transferRes->getStatusCode() === 201 &&
            $transferRow !== null &&
            str_starts_with((string)$transferRow['transfer_number'], 'TR-' . date('Y') . '-') &&
            $transferRow['status'] === 'APPROVED' &&
            $itemRow !== null &&
            (float)$itemRow['quantity'] === 10.0
        );

        // 6. Validation: Same source and destination warehouse rejected
        $sameWhPayload = $transferPayload;
        $sameWhPayload['to_warehouse_id'] = 1;

        $sameWhReq = new Request([], $sameWhPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/transfers/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $sameWhRes = $router->dispatch($sameWhReq);

        $assert('WH-06', 'Same source and destination warehouse rejected with HTTP 422 Unprocessable Entity',
            $sameWhRes->getStatusCode() === 422 &&
            str_contains($sameWhRes->getContent(), 'Source and destination warehouses cannot be the same')
        );

        // 7. Validation: Exceeding source available stock rejected
        $excessPayload = $transferPayload;
        $excessPayload['items'][0]['quantity'] = 99999.0;

        $excessReq = new Request([], $excessPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/inventory/transfers/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $excessRes = $router->dispatch($excessReq);

        $assert('WH-07', 'Insufficient source warehouse stock blocks transfer creation with clear validation error',
            $excessRes->getStatusCode() === 422 &&
            str_contains($excessRes->getContent(), 'Insufficient stock')
        );

        // 8. Dispatch Transfer: Updates status to DISPATCHED and places into transit
        $dispatchReq = new Request([], ['_csrf_token' => $csrfToken], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/inventory/transfers/{$transferId}/dispatch",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $dispatchRes = $router->dispatch($dispatchReq);

        $afterDispatchTransfer = Database::selectOne("SELECT * FROM stock_transfers WHERE id = ?", [$transferId]);
        $destBalanceAfterDispatch = Database::selectOne(
            "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
            [$companyId, $newWhId, $productId]
        );

        $assert('WH-08', 'POST /inventory/transfers/{id}/dispatch transitions status to DISPATCHED and increases in_transit_quantity',
            $dispatchRes->getStatusCode() === 200 &&
            $afterDispatchTransfer['status'] === 'DISPATCHED' &&
            $destBalanceAfterDispatch !== null &&
            (float)$destBalanceAfterDispatch['in_transit_quantity'] >= 10.0
        );

        // 9. Receive Transfer: Updates status to RECEIVED and credits available quantity at destination
        $receiveReq = new Request([], ['_csrf_token' => $csrfToken], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/inventory/transfers/{$transferId}/receive",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $receiveRes = $router->dispatch($receiveReq);

        $afterReceiveTransfer = Database::selectOne("SELECT * FROM stock_transfers WHERE id = ?", [$transferId]);
        $destBalanceAfterReceive = Database::selectOne(
            "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
            [$companyId, $newWhId, $productId]
        );

        $assert('WH-09', 'POST /inventory/transfers/{id}/receive transitions status to RECEIVED and credits destination available_quantity',
            $receiveRes->getStatusCode() === 200 &&
            $afterReceiveTransfer['status'] === 'RECEIVED' &&
            $destBalanceAfterReceive !== null &&
            (float)$destBalanceAfterReceive['quantity'] >= 10.0 &&
            (float)$destBalanceAfterReceive['in_transit_quantity'] === 0.0
        );

        // 10. Product Velocity & Low Stock Analytics
        $velocityReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/inventory/velocity?days=30',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $velocityRes = $router->dispatch($velocityReq);
        $velocityData = json_decode($velocityRes->getContent(), true);

        $assert('WH-10', 'GET /inventory/velocity classifies product movements into FAST, SLOW, and NON_MOVING and detects low-stock items',
            $velocityRes->getStatusCode() === 200 &&
            isset($velocityData['velocity']['summary']) &&
            isset($velocityData['velocity']['summary']['total_products']) &&
            is_array($velocityData['low_stock'])
        );

        // Clean up test transfer & test warehouse
        Database::delete('stock_transfer_items', 'transfer_id = ?', [$transferId]);
        Database::delete('stock_transfers', 'id = ?', [$transferId]);
        Database::delete('stock_balances', 'warehouse_id = ?', [$newWhId]);
        Database::delete('warehouses', 'id = ?', [$newWhId]);
    }
}
