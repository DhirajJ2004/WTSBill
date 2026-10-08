<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\InventoryService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class ProductImportController
{
    public function preview()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $input = get_json_input();
        $csvText = $input['csv_content'] ?? '';

        if (empty($csvText)) {
            return response_json(['status' => 'error', 'message' => 'CSV content is empty.'], 422);
        }

        $lines = explode("\n", str_replace("\r", "", $csvText));
        $header = str_getcsv(array_shift($lines));

        $previewRows = [];
        $errors = [];

        foreach ($lines as $idx => $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $mapped = [];

            foreach ($header as $colIdx => $colName) {
                $mapped[strtolower(trim($colName))] = $row[$colIdx] ?? '';
            }

            $name = $mapped['name'] ?? $mapped['product_name'] ?? '';
            $sku = $mapped['sku'] ?? 'SKU-' . rand(1000, 9999);
            $price = floatval($mapped['sales_price'] ?? $mapped['price'] ?? 0);

            $isValid = !empty($name);
            if (!$isValid) {
                $errors[] = "Row " . ($idx + 2) . ": Product Name is missing.";
            }

            $previewRows[] = [
                'row_index' => $idx + 2,
                'name' => $name,
                'sku' => $sku,
                'hsn_sac' => $mapped['hsn_sac'] ?? $mapped['hsn'] ?? '84818030',
                'sales_price' => $price,
                'purchase_price' => floatval($mapped['purchase_price'] ?? 0),
                'tax_rate' => floatval($mapped['tax_rate'] ?? 18.0),
                'opening_stock' => floatval($mapped['opening_stock'] ?? $mapped['stock'] ?? 0),
                'is_valid' => $isValid,
            ];
        }

        return response_json([
            'status' => 'success',
            'total_rows' => count($previewRows),
            'valid_rows' => count(array_filter($previewRows, fn($r) => $r['is_valid'])),
            'errors' => $errors,
            'data' => $previewRows,
        ]);
    }

    public function import()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $companyId = $user->current_company_id;
        $input = get_json_input();
        $rows = $input['rows'] ?? [];

        if (empty($rows)) {
            return response_json(['status' => 'error', 'message' => 'No rows provided for import.'], 422);
        }

        $importedCount = 0;

        DB::transaction(function () use ($companyId, $user, $rows, &$importedCount) {
            foreach ($rows as $r) {
                if (empty($r['name'])) continue;

                $sku = !empty($r['sku']) ? $r['sku'] : 'SKU-' . rand(10000, 99999);

                $product = Product::create([
                    'company_id' => $companyId,
                    'category_id' => 1,
                    'product_type' => 'Goods',
                    'name' => trim($r['name']),
                    'sku' => $sku,
                    'barcode' => 'BAR-' . $sku,
                    'hsn_sac' => $r['hsn_sac'] ?? '84818030',
                    'unit' => $r['unit'] ?? 'Pcs',
                    'sales_price' => floatval($r['sales_price'] ?? 0),
                    'purchase_price' => floatval($r['purchase_price'] ?? 0),
                    'tax_rate' => floatval($r['tax_rate'] ?? 18.0),
                    'current_stock' => 0.0,
                    'min_stock_alert' => 10,
                    'is_active' => true,
                ]);

                $openingStock = floatval($r['opening_stock'] ?? 0);
                if ($openingStock > 0) {
                    InventoryService::recordStockMovement(
                        $companyId,
                        1,
                        $product->id,
                        'OPENING_STOCK',
                        $openingStock,
                        'Product',
                        $product->id,
                        'Imported opening stock'
                    );
                }

                $importedCount++;
            }

            AuditLogService::log(
                $companyId,
                $user->name,
                'PRODUCT_EDIT',
                'Product',
                0,
                "Bulk imported {$importedCount} products via CSV"
            );
        });

        return response_json([
            'status' => 'success',
            'message' => "Successfully imported {$importedCount} products.",
            'imported_count' => $importedCount,
        ], 201);
    }
}
