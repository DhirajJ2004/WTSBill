<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $table = 'stock_count_items';

    protected $fillable = [
        'company_id',
        'stock_count_id',
        'product_id',
        'system_quantity',
        'physical_quantity',
        'difference',
        'unit_cost',
        'adjustment_created',
        'adjustment_id',
    ];

    protected $casts = [
        'system_quantity' => 'float',
        'physical_quantity' => 'float',
        'difference' => 'float',
        'unit_cost' => 'float',
        'adjustment_created' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'adjustment_id');
    }
}
