<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\ExtendedAccountingService;

/**
 * Extended Accounting Controller.
 * Handles double-entry Trial Balance reports, Cash Book registers,
 * Bank Book reconciliation statements, and Cash Flow Statements.
 */
class ExtendedAccountingController
{
    protected ExtendedAccountingService $accountingService;

    public function __construct(?ExtendedAccountingService $accountingService = null)
    {
        $this->accountingService = $accountingService ?? new ExtendedAccountingService();
    }

    /**
     * Trial Balance Statement View.
     * Route: GET /reports/trial-balance
     */
    public function trialBalance(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $asOfDate = (string)$req->get('as_of_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $trialBalance = $this->accountingService->getTrialBalance($companyId, [
            'as_of_date' => $asOfDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('accounting/trial_balance', [
            'title' => 'Trial Balance Statement',
            'currentUri' => '/reports/trial-balance',
            'trialBalance' => $trialBalance,
            'branches' => $branches,
            'asOfDate' => $asOfDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Trial Balance JSON API.
     * Route: GET /api/reports/trial-balance
     */
    public function trialBalanceApi(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $data = $this->accountingService->getTrialBalance($companyId, [
            'as_of_date' => (string)$req->get('as_of_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        return Response::json(['success' => true, 'data' => $data]);
    }

    /**
     * Export Trial Balance as CSV.
     * Route: GET /reports/trial-balance/export
     */
    public function trialBalanceExport(Request $req): Response
    {
        Gate::authorize('reports.export', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csv = $this->accountingService->exportTrialBalanceCsv($companyId, [
            'as_of_date' => (string)$req->get('as_of_date', date('Y-m-d')),
            'branch_id' => (int)$req->get('branch_id', 0),
        ]);

        $filename = 'trial_balance_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Cash Book Ledger View.
     * Route: GET /accounting/cash-book
     */
    public function cashBook(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $cashBook = $this->accountingService->getCashBook($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('accounting/cash_book', [
            'title' => 'Cash Book Ledger',
            'currentUri' => '/accounting/cash-book',
            'cashBook' => $cashBook,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Bank Book Register View.
     * Route: GET /accounting/bank-book
     */
    public function bankBook(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $bankAccountId = (int)$req->get('bank_account_id', 0);
        $branchId = (int)$req->get('branch_id', 0);

        $bankBook = $this->accountingService->getBankBook($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'bank_account_id' => $bankAccountId,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('accounting/bank_book', [
            'title' => 'Bank Book Register',
            'currentUri' => '/accounting/bank-book',
            'bankBook' => $bankBook,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBankId' => $bankAccountId,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Cash Flow Statement View.
     * Route: GET /reports/cash-flow
     */
    public function cashFlow(Request $req): Response
    {
        Gate::authorize('reports.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $fromDate = (string)$req->get('from_date', date('Y-m-01'));
        $toDate = (string)$req->get('to_date', date('Y-m-d'));
        $branchId = (int)$req->get('branch_id', 0);

        $statement = $this->accountingService->getCashFlowStatement($companyId, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
        ]);

        $branches = Database::select("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('reports/cash_flow', [
            'title' => 'Cash Flow Statement',
            'currentUri' => '/reports/cash-flow',
            'statement' => $statement,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedBranch' => $branchId,
        ], 'layouts/app');

        return Response::html($html);
    }
}

