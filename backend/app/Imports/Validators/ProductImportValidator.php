<?php

namespace App\Imports\Validators;

use App\Models\Product;

class ProductImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'name', 'Product Name', $issues);
        $this->validateRequired($row, 'selling_price', 'Selling Price', $issues);

        $name = trim((string)($row['name'] ?? ''));
        $sku = trim((string)($row['sku'] ?? ''));
        $barcode = trim((string)($row['barcode'] ?? ''));

        $sellingPrice = $this->validateNumeric($row, 'selling_price', 'Selling Price', $issues, false, 0.0);
        $purchasePrice = $this->validateNumeric($row, 'purchase_price', 'Purchase Price', $issues, false, 0.0);
        $mrp = $this->validateNumeric($row, 'mrp', 'MRP', $issues, false, $sellingPrice);
        $gstRate = $this->validateNumeric($row, 'gst_rate', 'GST Rate %', $issues, false, 0.0);
        $minStock = $this->validateNumeric($row, 'min_stock_level', 'Min Stock Level', $issues, false, 0.0);
        $openingStock = $this->validateNumeric($row, 'opening_stock', 'Opening Stock', $issues, false, 0.0);

        if ($barcode === '') {
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'barcode',
                'message' => 'Product has no barcode configured.',
                'suggested_fix' => 'Provide a barcode or use generated SKU.',
            ];
        }

        // Check duplicate by SKU or Barcode or Name
        $isDuplicate = false;
        $existing = null;
        if ($sku !== '') {
            $existing = Product::where('company_id', $companyId)->where('sku', $sku)->first();
        }
        if (!$existing && $barcode !== '') {
            $existing = Product::where('company_id', $companyId)->where('barcode', $barcode)->first();
        }
        if (!$existing && $name !== '') {
            $existing = Product::where('company_id', $companyId)->where('name', $name)->first();
        }

        if ($existing) {
            $isDuplicate = true;
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'name',
                'message' => "Duplicate product identified (Matches existing product #{$existing->id}: '{$existing->name}').",
                'suggested_fix' => 'Choose duplicate action: Skip, Update, or Create New.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $hasWarnings = count(array_filter($issues, fn($i) => $i['type'] === 'WARNING')) > 0;

        $status = 'VALID';
        if ($hasErrors) {
            $status = 'ERROR';
        } elseif ($isDuplicate) {
            $status = 'DUPLICATE';
        } elseif ($hasWarnings) {
            $status = 'WARNING';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => $isDuplicate,
            'existing_id' => $existing?->id,
            'parsed' => [
                'name' => $name,
                'sku' => $sku ?: ('SKU-' . strtoupper(substr(md5($name), 0, 8))),
                'barcode' => $barcode,
                'category' => $row['category'] ?? 'General',
                'unit' => $row['unit'] ?? 'PCS',
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'mrp' => $mrp,
                'gst_rate' => $gstRate,
                'hsn_code' => $row['hsn_code'] ?? null,
                'min_stock_level' => $minStock,
                'opening_stock' => $openingStock,
            ],
        ];
    }
}
