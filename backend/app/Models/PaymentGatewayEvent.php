<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayEvent extends Model
{
    protected $table = 'payment_gateway_events';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload_json',
        'processed_at',
        'status', // PROCESSED, IGNORED, FAILED
    ];

    protected $casts = [
        'payload_json' => 'array',
        'processed_at' => 'datetime',
    ];
}
