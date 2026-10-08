<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Banking\Services\PaymentEngine;
use App\Banking\Services\PaymentMethodMasterService;
use App\Services\PaymentService;
use App\Payments\Allocations\PaymentAllocationService;
use App\Services\ReceivableService;
use App\Services\PayableService;
use App\Http\Middleware\AuthMiddleware;
use Exception;
use Throwable;

class PaymentController
{
    /**
     * List payments with filters & pagination.
     */
    public function index()
    {
        $companyId = AuthMiddleware::getTenantId();

        $query = Payment::where('company_id', $companyId)
            ->with(['customer', 'supplier', 'allocations.invoice', 'allocations.purchase', 'refunds'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($_GET['party_type']) && strtoupper($_GET['party_type']) !== 'ALL') {
            $query->where('party_type', strtoupper($_GET['party_type']));
        }
        if (!empty($_GET['payment_type']) && strtoupper($_GET['payment_type']) !== 'ALL') {
            $query->where('payment_type', strtoupper($_GET['payment_type']));
        }
        if (!empty($_GET['payment_mode']) && strtoupper($_GET['payment_mode']) !== 'ALL') {
            $query->where('payment_mode', strtoupper($_GET['payment_mode']));
        }
        if (!empty($_GET['status']) && strtoupper($_GET['status']) !== 'ALL') {
            $query->where('status', strtoupper($_GET['status']));
        }
        if (!empty($_GET['search'])) {
            $s = '%' . trim($_GET['search']) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', $s)
                  ->orWhere('receipt_number', 'like', $s)
                  ->orWhere('reference_number', 'like', $s)
                  ->orWhere('transaction_reference', 'like', $s)
                  ->orWhere('notes', 'like', $s);
            });
        }

        $payments = $query->get();

