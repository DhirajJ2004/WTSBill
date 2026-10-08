<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationRule extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_rules';

    protected $fillable = [
        'company_id',
        'event_type',
        'rule_name',
        'days_offset',
        'channel_priority',
        'is_active',
        'config_json',
    ];

    protected $casts = [
        'days_offset' => 'integer',
        'is_active' => 'boolean',
        'config_json' => 'array',
    ];
}
