<?php

namespace App\Controllers;

use App\Services\ReportService;
use App\Middleware\AuthMiddleware;

class ReportController
{
    /**
     * Web View: Main Reports Hub
     */
    public function index()
    {
        include __DIR__ . '/../../views/reports/index.php';
    }

    /**
     * API / Web: Get Report Data JSON
     */
    public function apiReportData(string $type)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = $_GET;

        try {
            $data = ReportService::generateReport($type, $companyId, $filters);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Export Report to CSV / Excel
     */
    public function export(string $type)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = $_GET;

        try {
            $csv = ReportService::exportCSV($type, $companyId, $filters);
            $filename = strtolower($type) . '_report_' . date('Y-m-d_His') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $csv;
            exit;
        } catch (\Throwable $e) {
            http_response_code(400);
            echo "Error generating export: " . htmlspecialchars($e->getMessage());
            exit;
        }
    }
}
