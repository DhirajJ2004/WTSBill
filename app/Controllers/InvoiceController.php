<?php

namespace App\Controllers;

use App\Services\InvoiceService;
use App\Repositories\InvoiceRepository;
use App\Repositories\PartyRepository;
use App\Repositories\InventoryRepository;
use App\Validators\InvoiceValidator;
use App\Middleware\AuthMiddleware;
use App\Http\Request;
use App\Http\JsonResponse;

class InvoiceController
{
    /**
     * Render the Invoices List View.
     */
    public function index(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            header('Location: /login');
            exit();
        }

        $page = max(1, (int)($request->get('page', 1)));
        $perPage = max(1, min(100, (int)($request->get('per_page', 25))));
        $filters = [
            'search' => $request->get('search', ''),
            'customer_id' => $request->get('customer_id', ''),
            'status' => $request->get('status', ''),
            'payment_status' => $request->get('payment_status', ''),
            'from_date' => $request->get('from_date', ''),
            'to_date' => $request->get('to_date', ''),
            'page' => $page,
            'per_page' => $perPage,
        ];

        $invoices = InvoiceRepository::getInvoices($companyId, $filters, $page, $perPage);
        $customers = PartyRepository::getCustomers($companyId, [], 1, 500);
        $stats = InvoiceRepository::getInvoiceStats($companyId);

        require_once __DIR__ . '/../../views/sales/invoices.php';
    }

    /**
     * API: Get Paginated Invoices List.
     */
    public function apiList(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $page = max(1, (int)($request->get('page', 1)));
        $perPage = max(1, min(100, (int)($request->get('per_page', 25))));
        $filters = [
            'search' => $request->get('search', ''),
            'customer_id' => $request->get('customer_id', ''),
            'status' => $request->get('status', ''),
            'payment_status' => $request->get('payment_status', ''),
            'page' => $page,
            'per_page' => $perPage,
        ];

        $data = InvoiceRepository::getInvoices($companyId, $filters, $page, $perPage);
        return new JsonResponse(['success' => true, 'data' => $data], 200);
    }

    /**
     * API: Get Single Invoice with Items.
     */
    public function apiDetails(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $details = InvoiceRepository::getInvoiceWithItems($id, $companyId);
        if (!$details) {
            return new JsonResponse(['success' => false, 'message' => "Invoice #{$id} not found."], 404);
        }

        return new JsonResponse(['success' => true, 'data' => $details], 200);
    }

    /**
     * Store a new Invoice.
     */
    public function store(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $request->all();
        $userName = $_SESSION['user_name'] ?? 'Admin';
        $userId = $_SESSION['user_id'] ?? null;

        $result = InvoiceService::createInvoice($input, $companyId, null, $userName, $userId);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 201);
    }

    /**
     * Cancel / Void an Invoice.
     */
    public function void(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $userName = $_SESSION['user_name'] ?? 'Admin';
        $reason = $request->get('reason', 'Cancelled by user');

        $result = InvoiceService::voidInvoice($id, $companyId, $userName, $reason);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 200);
    }

    /**
     * Delete DRAFT invoice.
     */
    public function destroy(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $userName = $_SESSION['user_name'] ?? 'Admin';
        $result = InvoiceService::deleteInvoice($id, $companyId, $userName);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 200);
    }
}
