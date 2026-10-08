<?php

namespace App\Validators;

use Illuminate\Database\Capsule\Manager as DB;

class InventoryValidator
{
    /**
     * Validate Product creation/update payload.
     */
    public static function validateProduct(array $data, int $companyId, ?int $excludeId = null): array
    {
        $errors = [];

        // 1. Name validation
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors['name'] = 'Product Name is required.';
        } elseif (strlen($name) < 2 || strlen($name) > 255) {
            $errors['name'] = 'Product Name must be between 2 and 255 characters.';
        }

        // 2. SKU validation and uniqueness per company
        $sku = trim($data['sku'] ?? '');
        if (!empty($sku)) {
            $dupQuery = DB::table('products')
                ->where('company_id', $companyId)
                ->where('sku', $sku)
                ->whereNull('deleted_at');

            if ($excludeId !== null && $excludeId > 0) {
                $dupQuery->where('id', '!=', $excludeId);
            }

            if ($dupQuery->exists()) {
                $errors['sku'] = "A product with SKU '{$sku}' already exists in this company.";
            }
        }

        // 3. Monetary and numerical values
        $numericFields = [
            'purchase_price' => 'Purchase price',
            'sales_price' => 'Selling price',
            'selling_price' => 'Selling price',
            'mrp' => 'MRP',
            'opening_stock' => 'Opening stock',
            'min_stock_level' => 'Minimum stock level',
            'min_stock_alert' => 'Minimum stock alert',
            'reorder_level' => 'Reorder level',
            'reorder_quantity' => 'Reorder quantity',
            'tax_rate' => 'Tax rate',
            'gst_rate' => 'GST rate',
        ];

        foreach ($numericFields as $field => $label) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if (!is_numeric($data[$field]) || (float)$data[$field] < 0) {
                    $errors[$field] = "{$label} must be a non-negative number.";
                }
            }
        }

        // 4. Warehouse validation if specified
        if (!empty($data['default_warehouse_id'])) {
            $whExists = DB::table('warehouses')
                ->where('company_id', $companyId)
                ->where('id', (int)$data['default_warehouse_id'])
                ->exists();
            if (!$whExists) {
                $errors['default_warehouse_id'] = 'Selected default warehouse is invalid or unauthorized.';
            }
        }

        // 5. Category validation if specified
        if (!empty($data['category_id'])) {
            $catExists = DB::table('categories')
                ->where('company_id', $companyId)
                ->where('id', (int)$data['category_id'])
                ->exists();
            if (!$catExists) {
                $errors['category_id'] = 'Selected category is invalid or unauthorized.';
            }
        }

        return $errors;
    }

    /**
     * Validate Category payload.
     */
    public static function validateCategory(array $data, int $companyId, ?int $excludeId = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors['name'] = 'Category Name is required.';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors['name'] = 'Category Name must be between 2 and 100 characters.';
        }

        return $errors;
    }

    /**
     * Validate Unit payload.
     */
    public static function validateUnit(array $data, int $companyId, ?int $excludeId = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        $shortName = trim($data['short_name'] ?? '');

        if (empty($name)) {
            $errors['name'] = 'Unit Name is required (e.g. Piece, Kilogram).';
        }
        if (empty($shortName)) {
            $errors['short_name'] = 'Unit Symbol/Short name is required (e.g. PCS, KG).';
        }

        return $errors;
    }

    /**
     * Validate Warehouse payload.
     */
    public static function validateWarehouse(array $data, int $companyId, ?int $excludeId = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors['name'] = 'Warehouse Name is required.';
        }

        $code = trim($data['code'] ?? '');
        if (!empty($code)) {
            $dupQuery = DB::table('warehouses')
                ->where('company_id', $companyId)
                ->where('code', $code);

            if ($excludeId !== null && $excludeId > 0) {
                $dupQuery->where('id', '!=', $excludeId);
            }

            if ($dupQuery->exists()) {
                $errors['code'] = "Warehouse code '{$code}' is already used.";
            }
        }

        return $errors;
    }

    /**
     * Validate Stock Adjustment payload.
     */
    public static function validateAdjustment(array $data, int $companyId): array
    {
        $errors = [];

        if (empty($data['warehouse_id'])) {
            $errors['warehouse_id'] = 'Warehouse is required for adjustment.';
        } else {
            $whExists = DB::table('warehouses')
                ->where('company_id', $companyId)
                ->where('id', (int)$data['warehouse_id'])
                ->exists();
            if (!$whExists) {
                $errors['warehouse_id'] = 'Specified warehouse does not exist or unauthorized.';
            }
        }

        if (empty($data['product_id'])) {
            $errors['product_id'] = 'Product is required for adjustment.';
        } else {
            $prodExists = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', (int)$data['product_id'])
                ->whereNull('deleted_at')
                ->exists();
            if (!$prodExists) {
                $errors['product_id'] = 'Specified product does not exist or unauthorized.';
            }
        }

        $qty = $data['quantity'] ?? null;
        if ($qty === null || !is_numeric($qty) || (float)$qty <= 0) {
            $errors['quantity'] = 'Adjustment quantity must be a positive number greater than 0.';
        }

        $type = strtoupper(trim($data['adjustment_type'] ?? ($data['type'] ?? '')));
        if (!in_array($type, ['ADD', 'REDUCE', 'SET', 'IN', 'OUT', 'DAMAGE', 'EXCESS', 'SHORTAGE', 'CORRECTION'], true)) {
            $errors['adjustment_type'] = 'Invalid adjustment type specified.';
        }

        return $errors;
    }

    /**
     * Validate Stock Transfer payload.
     */
    public static function validateTransfer(array $data, int $companyId): array
    {
        $errors = [];

        $sourceId = (int)($data['source_warehouse_id'] ?? ($data['from_warehouse_id'] ?? 0));
        $destId = (int)($data['destination_warehouse_id'] ?? ($data['to_warehouse_id'] ?? 0));

        if ($sourceId <= 0) {
            $errors['source_warehouse_id'] = 'Source warehouse is required.';
        }
        if ($destId <= 0) {
            $errors['destination_warehouse_id'] = 'Destination warehouse is required.';
        }
        if ($sourceId > 0 && $destId > 0 && $sourceId === $destId) {
            $errors['destination_warehouse_id'] = 'Source and Destination warehouses cannot be the same.';
        }

        if ($sourceId > 0) {
            $srcExists = DB::table('warehouses')->where('company_id', $companyId)->where('id', $sourceId)->exists();
            if (!$srcExists) {
                $errors['source_warehouse_id'] = 'Source warehouse is invalid or unauthorized.';
            }
        }

        if ($destId > 0) {
            $destExists = DB::table('warehouses')->where('company_id', $companyId)->where('id', $destId)->exists();
            if (!$destExists) {
                $errors['destination_warehouse_id'] = 'Destination warehouse is invalid or unauthorized.';
            }
        }

        $productId = (int)($data['product_id'] ?? 0);
        if ($productId <= 0) {
            $errors['product_id'] = 'Product is required for transfer.';
        } else {
            $prodExists = DB::table('products')->where('company_id', $companyId)->where('id', $productId)->whereNull('deleted_at')->exists();
            if (!$prodExists) {
                $errors['product_id'] = 'Product is invalid or unauthorized.';
            }
        }

        $qty = $data['quantity'] ?? null;
        if ($qty === null || !is_numeric($qty) || (float)$qty <= 0) {
            $errors['quantity'] = 'Transfer quantity must be a positive number greater than 0.';
        }

        return $errors;
    }
}
