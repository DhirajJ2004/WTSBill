<?php

namespace App\Imports\Processors;

use App\Models\PriceList;
use App\Models\PriceListItem;

class PriceListImportProcessor
{
    public static function process(int $companyId, array $parsedRows, string $duplicateAction = 'SKIP'): array
    {
        $imported = 0;

        foreach ($parsedRows as $item) {
            $data = $item['parsed'];
            if (!$data['product_id']) {
                continue;
            }

            $priceList = PriceList::firstOrCreate(
                ['company_id' => $companyId, 'name' => $data['price_list_name']],
                ['type' => 'FIXED', 'is_active' => true]
            );

            PriceListItem::updateOrCreate(
                [
                    'price_list_id' => $priceList->id,
                    'product_id' => $data['product_id'],
                    'min_quantity' => $data['min_quantity'] ?? 1,
                ],
                [
                    'custom_price' => $data['custom_price'],
                    'max_quantity' => $data['max_quantity'] ?? null,
                ]
            );

            $imported++;
        }

        return ['imported' => $imported, 'updated' => 0, 'skipped' => 0];
    }
}
