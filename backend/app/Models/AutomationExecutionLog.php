<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AutomationExecutionLog extends Model
{
    use BelongsToTenant;

    protected $table = 'automation_execution_logs';

    protected $fillable = [
        'company_id',
        'rule_id',
        'event_id',
        'action_type',
        'entity_type',
        'entity_id',
        'status', // SUCCESS, FAILED, SKIPPED
        'result_data_json',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'result_data_json' => 'array',
        'executed_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
