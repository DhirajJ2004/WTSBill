<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentGatewaySettlement extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_gateway_settlements';

    protected $fillable = [
        'company_id',
        'provider',
        'settlement_id',
        'settlement_date',
        'gross_amount',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'bank_account_id',
        'status', // MATCHED, PARTIALLY_MATCHED, UNMATCHED
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'gross_amount' => 'float',
        'fee_amount' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
