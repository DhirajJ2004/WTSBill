<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankTransfer extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_transfers';

    protected $fillable = [
        'company_id',
        'branch_id',
        'transfer_number',
        'from_type', // BANK, CASH
        'from_account_id',
        'to_type', // BANK, CASH
        'to_account_id',
        'amount',
        'transfer_date',
        'reference_number',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'transfer_date' => 'date:Y-m-d',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
