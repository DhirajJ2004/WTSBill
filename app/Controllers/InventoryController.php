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
        Gate::authorize('categories.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
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
        Gate::authorize('categories.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
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
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        }

        $existing = Database::selectOne(
            "SELECT id FROM categories WHERE company_id = ? AND (name = ? OR code = ?)",
            [$companyId, $name, $code]
        );
        if ($existing) {
            $err = ['name' => 'A category with this name or code already exists.'];
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'A category with this name or code already exists.', 'errors' => $err], 422);
            }
            Session::flash('error', 'A category with this name or code already exists.');
            return Response::redirect('/categories');
        }

        try {
            $id = Database::insert('categories', [
                'company_id' => $companyId,
                'name' => $name,
                'code' => $code,
                'description' => $desc,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            AuditLogger::log(
                action: 'create',
                module: 'inventory',
                entityType: 'CATEGORY',
                entityId: $id,
                entityLabel: $name,
                newValues: ['name' => $name, 'code' => $code],
                status: 'success',
                notes: "Created product category '{$name}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Category '{$name}' created successfully.",
                    'category' => ['id' => $id, 'name' => $name, 'code' => $code],
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
     * Retrieve category details and its linked products (for Manage / Edit modal).
     * Route: GET /categories/{id}
     */
    public function categoryShow(Request $req, mixed $id = null): Response
    {
        Gate::authorize('categories.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $categoryId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $category = Database::selectOne(
            "SELECT c.*, COUNT(p.id) as product_count 
             FROM categories c 
             LEFT JOIN products p ON p.category_id = c.id AND p.company_id = c.company_id AND p.deleted_at IS NULL 
             WHERE c.id = ? AND c.company_id = ? 
             GROUP BY c.id",
            [$categoryId, $companyId]
        );

        if (!$category) {
            return Response::json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $products = Database::select(
            "SELECT id, name, sku, hsn_sac, COALESCE(selling_price, sales_price, 0) as price, current_stock, unit, is_active 
             FROM products 
             WHERE category_id = ? AND company_id = ? AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$categoryId, $companyId]
        );

        return Response::json([
            'success' => true,
            'category' => $category,
            'products' => $products,
            'count' => count($products),
        ]);
    }

    /**
     * Update existing category.
     * Route: POST /categories/{id}/update
     */
    public function categoryUpdate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('categories.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $categoryId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM categories WHERE id = ? AND company_id = ?",
            [$categoryId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Category not found.'], 404);
            }
            Session::flash('error', 'Category not found.');
            return Response::redirect('/categories');
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $name = trim((string)($payload['name'] ?? ''));
        $code = strtoupper(trim((string)($payload['code'] ?? '')));
        $desc = trim((string)($payload['description'] ?? ''));
        $status = strtoupper(trim((string)($payload['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }

        if (empty($name)) {
            $err = ['name' => 'Category name is required.'];
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Category name is required.', 'errors' => $err], 422);
            }
            Session::flash('error', 'Category name is required.');
            return Response::redirect('/categories');
        }

        if (empty($code)) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        }

        // Check uniqueness excluding current category
        $duplicate = Database::selectOne(
            "SELECT id FROM categories WHERE company_id = ? AND (name = ? OR code = ?) AND id != ?",
            [$companyId, $name, $code, $categoryId]
        );
        if ($duplicate) {
            $err = ['name' => 'Another category with this name or code already exists.'];
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Another category with this name or code already exists.', 'errors' => $err], 422);
            }
            Session::flash('error', 'Another category with this name or code already exists.');
            return Response::redirect('/categories');
        }

        try {
            Database::update(
                'categories',
                [
                    'name' => $name,
                    'code' => $code,
                    'description' => $desc,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$categoryId, $companyId]
            );

            AuditLogger::log(
                action: 'update',
                module: 'inventory',
                entityType: 'CATEGORY',
                entityId: $categoryId,
                entityLabel: $name,
                oldValues: [
                    'name' => $existing['name'],
                    'code' => $existing['code'],
                    'description' => $existing['description'],
                    'status' => $existing['status'],
                ],
                newValues: [
                    'name' => $name,
                    'code' => $code,
                    'description' => $desc,
                    'status' => $status,
                ],
                status: 'success',
                notes: "Updated product category '{$name}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Category '{$name}' updated successfully.",
                    'category' => ['id' => $categoryId, 'name' => $name, 'code' => $code, 'status' => $status],
                ]);
            }

            Session::flash('success', "Category '{$name}' updated successfully.");
            return Response::redirect('/categories');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to update category: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to update category: ' . $e->getMessage());
            return Response::redirect('/categories');
        }
    }

    /**
     * Delete category with linked product safeguard.
     * Route: POST /categories/{id}/delete, DELETE /categories/{id}
     */
    public function categoryDelete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('categories.delete', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $categoryId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM categories WHERE id = ? AND company_id = ?",
            [$categoryId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Category not found.'], 404);
            }
            Session::flash('error', 'Category not found.');
            return Response::redirect('/categories');
        }

        // Safety check: Cannot delete if products are linked
        $linkedCountRow = Database::selectOne(
            "SELECT COUNT(*) as cnt FROM products WHERE category_id = ? AND company_id = ? AND deleted_at IS NULL",
            [$categoryId, $companyId]
        );
        $linkedCount = (int)($linkedCountRow['cnt'] ?? 0);

        if ($linkedCount > 0) {
            $msg = "Cannot delete category '{$existing['name']}' because it has {$linkedCount} linked product(s). Please reassign or remove the products first, or mark the category as Inactive.";
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $msg,
                    'linked_products_count' => $linkedCount,
                ], 422);
            }
            Session::flash('error', $msg);
            return Response::redirect('/categories');
        }

        try {
            Database::execute("DELETE FROM categories WHERE id = ? AND company_id = ?", [$categoryId, $companyId]);

            AuditLogger::log(
                action: 'delete',
                module: 'inventory',
                entityType: 'CATEGORY',
                entityId: $categoryId,
                entityLabel: $existing['name'],
                oldValues: [
                    'name' => $existing['name'],
                    'code' => $existing['code'],
                ],
                status: 'success',
                notes: "Deleted product category '{$existing['name']}'"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Category '{$existing['name']}' deleted successfully.",
                ]);
            }

            Session::flash('success', "Category '{$existing['name']}' deleted successfully.");
            return Response::redirect('/categories');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to delete category: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to delete category: ' . $e->getMessage());
            return Response::redirect('/categories');
        }
    }

    /**
     * Toggle category active/inactive status.
     * Route: POST /categories/{id}/toggle-status
     */
    public function categoryToggleStatus(Request $req, mixed $id = null): Response
    {
        Gate::authorize('categories.edit', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $categoryId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->query('id') ?? 0)));

        $existing = Database::selectOne(
            "SELECT * FROM categories WHERE id = ? AND company_id = ?",
            [$categoryId, $companyId]
        );

        if (!$existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Category not found.'], 404);
            }
            Session::flash('error', 'Category not found.');
            return Response::redirect('/categories');
        }

        $newStatus = ($existing['status'] === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';

        try {
            Database::update(
                'categories',
                ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ? AND company_id = ?',
                [$categoryId, $companyId]
            );

            AuditLogger::log(
                action: 'toggle_status',
                module: 'inventory',
                entityType: 'CATEGORY',
                entityId: $categoryId,
                entityLabel: $existing['name'],
                oldValues: ['status' => $existing['status']],
                newValues: ['status' => $newStatus],
                status: 'success',
                notes: "Toggled status of category '{$existing['name']}' to {$newStatus}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Category '{$existing['name']}' status changed to {$newStatus}.",
                    'status' => $newStatus,
                    'category' => [
                        'id' => $categoryId,
                        'name' => $existing['name'],
                        'status' => $newStatus,
                    ],
                ]);
            }

            Session::flash('success', "Category '{$existing['name']}' status changed to {$newStatus}.");
            return Response::redirect('/categories');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to toggle category status: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to toggle category status: ' . $e->getMessage());
            return Response::redirect('/categories');
        }
    }

    /**
     * Stock balances and adjustments view.
     */
    public function stockIndex(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
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
             WHERE company_id = ? AND deleted_at IS NULL 
             ORDER BY name ASC",
            [$companyId]
        );

        $company = Database::selectOne("SELECT allow_negative_stock FROM companies WHERE id = ?", [$companyId]);
        $allowNegativeStock = (bool)($company['allow_negative_stock'] ?? false);

        $initialBalances = $this->inventoryService->getStockBalances($companyId, ['per_page' => 25]);
        $initialHistory = $this->inventoryService->getAdjustmentHistory($companyId, ['per_page' => 25]);
        $initialLedger = $this->inventoryService->getStockLedger($companyId, ['per_page' => 25]);

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
     * Record physical stock adjustment.
     */
    public function stockAdjust(Request $req): Response
    {
        Gate::authorize('inventory.adjustment.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userId = $user ? (int)$user['id'] : (int)(Session::getUserId() ?? 1);
        $userName = $user ? (string)$user['name'] : (string)(Session::get('user_name') ?? 'Inventory Manager');
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->inventoryService->recordAdjustment($companyId, $userId, $userName, $payload);

            AuditLogger::log(
                action: 'create',
                module: 'inventory',
                entityType: 'STOCK_ADJUSTMENT',
                entityId: (int)($result['adjustment']['id'] ?? 0),
                entityLabel: (string)($result['adjustment']['adjustment_number'] ?? ''),
                newValues: $payload,
                status: 'success',
                notes: "Recorded stock adjustment for product #{$payload['product_id']}"
            );

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => $result['message'] ?? 'Stock adjustment recorded successfully.',
                    'adjustment' => $result['adjustment'] ?? null,
                ], 200);
            }

            Session::flash('success', $result['message'] ?? 'Stock adjustment recorded successfully.');
            return Response::redirect('/inventory/stock?tab=history');
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
     * Update existing stock adjustment (e.g. reason / notes).
     */
    public function adjustmentUpdate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.adjustment.edit', $req);

        $adjId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userName = $user ? (string)$user['name'] : 'Inventory Manager';
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->inventoryService->updateAdjustment($companyId, $adjId, $payload, $userName);

            AuditLogger::log(
                action: 'update',
                module: 'inventory',
                entityType: 'STOCK_ADJUSTMENT',
                entityId: $adjId,
                entityLabel: '#' . $adjId,
                newValues: $payload,
                status: 'success',
                notes: "Updated stock adjustment #{$adjId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json($result);
            }

            Session::flash('success', $result['message']);
            return Response::redirect('/inventory/stock?tab=history');
        } catch (ValidationException $ve) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $ve->getMessage()], 422);
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect('/inventory/stock?tab=history');
        }
    }

    /**
     * Delete / rollback physical stock adjustment.
     */
    public function adjustmentDelete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('inventory.adjustment.delete', $req);

        $adjId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->input('id') ?? 0)));
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userName = $user ? (string)$user['name'] : 'Inventory Manager';

        try {
            $result = $this->inventoryService->deleteAdjustment($companyId, $adjId, $userName);

            AuditLogger::log(
                action: 'delete',
                module: 'inventory',
                entityType: 'STOCK_ADJUSTMENT',
                entityId: $adjId,
                entityLabel: '#' . $adjId,
                status: 'success',
                notes: "Deleted and reversed stock adjustment #{$adjId}"
            );

            if ($this->expectsJson($req)) {
                return Response::json($result);
            }

            Session::flash('success', $result['message']);
            return Response::redirect('/inventory/stock?tab=history');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/inventory/stock?tab=history');
        }
    }

    /**
     * Stock balances, history, and ledger API endpoint.
     * Route: GET /api/inventory/stock
     */
    public function stockApi(Request $req): Response
    {
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
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
     * Dedicated REST API for recording stock adjustments with Gate authorization.
     * Route: POST /api/inventory/adjustments
     */
    public function stockAdjustmentApi(Request $req): Response
    {
        Gate::authorize('inventory.adjustment.create', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Gate::user();
        $userId = $user ? (int)$user['id'] : (int)(Session::getUserId() ?? 1);
        $userName = $user ? (string)$user['name'] : 'Inventory Manager';

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $result = $this->inventoryService->recordAdjustment($companyId, $userId, $userName, $payload);

            AuditLogger::log(
                action: 'create',
                module: 'inventory',
                entityType: 'STOCK_ADJUSTMENT',
                entityId: (int)($result['adjustment']['id'] ?? 0),
                entityLabel: (string)($result['adjustment']['adjustment_number'] ?? ''),
                newValues: $payload,
                status: 'success',
                notes: "API stock adjustment recorded"
            );

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
        Gate::authorize('inventory.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
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
