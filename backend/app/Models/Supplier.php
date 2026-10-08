<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class Supplier extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'suppliers';

    protected $fillable = [
        'company_id',
        'supplier_group_id',
        'name',
        'company_name',
        'gstin',
        'pan',
        'email',
        'phone',
        'address_line1',
        'city',
        'state',
        'state_code',
        'pincode',
        'opening_balance',
        'current_balance',
        'is_active',
        'alt_phone',
        'tax_type',
        'place_of_supply',
        'contact_person',
        'notes',
        'status',
        'credit_period',
        'default_payment_mode',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'supplier_group_id' => 'integer',
        'opening_balance' => 'float',
        'current_balance' => 'float',
        'credit_period' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function addresses()
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function contacts()
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    public function group()
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
