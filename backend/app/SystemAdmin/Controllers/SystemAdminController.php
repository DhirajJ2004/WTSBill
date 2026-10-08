<?php

namespace App\SystemAdmin\Controllers;

use App\SystemAdmin\Services\SystemHealthService;
use App\SystemAdmin\Services\DataIntegrityService;
use App\SystemAdmin\Services\NumberingConfigService;
use App\SystemAdmin\Services\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemAdminController
{
    public function health(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $health = SystemHealthService::checkSystemHealth($companyId);
        return response()->json(['success' => true, 'data' => $health]);
    }

    public function runIntegrityCheck(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $res = DataIntegrityService::runIntegrityCheck($companyId);
        return response()->json(['success' => true, 'data' => $res]);
    }

    public function getNumberingConfigs(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $branchId = $request->input('branch_id');
        $configs = NumberingConfigService::getConfigurations($companyId, $branchId);
        return response()->json(['success' => true, 'data' => $configs]);
    }

    public function updateNumberingConfig(Request $request, string $documentType): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $userName = $request->attributes->get('user_name', 'Admin');

        try {
            $config = NumberingConfigService::updateConfiguration($companyId, $documentType, $request->all(), $userName);
            return response()->json(['success' => true, 'data' => $config]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function listSessions(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $sessions = SessionManagementService::listActiveSessions($companyId);
        return response()->json(['success' => true, 'data' => $sessions]);
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $userName = $request->attributes->get('user_name', 'Admin');
        try {
            SessionManagementService::revokeSession($id, $userName);
            return response()->json(['success' => true, 'message' => 'Session revoked successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
