<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class StockAdjustmentItem extends Model
{
    use BelongsToTenant;

    protected $table = 'stock_adjustment_items';

    protected $fillable = [
        'company_id',
        'stock_adjustment_id',
        'adjustment_id',
        'product_id',
        'adjustment_type',
        'type', // INCREASE, DECREASE
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
