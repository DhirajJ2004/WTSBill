<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HsnSacMaster extends Model
{
    protected $table = 'hsn_sac_master';

    protected $fillable = [
        'company_id',
        'code',
        'type', // HSN, SAC
        'description',
        'default_gst_rate',
        'default_cess_rate',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'default_gst_rate' => 'float',
        'default_cess_rate' => 'float',
        'is_active' => 'boolean',
    ];
}
