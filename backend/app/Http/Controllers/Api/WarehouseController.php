<?php

namespace App\Http\Controllers\Api;

use App\Models\Warehouse;
use App\Services\WarehouseManagementService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class WarehouseController
{
    /**
     * List all warehouses.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = $request->input('branch_id');

        $query = Warehouse::where('company_id', $companyId)->with('branch');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $warehouses = $query->get();

        return response()->json([
            'status' => 'success',
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Show single warehouse.
     */
    public function show(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $raw = Warehouse::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response()->json(['status' => 'error', 'message' => 'Warehouse not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $warehouse = Warehouse::where('company_id', $companyId)->with(['branch', 'balances.product'])->find($id);

        return response()->json([
            'status' => 'success',
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Create warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        if ($user && !PermissionManager::can($user, 'warehouse', 'create')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $branchId = intval($request->input('branch_id', 1));

        try {
            $warehouse = WarehouseManagementService::createWarehouse($companyId, $branchId, $request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse created successfully',
                'warehouse' => $warehouse,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Update warehouse.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        if ($user && !PermissionManager::can($user, 'warehouse', 'update')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $warehouse = WarehouseManagementService::updateWarehouse($id, $request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse updated successfully',
                'warehouse' => $warehouse,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Deactivate warehouse.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        if ($user && !PermissionManager::can($user, 'warehouse', 'delete')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $warehouse = WarehouseManagementService::deactivateWarehouse($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse deactivated successfully',
                'warehouse' => $warehouse,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Stock by location for a product.
     */
    public function stockByLocation(int $productId): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $stock = WarehouseManagementService::getStockByLocation($companyId, $productId);

        return response()->json([
            'status' => 'success',
            'stock' => $stock,
        ]);
    }
}
