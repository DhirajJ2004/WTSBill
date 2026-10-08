<?php

namespace App\Controllers;

use App\Repositories\DeliveryChallanRepository;
use App\Services\DeliveryChallanService;
use App\Services\SalesConversionService;
use App\Middleware\AuthMiddleware;

class DeliveryChallanController
{
    /**
     * API: List Delivery Challans
     */
    public function apiList()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'customer_id' => $_GET['customer_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $res = DeliveryChallanRepository::getDeliveryChallans($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Delivery Challan Details
     */
    public function apiDetails(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $details = DeliveryChallanRepository::getDeliveryChallanWithItems($id, $companyId);

        header('Content-Type: application/json');
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Delivery Challan not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $details]);
        exit;
    }

    /**
     * API: Create Delivery Challan
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = DeliveryChallanService::createDeliveryChallan($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Dispatch Delivery Challan
     */
    public function dispatch(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = DeliveryChallanService::dispatchChallan($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Delete Delivery Challan
     */
    public function destroy(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = DeliveryChallanService::deleteDeliveryChallan($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Convert Delivery Challan to Invoice
     */
    public function convertToInvoice(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = SalesConversionService::convertChallanToInvoice($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
