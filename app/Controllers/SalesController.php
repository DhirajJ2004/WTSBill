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
use WTSBill\Services\QrCodeService;
use WTSBill\Services\InvoiceNumberService;

/**
 * Sales Controller.
 * Manages tax invoices, quotations, delivery challans, line item tax splits, and persistence.
 */
class SalesController
{
    /**
     * List all tax invoices with comprehensive payment status, date range, branch, and amount filters.
     */
    public function invoicesIndex(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $statusFilter = strtoupper(trim((string)$req->query('status', 'ALL')));
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
        } elseif ($branchQuery === null || $branchQuery === '') {
            $effectiveBranchId = Session::getBranchId() ? (int)Session::getBranchId() : 1;
        }

        // 1. Compute exact status counts for tabs / badges
        $countWhere = ["i.company_id = ?", "i.deleted_at IS NULL", "i.status != 'CANCELLED'"];
        $countParams = [$companyId];
        if ($effectiveBranchId !== null) {
            $countWhere[] = "(i.branch_id = ? OR i.branch_id IS NULL)";
            $countParams[] = $effectiveBranchId;
        }

        $countSql = "SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN i.status = 'UNPAID' THEN 1 END) AS unpaid,
            COUNT(CASE WHEN i.status = 'PARTIAL' THEN 1 END) AS partial,
            COUNT(CASE WHEN i.status = 'PAID' THEN 1 END) AS paid,
            COUNT(CASE WHEN i.status != 'PAID' AND i.due_date IS NOT NULL AND i.due_date < CURDATE() THEN 1 END) AS overdue
        FROM invoices i
        WHERE " . implode(' AND ', $countWhere);

        $statusCounts = Database::selectOne($countSql, $countParams) ?: [
            'total' => 0, 'unpaid' => 0, 'partial' => 0, 'paid' => 0, 'overdue' => 0
        ];

        // 2. Build filtered invoices query
        $where = ["i.company_id = ?", "i.deleted_at IS NULL", "i.status != 'CANCELLED'"];
        $params = [$companyId];

        if ($effectiveBranchId !== null) {
            $where[] = "(i.branch_id = ? OR i.branch_id IS NULL)";
            $params[] = $effectiveBranchId;
        }

        if ($statusFilter === 'UNPAID' || $statusFilter === 'PENDING') {
            $where[] = "i.status = 'UNPAID'";
        } elseif ($statusFilter === 'PARTIAL' || $statusFilter === 'PARTIALLY_PAID') {
            $where[] = "i.status = 'PARTIAL'";
        } elseif ($statusFilter === 'PAID') {
            $where[] = "i.status = 'PAID'";
        } elseif ($statusFilter === 'OVERDUE') {
            $where[] = "(i.status != 'PAID' AND i.due_date IS NOT NULL AND i.due_date < CURDATE())";
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR i.customer_name LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($fromDate)) {
            $where[] = "i.invoice_date >= ?";
            $params[] = $fromDate;
        }
        if (!empty($toDate)) {
            $where[] = "i.invoice_date <= ?";
            $params[] = $toDate;
        }

        if ($minAmount !== null && $minAmount !== '' && is_numeric($minAmount)) {
            $where[] = "i.grand_total >= ?";
            $params[] = (float)$minAmount;
        }
        if ($maxAmount !== null && $maxAmount !== '' && is_numeric($maxAmount)) {
            $where[] = "i.grand_total <= ?";
            $params[] = (float)$maxAmount;
        }

        // 3. Pagination count
        $countQuery = "SELECT COUNT(*) AS cnt FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE " . implode(' AND ', $where);
        $countRow = Database::selectOne($countQuery, $params);
        $totalMatching = (int)($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int)ceil($totalMatching / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // 4. Invoices dataset
        $sql = "SELECT i.id, i.invoice_number, i.customer_id, COALESCE(c.name, i.customer_name, 'Direct Customer') as customer_name,
                       c.phone as customer_phone, c.email as customer_email,
                       i.invoice_date, i.due_date, i.sub_total, i.total_tax, i.grand_total, i.amount_paid, i.amount_due, i.status, i.branch_id
                FROM invoices i 
                LEFT JOIN customers c ON i.customer_id = c.id 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.id DESC
                LIMIT ? OFFSET ?";
        $queryParams = array_merge($params, [$perPage, $offset]);
        $invoices = Database::select($sql, $queryParams);

        $bankAccounts = Database::select(
            "SELECT id, bank_name, account_name, account_number 
             FROM bank_accounts 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY is_primary DESC, id ASC",
            [$companyId]
        );

        $branches = Database::select(
            "SELECT id, name, branch_code FROM branches WHERE company_id = ? AND is_active = 1 ORDER BY id ASC",
            [$companyId]
        );

        if ($req->expectsJson()) {
            return Response::success([
                'invoices' => $invoices,
                'counts' => $statusCounts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalMatching,
                    'total_pages' => $totalPages,
                ],
                'filters' => [
                    'status' => $statusFilter,
                    'search' => $search,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'branch_id' => $branchQuery,
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                ],
            ]);
        }

