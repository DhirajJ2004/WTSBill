<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankReconciliation extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'statement_start_date',
        'statement_end_date',
        'opening_balance',
        'closing_balance',
        'statement_opening_balance',
        'statement_closing_balance',
        'book_opening_balance',
        'book_closing_balance',
        'system_balance',
        'unreconciled_difference',
        'status', // OPEN, IN_PROGRESS, COMPLETED
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'system_balance' => 'float',
        'unreconciled_difference' => 'float',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function matches()
    {
        return $this->hasMany(BankReconciliationMatch::class, 'bank_reconciliation_id');
    }
}
