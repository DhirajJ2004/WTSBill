<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class SalesOrder extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'sales_orders';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'order_number',
        'order_date',
        'expected_delivery',
        'reference_no',
        'sub_total',
        'discount_amount',
        'total_tax',
        'grand_total',
        'status',
        'fulfillment_status',
        'payment_status',
        'notes',
        'source_quotation_id',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'discount_amount' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
