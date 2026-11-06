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
use WTSBill\Services\SalesOrderService;

/**
 * Sales Order Controller.
 * Handles the complete commercial order fulfillment lifecycle:
 * Listing, creation, quotation conversion, delivery challan generation,
 * tax invoice generation, and printable order view.
 */
class SalesOrderController
{
    protected SalesOrderService $orderService;

    public function __construct(?SalesOrderService $orderService = null)
    {
        $this->orderService = $orderService ?? new SalesOrderService();
    }

    /**
     * Sales Orders List with Status Pills, Search, and Branch filter.
     * Route: GET /sales/orders
     */
    public function index(Request $req): Response
    {
        Gate::authorize('quotations.view', $req);

        $filters = [
            'status' => (string)($req->query('status') ?? 'ALL'),
            'search' => (string)($req->query('search') ?? ''),
            'branch_id' => $req->query('branch_id') !== null ? (int)$req->query('branch_id') : (int)(Session::getBranchId() ?? 0),
        ];

        $result = $this->orderService->getOrders($filters);

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'data' => $result['orders'],
                'counts' => $result['counts'],
            ]);
        }

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY id ASC", [Session::getCompanyId() ?? 1]);

        $html = View::renderWithLayout('sales/orders/index', [
            'title' => 'Sales Orders',
            'currentUri' => '/sales/orders',
            'orders' => $result['orders'],
            'counts' => $result['counts'],
            'currentStatus' => $filters['status'],
            'search' => $filters['search'],
            'branchId' => $filters['branch_id'],
            'branches' => $branches,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Render Create Sales Order Form.
     * Route: GET /sales/orders/create
     */
    public function createForm(Request $req): Response
    {
        Gate::authorize('quotations.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $customers = Database::select(
            "SELECT id, name, company_name, gstin, state_code, address_line1, city, phone FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC",
            [$companyId]
        );
        $categories = Database::select("SELECT id, name FROM categories WHERE company_id = ? AND is_active = 1 ORDER BY name ASC", [$companyId]);
        $products = Database::select("SELECT id, category_id, name, sku, hsn_sac, selling_price, tax_rate, unit FROM products WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $nextOrderNumber = $this->orderService->generateOrderNumber($companyId);
        $selectedCustomerId = (int)($req->query('customer_id') ?? 0);

        $html = View::renderWithLayout('sales/orders/create', [
            'title' => 'Create Sales Order',
            'currentUri' => '/sales/orders/create',
            'orderNumber' => $nextOrderNumber,
            'customers' => $customers,
            'categories' => $categories,
            'products' => $products,
            'selectedCustomerId' => $selectedCustomerId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new Sales Order.
     * Route: POST /sales/orders/create or POST /sales/orders
     */
    public function store(Request $req): Response
    {
        Gate::authorize('quotations.create', $req);

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $order = $this->orderService->createOrder($payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Sales Order '{$order['order_number']}' created successfully.",
                    'order' => $order,
                ], 201);
            }

            Session::flash('success', "Sales Order '{$order['order_number']}' created successfully.");
            return Response::redirect('/sales/orders/' . $order['id']);
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/orders/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to create sales order: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create sales order: ' . $e->getMessage());
            return Response::redirect('/sales/orders/create');
        }
    }

    /**
     * Show single Sales Order.
     * Route: GET /sales/orders/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.view', $req);

        $orderId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Sales Order not found.'], 404);
            }
            Session::flash('error', 'Sales Order not found.');
            return Response::redirect('/sales/orders');
        }

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'order' => $order]);
        }

        $html = View::renderWithLayout('sales/orders/show', [
            'title' => $order['order_number'] . ' — Sales Order',
            'currentUri' => '/sales/orders/' . $orderId,
            'order' => $order,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Convert Sales Order into Delivery Challan.
     * Route: POST /sales/orders/{id}/convert-to-challan
     */
    public function convertToChallan(Request $req, mixed $id = null): Response
    {
        Gate::authorize('challans.create', $req);

        $orderId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        try {
            $challan = $this->orderService->convertOrderToChallan($orderId);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Sales Order converted to Delivery Challan '{$challan['challan_number']}' successfully.",
                    'challan' => $challan,
                ], 201);
            }

            Session::flash('success', "Sales Order converted to Delivery Challan '{$challan['challan_number']}' successfully.");
            return Response::redirect('/sales/challans');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to convert order: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to convert order: ' . $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
        }
    }

    /**
     * Convert Sales Order into confirmed Tax Invoice.
     * Route: POST /sales/orders/{id}/convert-to-invoice
     */
    public function convertToInvoice(Request $req, mixed $id = null): Response
    {
        Gate::authorize('invoices.create', $req);

        $orderId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        try {
            $inv = $this->orderService->convertOrderToInvoice($orderId);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Sales Order converted to Tax Invoice '{$inv['invoice_number']}' successfully.",
                    'invoice' => $inv,
                ], 201);
            }

            Session::flash('success', "Sales Order converted to Tax Invoice '{$inv['invoice_number']}' successfully.");
            return Response::redirect('/sales/invoices/' . $inv['invoice_id'] . '/print');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to convert order: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to convert order: ' . $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
        }
    }

    /**
     * Printable Sales Order Document.
     * Route: GET /sales/orders/{id}/print
     */
    public function print(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.print', $req);

        $orderId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            Session::flash('error', 'Sales Order not found.');
            return Response::redirect('/sales/orders');
        }

        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [Session::getCompanyId() ?? 1]);
        $branch = Database::selectOne("SELECT * FROM branches WHERE id = ?", [$order['branch_id'] ?? 1]);

        $html = View::render('sales/orders/print', [
            'order' => $order,
            'company' => $company,
            'branch' => $branch,
        ]);

        return Response::html($html);
    }

    /**
     * Delete Sales Order.
     * Route: POST /sales/orders/{id}/delete
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('quotations.delete', $req);

        $orderId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        try {
            $deleted = $this->orderService->deleteOrder($orderId);

            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => 'Sales Order deleted successfully.']);
            }

            Session::flash('success', 'Sales Order deleted successfully.');
            return Response::redirect('/sales/orders');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to delete sales order: ' . $e->getMessage());
            return Response::redirect('/sales/orders/' . $orderId);
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

