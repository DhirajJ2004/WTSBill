<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\GstComplianceService;

/**
 * GST Compliance Controller.
 * Handles GSTR-3B monthly return summaries, GST Sales Register,
 * and GST Purchase Register statements and statutory exports.
 */
class GstComplianceController
{
    protected GstComplianceService $gstService;

    public function __construct(?GstComplianceService $gstService = null)
    {
        $this->gstService = $gstService ?? new GstComplianceService();
    }

    /**
     * GSTR-3B Monthly Return Summary View.
     * Route: GET /reports/gstr-3b
     */
    public function gstr3b(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $summary = $this->gstService->getGstr3bSummary($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('reports/gstr_3b', [
            'title' => 'GSTR-3B Monthly Return Summary',
            'currentUri' => '/reports/gstr-3b',
            'summary' => $summary,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GSTR-3B JSON API.
     * Route: GET /api/reports/gstr-3b
     */
    public function gstr3bApi(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $summary = $this->gstService->getGstr3bSummary($companyId, [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        return Response::json(['success' => true, 'data' => $summary]);
    }

    /**
     * Export GSTR-3B as JSON or CSV.
     * Route: GET /reports/gstr-3b/export
     */
    public function gstr3bExport(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $format = strtolower(trim((string)$req->get('format', 'csv')));
        $filters = [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ];

        if ($format === 'json') {
            $data = $this->gstService->exportGstr3bJson($companyId, $filters);
            $filename = 'gstr3b_' . ($data['ret_period'] ?? date('mY')) . '.json';
            $resp = Response::json($data);
            return $resp->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                        ->withHeader('Content-Type', 'application/json');
        }

        $csv = $this->gstService->exportGstr3bCsv($companyId, $filters);
        $filename = 'gstr3b_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * GST Sales Register View.
     * Route: GET /reports/gst-sales-register
     */
    public function salesRegister(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);
        $supplyType = (string)$req->get('supply_type', 'ALL');

        $register = $this->gstService->getSalesRegister($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
            'supply_type' => $supplyType,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('reports/gst_sales_register', [
            'title' => 'GST Outward Sales Register',
            'currentUri' => '/reports/gst-sales-register',
            'register' => $register,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
            'selectedSupplyType' => $supplyType,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Export GST Sales Register CSV.
     * Route: GET /reports/gst-sales-register/export
     */
    public function salesRegisterExport(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csv = $this->gstService->exportSalesRegisterCsv($companyId, [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
            'supply_type' => (string)$req->get('supply_type', 'ALL'),
        ]);

        $filename = 'gst_sales_register_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * GST Inward Purchase Register View.
     * Route: GET /reports/gst-purchase-register
     */
    public function purchaseRegister(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $register = $this->gstService->getPurchaseRegister($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('reports/gst_purchase_register', [
            'title' => 'GST Inward Purchase Register',
            'currentUri' => '/reports/gst-purchase-register',
            'register' => $register,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Export GST Purchase Register CSV.
     * Route: GET /reports/gst-purchase-register/export
     */
    public function purchaseRegisterExport(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csv = $this->gstService->exportPurchaseRegisterCsv($companyId, [
            'from_date' => (string)$req->get('from_date', date('Y-m-01')),
            'to_date' => (string)$req->get('to_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        $filename = 'gst_purchase_register_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}

