<?php

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Services\PaymentService;
use App\Middleware\AuthMiddleware;

class PaymentController
{
    /**
     * Web View: Payments & Receipts
     */
    public function index()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $payments = PaymentRepository::getPayments($companyId);
        $customers = \App\Repositories\PartyRepository::getCustomers($companyId);
        $suppliers = \App\Repositories\PartyRepository::getSuppliers($companyId);
        $stats = PaymentRepository::getPaymentStats($companyId);

        include __DIR__ . '/../../views/payments/index.php';
    }

    /**
     * API: List Payments
     */
    public function apiList()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'payment_type' => $_GET['payment_type'] ?? '',
            'party_type' => $_GET['party_type'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $res = PaymentRepository::getPayments($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Payment / Receipt Details
     */
    public function apiDetails(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $details = PaymentRepository::getPaymentWithDetails($id, $companyId);

        header('Content-Type: application/json');
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment record not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $details]);
        exit;
    }

    /**
     * API: Record Customer Receipt
     */
    public function storeCustomerReceipt()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = PaymentService::recordCustomerReceipt($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Record Supplier Payment
     */
    public function storeSupplierPayment()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = PaymentService::recordSupplierPayment($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Void Payment
     */
    public function void(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';
        $reason = $_POST['reason'] ?? 'Voided by authorized user';

        $res = PaymentService::voidPayment($id, $companyId, $userName, $reason);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
