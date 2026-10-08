<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerPriceOverride extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_price_overrides';

    protected $fillable = [
        'company_id',
        'customer_id',
        'product_id',
        'price',
        'discount_rate',
        'notes',
    ];

    protected $casts = [
        'price' => 'float',
        'discount_rate' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
