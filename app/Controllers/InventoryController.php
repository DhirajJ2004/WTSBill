<?php

namespace App\Controllers;

use App\Services\InventoryService;
use App\Repositories\InventoryRepository;
use App\Validators\InventoryValidator;
use App\Middleware\AuthMiddleware;
use App\Http\Request;
use App\Http\JsonResponse;

class InventoryController
{
    /**
     * Render the Inventory Management Page.
     */
    public function index(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            header('Location: /login');
            exit();
        }

        $page = max(1, (int)($request->get('page', 1)));
        $perPage = max(1, min(100, (int)($request->get('per_page', 25))));
        $filters = [
            'search' => $request->get('search', ''),
            'category_id' => $request->get('category_id', ''),
            'product_type' => $request->get('product_type', ''),
            'low_stock' => $request->get('low_stock', ''),
            'is_active' => $request->get('is_active', ''),
            'page' => $page,
            'per_page' => $perPage,
        ];

        $products = InventoryRepository::getProducts($companyId, $filters, $page, $perPage);
        $categories = InventoryRepository::getCategories($companyId);
        $units = InventoryRepository::getUnits($companyId);
        $warehouses = InventoryRepository::getWarehouses($companyId);
        $summary = InventoryRepository::getStockValuationSummary($companyId);
        $lowStockItems = InventoryRepository::getLowStockProducts($companyId, 10);

        require_once __DIR__ . '/../../views/inventory/index.php';
    }

    /**
     * API: Get Paginated Products.
     */
    public function apiList(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $page = max(1, (int)($request->get('page', 1)));
        $perPage = max(1, min(100, (int)($request->get('per_page', 25))));
        $filters = [
            'search' => $request->get('search', ''),
            'category_id' => $request->get('category_id', ''),
            'low_stock' => $request->get('low_stock', ''),
            'page' => $page,
            'per_page' => $perPage,
        ];

        $data = InventoryRepository::getProducts($companyId, $filters, $page, $perPage);
        return new JsonResponse(['success' => true, 'data' => $data], 200);
    }

    /**
     * API: Get Product Details & Stock Ledger.
     */
    public function apiDetails(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $product = InventoryRepository::findProduct($id, $companyId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => "Product #{$id} not found."], 404);
        }

        $balances = InventoryRepository::getProductWarehouseBalances($id, $companyId);
        $ledger = InventoryRepository::getStockLedger($id, $companyId);

        return new JsonResponse([
            'success' => true,
            'product' => $product,
            'balances' => $balances,
            'ledger' => $ledger,
        ], 200);
    }

    /**
     * Store a new Product.
     */
    public function store(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $request->all();
        $userName = $_SESSION['user_name'] ?? 'Admin';

        $result = InventoryService::createProduct($input, $companyId, $userName);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 201);
    }

    /**
     * Update an existing Product.
     */
    public function update(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $request->all();
        $userName = $_SESSION['user_name'] ?? 'Admin';

        $result = InventoryService::updateProduct($id, $input, $companyId, $userName);

        if (!$result['success']) {
            $status = isset($result['errors']) ? 422 : 404;
            return new JsonResponse($result, $status);
        }

        return new JsonResponse($result, 200);
    }

    /**
     * Delete a Product.
     */
    public function destroy(Request $request, int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $userName = $_SESSION['user_name'] ?? 'Admin';
        $result = InventoryService::deleteProduct($id, $companyId, $userName);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 200);
    }

    /**
     * API: Adjust Stock.
     */
    public function adjust(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $request->all();
        $whId = (int)($input['warehouse_id'] ?? 0);
        $prodId = (int)($input['product_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);
        $type = $input['adjustment_type'] ?? 'ADD';
        $reason = $input['reason'] ?? '';
        $userName = $_SESSION['user_name'] ?? 'Admin';

        $result = InventoryService::adjustStock($companyId, $whId, $prodId, $qty, $type, $reason, $userName);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 200);
    }

    /**
     * API: Transfer Stock between Warehouses.
     */
    public function transfer(Request $request)
    {
        $companyId = AuthMiddleware::getTenantId();
        if (!$companyId) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $request->all();
        $fromWh = (int)($input['source_warehouse_id'] ?? 0);
        $toWh = (int)($input['destination_warehouse_id'] ?? 0);
        $prodId = (int)($input['product_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);
        $notes = $input['notes'] ?? '';
        $userName = $_SESSION['user_name'] ?? 'Admin';

        $result = InventoryService::transferStock($companyId, $fromWh, $toWh, $prodId, $qty, $notes, $userName);

        if (!$result['success']) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse($result, 200);
    }
}
