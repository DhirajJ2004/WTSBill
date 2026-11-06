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
use WTSBill\Services\SupplierService;

/**
 * Supplier Controller.
 * Handles supplier directory, modal registration, view, and updates.
 */
class SupplierController
{
    protected SupplierService $supplierService;

    public function __construct(?SupplierService $supplierService = null)
    {
        $this->supplierService = $supplierService ?? new SupplierService();
    }

    /**
     * Supplier master directory view.
     */
    public function index(Request $req): Response
    {
        $search = (string)($req->query('search') ?? '');
        $suppliers = $this->supplierService->getSuppliers($search);

        $html = View::renderWithLayout('parties/suppliers', [
            'title' => 'Suppliers & Vendors',
            'currentUri' => '/suppliers',
            'suppliers' => $suppliers,
            'search' => $search,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new supplier.
     */
    public function store(Request $req): Response
    {
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $supplier = $this->supplierService->createSupplier($payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Supplier '{$supplier['name']}' registered successfully.",
                    'supplier' => $supplier,
                ], 201);
            }

            Session::flash('success', "Supplier '{$supplier['name']}' registered successfully.");
            return Response::redirect('/suppliers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/suppliers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'An error occurred while saving supplier: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to register supplier: ' . $e->getMessage());
            return Response::redirect('/suppliers');
        }
    }

    /**
     * Show single supplier record (JSON for modal / details or Profile Page).
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));

        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            $supplier = $this->supplierService->getSupplier($supplierId);
            if (!$supplier) {
                return Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
            }
            return Response::json(['success' => true, 'supplier' => $supplier]);
        }

        return $this->profile($req, $supplierId);
    }

    /**
     * 360-degree Supplier Profile page.
     * Route: GET /suppliers/{id} or /suppliers/{id}/profile
     */
    public function profile(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $branchId = (int)($req->query('branch_id') ?? Session::getBranchId() ?? 0);

        $profileData = $this->supplierService->getSupplierProfileData($supplierId, $branchId);

        if (!$profileData) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Supplier record not found.'], 404);
            }
            Session::flash('error', 'Supplier record not found.');
            return Response::redirect('/suppliers');
        }

        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            return Response::json($profileData);
        }

        $bankAccounts = Database::select(
            "SELECT id, bank_name, account_name, account_number FROM bank_accounts WHERE company_id = ? AND is_active = 1 ORDER BY is_primary DESC, id ASC",
            [Session::getCompanyId() ?? 1]
        );

        $html = View::renderWithLayout('parties/supplier_profile', [
            'title' => $profileData['supplier']['name'] . ' — Supplier Profile',
            'currentUri' => '/suppliers/' . $supplierId,
            'data' => $profileData,
            'supplier' => $profileData['supplier'],
            'stats' => $profileData['financial_summary'],
            'bills' => $profileData['bills'],
            'outstandingBills' => $profileData['outstanding_bills'],
            'payments' => $profileData['payments'],
            'debitNotes' => $profileData['debit_notes'],
            'ledger' => $profileData['ledger'],
            'bankAccounts' => $bankAccounts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Export supplier ledger statement as CSV.
     * Route: GET /suppliers/{id}/statement/csv
     */
    public function statementCsv(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $branchId = (int)($req->query('branch_id') ?? Session::getBranchId() ?? 0);

        $profileData = $this->supplierService->getSupplierProfileData($supplierId, $branchId);
        if (!$profileData) {
            return Response::json(['error' => 'Supplier not found'], 404);
        }

        $supplier = $profileData['supplier'];
        $ledger = $profileData['ledger'];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['WTSBill ERP — Supplier Account Statement']);
        fputcsv($output, ['Supplier:', $supplier['name'], 'Code:', $supplier['supplier_code']]);
        fputcsv($output, ['GSTIN:', $supplier['gstin'], 'Phone:', $supplier['phone']]);
        fputcsv($output, ['Generated At:', date('d-M-Y H:i:s'), 'Branch:', $supplier['assigned_branch']]);
        fputcsv($output, []);
        fputcsv($output, ['Date', 'Type', 'Voucher #', 'Ref / Inv #', 'Description', 'Debit (Payment/Return)', 'Credit (Inward Bill)', 'Running Balance (Payable)']);

        foreach ($ledger as $row) {
            fputcsv($output, [
                $row['date'],
                $row['type'],
                $row['voucher_no'],
                $row['ref_no'],
                $row['description'],
                $row['debit'] > 0 ? number_format((float)$row['debit'], 2, '.', '') : '0.00',
                $row['credit'] > 0 ? number_format((float)$row['credit'], 2, '.', '') : '0.00',
                number_format((float)$row['running_balance'], 2, '.', ''),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $supplier['name']);
        $filename = "Statement_{$safeName}_" . date('Ymd_His') . ".csv";

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Update supplier record.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $supplier = $this->supplierService->updateSupplier($supplierId, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Supplier '{$supplier['name']}' updated successfully.",
                    'supplier' => $supplier,
                ]);
            }

            Session::flash('success', "Supplier '{$supplier['name']}' updated successfully.");
            return Response::redirect('/suppliers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/suppliers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update supplier: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update supplier: ' . $e->getMessage());
            return Response::redirect('/suppliers');
        }
    }

    /**
     * Delete supplier record.
     * Routes: POST /suppliers/{id}/delete, DELETE /suppliers/{id}
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('suppliers.delete', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? $req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM suppliers WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$supplierId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
            }
            Session::flash('error', 'Supplier not found.');
            return Response::redirect('/suppliers');
        }

        try {
            // Check if supplier has linked purchase bills
            $purchasesCountRow = Database::selectOne(
                "SELECT COUNT(*) as cnt FROM purchases WHERE supplier_id = ? AND company_id = ?",
                [$supplierId, $companyId]
            );
            $hasPurchases = (int)($purchasesCountRow['cnt'] ?? 0) > 0;

            if ($hasPurchases) {
                // Soft-delete to preserve purchase history and ledger integrity
                Database::update(
                    'suppliers',
                    [
                        'is_active' => 0,
                        'status' => 'INACTIVE',
                        'deleted_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ? AND company_id = ?',
                    [$supplierId, $companyId]
                );
                $msg = "Supplier '{$existing['name']}' has historical purchase records and was archived.";
            } else {
                // Permanent deletion if never referenced
                Database::execute("DELETE FROM suppliers WHERE id = ? AND company_id = ?", [$supplierId, $companyId]);
                $msg = "Supplier '{$existing['name']}' deleted successfully.";
            }

            AuditLogger::log(
                action: 'delete',
                module: 'purchases',
                entityType: 'SUPPLIER',
                entityId: $supplierId,
                entityLabel: $existing['name'],
                oldValues: [
                    'name' => $existing['name'],
                    'gstin' => $existing['gstin'] ?? '',
                ],
                status: 'success',
                notes: $msg
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => $msg,
                    'supplier_id' => $supplierId,
                ]);
            }

            Session::flash('success', $msg);
            return Response::redirect('/suppliers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to delete supplier: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to delete supplier: ' . $e->getMessage());
            return Response::redirect('/suppliers');
        }
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
