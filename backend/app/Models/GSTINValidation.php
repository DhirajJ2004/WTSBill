<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GSTINValidation extends Model
{
    protected $table = 'gstin_validations';

    protected $fillable = [
        'company_id',
        'gstin',
        'legal_name',
        'trade_name',
        'state_code',
        'state_name',
        'taxpayer_type',
        'registration_status',
        'is_format_valid',
        'is_portal_verified',
        'source', // LOCAL_CHECKSUM, SANDBOX_SIMULATION, PORTAL_API
        'response_reference',
        'validated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_format_valid' => 'boolean',
        'is_portal_verified' => 'boolean',
    ];
}
