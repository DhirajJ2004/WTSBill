<?php

namespace App\Controllers;

use App\Repositories\QuotationRepository;
use App\Services\QuotationService;
use App\Services\SalesConversionService;
use App\Middleware\AuthMiddleware;
use App\Http\Request;

class QuotationController
{
    /**
     * Web View: List Quotations
     */
    public function index()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $invoices = \App\Repositories\InvoiceRepository::getInvoices($companyId);
        $customers = \App\Repositories\PartyRepository::getCustomers($companyId);
        $quotes = QuotationRepository::getQuotations($companyId);
        $stats = QuotationRepository::getQuotationStats($companyId);

        include __DIR__ . '/../../views/sales/quotations.php';
    }

    /**
     * API: List Quotations
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

        $res = QuotationRepository::getQuotations($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Quotation Details
     */
    public function apiDetails(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $details = QuotationRepository::getQuotationWithItems($id, $companyId);

        header('Content-Type: application/json');
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Quotation not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $details]);
        exit;
    }

    /**
     * API / POST: Create Quotation
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = QuotationService::createQuotation($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Update Quotation
     */
    public function update(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = QuotationService::updateQuotation($id, $input, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Delete Quotation
     */
    public function destroy(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = QuotationService::deleteQuotation($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Convert Quotation to Sales Order
     */
    public function convertToSalesOrder(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = SalesConversionService::convertQuotationToSalesOrder($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Convert Quotation to Invoice
     */
    public function convertToInvoice(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = SalesConversionService::convertQuotationToInvoice($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
