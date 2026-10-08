<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Unit extends Model
{
    use BelongsToTenant;

    protected $table = 'units';

    protected $fillable = [
        'company_id',
        'name',
        'short_name',
        'type',
        'decimal_precision',
        'status',
    ];

    protected $casts = [
        'decimal_precision' => 'integer',
    ];
}
