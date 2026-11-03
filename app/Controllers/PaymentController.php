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
use WTSBill\Services\PaymentService;

/**
 * Payments & Receipts Controller.
 * Manages customer collection receipts, partial invoice payments, supplier disbursements, voucher printing, and audits.
 */
class PaymentController
{
    protected PaymentService $paymentService;

    public function __construct(?PaymentService $paymentService = null)
    {
        $this->paymentService = $paymentService ?? new PaymentService();
    }

    /**
     * Payments & Receipts master directory view.
     */
    public function index(Request $req): Response
    {
        Gate::authorize('payments.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $tab = strtolower(trim((string)$req->query('tab', 'vouchers')));
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
        $bankAccounts = Database::select("SELECT id, bank_name, account_name, account_number, current_balance FROM `bank_accounts` WHERE company_id = ? AND is_active = 1 ORDER BY is_primary DESC, id ASC", [$companyId]);

        // Invoice Payments & Partial Balances
        $filters = [
            'search' => $search,
            'customer_id' => $req->query('customer_id'),
            'from_date' => $req->query('from_date'),
            'to_date' => $req->query('to_date'),
            'status' => $req->query('payment_status', 'ALL'),
        ];
        $invoicePayments = $this->paymentService->getInvoicePaymentsList($companyId, $filters);

        // Calculate Invoice Receivables KPI Summary
        $invKpi = Database::selectOne(
            "SELECT 
                COALESCE(SUM(grand_total), 0) AS total_invoiced,
                COALESCE(SUM(amount_paid), 0) AS total_collected,
                COALESCE(SUM(amount_due), 0) AS total_outstanding,
                COUNT(CASE WHEN amount_due > 0.001 AND due_date < CURRENT_DATE THEN 1 END) AS overdue_count
             FROM `invoices` 
             WHERE company_id = ? AND deleted_at IS NULL AND status != 'CANCELLED'",
            [$companyId]
        );

        if ($req->expectsJson()) {
            return Response::success([
                'payments' => $payments,
                'summary' => $summary,
                'invoice_payments' => $invoicePayments,
                'invoice_kpi' => $invKpi,
            ]);
        }

        $html = View::renderWithLayout('payments/index', [
            'title' => 'Payments & Receipts',
            'currentUri' => '/payments',
            'payments' => $payments,
            'summary' => $summary,
            'activeType' => $type,
            'activeTab' => $tab,
            'search' => $search,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'bankAccounts' => $bankAccounts,
            'invoicePayments' => $invoicePayments,
            'invoiceKpi' => $invKpi,
            'filters' => $filters,
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

        // Check if this payment is directly allocated to an invoice
        $invoiceId = (int)($payload['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            try {
                $result = $this->paymentService->recordInvoicePayment(
                    $companyId,
                    (int)($branchId ?: 1),
                    (int)(Session::getUserId() ?: 1),
                    (string)Session::get('user_name', 'System'),
                    $invoiceId,
                    $payload
                );

                if ($req->expectsJson()) {
                    return Response::json($result, 201);
                }

                Session::flash('success', $result['message']);
                return Response::redirect('/payments?tab=invoices');
            } catch (ValidationException $e) {
                if ($req->expectsJson()) {
                    return Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
                }
                Session::flash('error', $e->getMessage());
                return Response::redirect('/payments?tab=invoices');
            } catch (\Throwable $e) {
                if ($req->expectsJson()) {
                    return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
                }
                Session::flash('error', 'Failed to record invoice payment: ' . $e->getMessage());
                return Response::redirect('/payments?tab=invoices');
            }
        }

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

        // Roll back invoice allocations if payment was linked to sales invoices
        $allocations = Database::select(
            "SELECT * FROM payment_allocations WHERE payment_id = ? AND company_id = ?",
            [$paymentId, $companyId]
        );
        foreach ($allocations as $alloc) {
            if ($alloc['document_type'] === 'INVOICE') {
                $inv = Database::selectOne("SELECT * FROM invoices WHERE id = ? AND company_id = ?", [$alloc['document_id'], $companyId]);
                if ($inv) {
                    $newPaid = max(0.00, round((float)$inv['amount_paid'] - (float)$alloc['allocated_amount'], 2));
                    $newDue = round((float)$inv['grand_total'] - $newPaid, 2);
                    $newStatus = ($newDue <= 0.001) ? 'PAID' : (($newPaid > 0.001) ? 'PARTIAL' : 'UNPAID');
                    $newPaymentStatus = ($newDue <= 0.001) ? 'PAID' : (($newPaid > 0.001) ? 'PARTIALLY_PAID' : 'UNPAID');

                    Database::update('invoices', [
                        'amount_paid' => $newPaid,
                        'amount_due' => $newDue,
                        'status' => $newStatus,
                        'payment_status' => $newPaymentStatus,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [$inv['id']]);

                    // Roll back customer balance
                    if (!empty($inv['customer_id'])) {
                        $cust = Database::selectOne("SELECT current_balance FROM customers WHERE id = ?", [$inv['customer_id']]);
                        if ($cust) {
                            $newCustBal = round((float)$cust['current_balance'] + (float)$alloc['allocated_amount'], 2);
                            Database::update('customers', ['current_balance' => $newCustBal], 'id = ?', [$inv['customer_id']]);
                        }
                    }
                }
            }
        }

        // Roll back bank balance if present
        if (!empty($payment['bank_account_id'])) {
            $ba = Database::selectOne("SELECT current_balance FROM bank_accounts WHERE id = ?", [$payment['bank_account_id']]);
            if ($ba) {
                $newBankBal = round((float)$ba['current_balance'] - (float)$payment['amount'], 2);
                Database::update('bank_accounts', ['current_balance' => $newBankBal], 'id = ?', [$payment['bank_account_id']]);
            }
        }

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

    /**
     * Verify a pending payment transaction.
     * Route: POST /payments/{id}/verify
     */
    public function verifyPayment(Request $req, mixed $id = null): Response
    {
        Gate::authorize('payments.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);
        $userName = (string)(Session::get('user_name') ?? 'Admin');
        $paymentId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('payment_id') ?? 0)));
        $externalRef = trim((string)$req->input('transaction_reference', ''));

        try {
            $result = $this->paymentService->verifyPayment($companyId, $paymentId, $userId, $userName, $externalRef ?: null);

            if ($req->expectsJson()) {
                return Response::json($result, 200);
            }

            Session::flash('success', 'Payment verified and confirmed successfully.');
            return Response::redirect('/payments');
        } catch (ValidationException $e) {
            if ($req->expectsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/payments');
        }
    }
}
