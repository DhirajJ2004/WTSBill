<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class JournalEntry extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'branch_id',
        'financial_year',
        'journal_number',
        'entry_number',
        'entry_date',
        'entry_type', // SALE, PURCHASE, RECEIPT, PAYMENT, EXPENSE, SALES_RETURN, PURCHASE_RETURN, REFUND, STOCK_ADJUSTMENT, OPENING_BALANCE, MANUAL
        'reference_type',
        'reference_id',
        'description',
        'narration',
        'status', // DRAFT, POSTED, REVERSED
        'total_debit',
        'total_credit',
        'is_system_generated',
        'original_journal_id',
        'reversal_journal_id',
        'reversal_reason',
        'created_by',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'total_debit' => 'float',
        'total_credit' => 'float',
        'is_system_generated' => 'boolean',
        'original_journal_id' => 'integer',
        'reversal_journal_id' => 'integer',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function originalJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'original_journal_id');
    }

    public function reversalJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_id');
    }
}
