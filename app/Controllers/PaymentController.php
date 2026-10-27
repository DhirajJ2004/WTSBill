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

/**
 * Payments & Receipts Controller.
 * Manages customer collection receipts, supplier disbursements, voucher printing, and audits.
 */
class PaymentController
{
    /**
     * Payments & Receipts master directory view.
     */
    public function index(Request $req): Response
    {
        Gate::authorize('payments.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $type = strtoupper(trim((string)$req->query('type', 'ALL')));
        $search = trim((string)$req->query('search', ''));

        $where = ["company_id = ?", "deleted_at IS NULL"];
        $params = [$companyId];

        if ($type === 'RECEIPT' || $type === 'PAYMENT') {
            $where[] = "payment_type = ?";
            $params[] = $type;
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(payment_number LIKE ? OR receipt_number LIKE ? OR reference_no LIKE ? OR notes LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "SELECT id, payment_number, receipt_number, party_type, party_id, payment_date, amount, payment_mode, reference_no, payment_type, status, notes, created_at 
                FROM `payments` 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY payment_date DESC, id DESC LIMIT 200";

        $payments = Database::select($sql, $params);

        // Calculate KPI summary
        $summary = Database::selectOne(
            "SELECT 
                COALESCE(SUM(CASE WHEN payment_type = 'RECEIPT' THEN amount ELSE 0 END), 0) AS total_received,
                COALESCE(SUM(CASE WHEN payment_type = 'PAYMENT' THEN amount ELSE 0 END), 0) AS total_paid,
                COUNT(*) AS total_transactions
             FROM `payments` 
             WHERE company_id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        // Fetch parties for receipt / payment modal
        $customers = Database::select("SELECT id, name FROM `customers` WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);
        $suppliers = Database::select("SELECT id, name FROM `suppliers` WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        if ($req->expectsJson()) {
            return Response::success([
                'payments' => $payments,
                'summary' => $summary,
            ]);
        }

        $html = View::renderWithLayout('payments/index', [
            'title' => 'Payments & Receipts',
            'currentUri' => '/payments',
            'payments' => $payments,
            'summary' => $summary,
            'activeType' => $type,
            'search' => $search,
            'customers' => $customers,
            'suppliers' => $suppliers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new Payment or Receipt.
     */
    public function store(Request $req): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = Session::getBranchId();
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $paymentType = strtoupper(trim((string)($payload['payment_type'] ?? 'RECEIPT')));
        if (!in_array($paymentType, ['RECEIPT', 'PAYMENT'], true)) {
            $paymentType = 'RECEIPT';
        }

        $amount = (float)($payload['amount'] ?? 0);
        if ($amount <= 0) {
            if ($req->expectsJson()) {
                return Response::error('Valid payment amount greater than zero is required.', 422);
            }
            Session::flash('error', 'Valid payment amount greater than zero is required.');
            return Response::redirect('/payments');
        }

        $paymentDate = trim((string)($payload['payment_date'] ?? date('Y-m-d')));
        $paymentMode = trim((string)($payload['payment_mode'] ?? 'NEFT/RTGS'));
        $partyType = trim((string)($payload['party_type'] ?? ($paymentType === 'RECEIPT' ? 'CUSTOMER' : 'SUPPLIER')));
        $partyId = (int)($payload['party_id'] ?? 0);
        $referenceNo = trim((string)($payload['reference_no'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        // Generate serial voucher / receipt number
        $prefix = ($paymentType === 'RECEIPT') ? 'RCP' : 'PMT';
        $year = date('Y');
        $count = (int)(Database::selectOne("SELECT COUNT(*) AS total FROM `payments` WHERE company_id = ? AND payment_type = ?", [$companyId, $paymentType])['total'] ?? 0) + 1;
        $docNumber = sprintf('%s-%s-%04d', $prefix, $year, $count);

        try {
            $id = Database::insert('payments', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $docNumber,
                'receipt_number' => $docNumber,
                'party_type' => $partyType,
                'party_id' => $partyId ?: null,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'allocated_amount' => $amount,
                'unallocated_amount' => 0.00,
                'payment_mode' => $paymentMode,
                'reference_no' => $referenceNo,
                'reference_number' => $referenceNo,
                'payment_type' => $paymentType,
                'currency' => 'INR',
                'status' => 'COMPLETED',
                'notes' => $notes,
                'created_by' => (string)Session::get('user_name', 'System'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Audit Trail entry
            AuditLogger::log(
                action: 'create',
                module: 'payments',
                entityType: 'PAYMENT',
                entityId: $id,
                entityLabel: $docNumber,
                oldValues: null,
                newValues: [
                    'payment_number' => $docNumber,
                    'type' => $paymentType,
                    'amount' => $amount,
                    'mode' => $paymentMode,
                    'date' => $paymentDate
                ],
                status: 'success',
                notes: "Recorded {$paymentType} voucher {$docNumber} for ₹" . number_format($amount, 2)
            );

            $msg = ($paymentType === 'RECEIPT' ? 'Receipt' : 'Payment') . " '{$docNumber}' of ₹" . number_format($amount, 2) . " recorded successfully.";

            if ($req->expectsJson()) {
                return Response::success(['id' => $id, 'doc_number' => $docNumber], $msg);
            }

            Session::flash('success', $msg);
            return Response::redirect('/payments');
        } catch (\Throwable $e) {
            if ($req->expectsJson()) {
                return Response::error('Failed to record payment: ' . $e->getMessage(), 500);
            }
            Session::flash('error', 'Failed to record payment: ' . $e->getMessage());
            return Response::redirect('/payments');
        }
    }

    /**
     * Print official receipt voucher.
     */
    public function printReceipt(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.print', $req);

        $paymentId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $payment = Database::selectOne(
            "SELECT * FROM `payments` WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$paymentId, $companyId]
        );

        if (!$payment) {
            return Response::html("<h1>404 Not Found</h1><p>Payment voucher record not found.</p>", 404);
        }

        $company = Database::selectOne("SELECT * FROM `companies` WHERE id = ?", [$companyId]) ?? [];

        // Fetch party details
        $partyName = 'Direct Party';
        if (!empty($payment['party_id'])) {
            if ($payment['party_type'] === 'CUSTOMER') {
                $c = Database::selectOne("SELECT name, phone, gstin FROM `customers` WHERE id = ?", [$payment['party_id']]);
                if ($c) $partyName = $c['name'];
            } else {
                $s = Database::selectOne("SELECT name, phone, gstin FROM `suppliers` WHERE id = ?", [$payment['party_id']]);
                if ($s) $partyName = $s['name'];
            }
        }

        // Audit Trail entry
        AuditLogger::log(
            action: 'print',
            module: 'payments',
            entityType: 'PAYMENT',
            entityId: $paymentId,
            entityLabel: (string)$payment['payment_number'],
            notes: "Printed payment voucher {$payment['payment_number']}"
        );

        $html = View::render('payments/print', [
            'payment' => $payment,
            'company' => $company,
            'partyName' => $partyName,
        ]);

        return Response::html($html);
    }

    /**
     * Delete payment voucher.
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.delete', $req);

        $paymentId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $payment = Database::selectOne("SELECT * FROM `payments` WHERE id = ? AND company_id = ?", [$paymentId, $companyId]);
        if (!$payment) {
            return Response::error('Payment record not found.', 404);
        }

        Database::update('payments', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'CANCELLED',
        ], 'id = ?', [$paymentId]);

        AuditLogger::log(
            action: 'delete',
            module: 'payments',
            entityType: 'PAYMENT',
            entityId: $paymentId,
            entityLabel: (string)$payment['payment_number'],
            oldValues: $payment,
            newValues: ['status' => 'CANCELLED'],
            status: 'success',
            notes: "Cancelled and deleted payment voucher {$payment['payment_number']}"
        );

        if ($req->expectsJson()) {
            return Response::success(null, 'Payment voucher cancelled successfully.');
        }

        Session::flash('success', 'Payment voucher cancelled successfully.');
        return Response::redirect('/payments');
    }
}
