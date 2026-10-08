<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EInvoiceStatusHistory extends Model
{
    protected $table = 'e_invoice_status_history';

    protected $fillable = [
        'e_invoice_id',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'e_invoice_id' => 'integer',
    ];

    public function eInvoice()
    {
        return $this->belongsTo(EInvoice::class, 'e_invoice_id');
    }
}
