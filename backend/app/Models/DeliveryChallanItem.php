<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryChallanItem extends Model
{
    protected $table = 'delivery_challan_items';

    protected $fillable = [
        'company_id',
        'delivery_challan_id',
        'product_id',
        'item_name',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'batch_no',
        'serial_no',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'total_amount' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
