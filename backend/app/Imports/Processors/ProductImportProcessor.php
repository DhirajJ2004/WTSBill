<?php

namespace App\Imports\Processors;

use App\Models\Product;
use App\Models\Category;

class ProductImportProcessor
{
    public static function process(int $companyId, array $parsedRows, string $duplicateAction = 'SKIP'): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($parsedRows as $item) {
            $data = $item['parsed'];
            $isDuplicate = $item['is_duplicate'] ?? false;
            $existingId = $item['existing_id'] ?? null;

            if ($isDuplicate && $duplicateAction === 'SKIP') {
                $skipped++;
                continue;
            }

            // Ensure Category exists or create it
            $categoryName = $data['category'] ?: 'General';
            $category = Category::firstOrCreate(
                ['company_id' => $companyId, 'name' => $categoryName],
                ['is_active' => true]
            );

            if ($isDuplicate && $duplicateAction === 'UPDATE' && $existingId) {
                $product = Product::where('company_id', $companyId)->find($existingId);
                if ($product) {
                    $product->update(array_filter([
                        'category_id' => $category->id,
                        'barcode' => $data['barcode'] ?: $product->barcode,
                        'unit' => $data['unit'] ?: $product->unit,
                        'purchase_price' => $data['purchase_price'] ?: $product->purchase_price,
                        'sales_price' => $data['selling_price'] ?: $product->sales_price,
                        'selling_price' => $data['selling_price'] ?: $product->selling_price,
                        'mrp' => $data['mrp'] ?: $product->mrp,
                        'tax_rate' => $data['gst_rate'] ?? $product->tax_rate,
                        'gst_rate' => $data['gst_rate'] ?? $product->gst_rate,
                        'hsn_code' => $data['hsn_code'] ?: $product->hsn_code,
                        'min_stock_alert' => $data['min_stock_level'] ?: $product->min_stock_alert,
                        'min_stock_level' => $data['min_stock_level'] ?: $product->min_stock_level,
                    ]));
                    $updated++;
                    continue;
                }
            }

            Product::create([
                'company_id' => $companyId,
                'category_id' => $category->id,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?: null,
                'unit' => $data['unit'] ?? 'PCS',
                'purchase_price' => $data['purchase_price'] ?? 0.00,
                'sales_price' => $data['selling_price'],
                'selling_price' => $data['selling_price'],
                'mrp' => $data['mrp'] ?? $data['selling_price'],
                'tax_rate' => $data['gst_rate'] ?? 0.00,
                'gst_rate' => $data['gst_rate'] ?? 0.00,
                'hsn_code' => $data['hsn_code'] ?? null,
                'min_stock_alert' => $data['min_stock_level'] ?? 0,
                'min_stock_level' => $data['min_stock_level'] ?? 0,
                'opening_stock' => $data['opening_stock'] ?? 0,
                'current_stock' => $data['opening_stock'] ?? 0,
                'is_active' => true,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }
}
