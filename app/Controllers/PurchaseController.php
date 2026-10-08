<?php

namespace App\Controllers;

use App\Repositories\PurchaseRepository;
use App\Services\PurchaseService;
use App\Middleware\AuthMiddleware;

class PurchaseController
{
    /**
     * Web View: Purchases List
     */
    public function index()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $purchases = PurchaseRepository::getPurchases($companyId);
        $suppliers = \App\Repositories\PartyRepository::getSuppliers($companyId);
        $stats = PurchaseRepository::getPurchaseStats($companyId);

        include __DIR__ . '/../../views/purchases/purchases.php';
    }

    /**
     * API: List Purchases
     */
    public function apiList()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'supplier_id' => $_GET['supplier_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'payment_status' => $_GET['payment_status'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $res = PurchaseRepository::getPurchases($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Purchase Details
     */
    public function apiDetails(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $details = PurchaseRepository::getPurchaseWithItems($id, $companyId);

        header('Content-Type: application/json');
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Purchase Bill not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $details]);
        exit;
    }

    /**
     * API: Create Purchase Bill
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = PurchaseService::createPurchase($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Void Purchase Bill
     */
    public function void(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';
        $reason = $_POST['reason'] ?? 'Voided by authorized user';

        $res = PurchaseService::voidPurchase($id, $companyId, $userName, $reason);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Delete Draft Purchase Bill
     */
    public function destroy(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = PurchaseService::deletePurchase($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
