<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class CreditNote extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'credit_notes';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'invoice_id',
        'credit_note_number',
        'credit_note_date',
        'original_invoice_number',
        'sub_total',
        'total_tax',
        'cgst_reversal',
        'sgst_reversal',
        'igst_reversal',
        'amount',
        'reason',
        'status',
        'notes',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'total_tax' => 'float',
        'cgst_reversal' => 'float',
        'sgst_reversal' => 'float',
        'igst_reversal' => 'float',
        'amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
