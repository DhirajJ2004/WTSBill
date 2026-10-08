<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AutomationRule extends Model
{
    use BelongsToTenant;

    protected $table = 'automation_rules';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'description',
        'event_type',
        'conditions_json',
        'actions_json',
        'is_active',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'conditions_json' => 'array',
        'actions_json' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function executionLogs()
    {
        return $this->hasMany(AutomationExecutionLog::class, 'rule_id');
    }
}
