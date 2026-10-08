<?php

namespace App\Scheduler\Jobs;

use App\Models\Product;
use App\Notifications\Events\BusinessEvent;
use App\Notifications\Events\EventDispatcher;

class StockAlertJob
{
    public static function run(int $companyId): array
    {
        $lowStockProducts = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('min_stock_level')
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->get();

        $alertsSent = 0;
        foreach ($lowStockProducts as $prod) {
            EventDispatcher::dispatch(new BusinessEvent(
                $companyId,
                'LOW_STOCK_DETECTED',
                'PRODUCT',
                $prod->id,
                null,
                [
                    'product_name' => $prod->name,
                    'sku' => $prod->sku,
                    'current_stock' => $prod->current_stock,
                    'minimum_stock' => $prod->min_stock_level,
                ]
            ));
            $alertsSent++;
        }

        return [
            'products_checked' => count($lowStockProducts),
            'alerts_sent' => $alertsSent,
        ];
    }
}
