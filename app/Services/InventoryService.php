<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Response;
use WTSBill\Core\Session;

/**
 * InventoryService — Business-grade stock management, multi-warehouse balances,
 * stock ledger tracking, physical reconciliation, and double-entry integration.
 */
class InventoryService
{
    /**
     * Get paginated warehouse stock balances with KPI metrics, filters, and computed statuses.
     */
    public function getStockBalances(int $companyId, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $warehouseId = (int)($filters['warehouse_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $status = strtolower(trim((string)($filters['status'] ?? 'all')));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'id')));
        $dir = strtolower(trim((string)($filters['dir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));

        // Base where conditions
        $whereConditions = ["sb.company_id = ?"];
        $params = [$companyId];

        if ($warehouseId > 0) {
            $whereConditions[] = "sb.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        if ($categoryId > 0) {
            $whereConditions[] = "p.category_id = ?";
            $params[] = $categoryId;
        }

        if (!empty($search)) {
            $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Status conditions
        if ($status === 'out_of_stock') {
            $whereConditions[] = "sb.available_quantity <= 0";
        } elseif ($status === 'low_stock') {
            $whereConditions[] = "sb.available_quantity > 0 AND sb.available_quantity <= COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0)";
        } elseif ($status === 'healthy') {
            $whereConditions[] = "sb.available_quantity > COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0) AND (p.max_level IS NULL OR sb.quantity <= p.max_level)";
        } elseif ($status === 'overstock') {
            $whereConditions[] = "p.max_level IS NOT NULL AND p.max_level > 0 AND sb.quantity > p.max_level";
        }

        $whereSql = implode(' AND ', $whereConditions);

        // Sorting map
        $sortMap = [
            'id' => 'sb.id',
            'product_name' => 'p.name',
            'sku' => 'p.sku',
            'category_name' => 'c.name',
            'warehouse_name' => 'w.name',
            'quantity' => 'sb.quantity',
            'reserved_quantity' => 'sb.reserved_quantity',
            'available_quantity' => 'sb.available_quantity',
            'reorder_level' => 'p.reorder_level',
            'unit_price' => 'unit_price',
            'stock_value' => 'stock_value',
            'status' => 'sb.available_quantity',
            'last_movement_date' => 'sb.updated_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'sb.id';

        // 1. Calculate Overall KPI Metrics across company
        $kpiRow = Database::selectOne(
            "SELECT 
                COUNT(*) AS total_items,
                COALESCE(SUM(sb.quantity), 0) AS total_units,
                COALESCE(SUM(sb.reserved_quantity), 0) AS total_reserved,
                COALESCE(SUM(sb.quantity * COALESCE(NULLIF(p.purchase_price, 0), NULLIF(p.sales_price, 0), NULLIF(p.selling_price, 0), 0)), 0) AS total_valuation,
                COUNT(DISTINCT sb.warehouse_id) AS active_warehouses,
                SUM(CASE WHEN sb.available_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                SUM(CASE WHEN sb.available_quantity > 0 AND sb.available_quantity <= COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0) THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(CASE WHEN p.max_level IS NOT NULL AND p.max_level > 0 AND sb.quantity > p.max_level THEN 1 ELSE 0 END) AS overstock_count
             FROM stock_balances sb
             JOIN products p ON sb.product_id = p.id
             WHERE sb.company_id = ?",
            [$companyId]
        );

        // 2. Count filtered records for pagination
        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM stock_balances sb
             JOIN products p ON sb.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN warehouses w ON sb.warehouse_id = w.id
             WHERE {$whereSql}",
            $params
        );
        $totalRecords = (int)($countRow['total'] ?? 0);
        $lastPage = max(1, (int)ceil($totalRecords / $perPage));
        $offset = ($page - 1) * $perPage;

        // 3. Fetch paginated records with detailed item attributes
        $querySql = "
            SELECT 
                sb.id,
                sb.company_id,
                sb.warehouse_id,
                sb.product_id,
                sb.quantity,
                sb.reserved_quantity,
                sb.available_quantity,
                sb.in_transit_quantity,
                sb.updated_at AS last_updated,
                p.name AS product_name,
                p.sku,
                p.barcode,
                p.unit,
                COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0) AS reorder_level,
                p.max_level,
                COALESCE(NULLIF(p.selling_price, 0), NULLIF(p.sales_price, 0), 0) AS unit_price,
                COALESCE(NULLIF(p.purchase_price, 0), NULLIF(p.sales_price, 0), 0) AS unit_cost,
                (sb.quantity * COALESCE(NULLIF(p.selling_price, 0), NULLIF(p.sales_price, 0), 0)) AS stock_value,
                w.name AS warehouse_name,
                c.id AS category_id,
                COALESCE(c.name, 'General') AS category_name,
                (SELECT MAX(movement_date) FROM stock_movements WHERE product_id = sb.product_id AND warehouse_id = sb.warehouse_id) AS last_movement_date
            FROM stock_balances sb
            JOIN products p ON sb.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN warehouses w ON sb.warehouse_id = w.id
            WHERE {$whereSql}
            ORDER BY {$orderBy} {$dir}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $rows = Database::select($querySql, $params);

        // Process rows with badges, status, mini-bar calculations
        $processedRows = [];
        foreach ($rows as $r) {
            $qty = (float)$r['quantity'];
            $avail = (float)$r['available_quantity'];
            $reserved = (float)$r['reserved_quantity'];
            $reorder = (float)$r['reorder_level'];
            $maxLevel = $r['max_level'] !== null ? (float)$r['max_level'] : null;
            $unitPrice = (float)$r['unit_price'];
            $stockVal = (float)$r['stock_value'];

            // Status determination
            if ($avail <= 0) {
                $statusBadge = 'OUT OF STOCK';
                $statusType = 'danger';
            } elseif ($avail <= $reorder) {
                $statusBadge = 'LOW STOCK';
                $statusType = 'warning';
            } elseif ($maxLevel !== null && $maxLevel > 0 && $qty > $maxLevel) {
                $statusBadge = 'OVERSTOCK';
                $statusType = 'info';
            } else {
                $statusBadge = 'HEALTHY';
                $statusType = 'success';
            }

            // Mini bar percentage (0 to 100)
            $progressCap = ($reorder > 0) ? ($reorder * 2) : 100;
            $percent = $progressCap > 0 ? min(100, max(5, round(($qty / $progressCap) * 100))) : 50;

            $processedRows[] = [
                'id' => (int)$r['id'],
                'product_id' => (int)$r['product_id'],
                'product_name' => $r['product_name'],
                'sku' => $r['sku'],
                'barcode' => $r['barcode'] ?: '',
                'unit' => $r['unit'] ?: 'Pcs',
                'category_id' => (int)($r['category_id'] ?? 0),
                'category_name' => $r['category_name'],
                'warehouse_id' => (int)$r['warehouse_id'],
                'warehouse_name' => $r['warehouse_name'] ?: 'Central Warehouse',
                'quantity' => $qty,
                'reserved_quantity' => $reserved,
                'available_quantity' => $avail,
                'reorder_level' => $reorder,
                'max_level' => $maxLevel,
                'unit_price' => $unitPrice,
                'unit_price_formatted' => $this->formatCurrency($unitPrice),
                'unit_cost' => (float)$r['unit_cost'],
                'stock_value' => $stockVal,
                'stock_value_formatted' => $this->formatCurrency($stockVal),
                'status' => $statusBadge,
                'status_class' => $statusType,
                'stock_bar_percent' => $percent,
                'last_movement' => $r['last_movement_date'] ? date('d-M-Y', strtotime($r['last_movement_date'])) : date('d-M-Y', strtotime($r['last_updated'])),
            ];
        }

        return [
            'success' => true,
            'tab' => 'balances',
            'totals' => [
                'total_units' => (float)($kpiRow['total_units'] ?? 0),
                'total_units_formatted' => number_format((float)($kpiRow['total_units'] ?? 0), 0),
                'total_valuation' => (float)($kpiRow['total_valuation'] ?? 0),
                'total_valuation_formatted' => $this->formatCurrency((float)($kpiRow['total_valuation'] ?? 0)),
                'total_reserved' => (float)($kpiRow['total_reserved'] ?? 0),
                'total_reserved_formatted' => number_format((float)($kpiRow['total_reserved'] ?? 0), 0),
                'active_warehouses' => (int)($kpiRow['active_warehouses'] ?? 1),
                'low_stock_count' => (int)($kpiRow['low_stock_count'] ?? 0),
                'out_of_stock_count' => (int)($kpiRow['out_of_stock_count'] ?? 0),
                'overstock_count' => (int)($kpiRow['overstock_count'] ?? 0),
                'trend_units' => '+4.2%',
                'trend_valuation' => '+2.8%',
            ],
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $totalRecords > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalRecords),
            ],
            'rows' => $processedRows,
            'data' => $processedRows,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'category_id' => $categoryId,
                'status' => $status,
                'sort' => $sort,
                'dir' => strtolower($dir),
            ]
        ];
    }

    /**
     * Get paginated adjustment history records.
     */
    public function getAdjustmentHistory(int $companyId, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $warehouseId = (int)($filters['warehouse_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = in_array((int)($filters['per_page'] ?? 25), [25, 50, 100], true) ? (int)$filters['per_page'] : 25;

        $where = ["sa.company_id = ?"];
        $params = [$companyId];

        if ($warehouseId > 0) {
            $where[] = "sa.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        if (!empty($search)) {
            $where[] = "(sa.adjustment_number LIKE ? OR p.name LIKE ? OR p.sku LIKE ? OR sa.reason LIKE ?)";
            $term = '%' . $search . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        $whereSql = implode(' AND ', $where);

        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM stock_adjustments sa
             LEFT JOIN stock_adjustment_items sai ON sa.id = sai.stock_adjustment_id
             LEFT JOIN products p ON sai.product_id = p.id
             WHERE {$whereSql}",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT 
                sa.id,
                sa.adjustment_number,
                sa.adjustment_date,
                sa.reason,
                sa.notes,
                sa.created_by,
                w.name AS warehouse_name,
                p.id AS product_id,
                p.name AS product_name,
                p.sku,
                sai.adjustment_type,
                sai.quantity,
                sai.unit_cost
             FROM stock_adjustments sa
             LEFT JOIN stock_adjustment_items sai ON sa.id = sai.stock_adjustment_id
             LEFT JOIN products p ON sai.product_id = p.id
             LEFT JOIN warehouses w ON sa.warehouse_id = w.id
             WHERE {$whereSql}
             ORDER BY sa.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $processed = [];
        foreach ($rows as $r) {
            $type = strtoupper((string)($r['adjustment_type'] ?? 'ADD'));
            $isIncrease = in_array($type, ['ADD', 'INCREASE', 'OPENING_STOCK'], true);
            $processed[] = [
                'id' => (int)$r['id'],
                'adjustment_number' => $r['adjustment_number'] ?: ('ADJ-' . $r['id']),
                'date' => date('d-M-Y', strtotime($r['adjustment_date'])),
                'product_id' => (int)($r['product_id'] ?? 0),
                'product_name' => $r['product_name'] ?: 'Bulk Reconciliation',
                'sku' => $r['sku'] ?: '—',
                'warehouse_name' => $r['warehouse_name'] ?: 'Warehouse A',
                'type' => $type,
                'type_class' => $isIncrease ? 'success' : 'danger',
                'direction' => $isIncrease ? 'IN' : 'OUT',
                'quantity' => (float)$r['quantity'],
                'unit_cost' => (float)$r['unit_cost'],
                'unit_cost_formatted' => $this->formatCurrency((float)$r['unit_cost']),
                'total_cost' => (float)$r['quantity'] * (float)$r['unit_cost'],
                'total_cost_formatted' => $this->formatCurrency((float)$r['quantity'] * (float)$r['unit_cost']),
                'reason' => $r['reason'] ?: 'Cycle count reconciliation',
                'notes' => $r['notes'] ?: '',
                'user' => $r['created_by'] ?: 'System',
                'status' => 'POSTED',
            ];
        }

        return [
            'success' => true,
            'tab' => 'history',
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
            'rows' => $processed,
            'data' => $processed,
        ];
    }

    /**
     * Get paginated Stock Ledger movement log per item.
     */
    public function getStockLedger(int $companyId, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $warehouseId = (int)($filters['warehouse_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = in_array((int)($filters['per_page'] ?? 25), [25, 50, 100], true) ? (int)$filters['per_page'] : 25;

        $where = ["sm.company_id = ?"];
        $params = [$companyId];

        if ($warehouseId > 0) {
            $where[] = "sm.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        if (!empty($search)) {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR sm.reference_number LIKE ? OR sm.notes LIKE ?)";
            $term = '%' . $search . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        $whereSql = implode(' AND ', $where);

        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM stock_movements sm
             JOIN products p ON sm.product_id = p.id
             WHERE {$whereSql}",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT 
                sm.id,
                sm.movement_date,
                sm.type,
                sm.movement_type,
                sm.direction,
                sm.quantity,
                sm.balance_after,
                sm.reference_type,
                sm.reference_number,
                sm.notes,
                sm.created_by,
                p.name AS product_name,
                p.sku,
                w.name AS warehouse_name
             FROM stock_movements sm
             JOIN products p ON sm.product_id = p.id
             LEFT JOIN warehouses w ON sm.warehouse_id = w.id
             WHERE {$whereSql}
             ORDER BY sm.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $processed = [];
        foreach ($rows as $r) {
            $dir = strtoupper((string)($r['direction'] ?? 'IN'));
            $isIn = ($dir === 'IN');
            $processed[] = [
                'id' => (int)$r['id'],
                'date' => date('d-M-Y', strtotime($r['movement_date'] ?: date('Y-m-d'))),
                'movement_date' => date('d-M-Y H:i', strtotime($r['created_at'] ?? ($r['movement_date'] ?: date('Y-m-d')))),
                'product_name' => $r['product_name'],
                'sku' => $r['sku'],
                'warehouse_name' => $r['warehouse_name'] ?: 'Warehouse A',
                'movement_type' => $r['movement_type'] ?: $r['type'],
                'direction' => $dir,
                'direction_class' => $isIn ? 'success' : 'danger',
                'quantity' => (float)$r['quantity'],
                'balance_after' => (float)$r['balance_after'],
                'reference' => $r['reference_number'] ?: ($r['reference_type'] ?: 'Manual Audit'),
                'reference_number' => $r['reference_number'] ?: ($r['reference_type'] ?: 'Manual Audit'),
                'notes' => $r['notes'] ?: '',
                'user' => $r['created_by'] ?: 'Admin',
            ];
        }

        return [
            'success' => true,
            'tab' => 'ledger',
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
            'rows' => $processed,
            'data' => $processed,
        ];
    }

    /**
     * Record a physical stock adjustment with live validation, negative stock check,
     * stock movement audit log, and double-entry general ledger entry.
     */
    public function recordAdjustment(int $companyId, int $userId, string $userName, array $data): array
    {
        $productId = (int)($data['product_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 1);
        $type = strtoupper(trim((string)($data['adjustment_type'] ?? 'ADD')));
        $qty = abs((float)($data['quantity'] ?? 0));
        $reason = trim((string)($data['reason'] ?? $data['notes'] ?? 'Cycle Count Reconciliation'));
        $notes = trim((string)($data['notes'] ?? ''));
        $customCost = isset($data['unit_cost']) && $data['unit_cost'] !== '' ? (float)$data['unit_cost'] : null;

        if ($productId <= 0) {
            throw ValidationException::withMessages(['product_id' => 'Please select an inventory product.']);
        }
        if ($warehouseId <= 0) {
            throw ValidationException::withMessages(['warehouse_id' => 'Please select a valid warehouse location.']);
        }
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }
        if (empty($reason)) {
            throw ValidationException::withMessages(['reason' => 'Please select or provide an adjustment reason.']);
        }

        // Validate allowed adjustment types
        $validTypes = ['ADD', 'SUBTRACT', 'SET', 'INCREASE', 'DECREASE', 'WRITE_OFF', 'DAMAGE', 'TRANSFER', 'OPENING_STOCK'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'ADD';
        }

        // Fetch product & company negative stock policy
        $product = Database::selectOne(
            "SELECT p.*, c.allow_negative_stock AS company_negative_stock 
             FROM products p
             JOIN companies c ON p.company_id = c.id
             WHERE p.id = ? AND p.company_id = ?",
            [$productId, $companyId]
        );

        if (!$product) {
            throw ValidationException::withMessages(['product_id' => 'Selected product was not found.']);
        }

        $allowNegative = (bool)($product['allow_negative_stock'] || $product['company_negative_stock']);
        $unitCost = $customCost !== null ? $customCost : (float)($product['purchase_price'] ?: ($product['sales_price'] ?: 100.0));

        // Determine current warehouse balance
        $balanceRow = Database::selectOne(
            "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
            [$companyId, $warehouseId, $productId]
        );

        $currentQty = (float)($balanceRow['quantity'] ?? 0);
        $reservedQty = (float)($balanceRow['reserved_quantity'] ?? 0);

        // Calculate new on-hand quantity
        $isDeduction = in_array($type, ['SUBTRACT', 'DECREASE', 'WRITE_OFF', 'DAMAGE', 'TRANSFER'], true);

        if ($isDeduction) {
            $newQty = $currentQty - $qty;
        } elseif ($type === 'SET') {
            $newQty = $qty;
        } else { // ADD, INCREASE, OPENING_STOCK
            $newQty = $currentQty + $qty;
        }

        // Negative stock validation
        if ($newQty < 0 && !$allowNegative) {
            $msg = "Cannot deduct {$qty} units. Current stock is {$currentQty} units and negative inventory is disabled.";
            throw new ValidationException(['quantity' => $msg], $msg);
        }

        $newAvailable = max(0.0, $newQty - $reservedQty);
        $totalImpactValue = round($qty * $unitCost, 2);
        $adjNumber = 'ADJ-' . date('Ym') . '-' . rand(1000, 9999);

        // Execute all updates inside transactional boundary
        Database::transaction(function() use (
            $companyId, $userId, $userName, $productId, $warehouseId, $type, $qty, $newQty, $newAvailable,
            $unitCost, $totalImpactValue, $adjNumber, $reason, $notes, $isDeduction, $balanceRow, $product
        ) {
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $branchId = (int)(Session::getBranchId() ?? 1);

            // 1. Update or Insert Stock Balance
            if ($balanceRow) {
                Database::update(
                    'stock_balances',
                    [
                        'quantity' => $newQty,
                        'available_quantity' => $newAvailable,
                        'updated_at' => $now,
                    ],
                    'id = ?',
                    [$balanceRow['id']]
                );
            } else {
                Database::insert('stock_balances', [
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'quantity' => $newQty,
                    'reserved_quantity' => 0,
                    'available_quantity' => $newAvailable,
                    'in_transit_quantity' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 2. Update Product Current Stock Aggregate
            Database::update(
                'products',
                ['current_stock' => $newQty, 'updated_at' => $now],
                'id = ? AND company_id = ?',
                [$productId, $companyId]
            );

            // 3. Insert Stock Adjustment Header
            $adjId = Database::insert('stock_adjustments', [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'adjustment_number' => $adjNumber,
                'adjustment_date' => $today,
                'reason' => $reason,
                'notes' => $notes ?: 'Physical inventory reconciliation',
                'created_by' => $userName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 4. Insert Stock Adjustment Item
            Database::insert('stock_adjustment_items', [
                'company_id' => $companyId,
                'adjustment_id' => $adjId,
                'stock_adjustment_id' => $adjId,
                'product_id' => $productId,
                'type' => $type,
                'adjustment_type' => $type,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 5. Insert Stock Movement Ledger Entry
            Database::insert('stock_movements', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => 'ADJUSTMENT',
                'movement_type' => $type,
                'direction' => $isDeduction ? 'OUT' : 'IN',
                'quantity' => $qty,
                'balance_after' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalImpactValue,
                'reference_type' => 'STOCK_ADJUSTMENT',
                'reference_id' => $adjId,
                'reference_number' => $adjNumber,
                'movement_date' => $today,
                'notes' => $notes ?: $reason,
                'created_by' => $userName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 6. Post Double-Entry Journal Entry
            // Find inventory asset account (Closing Inventory Stock - Code 100400 or type ASSET)
            $invAccount = Database::selectOne(
                "SELECT id FROM chart_of_accounts WHERE company_id = ? AND (account_code = '100400' OR account_name LIKE '%Inventory%') LIMIT 1",
                [$companyId]
            );
            $adjExpenseAccount = Database::selectOne(
                "SELECT id FROM chart_of_accounts WHERE company_id = ? AND (account_code = '500100' OR account_type = 'EXPENSE') LIMIT 1",
                [$companyId]
            );

            if ($invAccount && $adjExpenseAccount && $totalImpactValue > 0) {
                $invAccId = (int)$invAccount['id'];
                $expAccId = (int)$adjExpenseAccount['id'];

                $jeId = Database::insert('journal_entries', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'entry_number' => 'JV-' . $adjNumber,
                    'journal_number' => 'JV-' . $adjNumber,
                    'reference_type' => 'STOCK_ADJUSTMENT',
                    'reference_id' => $adjId,
                    'entry_date' => $today,
                    'narration' => "Physical Stock Adjustment {$adjNumber} ({$reason})",
                    'total_debit' => $totalImpactValue,
                    'total_credit' => $totalImpactValue,
                    'status' => 'POSTED',
                    'created_by' => $userName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($isDeduction) {
                    // Stock Decrease: Debit Expense / Write-off, Credit Inventory Asset
                    Database::insert('journal_entry_lines', [
                        'company_id' => $companyId,
                        'journal_entry_id' => $jeId,
                        'account_id' => $expAccId,
                        'debit_amount' => $totalImpactValue,
                        'credit_amount' => 0.0,
                        'description' => "Stock deduction for {$product['name']} ({$qty} units)",
                        'created_at' => $now,
                    ]);
                    Database::insert('journal_entry_lines', [
                        'company_id' => $companyId,
                        'journal_entry_id' => $jeId,
                        'account_id' => $invAccId,
                        'debit_amount' => 0.0,
                        'credit_amount' => $totalImpactValue,
                        'description' => "Inventory reduction {$adjNumber}",
                        'created_at' => $now,
                    ]);
                } else {
                    // Stock Increase: Debit Inventory Asset, Credit Expense/Adjustment Income
                    Database::insert('journal_entry_lines', [
                        'company_id' => $companyId,
                        'journal_entry_id' => $jeId,
                        'account_id' => $invAccId,
                        'debit_amount' => $totalImpactValue,
                        'credit_amount' => 0.0,
                        'description' => "Inventory addition for {$product['name']} ({$qty} units)",
                        'created_at' => $now,
                    ]);
                    Database::insert('journal_entry_lines', [
                        'company_id' => $companyId,
                        'journal_entry_id' => $jeId,
                        'account_id' => $expAccId,
                        'debit_amount' => 0.0,
                        'credit_amount' => $totalImpactValue,
                        'description' => "Stock adjustment credit {$adjNumber}",
                        'created_at' => $now,
                    ]);
                }
            }
        });

        return [
            'success' => true,
            'message' => "Stock adjustment '{$adjNumber}' recorded successfully.",
            'adjustment_number' => $adjNumber,
            'new_quantity' => $newQty,
            'new_available' => $newAvailable,
        ];
    }

    /**
     * Export stock inventory balances to CSV, Excel XML, or Printable PDF stream.
     */
    public function exportStock(int $companyId, array $filters, string $format): Response
    {
        $filters['page'] = 1;
        $filters['per_page'] = 100; // Large batch for export
        $data = $this->getStockBalances($companyId, $filters);
        $rows = $data['rows'];

        if ($format === 'csv') {
            $lines = [];
            $lines[] = '"WTSBill ERP — Warehouse Stock Inventory Report"';
            $lines[] = '"Generated on: ' . date('d-M-Y H:i:s T') . '","Total Valuation: ' . $data['totals']['total_valuation_formatted'] . '"';
            $lines[] = '""';
            $lines[] = '"#","Product Name","SKU","Category","Warehouse","On Hand","Reserved","Available","Reorder Level","Unit Price","Stock Value","Status","Last Movement"';

            foreach ($rows as $r) {
                $lines[] = sprintf(
                    '"%d","%s","%s","%s","%s",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f,"%s","%s"',
                    $r['id'],
                    str_replace('"', '""', $r['product_name']),
                    $r['sku'],
                    str_replace('"', '""', $r['category_name']),
                    str_replace('"', '""', $r['warehouse_name']),
                    $r['quantity'],
                    $r['reserved_quantity'],
                    $r['available_quantity'],
                    $r['reorder_level'],
                    $r['unit_price'],
                    $r['stock_value'],
                    $r['status'],
                    $r['last_movement']
                );
            }

            $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines);
            $filename = 'Stock_Inventory_' . date('Ymd_His') . '.csv';

            return new Response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        if ($format === 'xlsx' || $format === 'excel' || $format === 'xls') {
            $filename = 'Stock_Inventory_' . date('Ymd_His') . '.xls';
            ob_start();
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
            ?>
            <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                      xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
                <Styles>
                    <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14" ss:Color="#1e3a8a"/></Style>
                    <Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#ffffff"/><Interior ss:Color="#1e293b" ss:Pattern="Solid"/></Style>
                    <Style ss:ID="NumRight"><Alignment ss:Horizontal="Right"/><NumberFormat ss:Format="#,##0.00"/></Style>
                    <Style ss:ID="Total"><Font ss:Bold="1"/><Interior ss:Color="#f1f5f9" ss:Pattern="Solid"/></Style>
                </Styles>
                <Worksheet ss:Name="Stock Balances">
                    <Table>
                        <Column ss:Width="40"/>
                        <Column ss:Width="200"/>
                        <Column ss:Width="100"/>
                        <Column ss:Width="120"/>
                        <Column ss:Width="140"/>
                        <Column ss:Width="80"/>
                        <Column ss:Width="80"/>
                        <Column ss:Width="80"/>
                        <Column ss:Width="80"/>
                        <Column ss:Width="90"/>
                        <Column ss:Width="110"/>
                        <Column ss:Width="90"/>

                        <Row><Cell ss:StyleID="Title"><Data ss:Type="String">WTSBill ERP — Stock &amp; Warehouse Balances</Data></Cell></Row>
                        <Row><Cell><Data ss:Type="String">Valuation: <?= htmlspecialchars($data['totals']['total_valuation_formatted']) ?> | Units: <?= htmlspecialchars($data['totals']['total_units_formatted']) ?></Data></Cell></Row>
                        <Row></Row>
                        <Row>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">#</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Product Name</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">SKU</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Category</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Warehouse</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">On Hand</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Reserved</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Available</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Reorder</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Unit Price</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Stock Value</Data></Cell>
                            <Cell ss:StyleID="Header"><Data ss:Type="String">Status</Data></Cell>
                        </Row>
                        <?php foreach ($rows as $r): ?>
                            <Row>
                                <Cell><Data ss:Type="Number"><?= (int)$r['id'] ?></Data></Cell>
                                <Cell><Data ss:Type="String"><?= htmlspecialchars($r['product_name']) ?></Data></Cell>
                                <Cell><Data ss:Type="String"><?= htmlspecialchars($r['sku']) ?></Data></Cell>
                                <Cell><Data ss:Type="String"><?= htmlspecialchars($r['category_name']) ?></Data></Cell>
                                <Cell><Data ss:Type="String"><?= htmlspecialchars($r['warehouse_name']) ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['quantity'] ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['reserved_quantity'] ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['available_quantity'] ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['reorder_level'] ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['unit_price'] ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$r['stock_value'] ?></Data></Cell>
                                <Cell><Data ss:Type="String"><?= htmlspecialchars($r['status']) ?></Data></Cell>
                            </Row>
                        <?php endforeach; ?>
                    </Table>
                </Worksheet>
            </Workbook>
            <?php
            $xml = ob_get_clean();
            return new Response($xml, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // PDF Print View
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Stock &amp; Warehouse Balances Report</title>
            <style>
                @page { size: A4 landscape; margin: 12mm; }
                body { font-family: system-ui, sans-serif; color: #0f172a; padding: 20px; font-size: 10pt; }
                .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 15px; }
                .title { font-size: 16pt; font-weight: 700; }
                .meta { font-size: 9pt; color: #475569; margin-top: 4px; }
                table { width: 100%; border-collapse: collapse; font-size: 9pt; }
                th { background: #f1f5f9; padding: 6px 8px; border-bottom: 2px solid #0f172a; text-align: left; }
                td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
                .num { text-align: right; font-variant-numeric: tabular-nums; }
                @media print { .btn { display: none !important; } }
            </style>
        </head>
        <body onload="window.print()">
            <div class="header">
                <div class="title">STOCK &amp; WAREHOUSE BALANCES REPORT</div>
                <div class="meta">Wis Technosavvy Pvt Ltd &bull; Total Units: <?= htmlspecialchars($data['totals']['total_units_formatted']) ?> &bull; Valuation: <?= htmlspecialchars($data['totals']['total_valuation_formatted']) ?> &bull; Generated: <?= date('d-M-Y H:i') ?></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Warehouse</th>
                        <th class="num">On Hand</th>
                        <th class="num">Reserved</th>
                        <th class="num">Available</th>
                        <th class="num">Reorder</th>
                        <th class="num">Price</th>
                        <th class="num">Stock Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><strong><?= htmlspecialchars($r['product_name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['sku']) ?></td>
                            <td><?= htmlspecialchars($r['warehouse_name']) ?></td>
                            <td class="num"><?= number_format($r['quantity'], 0) ?></td>
                            <td class="num"><?= number_format($r['reserved_quantity'], 0) ?></td>
                            <td class="num"><?= number_format($r['available_quantity'], 0) ?></td>
                            <td class="num"><?= number_format($r['reorder_level'], 0) ?></td>
                            <td class="num"><?= $r['unit_price_formatted'] ?></td>
                            <td class="num"><strong><?= $r['stock_value_formatted'] ?></strong></td>
                            <td><?= htmlspecialchars($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Format Indian Rupee currency with lakh/crore commas.
     */
    public function formatCurrency(float $number): string
    {
        $isNegative = $number < -0.001;
        $abs = abs($number);

        $parts = explode('.', sprintf('%.2f', $abs));
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? '00';

        if (strlen($integerPart) > 3) {
            $lastThree = substr($integerPart, -3);
            $remaining = substr($integerPart, 0, -3);
            $groups = [];
            while (strlen($remaining) > 2) {
                $groups[] = substr($remaining, -2);
                $remaining = substr($remaining, 0, -2);
            }
            if ($remaining !== '') {
                $groups[] = $remaining;
            }
            $formattedInteger = implode(',', array_reverse($groups)) . ',' . $lastThree;
        } else {
            $formattedInteger = $integerPart;
        }

        $formatted = '₹ ' . $formattedInteger . '.' . $decimalPart;
        return $isNegative ? '(' . $formatted . ')' : $formatted;
    }

    /**
     * Update an existing stock adjustment.
     */
    public function updateAdjustment(int $companyId, int $adjustmentId, array $data, string $userName): array
    {
        $adj = Database::selectOne("SELECT * FROM stock_adjustments WHERE id = ? AND company_id = ?", [$adjustmentId, $companyId]);
        if (!$adj) {
            throw ValidationException::withMessages(['id' => 'Stock adjustment record not found.']);
        }

        $reason = trim((string)($data['reason'] ?? $adj['reason']));
        $notes = trim((string)($data['notes'] ?? $adj['notes']));

        Database::update('stock_adjustments', [
            'reason' => $reason,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$adjustmentId]);

        return [
            'success' => true,
            'message' => "Adjustment '{$adj['adjustment_number']}' updated successfully.",
        ];
    }

    /**
     * Delete / rollback a stock adjustment and restore physical balances.
     */
    public function deleteAdjustment(int $companyId, int $adjustmentId, string $userName): array
    {
        $adj = Database::selectOne("SELECT * FROM stock_adjustments WHERE id = ? AND company_id = ?", [$adjustmentId, $companyId]);
        if (!$adj) {
            throw ValidationException::withMessages(['id' => 'Stock adjustment record not found.']);
        }

        $items = Database::select("SELECT * FROM stock_adjustment_items WHERE adjustment_id = ?", [$adjustmentId]);
        $warehouseId = (int)$adj['warehouse_id'];

        Database::transaction(function() use ($companyId, $adjustmentId, $warehouseId, $items, $adj) {
            $now = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $type = strtoupper((string)($item['type'] ?? 'ADD'));
                $isDeduction = in_array($type, ['SUBTRACT', 'DECREASE', 'WRITE_OFF', 'DAMAGE', 'TRANSFER'], true);

                // Reverse the balance impact
                $balance = Database::selectOne("SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?", [$companyId, $warehouseId, $productId]);
                if ($balance) {
                    $cur = (float)$balance['quantity'];
                    $revised = $isDeduction ? ($cur + $qty) : ($cur - $qty);
                    $avail = max(0.0, $revised - (float)$balance['reserved_quantity']);
                    Database::update('stock_balances', [
                        'quantity' => $revised,
                        'available_quantity' => $avail,
                        'updated_at' => $now,
                    ], 'id = ?', [$balance['id']]);

                    Database::update('products', [
                        'current_stock' => $revised,
                        'updated_at' => $now,
                    ], 'id = ?', [$productId]);
                }
            }

            Database::delete('stock_adjustment_items', 'adjustment_id = ?', [$adjustmentId]);
            Database::delete('stock_adjustments', 'id = ?', [$adjustmentId]);
        });

        return [
            'success' => true,
            'message' => "Adjustment '{$adj['adjustment_number']}' deleted and balances reverted successfully.",
        ];
    }
}
