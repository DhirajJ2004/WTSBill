<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'company_id',
        'branch_id',
        'bank_name',
        'account_name',
        'account_number',
        'account_type', // CURRENT, SAVINGS, CASH_CREDIT, OD, OTHER
        'ifsc_code',
        'ifsc',
        'branch_name',
        'opening_balance',
        'opening_balance_date',
        'ledger_account_id',
        'currency',
        'current_balance',
        'is_active',
        'is_primary',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'current_balance' => 'float',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
    ];

    protected $appends = [
        'masked_account_number',
    ];

    public function getMaskedAccountNumberAttribute(): string
    {
        $num = (string)$this->account_number;
        if (strlen($num) <= 4) {
            return $num;
        }
        $last4 = substr($num, -4);
        return 'XXXX XXXX ' . $last4;
    }

    public function ledgerAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'ledger_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function reconciliations()
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }
}
