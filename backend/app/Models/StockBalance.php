<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class StockBalance extends Model
{
    use BelongsToTenant;

    protected $table = 'stock_balances';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'last_movement_id',
    ];

    protected $casts = [
        'quantity' => 'float',
        'reserved_quantity' => 'float',
        'available_quantity' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lastMovement()
    {
        return $this->belongsTo(StockMovement::class, 'last_movement_id');
    }
}
