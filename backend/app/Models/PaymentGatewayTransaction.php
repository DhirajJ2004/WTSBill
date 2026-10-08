<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentGatewayTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_gateway_transactions';

    protected $fillable = [
        'company_id',
        'payment_id',
        'payment_link_id',
        'provider',
        'gateway_order_id',
        'gateway_payment_id',
        'amount',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'status', // PENDING, SUCCESS, FAILED, REFUNDED
        'raw_payload_json',
    ];

    protected $casts = [
        'amount' => 'float',
        'fee_amount' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
        'raw_payload_json' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function paymentLink()
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id');
    }
}
