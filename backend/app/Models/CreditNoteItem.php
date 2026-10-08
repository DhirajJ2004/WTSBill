<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    protected $table = 'credit_note_items';

    protected $fillable = [
        'company_id',
        'credit_note_id',
        'product_id',
        'item_name',
        'hsn_sac',
        'quantity',
        'unit',
        'unit_price',
        'discount_rate',
        'discount_amount',
        'taxable_value',
        'gst_rate',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'amount',
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
        'amount' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
