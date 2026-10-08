<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Unit;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Repositories\InventoryRepository;
use App\Validators\InventoryValidator;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class InventoryService
{
    /**
     * Create a new Product and record opening stock if provided.
     */
    public static function createProduct(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = InventoryValidator::validateProduct($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        return DB::transaction(function () use ($data, $companyId, $userName) {
            // Determine default warehouse
            $warehouseId = !empty($data['default_warehouse_id']) 
                ? (int)$data['default_warehouse_id'] 
                : null;

            if (!$warehouseId) {
                $primaryWh = InventoryRepository::getPrimaryWarehouse($companyId);
                if (!$primaryWh) {
                    $primaryWh = Warehouse::create([
                        'company_id' => $companyId,
                        'name' => 'Main Warehouse',
                        'code' => 'WH-MAIN',
                        'is_primary' => true,
                        'is_default' => true,
                        'is_active' => true,
                    ]);
                }
                $warehouseId = $primaryWh->id;
            }

            $openingStock = (float)($data['opening_stock'] ?? 0.0);
            $purchasePrice = (float)($data['purchase_price'] ?? 0.0);
            $sellingPrice = (float)($data['selling_price'] ?? ($data['sales_price'] ?? 0.0));
            $taxRate = (float)($data['tax_rate'] ?? ($data['gst_rate'] ?? 0.0));
            $minStock = (float)($data['min_stock_level'] ?? ($data['min_stock_alert'] ?? 0.0));

            $product = Product::create([
                'company_id' => $companyId,
                'name' => trim($data['name']),
                'sku' => trim($data['sku'] ?? ''),
                'barcode' => trim($data['barcode'] ?? ''),
                'hsn_sac' => trim($data['hsn_sac'] ?? ''),
                'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
                'unit' => trim($data['unit'] ?? 'PCS'),
                'product_type' => trim($data['product_type'] ?? 'Goods'),
                'purchase_price' => $purchasePrice,
                'sales_price' => $sellingPrice,
                'selling_price' => $sellingPrice,
                'mrp' => (float)($data['mrp'] ?? $sellingPrice),
                'tax_rate' => $taxRate,
                'gst_rate' => $taxRate,
                'opening_stock' => $openingStock,
                'current_stock' => $openingStock,
                'min_stock_level' => $minStock,
                'min_stock_alert' => $minStock,
                'track_inventory' => isset($data['track_inventory']) ? (bool)$data['track_inventory'] : true,
                'allow_negative_stock' => (bool)($data['allow_negative_stock'] ?? false),
                'default_warehouse_id' => $warehouseId,
                'description' => trim($data['description'] ?? ''),
                'is_active' => true,
            ]);

            // Initialize warehouse stock balance
            StockBalance::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'quantity' => $openingStock,
                'available_quantity' => $openingStock,
                'reserved_quantity' => 0.0,
            ]);

            // Record Opening Stock ledger movement if > 0
            if ($openingStock > 0) {
                StockMovement::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $product->id,
                    'movement_type' => 'OPENING_STOCK',
                    'type' => 'OPENING_STOCK',
                    'direction' => 'IN',
                    'quantity' => $openingStock,
                    'unit_cost' => $purchasePrice,
                    'total_cost' => round($openingStock * $purchasePrice, 2),
                    'balance_after' => $openingStock,
                    'reference_type' => 'OPENING_STOCK',
                    'reference_number' => 'OP-STOCK',
                    'movement_date' => date('Y-m-d'),
                    'created_by' => $userName,
                    'notes' => 'Initial opening stock setup',
                ]);
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'PRODUCT_CREATE',
                'Product',
                $product->id,
                "Created product '{$product->name}' (SKU: {$product->sku}) with opening stock {$openingStock}"
            );

            return [
                'success' => true,
                'product' => $product,
                'product_id' => $product->id,
                'message' => "Product '{$product->name}' created successfully."
            ];
        });
    }

    /**
     * Update an existing product.
     */
    public static function updateProduct(int $id, array $data, int $companyId, string $userName = 'Admin'): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$product) {
            return ['success' => false, 'message' => "Product #{$id} not found or unauthorized."];
        }

        $errors = InventoryValidator::validateProduct($data, $companyId, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $purchasePrice = isset($data['purchase_price']) ? (float)$data['purchase_price'] : $product->purchase_price;
        $sellingPrice = isset($data['selling_price']) ? (float)$data['selling_price'] : ($data['sales_price'] ?? $product->selling_price);

        $product->update([
            'name' => trim($data['name'] ?? $product->name),
            'sku' => trim($data['sku'] ?? $product->sku),
            'barcode' => trim($data['barcode'] ?? $product->barcode),
            'hsn_sac' => trim($data['hsn_sac'] ?? $product->hsn_sac),
            'category_id' => isset($data['category_id']) ? (int)$data['category_id'] : $product->category_id,
            'unit' => trim($data['unit'] ?? $product->unit),
            'product_type' => trim($data['product_type'] ?? $product->product_type),
            'purchase_price' => $purchasePrice,
            'sales_price' => $sellingPrice,
            'selling_price' => $sellingPrice,
            'mrp' => isset($data['mrp']) ? (float)$data['mrp'] : $product->mrp,
            'tax_rate' => isset($data['tax_rate']) ? (float)$data['tax_rate'] : $product->tax_rate,
            'min_stock_level' => isset($data['min_stock_level']) ? (float)$data['min_stock_level'] : $product->min_stock_level,
            'min_stock_alert' => isset($data['min_stock_level']) ? (float)$data['min_stock_level'] : $product->min_stock_alert,
            'description' => trim($data['description'] ?? $product->description),
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : $product->is_active,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'PRODUCT_UPDATE',
            'Product',
            $product->id,
            "Updated product '{$product->name}' (ID #{$product->id})"
        );

        return [
            'success' => true,
            'product' => $product,
            'product_id' => $product->id,
            'message' => "Product '{$product->name}' updated successfully."
        ];
    }

    /**
     * Delete Product (with reference integrity protection).
     */
    public static function deleteProduct(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$product) {
            return ['success' => false, 'message' => "Product #{$id} not found or unauthorized."];
        }

        // Check if invoice items exist for this product
        $invoiceItemsCount = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('invoice_items.product_id', $id)
            ->whereNull('invoices.deleted_at')
            ->count();

        if ($invoiceItemsCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete product: {$invoiceItemsCount} active invoice items reference this product."
            ];
        }

        // Check if purchase items exist for this product
        $purchaseItemsCount = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.company_id', $companyId)
            ->where('purchase_items.product_id', $id)
            ->whereNull('purchases.deleted_at')
            ->count();

        if ($purchaseItemsCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete product: {$purchaseItemsCount} purchase items reference this product."
            ];
        }

        $product->delete();

        AuditLogService::log(
            $companyId,
            $userName,
            'PRODUCT_DELETE',
            'Product',
            $id,
            "Deleted product '{$product->name}' (ID #{$id})"
        );

        return ['success' => true, 'message' => "Product '{$product->name}' deleted successfully."];
    }

    /**
     * Record Stock In (Purchases / GRN / Inward).
     */
    public static function recordStockIn(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost = 0.0,
        string $movementType = 'PURCHASE',
        array $metadata = []
    ): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Stock in quantity must be greater than zero.'];
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $productId, $quantity, $unitCost, $movementType, $metadata) {
            // Verify product tenant isolation
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            // Verify warehouse tenant isolation
            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->first();

            if (!$warehouse) {
                return ['success' => false, 'message' => "Warehouse #{$warehouseId} not found or unauthorized."];
            }

            // Get or create warehouse stock balance
            $balance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }

            $newWhQty = (float)$balance->quantity + $quantity;
            $balance->update([
                'quantity' => $newWhQty,
                'available_quantity' => $newWhQty - (float)$balance->reserved_quantity,
            ]);

            // Update master product current stock
            $newProductStock = (float)$product->current_stock + $quantity;
            $product->update(['current_stock' => $newProductStock]);

            $cost = ($unitCost > 0) ? $unitCost : (float)$product->purchase_price;

            // Record Movement in Ledger
            $movement = StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'movement_type' => $movementType,
                'type' => $movementType,
                'direction' => 'IN',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => $newProductStock,
                'reference_type' => $metadata['reference_type'] ?? 'PURCHASE',
                'reference_id' => $metadata['reference_id'] ?? null,
                'reference_number' => $metadata['reference_number'] ?? null,
                'movement_date' => $metadata['movement_date'] ?? date('Y-m-d'),
                'created_by' => $metadata['created_by'] ?? 'System',
                'notes' => $metadata['notes'] ?? 'Stock Inward movement',
            ]);

            return [
                'success' => true,
                'movement_id' => $movement->id,
                'new_stock' => $newProductStock,
                'warehouse_stock' => $newWhQty,
                'message' => "Added {$quantity} units to '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Record Stock Out (Sales / Delivery Challan / Outward).
     */
    public static function recordStockOut(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost = 0.0,
        string $movementType = 'SALE',
        array $metadata = []
    ): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Stock out quantity must be greater than zero.'];
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $productId, $quantity, $unitCost, $movementType, $metadata) {
            // Verify product tenant isolation with lock
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            // Verify warehouse tenant isolation
            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->first();

            if (!$warehouse) {
                return ['success' => false, 'message' => "Warehouse #{$warehouseId} not found or unauthorized."];
            }

            // Get warehouse stock balance with lock
            $balance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $currentWhQty = $balance ? (float)$balance->quantity : 0.0;
            $allowNegative = (bool)($product->allow_negative_stock ?? false);

            if ($currentWhQty < $quantity && !$allowNegative) {
                return [
                    'success' => false,
                    'message' => "Insufficient stock in warehouse '{$warehouse->name}'. Available: {$currentWhQty}, Requested: {$quantity}."
                ];
            }

            if (!$balance) {
                $balance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }

            $newWhQty = $currentWhQty - $quantity;
            $balance->update([
                'quantity' => $newWhQty,
                'available_quantity' => $newWhQty - (float)$balance->reserved_quantity,
            ]);

            // Master product stock update
            $newProductStock = (float)$product->current_stock - $quantity;
            $product->update(['current_stock' => $newProductStock]);

            $cost = ($unitCost > 0) ? $unitCost : (float)$product->purchase_price;

            // Record Movement in Ledger
            $movement = StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'movement_type' => $movementType,
                'type' => $movementType,
                'direction' => 'OUT',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => $newProductStock,
                'reference_type' => $metadata['reference_type'] ?? 'INVOICE',
                'reference_id' => $metadata['reference_id'] ?? null,
                'reference_number' => $metadata['reference_number'] ?? null,
                'movement_date' => $metadata['movement_date'] ?? date('Y-m-d'),
                'created_by' => $metadata['created_by'] ?? 'System',
                'notes' => $metadata['notes'] ?? 'Stock Outward movement',
            ]);

            return [
                'success' => true,
                'movement_id' => $movement->id,
                'new_stock' => $newProductStock,
                'warehouse_stock' => $newWhQty,
                'message' => "Deducted {$quantity} units from '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Adjust stock up or down (Discrepancy / Damage / Audit Correction).
     */
    public static function adjustStock(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        string $type,
        string $reason = '',
        string $userName = 'Admin'
    ): array {
        $validation = InventoryValidator::validateAdjustment([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'adjustment_type' => $type,
        ], $companyId);

        if (!empty($validation)) {
            return ['success' => false, 'errors' => $validation, 'message' => reset($validation)];
        }

        $isPositive = in_array(strtoupper($type), ['ADD', 'IN', 'EXCESS', 'CORRECTION_ADD'], true);

        if ($isPositive) {
            return self::recordStockIn($companyId, $warehouseId, $productId, $quantity, 0.0, 'STOCK_ADJUSTMENT_IN', [
                'reference_type' => 'STOCK_ADJUSTMENT',
                'created_by' => $userName,
                'notes' => "Stock Adjustment (Add): {$reason}",
            ]);
        } else {
            return self::recordStockOut($companyId, $warehouseId, $productId, $quantity, 0.0, 'STOCK_ADJUSTMENT_OUT', [
                'reference_type' => 'STOCK_ADJUSTMENT',
                'created_by' => $userName,
                'notes' => "Stock Adjustment (Reduce): {$reason}",
            ]);
        }
    }

    /**
     * Transfer stock between warehouses atomically.
     */
    public static function transferStock(
        int $companyId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        float $quantity,
        string $notes = '',
        string $userName = 'Admin'
    ): array {
        $validation = InventoryValidator::validateTransfer([
            'source_warehouse_id' => $fromWarehouseId,
            'destination_warehouse_id' => $toWarehouseId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ], $companyId);

        if (!empty($validation)) {
            return ['success' => false, 'errors' => $validation, 'message' => reset($validation)];
        }

        return DB::transaction(function () use ($companyId, $fromWarehouseId, $toWarehouseId, $productId, $quantity, $notes, $userName) {
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            // Check source warehouse balance with lock
            $srcBalance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $fromWarehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $srcQty = $srcBalance ? (float)$srcBalance->quantity : 0.0;
            $allowNegative = (bool)($product->allow_negative_stock ?? false);

            if ($srcQty < $quantity && !$allowNegative) {
                return [
                    'success' => false,
                    'message' => "Cannot transfer: Insufficient stock in source warehouse. Available: {$srcQty}, Transfer: {$quantity}."
                ];
            }

            // Deduct from source warehouse
            $newSrcQty = $srcQty - $quantity;
            if (!$srcBalance) {
                $srcBalance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $fromWarehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }
            $srcBalance->update([
                'quantity' => $newSrcQty,
                'available_quantity' => $newSrcQty - (float)$srcBalance->reserved_quantity,
            ]);

            // Add to destination warehouse
            $destBalance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $toWarehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$destBalance) {
                $destBalance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $toWarehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }
            $newDestQty = (float)$destBalance->quantity + $quantity;
            $destBalance->update([
                'quantity' => $newDestQty,
                'available_quantity' => $newDestQty - (float)$destBalance->reserved_quantity,
            ]);

            $cost = (float)$product->purchase_price;
            $transferNo = 'TRF-' . strtoupper(substr(uniqid(), -6));

            // Log Source OUT movement
            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $fromWarehouseId,
                'product_id' => $productId,
                'movement_type' => 'TRANSFER_OUT',
                'type' => 'TRANSFER_OUT',
                'direction' => 'OUT',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => (float)$product->current_stock, // Total company stock remains unchanged
                'reference_type' => 'TRANSFER',
                'reference_number' => $transferNo,
                'movement_date' => date('Y-m-d'),
                'created_by' => $userName,
                'notes' => "Transferred to WH #{$toWarehouseId}. {$notes}",
            ]);

            // Log Destination IN movement
            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $toWarehouseId,
                'product_id' => $productId,
                'movement_type' => 'TRANSFER_IN',
                'type' => 'TRANSFER_IN',
                'direction' => 'IN',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => (float)$product->current_stock,
                'reference_type' => 'TRANSFER',
                'reference_number' => $transferNo,
                'movement_date' => date('Y-m-d'),
                'created_by' => $userName,
                'notes' => "Transferred from WH #{$fromWarehouseId}. {$notes}",
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'STOCK_TRANSFER',
                'Product',
                $productId,
                "Transferred {$quantity} units of '{$product->name}' from WH #{$fromWarehouseId} to WH #{$toWarehouseId} (Ref: {$transferNo})"
            );

            return [
                'success' => true,
                'transfer_number' => $transferNo,
                'source_quantity' => $newSrcQty,
                'destination_quantity' => $newDestQty,
                'message' => "Transferred {$quantity} units of '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Create a new Warehouse.
     */
    public static function createWarehouse(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = InventoryValidator::validateWarehouse($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $code = trim($data['code'] ?? '');
        if (empty($code)) {
            $code = 'WH-' . strtoupper(substr(uniqid(), -4));
        }

        $warehouse = Warehouse::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'code' => $code,
            'address' => trim($data['address'] ?? ''),
            'city' => trim($data['city'] ?? ''),
            'state' => trim($data['state'] ?? ''),
            'pincode' => trim($data['pincode'] ?? ''),
            'manager_name' => trim($data['manager_name'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'is_primary' => (bool)($data['is_primary'] ?? false),
            'is_default' => (bool)($data['is_default'] ?? false),
            'is_active' => true,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'WAREHOUSE_CREATE',
            'Warehouse',
            $warehouse->id,
            "Created Warehouse '{$warehouse->name}' (Code: {$warehouse->code})"
        );

        return [
            'success' => true,
            'warehouse' => $warehouse,
            'warehouse_id' => $warehouse->id,
            'message' => "Warehouse '{$warehouse->name}' created successfully."
        ];
    }

    /**
     * Create a new Category.
     */
    public static function createCategory(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = InventoryValidator::validateCategory($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $category = Category::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'code' => trim($data['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['name']), 0, 6))),
            'description' => trim($data['description'] ?? ''),
            'status' => 'ACTIVE',
        ]);

        return [
            'success' => true,
            'category' => $category,
            'category_id' => $category->id,
            'message' => "Category '{$category->name}' created successfully."
        ];
    }

    /**
     * Create a new Unit.
     */
    public static function createUnit(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = InventoryValidator::validateUnit($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $unit = Unit::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'short_name' => strtoupper(trim($data['short_name'])),
            'type' => trim($data['type'] ?? 'Unit'),
            'decimal_precision' => (int)($data['decimal_precision'] ?? 0),
            'status' => 'ACTIVE',
        ]);

        return [
            'success' => true,
            'unit' => $unit,
            'unit_id' => $unit->id,
            'message' => "Unit '{$unit->name}' created successfully."
        ];
    }
}
