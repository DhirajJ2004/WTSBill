<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Services\PurchaseService;

/**
 * Purchase Controller.
 * Manages inward vendor bills, accounts payable, and supplier liability tracking.
 */
class PurchaseController
{
    protected PurchaseService $purchaseService;

    public function __construct(?PurchaseService $purchaseService = null)
    {
        $this->purchaseService = $purchaseService ?? new PurchaseService();
    }

    /**
     * Vendor purchase bills view with White + Blue theme.
     */
    public function billsIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;

        // Fetch suppliers for the dropdown
        $suppliers = Database::select(
            "SELECT id, name, company_name, gstin FROM suppliers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        // Fetch active bank accounts for the payment modal
        $bankAccounts = Database::select(
            "SELECT id, bank_name, account_name, account_number, current_balance 
             FROM bank_accounts 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY bank_name ASC",
            [$companyId]
        );

        // Fetch initial data using service
        $initialData = $this->purchaseService->getBills($companyId, $branchId, [
            'page' => (int)($req->get('page') ?? 1),
            'per_page' => (int)($req->get('per_page') ?? 25),
            'search' => (string)($req->get('search') ?? ''),
            'supplier_id' => (int)($req->get('supplier_id') ?? 0),
            'status' => (string)($req->get('status') ?? 'ALL'),
            'from' => (string)($req->get('from') ?? ''),
            'to' => (string)($req->get('to') ?? ''),
            'sort' => (string)($req->get('sort') ?? 'id'),
            'dir' => (string)($req->get('dir') ?? 'desc')
        ]);

        $html = View::renderWithLayout('purchases/bills', [
            'title' => 'Purchase Bills',
            'currentUri' => '/purchases/bills',
            'purchases' => $initialData['rows'],
            'kpis' => $initialData['kpis'],
            'totals' => $initialData['totals'],
            'meta' => $initialData['meta'],
            'suppliers' => $suppliers,
            'bankAccounts' => $bankAccounts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * API: GET /api/purchases/bills
     * Returns JSON list of bills, pagination metadata, totals, and updated KPIs.
     */
    public function billsApi(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;

        $filters = [
            'search' => (string)($req->get('search') ?? ''),
            'supplier_id' => (int)($req->get('supplier_id') ?? 0),
            'status' => (string)($req->get('status') ?? 'ALL'),
            'from' => (string)($req->get('from') ?? ''),
            'to' => (string)($req->get('to') ?? ''),
            'sort' => (string)($req->get('sort') ?? 'id'),
            'dir' => (string)($req->get('dir') ?? 'desc'),
            'page' => (int)($req->get('page') ?? 1),
            'per_page' => (int)($req->get('per_page') ?? 25),
        ];

        try {
            $data = $this->purchaseService->getBills($companyId, $branchId, $filters);
            return Response::json([
                'success' => true,
                'data' => $data['rows'],
                'kpis' => $data['kpis'],
                'totals' => $data['totals'],
                'meta' => $data['meta']
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to retrieve bills: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: GET /api/purchases/bills/{id}
     * Returns single bill full details with line items and payment history.
     */
    public function billDetailsApi(Request $req, mixed $idOrParams = null): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $billId = is_array($idOrParams) ? (int)($idOrParams['id'] ?? 0) : (int)$idOrParams;

        $bill = $this->purchaseService->getBillDetails($companyId, $billId);
        if (!$bill) {
            return Response::json(['success' => false, 'message' => 'Purchase bill not found.'], 404);
        }

        return Response::json(['success' => true, 'bill' => $bill]);
    }

    /**
     * API: POST /api/purchases/bills/{id}/payments
     * Record payment against a bill with GL journal entry and ledger updates.
     */
    public function recordPaymentApi(Request $req, mixed $idOrParams = null): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;
        $userId = (int)(Session::get('user_id') ?? 1);
        $userName = (string)Session::get('user_name', 'Administrator');
        $billId = is_array($idOrParams) ? (int)($idOrParams['id'] ?? 0) : (int)$idOrParams;

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->purchaseService->recordPayment($companyId, $branchId, $userId, $userName, $billId, $payload);
            return Response::json($result, 201);
        } catch (ValidationException $ve) {
            return Response::json(['success' => false, 'message' => $ve->getMessage(), 'errors' => $ve->getErrors()], 422);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'message' => 'Payment failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: DELETE /api/purchases/bills/{id}
     */
    public function billDeleteApi(Request $req, mixed $idOrParams = null): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $billId = is_array($idOrParams) ? (int)($idOrParams['id'] ?? 0) : (int)$idOrParams;

        try {
            $this->purchaseService->deleteBill($companyId, $billId);
            return Response::json(['success' => true, 'message' => 'Purchase bill deleted successfully.']);
        } catch (ValidationException $ve) {
            return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: POST /api/purchases/bills/{id}/duplicate
     */
    public function billDuplicateApi(Request $req, mixed $idOrParams = null): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;
        $billId = is_array($idOrParams) ? (int)($idOrParams['id'] ?? 0) : (int)$idOrParams;

        try {
            $newId = $this->purchaseService->duplicateBill($companyId, $branchId, $billId);
            return Response::json([
                'success' => true,
                'message' => 'Purchase bill duplicated successfully.',
                'new_bill_id' => $newId
            ], 201);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export bills to CSV or Excel.
     */
    public function billExport(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;

        $format = strtolower((string)($req->get('format') ?? 'csv'));
        $filters = [
            'search' => (string)($req->get('search') ?? ''),
            'supplier_id' => (int)($req->get('supplier_id') ?? 0),
            'status' => (string)($req->get('status') ?? 'ALL'),
            'from' => (string)($req->get('from') ?? ''),
            'to' => (string)($req->get('to') ?? ''),
            'sort' => (string)($req->get('sort') ?? 'id'),
            'dir' => (string)($req->get('dir') ?? 'desc'),
        ];

        $export = $this->purchaseService->exportBills($companyId, $branchId, $filters, $format);

        $response = new Response($export['content'], 200, [
            'Content-Type' => $export['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"'
        ]);

        return $response;
    }

    /**
     * Record new inward purchase bill (backward compatible with existing modal).
     */
    public function billStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = (int)(Session::getBranchId() ?? 1);
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $supplierId = (int)($payload['supplier_id'] ?? 0);
        $vendorInvoiceNo = trim((string)($payload['vendor_invoice_number'] ?? ''));
        $amount = (float)($payload['grand_total'] ?? ($payload['amount'] ?? 0));
        $billDate = trim((string)($payload['purchase_date'] ?? date('Y-m-d')));
        $dueDate = trim((string)($payload['due_date'] ?? date('Y-m-d', strtotime('+30 days'))));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($supplierId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a vendor/supplier.'], 422);
            }
            Session::flash('error', 'Please select a vendor/supplier.');
            return Response::redirect('/purchases/bills');
        }

        if ($amount <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Bill amount must be greater than zero.'], 422);
            }
            Session::flash('error', 'Bill amount must be greater than zero.');
            return Response::redirect('/purchases/bills');
        }

        $subTotal = round($amount / 1.18, 2);
        $totalTax = round($amount - $subTotal, 2);
        $cgst = round($totalTax / 2, 2);
        $sgst = round($totalTax / 2, 2);

        $purchaseNumber = 'PB-' . date('Y') . '-' . rand(1000, 9999);
        if (empty($vendorInvoiceNo)) {
            $vendorInvoiceNo = 'VND-' . rand(10000, 99999);
        }

        $supplier = Database::selectOne("SELECT name, gstin FROM suppliers WHERE id = ?", [$supplierId]);

        try {
            Database::transaction(function() use (
                $companyId, $branchId, $supplierId, $supplier, $purchaseNumber, $vendorInvoiceNo,
                $billDate, $dueDate, $subTotal, $cgst, $sgst, $totalTax, $amount, $notes
            ) {
                Database::insert('purchases', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier['name'] ?? 'Direct Vendor',
                    'supplier_gstin' => $supplier['gstin'] ?? null,
                    'purchase_number' => $purchaseNumber,
                    'vendor_invoice_number' => $vendorInvoiceNo,
                    'purchase_date' => $billDate,
                    'due_date' => $dueDate,
                    'sub_total' => $subTotal,
                    'cgst_amount' => $cgst,
                    'sgst_amount' => $sgst,
                    'igst_amount' => 0.0,
                    'total_tax' => $totalTax,
                    'round_off' => 0.0,
                    'grand_total' => $amount,
                    'amount_paid' => 0.0,
                    'amount_due' => $amount,
                    'status' => 'UNPAID',
                    'notes' => $notes ?: 'Procurement purchase bill',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Update supplier payable balance
                $supRow = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$supplierId]);
                $newBal = (float)($supRow['current_balance'] ?? 0) + $amount;
                Database::update(
                    'suppliers',
                    ['current_balance' => $newBal],
                    'id = ? AND company_id = ?',
                    [$supplierId, $companyId]
                );
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Purchase bill '{$purchaseNumber}' recorded successfully.",
                ], 201);
            }

            Session::flash('success', "Purchase bill '{$purchaseNumber}' recorded successfully.");
            return Response::redirect('/purchases/bills');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to record purchase bill: ' . $e->getMessage());
            return Response::redirect('/purchases/bills');
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
