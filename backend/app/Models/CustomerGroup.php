<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerGroup extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_groups';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'discount_percentage',
        'default_price_list_id',
        'status',
    ];

    protected $casts = [
        'discount_percentage' => 'float',
    ];
}
