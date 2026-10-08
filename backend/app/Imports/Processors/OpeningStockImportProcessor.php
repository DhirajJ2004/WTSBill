<?php

namespace App\Imports\Processors;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\StockBalance;

class OpeningStockImportProcessor
{
    public static function process(int $companyId, array $parsedRows, string $duplicateAction = 'SKIP'): array
    {
        $imported = 0;

        foreach ($parsedRows as $item) {
            $data = $item['parsed'];
            $product = Product::where('company_id', $companyId)->find($data['product_id']);
            $warehouse = Warehouse::where('company_id', $companyId)->find($data['warehouse_id']);

            if (!$product || !$warehouse) {
                continue;
            }

            $qty = $data['quantity'];
            $unitCost = $data['unit_cost'];

            // Update product opening stock & current stock
            $product->update([
                'opening_stock' => $qty,
                'current_stock' => $qty,
                'purchase_price' => ($product->purchase_price > 0 ? $product->purchase_price : $unitCost),
            ]);

            // Update Warehouse stock balance
            $whStock = StockBalance::firstOrNew([
                'company_id' => $companyId,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
            ]);
            $whStock->branch_id = $warehouse->branch_id;
            $whStock->quantity = $qty;
            $whStock->available_quantity = $qty;
            $whStock->save();

            // Record Stock Movement
            StockMovement::create([
                'company_id' => $companyId,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'type' => 'IN',
                'movement_type' => 'OPENING_STOCK',
                'direction' => 'IN',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $qty * $unitCost,
                'balance_after' => $qty,
                'reference_type' => 'IMPORT',
                'reference_id' => null,
                'movement_date' => date('Y-m-d'),
                'notes' => 'Imported opening stock',
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'updated' => 0, 'skipped' => 0];
    }
}
