<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentRefund extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_refunds';

    protected $fillable = [
        'company_id',
        'branch_id',
        'financial_year',
        'payment_id',
        'party_type',
        'party_id',
        'refund_number',
        'refund_date',
        'amount',
        'reason',
        'payment_mode',
        'refund_mode',
        'bank_account_id',
        'reference_no',
        'status', // REQUESTED, PROCESSING, COMPLETED, FAILED, CANCELLED
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'refund_date' => 'date',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
