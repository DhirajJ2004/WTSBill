<?php

namespace App\Controllers;

use App\Repositories\SalesOrderRepository;
use App\Services\SalesOrderService;
use App\Services\SalesConversionService;
use App\Middleware\AuthMiddleware;

class SalesOrderController
{
    /**
     * API: List Sales Orders
     */
    public function apiList()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'customer_id' => $_GET['customer_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'fulfillment_status' => $_GET['fulfillment_status'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $res = SalesOrderRepository::getSalesOrders($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Sales Order Details
     */
    public function apiDetails(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $details = SalesOrderRepository::getSalesOrderWithItems($id, $companyId);

        header('Content-Type: application/json');
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Sales Order not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $details]);
        exit;
    }

    /**
     * API: Create Sales Order
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = SalesOrderService::createSalesOrder($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Update Sales Order
     */
    public function update(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = SalesOrderService::updateSalesOrder($id, $input, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Delete Sales Order
     */
    public function destroy(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = SalesOrderService::deleteSalesOrder($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Convert Sales Order to Delivery Challan
     */
    public function convertToChallan(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';
        $dispatchStock = !empty($_POST['dispatch_stock']) || !empty($_GET['dispatch_stock']);

        $res = SalesConversionService::convertSalesOrderToChallan($id, $companyId, $userName, $dispatchStock);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Convert Sales Order to Invoice
     */
    public function convertToInvoice(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = SalesConversionService::convertSalesOrderToInvoice($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
