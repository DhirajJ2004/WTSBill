<?php

namespace App\Http\Controllers\Api;

use App\Models\GoodsReceipt;
use App\Http\Middleware\AuthMiddleware;
use App\Services\GoodsReceiptService;
use App\Services\AuditLogService;

class GoodsReceiptController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $receipts = GoodsReceipt::with(['supplier', 'purchaseOrder', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $receipts
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $grn = GoodsReceipt::with(['supplier', 'purchaseOrder', 'items.product'])->find($id);

        if (!$grn) {
            return response_json(['status' => 'error', 'message' => 'Goods Receipt record not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $grn
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = $user->current_company_id;

        $input = get_json_input();

        try {
            $grn = GoodsReceiptService::createGRN($input);

            AuditLogService::log($companyId, $user->name, 'GOODS_RECEIPT_CREATE', 'GoodsReceipt', $grn->id, "Logged Goods Receipt #{$grn->grn_number}");

            return response_json([
                'status' => 'success',
                'message' => "Goods Receipt #{$grn->grn_number} created successfully.",
                'data' => $grn->load('items')
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
