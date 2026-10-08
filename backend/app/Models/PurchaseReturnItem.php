<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $table = 'purchase_return_items';

    protected $fillable = [
        'company_id',
        'purchase_return_id',
        'product_id',
        'item_name',
        'hsn_sac',
        'quantity',
        'unit',
        'unit_price',
        'taxable_value',
        'gst_rate',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'taxable_value' => 'float',
        'gst_rate' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }
}
