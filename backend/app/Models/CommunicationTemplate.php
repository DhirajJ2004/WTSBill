<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CommunicationTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'communication_templates';

    protected $fillable = [
        'company_id',
        'channel',
        'template_code',
        'name',
        'category',
        'subject',
        'body',
        'variables_json',
        'language',
        'is_active',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'is_active' => 'boolean',
    ];
}
