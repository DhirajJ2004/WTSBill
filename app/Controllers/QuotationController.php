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
use WTSBill\Services\InvoiceNumberService;

/**
 * Quotation Management Controller.
 * Handles quotation creation, line items with dynamic category filtering,
 * customer registration bridge, document preview/printing, duplication,
 * and one-click atomic conversion to GST Tax Invoices.
 */
class QuotationController
{
    /**
     * Quotations Register list view.
     * Route: GET /sales/quotations
     */
    public function index(Request $req): Response
    {
        Gate::authorize('quotations.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $statusFilter = strtoupper(trim((string)$req->query('status', 'ALL')));
        $paymentStatusFilter = strtoupper(trim((string)$req->query('payment_status', 'ALL')));
        $search = trim((string)$req->query('search', ''));
        $fromDate = trim((string)$req->query('from_date', ''));
        $toDate = trim((string)$req->query('to_date', ''));
        $branchQuery = $req->query('branch_id');
        $minAmount = $req->query('min_amount');
        $maxAmount = $req->query('max_amount');
        $page = max(1, (int)$req->query('page', 1));
        $perPage = max(5, min(100, (int)$req->query('per_page', 25)));

        // Branch scoping
        $effectiveBranchId = null;
        if ($branchQuery !== null && $branchQuery !== '' && strtoupper((string)$branchQuery) !== 'ALL') {
            $effectiveBranchId = (int)$branchQuery;
        }

        // 1. Status counts (Lifecycle & Linked Invoice Payment status)
        $countWhere = ["q.company_id = ?", "q.deleted_at IS NULL"];
        $countParams = [$companyId];
        if ($effectiveBranchId !== null) {
            $countWhere[] = "(q.branch_id = ? OR q.branch_id IS NULL)";
            $countParams[] = $effectiveBranchId;
        }

        $counts = Database::selectOne(
            "SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN q.status = 'DRAFT' THEN 1 END) AS draft,
                COUNT(CASE WHEN q.status = 'SENT' THEN 1 END) AS sent,
                COUNT(CASE WHEN q.status = 'ACCEPTED' THEN 1 END) AS accepted,
                COUNT(CASE WHEN q.converted_to_invoice = 1 OR q.status = 'CONVERTED' THEN 1 END) AS converted,
                COUNT(CASE WHEN q.status = 'REJECTED' THEN 1 END) AS rejected,
                COUNT(CASE WHEN q.status = 'EXPIRED' OR (q.status = 'SENT' AND q.valid_until < CURDATE()) THEN 1 END) AS expired,
                COUNT(CASE WHEN q.converted_to_invoice = 1 AND inv.status = 'UNPAID' THEN 1 END) AS pay_unpaid,
                COUNT(CASE WHEN q.converted_to_invoice = 1 AND inv.status = 'PARTIAL' THEN 1 END) AS pay_partial,
                COUNT(CASE WHEN q.converted_to_invoice = 1 AND inv.status = 'PAID' THEN 1 END) AS pay_paid
             FROM quotations q
             LEFT JOIN invoices inv ON q.converted_invoice_id = inv.id
             WHERE " . implode(' AND ', $countWhere),
            $countParams
        ) ?: [
            'total' => 0, 'draft' => 0, 'sent' => 0, 'accepted' => 0, 'converted' => 0, 'rejected' => 0, 'expired' => 0,
            'pay_unpaid' => 0, 'pay_partial' => 0, 'pay_paid' => 0
        ];

        // 2. Build filtered where conditions
        $where = ["q.company_id = ?", "q.deleted_at IS NULL"];
        $params = [$companyId];

        if ($effectiveBranchId !== null) {
            $where[] = "(q.branch_id = ? OR q.branch_id IS NULL)";
            $params[] = $effectiveBranchId;
        }

        if ($statusFilter !== 'ALL' && !empty($statusFilter)) {
            if ($statusFilter === 'CONVERTED') {
                $where[] = "(q.converted_to_invoice = 1 OR q.status = 'CONVERTED')";
            } elseif ($statusFilter === 'EXPIRED') {
                $where[] = "(q.status = 'EXPIRED' OR (q.status = 'SENT' AND q.valid_until < CURDATE()))";
            } else {
                $where[] = "q.status = ?";
                $params[] = $statusFilter;
            }
        }

        if ($paymentStatusFilter !== 'ALL' && !empty($paymentStatusFilter)) {
            if ($paymentStatusFilter === 'UNPAID' || $paymentStatusFilter === 'PENDING') {
                $where[] = "(q.converted_to_invoice = 1 AND inv.status = 'UNPAID')";
            } elseif ($paymentStatusFilter === 'PARTIAL' || $paymentStatusFilter === 'PARTIALLY_PAID') {
                $where[] = "(q.converted_to_invoice = 1 AND inv.status = 'PARTIAL')";
            } elseif ($paymentStatusFilter === 'PAID') {
                $where[] = "(q.converted_to_invoice = 1 AND inv.status = 'PAID')";
            }
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(q.quotation_number LIKE ? OR c.name LIKE ? OR q.reference_no LIKE ? OR q.notes LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($fromDate)) {
            $where[] = "q.quotation_date >= ?";
            $params[] = $fromDate;
        }
        if (!empty($toDate)) {
            $where[] = "q.quotation_date <= ?";
            $params[] = $toDate;
        }

        if ($minAmount !== null && $minAmount !== '' && is_numeric($minAmount)) {
            $where[] = "q.grand_total >= ?";
            $params[] = (float)$minAmount;
        }
        if ($maxAmount !== null && $maxAmount !== '' && is_numeric($maxAmount)) {
            $where[] = "q.grand_total <= ?";
            $params[] = (float)$maxAmount;
        }

        // 3. Count matching quotations for pagination
        $countQuery = "SELECT COUNT(*) AS cnt FROM quotations q 
                       LEFT JOIN customers c ON q.customer_id = c.id 
                       LEFT JOIN invoices inv ON q.converted_invoice_id = inv.id 
                       WHERE " . implode(' AND ', $where);
        $countRow = Database::selectOne($countQuery, $params);
        $totalMatching = (int)($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int)ceil($totalMatching / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // 4. Quotations dataset
        $sql = "SELECT q.id, q.quotation_number, q.customer_id, q.quotation_date, q.valid_until,
                       q.sub_total, q.total_tax, q.grand_total, q.status, q.reference_no,
                       q.converted_to_invoice, q.converted_invoice_id, q.created_at, q.branch_id,
                       COALESCE(c.name, 'Direct Client') AS customer_name,
                       c.phone AS customer_phone,
                       c.email AS customer_email,
                       inv.invoice_number AS converted_invoice_number,
                       inv.status AS converted_invoice_status,
                       inv.grand_total AS converted_invoice_total,
                       inv.amount_paid AS converted_invoice_paid,
                       inv.amount_due AS converted_invoice_due
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id
                LEFT JOIN invoices inv ON q.converted_invoice_id = inv.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY q.id DESC
                LIMIT ? OFFSET ?";
        $queryParams = array_merge($params, [$perPage, $offset]);
        $quotations = Database::select($sql, $queryParams);

        // Calculate KPI summary
        $summary = Database::selectOne(
            "SELECT 
                COUNT(*) AS total_count,
                COALESCE(SUM(grand_total), 0) AS pipeline_value,
                COUNT(CASE WHEN status = 'ACCEPTED' THEN 1 END) AS accepted_count,
                COALESCE(SUM(CASE WHEN status = 'ACCEPTED' THEN grand_total ELSE 0 END), 0) AS accepted_value,
                COUNT(CASE WHEN converted_to_invoice = 1 THEN 1 END) AS converted_count,
                COALESCE(SUM(CASE WHEN converted_to_invoice = 1 THEN grand_total ELSE 0 END), 0) AS converted_value
             FROM quotations 
             WHERE company_id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name FROM customers WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC",
            [$companyId]
        );

        $branches = Database::select(
            "SELECT id, name, branch_code FROM branches WHERE company_id = ? AND is_active = 1 ORDER BY id ASC",
            [$companyId]
        );

        if ($req->expectsJson()) {
            return Response::success([
                'quotations' => $quotations,
                'summary' => $summary,
                'counts' => $counts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalMatching,
                    'total_pages' => $totalPages,
                ],
                'filters' => [
                    'status' => $statusFilter,
                    'payment_status' => $paymentStatusFilter,
                    'search' => $search,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'branch_id' => $branchQuery,
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                ],
            ]);
        }

        $html = View::renderWithLayout('sales/quotations', [
            'title' => 'Quotations & Estimates',
            'currentUri' => '/sales/quotations',
            'quotations' => $quotations,
            'summary' => $summary,
            'customers' => $customers,
            'branches' => $branches,
            'statusCounts' => $counts,
            'activeStatus' => $statusFilter,
            'activePaymentStatus' => $paymentStatusFilter,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchQuery !== null ? (string)$branchQuery : 'ALL',
            'minAmount' => $minAmount,
            'maxAmount' => $maxAmount,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalMatching,
                'total_pages' => $totalPages,
            ],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Render dedicated Quotation creation form.
     * Route: GET /sales/quotations/create
     */
    public function createForm(Request $req): Response
    {
        Gate::authorize('quotations.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);

        $customers = Database::select(
            "SELECT id, name, company_name, gstin, state_code, address_line1, city, phone 
             FROM customers 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $categories = Database::select(
            "SELECT id, name, code FROM categories 
             WHERE company_id = ? AND status = 'ACTIVE' 
             ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, category_id, name, sku, hsn_sac, 
                    COALESCE(selling_price, sales_price, 0) AS selling_price, 
                    COALESCE(tax_rate, 18) AS tax_rate, 
                    COALESCE(unit, 'Pcs') AS unit 
             FROM products 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/quotation_create', [
            'title' => 'Create Sales Quotation',
            'currentUri' => '/sales/quotations',
            'customers' => $customers,
            'categories' => $categories,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new quotation with multiple line items and dynamic category associations.
     * Route: POST /sales/quotations/create & POST /sales/quotations
     */
    public function store(Request $req): Response
    {
        Gate::authorize('quotations.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a valid customer.'], 422);
            }
            Session::flash('error', 'Please select a valid customer.');
            return Response::redirect('/sales/quotations/create');
        }

        $customer = Database::selectOne(
            "SELECT id, name, gstin, state_code, address_line1, city, state, pincode 
             FROM customers 
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$customerId, $companyId]
        );

        if (!$customer) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Selected customer not found.'], 422);
            }
            Session::flash('error', 'Selected customer not found.');
            return Response::redirect('/sales/quotations/create');
        }

        $quoteDate = trim((string)($payload['quotation_date'] ?? date('Y-m-d')));
        $validUntil = !empty($payload['valid_until']) ? trim((string)$payload['valid_until']) : date('Y-m-d', strtotime('+30 days'));
        $posCode = trim((string)($payload['place_of_supply_state_code'] ?? ($customer['state_code'] ?? '27')));
        if (empty($posCode)) $posCode = '27';

        $refNo = trim((string)($payload['reference_no'] ?? ($payload['reference_po_number'] ?? '')));
        $kindAttn = trim((string)($payload['kind_attn'] ?? ''));
        $paymentDays = (int)($payload['payment_days'] ?? 0);
        $billingAddress = trim((string)($payload['billing_address'] ?? ($customer['address_line1'] ?? '')));
        $shippingAddress = trim((string)($payload['shipping_address'] ?? $billingAddress));
        $notes = trim((string)($payload['notes'] ?? ''));
        $terms = trim((string)($payload['terms'] ?? ''));

        // Desired status: DRAFT or SENT
        $reqStatus = strtoupper(trim((string)($payload['status'] ?? ($payload['action_type'] === 'draft' ? 'DRAFT' : 'SENT'))));
        if (!in_array($reqStatus, ['DRAFT', 'SENT', 'ACCEPTED'], true)) {
            $reqStatus = 'SENT';
        }

        // Process line items
        $rawItems = $payload['items'] ?? null;
        $items = [];

        if (is_array($rawItems) && !empty($rawItems)) {
            foreach ($rawItems as $it) {
                if (!is_array($it)) continue;
                $pId = (int)($it['product_id'] ?? 0);
                $catId = (int)($it['category_id'] ?? 0);
                $q = (float)($it['quantity'] ?? 1);
                $r = (float)($it['rate'] ?? ($it['unit_price'] ?? 0));
                $g = (float)($it['gst_rate'] ?? 18);
                $h = trim((string)($it['hsn_sac'] ?? ''));
                $d = (float)($it['discount_rate'] ?? 0);
                $itemName = trim((string)($it['item_name'] ?? ''));

                if ($pId > 0) {
                    $prod = Database::selectOne(
                        "SELECT id, category_id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) AS price, tax_rate, unit 
                         FROM products 
                         WHERE id = ? AND company_id = ? AND is_active = 1 AND deleted_at IS NULL",
                        [$pId, $companyId]
                    );

                    if (!$prod) {
                        if ($this->expectsJson($req)) {
                            return Response::json(['success' => false, 'message' => "Selected product (ID: {$pId}) is inactive or invalid."], 422);
                        }
                        Session::flash('error', "Selected product is invalid or inactive.");
                        return Response::redirect('/sales/quotations/create');
                    }

                    if ($catId > 0 && (int)$prod['category_id'] !== $catId) {
                        if ($this->expectsJson($req)) {
                            return Response::json(['success' => false, 'message' => "Product '{$prod['name']}' does not belong to the selected category."], 422);
                        }
                        Session::flash('error', "Product '{$prod['name']}' does not belong to the selected category.");
                        return Response::redirect('/sales/quotations/create');
                    }

                    if ($r <= 0) $r = (float)$prod['price'];
                    if (empty($h)) $h = (string)($prod['hsn_sac'] ?? '7318');
                    if (empty($itemName)) $itemName = (string)$prod['name'];
                }

                if (empty($h)) $h = '7318';
                if ($r <= 0) $r = 100.00;
                if ($q <= 0) $q = 1.0;
                if (empty($itemName)) $itemName = 'Standard Industrial Hardware Item';

                $items[] = [
                    'category_id' => $catId > 0 ? $catId : null,
                    'product_id' => $pId > 0 ? $pId : null,
                    'item_name' => $itemName,
                    'quantity' => $q,
                    'rate' => $r,
                    'gst_rate' => $g,
                    'hsn_sac' => $h,
                    'discount_rate' => $d,
                ];
            }
        }

        // Fallback for simple single item submission
        if (empty($items)) {
            $pId = (int)($payload['product_id'] ?? 0);
            $catId = (int)($payload['category_id'] ?? 0);
            $q = (float)($payload['quantity'] ?? 1);
            $r = (float)($payload['rate'] ?? ($payload['amount'] ?? 1000));
            $g = (float)($payload['gst_rate'] ?? 18);
            $h = trim((string)($payload['hsn_sac'] ?? '7318'));
            $itemName = 'Standard Industrial Hardware Item';

            if ($pId > 0) {
                $p = Database::selectOne("SELECT name, hsn_sac, selling_price FROM products WHERE id = ? AND company_id = ?", [$pId, $companyId]);
                if ($p) {
                    $itemName = $p['name'];
                    if (empty($h)) $h = (string)($p['hsn_sac'] ?? '7318');
                    if ($r <= 0) $r = (float)$p['selling_price'];
                }
            }

            $items[] = [
                'category_id' => $catId > 0 ? $catId : null,
                'product_id' => $pId > 0 ? $pId : null,
                'item_name' => $itemName,
                'quantity' => $q > 0 ? $q : 1.0,
                'rate' => $r > 0 ? $r : 100.00,
                'gst_rate' => $g,
                'hsn_sac' => $h ?: '7318',
                'discount_rate' => 0.0,
            ];
        }

        // Tax calculations (Intra-state 27 vs Inter-state)
        $isIntraState = ($posCode === '27');
        $taxableValue = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $processedItems = [];

        foreach ($items as $item) {
            $q = $item['quantity'];
            $r = $item['rate'];
            $g = $item['gst_rate'];
            $dRate = $item['discount_rate'];
            $dAmt = round(($q * $r) * ($dRate / 100), 2);
            $lineTaxable = round(($q * $r) - $dAmt, 2);

            if ($isIntraState) {
                $cRate = $g / 2;
                $sRate = $g / 2;
                $iRate = 0.0;
                $cAmt = round($lineTaxable * ($cRate / 100), 2);
                $sAmt = round($lineTaxable * ($sRate / 100), 2);
                $iAmt = 0.0;
            } else {
                $cRate = 0.0;
                $sRate = 0.0;
                $iRate = $g;
                $cAmt = 0.0;
                $sAmt = 0.0;
                $iAmt = round($lineTaxable * ($iRate / 100), 2);
            }

            $lineTotal = round($lineTaxable + $cAmt + $sAmt + $iAmt, 2);

            $taxableValue += $lineTaxable;
            $cgstAmount += $cAmt;
            $sgstAmount += $sAmt;
            $igstAmount += $iAmt;

            $processedItems[] = array_merge($item, [
                'discount_amount' => $dAmt,
                'taxable_value' => $lineTaxable,
                'cgst_rate' => $cRate,
                'cgst_amount' => $cAmt,
                'sgst_rate' => $sRate,
                'sgst_amount' => $sAmt,
                'igst_rate' => $iRate,
                'igst_amount' => $iAmt,
                'total_amount' => $lineTotal,
            ]);
        }

        $taxableValue = round($taxableValue, 2);
        $cgstAmount = round($cgstAmount, 2);
        $sgstAmount = round($sgstAmount, 2);
        $igstAmount = round($igstAmount, 2);
        $totalTax = round($cgstAmount + $sgstAmount + $igstAmount, 2);
        $rawTotal = $taxableValue + $totalTax;
        $grandTotal = round($rawTotal);
        $roundOff = round($grandTotal - $rawTotal, 2);

        try {
            $quoteId = 0;
            $quoteNumber = '';

            Database::transaction(function () use (
                $companyId, $branchId, $customerId, $customer, $quoteDate, $validUntil,
                $posCode, $refNo, $kindAttn, $paymentDays, $billingAddress, $shippingAddress,
                $notes, $terms, $reqStatus, $taxableValue, $cgstAmount, $sgstAmount,
                $igstAmount, $totalTax, $roundOff, $grandTotal, $processedItems,
                &$quoteId, &$quoteNumber
            ) {
                // Generate sequential quotation number: QT-YYYYMM-XXXX
                $yearMonth = date('Ym');
                $countRow = Database::selectOne(
                    "SELECT COUNT(*) AS total FROM quotations WHERE company_id = ?",
                    [$companyId]
                );
                $seq = (int)($countRow['total'] ?? 0) + 1;
                $quoteNumber = sprintf('QT-%s-%04d', $yearMonth, $seq);

                $quoteId = (int)Database::insert('quotations', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'quotation_number' => $quoteNumber,
                    'quotation_date' => $quoteDate,
                    'valid_until' => $validUntil,
                    'reference_no' => $refNo ?: null,
                    'kind_attn' => $kindAttn ?: null,
                    'payment_days' => $paymentDays,
                    'place_of_supply_state_code' => $posCode,
                    'billing_address' => $billingAddress ?: ($customer['address_line1'] ?? null),
                    'shipping_address' => $shippingAddress ?: null,
                    'sub_total' => $taxableValue,
                    'discount_amount' => 0.00,
                    'taxable_value' => $taxableValue,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => $igstAmount,
                    'total_tax' => $totalTax,
                    'round_off' => $roundOff,
                    'grand_total' => $grandTotal,
                    'status' => $reqStatus,
                    'notes' => $notes ?: 'Quotation valid for 30 days from date of issue.',
                    'terms' => $terms ?: "1) Payment Condition: {$paymentDays} credit days.\n2) Goods once sold will not be taken back.\n3) Subject to local jurisdiction.",
                    'converted_to_invoice' => 0,
                    'converted_invoice_id' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Insert quotation line items
                foreach ($processedItems as $item) {
                    Database::insert('quotation_items', [
                        'company_id' => $companyId,
                        'quotation_id' => $quoteId,
                        'product_id' => $item['product_id'],
                        'category_id' => $item['category_id'],
                        'item_name' => $item['item_name'],
                        'hsn_sac' => $item['hsn_sac'],
                        'quantity' => $item['quantity'],
                        'unit' => 'Pcs',
                        'unit_price' => $item['rate'],
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

                AuditLogger::log(
                    action: 'create',
                    module: 'sales',
                    entityType: 'QUOTATION',
                    entityId: $quoteId,
                    entityLabel: $quoteNumber,
                    newValues: ['quotation_number' => $quoteNumber, 'grand_total' => $grandTotal, 'status' => $reqStatus],
                    status: 'success',
                    notes: "Created sales quotation {$quoteNumber} for ₹" . number_format($grandTotal, 2)
                );
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Quotation '{$quoteNumber}' created successfully.",
                    'quotation_id' => $quoteId,
                    'quotation_number' => $quoteNumber,
                    'redirect' => '/sales/quotations',
                ], 201);
            }

            Session::flash('success', "Quotation '{$quoteNumber}' created successfully.");
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to create quotation: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create quotation: ' . $e->getMessage());
            return Response::redirect('/sales/quotations/create');
        }
    }

    /**
     * Show single quotation details with line items (JSON API).
     * Route: GET /sales/quotations/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));

        if (!$this->expectsJson($req)) {
            return $this->print($req, $quoteId);
        }

        $quote = Database::selectOne(
            "SELECT q.*, COALESCE(c.name, 'Direct Client') AS customer_name,
                    c.gstin AS customer_gstin, c.phone AS customer_phone,
                    c.address_line1 AS customer_address, c.city AS customer_city,
                    c.state AS customer_state,
                    inv.invoice_number AS converted_invoice_number
             FROM quotations q
             LEFT JOIN customers c ON q.customer_id = c.id
             LEFT JOIN invoices inv ON q.converted_invoice_id = inv.id
             WHERE q.id = ? AND q.company_id = ? AND q.deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
        }

        $items = Database::select(
            "SELECT qi.*, cat.name AS category_name, p.name AS product_name, p.sku AS product_sku 
             FROM quotation_items qi
             LEFT JOIN categories cat ON qi.category_id = cat.id
             LEFT JOIN products p ON qi.product_id = p.id
             WHERE qi.quotation_id = ? AND qi.company_id = ? 
             ORDER BY qi.id ASC",
            [$quoteId, $companyId]
        );

        return Response::json([
            'success' => true,
            'quotation' => $quote,
            'items' => $items,
        ]);
    }

    /**
     * Render Quotation Edit form.
     * Route: GET /sales/quotations/{id}/edit
     */
    public function editForm(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));

        $quote = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            Session::flash('error', 'Quotation not found.');
            return Response::redirect('/sales/quotations');
        }

        if ((int)($quote['converted_to_invoice'] ?? 0) === 1) {
            Session::flash('error', "Quotation '{$quote['quotation_number']}' has already been converted to an invoice and cannot be edited.");
            return Response::redirect('/sales/quotations');
        }

        $items = Database::select(
            "SELECT qi.*, cat.name AS category_name, p.name AS catalog_product_name, p.sku AS catalog_product_sku 
             FROM quotation_items qi 
             LEFT JOIN categories cat ON qi.category_id = cat.id 
             LEFT JOIN products p ON qi.product_id = p.id 
             WHERE qi.quotation_id = ? AND qi.company_id = ? 
             ORDER BY qi.id ASC",
            [$quoteId, $companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name, gstin, state_code, address_line1, city, phone 
             FROM customers 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $categories = Database::select(
            "SELECT id, name, code FROM categories 
             WHERE company_id = ? AND status = 'ACTIVE' 
             ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, category_id, name, sku, hsn_sac, 
                    COALESCE(selling_price, sales_price, 0) AS selling_price, 
                    COALESCE(tax_rate, 18) AS tax_rate, 
                    COALESCE(unit, 'Pcs') AS unit 
             FROM products 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/quotation_edit', [
            'title' => 'Edit Quotation ' . $quote['quotation_number'],
            'currentUri' => '/sales/quotations',
            'quotation' => $quote,
            'quote' => $quote,
            'items' => $items,
            'existingItems' => $items,
            'customers' => $customers,
            'categories' => $categories,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Update existing quotation.
     * Route: POST /sales/quotations/{id}/update
     */
    public function update(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
            }
            Session::flash('error', 'Quotation not found.');
            return Response::redirect('/sales/quotations');
        }

        if ((int)($existing['converted_to_invoice'] ?? 0) === 1) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Converted quotations cannot be modified.'], 422);
            }
            Session::flash('error', 'Converted quotations cannot be modified.');
            return Response::redirect('/sales/quotations');
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $customerId = (int)($payload['customer_id'] ?? $existing['customer_id']);

        $customer = Database::selectOne("SELECT name, gstin, address_line1 FROM customers WHERE id = ?", [$customerId]);
        $quoteDate = trim((string)($payload['quotation_date'] ?? $existing['quotation_date']));
        $validUntil = trim((string)($payload['valid_until'] ?? $existing['valid_until']));
        $posCode = trim((string)($payload['place_of_supply_state_code'] ?? ($existing['place_of_supply_state_code'] ?? '27')));
        $refNo = trim((string)($payload['reference_no'] ?? $existing['reference_no']));
        $kindAttn = trim((string)($payload['kind_attn'] ?? $existing['kind_attn']));
        $paymentDays = (int)($payload['payment_days'] ?? $existing['payment_days']);
        $notes = trim((string)($payload['notes'] ?? $existing['notes']));
        $terms = trim((string)($payload['terms'] ?? $existing['terms']));
        $status = strtoupper(trim((string)($payload['status'] ?? $existing['status'])));

        // Recalculate line items
        $rawItems = $payload['items'] ?? null;
        $items = [];

        if (is_array($rawItems) && !empty($rawItems)) {
            foreach ($rawItems as $it) {
                if (!is_array($it)) continue;
                $pId = (int)($it['product_id'] ?? 0);
                $catId = (int)($it['category_id'] ?? 0);
                $q = (float)($it['quantity'] ?? 1);
                $r = (float)($it['rate'] ?? ($it['unit_price'] ?? 0));
                $g = (float)($it['gst_rate'] ?? 18);
                $h = trim((string)($it['hsn_sac'] ?? ''));
                $d = (float)($it['discount_rate'] ?? 0);
                $name = trim((string)($it['item_name'] ?? ''));

                if ($pId > 0) {
                    $p = Database::selectOne("SELECT name, hsn_sac, selling_price FROM products WHERE id = ?", [$pId]);
                    if ($p) {
                        if (empty($name)) $name = $p['name'];
                        if ($r <= 0) $r = (float)$p['selling_price'];
                        if (empty($h)) $h = (string)($p['hsn_sac'] ?? '7318');
                    }
                }

                if (empty($h)) $h = '7318';
                if ($r <= 0) $r = 100.00;
                if ($q <= 0) $q = 1.0;
                if (empty($name)) $name = 'Standard Hardware Item';

                $items[] = [
                    'category_id' => $catId > 0 ? $catId : null,
                    'product_id' => $pId > 0 ? $pId : null,
                    'item_name' => $name,
                    'quantity' => $q,
                    'rate' => $r,
                    'gst_rate' => $g,
                    'hsn_sac' => $h,
                    'discount_rate' => $d,
                ];
            }
        }

        if (empty($items)) {
            $existingDbItems = Database::select("SELECT * FROM quotation_items WHERE quotation_id = ?", [$quoteId]);
            foreach ($existingDbItems as $ei) {
                $items[] = [
                    'category_id' => $ei['category_id'] ?? null,
                    'product_id' => $ei['product_id'] ?? null,
                    'item_name' => $ei['item_name'],
                    'quantity' => (float)$ei['quantity'],
                    'rate' => (float)$ei['unit_price'],
                    'gst_rate' => (float)$ei['gst_rate'],
                    'hsn_sac' => (string)$ei['hsn_sac'],
                    'discount_rate' => (float)$ei['discount_rate'],
                ];
            }
        }

        $isIntraState = ($posCode === '27');
        $taxableValue = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $processedItems = [];

        foreach ($items as $item) {
            $q = $item['quantity'];
            $r = $item['rate'];
            $g = $item['gst_rate'];
            $dRate = $item['discount_rate'];
            $dAmt = round(($q * $r) * ($dRate / 100), 2);
            $lineTaxable = round(($q * $r) - $dAmt, 2);

            if ($isIntraState) {
                $cRate = $g / 2;
                $sRate = $g / 2;
                $iRate = 0.0;
                $cAmt = round($lineTaxable * ($cRate / 100), 2);
                $sAmt = round($lineTaxable * ($sRate / 100), 2);
                $iAmt = 0.0;
            } else {
                $cRate = 0.0;
                $sRate = 0.0;
                $iRate = $g;
                $cAmt = 0.0;
                $sAmt = 0.0;
                $iAmt = round($lineTaxable * ($iRate / 100), 2);
            }

            $lineTotal = round($lineTaxable + $cAmt + $sAmt + $iAmt, 2);

            $taxableValue += $lineTaxable;
            $cgstAmount += $cAmt;
            $sgstAmount += $sAmt;
            $igstAmount += $iAmt;

            $processedItems[] = array_merge($item, [
                'discount_amount' => $dAmt,
                'taxable_value' => $lineTaxable,
                'cgst_rate' => $cRate,
                'cgst_amount' => $cAmt,
                'sgst_rate' => $sRate,
                'sgst_amount' => $sAmt,
                'igst_rate' => $iRate,
                'igst_amount' => $iAmt,
                'total_amount' => $lineTotal,
            ]);
        }

        $taxableValue = round($taxableValue, 2);
        $cgstAmount = round($cgstAmount, 2);
        $sgstAmount = round($sgstAmount, 2);
        $igstAmount = round($igstAmount, 2);
        $totalTax = round($cgstAmount + $sgstAmount + $igstAmount, 2);
        $rawTotal = $taxableValue + $totalTax;
        $grandTotal = round($rawTotal);
        $roundOff = round($grandTotal - $rawTotal, 2);

        try {
            Database::transaction(function () use (
                $companyId, $quoteId, $customerId, $quoteDate, $validUntil, $posCode,
                $refNo, $kindAttn, $paymentDays, $notes, $terms, $status,
                $taxableValue, $cgstAmount, $sgstAmount, $igstAmount, $totalTax,
                $roundOff, $grandTotal, $processedItems
            ) {
                Database::update(
                    'quotations',
                    [
                        'customer_id' => $customerId,
                        'quotation_date' => $quoteDate,
                        'valid_until' => $validUntil,
                        'reference_no' => $refNo ?: null,
                        'kind_attn' => $kindAttn ?: null,
                        'payment_days' => $paymentDays,
                        'place_of_supply_state_code' => $posCode,
                        'sub_total' => $taxableValue,
                        'taxable_value' => $taxableValue,
                        'cgst_amount' => $cgstAmount,
                        'sgst_amount' => $sgstAmount,
                        'igst_amount' => $igstAmount,
                        'total_tax' => $totalTax,
                        'round_off' => $roundOff,
                        'grand_total' => $grandTotal,
                        'status' => $status,
                        'notes' => $notes,
                        'terms' => $terms,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ? AND company_id = ?',
                    [$quoteId, $companyId]
                );

                // Replace quotation line items
                Database::delete('quotation_items', 'quotation_id = ?', [$quoteId]);

                foreach ($processedItems as $item) {
                    Database::insert('quotation_items', [
                        'company_id' => $companyId,
                        'quotation_id' => $quoteId,
                        'product_id' => $item['product_id'],
                        'category_id' => $item['category_id'],
                        'item_name' => $item['item_name'],
                        'hsn_sac' => $item['hsn_sac'],
                        'quantity' => $item['quantity'],
                        'unit' => 'Pcs',
                        'unit_price' => $item['rate'],
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

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Quotation '{$existing['quotation_number']}' updated successfully.",
                    'quotation_id' => $quoteId,
                    'grand_total' => $grandTotal,
                ]);
            }

            Session::flash('success', "Quotation '{$existing['quotation_number']}' updated successfully.");
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to update quotation: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to update quotation: ' . $e->getMessage());
            return Response::redirect('/sales/quotations/' . $quoteId . '/edit');
        }
    }

    /**
     * Convert Quotation to full GST Tax Invoice.
     * Route: POST /sales/quotations/{id}/convert
     */
    public function convertToInvoice(Request $req, mixed $id = null): Response
    {
        Gate::authorize('invoices.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $quote = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
            }
            Session::flash('error', 'Quotation not found.');
            return Response::redirect('/sales/quotations');
        }

        // Prevent accidental duplicate conversions
        if ((int)($quote['converted_to_invoice'] ?? 0) === 1) {
            $existingInvNum = '';
            if (!empty($quote['converted_invoice_id'])) {
                $inv = Database::selectOne("SELECT invoice_number FROM invoices WHERE id = ?", [$quote['converted_invoice_id']]);
                if ($inv) $existingInvNum = $inv['invoice_number'];
            }
            $errMsg = "Quotation '{$quote['quotation_number']}' has already been converted to Tax Invoice " . ($existingInvNum ? "'{$existingInvNum}'" : "#{$quote['converted_invoice_id']}") . ". Duplicate conversion prevented.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $errMsg], 422);
            }
            Session::flash('error', $errMsg);
            return Response::redirect('/sales/quotations');
        }

        // Fetch customer details
        $customer = Database::selectOne(
            "SELECT id, name, gstin, address_line1, state_code, current_balance 
             FROM customers 
             WHERE id = ?",
            [$quote['customer_id']]
        );

        // Fetch quotation items
        $quoteItems = Database::select(
            "SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC",
            [$quoteId]
        );

        if (empty($quoteItems)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Quotation has no line items to convert.'], 422);
            }
            Session::flash('error', 'Quotation has no line items to convert.');
            return Response::redirect('/sales/quotations');
        }

        try {
            $invoiceId = 0;
            $invoiceNumber = '';

            Database::transaction(function () use (
                $companyId, $branchId, $quote, $quoteId, $customer, $quoteItems,
                &$invoiceId, &$invoiceNumber
            ) {
                // Acquire sequential or reusable Tax Invoice Number
                $targetBranchId = (int)($quote['branch_id'] ?: $branchId);
                $invoiceNumber = InvoiceNumberService::acquireInvoiceNumber($companyId, $targetBranchId);

                $isIntraState = ((string)($quote['place_of_supply_state_code'] ?? '27') === '27');
                $grandTotal = (float)$quote['grand_total'];

                // 1. Insert into invoices
                $invoiceId = (int)Database::insert('invoices', [
                    'company_id' => $companyId,
                    'branch_id' => $quote['branch_id'] ?: $branchId,
                    'customer_id' => $quote['customer_id'],
                    'customer_name' => $customer['name'] ?? 'Direct Customer',
                    'customer_gstin' => $customer['gstin'] ?? null,
                    'billing_address' => $quote['billing_address'] ?: ($customer['address_line1'] ?? null),
                    'shipping_address' => $quote['shipping_address'] ?: null,
                    'invoice_number' => $invoiceNumber,
                    'reference_po_number' => $quote['reference_no'] ?: null,
                    'po_no' => $quote['reference_no'] ?: null,
                    'po_date' => $quote['quotation_date'],
                    'bill_credit_days' => (int)($quote['payment_days'] ?? 0),
                    'place_of_supply' => (string)($quote['place_of_supply_state_code'] ?? '27'),
                    'place_of_supply_state_code' => (string)($quote['place_of_supply_state_code'] ?? '27'),
                    'invoice_date' => date('Y-m-d'),
                    'due_date' => date('Y-m-d', strtotime('+' . (int)($quote['payment_days'] ?? 30) . ' days')),
                    'is_igst' => $isIntraState ? 0 : 1,
                    'sub_total' => (float)$quote['sub_total'],
                    'discount_rate' => 0.00,
                    'discount_amount' => (float)($quote['discount_amount'] ?? 0),
                    'cgst_amount' => (float)($quote['cgst_amount'] ?? 0),
                    'sgst_amount' => (float)($quote['sgst_amount'] ?? 0),
                    'igst_amount' => (float)($quote['igst_amount'] ?? 0),
                    'total_tax' => (float)($quote['total_tax'] ?? 0),
                    'round_off' => (float)($quote['round_off'] ?? 0),
                    'grand_total' => $grandTotal,
                    'amount_paid' => 0.00,
                    'amount_due' => $grandTotal,
                    'status' => 'UNPAID',
                    'payment_status' => 'UNPAID',
                    'source_quotation_id' => $quoteId,
                    'notes' => $quote['notes'] ?: "Converted from Quotation {$quote['quotation_number']}",
                    'terms_and_conditions' => $quote['terms'] ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                InvoiceNumberService::confirmInvoiceNumberUsage($companyId, $invoiceNumber, $invoiceId);


                // 2. Insert line items into invoice_items
                foreach ($quoteItems as $qi) {
                    Database::insert('invoice_items', [
                        'company_id' => $companyId,
                        'invoice_id' => $invoiceId,
                        'product_id' => $qi['product_id'] ?: null,
                        'category_id' => $qi['category_id'] ?: null,
                        'item_name' => $qi['item_name'],
                        'hsn_sac' => $qi['hsn_sac'] ?: '7318',
                        'quantity' => $qi['quantity'],
                        'unit' => $qi['unit'] ?: 'Pcs',
                        'unit_price' => $qi['unit_price'],
                        'discount_rate' => $qi['discount_rate'] ?? 0.0,
                        'discount_amount' => $qi['discount_amount'] ?? 0.0,
                        'taxable_value' => $qi['taxable_value'],
                        'gst_rate' => $qi['gst_rate'],
                        'cgst_rate' => $qi['cgst_rate'],
                        'cgst_amount' => $qi['cgst_amount'],
                        'sgst_rate' => $qi['sgst_rate'],
                        'sgst_amount' => $qi['sgst_amount'],
                        'igst_rate' => $qi['igst_rate'],
                        'igst_amount' => $qi['igst_amount'],
                        'total_amount' => $qi['total_amount'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // 3. Update customer current balance (accounts receivable increase)
                if (!empty($quote['customer_id'])) {
                    $newCustBal = round((float)($customer['current_balance'] ?? 0) + $grandTotal, 2);
                    Database::update(
                        'customers',
                        ['current_balance' => $newCustBal, 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ? AND company_id = ?',
                        [$quote['customer_id'], $companyId]
                    );
                }

                // 4. Update Quotation status to CONVERTED
                Database::update(
                    'quotations',
                    [
                        'status' => 'CONVERTED',
                        'converted_to_invoice' => 1,
                        'converted_invoice_id' => $invoiceId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ? AND company_id = ?',
                    [$quoteId, $companyId]
                );

                // 5. Security audit logging
                AuditLogger::log(
                    action: 'convert',
                    module: 'sales',
                    entityType: 'QUOTATION',
                    entityId: $quoteId,
                    entityLabel: $quote['quotation_number'],
                    newValues: [
                        'status' => 'CONVERTED',
                        'converted_to_invoice' => 1,
                        'converted_invoice_id' => $invoiceId,
                        'invoice_number' => $invoiceNumber,
                    ],
                    status: 'success',
                    notes: "Successfully converted Quotation {$quote['quotation_number']} to Tax Invoice {$invoiceNumber} for ₹" . number_format($grandTotal, 2)
                );
            });

            $msg = "Quotation '{$quote['quotation_number']}' successfully converted to Tax Invoice '{$invoiceNumber}'.";

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => $msg,
                    'quotation_id' => $quoteId,
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'redirect' => '/sales/invoices/' . $invoiceId,
                ], 201);
            }

            Session::flash('success', $msg);
            return Response::redirect('/sales/invoices');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Conversion failed: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Conversion failed: ' . $e->getMessage());
            return Response::redirect('/sales/quotations');
        }
    }

    /**
     * Convert Accepted Quotation to Sales Order.
     * Route: POST /sales/quotations/{id}/convert-to-order
     */
    public function convertToOrder(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.create', $req);

        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));
        $orderService = new \WTSBill\Services\SalesOrderService();

        try {
            $order = $orderService->convertQuotationToOrder($quoteId);

            $msg = "Quotation successfully converted to Sales Order '{$order['order_number']}'.";

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => $msg,
                    'order' => $order,
                    'order_id' => $order['id'],
                    'redirect' => '/sales/orders/' . $order['id'],
                ], 201);
            }

            Session::flash('success', $msg);
            return Response::redirect('/sales/orders/' . $order['id']);
        } catch (\WTSBill\Core\Exceptions\ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Conversion to sales order failed: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Conversion to sales order failed: ' . $e->getMessage());
            return Response::redirect('/sales/quotations');
        }
    }

    /**
     * Duplicate an existing quotation.
     * Route: POST /sales/quotations/{id}/duplicate
     */
    public function duplicate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $orig = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$orig) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Original quotation not found.'], 404);
            }
            Session::flash('error', 'Quotation not found.');
            return Response::redirect('/sales/quotations');
        }

