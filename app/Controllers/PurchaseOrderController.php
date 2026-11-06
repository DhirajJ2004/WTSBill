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
use WTSBill\Services\PurchaseOrderService;

/**
 * Purchase Order Controller.
 * Manages procurement workflows, Goods Receipt Notes (GRN), PO-to-Bill conversion, and Debit Notes.
 */
class PurchaseOrderController
{
    protected PurchaseOrderService $poService;

    public function __construct(?PurchaseOrderService $poService = null)
    {
        $this->poService = $poService ?? new PurchaseOrderService();
    }

    /**
     * List all Purchase Orders.
     * Route: GET /purchases/orders
     */
    public function index(Request $req): Response
    {
        Gate::authorize('bills.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $status = (string)$req->get('status', 'ALL');
        $orders = $this->poService->getOrders($companyId, [
            'status' => $status,
            'search' => (string)$req->get('search', ''),
            'supplier_id' => (int)$req->get('supplier_id', 0),
        ]);

        $suppliers = Database::select("SELECT id, name, company_name FROM suppliers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        // Status counts
        $counts = [
            'ALL' => count($this->poService->getOrders($companyId, ['status' => 'ALL'])),
            'PENDING' => count($this->poService->getOrders($companyId, ['status' => 'PENDING'])),
            'APPROVED' => count($this->poService->getOrders($companyId, ['status' => 'APPROVED'])),
            'PARTIAL' => count($this->poService->getOrders($companyId, ['status' => 'PARTIAL'])),
            'RECEIVED' => count($this->poService->getOrders($companyId, ['status' => 'RECEIVED'])),
            'CANCELLED' => count($this->poService->getOrders($companyId, ['status' => 'CANCELLED'])),
        ];

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'data' => $orders, 'counts' => $counts]);
        }

        $html = View::renderWithLayout('purchases/orders/index', [
            'title' => 'Purchase Orders (PO)',
            'currentUri' => '/purchases/orders',
            'orders' => $orders,
            'suppliers' => $suppliers,
            'currentStatus' => strtoupper($status),
            'counts' => $counts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Render Purchase Order Creation Form.
     * Route: GET /purchases/orders/create
     */
    public function create(Request $req): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $suppliers = Database::select("SELECT id, name, company_name, gstin, state FROM suppliers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);
        $products = Database::select("SELECT id, name, sku, hsn_sac, unit, COALESCE(purchase_price, 0) as cost_price, current_stock FROM products WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);
        $branches = Database::select("SELECT id, name, code FROM branches WHERE company_id = ? ORDER BY id ASC", [$companyId]);

        $html = View::renderWithLayout('purchases/orders/create', [
            'title' => 'Create Purchase Order',
            'currentUri' => '/purchases/orders',
            'suppliers' => $suppliers,
            'products' => $products,
            'branches' => $branches,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new Purchase Order.
     * Route: POST /purchases/orders/create, POST /purchases/orders
     */
    public function store(Request $req): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userName = $user ? (string)$user['name'] : (string)(Session::get('user_name') ?? 'Purchasing Officer');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $poId = $this->poService->createOrder($companyId, $payload, $userName);

            AuditLogger::log(
                action: 'create',
                module: 'procurement',
                entityType: 'PURCHASE_ORDER',
                entityId: $poId,
                newValues: $payload,
                status: 'success',
                notes: "Created purchase order #{$poId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Purchase order created successfully.',
                    'id' => $poId,
                ], 201);
            }

            Session::flash('success', 'Purchase order created successfully.');
            return Response::redirect('/purchases/orders/' . $poId);
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/purchases/orders/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create PO: ' . $e->getMessage());
            return Response::redirect('/purchases/orders/create');
        }
    }

    /**
     * View Purchase Order Details.
     * Route: GET /purchases/orders/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->get('id') ?? 0)));

        $order = $this->poService->getOrder($companyId, $poId);
        if (!$order) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Purchase order not found.'], 404);
            }
            Session::flash('error', 'Purchase order not found.');
            return Response::redirect('/purchases/orders');
        }

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'order' => $order]);
        }

        $warehouses = Database::select("SELECT id, name, code FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('purchases/orders/show', [
            'title' => 'Purchase Order ' . $order['po_number'],
            'currentUri' => '/purchases/orders',
            'order' => $order,
            'warehouses' => $warehouses,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Printable Purchase Order Document.
     * Route: GET /purchases/orders/{id}/print
     */
    public function print(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->get('id') ?? 0)));

