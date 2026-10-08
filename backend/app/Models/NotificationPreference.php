<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationPreference extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'company_id',
        'user_id',
        'category',
        'in_app_enabled',
        'email_enabled',
        'whatsapp_enabled',
        'sms_enabled',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
