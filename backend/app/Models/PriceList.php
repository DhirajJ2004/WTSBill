<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class PriceList extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'price_lists';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'description',
        'currency',
        'price_type', // FIXED, PERCENTAGE_ADJUSTMENT
        'adjustment_value',
        'tax_mode', // TAX_EXCLUSIVE, TAX_INCLUSIVE
        'is_default',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'adjustment_value' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }
}
