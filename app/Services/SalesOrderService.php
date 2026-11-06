<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;

/**
 * Enterprise Sales Order Business Service.
 * Manages Sales Order lifecycle (Quotation -> Sales Order -> Delivery Challan -> Invoice),
 * order numbering (SO-YYYY-XXXX), multi-item calculations with CGST/SGST/IGST tax splits,
 * fulfillment status, and non-accounting commercial commitment safety.
 */
class SalesOrderService
{
    /**
     * Get paginated/filtered list of sales orders.
     */
    public function getOrders(array $filters = []): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)($filters['branch_id'] ?? Session::getBranchId() ?? 0);

        $conditions = ["so.company_id = ?"];
        $params = [$companyId];

        if ($branchId > 0) {
            $conditions[] = "(so.branch_id = ? OR so.branch_id IS NULL)";
            $params[] = $branchId;
        }

        if (!empty($filters['status']) && strtoupper($filters['status']) !== 'ALL') {
            $conditions[] = "so.status = ?";
            $params[] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $conditions[] = "so.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(so.order_number LIKE ? OR c.name LIKE ? OR so.reference_no LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = implode(' AND ', $conditions);

        $orders = Database::select(
            "SELECT so.*, c.name AS customer_name, c.phone AS customer_phone, c.gstin AS customer_gstin,
                    b.name AS branch_name, q.quotation_number AS source_quotation_number
             FROM sales_orders so
             LEFT JOIN customers c ON so.customer_id = c.id
             LEFT JOIN branches b ON so.branch_id = b.id
             LEFT JOIN quotations q ON so.source_quotation_id = q.id
             WHERE {$whereSql}
             ORDER BY so.order_date DESC, so.id DESC",
            $params
        );

        // Status counts for status pills bar
        $countsSql = "SELECT status, COUNT(*) AS cnt FROM sales_orders WHERE company_id = ? GROUP BY status";
        $countRows = Database::select($countsSql, [$companyId]);
        $statusCounts = [
            'ALL' => 0,
            'PENDING' => 0,
            'CONFIRMED' => 0,
            'PROCESSING' => 0,
            'DISPATCHED' => 0,
            'COMPLETED' => 0,
            'CANCELLED' => 0,
        ];
        foreach ($countRows as $cr) {
            $st = strtoupper((string)$cr['status']);
            $cnt = (int)$cr['cnt'];
            $statusCounts['ALL'] += $cnt;
            if (isset($statusCounts[$st])) {
                $statusCounts[$st] = $cnt;
            }
        }

        return [
            'orders' => $orders,
            'counts' => $statusCounts,
        ];
    }

    /**
     * Get single sales order with details, customer information, and line items.
     */
    public function getOrder(int $orderId): ?array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $order = Database::selectOne(
            "SELECT so.*, c.name AS customer_name, c.company_name AS customer_company,
                    c.phone AS customer_phone, c.email AS customer_email, c.gstin AS customer_gstin,
                    c.address_line1 AS customer_address, c.city AS customer_city,
                    c.state AS customer_state, c.state_code AS customer_state_code,
                    c.pincode AS customer_pincode,
                    b.name AS branch_name, q.quotation_number AS source_quotation_number
             FROM sales_orders so
             LEFT JOIN customers c ON so.customer_id = c.id
             LEFT JOIN branches b ON so.branch_id = b.id
             LEFT JOIN quotations q ON so.source_quotation_id = q.id
             WHERE so.id = ? AND so.company_id = ?",
            [$orderId, $companyId]
        );

        if (!$order) {
            return null;
        }

        $items = Database::select(
            "SELECT soi.*, p.name AS catalog_product_name, p.sku AS catalog_product_sku
             FROM sales_order_items soi
             LEFT JOIN products p ON soi.product_id = p.id
             WHERE soi.sales_order_id = ? AND soi.company_id = ?
             ORDER BY soi.id ASC",
            [$orderId, $companyId]
        );

        // Check if converted to invoice or delivery challan
        $linkedInvoice = Database::selectOne(
            "SELECT id, invoice_number, invoice_date, status, grand_total FROM invoices WHERE source_sales_order_id = ? AND company_id = ? AND deleted_at IS NULL",
            [$orderId, $companyId]
        );

        $linkedChallan = Database::selectOne(
            "SELECT id, challan_number, challan_date, status FROM delivery_challans WHERE sales_order_id = ? AND company_id = ?",
            [$orderId, $companyId]
        );

        $order['items'] = $items;
        $order['linked_invoice'] = $linkedInvoice;
        $order['linked_challan'] = $linkedChallan;

        return $order;
    }

    /**
     * Generate next sequential Sales Order Number (SO-YYYY-XXXX).
     */
    public function generateOrderNumber(int $companyId): string
    {
        $year = date('Y');
        $prefix = "SO-{$year}-";

        $lastRow = Database::selectOne(
            "SELECT order_number FROM sales_orders WHERE company_id = ? AND order_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$companyId, "{$prefix}%"]
        );

        if ($lastRow && preg_match('/-(\d+)$/', (string)$lastRow['order_number'], $matches)) {
            $nextSeq = (int)$matches[1] + 1;
        } else {
            $nextSeq = 1001;
        }

        return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new Sales Order.
     */
    public function createOrder(array $data): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)($data['branch_id'] ?? Session::getBranchId() ?? 1);

        $customerId = (int)($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new ValidationException(['customer_id' => 'Please select a valid customer.']);
        }

        $customer = Database::selectOne(
            "SELECT id, name, gstin, state_code, address_line1, city, state, pincode FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$customerId, $companyId]
        );
        if (!$customer) {
            throw new ValidationException(['customer_id' => 'Selected customer not found.']);
        }

        $orderNumber = !empty($data['order_number']) ? trim((string)$data['order_number']) : $this->generateOrderNumber($companyId);
        $orderDate = !empty($data['order_date']) ? trim((string)$data['order_date']) : date('Y-m-d');
        $expectedDelivery = !empty($data['expected_delivery']) ? trim((string)$data['expected_delivery']) : null;
        $refNo = trim((string)($data['reference_no'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $status = strtoupper(trim((string)($data['status'] ?? 'PENDING')));
        if (!in_array($status, ['PENDING', 'CONFIRMED', 'PROCESSING', 'DISPATCHED', 'COMPLETED', 'CANCELLED'], true)) {
            $status = 'PENDING';
        }

        $posCode = trim((string)($data['place_of_supply_state_code'] ?? ($customer['state_code'] ?? '27')));
        if (empty($posCode)) $posCode = '27';
        $isIntraState = ($posCode === '27');

        $rawItems = $data['items'] ?? [];
        if (!is_array($rawItems) || empty($rawItems)) {
            throw new ValidationException(['items' => 'At least one line item is required for a sales order.']);
        }

        $taxableValue = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $totalDiscount = 0.0;
        $processedItems = [];

        foreach ($rawItems as $it) {
            if (!is_array($it)) continue;
            $pId = (int)($it['product_id'] ?? 0);
            $itemName = trim((string)($it['item_name'] ?? ''));
            $hsn = trim((string)($it['hsn_sac'] ?? ''));
            $qty = (float)($it['quantity'] ?? 1);
            $rate = (float)($it['unit_price'] ?? ($it['rate'] ?? 0));
            $dRate = (float)($it['discount_rate'] ?? 0);
            $gstRate = (float)($it['gst_rate'] ?? 18);
            $unit = trim((string)($it['unit'] ?? 'Pcs'));

            if ($pId > 0 && (empty($itemName) || $rate <= 0 || empty($hsn))) {
                $p = Database::selectOne("SELECT name, hsn_sac, COALESCE(selling_price, sales_price, 0) AS price, tax_rate, unit FROM products WHERE id = ?", [$pId]);
                if ($p) {
                    if (empty($itemName)) $itemName = $p['name'];
                    if ($rate <= 0) $rate = (float)$p['price'];
                    if (empty($hsn)) $hsn = (string)($p['hsn_sac'] ?? '7318');
                    if (empty($unit)) $unit = (string)($p['unit'] ?? 'Pcs');
                }
            }

            if (empty($itemName)) $itemName = 'Standard Item';
            if ($qty <= 0) $qty = 1.0;
            if ($rate < 0) $rate = 0.0;

            $gross = round($qty * $rate, 2);
            $lineDiscount = round($gross * ($dRate / 100), 2);
            $lineTaxable = round($gross - $lineDiscount, 2);

            if ($isIntraState) {
                $cRate = $gstRate / 2;
                $sRate = $gstRate / 2;
                $iRate = 0.0;
                $cAmt = round($lineTaxable * ($cRate / 100), 2);
                $sAmt = round($lineTaxable * ($sRate / 100), 2);
                $iAmt = 0.0;
            } else {
                $cRate = 0.0;
                $sRate = 0.0;
                $iRate = $gstRate;
                $cAmt = 0.0;
                $sAmt = 0.0;
                $iAmt = round($lineTaxable * ($iRate / 100), 2);
            }

            $lineTotal = round($lineTaxable + $cAmt + $sAmt + $iAmt, 2);

            $taxableValue += $lineTaxable;
            $cgstAmount += $cAmt;
            $sgstAmount += $sAmt;
            $igstAmount += $iAmt;
            $totalDiscount += $lineDiscount;

            $processedItems[] = [
                'product_id' => $pId > 0 ? $pId : 1,
                'item_name' => $itemName,
                'hsn_sac' => $hsn ?: '7318',
                'quantity' => $qty,
                'unit' => $unit ?: 'Pcs',
                'unit_price' => $rate,
                'discount_rate' => $dRate,
                'discount_amount' => $lineDiscount,
                'taxable_value' => $lineTaxable,
                'gst_rate' => $gstRate,
                'cgst_rate' => $cRate,
                'cgst_amount' => $cAmt,
                'sgst_rate' => $sRate,
                'sgst_amount' => $sAmt,
                'igst_rate' => $iRate,
                'igst_amount' => $iAmt,
                'total_amount' => $lineTotal,
            ];
        }

        $taxableValue = round($taxableValue, 2);
        $totalTax = round($cgstAmount + $sgstAmount + $igstAmount, 2);
        $grandTotal = round($taxableValue + $totalTax, 2);

        $orderId = 0;
        Database::transaction(function () use (
            $companyId, $branchId, $customerId, $orderNumber, $orderDate, $expectedDelivery,
            $refNo, $notes, $status, $taxableValue, $totalDiscount, $totalTax, $grandTotal,
            $data, $processedItems, &$orderId
        ) {
            $orderId = (int)Database::insert('sales_orders', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'order_date' => $orderDate,
                'expected_delivery' => $expectedDelivery,
                'reference_no' => $refNo ?: null,
                'sub_total' => $taxableValue,
                'discount_amount' => $totalDiscount,
                'total_tax' => $totalTax,
                'grand_total' => $grandTotal,
                'status' => $status,
                'fulfillment_status' => 'UNFULFILLED',
                'payment_status' => 'PENDING',
                'notes' => $notes ?: null,
                'source_quotation_id' => !empty($data['source_quotation_id']) ? (int)$data['source_quotation_id'] : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($processedItems as $item) {
                Database::insert('sales_order_items', [
                    'company_id' => $companyId,
                    'sales_order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'hsn_sac' => $item['hsn_sac'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'discount_rate' => $item['discount_rate'],
                    'discount_amount' => $item['discount_amount'],
                    'taxable_value' => $item['taxable_value'],
                    'gst_rate' => $item['gst_rate'],
                    'cgst_rate' => $item['cgst_rate'],
                    'cgst_amount' => $item['cgst_amount'],
                    'sgst_rate' => $item['sgst_rate'],
                    'sgst_amount' => $item['sgst_amount'],
                    'igst_rate' => $item['igst_rate'],
                    'igst_amount' => $item['igst_amount'],
                    'total_amount' => $item['total_amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });

        return $this->getOrder($orderId);
    }

    /**
     * Convert an Accepted Quotation into a Sales Order.
     */
    public function convertQuotationToOrder(int $quoteId): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $quote = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            throw new ValidationException(['quotation_id' => 'Quotation not found.']);
        }

        // Check if already converted to order
        $existingOrder = Database::selectOne(
            "SELECT id, order_number FROM sales_orders WHERE source_quotation_id = ? AND company_id = ?",
            [$quoteId, $companyId]
        );
        if ($existingOrder) {
            throw new ValidationException([
                'quotation_id' => "Quotation '{$quote['quotation_number']}' has already been converted to Sales Order '{$existingOrder['order_number']}'.",
            ]);
        }

        $quoteItems = Database::select(
            "SELECT * FROM quotation_items WHERE quotation_id = ? AND company_id = ? ORDER BY id ASC",
            [$quoteId, $companyId]
        );

        if (empty($quoteItems)) {
            throw new ValidationException(['items' => 'Quotation has no line items to convert into a sales order.']);
        }

        $orderPayload = [
            'branch_id' => $quote['branch_id'] ?? 1,
            'customer_id' => $quote['customer_id'],
            'order_date' => date('Y-m-d'),
            'expected_delivery' => $quote['valid_until'] ?? null,
            'reference_no' => $quote['quotation_number'],
            'notes' => 'Generated from Quotation #' . $quote['quotation_number'],
            'status' => 'CONFIRMED',
            'place_of_supply_state_code' => $quote['place_of_supply_state_code'] ?? '27',
            'source_quotation_id' => $quoteId,
            'items' => array_map(fn($it) => [
                'product_id' => $it['product_id'],
                'item_name' => $it['item_name'],
                'hsn_sac' => $it['hsn_sac'],
                'quantity' => $it['quantity'],
                'unit' => $it['unit'] ?: 'Pcs',
                'unit_price' => $it['unit_price'],
                'discount_rate' => $it['discount_rate'],
                'gst_rate' => $it['gst_rate'],
            ], $quoteItems),
        ];

        $order = $this->createOrder($orderPayload);

        // Update quotation status to ACCEPTED
        Database::update(
            'quotations',
            ['status' => 'ACCEPTED', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND company_id = ?',
            [$quoteId, $companyId]
        );

        return $order;
    }

    /**
     * Convert Sales Order into Delivery Challan.
     */
    public function convertOrderToChallan(int $orderId): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $order = $this->getOrder($orderId);

        if (!$order) {
            throw new ValidationException(['id' => 'Sales Order not found.']);
        }

        if (!empty($order['linked_challan'])) {
            throw new ValidationException([
                'challan' => "Sales Order '{$order['order_number']}' already has Delivery Challan '{$order['linked_challan']['challan_number']}'.",
            ]);
        }

        $challanNumber = 'DC-' . date('Y') . '-' . rand(1000, 9999);
        $challanId = 0;

        Database::transaction(function () use ($companyId, $order, $challanNumber, &$challanId) {
            $challanId = (int)Database::insert('delivery_challans', [
                'company_id' => $companyId,
                'branch_id' => $order['branch_id'] ?? 1,
                'customer_id' => $order['customer_id'],
                'challan_number' => $challanNumber,
                'challan_date' => date('Y-m-d'),
                'sales_order_id' => $order['id'],
                'reference_so' => $order['order_number'],
                'delivery_address' => $order['customer_address'] ?: 'Standard Site Delivery',
                'transport_details' => 'Road freight logistics',
                'status' => 'DISPATCHED',
                'notes' => 'Fulfillment for Sales Order #' . $order['order_number'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($order['items'] as $item) {
                Database::insert('delivery_challan_items', [
                    'company_id' => $companyId,
                    'delivery_challan_id' => $challanId,
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'unit' => $item['unit'] ?: 'Pcs',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['total_amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Database::update(
                'sales_orders',
                [
                    'status' => 'DISPATCHED',
                    'fulfillment_status' => 'DISPATCHED',
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$order['id'], $companyId]
            );
        });

        return [
            'challan_id' => $challanId,
            'challan_number' => $challanNumber,
        ];
    }

    /**
     * Convert Sales Order into confirmed Tax Invoice.
     * Preserves strict invoice numbering via InvoiceNumberService.
     */
    public function convertOrderToInvoice(int $orderId): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $order = $this->getOrder($orderId);

        if (!$order) {
            throw new ValidationException(['id' => 'Sales Order not found.']);
        }

        if (!empty($order['linked_invoice'])) {
            throw new ValidationException([
                'invoice' => "Sales Order '{$order['order_number']}' has already been converted to Tax Invoice '{$order['linked_invoice']['invoice_number']}'.",
            ]);
        }

        $invoiceId = 0;
        $invoiceNumber = '';

        Database::transaction(function () use ($companyId, $branchId, $order, &$invoiceId, &$invoiceNumber) {
            $invoiceNumber = \WTSBill\Services\InvoiceNumberService::acquireInvoiceNumber(
                $companyId,
                $branchId,
                date('Y-m-d')
            );

            $invDate = date('Y-m-d');
            $dueDate = !empty($order['expected_delivery']) ? $order['expected_delivery'] : date('Y-m-d', strtotime('+30 days'));

            $invoiceId = (int)Database::insert('invoices', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => 1,
                'customer_id' => $order['customer_id'],
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invDate,
                'due_date' => $dueDate,
                'place_of_supply' => $order['customer_state_code'] ?? '27',
                'is_igst' => ($order['customer_state_code'] ?? '27') === '27' ? 0 : 1,
                'sub_total' => $order['sub_total'],
                'discount_rate' => 0.00,
                'discount_amount' => $order['discount_amount'],
                'cgst_amount' => round(array_sum(array_column($order['items'], 'cgst_amount')), 2),
                'sgst_amount' => round(array_sum(array_column($order['items'], 'sgst_amount')), 2),
                'igst_amount' => round(array_sum(array_column($order['items'], 'igst_amount')), 2),
                'total_tax' => $order['total_tax'],
                'round_off' => 0.00,
                'grand_total' => $order['grand_total'],
                'amount_paid' => 0.00,
                'amount_due' => $order['grand_total'],
                'status' => 'UNPAID',
                'notes' => 'Converted from Sales Order #' . $order['order_number'],
                'source_sales_order_id' => $order['id'],
                'source_quotation_id' => $order['source_quotation_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($order['items'] as $item) {
                Database::insert('invoice_items', [
                    'company_id' => $companyId,
                    'invoice_id' => $invoiceId,
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'hsn_sac' => $item['hsn_sac'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'discount_rate' => $item['discount_rate'],
                    'discount_amount' => $item['discount_amount'],
                    'taxable_value' => $item['taxable_value'],
                    'gst_rate' => $item['gst_rate'],
                    'cgst_rate' => $item['cgst_rate'],
                    'cgst_amount' => $item['cgst_amount'],
                    'sgst_rate' => $item['sgst_rate'],
                    'sgst_amount' => $item['sgst_amount'],
                    'igst_rate' => $item['igst_rate'],
                    'igst_amount' => $item['igst_amount'],
                    'total_amount' => $item['total_amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Update sales order fulfillment status
            Database::update(
                'sales_orders',
                [
                    'status' => 'COMPLETED',
                    'fulfillment_status' => 'FULFILLED',
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$order['id'], $companyId]
            );
        });

        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
        ];
    }

    /**
     * Delete Sales Order (if not converted to invoice).
     */
    public function deleteOrder(int $orderId): bool
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $order = $this->getOrder($orderId);

        if (!$order) {
            return false;
        }

        if (!empty($order['linked_invoice'])) {
            throw new ValidationException(['id' => 'Cannot delete a sales order that has already been converted to an active Tax Invoice.']);
        }

        Database::transaction(function () use ($companyId, $orderId) {
            Database::delete('sales_order_items', 'sales_order_id = ? AND company_id = ?', [$orderId, $companyId]);
            Database::delete('sales_orders', 'id = ? AND company_id = ?', [$orderId, $companyId]);
        });

        return true;
    }
}

