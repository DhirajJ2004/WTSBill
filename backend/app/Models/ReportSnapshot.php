<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ReportSnapshot extends Model
{
    use BelongsToTenant;

    protected $table = 'report_snapshots';

    protected $fillable = [
        'company_id',
        'report_key',
        'period_type',
        'period_label',
        'start_date',
        'end_date',
        'dataset_json',
        'is_locked',
        'locked_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'dataset_json' => 'array',
        'is_locked' => 'boolean',
    ];
}
