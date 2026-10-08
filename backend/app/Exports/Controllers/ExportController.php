<?php

namespace App\Exports\Controllers;

use App\Exports\Services\ExportService;
use App\Models\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExportController
{
    public function export(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $dataType = $request->input('data_type');
        $format = $request->input('format', 'CSV');
        $filters = $request->input('filters', []);
        $columns = $request->input('columns');
        $branchId = $request->input('branch_id');

        try {
            $res = ExportService::createExport(
                $companyId,
                $dataType,
                $format,
                $filters,
                $columns,
                $branchId,
                $request->attributes->get('user_name', 'Admin')
            );
            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function download(Request $request, string $token): Response
    {
        $job = ExportJob::where('download_token', $token)
            ->where('download_token_expires_at', '>', \Carbon\Carbon::now())
            ->first();

        if (!$job) {
            return response('Invalid or expired download token.', 403);
        }

        // Re-generate on demand or fetch
        $res = ExportService::createExport(
            $job->company_id,
            $job->data_type,
            $job->export_format,
            $job->filters_json ?: [],
            $job->columns_json,
            $job->branch_id,
            'Token Download'
        );

        $contentType = ($job->export_format === 'XLSX')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response($res['content'], 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$job->file_name}\"",
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $companyId = \App\Http\Middleware\AuthMiddleware::getTenantId();
        $jobs = ExportJob::where('company_id', $companyId)->orderBy('id', 'desc')->take(50)->get();
        return response()->json(['success' => true, 'data' => $jobs]);
    }
}
