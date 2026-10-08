<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CommunicationPreference extends Model
{
    use BelongsToTenant;

    protected $table = 'communication_preferences';

    protected $fillable = [
        'company_id',
        'party_type',
        'party_id',
        'email_opt_in',
        'whatsapp_opt_in',
        'sms_opt_in',
        'promotional_opt_in',
        'preferred_channel',
    ];

    protected $casts = [
        'email_opt_in' => 'boolean',
        'whatsapp_opt_in' => 'boolean',
        'sms_opt_in' => 'boolean',
        'promotional_opt_in' => 'boolean',
    ];
}
