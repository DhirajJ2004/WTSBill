<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_logs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'event_type',
        'entity_type',
        'entity_id',
        'channel',
        'recipient',
        'template_id',
        'status', // PENDING, PROCESSING, SENT, DELIVERED, FAILED, CANCELLED
        'provider_message_id',
        'attempt_count',
        'last_error',
        'idempotency_key',
        'sent_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
