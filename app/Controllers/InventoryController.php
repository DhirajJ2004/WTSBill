<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\InventoryService;

/**
 * Inventory Controller.
 * Handles category taxonomy, category creation, stock balances, and physical stock adjustments.
 */
class InventoryController
{
    protected InventoryService $inventoryService;

    public function __construct(?InventoryService $inventoryService = null)
    {
        $this->inventoryService = $inventoryService ?? new InventoryService();
    }
    /**
     * Categories list view.
     */
    public function categoriesIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $categories = Database::select(
            "SELECT c.id, c.name, c.code, c.description, c.status, COUNT(p.id) as product_count 
             FROM categories c 
             LEFT JOIN products p ON p.category_id = c.id AND p.company_id = c.company_id 
             WHERE c.company_id = ? 
             GROUP BY c.id 
             ORDER BY c.id ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('inventory/categories', [
            'title' => 'Product Categories',
            'currentUri' => '/categories',
            'categories' => $categories,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new category.
     */
    public function categoryStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $name = trim((string)($payload['name'] ?? ''));
        $code = strtoupper(trim((string)($payload['code'] ?? '')));
        $desc = trim((string)($payload['description'] ?? ''));

        if (empty($name)) {
            $err = ['name' => 'Category name is required.'];
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Category name is required.', 'errors' => $err], 422);
            }
            Session::flash('error', 'Category name is required.');
            return Response::redirect('/categories');
        }

        if (empty($code)) {
            $code = 'CAT-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4)) . '-' . rand(10, 99);
        }

        try {
            $catId = Database::insert('categories', [
                'company_id' => $companyId,
                'name' => $name,
                'code' => $code,
                'description' => $desc ?: null,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $cat = Database::selectOne("SELECT * FROM categories WHERE id = ?", [$catId]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Category '{$name}' created successfully.",
                    'category' => $cat,
                ], 201);
            }

            Session::flash('success', "Category '{$name}' created successfully.");
            return Response::redirect('/categories');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to save category: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to save category: ' . $e->getMessage());
            return Response::redirect('/categories');
        }
    }

    /**
     * Stock balances and adjustments view.
     */
    public function stockIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $tab = strtolower(trim((string)$req->get('tab', 'balances')));
        if (!in_array($tab, ['balances', 'history', 'ledger'], true)) {
            $tab = 'balances';
        }

        $categories = Database::select(
            "SELECT id, name, code FROM categories WHERE company_id = ? ORDER BY name ASC",
            [$companyId]
        );

        $warehouses = Database::select(
            "SELECT id, name, code FROM warehouses WHERE company_id = ? ORDER BY id ASC",
            [$companyId]
        );
        if (empty($warehouses)) {
            $warehouses = Database::select("SELECT id, name, code FROM warehouses ORDER BY id ASC");
        }

        $products = Database::select(
            "SELECT id, name, sku, COALESCE(sales_price, 0) as price,
                    COALESCE(purchase_price, 0) as cost_price,
                    current_stock, reorder_level, max_level
             FROM products 
             WHERE company_id = ? 
             ORDER BY name ASC",
            [$companyId]
        );

        $initialBalances = $this->inventoryService->getStockBalances($companyId, ['page' => 1, 'per_page' => 25]);
        $initialHistory = $this->inventoryService->getAdjustmentHistory($companyId, ['page' => 1, 'per_page' => 25]);
        $initialLedger = $this->inventoryService->getStockLedger($companyId, ['page' => 1, 'per_page' => 25]);

        // Check settings for negative stock allowance
        $allowNegativeStock = false;
        try {
            $settingRow = Database::selectOne("SELECT `value` FROM `settings` WHERE `key` = 'allow_negative_stock' AND (company_id = ? OR company_id IS NULL) LIMIT 1", [$companyId]);
            if ($settingRow) {
                $allowNegativeStock = in_array(strtolower((string)$settingRow['value']), ['1', 'true', 'yes', 'on'], true);
            }
        } catch (\Throwable $e) {
            $allowNegativeStock = false;
        }

        $html = View::renderWithLayout('inventory/stock', [
            'title' => 'Stock & Warehouse Balances',
            'currentUri' => '/inventory/stock',
            'tab' => $tab,
            'initialBalances' => $initialBalances,
            'initialHistory' => $initialHistory,
            'initialLedger' => $initialLedger,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'products' => $products,
            'allowNegativeStock' => $allowNegativeStock,
            'stockBalances' => $initialBalances['rows'] ?? $initialBalances['data'] ?? [],
            'totalValuation' => $initialBalances['totals']['total_valuation'] ?? 0.0,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Record physical stock adjustment (Supports both web forms and backward compatibility).
     */
    public function stockAdjust(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $userId = Session::getUserId() ?? (int)(Session::getUser()['id'] ?? 1);
        $userName = (string)(Session::getUser()['name'] ?? 'Admin User');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->inventoryService->recordAdjustment($companyId, $userId, $userName, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => $result['message'] ?? 'Stock adjustment recorded successfully.',
                    'adjustment' => $result['adjustment'] ?? null,
                ], 200);
            }

            Session::flash('success', $result['message'] ?? 'Stock adjustment recorded successfully.');
            return Response::redirect('/inventory/stock');
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $ve->getMessage(),
                    'errors' => $ve->getErrors(),
                ], 422);
            }

            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/stock');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to record stock adjustment: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to record stock adjustment: ' . $e->getMessage());
            return Response::redirect('/inventory/stock');
        }
    }

    /**
     * Stock balances, history, and ledger API endpoint.
     * Route: GET /api/inventory/stock
     */
    public function stockApi(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $tab = strtolower(trim((string)$req->get('tab', 'balances')));

        $filters = [
            'search' => (string)$req->get('search', ''),
            'warehouse_id' => (int)$req->get('warehouse_id', 0),
            'category_id' => (int)$req->get('category_id', 0),
            'status' => (string)$req->get('status', 'all'),
            'type' => (string)$req->get('type', ''),
            'product_id' => (int)$req->get('product_id', 0),
            'from_date' => (string)$req->get('from_date', ''),
            'to_date' => (string)$req->get('to_date', ''),
            'sort' => (string)$req->get('sort', ''),
            'dir' => (string)$req->get('dir', 'asc'),
            'page' => (int)$req->get('page', 1),
            'per_page' => (int)$req->get('per_page', 25),
        ];

        try {
            if ($tab === 'history') {
                $data = $this->inventoryService->getAdjustmentHistory($companyId, $filters);
            } elseif ($tab === 'ledger') {
                $data = $this->inventoryService->getStockLedger($companyId, $filters);
            } else {
                $data = $this->inventoryService->getStockBalances($companyId, $filters);
            }

            return Response::json($data);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to load stock data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dedicated REST API for recording stock adjustments with role verification.
     * Route: POST /api/inventory/adjustments
     */
    public function stockAdjustmentApi(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $userId = Session::getUserId() ?? (int)(Session::getUser()['id'] ?? 1);
        $userName = (string)(Session::getUser()['name'] ?? 'Admin User');
        $role = strtoupper((string)(Session::get('user_role') ?? (Session::getUser()['role'] ?? 'ADMIN')));

        $allowedRoles = ['ADMIN', 'SUPERADMIN', 'OWNER', 'INVENTORY MANAGER', 'INVENTORY_MANAGER', 'MANAGER'];
        if (!in_array($role, $allowedRoles, true)) {
            return Response::json([
                'success' => false,
                'message' => 'Permission denied: Only Admin and Inventory Managers can record stock adjustments.',
            ], 403);
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->inventoryService->recordAdjustment($companyId, $userId, $userName, $payload);
            return Response::json($result, 201);
        } catch (ValidationException $ve) {
            return Response::json([
                'success' => false,
                'message' => $ve->getMessage(),
                'errors' => $ve->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to record adjustment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export stock data in CSV, Excel XML, or PDF layout.
     * Route: GET /inventory/stock/export
     */
    public function stockExport(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $format = strtolower(trim((string)$req->get('format', 'csv')));

        $filters = [
            'search' => (string)$req->get('search', ''),
            'warehouse_id' => (int)$req->get('warehouse_id', 0),
            'category_id' => (int)$req->get('category_id', 0),
            'status' => (string)$req->get('status', 'all'),
            'sort' => (string)$req->get('sort', ''),
            'dir' => (string)$req->get('dir', 'asc'),
        ];

        return $this->inventoryService->exportStock($companyId, $filters, $format);
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
