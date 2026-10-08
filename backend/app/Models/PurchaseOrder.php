<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class PurchaseOrder extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'po_number',
        'po_date',
        'expected_delivery',
        'reference_no',
        'sub_total',
        'discount_amount',
        'total_tax',
        'grand_total',
        'status',
        'notes',
        'terms',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'discount_amount' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