        $html = View::renderWithLayout('sales/invoices', [
            'title' => 'Tax Invoices',
            'currentUri' => '/sales/invoices',
            'invoices' => $invoices,
            'bankAccounts' => $bankAccounts,
            'branches' => $branches,
            'statusCounts' => $statusCounts,
            'activeStatus' => $statusFilter,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchQuery !== null ? (string)$branchQuery : ($effectiveBranchId !== null ? (string)$effectiveBranchId : 'ALL'),
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
     * Show single invoice details with line items (JSON API).
     */
    public function invoiceShow(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        // If client is a standard browser navigation (not AJAX / API requesting JSON),
        // seamlessly render the full printable A4 Tax Invoice document.
        if (!$this->expectsJson($req)) {
            return $this->invoicePrint($req, $invId);
        }

        $invoice = Database::selectOne(
            "SELECT i.*, COALESCE(c.name, i.customer_name) as customer_name, c.gstin as customer_gstin, c.phone as customer_phone, c.address_line1 as customer_address, c.city as customer_city, c.state as customer_state 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL",
            [$invId, $companyId]
        );

        if (!$invoice) {
            return Response::json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $items = Database::select(
            "SELECT * FROM invoice_items WHERE invoice_id = ? AND company_id = ? ORDER BY id ASC",
            [$invId, $companyId]
        );

        $paymentService = new \WTSBill\Services\PaymentService();
        $payments = $paymentService->getInvoicePaymentHistory($companyId, $invId);

        $bankAccounts = Database::select(
            "SELECT id, bank_name, account_name, account_number, current_balance 
             FROM bank_accounts 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY is_primary DESC, id ASC",
            [$companyId]
        );

        // Compute confirmed paid amount and exact outstanding balance
        $confirmedAlloc = Database::selectOne(
            "SELECT COALESCE(SUM(pa.allocated_amount), 0) AS total_paid
             FROM payment_allocations pa
             JOIN payments py ON pa.payment_id = py.id
             WHERE pa.document_type = 'INVOICE'
               AND pa.document_id = ?
               AND pa.company_id = ?
               AND py.deleted_at IS NULL
               AND py.status IN ('COMPLETED', 'CONFIRMED', 'VERIFIED')",
            [$invId, $companyId]
        );
        $totalPaid = round((float)($confirmedAlloc['total_paid'] ?? $invoice['amount_paid']), 2);
        $grandTotal = round((float)$invoice['grand_total'], 2);
        $outstanding = max(0.00, round($grandTotal - $totalPaid, 2));

        $payStatus = 'UNPAID';
        if ($outstanding <= 0.001) {
            $payStatus = 'PAID';
            $outstanding = 0.00;
        } elseif ($totalPaid > 0.001) {
            $payStatus = 'PARTIAL';
        }

        // Fetch company merchant UPI settings
        $company = Database::selectOne(
            "SELECT c.upi_id, c.upi_display_name, c.payment_instructions, c.name, c.legal_name,
                    b.upi_id AS branch_upi_id
             FROM companies c
             LEFT JOIN branches b ON b.id = ?
             WHERE c.id = ?",
            [(int)($invoice['branch_id'] ?? 1), $companyId]
        );

        $merchantUpi = trim((string)($company['upi_id'] ?? ($company['branch_upi_id'] ?? '')));
        $merchantName = trim((string)($company['upi_display_name'] ?? ($company['legal_name'] ?: $company['name'])));
        $paymentInstructions = trim((string)($company['payment_instructions'] ?? 'Scan this QR code using any supported UPI application to pay the outstanding invoice balance.'));
        $hasMerchantUpi = ($merchantUpi !== '' && preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $merchantUpi));

        $qrSvg = '';
        $upiPayload = '';
        if ($outstanding > 0.001 && $hasMerchantUpi) {
            $upiPayload = QrCodeService::buildUpiPayload(
                $merchantUpi,
                $merchantName,
                $outstanding,
                (string)$invoice['invoice_number']
            );
            $qrSvg = QrCodeService::generateSvg($upiPayload, 110);
        }

        return Response::json([
            'success' => true,
            'invoice' => array_merge($invoice, [
                'amount_paid' => $totalPaid,
                'amount_due' => $outstanding,
                'status' => $payStatus,
                'payment_status' => $payStatus === 'PAID' ? 'PAID' : ($payStatus === 'PARTIAL' ? 'PARTIALLY_PAID' : 'UNPAID'),
            ]),
            'items' => $items,
            'payments' => $payments,
            'bank_accounts' => $bankAccounts,
            'qr_svg' => $qrSvg,
            'upi_payload' => $upiPayload,
            'merchant_upi' => $merchantUpi,
            'merchant_name' => $merchantName,
            'payment_instructions' => $paymentInstructions,
            'has_merchant_upi' => $hasMerchantUpi,
            'outstanding_amount' => $outstanding,
            'total_paid' => $totalPaid,
            'payment_status' => $payStatus,
        ]);
    }

    /**
     * Verify a pending UPI payment or record and verify in one atomic step.
     * Route: POST /sales/invoices/{id}/verify-payment
     */
    public function verifyPayment(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);
        $userName = (string)(Session::get('user_name') ?? 'Admin');
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $paymentId = (int)$req->input('payment_id', 0);
        $externalRef = trim((string)$req->input('transaction_reference', ''));

        $paymentService = new \WTSBill\Services\PaymentService();

        try {
            if ($paymentId > 0) {
                $result = $paymentService->verifyPayment($companyId, $paymentId, $userId, $userName, $externalRef ?: null);
            } else {
                $amount = (float)$req->input('amount', 0);
                $branchId = (int)(Session::getBranchId() ?? 1);
                $result = $paymentService->recordInvoicePayment($companyId, $branchId, $userId, $userName, $invId, [
                    'amount' => $amount,
                    'payment_mode' => 'UPI',
                    'reference_no' => $externalRef,
                    'notes' => 'Verified UPI payment reconciliation',
                ]);
            }

            return Response::json(array_merge(['success' => true], $result), 200);
        } catch (\WTSBill\Core\Exceptions\ValidationException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        }
    }

    /**
     * Record payment installment against invoice.
     * Route: POST /sales/invoices/{id}/payments
     */
    public function recordPayment(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);
        $userName = (string)Session::get('user_name', 'System');
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('invoice_id') ?? $req->param('id') ?? 0)));

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $paymentService = new \WTSBill\Services\PaymentService();
            $result = $paymentService->recordInvoicePayment(
                $companyId,
                $branchId,
                $userId,
                $userName,
                $invId,
                $payload
            );

            if ($this->expectsJson($req)) {
                return Response::json($result, 201);
            }

            Session::flash('success', $result['message']);
            return Response::redirect('/sales/invoices');
        } catch (\WTSBill\Core\Exceptions\ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/invoices');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to record payment: ' . $e->getMessage(),
                ], 500);
            }
            Session::flash('error', 'Failed to record payment: ' . $e->getMessage());
            return Response::redirect('/sales/invoices');
        }
    }

    /**
     * Fetch payment installments history for an invoice (JSON).
     * Route: GET /sales/invoices/{id}/payments
     */
    public function invoicePayments(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));

        $paymentService = new \WTSBill\Services\PaymentService();
        $payments = $paymentService->getInvoicePaymentHistory($companyId, $invId);

        return Response::json([
            'success' => true,
            'invoice_id' => $invId,
            'payments' => $payments,
        ]);
    }

    /**
     * Render pixel-perfect printable A4 Tax Invoice document matching sample layout.
     * Route: /sales/invoices/{id}/print
     */
    public function invoicePrint(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        if ($invId <= 0) {
            return Response::html('<div style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Invalid Invoice ID</h2><p><a href="/sales/invoices">&larr; Back to Invoices</a></p></div>', 404);
        }

        // Single master query loading invoice, buyer, company, branch, bank, and ledger data
        $sql = "SELECT 
            i.id,
            i.company_id,
            i.branch_id,
            i.customer_id,
            i.invoice_number,
            i.invoice_date,
            i.due_date,
            COALESCE(i.po_no, i.reference_po_number, '') AS po_no,
            i.po_date,
            COALESCE(i.challan_no, '') AS challan_no,
            COALESCE(i.bill_credit_days, DATEDIFF(i.due_date, i.invoice_date), 0) AS bill_credit_days,
            COALESCE(i.delivery_by, '') AS delivery_by,
            COALESCE(i.receipt_mode, i.payment_mode, 'Credit') AS receipt_mode,
            COALESCE(i.lr_no, '') AS lr_no,
            COALESCE(i.place_of_supply, i.place_of_supply_state_code, '27') AS place_of_supply,
            COALESCE(i.place_of_supply_state_code, i.place_of_supply, '27') AS place_of_supply_state_code,
            i.is_igst,
            i.sub_total,
            i.discount_rate,
            i.discount_amount,
            i.cgst_amount,
            i.sgst_amount,
            i.igst_amount,
            i.total_tax,
            i.round_off,
            i.grand_total,
            i.amount_paid,
            i.amount_due,
            i.status,
            i.payment_status,
            COALESCE(i.terms_and_conditions, '') AS invoice_terms,
            COALESCE(i.notes, '') AS invoice_notes,

            c.id AS buyer_id,
            COALESCE(NULLIF(c.name, ''), NULLIF(c.company_name, ''), NULLIF(i.customer_name, ''), 'DIRECT CUSTOMER') AS buyer_name,
            COALESCE(NULLIF(c.company_name, ''), NULLIF(c.name, ''), '') AS buyer_company_name,
            COALESCE(NULLIF(c.address_line1, ''), NULLIF(i.billing_address, ''), '') AS buyer_address,
            COALESCE(c.city, '') AS buyer_city,
            COALESCE(c.state, 'Maharashtra') AS buyer_state,
            COALESCE(c.pincode, '') AS buyer_pincode,
            COALESCE(NULLIF(c.gstin, ''), NULLIF(i.customer_gstin, ''), '') AS buyer_gstin,
            COALESCE(c.tax_type, 'Regular') AS buyer_gst_type,
            COALESCE(NULLIF(c.state_code, ''), NULLIF(i.place_of_supply, ''), '27') AS buyer_state_code,
            COALESCE(NULLIF(c.contact_person, ''), NULLIF(c.name, ''), '') AS buyer_contact_person,
            COALESCE(c.phone, '') AS buyer_phone,
            COALESCE(c.email, '') AS buyer_email,
            c.opening_balance AS buyer_opening_balance,
            COALESCE(c.opening_balance_type, 'DEBIT') AS buyer_opening_balance_type,

            comp.id AS company_id,
            COALESCE(comp.name, comp.legal_name, 'Wis Technosavvy Pvt Ltd') AS company_name,
            COALESCE(comp.legal_name, comp.name, 'Wis Technosavvy Pvt Ltd') AS company_legal_name,
            COALESCE(NULLIF(comp.gstin, ''), b.gstin, '') AS company_gstin,
            COALESCE(NULLIF(comp.pan, ''), b.pan, '') AS company_pan,
            COALESCE(comp.msme_no, b.msme_no, 'UDYAM-MH-26-0734598') AS company_msme_no,
            COALESCE(comp.website, b.website, 'http://www.wtsindia.co.in') AS company_website,
            COALESCE(comp.email, b.email, 'info@wtsindia.co.in') AS company_email,
            COALESCE(comp.phone, b.phone, '020 47252364') AS company_phone,
            COALESCE(comp.address_line1, b.address, '') AS company_address_1,
            COALESCE(comp.address_line2, '') AS company_address_2,
            COALESCE(comp.city, b.city, 'Pune') AS company_city,
            COALESCE(comp.state, b.state, 'Maharashtra') AS company_state,
            COALESCE(comp.state_code, b.state_code, '27') AS company_state_code,
            COALESCE(comp.pincode, b.pincode, '411041') AS company_pincode,
            comp.logo_url AS company_logo_url,
            comp.stamp_signature_url AS company_stamp_url,
            COALESCE(i.terms_and_conditions, comp.terms_and_conditions, b.terms_and_conditions, '') AS company_terms,
            COALESCE(comp.upi_id, b.upi_id) AS company_upi_id,
            COALESCE(comp.upi_display_name, comp.legal_name, comp.name, b.name) AS company_upi_name,
            COALESCE(comp.payment_instructions, 'Scan this QR code using any supported UPI application to pay the outstanding invoice balance.') AS payment_instructions,

            b.name AS branch_name,
            b.branch_code AS branch_code,

            bank.bank_name,
            bank.account_name,
            bank.account_number,
            COALESCE(bank.ifsc_code, bank.ifsc, '') AS bank_ifsc,
            bank.branch_name AS bank_branch,

            -- Customer ledger previous balance before this invoice
            (
                SELECT COALESCE(
                    CASE 
                        WHEN c.opening_balance_type IN ('CR', 'CREDIT', 'Credit') THEN -1 * c.opening_balance 
                        ELSE c.opening_balance 
                    END, 0
                )
                + COALESCE(
                    (SELECT SUM(pi.grand_total - pi.amount_paid) 
                     FROM invoices pi 
                     WHERE pi.customer_id = i.customer_id 
                       AND pi.company_id = i.company_id 
                       AND (pi.invoice_date < i.invoice_date OR (pi.invoice_date = i.invoice_date AND pi.id < i.id))
                       AND pi.deleted_at IS NULL), 0
                )
            ) AS buyer_previous_balance

        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN companies comp ON i.company_id = comp.id
        LEFT JOIN branches b ON i.branch_id = b.id
        LEFT JOIN bank_accounts bank ON (
            (b.id IS NOT NULL AND bank.branch_id = b.id AND bank.is_active = 1)
            OR (bank.company_id = i.company_id AND bank.is_primary = 1 AND bank.is_active = 1)
        )
        WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL
        ORDER BY (b.id IS NOT NULL AND bank.branch_id = b.id) DESC, bank.is_primary DESC, bank.id DESC
        LIMIT 1";

        $invData = Database::selectOne($sql, [$invId, $companyId]);

        if (!$invData) {
            return Response::html('<div style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Invoice Not Found</h2><p>The requested invoice could not be found or you lack permission to view it.</p><p><a href="/sales/invoices">&larr; Back to Invoices</a></p></div>', 404);
        }

        // Fetch line items for this specific invoice
        $rawItems = Database::select(
            "SELECT ii.*, p.sku as product_sku, p.description as product_description 
             FROM invoice_items ii 
             LEFT JOIN products p ON ii.product_id = p.id 
             WHERE ii.invoice_id = ? AND ii.company_id = ? 
             ORDER BY ii.id ASC",
            [$invId, $companyId]
        );

        $sellerStateCode = (string)($invData['company_state_code'] ?? '27');
        $buyerPosCode = (string)($invData['place_of_supply_state_code'] ?? ($invData['place_of_supply'] ?? '27'));
        $isIntraState = ($sellerStateCode === $buyerPosCode) && ((int)($invData['is_igst'] ?? 0) === 0);

        $computedItems = [];
        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

        foreach ($rawItems as $it) {
            $qty = (float)($it['quantity'] ?? 1);
            $price = (float)($it['unit_price'] ?? 0);
            $gstRate = (float)($it['gst_rate'] ?? 18);
            $discRate = (float)($it['discount_rate'] ?? 0);
            $discAmt = (float)($it['discount_amount'] ?? 0);

            if ($discAmt <= 0 && $discRate > 0) {
                $discAmt = round(($qty * $price) * ($discRate / 100), 2);
            }

            $lineTaxable = round(($qty * $price) - $discAmt, 2);
            $priceWithGst = round($price * (1 + ($gstRate / 100)), 2);

            if ($isIntraState) {
                $cgstRate = round($gstRate / 2, 2);
                $sgstRate = round($gstRate / 2, 2);
                $igstRate = 0.0;
                $cgstAmount = round($lineTaxable * ($cgstRate / 100), 2);
                $sgstAmount = round($lineTaxable * ($sgstRate / 100), 2);
                $igstAmount = 0.0;
            } else {
                $cgstRate = 0.0;
                $sgstRate = 0.0;
                $igstRate = $gstRate;
                $cgstAmount = 0.0;
                $sgstAmount = 0.0;
                $igstAmount = round($lineTaxable * ($igstRate / 100), 2);
            }

            $lineTotal = round($lineTaxable + $cgstAmount + $sgstAmount + $igstAmount, 2);

            $totalTaxable += $lineTaxable;
            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;

            $computedItems[] = [
                'item_name' => $it['item_name'],
                'description' => $it['product_description'] ?? '',
                'item_id_code' => $it['product_sku'] ?: ($it['product_id'] ? 'IDP' . str_pad((string)$it['product_id'], 4, '0', STR_PAD_LEFT) : ''),
                'hsn_sac' => $it['hsn_sac'] ?? '',
                'quantity' => $qty,
                'unit' => $it['unit'] ?: 'NOS',
                'discount_rate' => $discRate,
                'discount_amount' => $discAmt,
                'unit_price' => $price,
                'price_with_gst' => $priceWithGst,
                'taxable_rate' => $price,
                'taxable_value' => $lineTaxable,
                'gst_rate' => $gstRate,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmount,
                'line_total' => $lineTotal,
            ];
        }

        $totalGst = round($totalCgst + $totalSgst + $totalIgst, 2);
        $rawGrand = $totalTaxable + $totalGst;
        $grandTotal = (float)($invData['grand_total'] > 0 ? $invData['grand_total'] : round($rawGrand));
        $roundOff = round($grandTotal - $rawGrand, 2);
        $roundOffDisplay = ($roundOff == 0.0) ? '0' : (($roundOff > 0 ? '+' : '') . sprintf('%.2f', $roundOff));

        // Ledger Summary calculation
        $prevBalance = (float)($invData['buyer_previous_balance'] ?? 0);
        if ($prevBalance > 0.001) {
            $prevLabel = '(Debit)';
            $prevAmountFormatted = format_inr_amount($prevBalance, false, 0);
        } elseif ($prevBalance < -0.001) {
            $prevLabel = '(Credit)';
            $prevAmountFormatted = format_inr_amount(abs($prevBalance), false, 0);
        } else {
            $prevLabel = '(Credit)';
            $prevAmountFormatted = '0';
        }

        // Fetch confirmed paid amount from payment_allocations
        $confirmedAlloc = Database::selectOne(
            "SELECT COALESCE(SUM(pa.allocated_amount), 0) AS total_paid
             FROM payment_allocations pa
             JOIN payments py ON pa.payment_id = py.id
             WHERE pa.document_type = 'INVOICE'
               AND pa.document_id = ?
               AND pa.company_id = ?
               AND py.deleted_at IS NULL
               AND py.status IN ('COMPLETED', 'CONFIRMED', 'VERIFIED')",
            [$invId, $companyId]
        );
        $amountPaid = round((float)($confirmedAlloc['total_paid'] ?? $invData['amount_paid']), 2);
        $amountDue = max(0.00, round($grandTotal - $amountPaid, 2));
        $statusUpper = strtoupper((string)($invData['status'] ?? ''));

        if ($amountDue <= 0.001 || $statusUpper === 'PAID') {
            $payStatus = 'Paid';
            $amountDue = 0.00;
            $newBalance = $prevBalance;
        } elseif ($amountPaid > 0.001 || $statusUpper === 'PARTIAL') {
            $payStatus = 'Partially Paid';
            $newBalance = $prevBalance + $amountDue;
        } else {
            $payStatus = 'Unpaid';
            $newBalance = $prevBalance + $grandTotal;
        }

        if ($newBalance > 0.001) {
            $newLabel = '(Debit)';
            $newAmountFormatted = format_inr_amount($newBalance, false, 0);
        } elseif ($newBalance < -0.001) {
            $newLabel = '(Credit)';
            $newAmountFormatted = format_inr_amount(abs($newBalance), false, 0);
        } else {
            $newLabel = '(Debit)';
            $newAmountFormatted = '0';
        }

        $invoiceAmtFormatted = '+' . format_inr_amount($grandTotal, false, 0);

        // Due date calculation
        $creditDays = (int)($invData['bill_credit_days'] ?? 0);
        $invDateStr = $invData['invoice_date'] ?? date('Y-m-d');
        if (!empty($invData['due_date']) && $creditDays <= 0) {
            $dueDateStr = $invData['due_date'];
        } else {
            $dueDateStr = date('Y-m-d', strtotime($invDateStr . " +{$creditDays} days"));
        }

        $posInfo = gst_state_info($invData['place_of_supply'] ?? $invData['buyer_state_code']);
        $amountInWords = amount_to_words_inr($grandTotal);

        // Copy label selector
        $copyKey = strtolower((string)($req->query('copy') ?? 'original'));
        if ($copyKey === 'duplicate') {
            $copyLabel = 'Duplicate for Transporter';
        } elseif ($copyKey === 'triplicate') {
            $copyLabel = 'Triplicate for Supplier';
        } else {
            $copyKey = 'original';
            $copyLabel = 'Original for Recipient';
        }

        // Dynamic UPI QR code generation: strictly from configured merchant details and exact remaining balance
        $upiVpa = trim((string)($invData['company_upi_id'] ?? ''));
        $upiName = trim((string)($invData['company_upi_name'] ?? ($invData['company_legal_name'] ?: $invData['company_name'])));
        $paymentInstructions = trim((string)($invData['payment_instructions'] ?? 'Scan this QR code using any supported UPI application to pay the outstanding invoice balance.'));
        $hasMerchantUpi = ($upiVpa !== '' && preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $upiVpa));

        $qrSvg = '';
        $upiPayload = '';
        if ($amountDue > 0.001 && $hasMerchantUpi) {
            $upiPayload = QrCodeService::buildUpiPayload(
                $upiVpa,
                $upiName,
                $amountDue,
                (string)$invData['invoice_number']
            );
            $qrSvg = QrCodeService::generateSvg($upiPayload, 95);
        }

        // Render standalone printable view
        $html = View::render('sales/print', [
            'invoice' => array_merge($invData, [
                'pay_status' => $payStatus,
                'due_date' => $dueDateStr,
                'amount_paid' => $amountPaid,
                'amount_due' => $amountDue,
            ]),
            'qrSvg' => $qrSvg,
            'upiPayload' => $upiPayload,
            'hasMerchantUpi' => $hasMerchantUpi,
            'merchantUpi' => $upiVpa,
            'merchantName' => $upiName,
            'paymentInstructions' => $paymentInstructions,
            'amountDue' => $amountDue,
            'amountPaid' => $amountPaid,
            'payStatus' => $payStatus,
            'items' => $computedItems,
            'buyer' => [
                'name' => $invData['buyer_name'],
                'address' => $invData['buyer_address'],
                'city' => $invData['buyer_city'],
                'state' => $invData['buyer_state'],
                'pincode' => $invData['buyer_pincode'],
                'gstin' => $invData['buyer_gstin'],
                'gst_type' => $invData['buyer_gst_type'],
                'pos_state' => $posInfo['name'],
                'pos_code' => $posInfo['code'],
                'contact_person' => $invData['buyer_contact_person'],
            ],
            'seller' => [
                'company_name' => $invData['company_name'],
                'company_legal_name' => $invData['company_legal_name'],
                'gstin' => $invData['company_gstin'],
                'pan' => $invData['company_pan'],
                'msme_no' => $invData['company_msme_no'],
                'website' => $invData['company_website'],
                'email' => $invData['company_email'],
                'phone' => $invData['company_phone'],
                'address_line1' => $invData['company_address_1'],
                'address_line2' => $invData['company_address_2'],
                'city' => $invData['company_city'],
                'state' => $invData['company_state'],
                'pincode' => $invData['company_pincode'],
                'logo_url' => $invData['company_logo_url'],
                'stamp_url' => $invData['company_stamp_url'],
                'terms' => $invData['company_terms'],
            ],
            'bank' => [
                'bank_name' => $invData['bank_name'] ?: 'KOTAK MAHINDRA BANK',
                'account_name' => $invData['account_name'] ?: ($invData['company_legal_name'] ?: 'WIS TECHNOSAVVY PRIVATE LIMITED'),
                'account_number' => $invData['account_number'] ?: '9881032297',
                'ifsc' => $invData['bank_ifsc'] ?: 'KKBK0001986',
            ],
            'ledger' => [
                'prev_label' => $prevLabel,
                'prev_amount' => $prevAmountFormatted,
                'invoice_amount_formatted' => $invoiceAmtFormatted,
                'new_label' => $newLabel,
                'new_amount' => $newAmountFormatted,
            ],
            'taxSummary' => [
                'taxable_amt' => $totalTaxable,
                'cgst_amt' => $totalCgst,
                'sgst_amt' => $totalSgst,
                'igst_amt' => $totalIgst,
                'total_gst' => $totalGst,
                'round_off_display' => $roundOffDisplay,
                'grand_total' => $grandTotal,
            ],
            'amountInWords' => $amountInWords,
            'qrSvg' => $qrSvg,
            'copyLabel' => $copyLabel,
            'copyKey' => $copyKey,
            'isIntraState' => $isIntraState,
        ]);

        return Response::html($html);
    }

    /**
     * Retrieve products filtered by category ID (JSON AJAX endpoint for dynamic invoice line items).
     * Route: GET /api/categories/{id}/products
     */
    public function categoryProductsApi(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $activeBranchId = (int)(Session::getBranchId() ?? 1);

        $categoryId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->query('category_id') ?? 0)));

        if ($categoryId <= 0) {
            return Response::json([
                'success' => false,
                'message' => 'Please provide a valid category ID.',
                'products' => [],
                'count' => 0,
            ], 422);
        }

        // Verify category exists and is ACTIVE for the active company scope
        $category = Database::selectOne(
            "SELECT id, name, code, status FROM categories WHERE id = ? AND company_id = ? AND status = 'ACTIVE'",
            [$categoryId, $companyId]
        );

        if (!$category) {
            return Response::json([
                'success' => false,
                'message' => 'Category not found, inactive, or unauthorized.',
                'products' => [],
                'count' => 0,
            ], 404);
        }

        // Fetch active, available products belonging strictly to this category
        $products = Database::select(
            "SELECT id, category_id, name, sku, hsn_sac,
                    COALESCE(selling_price, sales_price, 0) as price,
                    tax_rate, gst_rate, unit, current_stock, is_active
             FROM products 
             WHERE category_id = ? 
               AND company_id = ? 
               AND is_active = 1 
               AND deleted_at IS NULL
             ORDER BY name ASC",
            [$categoryId, $companyId]
        );

        $formatted = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'category_id' => (int)$p['category_id'],
                'name' => (string)$p['name'],
                'sku' => (string)$p['sku'],
                'price' => (float)$p['price'],
                'selling_price' => (float)$p['price'],
                'hsn' => (string)($p['hsn_sac'] ?: '7318'),
                'hsn_sac' => (string)($p['hsn_sac'] ?: '7318'),
                'tax' => (float)($p['tax_rate'] ?? 18.0),
                'tax_rate' => (float)($p['tax_rate'] ?? 18.0),
                'gst_rate' => (float)($p['gst_rate'] ?? $p['tax_rate'] ?? 18.0),
                'unit' => (string)($p['unit'] ?: 'Pcs'),
                'current_stock' => (float)($p['current_stock'] ?? 0.0),
            ];
        }, $products);

        return Response::json([
            'success' => true,
            'category' => [
                'id' => (int)$category['id'],
                'name' => (string)$category['name'],
                'code' => (string)($category['code'] ?? ''),
            ],
            'products' => $formatted,
            'count' => count($formatted),
            'branch_id' => $activeBranchId,
        ]);
    }

    /**
     * Render sales invoice creation page.
     */
    public function invoiceCreateForm(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;

        $categories = Database::select(
            "SELECT id, name, code, description 
             FROM categories 
             WHERE company_id = ? AND status = 'ACTIVE' 
             ORDER BY name ASC",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name, gstin, phone, state_code FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, category_id, name, sku, COALESCE(selling_price, sales_price, 0) as selling_price, hsn_sac, tax_rate, current_stock, unit 
             FROM products 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL
             ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/create', [
            'title' => 'Generate Tax Invoice',
            'currentUri' => '/sales/invoices/create',
            'categories' => $categories,
            'customers' => $customers,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new tax invoice with line items, tax split, and transaction safety.
     */
    public function invoiceStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $invoiceDate = trim((string)($payload['invoice_date'] ?? date('Y-m-d')));
        $dueDate = trim((string)($payload['due_date'] ?? date('Y-m-d', strtotime('+30 days'))));
        $placeOfSupply = trim((string)($payload['place_of_supply'] ?? '27'));
        $notes = trim((string)($payload['notes'] ?? ''));

        // Extended GST Invoice Metadata Fields
        $poNo = trim((string)($payload['po_no'] ?? ($payload['reference_po_number'] ?? '')));
        $poDate = !empty($payload['po_date']) ? trim((string)$payload['po_date']) : null;
        $challanNo = trim((string)($payload['challan_no'] ?? ''));
        $billCreditDays = isset($payload['bill_credit_days']) ? (int)$payload['bill_credit_days'] : 0;
        $deliveryBy = trim((string)($payload['delivery_by'] ?? ''));
        $receiptMode = trim((string)($payload['receipt_mode'] ?? 'Credit'));
        $lrNo = trim((string)($payload['lr_no'] ?? ''));
        $posStateCode = trim((string)($payload['place_of_supply_state_code'] ?? $placeOfSupply));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a valid customer.'], 422);
            }
            Session::flash('error', 'Please select a valid customer.');
            return Response::redirect('/sales/invoices/create');
        }

        // Multi-line items support (with backward-compatible fallback to single line item)
        $rawItems = $payload['items'] ?? null;
        $items = [];

        if (is_array($rawItems) && !empty($rawItems)) {
            foreach ($rawItems as $it) {
                if (!is_array($it)) continue;
                $pId = (int)($it['product_id'] ?? 0);
                $catId = (int)($it['category_id'] ?? 0);
                $q = (float)($it['quantity'] ?? 1);
                $r = (float)($it['rate'] ?? 0);
                $g = (float)($it['gst_rate'] ?? 18);
                $h = trim((string)($it['hsn_sac'] ?? ''));
                $d = (float)($it['discount_rate'] ?? 0);

                if ($pId > 0) {
                    $prod = Database::selectOne(
                        "SELECT id, category_id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) as price, tax_rate, is_active 
                         FROM products 
                         WHERE id = ? AND company_id = ? AND is_active = 1 AND deleted_at IS NULL",
                        [$pId, $companyId]
                    );

                    if (!$prod) {
                        if ($this->expectsJson($req)) {
                            return Response::json(['success' => false, 'message' => "Selected product (ID: {$pId}) is inactive, unauthorized, or does not exist."], 422);
                        }
                        Session::flash('error', "Selected product (ID: {$pId}) is invalid or inactive.");
                        return Response::redirect('/sales/invoices/create');
                    }

                    if ($catId > 0) {
                        $cat = Database::selectOne(
                            "SELECT id, name, status FROM categories WHERE id = ? AND company_id = ? AND status = 'ACTIVE'",
                            [$catId, $companyId]
                        );
                        if (!$cat) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Selected category (ID: {$catId}) is invalid or inactive."], 422);
                            }
                            Session::flash('error', "Selected category is invalid or inactive.");
                            return Response::redirect('/sales/invoices/create');
                        }
                        if ((int)$prod['category_id'] !== $catId) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Product '{$prod['name']}' does not belong to category '{$cat['name']}'."], 422);
                            }
                            Session::flash('error', "Product '{$prod['name']}' does not belong to category '{$cat['name']}'.");
                            return Response::redirect('/sales/invoices/create');
                        }
                    } else {
                        $catId = (int)($prod['category_id'] ?? 0);
                    }

                    if ($r <= 0) {
                        $r = (float)$prod['price'];
                    }
                    if (empty($h) && !empty($prod['hsn_sac'])) {
                        $h = (string)$prod['hsn_sac'];
                    }
                }

                if (empty($h)) $h = '7318';
                if ($r <= 0) $r = 100.00;
                if ($q <= 0) $q = 1.0;

                $items[] = [
                    'category_id' => $catId > 0 ? $catId : null,
                    'product_id' => $pId,
                    'quantity' => $q,
                    'rate' => $r,
                    'gst_rate' => $g,
                    'hsn_sac' => $h,
                    'discount_rate' => $d,
                ];
            }
        }

        if (empty($items)) {
            $productId = (int)($payload['product_id'] ?? 0);
            $catId = (int)($payload['category_id'] ?? 0);
            $qty = (float)($payload['quantity'] ?? 1);
            $rate = (float)($payload['rate'] ?? 0);
            $gstRate = (float)($payload['gst_rate'] ?? 18);
            $hsn = trim((string)($payload['hsn_sac'] ?? ''));

            if ($productId > 0) {
                $p = Database::selectOne(
                    "SELECT id, category_id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) as price, tax_rate, is_active 
                     FROM products 
                     WHERE id = ? AND company_id = ? AND is_active = 1 AND deleted_at IS NULL",
                    [$productId, $companyId]
                );
                if ($p) {
                    if ($catId > 0) {
                        $cat = Database::selectOne(
                            "SELECT id, name, status FROM categories WHERE id = ? AND company_id = ? AND status = 'ACTIVE'",
                            [$catId, $companyId]
                        );
                        if (!$cat || (int)$p['category_id'] !== $catId) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Product does not belong to the selected category."], 422);
                            }
                            Session::flash('error', "Product does not belong to the selected category.");
                            return Response::redirect('/sales/invoices/create');
                        }
                    } else {
                        $catId = (int)($p['category_id'] ?? 0);
                    }
                    if ($rate <= 0) {
                        $rate = (float)$p['price'];
                    }
                    if (empty($hsn) && !empty($p['hsn_sac'])) {
                        $hsn = (string)$p['hsn_sac'];
                    }
                }
            }
            if (empty($hsn)) $hsn = '7318';
            if ($rate <= 0) $rate = 100.00;
            if ($qty <= 0) $qty = 1.0;

            $items[] = [
                'category_id' => $catId > 0 ? $catId : null,
                'product_id' => $productId,
                'quantity' => $qty,
                'rate' => $rate,
                'gst_rate' => $gstRate,
                'hsn_sac' => $hsn,
                'discount_rate' => 0.0,
            ];
        }

        // Intra-state (27 Maharashtra) vs Inter-state
        $isIntraState = ($placeOfSupply === '27');
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

        // Fetch customer details
        $customer = Database::selectOne("SELECT name, gstin, address_line1 FROM customers WHERE id = ?", [$customerId]);

        $customInvNum = trim((string)($req->input('invoice_number') ?? $req->post('invoice_number') ?? ''));

        try {
            $invoiceId = 0;
            $invoiceNumber = '';

            Database::transaction(function() use (
                $customInvNum,
                $companyId, $customerId, $customer, $invoiceDate, $dueDate, $placeOfSupply,
                $isIntraState, $taxableValue, $cgstAmount, $sgstAmount, $igstAmount, $totalTax,
                $roundOff, $grandTotal, $notes, $processedItems, $poNo, $poDate, $challanNo, $billCreditDays,
                $deliveryBy, $receiptMode, $lrNo, $posStateCode, &$invoiceId, &$invoiceNumber
            ) {
                // Acquire sequential or reusable invoice number
                $branchId = (int)(Session::getBranchId() ?? 1);
                if (!empty($customInvNum)) {
                    $activeConflict = Database::selectOne(
                        "SELECT id FROM invoices WHERE company_id = ? AND invoice_number = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE",
                        [$companyId, $customInvNum]
                    );
                    if ($activeConflict) {
                        throw new ValidationException(['invoice_number' => "Invoice number '{$customInvNum}' is already in use by an active invoice."]);
                    }
                    $invoiceNumber = $customInvNum;
                } else {
                    $invoiceNumber = InvoiceNumberService::acquireInvoiceNumber($companyId, $branchId);
                }

                // Insert into invoices
                $invoiceId = (int)Database::insert('invoices', [
                    'company_id' => $companyId,
                    'branch_id' => (int)(Session::getBranchId() ?? 1),
                    'customer_id' => $customerId,
                    'customer_name' => $customer['name'] ?? 'Direct Customer',
                    'customer_gstin' => $customer['gstin'] ?? null,
                    'billing_address' => $customer['address_line1'] ?? null,
                    'invoice_number' => $invoiceNumber,
                    'reference_po_number' => $poNo ?: null,
                    'po_no' => $poNo ?: null,
                    'po_date' => $poDate ?: null,
                    'challan_no' => $challanNo ?: null,
                    'bill_credit_days' => $billCreditDays,
                    'delivery_by' => $deliveryBy ?: null,
                    'receipt_mode' => $receiptMode ?: 'Credit',
                    'lr_no' => $lrNo ?: null,
                    'place_of_supply' => $placeOfSupply,
                    'place_of_supply_state_code' => $posStateCode ?: $placeOfSupply,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'is_igst' => $isIntraState ? 0 : 1,
                    'sub_total' => $taxableValue,
                    'discount_rate' => 0.0,
                    'discount_amount' => 0.0,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => $igstAmount,
                    'total_tax' => $totalTax,
                    'round_off' => $roundOff,
                    'grand_total' => $grandTotal,
                    'amount_paid' => 0.0,
                    'amount_due' => $grandTotal,
                    'status' => 'UNPAID',
                    'notes' => $notes ?: 'Thank you for your business.',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                InvoiceNumberService::confirmInvoiceNumberUsage($companyId, $invoiceNumber, $invoiceId);

                // Insert line items
                foreach ($processedItems as $item) {
                    $pId = $item['product_id'];
                    $productName = 'Standard Industrial Hardware Item';
                    if ($pId > 0) {
                        $p = Database::selectOne("SELECT name FROM products WHERE id = ?", [$pId]);
                        if ($p) {
                            $productName = $p['name'];
                        }
                    }

                    Database::insert('invoice_items', [
                        'company_id' => $companyId,
                        'invoice_id' => $invoiceId,
                        'product_id' => $pId > 0 ? $pId : null,
                        'category_id' => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                        'item_name' => $productName,
                        'hsn_sac' => $item['hsn_sac'] ?: '7318',
                        'quantity' => $item['quantity'],
                        'unit' => 'Pcs',
                        'unit_price' => $item['rate'],
                        'discount_rate' => $item['discount_rate'] ?? 0.0,
                        'discount_amount' => $item['discount_amount'] ?? 0.0,
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

                // Update customer current balance
                $custRow = Database::selectOne("SELECT current_balance FROM customers WHERE id = ?", [$customerId]);
                $newBal = (float)($custRow['current_balance'] ?? 0) + $grandTotal;
                Database::update(
                    'customers',
                    ['current_balance' => $newBal],
                    'id = ? AND company_id = ?',
                    [$customerId, $companyId]
                );
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Invoice '{$invoiceNumber}' generated successfully.",
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                ], 201);
            }

            Session::flash('success', "Invoice '{$invoiceNumber}' generated successfully.");
            return Response::redirect('/sales/invoices');
        } catch (ValidationException $e) {
            $errs = $e->getErrors();
            $firstErr = !empty($errs) ? (is_array(reset($errs)) ? reset($errs)[0] : reset($errs)) : $e->getMessage();
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $firstErr,
                    'errors' => $errs,
                ], 422);
            }
            Session::flash('error', $firstErr);
            return Response::redirect('/sales/invoices/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to create invoice: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to create invoice: ' . $e->getMessage());
            return Response::redirect('/sales/invoices/create');
        }
    }

    /**
     * Render sales invoice edit page.
     * Route: GET /sales/invoices/{id}/edit
     */
    public function invoiceEditForm(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$invId, $companyId]
        );

        if (!$invoice) {
            Session::flash('error', 'Invoice not found or already deleted.');
            return Response::redirect('/sales/invoices');
        }

        $categories = Database::select(
            "SELECT id, name, code, description 
             FROM categories 
             WHERE company_id = ? AND status = 'ACTIVE' 
             ORDER BY name ASC",
            [$companyId]
        );

        $items = Database::select(
            "SELECT ii.*, COALESCE(ii.category_id, p.category_id) AS category_id, p.name AS product_name 
             FROM invoice_items ii 
             LEFT JOIN products p ON ii.product_id = p.id 
             WHERE ii.invoice_id = ? AND ii.company_id = ? 
             ORDER BY ii.id ASC",
            [$invId, $companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name, gstin, phone, state_code FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, category_id, name, sku, COALESCE(selling_price, sales_price, 0) as selling_price, hsn_sac, tax_rate, current_stock, unit 
             FROM products 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/edit', [
            'title' => "Edit Invoice #{$invoice['invoice_number']}",
            'currentUri' => '/sales/invoices',
            'invoice' => $invoice,
            'categories' => $categories,
            'items' => $items,
            'customers' => $customers,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Update existing tax invoice with line items, tax recalculation, and ledger balance adjustments.
     * Route: POST /sales/invoices/{id}/update
     */
    public function invoiceUpdate(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$invId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Invoice not found.'], 404);
            }
            Session::flash('error', 'Invoice not found.');
            return Response::redirect('/sales/invoices');
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $customerId = (int)($payload['customer_id'] ?? $existing['customer_id']);
        $invoiceDate = trim((string)($payload['invoice_date'] ?? $existing['invoice_date']));
        $dueDate = trim((string)($payload['due_date'] ?? $existing['due_date']));
        $placeOfSupply = trim((string)($payload['place_of_supply'] ?? $existing['place_of_supply']));
        $poNo = trim((string)($payload['po_no'] ?? ''));
        $poDate = !empty($payload['po_date']) ? trim((string)$payload['po_date']) : null;
        $challanNo = trim((string)($payload['challan_no'] ?? ''));
        $billCreditDays = (int)($payload['bill_credit_days'] ?? 0);
        $deliveryBy = trim((string)($payload['delivery_by'] ?? ''));
        $receiptMode = trim((string)($payload['receipt_mode'] ?? 'Credit'));
        $lrNo = trim((string)($payload['lr_no'] ?? ''));
        $posStateCode = trim((string)($payload['place_of_supply_state_code'] ?? $placeOfSupply));
        $notes = trim((string)($payload['notes'] ?? $existing['notes']));

        $rawItems = $payload['items'] ?? null;
        $items = [];

        if (is_array($rawItems) && !empty($rawItems)) {
            foreach ($rawItems as $it) {
                if (!is_array($it)) continue;
                $pId = (int)($it['product_id'] ?? 0);
                $catId = (int)($it['category_id'] ?? 0);
                $q = (float)($it['quantity'] ?? 1);
                $r = (float)($it['rate'] ?? 0);
                $g = (float)($it['gst_rate'] ?? 18);
                $h = trim((string)($it['hsn_sac'] ?? ''));
                $d = (float)($it['discount_rate'] ?? 0);

                if ($pId > 0) {
                    $prod = Database::selectOne(
                        "SELECT id, category_id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) as price, tax_rate, is_active 
                         FROM products 
                         WHERE id = ? AND company_id = ? AND is_active = 1 AND deleted_at IS NULL",
                        [$pId, $companyId]
                    );

                    if (!$prod) {
                        if ($this->expectsJson($req)) {
                            return Response::json(['success' => false, 'message' => "Selected product (ID: {$pId}) is inactive, unauthorized, or does not exist."], 422);
                        }
                        Session::flash('error', "Selected product (ID: {$pId}) is invalid or inactive.");
                        return Response::redirect("/sales/invoices/{$invId}/edit");
                    }

                    if ($catId > 0) {
                        $cat = Database::selectOne(
                            "SELECT id, name, status FROM categories WHERE id = ? AND company_id = ? AND status = 'ACTIVE'",
                            [$catId, $companyId]
                        );
                        if (!$cat) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Selected category (ID: {$catId}) is invalid or inactive."], 422);
                            }
                            Session::flash('error', "Selected category is invalid or inactive.");
                            return Response::redirect("/sales/invoices/{$invId}/edit");
                        }
                        if ((int)$prod['category_id'] !== $catId) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Product '{$prod['name']}' does not belong to category '{$cat['name']}'."], 422);
                            }
                            Session::flash('error', "Product '{$prod['name']}' does not belong to category '{$cat['name']}'.");
                            return Response::redirect("/sales/invoices/{$invId}/edit");
                        }
                    } else {
                        $catId = (int)($prod['category_id'] ?? 0);
                    }

                    if ($r <= 0) {
                        $r = (float)$prod['price'];
                    }
                    if (empty($h) && !empty($prod['hsn_sac'])) {
                        $h = (string)$prod['hsn_sac'];
                    }
                }

                if (empty($h)) $h = '7318';
                if ($r <= 0) $r = 100.00;
                if ($q <= 0) $q = 1.0;

                $items[] = [
                    'category_id' => $catId > 0 ? $catId : null,
                    'product_id' => $pId,
                    'quantity' => $q,
                    'rate' => $r,
                    'gst_rate' => $g,
                    'hsn_sac' => $h,
                    'discount_rate' => $d,
                ];
            }
        }

        if (empty($items)) {
            $productId = (int)($payload['product_id'] ?? 0);
            $catId = (int)($payload['category_id'] ?? 0);
            $qty = (float)($payload['quantity'] ?? 1);
            $rate = (float)($payload['rate'] ?? 0);
            $gstRate = (float)($payload['gst_rate'] ?? 18);
            $hsn = trim((string)($payload['hsn_sac'] ?? ''));

            if ($productId > 0) {
                $p = Database::selectOne(
                    "SELECT id, category_id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) as price, tax_rate, is_active 
                     FROM products 
                     WHERE id = ? AND company_id = ? AND is_active = 1 AND deleted_at IS NULL",
                    [$productId, $companyId]
                );
                if ($p) {
                    if ($catId > 0) {
                        $cat = Database::selectOne(
                            "SELECT id, name, status FROM categories WHERE id = ? AND company_id = ? AND status = 'ACTIVE'",
                            [$catId, $companyId]
                        );
                        if (!$cat || (int)$p['category_id'] !== $catId) {
                            if ($this->expectsJson($req)) {
                                return Response::json(['success' => false, 'message' => "Product does not belong to the selected category."], 422);
                            }
                            Session::flash('error', "Product does not belong to the selected category.");
                            return Response::redirect("/sales/invoices/{$invId}/edit");
                        }
                    } else {
                        $catId = (int)($p['category_id'] ?? 0);
                    }
                    if ($rate <= 0) $rate = (float)$p['price'];
                    if (empty($hsn) && !empty($p['hsn_sac'])) {
                        $hsn = (string)$p['hsn_sac'];
                    }
                }
            }
            if (empty($hsn)) $hsn = '7318';
            if ($rate <= 0) $rate = 100.00;
            if ($qty <= 0) $qty = 1.0;

            $items[] = [
                'category_id' => $catId > 0 ? $catId : null,
                'product_id' => $productId,
                'quantity' => $qty,
                'rate' => $rate,
                'gst_rate' => $gstRate,
                'hsn_sac' => $hsn,
                'discount_rate' => 0.0,
            ];
        }

        $isIntraState = ($placeOfSupply === '27');
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

        $customer = Database::selectOne("SELECT name, gstin, address_line1 FROM customers WHERE id = ?", [$customerId]);

        try {
            Database::transaction(function() use (
                $companyId, $invId, $existing, $customerId, $customer, $invoiceDate, $dueDate,
                $placeOfSupply, $isIntraState, $taxableValue, $cgstAmount, $sgstAmount, $igstAmount,
                $totalTax, $roundOff, $grandTotal, $notes, $processedItems, $poNo, $poDate,
                $challanNo, $billCreditDays, $deliveryBy, $receiptMode, $lrNo, $posStateCode
            ) {
                $oldGrandTotal = (float)$existing['grand_total'];
                $oldCustomerId = (int)$existing['customer_id'];
                $oldAmountPaid = (float)$existing['amount_paid'];
                $newDue = max(0.0, $grandTotal - $oldAmountPaid);
                $newStatus = ($newDue <= 0.0) ? 'PAID' : (($oldAmountPaid > 0) ? 'PARTIAL' : 'UNPAID');

                // Update invoice header
                Database::execute(
                    "UPDATE invoices SET 
                        customer_id = :customer_id,
                        customer_name = :customer_name,
                        customer_gstin = :customer_gstin,
                        billing_address = :billing_address,
                        reference_po_number = :reference_po_number,
                        po_no = :po_no,
                        po_date = :po_date,
                        challan_no = :challan_no,
                        bill_credit_days = :bill_credit_days,
                        delivery_by = :delivery_by,
                        receipt_mode = :receipt_mode,
                        lr_no = :lr_no,
                        place_of_supply = :place_of_supply,
                        place_of_supply_state_code = :place_of_supply_state_code,
                        invoice_date = :invoice_date,
                        due_date = :due_date,
                        is_igst = :is_igst,
                        sub_total = :sub_total,
                        cgst_amount = :cgst_amount,
                        sgst_amount = :sgst_amount,
                        igst_amount = :igst_amount,
                        total_tax = :total_tax,
                        round_off = :round_off,
                        grand_total = :grand_total,
                        amount_due = :amount_due,
                        status = :status,
                        notes = :notes,
                        updated_at = NOW()
                     WHERE id = :id AND company_id = :company_id",
                    [
                        'customer_id' => $customerId,
                        'customer_name' => $customer['name'] ?? 'Direct Customer',
                        'customer_gstin' => $customer['gstin'] ?? null,
                        'billing_address' => $customer['address_line1'] ?? null,
                        'reference_po_number' => $poNo ?: null,
                        'po_no' => $poNo ?: null,
                        'po_date' => $poDate ?: null,
                        'challan_no' => $challanNo ?: null,
                        'bill_credit_days' => $billCreditDays,
                        'delivery_by' => $deliveryBy ?: null,
                        'receipt_mode' => $receiptMode ?: 'Credit',
                        'lr_no' => $lrNo ?: null,
                        'place_of_supply' => $placeOfSupply,
                        'place_of_supply_state_code' => $posStateCode ?: $placeOfSupply,
                        'invoice_date' => $invoiceDate,
                        'due_date' => $dueDate,
                        'is_igst' => $isIntraState ? 0 : 1,
                        'sub_total' => $taxableValue,
                        'cgst_amount' => $cgstAmount,
                        'sgst_amount' => $sgstAmount,
                        'igst_amount' => $igstAmount,
                        'total_tax' => $totalTax,
                        'round_off' => $roundOff,
                        'grand_total' => $grandTotal,
                        'amount_due' => $newDue,
                        'status' => $newStatus,
                        'notes' => $notes ?: 'Thank you for your business.',
                        'id' => $invId,
                        'company_id' => $companyId,
                    ]
                );

                // Re-sync invoice items: delete previous items and reinsert fresh updated rows
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ? AND company_id = ?", [$invId, $companyId]);

                foreach ($processedItems as $item) {
                    $pId = $item['product_id'];
                    $productName = 'Standard Industrial Hardware Item';
                    if ($pId > 0) {
                        $p = Database::selectOne("SELECT name FROM products WHERE id = ?", [$pId]);
                        if ($p) $productName = $p['name'];
                    }

                    Database::insert('invoice_items', [
                        'company_id' => $companyId,
                        'invoice_id' => $invId,
                        'product_id' => $pId > 0 ? $pId : null,
                        'category_id' => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                        'item_name' => $productName,
                        'hsn_sac' => $item['hsn_sac'] ?: '7318',
                        'quantity' => $item['quantity'],
                        'unit' => 'Pcs',
                        'unit_price' => $item['rate'],
                        'discount_rate' => $item['discount_rate'] ?? 0.0,
                        'discount_amount' => $item['discount_amount'] ?? 0.0,
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

                // Customer balance adjustment:
                if ($oldCustomerId === $customerId) {
                    $diff = $grandTotal - $oldGrandTotal;
                    if (abs($diff) > 0.001) {
                        Database::execute("UPDATE customers SET current_balance = current_balance + ? WHERE id = ? AND company_id = ?", [$diff, $customerId, $companyId]);
                    }
                } else {
                    // Reversed from previous customer, added to new customer
                    Database::execute("UPDATE customers SET current_balance = current_balance - ? WHERE id = ? AND company_id = ?", [$oldGrandTotal, $oldCustomerId, $companyId]);
                    Database::execute("UPDATE customers SET current_balance = current_balance + ? WHERE id = ? AND company_id = ?", [$grandTotal, $customerId, $companyId]);
                }
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Invoice '{$existing['invoice_number']}' updated successfully.",
                ]);
            }

            Session::flash('success', "Invoice '{$existing['invoice_number']}' updated successfully.");
            return Response::redirect('/sales/invoices');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to update invoice: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to update invoice: ' . $e->getMessage());
            return Response::redirect('/sales/invoices/' . $invId . '/edit');
        }
    }

    /**
     * Delete tax invoice and roll back customer ledger balance.
     * Route: POST /sales/invoices/{id}/delete
     */
    public function invoiceDelete(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$invId, $companyId]
        );

        if (!$invoice) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Invoice not found or already deleted.'], 404);
            }
            Session::flash('error', 'Invoice not found or already deleted.');
            return Response::redirect('/sales/invoices');
        }

        // Accounting safety check: Paid or partially paid invoices cannot be deleted
        $canDelete = InvoiceNumberService::canDeleteInvoice($invoice, $companyId);
        if (!$canDelete['allowed']) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $canDelete['reason']], 422);
            }
            Session::flash('error', $canDelete['reason']);
            return Response::redirect('/sales/invoices');
        }

        $reason = trim((string)($req->input('reason') ?? $req->input('deletion_reason') ?? 'Invoice deleted'));
        $userId = Session::getUserId();

        $res = InvoiceNumberService::deleteInvoice($companyId, $invId, $reason, $userId, false);

        if (!$res['success']) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $res['message']], 422);
            }
            Session::flash('error', $res['message']);
            return Response::redirect('/sales/invoices');
        }

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'message' => "Invoice '{$invoice['invoice_number']}' deleted successfully.",
            ]);
        }

        Session::flash('success', "Invoice '{$invoice['invoice_number']}' deleted successfully.");
        return Response::redirect('/sales/invoices');
    }

    /**
     * Cancel an invoice without releasing its number.
     * Preserves invoice number and cancellation history for GST audit compliance.
     * Route: POST /sales/invoices/{id}/cancel
     */
    public function invoiceCancel(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$invId, $companyId]
        );

        if (!$invoice) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Invoice not found or already deleted.'], 404);
            }
            Session::flash('error', 'Invoice not found or already deleted.');
            return Response::redirect('/sales/invoices');
        }

        $reason = trim((string)($req->input('reason') ?? $req->input('cancellation_reason') ?? 'Cancelled by user'));
        $userId = Session::getUserId();

        $res = InvoiceNumberService::cancelInvoice($companyId, $invId, $reason, $userId);

        if (!$res['success']) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $res['message']], 422);
            }
            Session::flash('error', $res['message']);
            return Response::redirect('/sales/invoices');
        }

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'message' => $res['message'],
            ]);
        }

        Session::flash('success', $res['message']);
        return Response::redirect('/sales/invoices');
    }

    /**
     * Permanently delete an invoice and release its number for reuse.
     * Route: POST /sales/invoices/{id}/permanent-delete
     */
    public function invoicePermanentDelete(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ?",
            [$invId, $companyId]
        );

        if (!$invoice) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Invoice not found.'], 404);
            }
            Session::flash('error', 'Invoice not found.');
            return Response::redirect('/sales/invoices');
        }

        $canDelete = InvoiceNumberService::canDeleteInvoice($invoice, $companyId);
        if (!$canDelete['allowed']) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $canDelete['reason']], 422);
            }
            Session::flash('error', $canDelete['reason']);
            return Response::redirect('/sales/invoices');
        }

        $reason = trim((string)($req->input('reason') ?? $req->input('deletion_reason') ?? 'Invoice permanently deleted'));
        $userId = Session::getUserId();

        $res = InvoiceNumberService::deleteInvoice($companyId, $invId, $reason, $userId, true);

        if (!$res['success']) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $res['message']], 422);
            }
            Session::flash('error', $res['message']);
            return Response::redirect('/sales/invoices');
        }

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'message' => "Invoice '{$invoice['invoice_number']}' permanently deleted. Number released for reuse.",
            ]);
        }

        Session::flash('success', "Invoice '{$invoice['invoice_number']}' permanently deleted. Number released for reuse.");
        return Response::redirect('/sales/invoices');
    }

    /**
     * Sales quotations view.
     */
    public function quotationsIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $quotations = Database::select(
            "SELECT q.id, q.quotation_number, q.customer_id, COALESCE(c.name, 'Direct Client') as customer_name,
                    q.quotation_date, q.valid_until, q.sub_total, q.total_tax, q.grand_total, q.status 
             FROM quotations q 
             LEFT JOIN customers c ON q.customer_id = c.id 
             WHERE q.company_id = ? 
             ORDER BY q.id DESC",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/quotations', [
            'title' => 'Quotations & Estimates',
            'currentUri' => '/sales/quotations',
            'quotations' => $quotations,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store sales quotation.
     */
    public function quotationStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $amount = (float)($payload['grand_total'] ?? ($payload['amount'] ?? 0));
        $notes = trim((string)($payload['notes'] ?? ''));
        $kindAttn = trim((string)($payload['kind_attn'] ?? ''));
        $paymentDays = (int)($payload['payment_days'] ?? 0);
        $posCode = trim((string)($payload['place_of_supply_state_code'] ?? '27'));
        $validUntil = !empty($payload['valid_until']) ? trim((string)$payload['valid_until']) : date('Y-m-d', strtotime('+30 days'));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a customer.'], 422);
            }
            Session::flash('error', 'Please select a customer.');
            return Response::redirect('/sales/quotations');
        }

        if ($amount <= 0) {
            $amount = 5000.00;
        }

        $subTotal = round($amount / 1.18, 2);
        $totalTax = round($amount - $subTotal, 2);
        $cgstAmt = round($totalTax / 2, 2);
        $sgstAmt = round($totalTax - $cgstAmt, 2);

        $fy = date('y') . (date('y') + 1);
        $seq = (int)(Database::selectOne("SELECT COUNT(*) as c FROM quotations WHERE company_id = ?", [$companyId])['c'] ?? 0) + 1;
        $quotationNumber = 'QN-' . $fy . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);

        try {
            $id = Database::insert('quotations', [
                'company_id' => $companyId,
                'branch_id' => (int)(Session::getBranchId() ?? 1),
                'customer_id' => $customerId,
                'quotation_number' => $quotationNumber,
                'quotation_date' => date('Y-m-d'),
                'valid_until' => $validUntil,
                'reference_no' => trim((string)($payload['reference_no'] ?? '')),
                'kind_attn' => $kindAttn ?: null,
                'payment_days' => $paymentDays,
                'place_of_supply_state_code' => $posCode ?: '27',
                'sub_total' => $subTotal,
                'discount_amount' => 0.0,
                'taxable_value' => $subTotal,
                'cgst_amount' => $cgstAmt,
                'sgst_amount' => $sgstAmt,
                'igst_amount' => 0.0,
                'total_tax' => $totalTax,
                'round_off' => round($amount - ($subTotal + $totalTax), 2),
                'grand_total' => $amount,
                'status' => 'SENT',
                'notes' => $notes ?: 'Commercial quotation',
                'terms' => "1) Payment Condition : On credit of {$paymentDays} days.",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Quotation '{$quotationNumber}' created successfully.",
                    'quotation_id' => $id,
                ], 201);
            }

            Session::flash('success', "Quotation '{$quotationNumber}' created successfully.");
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create quotation: ' . $e->getMessage());
            return Response::redirect('/sales/quotations');
        }
    }

    /**
     * Render pixel-perfect printable A4 Quotation document matching WIS TECHNOSAVVY PVT LTD format.
     * Route: GET /sales/quotations/{id}/print & /sales/quotations/{id}
     */
    public function quotationPrint(Request $req, mixed $id = null): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quoteId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        if ($quoteId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Invalid Quotation ID.'], 404);
            }
            return Response::html('<div style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Invalid Quotation ID</h2><p><a href="/sales/quotations">&larr; Back to Quotations</a></p></div>', 404);
        }

        $sql = "SELECT 
            q.id,
            q.company_id,
            q.branch_id,
            q.customer_id,
            q.quotation_number,
            q.quotation_date,
            q.valid_until,
            COALESCE(q.reference_no, '') AS reference_no,
            COALESCE(q.kind_attn, '') AS kind_attn,
            COALESCE(q.payment_days, 0) AS payment_days,
            COALESCE(q.place_of_supply_state_code, '') AS place_of_supply_state_code,
            q.sub_total,
            COALESCE(q.discount_amount, 0) AS discount_amount,
            COALESCE(q.taxable_value, q.sub_total) AS taxable_value,
            COALESCE(q.cgst_amount, 0) AS cgst_amount,
            COALESCE(q.sgst_amount, 0) AS sgst_amount,
            COALESCE(q.igst_amount, 0) AS igst_amount,
            COALESCE(q.total_tax, 0) AS total_tax,
            COALESCE(q.round_off, 0) AS round_off,
            q.grand_total,
            q.status,
            COALESCE(q.notes, '') AS quote_notes,
            COALESCE(q.terms, '') AS quote_terms,
            COALESCE(q.terms_json, '') AS terms_json,
            COALESCE(q.billing_address, '') AS quote_billing_address,
            COALESCE(q.shipping_address, '') AS quote_shipping_address,

            c.id AS customer_id,
            COALESCE(NULLIF(c.name, ''), 'DIRECT CUSTOMER') AS customer_name,
            COALESCE(c.company_name, '') AS customer_company,
            COALESCE(c.address_line1, '') AS customer_address,
            COALESCE(c.city, '') AS customer_city,
            COALESCE(c.state, 'Maharashtra') AS customer_state,
            COALESCE(c.state_code, '27') AS customer_state_code,
            COALESCE(c.pincode, '') AS customer_pincode,
            COALESCE(c.gstin, '') AS customer_gstin,
            COALESCE(c.phone, '') AS customer_phone,
            COALESCE(c.email, '') AS customer_email,
            COALESCE(c.contact_person, '') AS customer_contact_person,

            comp.id AS company_id,
            COALESCE(comp.name, comp.legal_name, 'Wis Technosavvy Pvt Ltd') AS company_name,
            COALESCE(comp.legal_name, comp.name, 'Wis Technosavvy Pvt Ltd') AS company_legal_name,
            COALESCE(NULLIF(comp.gstin, ''), b.gstin, '27AADCW7577N1ZE') AS company_gstin,
            COALESCE(NULLIF(comp.pan, ''), b.pan, 'AADCW7577N') AS company_pan,
            COALESCE(comp.logo_url, '') AS company_logo,
            COALESCE(comp.stamp_signature_url, '') AS company_stamp_url,
            COALESCE(comp.address_line1, '') AS company_address_line1,
            COALESCE(comp.address_line2, '') AS company_address_line2,
            COALESCE(comp.city, 'Pune') AS company_city,
            COALESCE(comp.state, 'Maharashtra') AS company_state,
            COALESCE(comp.state_code, '27') AS company_state_code,
            COALESCE(comp.pincode, '411041') AS company_pincode,
            COALESCE(comp.phone, '020 47252364') AS company_phone,
            COALESCE(comp.email, 'info@wtsindia.co.in') AS company_email,
            COALESCE(comp.website, 'http://www.wtsindia.co.in') AS company_website,
            comp.upi_id AS company_upi_id,

            b.name AS branch_name,

            bank.bank_name,
            bank.account_name,
            bank.account_number,
            COALESCE(bank.ifsc_code, bank.ifsc, '') AS bank_ifsc

        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN companies comp ON q.company_id = comp.id
        LEFT JOIN branches b ON q.branch_id = b.id
        LEFT JOIN bank_accounts bank ON (
            (b.id IS NOT NULL AND bank.branch_id = b.id AND bank.is_active = 1)
            OR (bank.company_id = q.company_id AND bank.is_active = 1)
        )
        WHERE q.id = ? AND q.company_id = ?
        ORDER BY (b.id IS NOT NULL AND bank.branch_id = b.id) DESC, (bank.bank_name LIKE '%KOTAK%') DESC, bank.is_primary DESC, bank.id DESC
        LIMIT 1";

        $quote = Database::selectOne($sql, [$quoteId, $companyId]);

        if (!$quote) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
            }
            return Response::html('<div style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Quotation Not Found</h2><p>The requested quotation could not be found.</p><p><a href="/sales/quotations">&larr; Back to Quotations</a></p></div>', 404);
        }

        // Intra-state vs Inter-state calculation
        $sellerStateCode = (string)($quote['company_state_code'] ?? '27');
        $buyerPosCode = (string)($quote['place_of_supply_state_code'] ?: ($quote['customer_state_code'] ?: '27'));
        $posInfo = gst_state_info($buyerPosCode);
        $isIntraState = ($sellerStateCode === $buyerPosCode) && ((float)($quote['igst_amount'] ?? 0) <= 0.001);

        // Fetch line items
        $rawItems = Database::select(
            "SELECT qi.*, p.name AS product_name, p.sku AS product_sku 
             FROM quotation_items qi 
             LEFT JOIN products p ON qi.product_id = p.id 
             WHERE qi.quotation_id = ? 
             ORDER BY qi.id ASC",
            [$quoteId]
        );

        if (empty($rawItems)) {
            $rawItems = [
                [
                    'item_name' => !empty($quote['quote_notes']) ? $quote['quote_notes'] : 'Commercial Products / Services as per Quote',
                    'hsn_sac' => '998313',
                    'quantity' => 1.0,
                    'unit' => 'Lot',
                    'unit_price' => (float)$quote['sub_total'],
                    'discount_rate' => 0.0,
                    'discount_amount' => 0.0,
                    'taxable_value' => (float)$quote['sub_total'],
                    'gst_rate' => 18.0,
                    'cgst_rate' => 9.0,
                    'cgst_amount' => (float)$quote['sub_total'] * 0.09,
                    'sgst_rate' => 9.0,
                    'sgst_amount' => (float)$quote['sub_total'] * 0.09,
                    'igst_rate' => 18.0,
                    'igst_amount' => (float)$quote['sub_total'] * 0.18,
                    'total_amount' => (float)$quote['grand_total'],
                ]
            ];
        }

        $items = [];
        $taxGroups = [];
        $totalQty = 0.0;
        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

        foreach ($rawItems as $it) {
            $qty = (float)($it['quantity'] ?? 1);
            $price = (float)($it['unit_price'] ?? 0);
            $discRate = (float)($it['discount_rate'] ?? 0);
            $discAmt = (float)($it['discount_amount'] ?? 0);
            if ($discAmt <= 0 && $discRate > 0) {
                $discAmt = round(($qty * $price) * ($discRate / 100), 2);
            }
            $lineTaxable = (float)($it['taxable_value'] ?? round(($qty * $price) - $discAmt, 2));
            $gstRate = (float)($it['gst_rate'] ?? 18.0);
            $priceWithGst = round($price * (1 + ($gstRate / 100)), 2);
            $taxableRate = ($qty > 0) ? round($lineTaxable / $qty, 2) : $price;

            if ($isIntraState) {
                $cgstRate = round($gstRate / 2, 2);
                $sgstRate = round($gstRate / 2, 2);
                $igstRate = 0.0;
                $cgstAmt = (float)($it['cgst_amount'] ?? round($lineTaxable * ($cgstRate / 100), 2));
                $sgstAmt = (float)($it['sgst_amount'] ?? round($lineTaxable * ($sgstRate / 100), 2));
                $igstAmt = 0.0;
            } else {
                $cgstRate = 0.0;
                $sgstRate = 0.0;
                $igstRate = $gstRate;
                $cgstAmt = 0.0;
                $sgstAmt = 0.0;
                $igstAmt = (float)($it['igst_amount'] ?? round($lineTaxable * ($igstRate / 100), 2));
            }

            // Split category vs description if present (e.g. "HARDWARE\n4 Port Poe switch")
            $rawName = trim((string)($it['item_name'] ?? ''));
            $nameLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawName));
            $category = '';
            $desc = '';
            if (count($nameLines) > 1 && strtoupper($nameLines[0]) === $nameLines[0] && strlen(trim($nameLines[0])) < 30) {
                $category = trim($nameLines[0]);
                $desc = trim(implode("\n", array_slice($nameLines, 1)));
            } else {
                $desc = $rawName;
            }

            $items[] = [
                'category' => $category,
                'description' => $desc,
                'item_name' => $rawName,
                'hsn_sac' => $it['hsn_sac'] ?? '',
                'quantity' => $qty,
                'unit' => $it['unit'] ?? 'NOS',
                'discount_rate' => $discRate,
                'discount_amount' => $discAmt,
                'unit_price' => $price,
                'price_with_gst' => $priceWithGst,
                'taxable_rate' => $taxableRate,
                'taxable_value' => $lineTaxable,
                'gst_rate' => $gstRate,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmt,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmt,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmt,
            ];

            $totalQty += $qty;
            $totalTaxable += $lineTaxable;
            $totalCgst += $cgstAmt;
            $totalSgst += $sgstAmt;
            $totalIgst += $igstAmt;

            // Group tax rates
            $rateKey = (string)$gstRate;
            if (!isset($taxGroups[$rateKey])) {
                $taxGroups[$rateKey] = [
                    'gst_rate' => $gstRate,
                    'cgst_rate' => $cgstRate,
                    'sgst_rate' => $sgstRate,
                    'igst_rate' => $igstRate,
                    'taxable' => 0.0,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                    'igst' => 0.0,
                ];
            }
            $taxGroups[$rateKey]['taxable'] += $lineTaxable;
            $taxGroups[$rateKey]['cgst'] += $cgstAmt;
            $taxGroups[$rateKey]['sgst'] += $sgstAmt;
            $taxGroups[$rateKey]['igst'] += $igstAmt;
        }

        // Totals & Round-off calculation
        $totalGst = $totalCgst + $totalSgst + $totalIgst;
        $rawGrand = $totalTaxable + $totalGst;
        $grandTotal = (float)($quote['grand_total'] ?? 0);
        if ($grandTotal <= 0) {
            $grandTotal = round($rawGrand);
        }
        $roundOff = round($grandTotal - $rawGrand, 2);
        $roundOffDisplay = ($roundOff == 0.0) ? '0' : sprintf('%.1f', $roundOff);

        // Amount in words
        $amountInWords = function_exists('amount_to_words_inr') 
            ? amount_to_words_inr($grandTotal) 
            : number_format($grandTotal, 2);

        // Server-side SVG UPI QR Code
        $upiVpa = trim((string)($quote['company_upi_id'] ?? ''));
        $upiName = !empty($quote['company_legal_name']) ? $quote['company_legal_name'] : $quote['company_name'];
        $qrSvg = '';
        if ($upiVpa !== '' && $grandTotal > 0) {
            $upiPayload = QrCodeService::buildUpiPayload(
                $upiVpa,
                $upiName,
                $grandTotal,
                (string)$quote['quotation_number']
            );
            $qrSvg = QrCodeService::generateSvg($upiPayload, 78);
        }

        // Kind Attn resolution
        $kindAttn = trim((string)($quote['kind_attn'] ?: ($quote['customer_contact_person'] ?: '')));
        if (empty($kindAttn) && str_starts_with($quote['quote_notes'], 'Kind Attn.')) {
            $kindAttn = trim(str_replace('Kind Attn.:', '', $quote['quote_notes']));
        }
        $quote['kind_attn'] = $kindAttn;

        // Structured buyer details
        $buyer = [
            'name' => $quote['customer_name'],
            'company' => $quote['customer_company'],
            'address' => $quote['customer_address'],
            'city' => $quote['customer_city'],
            'state' => $quote['customer_state'],
            'pincode' => $quote['customer_pincode'],
            'gstin' => $quote['customer_gstin'],
            'phone' => $quote['customer_phone'],
            'email' => $quote['customer_email'],
            'pos_state' => $posInfo['name'],
            'pos_code' => $posInfo['code'],
        ];

        // Structured seller details
        $seller = [
            'company_name' => strtoupper($quote['company_name'] ?: 'WIS TECHNOSAVVY PVT LTD'),
            'company_legal_name' => strtoupper($quote['company_legal_name'] ?: 'WIS TECHNOSAVVY PVT LTD'),
            'company_gstin' => $quote['company_gstin'],
            'company_pan' => $quote['company_pan'],
            'company_logo' => $quote['company_logo'],
            'company_stamp_url' => $quote['company_stamp_url'],
            'company_address_line1' => $quote['company_address_line1'],
            'company_address_line2' => $quote['company_address_line2'],
            'company_city' => $quote['company_city'],
            'company_state' => $quote['company_state'],
            'company_pincode' => $quote['company_pincode'],
            'company_phone' => $quote['company_phone'],
            'company_email' => $quote['company_email'],
            'company_website' => $quote['company_website'],
            'branch_name' => $quote['branch_name'],
        ];

        // Structured bank details
        $bank = [
            'bank_name' => $quote['bank_name'] ?: 'KOTAK MAHINDRA BANK',
            'account_name' => $quote['account_name'] ?: ($quote['company_legal_name'] ?: 'WIS TECHNOSAVVY PRIVATE LIMITED'),
            'account_number' => $quote['account_number'] ?: '9881032297',
            'bank_ifsc' => $quote['bank_ifsc'] ?: 'KKBK0001986',
        ];

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'quotation' => $quote,
                'items' => $items,
                'tax_groups' => $taxGroups,
                'total_qty' => $totalQty,
                'total_taxable' => $totalTaxable,
                'total_tax' => $totalGst,
                'grand_total' => $grandTotal,
                'amount_in_words' => $amountInWords,
            ]);
        }

        $html = View::render('sales/quotation_print', [
            'quote' => $quote,
            'items' => $items,
            'taxGroups' => $taxGroups,
            'seller' => $seller,
            'buyer' => $buyer,
            'bank' => $bank,
            'totalQty' => $totalQty,
            'totalTaxable' => $totalTaxable,
            'totalCgst' => $totalCgst,
            'totalSgst' => $totalSgst,
            'totalIgst' => $totalIgst,
            'totalGst' => $totalGst,
            'roundOff' => $roundOff,
            'roundOffDisplay' => $roundOffDisplay,
            'grandTotal' => $grandTotal,
            'amountInWords' => $amountInWords,
            'qrSvg' => $qrSvg,
            'isIntraState' => $isIntraState,
        ]);

        return Response::html($html);
    }

    /**
     * Delivery challans view.
     */
    public function challansIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $challans = Database::select(
            "SELECT dc.id, dc.challan_number, dc.customer_id, COALESCE(c.name, 'Direct Client') as customer_name,
                    dc.challan_date, dc.status, dc.notes 
             FROM delivery_challans dc 
             LEFT JOIN customers c ON dc.customer_id = c.id 
             WHERE dc.company_id = ? 
             ORDER BY dc.id DESC",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/challans', [
            'title' => 'Delivery Challans',
            'currentUri' => '/sales/challans',
            'challans' => $challans,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store delivery challan.
     */
    public function challanStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $deliveryAddress = trim((string)($payload['delivery_address'] ?? ''));
        $transport = trim((string)($payload['transport_details'] ?? ''));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a customer.'], 422);
            }
            Session::flash('error', 'Please select a customer.');
            return Response::redirect('/sales/challans');
        }

        $challanNumber = 'DC-' . date('Y') . '-' . rand(1000, 9999);

        try {
            Database::insert('delivery_challans', [
                'company_id' => $companyId,
                'branch_id' => (int)(Session::getBranchId() ?? 1),
                'customer_id' => $customerId,
                'challan_number' => $challanNumber,
                'challan_date' => date('Y-m-d'),
                'delivery_address' => $deliveryAddress ?: 'Standard site delivery',
                'transport_details' => $transport ?: 'Direct dispatch via road logistics',
                'status' => 'DELIVERED',
                'notes' => 'Materials dispatched in good condition',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Delivery challan '{$challanNumber}' generated successfully.",
                ], 201);
            }

            Session::flash('success', "Delivery challan '{$challanNumber}' generated successfully.");
            return Response::redirect('/sales/challans');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create challan: ' . $e->getMessage());
            return Response::redirect('/sales/challans');
        }
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
