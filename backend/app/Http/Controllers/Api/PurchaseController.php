<?php

namespace App\Http\Controllers\Api;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\StockMovement;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class PurchaseController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $purchases = Purchase::where('company_id', $companyId)
            ->with(['supplier', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $purchases,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Purchase::withoutGlobalScopes()->find(intval($id));
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Purchase invoice not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $purchase = Purchase::where('company_id', $companyId)
            ->with(['supplier', 'items.product', 'attachments'])
            ->find(intval($id));

        return response_json([
            'status' => 'success',
            'data' => $purchase,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = AuthMiddleware::getTenantId();

        $input = get_json_input();
        $input['company_id'] = $companyId;

        try {
            $purchase = \App\Services\PurchaseInvoiceService::createPurchase($input);

            AuditLogService::log(
                $companyId,
                $user?->name ?: 'Admin',
                'PURCHASE_CREATE',
                'Purchase',
                $purchase->id,
                "Created Purchase Invoice #{$purchase->purchase_number} from {$purchase->supplier_name} of ₹" . number_format($purchase->grand_total, 2)
            );

            return response_json([
                'status' => 'success',
                'message' => "Purchase Invoice #{$purchase->purchase_number} generated successfully.",
                'data' => $purchase->load('items'),
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function post($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = AuthMiddleware::getTenantId();

        try {
            $purchase = \App\Services\PurchaseInvoiceService::postPurchase(intval($id));

            AuditLogService::log(
                $companyId,
                $user?->name ?: 'Admin',
                'PURCHASE_POST',
                'Purchase',
                $purchase->id,
                "Posted Purchase Invoice #{$purchase->purchase_number}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Purchase Invoice #{$purchase->purchase_number} posted successfully.",
                'data' => $purchase->load(['supplier', 'items']),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'cancel');
        $companyId = AuthMiddleware::getTenantId();

        $input = get_json_input();
        $reason = $input['reason'] ?? 'Cancelled by user';

        try {
            $purchase = \App\Services\PurchaseInvoiceService::cancelPurchase(intval($id), $reason);

            AuditLogService::log(
                $companyId,
                $user?->name ?: 'Admin',
                'PURCHASE_CANCEL',
                'Purchase',
                $purchase->id,
                "Cancelled Purchase Invoice #{$purchase->purchase_number}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Purchase Invoice #{$purchase->purchase_number} has been cancelled.",
                'data' => $purchase->load(['supplier', 'items']),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function duplicate($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = AuthMiddleware::getTenantId();

        try {
            $purchase = \App\Services\PurchaseInvoiceService::duplicatePurchase(intval($id));

            AuditLogService::log(
                $companyId,
                $user?->name ?: 'Admin',
                'PURCHASE_DUPLICATE',
                'Purchase',
                $purchase->id,
                "Duplicated Purchase Invoice ID {$id} into Draft #{$purchase->purchase_number}"
            );

            return response_json([
                'status' => 'success',
                'message' => "Purchase duplicated as Draft #{$purchase->purchase_number} successfully.",
                'data' => $purchase->load('items'),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
