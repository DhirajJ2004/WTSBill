<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Warehouse extends Model
{
    use BelongsToTenant;

    protected $table = 'warehouses';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'pincode',
        'manager_name',
        'phone',
        'is_primary',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function balances()
    {
        return $this->hasMany(StockBalance::class);
    }
}
