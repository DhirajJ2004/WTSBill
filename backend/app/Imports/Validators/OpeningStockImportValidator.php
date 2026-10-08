<?php

namespace App\Imports\Validators;

use App\Models\Product;
use App\Models\Warehouse;

class OpeningStockImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'sku', 'Product SKU', $issues);
        $this->validateRequired($row, 'warehouse_code', 'Warehouse Code', $issues);
        $this->validateRequired($row, 'quantity', 'Quantity', $issues);

        $sku = trim((string)($row['sku'] ?? ''));
        $whCode = trim((string)($row['warehouse_code'] ?? ''));

        $qty = $this->validateNumeric($row, 'quantity', 'Quantity', $issues, false, 0.0);
        $unitCost = $this->validateNumeric($row, 'unit_cost', 'Unit Cost', $issues, false, 0.0);

        $product = Product::where('company_id', $companyId)->where('sku', $sku)->first();
        if (!$product && $sku !== '') {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'sku',
                'value' => $sku,
                'message' => "Product SKU '{$sku}' does not exist in master catalog.",
                'suggested_fix' => 'Import Products before importing opening stock.',
            ];
        }

        $warehouse = Warehouse::where('company_id', $companyId)->where('code', $whCode)->first();
        if (!$warehouse && $whCode !== '') {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'warehouse_code',
                'value' => $whCode,
                'message' => "Warehouse code '{$whCode}' not found.",
                'suggested_fix' => 'Check active warehouse codes or create warehouse.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $status = $hasErrors ? 'ERROR' : 'VALID';

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => false,
            'parsed' => [
                'product_id' => $product?->id,
                'warehouse_id' => $warehouse?->id,
                'sku' => $sku,
                'warehouse_code' => $whCode,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'as_of_date' => $row['as_of_date'] ?? date('Y-04-01'),
            ],
        ];
    }
}
