<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Cheque extends Model
{
    use BelongsToTenant;

    protected $table = 'cheques';

    protected $fillable = [
        'company_id',
        'branch_id',
        'cheque_type', // RECEIVED, ISSUED
        'cheque_number',
        'cheque_date',
        'amount',
        'party_type', // CUSTOMER, SUPPLIER, OTHER
        'party_id',
        'party_name',
        'payee',
        'bank_name',
        'bank_account_id', // Company Bank Account (For issued, or for deposited received)
        'deposit_bank_id',
        'received_date',
        'issue_date',
        'deposit_date',
        'clearance_date',
        'bounce_date',
        'bounce_reason',
        'bounce_charges',
        'status', // RECEIVED, PREPARED, ISSUED, DEPOSITED, PRESENTED, CLEARED, BOUNCED, CANCELLED
        'reference_number',
        'notes',
        'payment_id',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'bounce_charges' => 'float',
        'cheque_date' => 'date:Y-m-d',
        'received_date' => 'date:Y-m-d',
        'issue_date' => 'date:Y-m-d',
        'deposit_date' => 'date:Y-m-d',
        'clearance_date' => 'date:Y-m-d',
        'bounce_date' => 'date:Y-m-d',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function depositBank()
    {
        return $this->belongsTo(BankAccount::class, 'deposit_bank_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function events()
    {
        return $this->hasMany(ChequeEvent::class, 'cheque_id');
    }
}
