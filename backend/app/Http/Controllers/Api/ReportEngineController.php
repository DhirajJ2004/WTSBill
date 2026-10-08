<?php

namespace App\Http\Controllers\Api;

use App\Reporting\Types\ReportRegistry;
use App\Reporting\Services\MasterReportService;
use App\Reporting\Services\ScheduledReportService;
use App\Reporting\Services\ManagementDashboardService;
use App\Models\ReportSavedView;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Exception;

class ReportEngineController
{
    /**
     * Get Report Registry & Categories metadata
     */
    public function index(): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        $allReports = ReportRegistry::getAllReports();
        $categories = ReportRegistry::getCategories();

        // Saved views & favorites for user
        $savedViews = ReportSavedView::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                }
            })->get();

        return response()->json([
            'status' => 'success',
            'categories' => $categories,
            'reports' => array_values($allReports),
            'saved_views' => $savedViews,
        ]);
    }

    /**
     * Get Report Metadata
     */
    public function show(string $reportKey): JsonResponse
    {
        $meta = ReportRegistry::getReportMeta(strtoupper($reportKey));
        if (!$meta) {
            return response()->json(['status' => 'error', 'message' => "Report '{$reportKey}' not found"], 404);
        }

        return response()->json([
            'status' => 'success',
            'report' => $meta,
        ]);
    }

    /**
     * Get Report Dataset with filters, charts, and KPIs
     */
    public function getReportData(string $reportKey): JsonResponse
    {
        $request = request();
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        $requestedCompId = $request ? ($request->input('company_id') ?? $request->query('company_id')) : null;
        if ($requestedCompId && (int)$requestedCompId !== (int)$companyId) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden: Cannot access report for another company.'], 403);
        }

        $meta = ReportRegistry::getReportMeta(strtoupper($reportKey));
        if (!$meta) {
            return response()->json(['status' => 'error', 'message' => "Report '{$reportKey}' not found"], 404);
        }

        // Check permission if user is present
        if ($user && !empty($meta['permission'])) {
            $parts = explode('.', $meta['permission']);
            if (count($parts) === 2 && !PermissionManager::can($user, $parts[0], $parts[1])) {
                return response()->json(['status' => 'error', 'message' => "Forbidden: You don't have {$meta['permission']} permission"], 403);
            }
        }

        try {
            $filters = $request ? $request->all() : [];
            $dataset = MasterReportService::generateReportData(
                strtoupper($reportKey),
                $companyId,
                $filters,
                $user?->id,
                $user?->name ?: 'Admin'
            );

            return response()->json([
                'status' => 'success',
                'data' => $dataset,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export Report to CSV, Excel, or PDF
     */
    public function export(string $reportKey): Response|JsonResponse
    {
        $request = request();
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        $format = $request ? $request->input('format', 'CSV') : 'CSV';
        $filters = $request ? $request->except('format') : [];

        try {
            $file = MasterReportService::exportReport(
                strtoupper($reportKey),
                $companyId,
                $format,
                $filters,
                $user?->id,
                $user?->name ?: 'Admin'
            );

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Disposition' => "attachment; filename=\"{$file['file_name']}\"",
                'Content-Length' => $file['size'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Save / Update Custom Report View
     */
    public function saveView(): JsonResponse
    {
        $request = request();
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        $reportKey = strtoupper($request->input('report_key', ''));
        $name = $request->input('name', 'Custom View');

        $view = ReportSavedView::create([
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'report_key' => $reportKey,
            'name' => $name,
            'filters_json' => $request->input('filters', []),
            'columns_json' => $request->input('columns', []),
            'sort_json' => $request->input('sort', []),
            'is_default' => (bool)$request->input('is_default', false),
            'is_favorite' => (bool)$request->input('is_favorite', false),
        ]);

        return response()->json([
            'status' => 'success',
            'view' => $view,
        ]);
    }

    /**
     * Delete Saved View
     */
    public function deleteView(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $view = ReportSavedView::where('company_id', $companyId)->find($id);
        if ($view) {
            $view->delete();
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * List Scheduled Reports
     */
    public function listSchedules(): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $schedules = ScheduledReportService::listSchedules($companyId);

        return response()->json([
            'status' => 'success',
            'data' => $schedules,
        ]);
    }

    /**
     * Create Scheduled Report
     */
    public function createSchedule(): JsonResponse
    {
        $request = request();
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        try {
            $schedule = ScheduledReportService::createSchedule($companyId, $request->all(), $user?->name ?: 'Admin');
            return response()->json([
                'status' => 'success',
                'schedule' => $schedule,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Executive Dashboard Endpoint
     */
    public function getDashboard(): JsonResponse
    {
        $request = request();
        $companyId = AuthMiddleware::getTenantId();
        $filters = $request ? $request->all() : [];

        $data = ManagementDashboardService::getManagementDashboard($companyId, $filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
