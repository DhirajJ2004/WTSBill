<?php

namespace App\Imports\Validators;

use App\Models\Product;
use App\Models\PriceList;

class PriceListImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'price_list_name', 'Price List Name', $issues);
        $this->validateRequired($row, 'sku', 'Product SKU', $issues);
        $this->validateRequired($row, 'custom_price', 'Custom Price', $issues);

        $plName = trim((string)($row['price_list_name'] ?? ''));
        $sku = trim((string)($row['sku'] ?? ''));
        $price = $this->validateNumeric($row, 'custom_price', 'Custom Price', $issues, false, 0.0);
        $minQty = (int)$this->validateNumeric($row, 'min_quantity', 'Min Quantity', $issues, false, 1.0);
        $maxQty = (int)$this->validateNumeric($row, 'max_quantity', 'Max Quantity', $issues, false, 0.0);

        $product = Product::where('company_id', $companyId)->where('sku', $sku)->first();
        if (!$product && $sku !== '') {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'sku',
                'value' => $sku,
                'message' => "Product SKU '{$sku}' does not exist.",
                'suggested_fix' => 'Verify SKU matches product catalog.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $status = $hasErrors ? 'ERROR' : 'VALID';

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => false,
            'parsed' => [
                'price_list_name' => $plName,
                'product_id' => $product?->id,
                'sku' => $sku,
                'custom_price' => $price,
                'min_quantity' => $minQty,
                'max_quantity' => $maxQty ?: null,
            ],
        ];
    }
}
