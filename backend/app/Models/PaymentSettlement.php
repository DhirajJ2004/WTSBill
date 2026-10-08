<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentSettlement extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_settlements';

    protected $fillable = [
        'company_id',
        'settlement_id',
        'provider',
        'settlement_date',
        'gross_amount',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'bank_account_id',
        'bank_transaction_id',
        'status', // PENDING, SETTLED, FAILED
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'fee_amount' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
        'settlement_date' => 'date:Y-m-d',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function bankTransaction()
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
