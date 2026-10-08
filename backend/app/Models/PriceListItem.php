<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PriceListItem extends Model
{
    use BelongsToTenant;

    protected $table = 'price_list_items';

    protected $fillable = [
        'company_id',
        'price_list_id',
        'product_id',
        'price',
        'minimum_quantity',
        'maximum_quantity',
        'discount_rate',
        'discount_amount',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'price' => 'float',
        'minimum_quantity' => 'float',
        'maximum_quantity' => 'float',
        'discount_rate' => 'float',
        'discount_amount' => 'float',
    ];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
