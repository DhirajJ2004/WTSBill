<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class Customer extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'customers';

    protected $fillable = [
        'company_id',
        'customer_group_id',
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
        'credit_limit',
        'payment_terms_days',
        'is_active',
        'customer_type',
        'alt_phone',
        'tax_type',
        'place_of_supply',
        'status',
        'notes',
        'credit_period',
        'opening_balance_type',
        'default_price_list',
        'price_list_id',
        'reminder_enabled',
        'preferred_channel',
        'whatsapp_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'customer_group_id' => 'integer',
        'reminder_enabled' => 'boolean',
        'opening_balance' => 'float',
        'current_balance' => 'float',
        'credit_limit' => 'float',
        'payment_terms_days' => 'integer',
        'credit_period' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
