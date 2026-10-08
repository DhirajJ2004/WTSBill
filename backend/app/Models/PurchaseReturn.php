<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class PurchaseReturn extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'purchase_id',
        'return_number',
        'return_date',
        'reason',
        'warehouse_id',
        'sub_total',
        'total_tax',
        'grand_total',
        'refund_amount',
        'tax_reversal_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
        'refund_amount' => 'float',
        'tax_reversal_amount' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
