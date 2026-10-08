<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankReconciliationMatch extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_reconciliation_matches';

    protected $fillable = [
        'company_id',
        'bank_reconciliation_id',
        'reconciliation_id',
        'bank_statement_row_id',
        'bank_transaction_id',
        'matched_amount',
        'match_confidence', // EXACT, HIGH_CONFIDENCE, MANUAL
        'confidence_score',
        'matched_by',
    ];

    protected $casts = [
        'matched_amount' => 'float',
    ];

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function statementRow()
    {
        return $this->belongsTo(BankStatementRow::class, 'bank_statement_row_id');
    }

    public function transaction()
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
