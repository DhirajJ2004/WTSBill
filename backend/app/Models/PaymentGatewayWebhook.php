<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentGatewayWebhook extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_gateway_webhooks';

    protected $fillable = [
        'company_id',
        'provider',
        'event_id',
        'event_type',
        'signature',
        'payload_json',
        'status', // RECEIVED, PROCESSED, DUPLICATE, FAILED
        'error_message',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];
}
