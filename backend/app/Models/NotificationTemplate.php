<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_templates';

    protected $fillable = [
        'company_id',
        'template_key',
        'name',
        'channel',
        'category',
        'subject',
        'body_template',
        'variables_json',
        'is_active',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'is_active' => 'boolean',
    ];
}
