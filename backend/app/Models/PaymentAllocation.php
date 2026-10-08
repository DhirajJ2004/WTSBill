<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $table = 'payment_allocations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'payment_id',
        'document_type', // INVOICE, PURCHASE_INVOICE, CREDIT_NOTE, DEBIT_NOTE, ADVANCE, REFUND
        'document_id',
        'allocated_amount',
        'allocation_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'float',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'document_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'document_id');
    }

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'document_id');
    }

    public function debitNote()
    {
        return $this->belongsTo(DebitNote::class, 'document_id');
    }
}
