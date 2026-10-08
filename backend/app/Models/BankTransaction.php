<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'company_id',
        'branch_id',
        'bank_account_id',
        'transaction_date',
        'value_date',
        'type', // CREDIT, DEBIT
        'transaction_type', // DEPOSIT, WITHDRAWAL, TRANSFER, BANK_CHARGE, INTEREST, PAYMENT_RECEIVED, PAYMENT_MADE, CHEQUE_DEPOSIT, CHEQUE_PAYMENT, OTHER
        'reference_no',
        'reference_number',
        'description',
        'amount',
        'debit_credit', // DEBIT, CREDIT
        'balance_after',
        'source', // CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, SALE, PURCHASE, BANK_IMPORT, MANUAL, TRANSFER, CHEQUE, GATEWAY_SETTLEMENT
        'source_id',
        'is_reconciled',
        'reconciliation_status', // UNRECONCILED, MATCHED, RECONCILED, EXCLUDED
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
        'is_reconciled' => 'boolean',
        'transaction_date' => 'datetime',
        'value_date' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
