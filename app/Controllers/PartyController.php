<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\PartyService;

/**
 * Unified Parties Controller for WTSBill ERP.
 * Serves the single combined Parties table, real-time multi-criteria filtering,
 * server-side pagination, Add/Edit party modals, and 360-degree party profiles.
 */
class PartyController
{
    protected PartyService $partyService;

    public function __construct(?PartyService $partyService = null)
    {
        $this->partyService = $partyService ?? new PartyService();
    }

    /**
     * Unified Parties Index listing with server-side filters & pagination.
     * Route: GET /parties
     */
    public function index(Request $req): Response
    {
        $filters = [
            'type' => $req->query('type') ?? 'all',
            'q' => $req->query('q') ?? ($req->query('search') ?? ''),
            'search_by' => $req->query('search_by') ?? 'all',
            'city' => $req->query('city') ?? '',
            'state' => $req->query('state') ?? '',
            'status' => $req->query('status') ?? 'all',
            'balance' => $req->query('balance') ?? 'all',
        ];

        $page = max(1, (int)($req->query('page') ?? 1));
        $perPage = max(5, min(100, (int)($req->query('per_page') ?? 25)));

        $data = $this->partyService->getPartiesList($filters, $page, $perPage);

        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            return Response::json([
                'success' => true,
                'data' => $data['data'],
                'pagination' => [
                    'total' => $data['total'],
                    'page' => $data['page'],
                    'per_page' => $data['per_page'],
                    'total_pages' => $data['total_pages'],
                ],
                'counts' => $data['counts'],
                'filters' => $data['filters'],
                'cities' => $data['cities'],
            ]);
        }

