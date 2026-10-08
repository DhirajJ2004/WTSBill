<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Services\BranchManagementService;
use App\Services\BranchAccountingAndReportingService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BranchController
{
    /**
     * List all branches.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        BranchManagementService::ensureMainBranch($companyId);

        if ($user) {
            $branches = BranchManagementService::getUserAccessibleBranches($user, $companyId);
        } else {
            $branches = Branch::where('company_id', $companyId)->get()->toArray();
        }

        return response()->json([
            'status' => 'success',
            'branches' => $branches,
        ]);
    }

    /**
     * Show single branch.
     */
    public function show(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $branch = Branch::where('company_id', $companyId)->with(['warehouses', 'settings'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'branch' => $branch,
        ]);
    }

    /**
     * Create branch.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        if ($user && !PermissionManager::can($user, 'branch', 'create')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $branch = BranchManagementService::createBranch($companyId, $request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'branch' => $branch,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Update branch.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        if ($user && !PermissionManager::can($user, 'branch', 'update')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $branch = BranchManagementService::updateBranch($id, $request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Branch updated successfully',
                'branch' => $branch,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Deactivate branch.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        if ($user && !PermissionManager::can($user, 'branch', 'delete')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $branch = BranchManagementService::deactivateBranch($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Branch deactivated successfully',
                'branch' => $branch,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Performance Comparison across branches.
     */
    public function comparison(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $data = BranchAccountingAndReportingService::getBranchPerformanceComparison(
            $companyId,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
