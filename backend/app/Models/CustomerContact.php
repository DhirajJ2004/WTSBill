<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerContact extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_contacts';

    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'designation',
        'email',
        'phone',
        'is_primary',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
