<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $table = 'purchase_items';

    protected $fillable = [
        'company_id',
        'purchase_id',
        'product_id',
        'item_name',
        'description',
        'hsn_sac',
        'quantity',
        'unit',
        'unit_price',
        'discount_rate',
        'discount_amount',
        'taxable_value',
        'taxable_amount',
        'gst_rate',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'cess_rate',
        'cess_amount',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount_rate' => 'float',
        'discount_amount' => 'float',
        'taxable_value' => 'float',
        'gst_rate' => 'float',
        'cgst_rate' => 'float',
        'cgst_amount' => 'float',
        'sgst_rate' => 'float',
        'sgst_amount' => 'float',
        'igst_rate' => 'float',
        'igst_amount' => 'float',
        'cess_rate' => 'float',
        'cess_amount' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function setTaxableAmountAttribute($value)
    {
        $this->attributes['taxable_value'] = $value;
    }

    public function getTaxableAmountAttribute()
    {
        return $this->attributes['taxable_value'] ?? 0.0;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
