<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ReportSchedule extends Model
{
    use BelongsToTenant;

    protected $table = 'report_schedules';

    protected $fillable = [
        'company_id',
        'branch_id',
        'report_key',
        'title',
        'frequency',
        'delivery_channel',
        'recipient',
        'export_format',
        'filters_json',
        'last_run_at',
        'next_run_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
