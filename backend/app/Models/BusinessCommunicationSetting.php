<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BusinessCommunicationSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'business_communication_settings';

    protected $fillable = [
        'company_id',
        'timezone',
        'preferred_send_time',
        'quiet_hours_start',
        'quiet_hours_end',
        'email_configured',
        'whatsapp_configured',
        'sms_configured',
        'settings_json',
    ];

    protected $casts = [
        'email_configured' => 'boolean',
        'whatsapp_configured' => 'boolean',
        'sms_configured' => 'boolean',
        'settings_json' => 'array',
    ];
}