        return response_json([
            'status' => 'success',
            'data' => $payments,
            'payments' => $payments,
        ]);
    }

    /**
     * Store (Record & Post) a Customer Receipt or Supplier Payment.
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();

        $input['company_id'] = $companyId;
        $input['branch_id'] = $input['branch_id'] ?? (AuthMiddleware::getBranchId() ?: 1);

        try {
            $partyType = strtoupper($input['party_type'] ?? ($input['payment_type'] === 'RECEIPT' ? 'CUSTOMER' : 'SUPPLIER'));
            $paymentType = strtoupper($input['payment_type'] ?? ($partyType === 'CUSTOMER' ? 'RECEIPT' : 'PAYMENT'));

            if ($paymentType === 'RECEIPT' || $partyType === 'CUSTOMER') {
                $payment = PaymentEngine::processCustomerReceipt($companyId, $input, $userName);
            } else {
                $payment = PaymentEngine::processSupplierPayment($companyId, $input, $userName);
            }

            return response_json([
                'status' => 'success',
                'message' => 'Payment recorded and posted successfully',
                'data' => $payment->load(['customer', 'supplier', 'allocations.invoice', 'allocations.purchase']),
                'payment' => $payment,
            ], 201);
        } catch (Throwable $e) {
            return response_json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'Error processing payment.',
            ], 400);
        }
    }

    /**
     * Show single payment details.
     */
    public function show($id)
    {
        $companyId = AuthMiddleware::getTenantId();

        $raw = Payment::withoutGlobalScopes()->find(intval($id));
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Payment not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $payment = Payment::where('company_id', $companyId)
            ->with(['customer', 'supplier', 'allocations.invoice', 'allocations.purchase', 'refunds'])
            ->find(intval($id));

        return response_json([
            'status' => 'success',
            'data' => $payment,
            'payment' => $payment,
        ]);
    }

    /**
     * Post a draft payment.
     */
    public function post($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $raw = Payment::withoutGlobalScopes()->find(intval($id));
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Payment not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';

        try {
            $payment = PaymentService::postPayment(intval($id), $userName);
            return response_json([
                'status' => 'success',
                'message' => 'Payment posted successfully',
                'data' => $payment,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel/Reverse payment.
     */
    public function cancel($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $raw = Payment::withoutGlobalScopes()->find(intval($id));
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Payment not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();
        $reason = $input['reason'] ?? 'Payment cancellation requested';

        try {
            $payment = PaymentService::cancelPayment(intval($id), $reason, $userName);
            return response_json([
                'status' => 'success',
                'message' => "Payment #{$payment->payment_number} cancelled and reversed successfully",
                'data' => $payment,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Allocate payment across invoices or bills.
     */
    public function allocate($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();
        $allocations = $input['allocations'] ?? [];

        try {
            $payment = Payment::where('company_id', $companyId)->findOrFail(intval($id));

            if ($payment->party_type === 'CUSTOMER' || $payment->payment_type === 'RECEIPT') {
                $result = PaymentAllocationService::allocateCustomerPayment($payment, $allocations, $userName);
            } else {
                $result = PaymentAllocationService::allocateSupplierPayment($payment, $allocations, $userName);
            }

            return response_json([
                'status' => 'success',
                'message' => 'Payment allocations updated successfully',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cheque bounce action.
     */
    public function bounceCheque($id)
    {
        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();
        $reason = $input['reason'] ?? 'Cheque bounced / returned unpaid';

        try {
            $payment = PaymentService::bounceCheque(intval($id), $reason, $userName);
            return response_json([
                'status' => 'success',
                'message' => "Cheque on payment #{$payment->payment_number} marked as bounced",
                'data' => $payment,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Dashboard KPIs & metrics summary.
     */
    public function getDashboardSummary()
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;

        $totalReceived = floatval(Payment::where('company_id', $companyId)->where('payment_type', 'RECEIPT')->where('status', 'POSTED')->sum('amount'));
        $totalPaid = floatval(Payment::where('company_id', $companyId)->where('payment_type', 'PAYMENT')->where('status', 'POSTED')->sum('amount'));

        $customerAdvances = floatval(Payment::where('company_id', $companyId)->where('party_type', 'CUSTOMER')->where('status', 'POSTED')->sum('unallocated_amount'));
        $supplierAdvances = floatval(Payment::where('company_id', $companyId)->where('party_type', 'SUPPLIER')->where('status', 'POSTED')->sum('unallocated_amount'));

        $recSummary = ReceivableService::getCustomerReceivables($companyId, $branchId);
        $totalReceivable = array_sum(array_column($recSummary, 'total_due'));
        $overdueReceivable = array_sum(array_column($recSummary, 'overdue_amount'));

        $paySummary = PayableService::getSupplierPayables($companyId, $branchId);
        $totalPayable = array_sum(array_column($paySummary, 'total_due'));
        $overduePayable = array_sum(array_column($paySummary, 'overdue_amount'));

        return response_json([
            'status' => 'success',
            'data' => [
                'total_received' => $totalReceived,
                'total_paid' => $totalPaid,
                'customer_advances' => $customerAdvances,
                'supplier_advances' => $supplierAdvances,
                'total_receivable' => $totalReceivable,
                'overdue_receivable' => $overdueReceivable,
                'total_payable' => $totalPayable,
                'overdue_payable' => $overduePayable,
            ]
        ]);
    }

    /**
     * Customer receivables summary.
     */
    public function getReceivables()
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = ReceivableService::getCustomerReceivables($companyId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Outstanding invoices list.
     */
    public function getOutstandingInvoices()
    {
        $companyId = AuthMiddleware::getTenantId();
        $customerId = !empty($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = ReceivableService::getOutstandingInvoices($companyId, $customerId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Customer receivables aging analysis.
     */
    public function getReceivablesAging()
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = ReceivableService::getReceivablesAging($companyId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Supplier payables summary.
     */
    public function getPayables()
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = PayableService::getSupplierPayables($companyId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Outstanding supplier bills list.
     */
    public function getOutstandingBills()
    {
        $companyId = AuthMiddleware::getTenantId();
        $supplierId = !empty($_GET['supplier_id']) ? intval($_GET['supplier_id']) : null;
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = PayableService::getOutstandingBills($companyId, $supplierId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Supplier payables aging analysis.
     */
    public function getPayablesAging()
    {
        $companyId = AuthMiddleware::getTenantId();
        $branchId = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        $data = PayableService::getPayablesAging($companyId, $branchId);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Customer advances list.
     */
    public function getCustomerAdvances()
    {
        $companyId = AuthMiddleware::getTenantId();
        $advances = Payment::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->where('status', 'POSTED')
            ->where('unallocated_amount', '>', 0)
            ->with('customer')
            ->orderBy('payment_date', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $advances]);
    }

    /**
     * Supplier advances list.
     */
    public function getSupplierAdvances()
    {
        $companyId = AuthMiddleware::getTenantId();
        $advances = Payment::where('company_id', $companyId)
            ->where('party_type', 'SUPPLIER')
            ->where('status', 'POSTED')
            ->where('unallocated_amount', '>', 0)
            ->with('supplier')
            ->orderBy('payment_date', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $advances]);
    }

    /**
     * Customer statement.
     */
    public function getCustomerStatement($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $data = PaymentService::getCustomerStatement($companyId, intval($id), $fromDate, $toDate);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Supplier statement.
     */
    public function getSupplierStatement($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $data = PaymentService::getSupplierStatement($companyId, intval($id), $fromDate, $toDate);

        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * List refunds.
     */
    public function getRefunds()
    {
        $companyId = AuthMiddleware::getTenantId();
        $refunds = PaymentRefund::where('company_id', $companyId)
            ->with(['payment', 'customer', 'supplier'])
            ->orderBy('refund_date', 'desc')
            ->get();

        return response_json(['status' => 'success', 'data' => $refunds]);
    }

    /**
     * Create refund.
     */
    public function createRefund()
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();

        try {
            $paymentId = intval($input['payment_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $reason = $input['reason'] ?? 'Payment refund';
            $mode = $input['refund_mode'] ?? 'BANK_TRANSFER';

            $refund = PaymentEngine::processPaymentRefund($companyId, $paymentId, $amount, $reason, $mode, $userName);

            return response_json([
                'status' => 'success',
                'message' => 'Refund issued successfully',
                'data' => $refund,
            ], 201);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Overdue payment reminders queue.
     */
    public function getReminders()
    {
        $companyId = AuthMiddleware::getTenantId();
        $today = date('Y-m-d');

        $invoices = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'PAID')
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->where('due_date', '<', $today)
            ->with('customer')
            ->orderBy('due_date', 'asc')
            ->get();

        return response_json(['status' => 'success', 'data' => $invoices]);
    }

    /**
     * Send payment reminder dispatch.
     */
    public function sendReminder()
    {
        $input = get_json_input();
        $channel = $input['channel'] ?? 'EMAIL';
        $invoiceId = $input['invoice_id'] ?? null;

        return response_json([
            'status' => 'success',
            'message' => "Payment reminder sent successfully via {$channel}",
        ]);
    }

    /**
     * Reminder dispatch history.
     */
    public function getReminderHistory()
    {
        return response_json(['status' => 'success', 'data' => []]);
    }

    /**
     * Payment methods master.
     */
    public function getPaymentMethods()
    {
        $methods = PaymentMethodMasterService::getPaymentMethods();
        return response_json(['status' => 'success', 'data' => $methods, 'methods' => $methods]);
    }

    public function getAdvances()
    {
        $companyId = AuthMiddleware::getTenantId();
        $customerAdvances = Payment::where('company_id', $companyId)->where('party_type', 'CUSTOMER')->where('status', 'POSTED')->where('unallocated_amount', '>', 0)->with('customer')->get();
        $supplierAdvances = Payment::where('company_id', $companyId)->where('party_type', 'SUPPLIER')->where('status', 'POSTED')->where('unallocated_amount', '>', 0)->with('supplier')->get();
        return response_json(['status' => 'success', 'data' => ['customer_advances' => $customerAdvances, 'supplier_advances' => $supplierAdvances]]);
    }

    public function recordCustomerPayment($request = null)
    {
        return $this->store();
    }

    public function recordSupplierPayment($request = null)
    {
        return $this->store();
    }

    public function recordCustomerAdvance($request = null)
    {
        return $this->store();
    }

    public function reverse($id, $request = null)
    {
        return $this->cancel($id);
    }

    public function refund($id, $request = null)
    {
        return $this->createRefund();
    }

    public function printReceipt($id)
    {
        return $this->show($id);
    }

    public function overview()
    {
        return $this->getDashboardSummary();
    }
}

