<?php

namespace App\Migrations\Controllers;

use App\Migrations\Profiles\MigrationProfileService;
use App\Migrations\Services\DataMigrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MigrationController
{
    public function getProfiles(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $type = $request->input('data_type');
        $profiles = MigrationProfileService::listProfiles($companyId, $type);
        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function saveProfile(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $name = $request->input('name');
        $dataType = $request->input('data_type');
        $source = $request->input('source_software', 'GENERIC');
        $mapping = $request->input('mapping', []);

        try {
            $profile = MigrationProfileService::saveProfile(
                $companyId,
                $name,
                $dataType,
                $source,
                $mapping,
                $request->attributes->get('user_name', 'Admin')
            );
            return response()->json(['success' => true, 'data' => $profile]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getMigrations(Request $request): JsonResponse
    {
        $history = DataMigrationService::getMigrationHistory();
        return response()->json(['success' => true, 'data' => $history]);
    }
}
