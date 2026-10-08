<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class StockCount extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'stock_counts';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'count_number',
        'count_date',
        'status', // DRAFT, IN_PROGRESS, COMPLETED, CANCELLED
        'counted_by',
        'notes',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_id');
    }
}
