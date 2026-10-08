<?php

namespace App\Http\Controllers\Api;

use App\Models\PurchaseReturn;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PurchaseReturnService;
use App\Services\AuditLogService;

class PurchaseReturnController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $returns = PurchaseReturn::with(['supplier', 'purchase', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $returns
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $ret = PurchaseReturn::with(['supplier', 'purchase', 'items.product'])->find($id);

        if (!$ret) {
            return response_json(['status' => 'error', 'message' => 'Purchase Return not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $ret
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = $user->current_company_id;

        $input = get_json_input();

        try {
            $ret = PurchaseReturnService::createReturn($input);

            AuditLogService::log($companyId, $user->name, 'PURCHASE_RETURN_CREATE', 'PurchaseReturn', $ret->id, "Created Purchase Return #{$ret->return_number}");

            return response_json([
                'status' => 'success',
                'message' => "Purchase Return #{$ret->return_number} logged successfully.",
                'data' => $ret->load('items')
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
