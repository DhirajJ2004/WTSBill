<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AnalyticsService;

/**
 * Analytics Controller.
 * Handles Executive BI Dashboard, Profit & Margin Analytics,
 * Sales velocity rankings, and Executive CSV report generation.
 */
class AnalyticsController
{
    protected AnalyticsService $analyticsService;

    public function __construct(?AnalyticsService $analyticsService = null)
    {
        $this->analyticsService = $analyticsService ?? new AnalyticsService();
    }

    /**
     * Executive BI Analytics Dashboard View.
     * Route: GET /analytics/bi
     */
    public function index(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $dashboard = $this->analyticsService->getExecutiveDashboard($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('analytics/bi_dashboard', [
            'title' => 'Executive BI & Business Analytics',
            'currentUri' => '/analytics/bi',
            'dashboard' => $dashboard,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Executive BI Analytics JSON API.
     * Route: GET /api/analytics/bi
     */
    public function api(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $dashboard = $this->analyticsService->getExecutiveDashboard($companyId, [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        return Response::json(['success' => true, 'data' => $dashboard]);
    }

    /**
     * Export Executive BI Report as CSV.
     * Route: GET /analytics/bi/export
     */
    public function export(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csv = $this->analyticsService->exportExecutiveReportCsv($companyId, [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        $filename = 'executive_bi_report_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}

