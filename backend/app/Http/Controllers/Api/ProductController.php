<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Http\Middleware\AuthMiddleware;
use App\Services\InventoryService;
use App\Services\AuditLogService;

class ProductController
{
    /**
     * Display paginated, searchable, filtered list of products.
     */
    public function index()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');

        $query = Product::with(['prices', 'stockBalances']);

        // 1. Search Query
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('hsn_sac', 'like', "%{$search}%");
            });
        }

        // 2. Category Filter
        if (!empty($_GET['category_id'])) {
            $query->where('category_id', intval($_GET['category_id']));
        }

        // 3. Status Filter
        if (isset($_GET['status'])) {
            $statusStr = $_GET['status'];
            if ($statusStr === 'active') {
                $query->where('is_active', true);
            } elseif ($statusStr === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // 4. Stock Status Filter
        if (!empty($_GET['stock_status'])) {
            $stockStatus = $_GET['stock_status'];
            if ($stockStatus === 'out_of_stock') {
                $query->where('current_stock', 0);
            } elseif ($stockStatus === 'low_stock') {
                $query->whereRaw('current_stock > 0 AND current_stock <= min_stock_alert');
            } elseif ($stockStatus === 'in_stock') {
                $query->whereRaw('current_stock > min_stock_alert');
            }
        }

        // 5. Sorting
        $sortBy = $_GET['sort_by'] ?? 'name';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'sku', 'sales_price', 'current_stock', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('name', 'asc');
        }

        // 6. Pagination
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 50)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $total = $query->count();
        $products = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response_json([
            'status' => 'success',
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'data' => $products,
        ]);
    }

    /**
     * Show single product details + price lists + warehouse breakdown.
     */
    public function show($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Product::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Product not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $product = Product::with(['prices', 'stockBalances', 'batches'])->find($id);

        return response_json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    /**
     * Create product + opening stock.
     */
    public function store()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            response_json(['status' => 'error', 'message' => 'Product Name is required.'], 422);
        }

        $sku = trim($input['sku'] ?? 'SKU-' . rand(10000, 99999));
        if (Product::where('sku', $sku)->exists()) {
            response_json(['status' => 'error', 'message' => "SKU '{$sku}' already exists."], 422);
        }

        $product = Product::create([
            'company_id' => $companyId,
            'category_id' => !empty($input['category_id']) ? intval($input['category_id']) : null,
            'subcategory_id' => !empty($input['subcategory_id']) ? intval($input['subcategory_id']) : null,
            'product_type' => $input['product_type'] ?? 'Goods',
            'name' => $name,
            'sku' => $sku,
            'barcode' => $input['barcode'] ?? 'BAR-' . $sku,
            'hsn_sac' => $input['hsn_sac'] ?? '84818030',
            'unit' => $input['unit'] ?? 'Pcs',
            'secondary_unit' => $input['secondary_unit'] ?? null,
            'conversion_ratio' => floatval($input['conversion_ratio'] ?? 1.0),
            'sales_price' => floatval($input['sales_price'] ?? 0),
            'purchase_price' => floatval($input['purchase_price'] ?? 0),
            'mrp' => floatval($input['mrp'] ?? $input['sales_price'] ?? 0),
            'wholesale_price' => floatval($input['wholesale_price'] ?? 0),
            'is_tax_inclusive' => boolval($input['is_tax_inclusive'] ?? false),
            'tax_rate' => floatval($input['tax_rate'] ?? 18.0),
            'cess_rate' => floatval($input['cess_rate'] ?? 0),
            'current_stock' => 0.0, // Managed via StockMovement
            'min_stock_alert' => floatval($input['min_stock_alert'] ?? 10),
            'has_batch' => boolval($input['has_batch'] ?? $input['is_batch_tracked'] ?? false),
            'has_serial' => boolval($input['has_serial'] ?? $input['is_serial_tracked'] ?? false),
            'image_url' => $input['image_url'] ?? null,
            'description' => $input['description'] ?? '',
            'is_active' => true,
        ]);

        // Customer Price Categories (Wholesale, VIP, Distributor, Custom)
        if (!empty($input['prices']) && is_array($input['prices'])) {
            foreach ($input['prices'] as $pCat => $priceVal) {
                ProductPrice::create([
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'price_category' => $pCat,
                    'price' => floatval($priceVal),
                ]);
            }
        }

        // Record Opening Stock if provided
        $openingStock = floatval($input['current_stock'] ?? $input['opening_stock'] ?? 0);
        if ($openingStock > 0) {
            $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId(
                $companyId,
                !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null
            );

            if ($warehouseId > 0) {
                InventoryService::recordStockMovement(
                    $companyId,
                    $warehouseId,
                    $product->id,
                    'OPENING_STOCK',
                    $openingStock,
                    'Product',
                    $product->id,
                    'Opening stock recorded on creation'
                );
            }
        }

        AuditLogService::record([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => AuditLogService::CREATE_PRODUCT,
            'entity' => 'Product',
            'entity_id' => $product->id,
            'old_values' => null,
            'new_values' => $product->toArray(),
            'description' => "Created Product '{$product->name}' (SKU: {$product->sku})"
        ]);

        return response_json([
            'status' => 'success',
            'message' => 'Product created successfully.',
            'data' => $product->load(['prices', 'stockBalances']),
        ], 201);
    }

    /**
     * Update product details.
     */
    public function update($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'edit');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Product::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Product not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $product = Product::find($id);
        $oldValues = $product->toArray();

        $input = get_json_input();

        $product->update([
            'name' => $input['name'] ?? $product->name,
            'product_type' => $input['product_type'] ?? $product->product_type,
            'barcode' => $input['barcode'] ?? $product->barcode,
            'hsn_sac' => $input['hsn_sac'] ?? $product->hsn_sac,
            'unit' => $input['unit'] ?? $product->unit,
            'sales_price' => isset($input['sales_price']) ? floatval($input['sales_price']) : $product->sales_price,
            'purchase_price' => isset($input['purchase_price']) ? floatval($input['purchase_price']) : $product->purchase_price,
            'mrp' => isset($input['mrp']) ? floatval($input['mrp']) : $product->mrp,
            'wholesale_price' => isset($input['wholesale_price']) ? floatval($input['wholesale_price']) : $product->wholesale_price,
            'tax_rate' => isset($input['tax_rate']) ? floatval($input['tax_rate']) : $product->tax_rate,
            'min_stock_alert' => isset($input['min_stock_alert']) ? floatval($input['min_stock_alert']) : $product->min_stock_alert,
            'description' => $input['description'] ?? $product->description,
            'is_active' => isset($input['is_active']) ? boolval($input['is_active']) : $product->is_active,
        ]);

        AuditLogService::record([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => 'UPDATE_PRODUCT',
            'entity' => 'Product',
            'entity_id' => $product->id,
            'old_values' => $oldValues,
            'new_values' => $product->toArray(),
            'description' => "Updated Product '{$product->name}' (SKU: {$product->sku})"
        ]);

        return response_json([
            'status' => 'success',
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    /**
     * Soft-delete or deactivate product.
     */
    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Product::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Product not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $product = Product::find($id);

        $product->update(['is_active' => false]);
        $product->delete();

        AuditLogService::log(
            $companyId,
            $user->name,
            'PRODUCT_EDIT',
            'Product',
            $product->id,
            "Deactivated and soft-deleted Product '{$product->name}'"
        );

        return response_json([
            'status' => 'success',
            'message' => 'Product deactivated and deleted successfully.',
        ]);
    }

    /**
     * Bulk actions (activate, deactivate, delete).
     */
    public function bulkAction()
    {
        $user = AuthMiddleware::authorize('inventory', 'edit');
        $input = get_json_input();

        $action = strtolower($input['action'] ?? '');
        $ids = $input['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            response_json(['status' => 'error', 'message' => 'No product IDs provided for bulk action.'], 422);
        }

        if ($action === 'deactivate') {
            Product::whereIn('id', $ids)->update(['is_active' => false]);
            $msg = count($ids) . ' products deactivated.';
        } elseif ($action === 'activate') {
            Product::whereIn('id', $ids)->update(['is_active' => true]);
            $msg = count($ids) . ' products reactivated.';
        } elseif ($action === 'delete') {
            AuthMiddleware::authorize('inventory', 'delete');
            Product::whereIn('id', $ids)->delete();
            $msg = count($ids) . ' products deleted.';
        } else {
            return response_json(['status' => 'error', 'message' => 'Invalid bulk action.'], 422);
        }

        return response_json(['status' => 'success', 'message' => $msg]);
    }

    /**
     * Resolve effective price for customer price list category.
     */
    public function resolvePrice($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $product = Product::find($id);
        if (!$product) {
            return response_json(['status' => 'error', 'message' => 'Product not found.'], 404);
        }

        $category = $_GET['category'] ?? 'Retail';
        $price = InventoryService::resolveProductPrice($product, $category);

        return response_json([
            'status' => 'success',
            'product_id' => $product->id,
            'price_category' => $category,
            'effective_price' => $price,
        ]);
    }
}
