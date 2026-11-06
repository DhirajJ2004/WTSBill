<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;

/**
 * Purchase Order and Procurement Workflow Service.
 * Manages PO lifecycle (PO -> Goods Receipt -> Inward Bill -> Debit Note / Return),
 * sequential PO-YYYY-XXXX numbering, GST tax splits, and warehouse stock receipts.
 */
class PurchaseOrderService
{
    /**
     * Retrieve list of Purchase Orders with filters.
     */
    public function getOrders(int $companyId, array $filters = []): array
    {
        $params = [$companyId];
        $where = "po.company_id = ?";

        if (!empty($filters['status']) && strtoupper((string)$filters['status']) !== 'ALL') {
            $where .= " AND po.status = ?";
            $params[] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $where .= " AND po.supplier_id = ?";
            $params[] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where .= " AND po.branch_id = ?";
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (po.po_number LIKE ? OR po.reference_no LIKE ? OR s.name LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT 
                po.*,
                s.name AS supplier_name,
                s.gstin AS supplier_gstin,
                b.name AS branch_name,
                COALESCE(items.item_count, 0) AS item_count,
                COALESCE(items.total_ordered, 0) AS total_ordered,
                COALESCE(items.total_received, 0) AS total_received
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN branches b ON po.branch_id = b.id
            LEFT JOIN (
                SELECT 
                    purchase_order_id,
                    COUNT(*) AS item_count,
                    SUM(quantity) AS total_ordered,
                    SUM(received_quantity) AS total_received
                FROM purchase_order_items
                WHERE company_id = ?
                GROUP BY purchase_order_id
            ) items ON items.purchase_order_id = po.id
            WHERE {$where}
            ORDER BY po.id DESC
        ";

        array_unshift($params, $companyId);

        return Database::select($sql, $params);
    }

    /**
     * Retrieve single Purchase Order details and line items.
     */
    public function getOrder(int $companyId, int $poId): ?array
    {
        $sql = "
            SELECT 
                po.*,
                s.name AS supplier_name,
                s.company_name AS supplier_company_name,
                s.gstin AS supplier_gstin,
                s.phone AS supplier_phone,
                s.email AS supplier_email,
                s.address_line1 AS supplier_address,
                s.state AS supplier_state,
                b.name AS branch_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN branches b ON po.branch_id = b.id
            WHERE po.id = ? AND po.company_id = ?
        ";

        $order = Database::selectOne($sql, [$poId, $companyId]);
        if (!$order) {
            return null;
        }

        $itemsSql = "
            SELECT 
                poi.*,
                p.sku,
                p.current_stock
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.purchase_order_id = ? AND poi.company_id = ?
            ORDER BY poi.id ASC
        ";
        $order['items'] = Database::select($itemsSql, [$poId, $companyId]);

        return $order;
    }

    /**
     * Generate sequential PO number (PO-YYYY-XXXX).
     */
    public function generatePoNumber(int $companyId): string
    {
        $year = date('Y');
        $prefix = "PO-{$year}-";

        $latest = Database::selectOne(
            "SELECT po_number FROM purchase_orders WHERE company_id = ? AND po_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$companyId, "{$prefix}%"]
        );

        $nextSeq = 1;
        if ($latest && preg_match('/PO-\d{4}-(\d+)/', (string)$latest['po_number'], $matches)) {
            $nextSeq = (int)$matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $nextSeq);
    }

    /**
     * Create a new Purchase Order.
     */
    public function createOrder(int $companyId, array $data, string $userName): int
    {
        $supplierId = (int)($data['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            throw new ValidationException(['supplier_id' => 'Please select a valid supplier.']);
        }

        $supplier = Database::selectOne(
            "SELECT * FROM suppliers WHERE id = ? AND company_id = ?",
            [$supplierId, $companyId]
        );
        if (!$supplier) {
            throw new ValidationException(['supplier_id' => 'Selected supplier does not exist.']);
        }

        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            throw new ValidationException(['items' => 'At least one line item is required for a purchase order.']);
        }

        $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : (int)(Session::getBranchId() ?? 1);
        $poNumber = $this->generatePoNumber($companyId);
        $poDate = !empty($data['po_date']) ? (string)$data['po_date'] : date('Y-m-d');
        $expectedDelivery = !empty($data['expected_delivery']) ? (string)$data['expected_delivery'] : null;
        $referenceNo = trim((string)($data['reference_no'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $terms = trim((string)($data['terms'] ?? ''));
        $status = !empty($data['status']) ? strtoupper(trim((string)$data['status'])) : 'PENDING';

        // Check company state vs supplier state for GST split
        $company = Database::selectOne("SELECT state, state_code FROM companies WHERE id = ?", [$companyId]);
        $companyState = strtolower(trim((string)($company['state'] ?? '')));
        $supplierState = strtolower(trim((string)($supplier['state'] ?? '')));
        $isInterstate = (!empty($companyState) && !empty($supplierState) && $companyState !== $supplierState);

        return Database::transaction(function() use (
            $companyId, $branchId, $supplierId, $poNumber, $poDate, $expectedDelivery,
            $referenceNo, $notes, $terms, $status, $items, $isInterstate
        ) {
            $now = date('Y-m-d H:i:s');

            $subTotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;
            $grandTotal = 0.0;
            $computedItems = [];

            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $prod = Database::selectOne("SELECT * FROM products WHERE id = ?", [$productId]);
                $itemName = !empty($item['item_name']) ? trim((string)$item['item_name']) : ($prod['name'] ?? 'Item');
                $hsnSac = !empty($item['hsn_sac']) ? trim((string)$item['hsn_sac']) : ($prod['hsn_sac'] ?? '');
                $unit = !empty($item['unit']) ? trim((string)$item['unit']) : ($prod['unit'] ?? 'PCS');
                $discRate = (float)($item['discount_rate'] ?? 0);
                $gstRate = isset($item['gst_rate']) ? (float)$item['gst_rate'] : (float)($prod['tax_rate'] ?? 18.0);

                $gross = $qty * $price;
                $discAmt = round(($gross * $discRate) / 100, 2);
                $taxable = $gross - $discAmt;

                $cgstRate = 0.0;
                $cgstAmt = 0.0;
                $sgstRate = 0.0;
                $sgstAmt = 0.0;
                $igstRate = 0.0;
                $igstAmt = 0.0;

                if ($isInterstate) {
                    $igstRate = $gstRate;
                    $igstAmt = round(($taxable * $igstRate) / 100, 2);
                } else {
                    $cgstRate = $gstRate / 2;
                    $sgstRate = $gstRate / 2;
                    $cgstAmt = round(($taxable * $cgstRate) / 100, 2);
                    $sgstAmt = round(($taxable * $sgstRate) / 100, 2);
                }

                $lineTax = $cgstAmt + $sgstAmt + $igstAmt;
                $lineTotal = $taxable + $lineTax;

                $subTotal += $taxable;
                $discountTotal += $discAmt;
                $taxTotal += $lineTax;
                $grandTotal += $lineTotal;

                $computedItems[] = [
                    'product_id' => $productId,
                    'item_name' => $itemName,
                    'hsn_sac' => $hsnSac,
                    'quantity' => $qty,
                    'received_quantity' => 0.0,
                    'unit' => $unit,
                    'unit_price' => $price,
                    'discount_rate' => $discRate,
                    'discount_amount' => $discAmt,
                    'taxable_value' => $taxable,
                    'gst_rate' => $gstRate,
                    'cgst_rate' => $cgstRate,
                    'cgst_amount' => $cgstAmt,
                    'sgst_rate' => $sgstRate,
                    'sgst_amount' => $sgstAmt,
                    'igst_rate' => $igstRate,
                    'igst_amount' => $igstAmt,
                    'total_amount' => $lineTotal,
                ];
            }

            if (empty($computedItems)) {
                throw new ValidationException(['items' => 'At least one valid line item with positive quantity and price is required.']);
            }

            $poId = (int)Database::insert('purchase_orders', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'po_number' => $poNumber,
                'po_date' => $poDate,
                'expected_delivery' => $expectedDelivery,
                'sub_total' => $subTotal,
                'discount_amount' => $discountTotal,
                'total_tax' => $taxTotal,
                'grand_total' => $grandTotal,
                'status' => $status,
                'reference_no' => $referenceNo,
                'notes' => $notes,
                'terms' => $terms,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($computedItems as $cItem) {
                $cItem['company_id'] = $companyId;
                $cItem['purchase_order_id'] = $poId;
                $cItem['created_at'] = $now;
                $cItem['updated_at'] = $now;
                Database::insert('purchase_order_items', $cItem);
            }

            return $poId;
        });
    }

    /**
     * Approve Purchase Order.
     */
    public function approveOrder(int $companyId, int $poId, string $userName): bool
    {
        $order = $this->getOrder($companyId, $poId);
        if (!$order) {
            throw new ValidationException(['id' => 'Purchase Order not found.']);
        }

        Database::update(
            'purchase_orders',
            ['status' => 'APPROVED', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND company_id = ?',
            [$poId, $companyId]
        );

        return true;
    }

    /**
     * Receive Goods (Goods Receipt Note / GRN workflow).
     * Credits received quantities directly into destination warehouse stock balances.
     */
    public function receiveGoods(int $companyId, int $poId, array $receipts, int $warehouseId, string $userName): array
    {
        $order = $this->getOrder($companyId, $poId);
        if (!$order) {
            throw new ValidationException(['id' => 'Purchase Order not found.']);
        }

        if (in_array($order['status'], ['CANCELLED'], true)) {
            throw new ValidationException(['status' => 'Cannot receive goods on a cancelled Purchase Order.']);
        }

        $warehouse = Database::selectOne(
            "SELECT id, branch_id FROM warehouses WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$warehouseId, $companyId]
        );
        if (!$warehouse) {
            throw new ValidationException(['warehouse_id' => 'Please select a valid destination warehouse.']);
        }

        return Database::transaction(function() use ($companyId, $poId, $order, $receipts, $warehouseId, $warehouse, $userName) {
            $now = date('Y-m-d H:i:s');
            $allReceived = true;
            $anyReceived = false;

            foreach ($order['items'] as $item) {
                $itemId = (int)$item['id'];
                $productId = (int)$item['product_id'];
                $orderedQty = (float)$item['quantity'];
                $alreadyReceived = (float)$item['received_quantity'];

                $incomingQty = isset($receipts[$itemId]) ? (float)$receipts[$itemId] : 0.0;
                if ($incomingQty <= 0) {
                    if ($alreadyReceived < $orderedQty) {
                        $allReceived = false;
                    }
                    continue;
                }

                $newReceivedTotal = $alreadyReceived + $incomingQty;
                if ($newReceivedTotal > $orderedQty) {
                    $newReceivedTotal = $orderedQty; // Bound to ordered quantity
                }

                // Update item received quantity
                Database::update(
                    'purchase_order_items',
                    ['received_quantity' => $newReceivedTotal, 'updated_at' => $now],
                    'id = ? AND company_id = ?',
                    [$itemId, $companyId]
                );

                // Credit into warehouse stock balance
                $balance = Database::selectOne(
                    "SELECT * FROM stock_balances WHERE company_id = ? AND warehouse_id = ? AND product_id = ?",
                    [$companyId, $warehouseId, $productId]
                );

                if ($balance) {
                    Database::update(
                        'stock_balances',
                        [
                            'quantity' => (float)$balance['quantity'] + $incomingQty,
                            'available_quantity' => (float)$balance['available_quantity'] + $incomingQty,
                            'updated_at' => $now,
                        ],
                        'id = ?',
                        [$balance['id']]
                    );
                } else {
                    Database::insert('stock_balances', [
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouseId,
                        'branch_id' => (int)($warehouse['branch_id'] ?? 1),
                        'product_id' => $productId,
                        'quantity' => $incomingQty,
                        'available_quantity' => $incomingQty,
                        'reserved_quantity' => 0.0,
                        'in_transit_quantity' => 0.0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Update Product Aggregate Current Stock
                $prod = Database::selectOne("SELECT current_stock FROM products WHERE id = ?", [$productId]);
                if ($prod) {
                    Database::update(
                        'products',
                        ['current_stock' => (float)$prod['current_stock'] + $incomingQty, 'updated_at' => $now],
                        'id = ? AND company_id = ?',
                        [$productId, $companyId]
                    );
                }

                $anyReceived = true;
                if ($newReceivedTotal < $orderedQty) {
                    $allReceived = false;
                }
            }

            // Update PO Status
            $newStatus = $allReceived ? 'RECEIVED' : ($anyReceived ? 'PARTIAL' : $order['status']);
            Database::update(
                'purchase_orders',
                ['status' => $newStatus, 'updated_at' => $now],
                'id = ? AND company_id = ?',
                [$poId, $companyId]
            );

            return [
                'success' => true,
                'status' => $newStatus,
                'message' => $allReceived ? 'All goods fully received and credited to warehouse.' : 'Goods receipt recorded successfully.',
            ];
        });
    }

    /**
     * Convert Purchase Order into Inward Purchase Bill (purchases & purchase_items).
     */
    public function convertToBill(int $companyId, int $poId, array $billData, string $userName): int
    {
        $order = $this->getOrder($companyId, $poId);
        if (!$order) {
            throw new ValidationException(['id' => 'Purchase Order not found.']);
        }

        // Prevent duplicate conversion if already billed
        $existingBill = Database::selectOne(
            "SELECT id, purchase_number FROM purchases WHERE purchase_order_id = ? AND company_id = ? AND deleted_at IS NULL",
            [$poId, $companyId]
        );
        if ($existingBill) {
            $dupMsg = "This Purchase Order has already been converted into Inward Bill {$existingBill['purchase_number']}.";
            throw new ValidationException(['purchase_order_id' => $dupMsg], $dupMsg);
        }

        return Database::transaction(function() use ($companyId, $poId, $order, $billData, $userName) {
            $now = date('Y-m-d H:i:s');
            $year = date('Y');

            // Sequential bill number
            $latestBill = Database::selectOne(
                "SELECT purchase_number FROM purchases WHERE company_id = ? AND purchase_number LIKE 'PB-{$year}-%' ORDER BY id DESC LIMIT 1",
                [$companyId]
            );
            $seq = 1;
            if ($latestBill && preg_match('/PB-\d{4}-(\d+)/', (string)$latestBill['purchase_number'], $matches)) {
                $seq = (int)$matches[1] + 1;
            }
            $billNumber = sprintf('PB-%s-%04d', $year, $seq);

            $purchaseDate = !empty($billData['purchase_date']) ? (string)$billData['purchase_date'] : date('Y-m-d');
            $vendorInvNo = !empty($billData['vendor_invoice_number']) ? trim((string)$billData['vendor_invoice_number']) : ($order['reference_no'] ?: 'INV-PO-' . $order['po_number']);
            $warehouseId = !empty($billData['warehouse_id']) ? (int)$billData['warehouse_id'] : 1;

            $totalTax = (float)$order['total_tax'];
            $cgstTotal = 0.0;
            $sgstTotal = 0.0;
            $igstTotal = 0.0;

            foreach ($order['items'] as $item) {
                $cgstTotal += (float)$item['cgst_amount'];
                $sgstTotal += (float)$item['sgst_amount'];
                $igstTotal += (float)$item['igst_amount'];
            }

            $purchaseId = (int)Database::insert('purchases', [
                'company_id' => $companyId,
                'branch_id' => (int)($order['branch_id'] ?? 1),
                'supplier_id' => (int)$order['supplier_id'],
                'purchase_order_id' => $poId,
                'purchase_number' => $billNumber,
                'vendor_invoice_number' => $vendorInvNo,
                'purchase_date' => $purchaseDate,
                'due_date' => date('Y-m-d', strtotime('+30 days', strtotime($purchaseDate))),
                'sub_total' => (float)$order['sub_total'],
                'cgst_amount' => $cgstTotal,
                'sgst_amount' => $sgstTotal,
                'igst_amount' => $igstTotal,
                'total_tax' => $totalTax,
                'grand_total' => (float)$order['grand_total'],
                'amount_paid' => 0.00,
                'amount_due' => (float)$order['grand_total'],
                'status' => 'UNPAID',
                'payment_status' => 'UNPAID',
                'warehouse_id' => $warehouseId,
                'reference_no' => $order['po_number'],
                'supplier_name' => $order['supplier_name'],
                'supplier_gstin' => $order['supplier_gstin'],
                'notes' => "Converted from Purchase Order {$order['po_number']}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($order['items'] as $item) {
                Database::insert('purchase_items', [
                    'company_id' => $companyId,
                    'purchase_id' => $purchaseId,
                    'product_id' => (int)$item['product_id'],
                    'item_name' => (string)$item['item_name'],
                    'hsn_sac' => (string)($item['hsn_sac'] ?? ''),
                    'quantity' => (float)$item['quantity'],
                    'unit' => (string)($item['unit'] ?? 'PCS'),
                    'unit_price' => (float)$item['unit_price'],
                    'taxable_value' => (float)$item['taxable_value'],
                    'gst_rate' => (float)$item['gst_rate'],
                    'tax_amount' => (float)($item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount']),
                    'cgst_rate' => (float)$item['cgst_rate'],
                    'cgst_amount' => (float)$item['cgst_amount'],
                    'sgst_rate' => (float)$item['sgst_rate'],
                    'sgst_amount' => (float)$item['sgst_amount'],
                    'igst_rate' => (float)$item['igst_rate'],
                    'igst_amount' => (float)$item['igst_amount'],
                    'total_amount' => (float)$item['total_amount'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Update PO Status to RECEIVED
            Database::update(
                'purchase_orders',
                ['status' => 'RECEIVED', 'updated_at' => $now],
                'id = ? AND company_id = ?',
                [$poId, $companyId]
            );

            // Update supplier current balance
            $supplier = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$order['supplier_id']]);
            if ($supplier) {
                Database::update(
                    'suppliers',
                    ['current_balance' => (float)$supplier['current_balance'] + (float)$order['grand_total']],
                    'id = ?',
                    [$order['supplier_id']]
                );
            }

            return $purchaseId;
        });
    }

    /**
     * Create Debit Note (Purchase Return / Rate Correction).
     */
    public function createDebitNote(int $companyId, array $data, string $userName): int
    {
        $supplierId = (int)($data['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            throw new ValidationException(['supplier_id' => 'Please select a valid supplier.']);
        }

        $purchaseId = !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null;
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new ValidationException(['amount' => 'Debit note amount must be greater than zero.']);
        }

        $reason = trim((string)($data['reason'] ?? 'Goods Damaged / Defective'));
        $notes = trim((string)($data['notes'] ?? ''));
        $date = !empty($data['debit_note_date']) ? (string)$data['debit_note_date'] : date('Y-m-d');
        $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : (int)(Session::getBranchId() ?? 1);

        $year = date('Y');
        $latestDn = Database::selectOne(
            "SELECT debit_note_number FROM debit_notes WHERE company_id = ? AND debit_note_number LIKE 'DN-{$year}-%' ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
        $seq = 1;
        if ($latestDn && preg_match('/DN-\d{4}-(\d+)/', (string)$latestDn['debit_note_number'], $matches)) {
            $seq = (int)$matches[1] + 1;
        }
        $dnNumber = sprintf('DN-%s-%04d', $year, $seq);

        $originalPurchNo = null;
        if ($purchaseId) {
            $purch = Database::selectOne("SELECT purchase_number, amount_due FROM purchases WHERE id = ? AND company_id = ?", [$purchaseId, $companyId]);
            if ($purch) {
                $originalPurchNo = $purch['purchase_number'];
            }
        }

        return Database::transaction(function() use (
            $companyId, $branchId, $supplierId, $purchaseId, $dnNumber, $date,
            $originalPurchNo, $amount, $reason, $notes, $userName
        ) {
            $now = date('Y-m-d H:i:s');

            $dnId = (int)Database::insert('debit_notes', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'purchase_id' => $purchaseId,
                'debit_note_number' => $dnNumber,
                'debit_note_date' => $date,
                'original_purchase_number' => $originalPurchNo,
                'sub_total' => $amount,
                'total_tax' => 0.00,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'APPROVED',
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Reduce supplier balance
            $supp = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$supplierId]);
            if ($supp) {
                $newBal = max(0, (float)$supp['current_balance'] - $amount);
                Database::update('suppliers', ['current_balance' => $newBal], 'id = ?', [$supplierId]);
            }

            // If linked to a purchase bill, reduce amount_due
            if ($purchaseId) {
                $purch = Database::selectOne("SELECT amount_due, amount_paid, grand_total FROM purchases WHERE id = ?", [$purchaseId]);
                if ($purch) {
                    $newDue = max(0, (float)$purch['amount_due'] - $amount);
                    $newStatus = ($newDue <= 0 && (float)$purch['amount_paid'] > 0) ? 'PAID' : 'PARTIAL';
                    Database::update('purchases', ['amount_due' => $newDue, 'status' => $newStatus], 'id = ?', [$purchaseId]);
                }
            }

            return $dnId;
        });
    }

    /**
     * Get list of debit notes.
     */
    public function getDebitNotes(int $companyId, array $filters = []): array
    {
        $params = [$companyId];
        $where = "dn.company_id = ?";

        if (!empty($filters['supplier_id'])) {
            $where .= " AND dn.supplier_id = ?";
            $params[] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (dn.debit_note_number LIKE ? OR dn.original_purchase_number LIKE ? OR s.name LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT 
                dn.*,
                s.name AS supplier_name,
                s.gstin AS supplier_gstin,
                b.name AS branch_name
            FROM debit_notes dn
            LEFT JOIN suppliers s ON dn.supplier_id = s.id
            LEFT JOIN branches b ON dn.branch_id = b.id
            WHERE {$where}
            ORDER BY dn.id DESC
        ";

        return Database::select($sql, $params);
    }
}
