<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Unit;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Capsule\Manager as DB;

class InventoryRepository
{
    /**
     * Get paginated products list with category, unit, and stock balances.
     */
    public static function getProducts(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%")
                  ->orWhere('hsn_sac', 'like', "%{$s}%");
            });
        }

        // Category Filter
        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $query->where('category_id', (int)$filters['category_id']);
        }

        // Product Type Filter
        if (!empty($filters['product_type']) && $filters['product_type'] !== 'all') {
            $query->where('product_type', $filters['product_type']);
        }

        // Low Stock Filter
        if (!empty($filters['low_stock']) && $filters['low_stock'] == 1) {
            $query->whereRaw('current_stock <= COALESCE(min_stock_level, min_stock_alert, 0)');
        }

        // Status Filter
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== 'all') {
            $query->where('is_active', (bool)$filters['is_active']);
        }

        $total = $query->count();
        $items = $query->orderBy('name', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Find product by ID scoped to tenant.
     */
    public static function findProduct(int $id, int $companyId): ?Product
    {
        return Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Find warehouse by ID scoped to tenant.
     */
    public static function findWarehouse(int $id, int $companyId): ?Warehouse
    {
        return Warehouse::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Get all active warehouses for tenant.
     */
    public static function getWarehouses(int $companyId)
    {
        return Warehouse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get primary warehouse or first available for company.
     */
    public static function getPrimaryWarehouse(int $companyId): ?Warehouse
    {
        $primary = Warehouse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_primary', 1)
            ->first();

        if (!$primary) {
            $primary = Warehouse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->first();
        }

        return $primary;
    }

    /**
     * Get all categories for tenant.
     */
    public static function getCategories(int $companyId)
    {
        return Category::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get all units for tenant.
     */
    public static function getUnits(int $companyId)
    {
        return Unit::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get low stock products list.
     */
    public static function getLowStockProducts(int $companyId, int $limit = 50): array
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('track_inventory', 1)
            ->whereRaw('current_stock <= COALESCE(min_stock_level, min_stock_alert, 0)')
            ->orderBy('current_stock', 'asc')
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get warehouse stock balances for a given product.
     */
    public static function getProductWarehouseBalances(int $productId, int $companyId): array
    {
        return DB::table('stock_balances')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->where('stock_balances.company_id', $companyId)
            ->where('stock_balances.product_id', $productId)
            ->select(
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'warehouses.is_primary',
                'stock_balances.quantity',
                'stock_balances.available_quantity',
                'stock_balances.reserved_quantity'
            )
            ->get()
            ->toArray();
    }

    /**
     * Get Item Stock Ledger with Chronological Running Balance.
     */
    public static function getStockLedger(int $productId, int $companyId, ?int $warehouseId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table('stock_movements')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->where('stock_movements.company_id', $companyId)
            ->where('stock_movements.product_id', $productId);

        if ($warehouseId !== null && $warehouseId > 0) {
            $query->where('stock_movements.warehouse_id', $warehouseId);
        }
        if (!empty($fromDate)) {
            $query->where('stock_movements.movement_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $query->where('stock_movements.movement_date', '<=', $toDate);
        }

        $movements = $query->orderBy('stock_movements.created_at', 'asc')
            ->orderBy('stock_movements.id', 'asc')
            ->select(
                'stock_movements.id',
                'stock_movements.movement_date',
                'stock_movements.movement_type',
                'stock_movements.direction',
                'stock_movements.quantity',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.balance_after',
                'stock_movements.reference_type',
                'stock_movements.reference_number',
                'stock_movements.notes',
                'warehouses.name as warehouse_name'
            )
            ->get();

        $runningBalance = 0.0;
        $ledgerEntries = [];

        foreach ($movements as $m) {
            $qty = (float)$m->quantity;
            $dir = strtoupper($m->direction ?? 'IN');
            $inQty = ($dir === 'IN') ? $qty : 0.0;
            $outQty = ($dir === 'OUT') ? $qty : 0.0;

            $runningBalance += ($inQty - $outQty);

            $ledgerEntries[] = [
                'id' => $m->id,
                'date' => $m->movement_date ?: date('Y-m-d'),
                'type' => $m->movement_type,
                'direction' => $dir,
                'warehouse' => $m->warehouse_name ?? 'Default Warehouse',
                'in_qty' => $inQty,
                'out_qty' => $outQty,
                'running_balance' => $runningBalance,
                'unit_cost' => (float)$m->unit_cost,
                'total_cost' => (float)$m->total_cost,
                'reference' => $m->reference_number ?: ($m->reference_type ? "{$m->reference_type} #{$m->id}" : '-'),
                'notes' => $m->notes,
            ];
        }

        return $ledgerEntries;
    }

    /**
     * Compute Total Inventory Valuation and KPIs.
     */
    public static function getStockValuationSummary(int $companyId): array
    {
        $products = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->get();

        $totalProducts = $products->count();
        $totalStockUnits = 0.0;
        $totalValuation = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($products as $p) {
            $stock = (float)$p->current_stock;
            $cost = (float)($p->purchase_price ?: 0.0);
            $minStock = (float)($p->min_stock_level ?? ($p->min_stock_alert ?? 0.0));

            if ($stock > 0) {
                $totalStockUnits += $stock;
                $totalValuation += ($stock * $cost);
            }

            if ($stock <= 0) {
                $outOfStockCount++;
            } elseif ($minStock > 0 && $stock <= $minStock) {
                $lowStockCount++;
            }
        }

        return [
            'total_products' => $totalProducts,
            'total_stock_units' => round($totalStockUnits, 2),
            'total_valuation' => round($totalValuation, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }
}
