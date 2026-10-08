<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankStatementRow extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_statement_rows';

    protected $fillable = [
        'company_id',
        'import_id',
        'bank_account_id',
        'row_date',
        'transaction_date',
        'description',
        'reference_number',
        'debit',
        'credit',
        'debit_amount',
        'credit_amount',
        'amount',
        'balance',
        'match_status', // UNMATCHED, MATCHED, PARTIALLY_MATCHED, IGNORED
        'duplicate_flag',
        'is_duplicate',
    ];

    protected $casts = [
        'row_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
        'duplicate_flag' => 'boolean',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function import()
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }
}
