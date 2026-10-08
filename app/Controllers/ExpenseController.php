<?php

namespace App\Controllers;

use App\Repositories\ExpenseRepository;
use App\Services\ExpenseService;
use App\Middleware\AuthMiddleware;

class ExpenseController
{
    /**
     * API: List Expenses
     */
    public function apiList()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'category' => $_GET['category'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $res = ExpenseRepository::getExpenses($companyId, $filters);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    /**
     * API: Record Expense
     */
    public function store()
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $branchId = AuthMiddleware::getBranchId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $res = ExpenseService::recordExpense($input, $companyId, $branchId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    /**
     * API: Delete Expense
     */
    public function destroy(int $id)
    {
        $companyId = AuthMiddleware::getTenantId() ?: 1;
        $userName = AuthMiddleware::getUserName() ?: 'Admin';

        $res = ExpenseService::deleteExpense($id, $companyId, $userName);

        header('Content-Type: application/json');
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }
}
