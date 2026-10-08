<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AutomationEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'automation_events';

    protected $fillable = [
        'event_id',
        'company_id',
        'branch_id',
        'event_type',
        'entity_type',
        'entity_id',
        'occurred_at',
        'triggered_by',
        'metadata_json',
        'status', // CREATED, PROCESSING, PROCESSED, FAILED, SKIPPED
        'error_message',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
