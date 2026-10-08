<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GSTRate extends Model
{
    protected $table = 'gst_rates';

    protected $fillable = [
        'company_id',
        'rate',
        'cess_rate',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
        'is_system_default',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'rate' => 'float',
        'cess_rate' => 'float',
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
    ];
}
