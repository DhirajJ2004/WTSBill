<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class Branch extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'legal_name',
        'gstin',
        'pan',
        'address',
        'city',
        'state',
        'state_code',
        'pincode',
        'phone',
        'email',
        'is_main_branch',
        'is_active',
    ];

    protected $casts = [
        'is_main_branch' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function settings()
    {
        return $this->hasOne(BranchSetting::class);
    }

    public function branchUsers()
    {
        return $this->hasMany(BranchUser::class);
    }
}
