<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockBalance;
use App\Models\Product;
use InvalidArgumentException;
use RuntimeException;

class WarehouseManagementService
{
    /**
     * Ensure a Main / Default Warehouse exists for the branch.
     */
    public static function ensureMainWarehouse(int $companyId, int $branchId): Warehouse
    {
        $warehouse = Warehouse::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('is_default', true)->orWhere('is_primary', true)->orWhere('code', 'MAIN-WH');
            })
            ->first();

        if (!$warehouse) {
            $branch = Branch::find($branchId);
            $branchCode = $branch ? $branch->code : 'MAIN';

            $warehouse = Warehouse::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => ($branch ? $branch->name . ' - ' : '') . 'Main Warehouse',
                'code' => $branchCode . '-WH1',
                'address' => $branch?->address,
                'city' => $branch?->city ?: 'Mumbai',
                'state' => $branch?->state ?: 'Maharashtra',
                'pincode' => $branch?->pincode ?: '400001',
                'manager_name' => 'Store Manager',
                'is_primary' => true,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        return $warehouse;
    }

    /**
     * Create a new warehouse.
     */
    public static function createWarehouse(int $companyId, int $branchId, array $data): Warehouse
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        if (empty($code)) {
            throw new InvalidArgumentException("Warehouse code is required.");
        }

        $existing = Warehouse::where('company_id', $companyId)->where('code', $code)->first();
        if ($existing) {
            throw new InvalidArgumentException("Warehouse code '{$code}' already exists for this business.");
        }

        $isDefault = !empty($data['is_default']);
        if ($isDefault) {
            Warehouse::where('company_id', $companyId)->where('branch_id', $branchId)->update(['is_default' => false]);
        }

        return Warehouse::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $data['name'] ?? $code,
            'code' => $code,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? 'Maharashtra',
            'pincode' => $data['pincode'] ?? null,
            'manager_name' => $data['manager_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_primary' => !empty($data['is_primary']),
            'is_default' => $isDefault,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update warehouse.
     */
    public static function updateWarehouse(int $warehouseId, array $data): Warehouse
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $companyId = $warehouse->company_id;

        if (!empty($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $existing = Warehouse::where('company_id', $companyId)
                ->where('code', $code)
                ->where('id', '!=', $warehouseId)
                ->first();
            if ($existing) {
                throw new InvalidArgumentException("Warehouse code '{$code}' is already used.");
            }
            $warehouse->code = $code;
        }

        if (isset($data['name'])) $warehouse->name = $data['name'];
        if (isset($data['address'])) $warehouse->address = $data['address'];
        if (isset($data['city'])) $warehouse->city = $data['city'];
        if (isset($data['state'])) $warehouse->state = $data['state'];
        if (isset($data['pincode'])) $warehouse->pincode = $data['pincode'];
        if (isset($data['manager_name'])) $warehouse->manager_name = $data['manager_name'];
        if (isset($data['phone'])) $warehouse->phone = $data['phone'];

        if (!empty($data['is_default']) && !$warehouse->is_default) {
            Warehouse::where('company_id', $companyId)->where('branch_id', $warehouse->branch_id)->update(['is_default' => false]);
            $warehouse->is_default = true;
        }

        if (isset($data['is_active'])) {
            $warehouse->is_active = (bool)$data['is_active'];
        }

        $warehouse->save();
        return $warehouse;
    }

    /**
     * Deactivate warehouse with stock safety check.
     */
    public static function deactivateWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        // Check non-zero physical stock
        $activeStock = StockBalance::where('warehouse_id', $warehouseId)->sum('quantity');
        if (floatval($activeStock) > 0.0001) {
            throw new RuntimeException("Cannot deactivate warehouse '{$warehouse->name}' because it contains " . floatval($activeStock) . " units of stock. Transfer stock first.");
        }

        $warehouse->update(['is_active' => false]);
        return $warehouse;
    }

    /**
     * Get stock by location for a product.
     */
    public static function getStockByLocation(int $companyId, int $productId, ?int $warehouseId = null): array
    {
        $query = StockBalance::where('stock_balances.company_id', $companyId)
            ->where('stock_balances.product_id', $productId)
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->select(
                'stock_balances.*',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'warehouses.branch_id'
            );

        if ($warehouseId) {
            $query->where('stock_balances.warehouse_id', $warehouseId);
        }

        $balances = $query->get();

        $rows = [];
        $totalQty = 0.0;
        $totalReserved = 0.0;
        $totalInTransit = 0.0;
        $totalAvailable = 0.0;

        foreach ($balances as $b) {
            $qty = floatval($b->quantity);
            $res = floatval($b->reserved_quantity ?? 0);
            $inTr = floatval($b->in_transit_quantity ?? 0);
            $avail = max(0, $qty - $res);

            $totalQty += $qty;
            $totalReserved += $res;
            $totalInTransit += $inTr;
            $totalAvailable += $avail;

            $rows[] = [
                'warehouse_id' => $b->warehouse_id,
                'warehouse_name' => $b->warehouse_name,
                'warehouse_code' => $b->warehouse_code,
                'branch_id' => $b->branch_id,
                'quantity' => $qty,
                'reserved_quantity' => $res,
                'in_transit_quantity' => $inTr,
                'available_quantity' => $avail,
            ];
        }

        return [
            'product_id' => $productId,
            'total_quantity' => $totalQty,
            'total_reserved' => $totalReserved,
            'total_in_transit' => $totalInTransit,
            'total_available' => $totalAvailable,
            'warehouses' => $rows,
        ];
    }

    /**
     * Reserve stock for orders.
     */
    public static function reserveStock(int $companyId, int $productId, int $warehouseId, float $quantity): void
    {
        $balance = StockBalance::firstOrCreate(
            ['company_id' => $companyId, 'product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0.000, 'reserved_quantity' => 0.000, 'in_transit_quantity' => 0.000]
        );

        $balance->reserved_quantity = floatval($balance->reserved_quantity) + $quantity;
        $balance->save();
    }

    /**
     * Release reserved stock.
     */
    public static function releaseReservedStock(int $companyId, int $productId, int $warehouseId, float $quantity): void
    {
        $balance = StockBalance::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($balance) {
            $balance->reserved_quantity = max(0, floatval($balance->reserved_quantity) - $quantity);
            $balance->save();
        }
    }

    /**
     * Toggle multi-warehouse mode.
     */
    public static function toggleMultiWarehouse(int $companyId, bool $enabled): bool
    {
        $company = Company::findOrFail($companyId);
        $company->update(['multi_warehouse_enabled' => $enabled]);
        return $enabled;
    }
}
