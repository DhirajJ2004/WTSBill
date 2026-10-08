<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLinkEvent extends Model
{
    protected $table = 'payment_link_events';

    protected $fillable = [
        'payment_link_id',
        'event_type', // CREATED, OPENED, ATTEMPTED, PAID, EXPIRED, CANCELLED
        'ip_address',
        'user_agent',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function paymentLink()
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id');
    }
}
