<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SchedulerJob extends Model
{
    use BelongsToTenant;

    protected $table = 'scheduler_jobs';

    protected $fillable = [
        'company_id',
        'job_key',
        'job_type',
        'status', // PENDING, PROCESSING, COMPLETED, FAILED
        'started_at',
        'completed_at',
        'attempt_count',
        'last_error',
        'locked_until',
        'locked_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'locked_until' => 'datetime',
        'attempt_count' => 'integer',
    ];
}