        $origItems = Database::select("SELECT * FROM quotation_items WHERE quotation_id = ?", [$quoteId]);

        try {
            $newId = 0;
            $newNumber = '';

            Database::transaction(function () use ($companyId, $branchId, $orig, $origItems, &$newId, &$newNumber) {
                $yearMonth = date('Ym');
                $countRow = Database::selectOne("SELECT COUNT(*) AS total FROM quotations WHERE company_id = ?", [$companyId]);
                $seq = (int)($countRow['total'] ?? 0) + 1;
                $newNumber = sprintf('QT-%s-%04d', $yearMonth, $seq);

                $newId = (int)Database::insert('quotations', [
                    'company_id' => $companyId,
                    'branch_id' => $orig['branch_id'] ?: $branchId,
                    'customer_id' => $orig['customer_id'],
                    'quotation_number' => $newNumber,
                    'quotation_date' => date('Y-m-d'),
                    'valid_until' => date('Y-m-d', strtotime('+30 days')),
                    'reference_no' => $orig['reference_no'] ? ($orig['reference_no'] . ' (Copy)') : null,
                    'kind_attn' => $orig['kind_attn'],
                    'payment_days' => $orig['payment_days'],
                    'place_of_supply_state_code' => $orig['place_of_supply_state_code'],
                    'billing_address' => $orig['billing_address'],
                    'shipping_address' => $orig['shipping_address'],
                    'sub_total' => $orig['sub_total'],
                    'discount_amount' => $orig['discount_amount'],
                    'taxable_value' => $orig['taxable_value'],
                    'cgst_amount' => $orig['cgst_amount'],
                    'sgst_amount' => $orig['sgst_amount'],
                    'igst_amount' => $orig['igst_amount'],
                    'total_tax' => $orig['total_tax'],
                    'round_off' => $orig['round_off'],
                    'grand_total' => $orig['grand_total'],
                    'status' => 'DRAFT',
                    'notes' => $orig['notes'],
                    'terms' => $orig['terms'],
                    'converted_to_invoice' => 0,
                    'converted_invoice_id' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                foreach ($origItems as $item) {
                    Database::insert('quotation_items', [
                        'company_id' => $companyId,
                        'quotation_id' => $newId,
                        'product_id' => $item['product_id'],
                        'category_id' => $item['category_id'] ?? null,
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

            $msg = "Quotation duplicated successfully as '{$newNumber}'.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg, 'quotation_id' => $newId], 201);
            }
            Session::flash('success', $msg);
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Duplication failed: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Duplication failed: ' . $e->getMessage());
            return Response::redirect('/sales/quotations');
        }
    }

    /**
     * Update quotation status (DRAFT, SENT, ACCEPTED, REJECTED, EXPIRED).
     * Route: POST /sales/quotations/{id}/status
     */
    public function updateStatus(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));
        $newStatus = strtoupper(trim((string)$req->post('status', 'SENT')));

        $validStatuses = ['DRAFT', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'];
        if (!in_array($newStatus, $validStatuses, true)) {
            return Response::json(['success' => false, 'message' => 'Invalid quotation status.'], 422);
        }

        $quote = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
        }

        if ((int)($quote['converted_to_invoice'] ?? 0) === 1) {
            return Response::json(['success' => false, 'message' => 'Converted quotations cannot change status.'], 422);
        }

        Database::update(
            'quotations',
            ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND company_id = ?',
            [$quoteId, $companyId]
        );

        AuditLogger::log(
            action: 'status_change',
            module: 'sales',
            entityType: 'QUOTATION',
            entityId: $quoteId,
            entityLabel: $quote['quotation_number'],
            oldValues: ['status' => $quote['status']],
            newValues: ['status' => $newStatus],
            status: 'success',
            notes: "Changed status of {$quote['quotation_number']} from {$quote['status']} to {$newStatus}"
        );

        return Response::json([
            'success' => true,
            'message' => "Quotation status updated to {$newStatus}.",
            'status' => $newStatus,
        ]);
    }

    /**
     * Delete / Cancel a quotation.
     * Route: POST/DELETE /sales/quotations/{id}/delete
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.delete', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $quote = Database::selectOne(
            "SELECT * FROM quotations WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$quoteId, $companyId]
        );

        if (!$quote) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
            }
            Session::flash('error', 'Quotation not found.');
            return Response::redirect('/sales/quotations');
        }

        // Safeguard: cannot delete converted quotation
        if ((int)($quote['converted_to_invoice'] ?? 0) === 1) {
            $msg = "Cannot delete quotation '{$quote['quotation_number']}' because it is linked to an active Tax Invoice.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/sales/quotations');
        }

        Database::update(
            'quotations',
            [
                'status' => 'CANCELLED',
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ? AND company_id = ?',
            [$quoteId, $companyId]
        );

        AuditLogger::log(
            action: 'delete',
            module: 'sales',
            entityType: 'QUOTATION',
            entityId: $quoteId,
            entityLabel: $quote['quotation_number'],
            oldValues: ['status' => $quote['status']],
            newValues: ['status' => 'CANCELLED'],
            status: 'success',
            notes: "Cancelled and deleted quotation {$quote['quotation_number']}"
        );

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => "Quotation '{$quote['quotation_number']}' deleted successfully."]);
        }

        Session::flash('success', "Quotation '{$quote['quotation_number']}' deleted successfully.");
        return Response::redirect('/sales/quotations');
    }

    /**
     * Render pixel-perfect printable A4 Quotation document.
     * Route: GET /sales/quotations/{id}/print
     */
    public function print(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.print', $req);

        // Delegate to existing pixel-perfect quotationPrint method in SalesController
        $salesController = new SalesController();
        return $salesController->quotationPrint($req, $id);
    }

    /**
     * Helper to detect JSON requests.
     */
    protected function expectsJson(Request $req): bool
    {
        return $req->expectsJson();
    }
}