        $html = View::renderWithLayout('parties/index', [
            'title' => 'Parties — Customers & Suppliers',
            'currentUri' => '/parties',
            'parties' => $data['data'],
            'pagination' => [
                'total' => $data['total'],
                'page' => $data['page'],
                'per_page' => $data['per_page'],
                'total_pages' => $data['total_pages'],
            ],
            'counts' => $data['counts'],
            'filters' => $data['filters'],
            'cities' => $data['cities'],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Show Party Add Form (or redirect to modal on /parties).
     * Route: GET /parties/create
     */
    public function createForm(Request $req): Response
    {
        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'action' => 'open_create_modal']);
        }
        return Response::redirect('/parties?action=create');
    }

    /**
     * Store a newly created party (Customer, Supplier, or Both).
     * Route: POST /parties/create or POST /parties
     */
    public function store(Request $req): Response
    {
        $input = $req->all();
        $userId = Session::get('user_id') ? (int)Session::get('user_id') : 1;

        $res = $this->partyService->createParty($input, $userId);

        if ($this->expectsJson($req)) {
            return Response::json($res, $res['success'] ? 201 : 422);
        }

        if ($res['success']) {
            Session::flash('success', $res['message']);
            return Response::redirect('/parties/' . $res['party_id']);
        }

        Session::flash('error', $res['message']);
        return Response::redirect('/parties?action=create');
    }

    /**
     * 360-degree Party Profile or JSON view.
     * Route: GET /parties/{id}
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $partyId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $branchId = (int)($req->query('branch_id') ?? Session::getBranchId() ?? 0);

        $profile = $this->partyService->getPartyProfileData($partyId, $branchId);

        if (!$profile) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Party record not found.'], 404);
            }
            Session::flash('error', 'Party record not found.');
            return Response::redirect('/parties');
        }

        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            return Response::json([
                'success' => true,
                'data' => $profile,
            ]);
        }

        $html = View::renderWithLayout('parties/profile', [
            'title' => $profile['party']['name'] . ' — Party Profile',
            'currentUri' => '/parties/' . $partyId,
            'party' => $profile['party'],
            'partyType' => $profile['party_type'],
            'summary' => $profile['financial_summary'],
            'invoices' => $profile['invoices'],
            'bills' => $profile['bills'],
            'quotations' => $profile['quotations'],
            'purchaseOrders' => $profile['purchase_orders'],
            'creditNotes' => $profile['credit_notes'],
            'debitNotes' => $profile['debit_notes'],
            'payments' => $profile['payments'],
            'ledger' => $profile['ledger'],
            'hasCustomerRole' => $profile['has_customer_role'],
            'hasSupplierRole' => $profile['has_supplier_role'],
            'customerRecord' => $profile['customer_record'],
            'supplierRecord' => $profile['supplier_record'],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Update an existing party record.
     * Route: POST /parties/{id}/update or POST /parties/{id}
     */
    public function update(Request $req, mixed $id = null): Response
    {
        $partyId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $input = $req->all();
        $userId = Session::get('user_id') ? (int)Session::get('user_id') : 1;

        $res = $this->partyService->updateParty($partyId, $input, $userId);

        if ($this->expectsJson($req)) {
            return Response::json($res, $res['success'] ? 200 : 422);
        }

        if ($res['success']) {
            Session::flash('success', $res['message']);
            return Response::redirect('/parties/' . $partyId);
        }

        Session::flash('error', $res['message']);
        return Response::redirect('/parties/' . $partyId);
    }

    /**
     * Toggle party active / inactive status.
     * Route: POST /parties/{id}/toggle-status
     */
    public function toggleStatus(Request $req, mixed $id = null): Response
    {
        $partyId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $res = $this->partyService->togglePartyStatus($partyId);

        if ($this->expectsJson($req)) {
            return Response::json($res, $res['success'] ? 200 : 404);
        }

        Session::flash($res['success'] ? 'success' : 'error', $res['message']);
        return Response::redirect('/parties');
    }

    /**
     * Soft delete or deactivate a party.
     * Route: POST /parties/{id}/delete or DELETE /parties/{id}
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        $partyId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $res = $this->partyService->deleteParty($partyId);

        if ($this->expectsJson($req)) {
            return Response::json($res, $res['success'] ? 200 : 404);
        }

        Session::flash($res['success'] ? 'success' : 'error', $res['message']);
        return Response::redirect('/parties');
    }

    /**
     * Export Party Ledger Statement as CSV.
     * Route: GET /parties/{id}/statement/csv
     */
    public function statementCsv(Request $req, mixed $id = null): Response
    {
        $partyId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $profile = $this->partyService->getPartyProfileData($partyId);

        if (!$profile) {
            return Response::json(['success' => false, 'message' => 'Party record not found.'], 404);
        }

        $party = $profile['party'];
        $ledger = $profile['ledger'] ?? [];

        $filename = 'Statement_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $party['party_code'] . '_' . $party['name']) . '.csv';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Party Ledger Statement']);
        fputcsv($output, ['Party Name:', $party['name']]);
        fputcsv($output, ['Party Code:', $party['party_code']]);
        fputcsv($output, ['Party Type:', $party['party_type']]);
        fputcsv($output, ['GSTIN:', $party['gstin'] ?: 'N/A']);
        fputcsv($output, ['Phone:', $party['phone'] ?: 'N/A']);
        fputcsv($output, ['Opening Balance:', number_format((float)$party['opening_balance'], 2)]);
        fputcsv($output, ['Generated At:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['Date', 'Transaction Type', 'Reference No', 'Description', 'Role', 'Debit (INR)', 'Credit (INR)', 'Running Balance (INR)']);

        foreach ($ledger as $row) {
            fputcsv($output, [
                $row['date'],
                $row['type'],
                $row['reference'],
                $row['description'],
                $row['role'] ?? '',
                number_format((float)$row['debit'], 2),
                number_format((float)$row['credit'], 2),
                number_format((float)($row['running_balance'] ?? 0.0), 2),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Check if request expects JSON response.
     */
    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $requestedWith = (string)($req->header('x-requested-with') ?? '');
        $path = $req->getPath();

        return str_starts_with($path, '/api/') ||
               str_contains($accept, 'application/json') ||
               strtolower($requestedWith) === 'xmlhttprequest';
    }
}

