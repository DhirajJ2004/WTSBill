<?php

namespace App\Audit\Controllers;

use App\Audit\Services\AuditTrailService;
use App\Audit\Formatters\AuditLogFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $filters = $request->all();

        try {
            $result = AuditTrailService::queryLogs($companyId, $filters);
            // Enrich with human-readable summary
            foreach ($result['data'] as &$log) {
                $log['story'] = AuditLogFormatter::formatHumanReadable($log);
            }
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
