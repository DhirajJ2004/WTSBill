<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GSTConfiguration extends Model
{
    use BelongsToTenant;

    protected $table = 'gst_configurations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'gst_registered',
        'gstin',
        'legal_business_name',
        'trade_name',
        'pan',
        'registered_state',
        'state_code',
        'tax_registration_type', // REGISTERED_REGULAR, REGISTERED_COMPOSITION, UNREGISTERED, CONSUMER
        'is_composition',
        'default_place_of_supply',
        'einvoice_applicable',
        'einvoice_threshold',
        'eway_bill_applicable',
        'eway_threshold',
        'filing_frequency', // MONTHLY, QUARTERLY
        'api_environment', // SANDBOX, PRODUCTION
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'gst_registered' => 'boolean',
        'is_composition' => 'boolean',
        'einvoice_applicable' => 'boolean',
        'einvoice_threshold' => 'float',
        'eway_bill_applicable' => 'boolean',
        'eway_threshold' => 'float',
    ];
}
