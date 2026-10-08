<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToBranchAndFY;

class Payment extends Model
{
    use SoftDeletes, BelongsToBranchAndFY;

    protected $table = 'payments';

    protected $fillable = [
        'company_id',
        'branch_id',
        'financial_year',
        'payment_number',
        'payment_type', // RECEIPT, PAYMENT
        'payment_date',
        'party_type',   // CUSTOMER, SUPPLIER
        'party_id',
        'amount',
        'allocated_amount',
        'unallocated_amount',
        'currency',
        'payment_mode', // CASH, UPI, CARD, BANK_TRANSFER, CHEQUE, OTHER
        'account_id',
        'reference_number',
        'transaction_reference',
        'bank_reference',
        'utr',
        'cheque_number',
        'cheque_date',
        'cheque_bank',
        'cheque_status', // RECEIVED, DEPOSITED, CLEARED, BOUNCED, CANCELLED
        'notes',
        'status', // DRAFT, PENDING, PROCESSING, SUCCESSFUL, FAILED, CANCELLED, REFUNDED, PARTIALLY_REFUNDED
        'receipt_number',
        'bank_account_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'allocated_amount' => 'float',
        'unallocated_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }
}
