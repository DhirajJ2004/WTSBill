<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuditLogger;
use WTSBill\Services\WarehouseService;

/**
 * Warehouse and Stock Logistics Controller.
 * Manages warehouses, stock transfer workflows (Pending -> Approved -> Dispatched -> Received),
 * stock velocity reporting, and low-stock replenishment alerts.
 */
class WarehouseController
{
    protected WarehouseService $warehouseService;

    public function __construct(?WarehouseService $warehouseService = null)
    {
        $this->warehouseService = $warehouseService ?? new WarehouseService();
    }

    /**
     * List all warehouses with stock metrics.
     * Route: GET /inventory/warehouses
     */
    public function index(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouses = $this->warehouseService->getWarehouses($companyId, [
            'search' => (string)$req->get('search', ''),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        $branches = Database::select("SELECT id, name, code, city FROM branches WHERE company_id = ? ORDER BY id ASC", [$companyId]);

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'data' => $warehouses]);
        }

        $html = View::renderWithLayout('inventory/warehouses/index', [
            'title' => 'Warehouses & Godowns',
            'currentUri' => '/inventory/warehouses',
            'warehouses' => $warehouses,
            'branches' => $branches,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new warehouse.
     * Route: POST /inventory/warehouses
     */
    public function store(Request $req): Response
    {
        Gate::authorize('inventory.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $id = $this->warehouseService->createWarehouse($companyId, $payload);

            AuditLogger::log(
                action: 'create',
                module: 'inventory',
                entityType: 'WAREHOUSE',
                entityId: $id,
                entityLabel: (string)($payload['name'] ?? ''),
                newValues: $payload,
                status: 'success',
                notes: "Created warehouse '{$payload['name']}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Warehouse created successfully.',
                    'id' => $id,
                ], 201);
            }

            Session::flash('success', 'Warehouse created successfully.');
            return Response::redirect('/inventory/warehouses');
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/warehouses');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create warehouse: ' . $e->getMessage());
            return Response::redirect('/inventory/warehouses');
        }
    }

    /**
     * Show single warehouse details and inventory.
     * Route: GET /inventory/warehouses/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouseId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->get('id') ?? 0)));

        $warehouse = $this->warehouseService->getWarehouse($companyId, $warehouseId);
        if (!$warehouse) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Warehouse not found.'], 404);
            }
            Session::flash('error', 'Warehouse not found.');
            return Response::redirect('/inventory/warehouses');
        }

        $stock = $this->warehouseService->getWarehouseStock($companyId, $warehouseId, [
            'search' => (string)$req->get('search', ''),
        ]);

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'warehouse' => $warehouse,
                'stock' => $stock,
            ]);
        }

        $branches = Database::select("SELECT id, name, code FROM branches WHERE company_id = ? ORDER BY id ASC", [$companyId]);

        $html = View::renderWithLayout('inventory/warehouses/show', [
            'title' => 'Warehouse: ' . $warehouse['name'],
            'currentUri' => '/inventory/warehouses',
            'warehouse' => $warehouse,
            'stock' => $stock,
            'branches' => $branches,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Update warehouse.
     * Route: POST /inventory/warehouses/{id}/update
     */
    public function update(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouseId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $this->warehouseService->updateWarehouse($companyId, $warehouseId, $payload);

            AuditLogger::log(
                action: 'update',
                module: 'inventory',
                entityType: 'WAREHOUSE',
                entityId: $warehouseId,
                entityLabel: (string)($payload['name'] ?? ''),
                newValues: $payload,
                status: 'success',
                notes: "Updated warehouse #{$warehouseId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Warehouse updated successfully.']);
            }

            Session::flash('success', 'Warehouse updated successfully.');
            return Response::redirect('/inventory/warehouses/' . $warehouseId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/warehouses/' . $warehouseId);
        }
    }

    /**
     * Delete warehouse.
     * Route: POST /inventory/warehouses/{id}/delete
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouseId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));

        try {
            $this->warehouseService->deleteWarehouse($companyId, $warehouseId);

            AuditLogger::log(
                action: 'delete',
                module: 'inventory',
                entityType: 'WAREHOUSE',
                entityId: $warehouseId,
                status: 'success',
                notes: "Deleted warehouse #{$warehouseId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Warehouse deleted successfully.']);
            }

            Session::flash('success', 'Warehouse deleted successfully.');
            return Response::redirect('/inventory/warehouses');
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/warehouses');
        }
    }

    /**
     * List stock transfers.
     * Route: GET /inventory/transfers
     */
    public function transfersIndex(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $status = (string)$req->get('status', 'ALL');
        $transfers = $this->warehouseService->getTransfers($companyId, [
            'status' => $status,
            'search' => (string)$req->get('search', ''),
            'warehouse_id' => (int)$req->get('warehouse_id', 0),
        ]);

        $warehouses = Database::select("SELECT id, name, code FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        // Status counts
        $counts = [
            'ALL' => count($this->warehouseService->getTransfers($companyId, ['status' => 'ALL'])),
            'PENDING' => count($this->warehouseService->getTransfers($companyId, ['status' => 'PENDING'])),
            'APPROVED' => count($this->warehouseService->getTransfers($companyId, ['status' => 'APPROVED'])),
            'DISPATCHED' => count($this->warehouseService->getTransfers($companyId, ['status' => 'DISPATCHED'])),
            'RECEIVED' => count($this->warehouseService->getTransfers($companyId, ['status' => 'RECEIVED'])),
            'CANCELLED' => count($this->warehouseService->getTransfers($companyId, ['status' => 'CANCELLED'])),
        ];

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'data' => $transfers, 'counts' => $counts]);
        }

        $html = View::renderWithLayout('inventory/transfers/index', [
            'title' => 'Stock Transfers & Logistics',
            'currentUri' => '/inventory/transfers',
            'transfers' => $transfers,
            'warehouses' => $warehouses,
            'currentStatus' => strtoupper($status),
            'counts' => $counts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Render stock transfer creation page.
     * Route: GET /inventory/transfers/create
     */
    public function transferCreate(Request $req): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouses = Database::select(
            "SELECT id, name, code, city, branch_id FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, name, sku, unit, current_stock FROM products WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('inventory/transfers/create', [
            'title' => 'New Stock Transfer Order',
            'currentUri' => '/inventory/transfers',
            'warehouses' => $warehouses,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new stock transfer.
     * Route: POST /inventory/transfers/create
     */
    public function transferStore(Request $req): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userName = $user ? (string)$user['name'] : (string)(Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $transferId = $this->warehouseService->createTransfer($companyId, $payload, $userName);

            AuditLogger::log(
                action: 'create',
                module: 'inventory',
                entityType: 'STOCK_TRANSFER',
                entityId: $transferId,
                newValues: $payload,
                status: 'success',
                notes: "Created stock transfer order #{$transferId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Stock transfer order created successfully.',
                    'id' => $transferId,
                ], 201);
            }

            Session::flash('success', 'Stock transfer order created successfully.');
            return Response::redirect('/inventory/transfers/' . $transferId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/transfers/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create transfer: ' . $e->getMessage());
            return Response::redirect('/inventory/transfers/create');
        }
    }

    /**
     * Show single transfer details.
     * Route: GET /inventory/transfers/{id}
     */
    public function transferShow(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->get('id') ?? 0)));

        $transfer = $this->warehouseService->getTransfer($companyId, $transferId);
        if (!$transfer) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Transfer order not found.'], 404);
            }
            Session::flash('error', 'Transfer order not found.');
            return Response::redirect('/inventory/transfers');
        }

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'transfer' => $transfer]);
        }

        $html = View::renderWithLayout('inventory/transfers/show', [
            'title' => 'Transfer ' . $transfer['transfer_number'],
            'currentUri' => '/inventory/transfers',
            'transfer' => $transfer,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Printable Stock Transfer Note / Gate Pass.
     * Route: GET /inventory/transfers/{id}/print
     */
    public function transferPrint(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->get('id') ?? 0)));

        $transfer = $this->warehouseService->getTransfer($companyId, $transferId);
        if (!$transfer) {
            return Response::html('<h1>Transfer Order Not Found</h1>', 404);
        }

        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]);

        $html = View::render('inventory/transfers/print', [
            'transfer' => $transfer,
            'company' => $company,
        ]);

        return Response::html($html);
    }

    /**
     * Approve stock transfer.
     * Route: POST /inventory/transfers/{id}/approve
     */
    public function transferApprove(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');

        try {
            $this->warehouseService->approveTransfer($companyId, $transferId, $userName);

            AuditLogger::log(
                action: 'approve',
                module: 'inventory',
                entityType: 'STOCK_TRANSFER',
                entityId: $transferId,
                status: 'success',
                notes: "Approved stock transfer #{$transferId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Transfer approved successfully.']);
            }

            Session::flash('success', 'Transfer approved successfully.');
            return Response::redirect('/inventory/transfers/' . $transferId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/transfers/' . $transferId);
        }
    }

    /**
     * Dispatch stock transfer.
     * Route: POST /inventory/transfers/{id}/dispatch
     */
    public function transferDispatch(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');

        try {
            $this->warehouseService->dispatchTransfer($companyId, $transferId, $userName);

            AuditLogger::log(
                action: 'dispatch',
                module: 'inventory',
                entityType: 'STOCK_TRANSFER',
                entityId: $transferId,
                status: 'success',
                notes: "Dispatched stock transfer #{$transferId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Transfer dispatched successfully. Goods are now in transit.']);
            }

            Session::flash('success', 'Transfer dispatched successfully. Goods are now in transit.');
            return Response::redirect('/inventory/transfers/' . $transferId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/transfers/' . $transferId);
        }
    }

    /**
     * Receive stock transfer at destination warehouse.
     * Route: POST /inventory/transfers/{id}/receive
     */
    public function transferReceive(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');

        try {
            $this->warehouseService->receiveTransfer($companyId, $transferId, $userName);

            AuditLogger::log(
                action: 'receive',
                module: 'inventory',
                entityType: 'STOCK_TRANSFER',
                entityId: $transferId,
                status: 'success',
                notes: "Received stock transfer #{$transferId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Transfer received successfully into destination warehouse inventory.']);
            }

            Session::flash('success', 'Transfer received successfully into destination warehouse inventory.');
            return Response::redirect('/inventory/transfers/' . $transferId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/transfers/' . $transferId);
        }
    }

    /**
     * Cancel stock transfer.
     * Route: POST /inventory/transfers/{id}/cancel
     */
    public function transferCancel(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.stock.add', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $transferId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');
        $reason = (string)($req->input('reason') ?? '');

        try {
            $this->warehouseService->cancelTransfer($companyId, $transferId, $userName, $reason);

            AuditLogger::log(
                action: 'cancel',
                module: 'inventory',
                entityType: 'STOCK_TRANSFER',
                entityId: $transferId,
                status: 'success',
                notes: "Cancelled stock transfer #{$transferId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Transfer cancelled successfully.']);
            }

            Session::flash('success', 'Transfer cancelled successfully.');
            return Response::redirect('/inventory/transfers/' . $transferId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/transfers/' . $transferId);
        }
    }

    /**
     * Product Velocity Analysis & Low Stock Dashboard.
     * Route: GET /inventory/velocity
     */
    public function velocityReport(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $days = (int)$req->get('days', 30);
        if (!in_array($days, [15, 30, 60, 90, 180, 365], true)) {
            $days = 30;
        }

        $velocity = $this->warehouseService->getStockVelocity($companyId, $days);
        $lowStock = $this->warehouseService->getLowStockAlerts($companyId);

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'velocity' => $velocity,
                'low_stock' => $lowStock,
            ]);
        }

        $html = View::renderWithLayout('inventory/velocity', [
            'title' => 'Stock Velocity & Inventory Health',
            'currentUri' => '/inventory/velocity',
            'velocity' => $velocity,
            'lowStock' => $lowStock,
            'days' => $days,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Low stock alerts API / endpoint.
     * Route: GET /inventory/low-stock
     */
    public function lowStockAlerts(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $warehouseId = (int)$req->get('warehouse_id', 0) ?: null;

        $alerts = $this->warehouseService->getLowStockAlerts($companyId, $warehouseId);

        return Response::json([
            'success' => true,
            'alerts' => $alerts,
            'count' => count($alerts),
        ]);
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}

