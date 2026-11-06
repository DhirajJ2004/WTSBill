<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuditLogger;
use WTSBill\Services\CustomerAdvanceService;

/**
 * Customer Advance & Receivables Aging Controller.
 * Manages advance payments, drawdown against open invoices, and aging reports.
 */
class CustomerAdvanceController
{
    protected CustomerAdvanceService $advanceService;

    public function __construct(?CustomerAdvanceService $advanceService = null)
    {
        $this->advanceService = $advanceService ?? new CustomerAdvanceService();
    }

    /**
     * List Customer Advances.
     * Route: GET /payments/advances
     */
    public function index(Request $req): Response
    {
        Gate::authorize('payments.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $status = (string)$req->get('status', 'ALL');
        $advances = $this->advanceService->getAdvances($companyId, [
            'status' => $status,
            'search' => (string)$req->get('search', ''),
            'customer_id' => (int)$req->get('customer_id', 0),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        $customers = Database::select("SELECT id, name FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $counts = [
            'ALL' => count($this->advanceService->getAdvances($companyId, ['status' => 'ALL'])),
            'ACTIVE' => count($this->advanceService->getAdvances($companyId, ['status' => 'ACTIVE'])),
            'EXHAUSTED' => count($this->advanceService->getAdvances($companyId, ['status' => 'EXHAUSTED'])),
        ];

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'data' => $advances, 'counts' => $counts]);
        }

        $html = View::renderWithLayout('payments/advances/index', [
            'title' => 'Customer Advances',
            'currentUri' => '/payments/advances',
            'advances' => $advances,
            'customers' => $customers,
            'counts' => $counts,
            'selectedStatus' => $status,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Show Advance Creation Form.
     * Route: GET /payments/advances/create
     */
    public function create(Request $req): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $customers = Database::select("SELECT id, name, company_name, current_balance FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);
        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $preSelectedCustomer = (int)$req->get('customer_id', 0);

        $html = View::renderWithLayout('payments/advances/create', [
            'title' => 'Record Customer Advance',
            'currentUri' => '/payments/advances',
            'customers' => $customers,
            'branches' => $branches,
            'preSelectedCustomer' => $preSelectedCustomer,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store Customer Advance.
     * Route: POST /payments/advances/create, POST /payments/advances
     */
    public function store(Request $req): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $advanceId = $this->advanceService->recordAdvance($companyId, $payload, $userName);

            AuditLogger::log(
                action: 'create_advance',
                module: 'payments',
                entityType: 'CUSTOMER_ADVANCE',
                entityId: $advanceId,
                status: 'success',
                notes: "Recorded customer advance payment #{$advanceId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Customer advance recorded successfully.',
                    'advance_id' => $advanceId,
                ], 201);
            }

            Session::flash('success', 'Customer advance recorded successfully.');
            return Response::redirect('/payments/advances/' . $advanceId);
        } catch (ValidationException $ve) {
            $errs = $ve->getErrors();
            $msg = ($ve->getMessage() !== 'The given data was invalid.') ? $ve->getMessage() : (!empty($errs) ? reset($errs) : $ve->getMessage());

            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg, 'errors' => $errs], 422);
            }

            Session::flash('error', $msg);
            return Response::redirect('/payments/advances/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            Session::flash('error', 'Failed to record customer advance: ' . $e->getMessage());
            return Response::redirect('/payments/advances/create');
        }
    }

    /**
     * View Advance Details and Drawdown History.
     * Route: GET /payments/advances/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $advanceId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));

        $advance = $this->advanceService->getAdvance($companyId, $advanceId);
        if (!$advance) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Advance not found.'], 404);
            }
            Session::flash('error', 'Customer advance not found.');
            return Response::redirect('/payments/advances');
        }

        // Open unpaid invoices for this customer
        $unpaidInvoices = Database::select(
            "SELECT id, invoice_number, invoice_date, due_date, grand_total, amount_paid, amount_due, status 
             FROM invoices 
             WHERE customer_id = ? AND company_id = ? AND amount_due > 0.001 AND deleted_at IS NULL AND status NOT IN ('CANCELLED', 'DRAFT')
             ORDER BY invoice_date ASC, id ASC",
            [$advance['customer_id'], $companyId]
        );

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'advance' => $advance, 'open_invoices' => $unpaidInvoices]);
        }

        $html = View::renderWithLayout('payments/advances/show', [
            'title' => 'Advance Voucher: ' . ($advance['payment_number'] ?? '#' . $advanceId),
            'currentUri' => '/payments/advances',
            'advance' => $advance,
            'unpaidInvoices' => $unpaidInvoices,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Drawdown/Allocate Advance against Invoices.
     * Route: POST /payments/advances/{id}/allocate
     */
    public function allocate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $advanceId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $userName = (string)(Gate::user()['name'] ?? Session::get('user_name') ?? 'Admin');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $allocations = $payload['allocations'] ?? [];

        try {
            $result = $this->advanceService->allocateAdvance($companyId, $advanceId, $allocations, $userName);

            AuditLogger::log(
                action: 'allocate_advance',
                module: 'payments',
                entityType: 'CUSTOMER_ADVANCE',
                entityId: $advanceId,
                status: 'success',
                notes: "Allocated ₹{$result['total_allocated']} of advance #{$advanceId} to invoices"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Advance allocated against invoice(s) successfully.',
                    'data' => $result,
                ], 200);
            }

            Session::flash('success', "Successfully allocated ₹" . number_format((float)$result['total_allocated'], 2) . " from advance balance.");
            return Response::redirect('/payments/advances/' . $advanceId);
        } catch (ValidationException $ve) {
            $errs = $ve->getErrors();
            $msg = ($ve->getMessage() !== 'The given data was invalid.') ? $ve->getMessage() : (!empty($errs) ? reset($errs) : $ve->getMessage());

            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $msg, 'errors' => $errs], 422);
            }

            Session::flash('error', $msg);
            return Response::redirect('/payments/advances/' . $advanceId);
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            Session::flash('error', 'Allocation failed: ' . $e->getMessage());
            return Response::redirect('/payments/advances/' . $advanceId);
        }
    }

    /**
     * Accounts Receivable Aging Report View.
     * Route: GET /reports/receivables-aging
     */
    public function receivablesAging(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $asOfDate = (string)$req->get('as_of_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);
        $customerId = (int)$req->get('customer_id', 0);

        $report = $this->advanceService->getReceivablesAging($companyId, [
            'as_of_date' => $asOfDate,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);
        $customers = Database::select("SELECT id, name FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('reports/receivables_aging', [
            'title' => 'Accounts Receivable Aging Report',
            'currentUri' => '/reports/receivables-aging',
            'report' => $report,
            'branches' => $branches,
            'customers' => $customers,
            'asOfDate' => $asOfDate,
            'selectedBranch' => $branchId,
            'selectedCustomer' => $customerId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Accounts Receivable Aging JSON API.
     * Route: GET /api/reports/receivables-aging
     */
    public function receivablesAgingApi(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $report = $this->advanceService->getReceivablesAging($companyId, [
            'as_of_date' => (string)$req->get('as_of_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
            'customer_id' => (int)$req->get('customer_id', 0),
        ]);

        return Response::json(['success' => true, 'data' => $report]);
    }

    /**
     * Export Accounts Receivable Aging to CSV.
     * Route: GET /reports/receivables-aging/export
     */
    public function receivablesAgingExport(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csv = $this->advanceService->exportReceivablesAgingCsv($companyId, [
            'as_of_date' => (string)$req->get('as_of_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
            'customer_id' => (int)$req->get('customer_id', 0),
        ]);

        $filename = 'receivables_aging_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}

