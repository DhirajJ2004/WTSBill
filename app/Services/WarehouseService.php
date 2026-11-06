<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;

/**
 * Warehouse and Stock Transfer Management Service.
 * Manages multi-godown tracking, inter-warehouse & inter-branch logistics, stock velocity, and low-stock alerts.
 */
class WarehouseService
{
    /**
     * Retrieve all active warehouses for a company with stock stats.
     */
    public function getWarehouses(int $companyId, array $filters = []): array
    {
        $params = [$companyId];
        $where = "w.company_id = ? AND w.deleted_at IS NULL";

        if (!empty($filters['branch_id'])) {
            $where .= " AND w.branch_id = ?";
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (w.name LIKE ? OR w.code LIKE ? OR w.city LIKE ? OR w.manager_name LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT 
                w.*,
                b.name AS branch_name,
                COALESCE(stats.product_count, 0) AS product_count,
                COALESCE(stats.total_quantity, 0) AS total_quantity,
                COALESCE(stats.total_valuation, 0) AS total_valuation
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            LEFT JOIN (
                SELECT 
                    sb.warehouse_id,
                    COUNT(DISTINCT sb.product_id) AS product_count,
                    SUM(sb.quantity) AS total_quantity,
                    SUM(sb.quantity * COALESCE(NULLIF(p.purchase_price, 0), NULLIF(p.sales_price, 0), 0)) AS total_valuation
                FROM stock_balances sb
                JOIN products p ON sb.product_id = p.id
                WHERE sb.company_id = ?
                GROUP BY sb.warehouse_id
            ) stats ON stats.warehouse_id = w.id
            WHERE {$where}
            ORDER BY w.is_primary DESC, w.name ASC
        ";

        array_unshift($params, $companyId);

        return Database::select($sql, $params);
    }

    /**
     * Retrieve single warehouse details.
     */
    public function getWarehouse(int $companyId, int $warehouseId): ?array
    {
        $sql = "
            SELECT w.*, b.name AS branch_name
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            WHERE w.id = ? AND w.company_id = ? AND w.deleted_at IS NULL
        ";
        $row = Database::selectOne($sql, [$warehouseId, $companyId]);
        return $row ?: null;
    }

    /**
     * Create a new warehouse.
     */
    public function createWarehouse(int $companyId, array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            throw new ValidationException(['name' => 'Warehouse name is required.']);
        }

        $code = strtoupper(trim((string)($data['code'] ?? '')));
        if (empty($code)) {
            $code = 'WH-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        }

        // Check unique code
        $exists = Database::selectOne(
            "SELECT id FROM warehouses WHERE company_id = ? AND (name = ? OR code = ?) AND deleted_at IS NULL",
            [$companyId, $name, $code]
        );
        if ($exists) {
            throw new ValidationException(['code' => 'A warehouse with this name or code already exists.']);
        }

        $isPrimary = !empty($data['is_primary']) ? 1 : 0;
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isPrimary === 1) {
            Database::update('warehouses', ['is_primary' => 0], 'company_id = ?', [$companyId]);
        }
        if ($isDefault === 1) {
            Database::update('warehouses', ['is_default' => 0], 'company_id = ?', [$companyId]);
        }

        $now = date('Y-m-d H:i:s');
        return (int)Database::insert('warehouses', [
            'company_id' => $companyId,
            'branch_id' => !empty($data['branch_id']) ? (int)$data['branch_id'] : 1,
            'name' => $name,
            'code' => $code,
            'city' => trim((string)($data['city'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'state' => trim((string)($data['state'] ?? '')),
            'pincode' => trim((string)($data['pincode'] ?? '')),
            'manager_name' => trim((string)($data['manager_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'is_primary' => $isPrimary,
            'is_default' => $isDefault,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Update an existing warehouse.
     */
    public function updateWarehouse(int $companyId, int $warehouseId, array $data): bool
    {
        $existing = $this->getWarehouse($companyId, $warehouseId);
        if (!$existing) {
            throw new ValidationException(['id' => 'Warehouse not found.']);
        }

        $name = trim((string)($data['name'] ?? $existing['name']));
        $code = strtoupper(trim((string)($data['code'] ?? $existing['code'])));

        $duplicate = Database::selectOne(
            "SELECT id FROM warehouses WHERE company_id = ? AND (name = ? OR code = ?) AND id != ? AND deleted_at IS NULL",
            [$companyId, $name, $code, $warehouseId]
        );
        if ($duplicate) {
            throw new ValidationException(['code' => 'Another warehouse with this name or code already exists.']);
        }

        $isPrimary = isset($data['is_primary']) ? (int)$data['is_primary'] : (int)$existing['is_primary'];
        $isDefault = isset($data['is_default']) ? (int)$data['is_default'] : (int)$existing['is_default'];

        if ($isPrimary === 1) {
            Database::update('warehouses', ['is_primary' => 0], 'company_id = ? AND id != ?', [$companyId, $warehouseId]);
        }
        if ($isDefault === 1) {
            Database::update('warehouses', ['is_default' => 0], 'company_id = ? AND id != ?', [$companyId, $warehouseId]);
        }

        $update = [
            'name' => $name,
            'code' => $code,
            'branch_id' => isset($data['branch_id']) ? (int)$data['branch_id'] : $existing['branch_id'],
            'city' => trim((string)($data['city'] ?? $existing['city'])),
            'address' => trim((string)($data['address'] ?? $existing['address'])),
            'state' => trim((string)($data['state'] ?? $existing['state'])),
            'pincode' => trim((string)($data['pincode'] ?? $existing['pincode'])),
            'manager_name' => trim((string)($data['manager_name'] ?? $existing['manager_name'])),
            'phone' => trim((string)($data['phone'] ?? $existing['phone'])),
            'is_primary' => $isPrimary,
            'is_default' => $isDefault,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : (int)$existing['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::update('warehouses', $update, 'id = ? AND company_id = ?', [$warehouseId, $companyId]);
        return true;
    }

    /**
     * Delete warehouse (soft-delete with safety checks).
     */
    public function deleteWarehouse(int $companyId, int $warehouseId): bool
    {
        $existing = $this->getWarehouse($companyId, $warehouseId);
        if (!$existing) {
            throw new ValidationException(['id' => 'Warehouse not found.']);
        }

        if ((int)$existing['is_primary'] === 1) {
            throw new ValidationException(['error' => 'Cannot delete the primary warehouse. Please set another warehouse as primary first.']);
        }

        // Check if warehouse has any positive stock
        $stockCheck = Database::selectOne(
            "SELECT SUM(quantity) AS total_qty FROM stock_balances WHERE company_id = ? AND warehouse_id = ?",
            [$companyId, $warehouseId]
        );
        $totalQty = (float)($stockCheck['total_qty'] ?? 0);
        if ($totalQty > 0) {
            throw new ValidationException(['error' => "Cannot delete warehouse '{$existing['name']}' because it holds {$totalQty} units of stock. Please transfer all stock before deleting."]);
        }

        // Check active pending transfers
        $pendingTransfers = Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM stock_transfers 
             WHERE company_id = ? AND (from_warehouse_id = ? OR to_warehouse_id = ?) 
             AND status IN ('PENDING', 'APPROVED', 'DISPATCHED')",
            [$companyId, $warehouseId, $warehouseId]
        );
        if ((int)($pendingTransfers['cnt'] ?? 0) > 0) {
            throw new ValidationException(['error' => "Cannot delete warehouse '{$existing['name']}' because active stock transfers are currently in progress."]);
        }

        Database::update(
            'warehouses',
            ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0],
            'id = ? AND company_id = ?',
            [$warehouseId, $companyId]
        );

        return true;
    }

    /**
     * Get stock inventory for a single warehouse.
     */
    public function getWarehouseStock(int $companyId, int $warehouseId, array $filters = []): array
    {
        $params = [$companyId, $warehouseId];
        $where = "sb.company_id = ? AND sb.warehouse_id = ? AND p.deleted_at IS NULL";

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.hsn_sac LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT 
                sb.*,
                p.name AS product_name,
                p.sku,
                p.unit,
                p.hsn_sac,
                COALESCE(p.purchase_price, 0) AS unit_cost,
                COALESCE(p.sales_price, p.selling_price, 0) AS unit_price,
                (sb.quantity * COALESCE(p.purchase_price, p.sales_price, 0)) AS stock_value,
                p.reorder_level,
                c.name AS category_name
            FROM stock_balances sb
            JOIN products p ON sb.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$where}
            ORDER BY p.name ASC
        ";

        return Database::select($sql, $params);
    }

    /**
     * Generate sequential transfer number (TR-YYYY-XXXX).
     */
    public function generateTransferNumber(int $companyId): string
    {
        $year = date('Y');
        $prefix = "TR-{$year}-";

        $latest = Database::selectOne(
            "SELECT transfer_number FROM stock_transfers WHERE company_id = ? AND transfer_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$companyId, "{$prefix}%"]
        );

        $nextSeq = 1;
        if ($latest && preg_match('/TR-\d{4}-(\d+)/', (string)$latest['transfer_number'], $matches)) {
            $nextSeq = (int)$matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $nextSeq);
    }

    /**
     * Get list of stock transfers with filters.
     */
    public function getTransfers(int $companyId, array $filters = []): array
    {
        $params = [$companyId];
        $where = "st.company_id = ?";

        if (!empty($filters['status']) && strtoupper((string)$filters['status']) !== 'ALL') {
            $where .= " AND st.status = ?";
            $params[] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['warehouse_id'])) {
            $where .= " AND (st.from_warehouse_id = ? OR st.to_warehouse_id = ?)";
            $params[] = (int)$filters['warehouse_id'];
            $params[] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (st.transfer_number LIKE ? OR st.reference_no LIKE ? OR st.notes LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT 
                st.*,
                fw.name AS from_warehouse_name,
                fw.code AS from_warehouse_code,
                tw.name AS to_warehouse_name,
                tw.code AS to_warehouse_code,
                COALESCE(items.total_items, 0) AS total_items,
                COALESCE(items.total_quantity, 0) AS total_quantity
            FROM stock_transfers st
            LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.id
            LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.id
            LEFT JOIN (
                SELECT 
                    transfer_id,
                    COUNT(*) AS total_items,
                    SUM(quantity) AS total_quantity
                FROM stock_transfer_items
                WHERE company_id = ?
                GROUP BY transfer_id
            ) items ON items.transfer_id = st.id
            WHERE {$where}
            ORDER BY st.id DESC
        ";

        array_unshift($params, $companyId);

        return Database::select($sql, $params);
    }

    /**
     * Get single transfer details with items.
     */
    public function getTransfer(int $companyId, int $transferId): ?array
    {
        $sql = "
            SELECT 
                st.*,
                fw.name AS from_warehouse_name,
                fw.code AS from_warehouse_code,
                fw.city AS from_city,
                tw.name AS to_warehouse_name,
                tw.code AS to_warehouse_code,
                tw.city AS to_city,
                fb.name AS from_branch_name,
                tb.name AS to_branch_name
            FROM stock_transfers st
            LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.id
            LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.id
            LEFT JOIN branches fb ON st.from_branch_id = fb.id
            LEFT JOIN branches tb ON st.to_branch_id = tb.id
            WHERE st.id = ? AND st.company_id = ?
        ";

        $transfer = Database::selectOne($sql, [$transferId, $companyId]);
        if (!$transfer) {
            return null;
        }

        $itemsSql = "
            SELECT 
                sti.*,
                p.name AS product_name,
                p.sku,
                p.hsn_sac,
                COALESCE(p.purchase_price, 0) AS unit_cost
            FROM stock_transfer_items sti
            JOIN products p ON sti.product_id = p.id
            WHERE sti.transfer_id = ? AND sti.company_id = ?
            ORDER BY sti.id ASC
        ";
        $transfer['items'] = Database::select($itemsSql, [$transferId, $companyId]);

        return $transfer;
    }

    /**
     * Create stock transfer order.
     */
    public function createTransfer(int $companyId, array $data, string $userName): int
    {
        $fromWhId = (int)($data['from_warehouse_id'] ?? 0);
        $toWhId = (int)($data['to_warehouse_id'] ?? 0);

        if ($fromWhId <= 0 || $toWhId <= 0) {
            throw new ValidationException(['warehouse' => 'Both source and destination warehouses are required.']);
        }

        if ($fromWhId === $toWhId) {
            throw new ValidationException(['to_warehouse_id' => 'Source and destination warehouses cannot be the same.']);
        }

        $fromWh = $this->getWarehouse($companyId, $fromWhId);
        $toWh = $this->getWarehouse($companyId, $toWhId);

        if (!$fromWh || !$toWh) {
            throw new ValidationException(['warehouse' => 'Specified warehouse does not exist.']);
        }

        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            throw new ValidationException(['items' => 'At least one item must be included in the transfer.']);
        }

        $transferNumber = $this->generateTransferNumber($companyId);
        $transferDate = !empty($data['transfer_date']) ? (string)$data['transfer_date'] : date('Y-m-d');
        $referenceNo = trim((string)($data['reference_no'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $initialStatus = !empty($data['auto_approve']) ? 'APPROVED' : 'PENDING';

        return Database::transaction(function() use (
            $companyId, $fromWhId, $toWhId, $fromWh, $toWh, $transferNumber,
            $transferDate, $referenceNo, $notes, $initialStatus, $userName, $items
        ) {
            $now = date('Y-m-d H:i:s');

            $transferId = (int)Database::insert('stock_transfers', [
                'company_id' => $companyId,
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $fromWhId,
                'to_warehouse_id' => $toWhId,
                'from_branch_id' => (int)($fromWh['branch_id'] ?? 1),
                'to_branch_id' => (int)($toWh['branch_id'] ?? 1),
                'transfer_date' => $transferDate,
                'status' => $initialStatus,
                'reference_no' => $referenceNo,
                'notes' => $notes,
                'created_by' => $userName,
                'approved_by' => $initialStatus === 'APPROVED' ? $userName : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (float)($item['quantity'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                // Check stock availability in source warehouse
                $stockRow = Database::selectOne(
                    "SELECT available_quantity, quantity FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                    [$companyId, $fromWhId, $productId]
                );
                $available = (float)($stockRow['available_quantity'] ?? $stockRow['quantity'] ?? 0);
                if ($available < $qty) {
                    $prod = Database::selectOne("SELECT name FROM products WHERE id = ?", [$productId]);
                    $prodName = $prod['name'] ?? "Product #{$productId}";
                    throw new ValidationException(['quantity' => "Insufficient stock for '{$prodName}' at source warehouse. Available: {$available}, Requested: {$qty}."]);
                }

                Database::insert('stock_transfer_items', [
                    'company_id' => $companyId,
                    'transfer_id' => $transferId,
                    'stock_transfer_id' => $transferId,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'quantity_sent' => $qty,
                    'quantity_received' => 0,
                    'unit' => trim((string)($item['unit'] ?? 'PCS')),
                    'notes' => trim((string)($item['notes'] ?? '')),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return (int)$transferId;
        });
    }

    /**
     * Approve stock transfer.
     */
    public function approveTransfer(int $companyId, int $transferId, string $userName): bool
    {
        $transfer = $this->getTransfer($companyId, $transferId);
        if (!$transfer) {
            throw new ValidationException(['id' => 'Transfer order not found.']);
        }

        if ($transfer['status'] !== 'PENDING') {
            throw new ValidationException(['status' => "Cannot approve transfer with status '{$transfer['status']}'."]);
        }

        Database::update(
            'stock_transfers',
            [
                'status' => 'APPROVED',
                'approved_by' => $userName,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ? AND company_id = ?',
            [$transferId, $companyId]
        );

        return true;
    }

    /**
     * Dispatch stock transfer (deduct from source warehouse, mark in-transit).
     */
    public function dispatchTransfer(int $companyId, int $transferId, string $userName): bool
    {
        $transfer = $this->getTransfer($companyId, $transferId);
        if (!$transfer) {
            throw new ValidationException(['id' => 'Transfer order not found.']);
        }

        if (!in_array($transfer['status'], ['PENDING', 'APPROVED'], true)) {
            throw new ValidationException(['status' => "Cannot dispatch transfer with status '{$transfer['status']}'."]);
        }

        return Database::transaction(function() use ($companyId, $transferId, $transfer, $userName) {
            $now = date('Y-m-d H:i:s');
            $fromWhId = (int)$transfer['from_warehouse_id'];
            $toWhId = (int)$transfer['to_warehouse_id'];

            foreach ($transfer['items'] as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];

                // Deduct from source warehouse
                $sourceBalance = Database::selectOne(
                    "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                    [$companyId, $fromWhId, $productId]
                );

                if ($sourceBalance) {
                    $newQty = max(0, (float)$sourceBalance['quantity'] - $qty);
                    $newAvail = max(0, (float)$sourceBalance['available_quantity'] - $qty);
                    Database::update(
                        'stock_balances',
                        ['quantity' => $newQty, 'available_quantity' => $newAvail, 'updated_at' => $now],
                        'id = ?',
                        [$sourceBalance['id']]
                    );
                }

                // Add to destination warehouse in_transit_quantity
                $destBalance = Database::selectOne(
                    "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                    [$companyId, $toWhId, $productId]
                );

                if ($destBalance) {
                    $newTransit = (float)$destBalance['in_transit_quantity'] + $qty;
                    Database::update(
                        'stock_balances',
                        ['in_transit_quantity' => $newTransit, 'updated_at' => $now],
                        'id = ?',
                        [$destBalance['id']]
                    );
                } else {
                    Database::insert('stock_balances', [
                        'company_id' => $companyId,
                        'warehouse_id' => $toWhId,
                        'branch_id' => (int)($transfer['to_branch_id'] ?? 1),
                        'product_id' => $productId,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'available_quantity' => 0,
                        'in_transit_quantity' => $qty,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            Database::update(
                'stock_transfers',
                [
                    'status' => 'DISPATCHED',
                    'dispatched_by' => $userName,
                    'approved_by' => $transfer['approved_by'] ?: $userName,
                    'updated_at' => $now,
                ],
                'id = ? AND company_id = ?',
                [$transferId, $companyId]
            );

            return true;
        });
    }

    /**
     * Receive stock transfer (add to destination warehouse stock, clear in-transit).
     */
    public function receiveTransfer(int $companyId, int $transferId, string $userName): bool
    {
        $transfer = $this->getTransfer($companyId, $transferId);
        if (!$transfer) {
            throw new ValidationException(['id' => 'Transfer order not found.']);
        }

        if ($transfer['status'] !== 'DISPATCHED') {
            throw new ValidationException(['status' => "Cannot receive transfer with status '{$transfer['status']}'. It must be DISPATCHED first."]);
        }

        return Database::transaction(function() use ($companyId, $transferId, $transfer, $userName) {
            $now = date('Y-m-d H:i:s');
            $toWhId = (int)$transfer['to_warehouse_id'];

            foreach ($transfer['items'] as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];

                $destBalance = Database::selectOne(
                    "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                    [$companyId, $toWhId, $productId]
                );

                if ($destBalance) {
                    $newTransit = max(0, (float)$destBalance['in_transit_quantity'] - $qty);
                    $newQty = (float)$destBalance['quantity'] + $qty;
                    $newAvail = (float)$destBalance['available_quantity'] + $qty;

                    Database::update(
                        'stock_balances',
                        [
                            'quantity' => $newQty,
                            'available_quantity' => $newAvail,
                            'in_transit_quantity' => $newTransit,
                            'updated_at' => $now,
                        ],
                        'id = ?',
                        [$destBalance['id']]
                    );
                } else {
                    Database::insert('stock_balances', [
                        'company_id' => $companyId,
                        'warehouse_id' => $toWhId,
                        'branch_id' => (int)($transfer['to_branch_id'] ?? 1),
                        'product_id' => $productId,
                        'quantity' => $qty,
                        'reserved_quantity' => 0,
                        'available_quantity' => $qty,
                        'in_transit_quantity' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Update item received quantity
                Database::update(
                    'stock_transfer_items',
                    ['quantity_received' => $qty, 'updated_at' => $now],
                    'transfer_id = ? AND product_id = ?',
                    [$transferId, $productId]
                );
            }

            Database::update(
                'stock_transfers',
                [
                    'status' => 'RECEIVED',
                    'received_by' => $userName,
                    'updated_at' => $now,
                ],
                'id = ? AND company_id = ?',
                [$transferId, $companyId]
            );

            return true;
        });
    }

    /**
     * Cancel transfer and revert stock if already dispatched.
     */
    public function cancelTransfer(int $companyId, int $transferId, string $userName, string $reason = ''): bool
    {
        $transfer = $this->getTransfer($companyId, $transferId);
        if (!$transfer) {
            throw new ValidationException(['id' => 'Transfer order not found.']);
        }

        if ($transfer['status'] === 'RECEIVED') {
            throw new ValidationException(['status' => 'Cannot cancel a transfer that has already been fully received.']);
        }
        if ($transfer['status'] === 'CANCELLED') {
            throw new ValidationException(['status' => 'Transfer is already cancelled.']);
        }

        return Database::transaction(function() use ($companyId, $transferId, $transfer, $userName, $reason) {
            $now = date('Y-m-d H:i:s');

            // If it was already dispatched, restore stock to source and remove from transit
            if ($transfer['status'] === 'DISPATCHED') {
                $fromWhId = (int)$transfer['from_warehouse_id'];
                $toWhId = (int)$transfer['to_warehouse_id'];

                foreach ($transfer['items'] as $item) {
                    $productId = (int)$item['product_id'];
                    $qty = (float)$item['quantity'];

                    // Restore source
                    $sourceBalance = Database::selectOne(
                        "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                        [$companyId, $fromWhId, $productId]
                    );
                    if ($sourceBalance) {
                        Database::update(
                            'stock_balances',
                            [
                                'quantity' => (float)$sourceBalance['quantity'] + $qty,
                                'available_quantity' => (float)$sourceBalance['available_quantity'] + $qty,
                                'updated_at' => $now,
                            ],
                            'id = ?',
                            [$sourceBalance['id']]
                        );
                    }

                    // Remove transit from destination
                    $destBalance = Database::selectOne(
                        "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                        [$companyId, $toWhId, $productId]
                    );
                    if ($destBalance) {
                        Database::update(
                            'stock_balances',
                            [
                                'in_transit_quantity' => max(0, (float)$destBalance['in_transit_quantity'] - $qty),
                                'updated_at' => $now,
                            ],
                            'id = ?',
                            [$destBalance['id']]
                        );
                    }
                }
            }

            Database::update(
                'stock_transfers',
                [
                    'status' => 'CANCELLED',
                    'cancelled_by' => $userName,
                    'notes' => trim($transfer['notes'] . ($reason ? " [Cancellation reason: {$reason}]" : '')),
                    'updated_at' => $now,
                ],
                'id = ? AND company_id = ?',
                [$transferId, $companyId]
            );

            return true;
        });
    }

    /**
     * Product Velocity Analysis (Fast-Moving, Slow-Moving, Non-Moving).
     */
    public function getStockVelocity(int $companyId, int $days = 30): array
    {
        $sinceDate = date('Y-m-d', strtotime("-{$days} days"));

        $sql = "
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.current_stock,
                p.reorder_level,
                c.name AS category_name,
                COALESCE(sales.sold_units, 0) AS units_sold,
                COALESCE(sales.total_revenue, 0) AS total_revenue
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT 
                    ii.product_id,
                    SUM(ii.quantity) AS sold_units,
                    SUM(ii.total_amount) AS total_revenue
                FROM invoice_items ii
                JOIN invoices inv ON ii.invoice_id = inv.id
                WHERE inv.company_id = ? AND inv.invoice_date >= ?
                GROUP BY ii.product_id
            ) sales ON sales.product_id = p.id
            WHERE p.company_id = ? AND p.deleted_at IS NULL
            ORDER BY units_sold DESC, p.name ASC
        ";

        $products = Database::select($sql, [$companyId, $sinceDate, $companyId]);

        $fast = [];
        $slow = [];
        $nonMoving = [];

        foreach ($products as $p) {
            $sold = (float)$p['units_sold'];
            if ($sold > 10) {
                $p['velocity_category'] = 'FAST';
                $fast[] = $p;
            } elseif ($sold > 0) {
                $p['velocity_category'] = 'SLOW';
                $slow[] = $p;
            } else {
                $p['velocity_category'] = 'NON_MOVING';
                $nonMoving[] = $p;
            }
        }

        return [
            'period_days' => $days,
            'summary' => [
                'total_products' => count($products),
                'fast_moving_count' => count($fast),
                'slow_moving_count' => count($slow),
                'non_moving_count' => count($nonMoving),
            ],
            'fast_moving' => $fast,
            'slow_moving' => $slow,
            'non_moving' => $nonMoving,
            'all' => $products,
        ];
    }

    /**
     * Low Stock and Reorder Alerts.
     */
    public function getLowStockAlerts(int $companyId, ?int $warehouseId = null): array
    {
        $params = [$companyId];
        $where = "p.company_id = ? AND p.deleted_at IS NULL AND p.current_stock <= COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0)";

        if ($warehouseId) {
            $where .= " AND sb.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        $sql = "
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.unit,
                p.current_stock,
                COALESCE(NULLIF(p.reorder_level, 0), p.min_stock_alert, 10.0) AS reorder_threshold,
                COALESCE(p.purchase_price, 0) AS unit_cost,
                c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$where}
            ORDER BY p.current_stock ASC
        ";

        return Database::select($sql, $params);
    }
}
