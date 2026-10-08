<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerAddress extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'company_id',
        'customer_id',
        'type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'state_code',
        'pincode',
        'country',
        'landmark',
        'is_default',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'is_default' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
