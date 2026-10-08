<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class DebitNote extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'debit_notes';

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'purchase_id',
        'debit_note_number',
        'debit_note_date',
        'original_purchase_number',
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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items()
    {
        return $this->hasMany(DebitNoteItem::class);
    }
}
