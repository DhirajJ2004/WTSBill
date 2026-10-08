<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Reminder extends Model
{
    use BelongsToTenant;

    protected $table = 'reminders';

    protected $fillable = [
        'company_id',
        'branch_id',
        'reminder_type',
        'entity_type',
        'entity_id',
        'recipient',
        'due_date',
        'reminder_date',
        'schedule_type',
        'offset_days',
        'frequency',
        'max_count',
        'sent_count',
        'stop_condition',
        'status',
        'priority',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_date' => 'date',
        'offset_days' => 'integer',
        'max_count' => 'integer',
        'sent_count' => 'integer',
    ];
}
