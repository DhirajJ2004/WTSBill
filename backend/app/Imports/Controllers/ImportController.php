<?php

namespace App\Imports\Controllers;

use App\Imports\Services\ImportService;
use App\Imports\Templates\TemplateGeneratorService;
use App\Imports\Mappers\ColumnAutoMapper;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ImportController
{
    public function getTemplate(Request $request, string $dataType): JsonResponse
    {
        try {
            $template = TemplateGeneratorService::getTemplate($dataType);
            return response()->json(['success' => true, 'data' => $template]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function downloadTemplateCsv(Request $request, string $dataType): Response
    {
        $csv = TemplateGeneratorService::generateCsvTemplate($dataType);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"template_{$dataType}.csv\"",
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $dataType = $request->input('data_type');
        $rawRows = $request->input('rows', []);
        $fileName = $request->input('file_name', 'upload.csv');
        $branchId = $request->input('branch_id');

        try {
            $job = ImportService::createImportJob($companyId, $dataType, $rawRows, $fileName, $branchId, $request->attributes->get('user_name', 'Admin'));
            return response()->json(['success' => true, 'data' => $job]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $mapping = $request->input('mapping', []);
        try {
            $preview = ImportService::previewImport($id, $mapping);
            return response()->json(['success' => true, 'data' => $preview]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $duplicateAction = $request->input('duplicate_action', 'SKIP');
        try {
            $result = ImportService::confirmAndProcess($id, $duplicateAction, $request->attributes->get('user_name', 'Admin'));
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function downloadErrorReport(Request $request, int $id): Response
    {
        $csv = ImportService::generateErrorReport($id);
        if (!$csv) {
            return response('No errors found for this import job.', 404);
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"error_report_job_{$id}.csv\"",
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $jobs = ImportJob::where('company_id', $companyId)->orderBy('id', 'desc')->take(50)->get();
        return response()->json(['success' => true, 'data' => $jobs]);
    }
}
