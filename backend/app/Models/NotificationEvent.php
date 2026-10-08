<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_events';

    protected $fillable = [
        'company_id',
        'branch_id',
        'event_type',
        'entity_type',
        'entity_id',
        'occurred_at',
        'payload_json',
        'status',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload_json' => 'array',
    ];
}
