<?php

namespace App\Backups\Controllers;

use App\Backups\Services\BackupService;
use App\Backups\Services\RestoreService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BackupController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $backups = BackupService::listBackups($companyId);
        return response()->json(['success' => true, 'data' => $backups]);
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $type = $request->input('type', 'MANUAL');
        $retention = (int)$request->input('retention_days', 30);
        $userName = $request->attributes->get('user_name', 'Admin');

        try {
            $backup = BackupService::createBackup($companyId, $type, $retention, $userName);
            return response()->json(['success' => true, 'data' => $backup]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        try {
            $res = BackupService::verifyBackup($id);
            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function validateRestore(Request $request, int $id): JsonResponse
    {
        try {
            $res = RestoreService::validateForRestore($id);
            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $userName = $request->attributes->get('user_name', 'Admin');
        $mode = $request->input('mode', 'BUSINESS');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $res = RestoreService::executeRestore($id, $mode, $userName, $ip);
            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
