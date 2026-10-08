<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $table = 'goods_receipt_items';

    protected $fillable = [
        'company_id',
        'goods_receipt_id',
        'product_id',
        'item_name',
        'quantity_ordered',
        'quantity_received',
        'unit',
        'unit_price',
    ];

    protected $casts = [
        'quantity_ordered' => 'float',
        'quantity_received' => 'float',
        'unit_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
