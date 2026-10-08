<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'legal_name',
        'gstin',
        'pan',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'state_code',
        'currency',
        'financial_year_start',
        'logo_url',
        'business_type',
        'status',
        'branch_management_enabled',
        'multi_warehouse_enabled',
        'allow_negative_stock',
    ];

    protected $casts = [
        'branch_management_enabled' => 'boolean',
        'multi_warehouse_enabled' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_companies');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
