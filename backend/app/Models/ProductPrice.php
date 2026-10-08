<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ProductPrice extends Model
{
    use BelongsToTenant;

    protected $table = 'product_prices';

    protected $fillable = [
        'company_id',
        'product_id',
        'price_category', // Retail, Wholesale, Distributor, VIP, Custom
        'price',
        'min_quantity',
    ];

    protected $casts = [
        'price' => 'float',
        'min_quantity' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
