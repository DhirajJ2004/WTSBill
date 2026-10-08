<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class ChartOfAccount extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'company_id',
        'branch_id',
        'parent_account_id',
        'account_code',
        'account_name',
        'account_type', // ASSET, LIABILITY, EQUITY, INCOME, EXPENSE
        'account_subtype', // CASH, BANK, ACCOUNTS_RECEIVABLE, INVENTORY, FIXED_ASSETS, ACCOUNTS_PAYABLE, GST_PAYABLE, CAPITAL, RETAINED_EARNINGS, SALES, COGS, OPERATING_EXPENSES, etc.
        'nature', // DEBIT, CREDIT
        'opening_balance',
        'opening_balance_type',
        'is_system_account',
        'is_active',
        'description',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'parent_account_id' => 'integer',
        'opening_balance' => 'float',
        'is_system_account' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getAccountTypeAttribute($value)
    {
        return ucfirst(strtolower($value ?? ''));
    }

    public function setAccountTypeAttribute($value)
    {
        $this->attributes['account_type'] = strtoupper($value ?? '');
    }

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    /**
     * Helper to determine normal balance nature (DEBIT for Assets/Expenses, CREDIT for Liabilities/Equity/Income)
     */
    public function getNormalNature(): string
    {
        if ($this->nature) {
            return strtoupper($this->nature);
        }
        $type = strtoupper($this->account_type);
        return in_array($type, ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT';
    }
}
