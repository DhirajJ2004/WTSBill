<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\StockCount;
use App\Models\Batch;
use App\Models\SerialNumber;
use App\Http\Middleware\AuthMiddleware;
use App\Services\InventoryService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class InventoryController
{
    /**
     * Get all warehouses.
     */
    public function getWarehouses()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $warehouses = Warehouse::where('company_id', $user->current_company_id)
            ->withCount(['balances as total_items' => function ($q) {
                $q->where('quantity', '>', 0);
            }])
            ->orderBy('is_primary', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return response_json(['status' => 'success', 'data' => $warehouses]);
    }

    /**
     * Create warehouse.
     */
    public function createWarehouse()
    {
        $user = AuthMiddleware::authorize('inventory', 'manage_warehouses');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Warehouse name is required.'], 422);
        }

        $code = trim($input['code'] ?? '') ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));

        $wh = Warehouse::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name,
            'code' => $code,
            'address' => $input['address'] ?? '',
            'city' => $input['city'] ?? '',
            'is_primary' => filter_var($input['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_active' => true,
        ]);

        AuditLogService::log($companyId, $user->name, 'WAREHOUSE_CREATE', 'Warehouse', $wh->id, "Created Warehouse: {$name} ({$code})");

        return response_json(['status' => 'success', 'message' => 'Warehouse created successfully.', 'data' => $wh], 201);
    }

    /**
     * Get stock balances across products and warehouses.
     */
    public function getStockBalances()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = $user->current_company_id;

        $query = StockBalance::where('company_id', $companyId)
            ->with(['product.category', 'warehouse']);

        if (!empty($_GET['warehouse_id'])) {
            $query->where('warehouse_id', intval($_GET['warehouse_id']));
        }
        if (!empty($_GET['product_id'])) {
            $query->where('product_id', intval($_GET['product_id']));
        }

        $balances = $query->get();
        return response_json(['status' => 'success', 'data' => $balances]);
    }

    /**
     * Get stock summary matrix (products with their warehouse breakdowns).
     */
    public function getStockSummary()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = $user->current_company_id;

        $products = Product::where('company_id', $companyId)
            ->with(['category', 'stockBalances.warehouse'])
            ->get();

        $warehouses = Warehouse::where('company_id', $companyId)->get();

        $summary = $products->map(function ($p) use ($warehouses) {
            $whMap = [];
            foreach ($p->stockBalances as $sb) {
                $whMap[$sb->warehouse_id] = floatval($sb->quantity);
            }

            $whBreakdown = [];
            foreach ($warehouses as $w) {
                $whBreakdown[] = [
                    'warehouse_id' => $w->id,
                    'warehouse_name' => $w->name,
                    'quantity' => $whMap[$w->id] ?? 0.0,
                ];
            }

            $currentStock = floatval($p->current_stock);
            $minAlert = floatval($p->min_stock_alert ?: 10);
            $status = 'NORMAL';
            if ($currentStock <= 0) $status = 'OUT_OF_STOCK';
            elseif ($currentStock <= $minAlert) $status = 'LOW_STOCK';

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'hsn_sac' => $p->hsn_sac,
                'category' => $p->category ? $p->category->name : 'General',
                'unit' => $p->unit ?: 'Pcs',
                'current_stock' => $currentStock,
                'min_stock_alert' => $minAlert,
                'reorder_level' => floatval($p->reorder_level ?: 10),
                'reorder_quantity' => floatval($p->reorder_quantity ?: 50),
                'purchase_price' => floatval($p->purchase_price),
                'sales_price' => floatval($p->sales_price),
                'valuation' => round($currentStock * floatval($p->purchase_price), 2),
                'status' => $status,
                'warehouses' => $whBreakdown,
            ];
        });

        return response_json([
            'status' => 'success',
            'data' => $summary,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Get stock movement ledger (single authoritative source of truth).
     */
    public function getStockMovements()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = $user->current_company_id;

        $query = StockMovement::where('company_id', $companyId)
            ->with(['product', 'warehouse', 'batch', 'serialNumber']);

        if (!empty($_GET['product_id'])) {
            $query->where('product_id', intval($_GET['product_id']));
        }
        if (!empty($_GET['warehouse_id'])) {
            $query->where('warehouse_id', intval($_GET['warehouse_id']));
        }
        if (!empty($_GET['movement_type'])) {
            $query->where('movement_type', trim($_GET['movement_type']));
        }
        if (!empty($_GET['direction'])) {
            $query->where('direction', strtoupper(trim($_GET['direction'])));
        }
        if (!empty($_GET['from_date'])) {
            $query->where('movement_date', '>=', $_GET['from_date']);
        }
        if (!empty($_GET['to_date'])) {
            $query->where('movement_date', '<=', $_GET['to_date']);
        }

        $movements = $query->orderBy('id', 'desc')->limit(250)->get();
        return response_json(['status' => 'success', 'data' => $movements]);
    }

    /**
     * Record Stock Adjustment (Adds or reduces stock with explicit reasons).
     */
    public function createStockAdjustment()
    {
        $user = AuthMiddleware::authorize('inventory', 'adjust');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null);
        if ($warehouseId <= 0) {
            return response_json(['status' => 'error', 'message' => 'No active warehouse found for this company.'], 422);
        }
        $reason = trim($input['reason'] ?? 'Manual Correction');
        $items = $input['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return response_json(['status' => 'error', 'message' => 'Adjustment must contain at least 1 item.'], 422);
        }

        try {
            $adjustment = InventoryService::createAdjustment(
                companyId: $companyId,
                warehouseId: $warehouseId,
                items: $items,
                reason: $reason,
                notes: $input['notes'] ?? '',
                createdByName: $user->name,
                branchId: $branchId
            );

            AuditLogService::log(
                $companyId,
                $user->name,
                'STOCK_ADJUSTMENT',
                'StockAdjustment',
                $adjustment->id,
                "Executed Stock Adjustment #{$adjustment->adjustment_number}: Reason - {$reason}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Stock Adjustment #{$adjustment->adjustment_number} completed.",
                'data' => $adjustment->load(['items.product', 'warehouse']),
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get stock adjustments list.
     */
    public function getAdjustments()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $adjustments = StockAdjustment::where('company_id', $user->current_company_id)
            ->with(['items.product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $adjustments]);
    }

    /**
     * Get warehouse stock transfers.
     */
    public function getTransfers()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $transfers = StockTransfer::where('company_id', $user->current_company_id)
            ->with(['items.product', 'fromWarehouse', 'toWarehouse'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $transfers]);
    }

    /**
     * Create warehouse stock transfer (Dispatches from source warehouse).
     */
    public function createTransfer()
    {
        $user = AuthMiddleware::authorize('inventory', 'transfer');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $fromWh = intval($input['from_warehouse_id'] ?? 0);
        $toWh = intval($input['to_warehouse_id'] ?? 0);
        $items = $input['items'] ?? [];

        if ($fromWh <= 0 || $toWh <= 0 || $fromWh === $toWh) {
            return response_json(['status' => 'error', 'message' => 'Valid, distinct source and destination warehouses are required.'], 422);
        }

        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Transfer must contain at least 1 item.'], 422);
        }

        try {
            $transfer = InventoryService::createTransfer(
                companyId: $companyId,
                fromWarehouseId: $fromWh,
                toWarehouseId: $toWh,
                items: $items,
                refNo: $input['reference_no'] ?? '',
                notes: $input['notes'] ?? '',
                branchId: $branchId
            );

            AuditLogService::log(
                $companyId,
                $user->name,
                'STOCK_TRANSFER',
                'StockTransfer',
                $transfer->id,
                "Created Transfer #{$transfer->transfer_number} from Warehouse #{$fromWh} to Warehouse #{$toWh}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Stock Transfer #{$transfer->transfer_number} dispatched and placed in transit.",
                'data' => $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse']),
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark Stock Transfer as RECEIVED at destination warehouse.
     */
    public function receiveTransfer($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'transfer');
        try {
            $transfer = InventoryService::receiveTransfer(intval($id));

            AuditLogService::log(
                $transfer->company_id,
                $user->name,
                'STOCK_TRANSFER_RECEIVE',
                'StockTransfer',
                $transfer->id,
                "Received Stock Transfer #{$transfer->transfer_number} at destination Warehouse"
            );

            return response_json([
                'status' => 'success',
                'message' => "Stock Transfer #{$transfer->transfer_number} marked as RECEIVED and inventory updated.",
                'data' => $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse']),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel an In-Transit Stock Transfer.
     */
    public function cancelTransfer($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'transfer');
        $input = get_json_input();
        $reason = trim($input['reason'] ?? 'Cancelled by authorized user');

        try {
            $transfer = InventoryService::cancelTransfer(intval($id), $reason);

            AuditLogService::log(
                $transfer->company_id,
                $user->name,
                'STOCK_TRANSFER_CANCEL',
                'StockTransfer',
                $transfer->id,
                "Cancelled Transfer #{$transfer->transfer_number}: {$reason}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Stock Transfer #{$transfer->transfer_number} cancelled and returned to source warehouse.",
                'data' => $transfer,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get stock counts (physical audits).
     */
    public function getStockCounts()
    {
        $user = AuthMiddleware::authorize('inventory', 'count');
        $counts = StockCount::where('company_id', $user->current_company_id)
            ->with(['items.product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $counts]);
    }

    /**
     * Create physical stock count sheet.
     */
    public function createStockCount()
    {
        $user = AuthMiddleware::authorize('inventory', 'count');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null);
        if ($warehouseId <= 0) {
            return response_json(['status' => 'error', 'message' => 'No active warehouse found for this company.'], 422);
        }
        $items = $input['items'] ?? [];

        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Please provide at least 1 product count line.'], 422);
        }

        try {
            $stockCount = InventoryService::createStockCount(
                companyId: $companyId,
                warehouseId: $warehouseId,
                items: $items,
                notes: $input['notes'] ?? '',
                countedBy: $user->name,
                branchId: $branchId
            );

            AuditLogService::log(
                $companyId,
                $user->name,
                'STOCK_COUNT_CREATE',
                'StockCount',
                $stockCount->id,
                "Created Physical Stock Count sheet #{$stockCount->count_number}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Physical Stock Count #{$stockCount->count_number} created.",
                'data' => $stockCount->load(['items.product', 'warehouse']),
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reconcile / Approve Stock Count (creates automated adjustment for variances).
     */
    public function reconcileStockCount($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'count');
        try {
            $stockCount = InventoryService::reconcileStockCount(intval($id));

            AuditLogService::log(
                $stockCount->company_id,
                $user->name,
                'STOCK_COUNT_RECONCILE',
                'StockCount',
                $stockCount->id,
                "Reconciled Physical Stock Count #{$stockCount->count_number} with automated adjustments"
            );

            return response_json([
                'status' => 'success',
                'message' => "Stock Count #{$stockCount->count_number} reconciled successfully.",
                'data' => $stockCount->load(['items.product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get Batches list.
     */
    public function getBatches()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $batches = Batch::where('company_id', $user->current_company_id)
            ->with(['product', 'warehouse'])
            ->orderBy('expiry_date', 'asc')
            ->get();

        return response_json(['status' => 'success', 'data' => $batches]);
    }

    /**
     * Create Batch.
     */
    public function createBatch()
    {
        $user = AuthMiddleware::authorize('inventory', 'manage_batches');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $batchNumber = trim($input['batch_number'] ?? '');
        $productId = intval($input['product_id'] ?? 0);

        if (empty($batchNumber) || $productId <= 0) {
            return response_json(['status' => 'error', 'message' => 'Product and Batch Number are required.'], 422);
        }

        $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null);
        if ($warehouseId <= 0) {
            return response_json(['status' => 'error', 'message' => 'No active warehouse found for this company.'], 422);
        }

        $batch = Batch::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_number' => $batchNumber,
            'mfg_date' => $input['mfg_date'] ?? date('Y-m-d'),
            'expiry_date' => $input['expiry_date'] ?? date('Y-m-d', strtotime('+365 days')),
            'purchase_rate' => floatval($input['purchase_rate'] ?? 0),
            'quantity' => floatval($input['quantity'] ?? 0),
            'status' => 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'BATCH_CREATE', 'Batch', $batch->id, "Registered Batch: {$batchNumber}");

        return response_json(['status' => 'success', 'message' => 'Batch created successfully.', 'data' => $batch], 201);
    }

    /**
     * Get FEFO Recommended Batches for product.
     */
    public function getFEFOBatches()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $productId = intval($_GET['product_id'] ?? 0);
        $warehouseId = !empty($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : null;

        $batches = InventoryService::getFEFOBatches($user->current_company_id, $productId, $warehouseId);
        return response_json(['status' => 'success', 'data' => $batches]);
    }

    /**
     * Get Serial Numbers list.
     */
    public function getSerialNumbers()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $query = SerialNumber::where('company_id', $user->current_company_id)
            ->with(['product', 'warehouse', 'invoice']);

        if (!empty($_GET['status'])) {
            $query->where('status', trim($_GET['status']));
        }
        if (!empty($_GET['product_id'])) {
            $query->where('product_id', intval($_GET['product_id']));
        }

        $serials = $query->orderBy('id', 'desc')->get();
        return response_json(['status' => 'success', 'data' => $serials]);
    }

    /**
     * Register Serial Number.
     */
    public function registerSerialNumber()
    {
        $user = AuthMiddleware::authorize('inventory', 'manage_serials');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $productId = intval($input['product_id'] ?? 0);
        $serialNo = trim($input['serial_number'] ?? '');

        if ($productId <= 0 || empty($serialNo)) {
            return response_json(['status' => 'error', 'message' => 'Product and Serial Number are required.'], 422);
        }

        if (SerialNumber::where('company_id', $companyId)->where('product_id', $productId)->where('serial_number', $serialNo)->exists()) {
            return response_json(['status' => 'error', 'message' => "Serial number '{$serialNo}' already exists for this product."], 422);
        }

        $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null);
        if ($warehouseId <= 0) {
            return response_json(['status' => 'error', 'message' => 'No active warehouse found for this company.'], 422);
        }

        $serial = SerialNumber::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'serial_number' => $serialNo,
            'status' => 'AVAILABLE',
            'notes' => $input['notes'] ?? '',
        ]);

        AuditLogService::log($companyId, $user->name, 'SERIAL_CREATE', 'SerialNumber', $serial->id, "Registered Serial: {$serialNo}");

        return response_json(['status' => 'success', 'message' => 'Serial number registered.', 'data' => $serial], 201);
    }

    /**
     * Trace Serial Number History.
     */
    public function traceSerialNumber($serialNumber)
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $serial = SerialNumber::where('company_id', $user->current_company_id)
            ->where('serial_number', $serialNumber)
            ->with(['product', 'warehouse', 'purchase', 'invoice.customer'])
            ->first();

        if (!$serial) {
            return response_json(['status' => 'error', 'message' => "Serial Number '{$serialNumber}' not found."], 404);
        }

        $movements = StockMovement::where('company_id', $user->current_company_id)
            ->where('serial_number_id', $serial->id)
            ->orderBy('id', 'asc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => [
                'serial' => $serial,
                'history' => $movements,
            ]
        ]);
    }

    /**
     * Low stock & out of stock alerts.
     */
    public function getLowStockAlerts()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = $user->current_company_id;

        $lowStock = Product::where('company_id', $companyId)
            ->whereRaw('current_stock > 0 AND current_stock <= min_stock_alert')
            ->with('category')
            ->get();

        $outOfStock = Product::where('company_id', $companyId)
            ->where('current_stock', '<=', 0)
            ->with('category')
            ->get();

        return response_json([
            'status' => 'success',
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
        ]);
    }

    /**
     * Expiring batches alerts (<7d, <30d, Expired).
     */
    public function getExpiringBatches()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = $user->current_company_id;
        $today = date('Y-m-d');
        $sevenDays = date('Y-m-d', strtotime('+7 days'));
        $thirtyDays = date('Y-m-d', strtotime('+30 days'));

        $expired = Batch::where('company_id', $companyId)
            ->where('expiry_date', '<', $today)
            ->where('quantity', '>', 0)
            ->with(['product', 'warehouse'])
            ->get();

        $sevenDaysExpiring = Batch::where('company_id', $companyId)
            ->whereBetween('expiry_date', [$today, $sevenDays])
            ->where('quantity', '>', 0)
            ->with(['product', 'warehouse'])
            ->get();

        $thirtyDaysExpiring = Batch::where('company_id', $companyId)
            ->whereBetween('expiry_date', [$sevenDays, $thirtyDays])
            ->where('quantity', '>', 0)
            ->with(['product', 'warehouse'])
            ->get();

        return response_json([
            'status' => 'success',
            'data' => [
                'expired' => $expired,
                'expiring_7_days' => $sevenDaysExpiring,
                'expiring_30_days' => $thirtyDaysExpiring,
                'total_expiring' => count($expired) + count($sevenDaysExpiring) + count($thirtyDaysExpiring),
            ]
        ]);
    }

    /**
     * Inventory Valuation and cost analysis.
     */
    public function getInventoryValuation()
    {
        $user = AuthMiddleware::authorize('inventory', 'view_cost');
        $valuation = InventoryService::getValuationSummary($user->current_company_id);
        return response_json(['status' => 'success', 'data' => $valuation]);
    }

    /**
     * Admin-only Inventory Recalculation & Reconciliation tool.
     */
    public function recalculateInventory()
    {
        $user = AuthMiddleware::authorize('inventory', 'adjust');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $warehouseId = !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null;
        $repair = filter_var($input['repair'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $result = InventoryService::recalculateInventory($companyId, $warehouseId, $repair);

        if ($repair) {
            AuditLogService::log(
                $companyId,
                $user->name,
                'INVENTORY_RECONCILIATION_REPAIR',
                'StockBalance',
                0,
                "Repaired {$result['discrepancies_found']} inventory balance discrepancies from ledger"
            );
        }

        return response_json([
            'status' => 'success',
            'message' => $repair ? 'Discrepancies reconciled and balances repaired.' : 'Verification complete.',
            'data' => $result,
        ]);
    }

    /**
     * Bulk Import Opening Inventory.
     */
    public function importOpeningStock()
    {
        $user = AuthMiddleware::authorize('inventory', 'adjust');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $rows = $input['rows'] ?? [];
        if (empty($rows)) {
            return response_json(['status' => 'error', 'message' => 'No rows provided for import.'], 422);
        }

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($companyId, $branchId, $rows, $user, &$imported, &$errors) {
            foreach ($rows as $idx => $r) {
                $sku = trim($r['sku'] ?? '');
                $prod = Product::where('company_id', $companyId)->where('sku', $sku)->first();

                if (!$prod && !empty($r['product_id'])) {
                    $prod = Product::where('company_id', $companyId)->find(intval($r['product_id']));
                }

                if (!$prod) {
                    $errors[] = "Row #" . ($idx + 1) . ": Product with SKU '{$sku}' not found.";
                    continue;
                }

                $qty = floatval($r['quantity'] ?? 0);
                if ($qty <= 0) {
                    $errors[] = "Row #" . ($idx + 1) . ": Quantity must be greater than 0.";
                    continue;
                }

                $whId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($r['warehouse_id']) ? intval($r['warehouse_id']) : null);
                if ($whId <= 0) {
                    $errors[] = "Row #" . ($idx + 1) . ": No active warehouse found for this company.";
                    continue;
                }
                $cost = floatval($r['unit_cost'] ?? $prod->purchase_price ?: 0);

                InventoryService::recordStockMovement(
                    companyId: $companyId,
                    warehouseId: $whId,
                    productId: $prod->id,
                    movementType: 'OPENING_STOCK',
                    quantity: $qty,
                    direction: 'IN',
                    unitCost: $cost,
                    branchId: $branchId,
                    refType: 'OPENING_BALANCE',
                    refNumber: 'IMPORT-' . date('Ymd'),
                    movementDate: $r['date'] ?? date('Y-m-d'),
                    createdBy: $user->name,
                    notes: 'Bulk opening inventory import'
                );

                $imported++;
            }
        });

        return response_json([
            'status' => 'success',
            'message' => "Successfully imported {$imported} opening stock entries.",
            'imported_count' => $imported,
            'errors' => $errors,
        ]);
    }
}
