<?php

namespace App\Http\Controllers\Api;

use App\Models\PurchaseOrder;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PurchaseOrderService;
use App\Services\AuditLogService;

class PurchaseOrderController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $orders = PurchaseOrder::with(['supplier', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $order = PurchaseOrder::with(['supplier', 'items.product'])->find($id);

        if (!$order) {
            return response_json(['status' => 'error', 'message' => 'Purchase Order not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = $user->current_company_id;

        $input = get_json_input();

        try {
            $po = PurchaseOrderService::createPO($input);

            AuditLogService::log($companyId, $user->name, 'PURCHASE_ORDER_CREATE', 'PurchaseOrder', $po->id, "Created Purchase Order #{$po->po_number}");

            return response_json([
                'status' => 'success',
                'message' => "Purchase Order #{$po->po_number} created successfully.",
                'data' => $po->load('items')
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'cancel');
        $companyId = $user->current_company_id;

        $po = PurchaseOrder::find($id);
        if (!$po) {
            return response_json(['status' => 'error', 'message' => 'Purchase Order not found.'], 404);
        }

        $po->update(['status' => 'CANCELLED']);

        AuditLogService::log($companyId, $user->name, 'PURCHASE_ORDER_CANCEL', 'PurchaseOrder', $po->id, "Cancelled Purchase Order #{$po->po_number}");

        return response_json([
            'status' => 'success',
            'message' => "Purchase Order #{$po->po_number} has been cancelled."
        ]);
    }

    public function convert($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'create');

        try {
            $bill = PurchaseOrderService::convertPOToInvoice($id);
            return response_json([
                'status' => 'success',
                'message' => "Purchase Order converted to Invoice Draft successfully.",
                'data' => $bill->load('items')
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
