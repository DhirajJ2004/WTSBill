<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CommunicationMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'communication_messages';

    protected $fillable = [
        'company_id',
        'branch_id',
        'channel',
        'entity_type',
        'entity_id',
        'recipient',
        'template_id',
        'template_code',
        'subject',
        'body',
        'variables_json',
        'attachment_path',
        'provider_name',
        'provider_reference',
        'status',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