        $order = $this->poService->getOrder($companyId, $poId);
        if (!$order) {
            return Response::html('<h1>Purchase Order Not Found</h1>', 404);
        }

        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]);

        $html = View::render('purchases/orders/print', [
            'order' => $order,
            'company' => $company,
        ]);

        return Response::html($html);
    }

    /**
     * Approve Purchase Order.
     * Route: POST /purchases/orders/{id}/approve
     */
    public function approve(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');

        try {
            $this->poService->approveOrder($companyId, $poId, $userName);

            AuditLogger::log(
                action: 'approve',
                module: 'procurement',
                entityType: 'PURCHASE_ORDER',
                entityId: $poId,
                status: 'success',
                notes: "Approved purchase order #{$poId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Purchase order approved successfully.']);
            }

            Session::flash('success', 'Purchase order approved successfully.');
            return Response::redirect('/purchases/orders/' . $poId);
        } catch (ValidationException $ve) {
            $errs = $ve->getErrors();
            $msg = ($ve->getMessage() !== 'The given data was invalid.') ? $ve->getMessage() : (!empty($errs) ? reset($errs) : $ve->getMessage());
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg, 'errors' => $errs], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/purchases/orders/' . $poId);
        }
    }

    /**
     * Record Goods Receipt (GRN).
     * Route: POST /purchases/orders/{id}/receive-goods
     */
    public function receiveGoods(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $receipts = $payload['receipts'] ?? [];
        $warehouseId = (int)($payload['warehouse_id'] ?? 1);

        try {
            $res = $this->poService->receiveGoods($companyId, $poId, $receipts, $warehouseId, $userName);

            AuditLogger::log(
                action: 'receive_goods',
                module: 'procurement',
                entityType: 'PURCHASE_ORDER',
                entityId: $poId,
                status: 'success',
                notes: "Recorded goods receipt for PO #{$poId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json($res);
            }

            Session::flash('success', $res['message']);
            return Response::redirect('/purchases/orders/' . $poId);
        } catch (ValidationException $ve) {
            $errs = $ve->getErrors();
            $msg = ($ve->getMessage() !== 'The given data was invalid.') ? $ve->getMessage() : (!empty($errs) ? reset($errs) : $ve->getMessage());
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg, 'errors' => $errs], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/purchases/orders/' . $poId);
        }
    }

    /**
     * Convert Purchase Order into Inward Bill.
     * Route: POST /purchases/orders/{id}/convert-to-bill
     */
    public function convertToBill(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $billId = $this->poService->convertToBill($companyId, $poId, $payload, $userName);

            AuditLogger::log(
                action: 'convert_to_bill',
                module: 'procurement',
                entityType: 'PURCHASE_ORDER',
                entityId: $poId,
                status: 'success',
                notes: "Converted PO #{$poId} into Inward Bill #{$billId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Purchase order converted into Inward Bill successfully.',
                    'bill_id' => $billId,
                ], 201);
            }

            Session::flash('success', 'Purchase order converted into Inward Bill successfully.');
            return Response::redirect('/purchases/bills');
        } catch (ValidationException $ve) {
            $errs = $ve->getErrors();
            $msg = ($ve->getMessage() !== 'The given data was invalid.') ? $ve->getMessage() : (!empty($errs) ? reset($errs) : $ve->getMessage());
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg, 'errors' => $errs], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/purchases/orders/' . $poId);
        }
    }

    /**
     * Delete Purchase Order (if unbilled).
     * Route: POST /purchases/orders/{id}/delete
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $poId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));

        $existingBill = Database::selectOne("SELECT id FROM purchases WHERE purchase_order_id = ? AND company_id = ?", [$poId, $companyId]);
        if ($existingBill) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Cannot delete a Purchase Order that has already been converted to an Inward Bill.'], 422);
            }
            Session::flash('error', 'Cannot delete a Purchase Order that has already been converted to an Inward Bill.');
            return Response::redirect('/purchases/orders');
        }

        Database::delete('purchase_order_items', 'purchase_order_id = ? AND company_id = ?', [$poId, $companyId]);
        Database::delete('purchase_orders', 'id = ? AND company_id = ?', [$poId, $companyId]);

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => 'Purchase order deleted successfully.']);
        }

        Session::flash('success', 'Purchase order deleted successfully.');
        return Response::redirect('/purchases/orders');
    }

    /**
     * List Debit Notes.
     * Route: GET /purchases/debit-notes
     */
    public function debitNotesIndex(Request $req): Response
    {
        Gate::authorize('bills.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $debitNotes = $this->poService->getDebitNotes($companyId, [
            'search' => (string)$req->get('search', ''),
            'supplier_id' => (int)$req->get('supplier_id', 0),
        ]);

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'data' => $debitNotes]);
        }

        $suppliers = Database::select("SELECT id, name, company_name FROM suppliers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('purchases/debit_notes/index', [
            'title' => 'Debit Notes & Purchase Returns',
            'currentUri' => '/purchases/debit-notes',
            'debitNotes' => $debitNotes,
            'suppliers' => $suppliers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Create Debit Note form.
     * Route: GET /purchases/debit-notes/create
     */
    public function debitNoteCreate(Request $req): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $suppliers = Database::select("SELECT id, name, company_name FROM suppliers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);
        $purchases = Database::select("SELECT id, purchase_number, supplier_id, grand_total, amount_due FROM purchases WHERE company_id = ? AND deleted_at IS NULL ORDER BY id DESC", [$companyId]);

        $html = View::renderWithLayout('purchases/debit_notes/create', [
            'title' => 'Issue Debit Note',
            'currentUri' => '/purchases/debit-notes',
            'suppliers' => $suppliers,
            'purchases' => $purchases,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new Debit Note.
     * Route: POST /purchases/debit-notes/create
     */
    public function debitNoteStore(Request $req): Response
    {
        Gate::authorize('bills.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userName = $user ? (string)$user['name'] : (string)(Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $dnId = $this->poService->createDebitNote($companyId, $payload, $userName);

            AuditLogger::log(
                action: 'create',
                module: 'procurement',
                entityType: 'DEBIT_NOTE',
                entityId: $dnId,
                newValues: $payload,
                status: 'success',
                notes: "Created debit note #{$dnId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Debit note issued successfully.',
                    'id' => $dnId,
                ], 201);
            }

            Session::flash('success', 'Debit note issued successfully.');
            return Response::redirect('/purchases/debit-notes');
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/purchases/debit-notes/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to issue debit note: ' . $e->getMessage());
            return Response::redirect('/purchases/debit-notes/create');
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
