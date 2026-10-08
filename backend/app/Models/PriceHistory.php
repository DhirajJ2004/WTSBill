<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PriceHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'price_histories';

    protected $fillable = [
        'company_id',
        'product_id',
        'price_list_id',
        'old_price',
        'new_price',
        'changed_by_user_id',
        'changed_by_name',
        'reason',
    ];

    protected $casts = [
        'old_price' => 'float',
        'new_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
