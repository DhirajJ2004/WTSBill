<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class StockAdjustment extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'stock_adjustments';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'adjustment_number',
        'adjustment_date',
        'reason',
        'notes',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class, 'adjustment_id');
    }
}
