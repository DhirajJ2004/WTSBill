<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class TaxRate extends Model
{
    use BelongsToTenant;

    protected $table = 'tax_rates';

    protected $fillable = [
        'company_id',
        'name',
        'rate',
        'tax_type',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'rate' => 'float',
    ];
}
