<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AccountingPeriod extends Model
{
    use BelongsToTenant;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'company_id',
        'financial_year',
        'period_name',
        'start_date',
        'end_date',
        'is_locked',
        'locked_by',
        'locked_at',
        'lock_reason',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_locked' => 'boolean',
    ];
}
