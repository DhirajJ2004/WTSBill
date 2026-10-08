<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class DeliveryChallan extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'delivery_challans';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'challan_number',
        'challan_date',
        'sales_order_id',
        'reference_so',
        'delivery_address',
        'transport_details',
        'status',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryChallanItem::class);
    }
}
