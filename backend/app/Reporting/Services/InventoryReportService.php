<?php

namespace App\Reporting\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Batch;
use App\Models\InvoiceItem;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class InventoryReportService
{
    /**
     * Inventory Summary & Overview
     */
    public static function getInventorySummary(int $companyId, array $filters = []): array
    {
        $products = Product::where('company_id', $companyId)->get();
        $totalProducts = $products->count();

        $totalUnits = 0.0;
        $totalValuation = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($products as $p) {
            $stock = floatval($p->current_stock);
            $cost = floatval($p->purchase_price ?: ($p->cost_price ?? 0));
            $minStock = floatval($p->min_stock_level ?? 0);

            $totalUnits += $stock;
            $totalValuation += ($stock * $cost);

            if ($stock <= 0) {
                $outOfStockCount++;
            } elseif ($minStock > 0 && $stock <= $minStock) {
                $lowStockCount++;
            }
        }

        // Expiring batches in next 30 days
        $expiringBatches = Batch::where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->count();

        // Stock by category chart
        $catMap = [];
        foreach ($products as $p) {
            $cat = is_object($p->category) ? ($p->category->name ?? 'General') : (string)($p->category ?: 'General');
            $catMap[$cat] = ($catMap[$cat] ?? 0) + floatval($p->current_stock);
        }
        $catItems = [];
        foreach ($catMap as $cat => $units) {
            $catItems[] = ['name' => $cat, 'value' => $units];
        }
        $catChart = ReportChartService::buildDonutChart('Stock Units by Category', $catItems);

        return [
            'summary_kpis' => [
                'total_products' => $totalProducts,
                'total_stock_units' => $totalUnits,
                'total_stock_valuation' => $totalValuation,
                'low_stock_items' => $lowStockCount,
                'out_of_stock_items' => $outOfStockCount,
                'expiring_soon_batches' => $expiringBatches,
            ],
            'charts' => [
                'stock_by_category' => $catChart,
            ],
            'rows' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?: '-',
                'category' => $p->category ?: 'General',
                'current_stock' => floatval($p->current_stock),
                'unit' => $p->unit ?: 'Pcs',
                'purchase_price' => floatval($p->purchase_price),
                'selling_price' => floatval($p->selling_price),
                'stock_value' => floatval($p->current_stock * ($p->purchase_price ?: 0)),
                'min_stock_level' => floatval($p->min_stock_level ?? 0),
            ])->toArray(),
            'columns' => [
                ['key' => 'name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'current_stock', 'label' => 'Stock Qty', 'type' => 'number'],
                ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                ['key' => 'purchase_price', 'label' => 'Cost Price (₹)', 'type' => 'currency'],
                ['key' => 'selling_price', 'label' => 'Selling Price (₹)', 'type' => 'currency'],
                ['key' => 'stock_value', 'label' => 'Valuation (₹)', 'type' => 'currency'],
                ['key' => 'min_stock_level', 'label' => 'Min Level', 'type' => 'number'],
            ],
        ];
    }

    /**
     * Stock Ledger (Opening + In - Out = Closing)
     */
    public static function getStockLedger(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $products = Product::where('company_id', $companyId)->get();

        $rows = [];
        foreach ($products as $p) {
            $movements = StockMovement::where('company_id', $companyId)
                ->where('product_id', $p->id)
                ->whereDate('movement_date', '>=', $dates['start_date'])
                ->whereDate('movement_date', '<=', $dates['end_date'])
                ->get();

            $opening = floatval($p->opening_stock);
            $qtyIn = 0.0;
            $qtyOut = 0.0;
            $purchases = 0.0;
            $sales = 0.0;
            $returns = 0.0;
            $adjustments = 0.0;

            foreach ($movements as $m) {
                $q = floatval($m->quantity);
                if ($m->direction === 'IN') {
                    $qtyIn += $q;
                    if ($m->movement_type === 'PURCHASE') $purchases += $q;
                    elseif ($m->movement_type === 'RETURN_IN') $returns += $q;
                    elseif (str_contains($m->movement_type, 'ADJUSTMENT')) $adjustments += $q;
                } else {
                    $qtyOut += $q;
                    if ($m->movement_type === 'SALE') $sales += $q;
                    elseif ($m->movement_type === 'RETURN_OUT') $returns -= $q;
                    elseif (str_contains($m->movement_type, 'ADJUSTMENT')) $adjustments -= $q;
                }
            }

            $closing = $opening + $qtyIn - $qtyOut;

            $rows[] = [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'sku' => $p->sku ?: '-',
                'unit' => $p->unit ?: 'Pcs',
                'opening_stock' => $opening,
                'purchases' => $purchases,
                'sales' => $sales,
                'returns' => $returns,
                'adjustments' => $adjustments,
                'total_in' => $qtyIn,
                'total_out' => $qtyOut,
                'closing_stock' => floatval($p->current_stock),
            ];
        }

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'opening_stock', 'label' => 'Opening', 'type' => 'number'],
                ['key' => 'total_in', 'label' => 'Inward (+)', 'type' => 'number'],
                ['key' => 'total_out', 'label' => 'Outward (-)', 'type' => 'number'],
                ['key' => 'closing_stock', 'label' => 'Closing Balance', 'type' => 'number'],
            ],
        ];
    }

    /**
     * Stock Movement Report
     */
    public static function getStockMovement(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $query = StockMovement::with(['product', 'warehouse'])
            ->where('company_id', $companyId)
            ->whereDate('movement_date', '>=', $dates['start_date'])
            ->whereDate('movement_date', '<=', $dates['end_date']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        $moves = $query->orderBy('movement_date', 'asc')->get();

        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($moves as $m) {
            $qty = floatval($m->quantity);
            $qtyIn = $m->direction === 'IN' ? $qty : 0.0;
            $qtyOut = $m->direction === 'OUT' ? $qty : 0.0;

            $totalIn += $qtyIn;
            $totalOut += $qtyOut;

            $rows[] = [
                'id' => $m->id,
                'date' => is_object($m->movement_date) ? $m->movement_date->format('Y-m-d') : (string)$m->movement_date,
                'product_name' => $m->product?->name ?: 'Item #' . $m->product_id,
                'warehouse_name' => $m->warehouse?->name ?: 'Main Warehouse',
                'movement_type' => $m->movement_type,
                'direction' => $m->direction,
                'reference_type' => $m->reference_type ?: '-',
                'reference_number' => $m->reference_number ?: '-',
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'unit_cost' => floatval($m->unit_cost),
                'total_cost' => floatval($qty * ($m->unit_cost ?: 0)),
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_movements' => count($rows),
                'total_qty_in' => $totalIn,
                'total_qty_out' => $totalOut,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'warehouse_name', 'label' => 'Warehouse', 'type' => 'text'],
                ['key' => 'movement_type', 'label' => 'Type', 'type' => 'badge'],
                ['key' => 'reference_number', 'label' => 'Ref #', 'type' => 'text'],
                ['key' => 'qty_in', 'label' => 'Qty In', 'type' => 'number'],
                ['key' => 'qty_out', 'label' => 'Qty Out', 'type' => 'number'],
                ['key' => 'unit_cost', 'label' => 'Unit Cost (₹)', 'type' => 'currency'],
                ['key' => 'total_cost', 'label' => 'Total Cost (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Stock Valuation Report
     */
    public static function getStockValuation(int $companyId, array $filters = []): array
    {
        $products = Product::where('company_id', $companyId)->get();

        $rows = [];
        $totalVal = 0.0;
        $totalQty = 0.0;

        foreach ($products as $p) {
            $qty = floatval($p->current_stock);
            $cost = floatval($p->purchase_price ?: ($p->cost_price ?? 0));
            $val = $qty * $cost;

            $totalQty += $qty;
            $totalVal += $val;

            $rows[] = [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'sku' => $p->sku ?: '-',
                'category' => $p->category ?: 'General',
                'quantity' => $qty,
                'unit' => $p->unit ?: 'Pcs',
                'average_cost' => $cost,
                'stock_value' => $val,
            ];
        }

        return [
            'summary_kpis' => [
                'total_items' => count($rows),
                'total_quantity' => $totalQty,
                'total_stock_value' => $totalVal,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'quantity', 'label' => 'Quantity on Hand', 'type' => 'number'],
                ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                ['key' => 'average_cost', 'label' => 'Avg Cost (₹)', 'type' => 'currency'],
                ['key' => 'stock_value', 'label' => 'Stock Valuation (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Low Stock Report
     */
    public static function getLowStock(int $companyId, array $filters = []): array
    {
        $products = Product::where('company_id', $companyId)->get();

        $rows = [];
        foreach ($products as $p) {
            $stock = floatval($p->current_stock);
            $min = floatval($p->min_stock_alert ?: ($p->reorder_level ?: ($p->min_stock_level ?? 0)));

            if ($min > 0 && $stock <= $min) {
                $shortage = $min - $stock;
                $rows[] = [
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'sku' => $p->sku ?: '-',
                    'current_stock' => $stock,
                    'min_stock_level' => $min,
                    'shortage' => $shortage > 0 ? $shortage : 0,
                    'suggested_reorder_qty' => $shortage > 0 ? ($shortage * 2) : $min,
                    'cost_price' => floatval($p->purchase_price),
                ];
            }
        }

        return [
            'summary_kpis' => [
                'low_stock_items_count' => count($rows),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'current_stock', 'label' => 'Current Stock', 'type' => 'number'],
                ['key' => 'min_stock_level', 'label' => 'Min Threshold', 'type' => 'number'],
                ['key' => 'shortage', 'label' => 'Shortage', 'type' => 'number'],
                ['key' => 'suggested_reorder_qty', 'label' => 'Suggested Reorder', 'type' => 'number'],
            ],
        ];
    }

    /**
     * Fast Moving Products (Top Velocity)
     */
    public static function getFastMovingProducts(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with(['invoice', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
            })->get();

        $map = [];
        foreach ($items as $it) {
            $pId = $it->product_id ?: 0;
            if (!isset($map[$pId])) {
                $map[$pId] = [
                    'product_id' => $pId,
                    'product_name' => $it->item_name ?: ($it->product?->name ?: 'Item #' . $pId),
                    'sku' => $it->product?->sku ?: '-',
                    'units_sold' => 0.0,
                    'revenue' => 0.0,
                ];
            }
            $map[$pId]['units_sold'] += floatval($it->quantity);
            $map[$pId]['revenue'] += floatval($it->total_amount ?: ($it->quantity * $it->unit_price));
        }

        $rows = array_values($map);
        usort($rows, fn($a, $b) => $b['units_sold'] <=> $a['units_sold']);

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'units_sold', 'label' => 'Units Sold', 'type' => 'number'],
                ['key' => 'revenue', 'label' => 'Total Revenue (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Dead Stock Report (>90 Days without sales)
     */
    public static function getDeadStock(int $companyId, array $filters = []): array
    {
        $thresholdDays = intval($filters['days_threshold'] ?? 90);
        $cutoffDate = Carbon::now()->subDays($thresholdDays);

        $products = Product::where('company_id', $companyId)
            ->where('current_stock', '>', 0)
            ->get();

        $rows = [];
        foreach ($products as $p) {
            $recentSale = InvoiceItem::whereHas('invoice', function ($q) use ($companyId, $cutoffDate) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $cutoffDate->toDateString())
                    ->where('status', '!=', 'CANCELLED');
            })->where('product_id', $p->id)->first();

            if (!$recentSale) {
                $cost = floatval($p->purchase_price ?: ($p->cost_price ?? 0));
                $stock = floatval($p->current_stock);
                $rows[] = [
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'sku' => $p->sku ?: '-',
                    'current_stock' => $stock,
                    'unit_cost' => $cost,
                    'trapped_capital' => $stock * $cost,
                    'days_dormant' => $thresholdDays,
                ];
            }
        }

        usort($rows, fn($a, $b) => $b['trapped_capital'] <=> $a['trapped_capital']);

        return [
            'summary_kpis' => [
                'dead_stock_count' => count($rows),
                'total_trapped_capital' => array_sum(array_column($rows, 'trapped_capital')),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'current_stock', 'label' => 'Current Stock', 'type' => 'number'],
                ['key' => 'unit_cost', 'label' => 'Unit Cost (₹)', 'type' => 'currency'],
                ['key' => 'trapped_capital', 'label' => 'Trapped Capital (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Batch Expiry Report
     */
    public static function getBatchExpiry(int $companyId, array $filters = []): array
    {
        $batches = Batch::with(['product', 'warehouse'])
            ->where('company_id', $companyId)
            ->where('quantity', '>', 0)
            ->get();

        $now = Carbon::now();
        $rows = [];

        foreach ($batches as $b) {
            $expDate = Carbon::parse($b->expiry_date);
            $daysToExpiry = $now->diffInDays($expDate, false);

            $status = 'SAFE';
            if ($daysToExpiry < 0) {
                $status = 'EXPIRED';
            } elseif ($daysToExpiry <= 30) {
                $status = 'EXPIRING_SOON';
            }

            $rows[] = [
                'batch_id' => $b->id,
                'product_name' => $b->product?->name ?: 'Item',
                'batch_number' => $b->batch_number,
                'warehouse_name' => $b->warehouse?->name ?: 'Main Warehouse',
                'expiry_date' => $expDate->format('Y-m-d'),
                'quantity' => floatval($b->quantity),
                'days_to_expiry' => (int)$daysToExpiry,
                'status' => $status,
            ];
        }

        usort($rows, fn($a, $b) => $a['days_to_expiry'] <=> $b['days_to_expiry']);

        return [
            'summary_kpis' => [
                'total_batches' => count($rows),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'batch_number', 'label' => 'Batch #', 'type' => 'text'],
                ['key' => 'warehouse_name', 'label' => 'Warehouse', 'type' => 'text'],
                ['key' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date'],
                ['key' => 'quantity', 'label' => 'Qty on Hand', 'type' => 'number'],
                ['key' => 'days_to_expiry', 'label' => 'Days Remaining', 'type' => 'number'],
                ['key' => 'status', 'label' => 'Risk Status', 'type' => 'badge'],
            ],
        ];
    }
}
