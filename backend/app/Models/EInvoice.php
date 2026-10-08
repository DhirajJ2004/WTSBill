<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class EInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'e_invoices';

    protected $fillable = [
        'company_id',
        'branch_id',
        'invoice_id',
        'irn',
        'ack_no',
        'ack_date',
        'signed_invoice',
        'signed_qr_data',
        'qr_code_url',
        'status', // NOT_REQUIRED, ELIGIBLE, VALIDATION_FAILED, READY, SUBMITTED, GENERATED, FAILED, CANCELLED
        'is_sandbox',
        'error_message',
        'cancel_reason',
        'cancel_remarks',
        'cancel_date',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'invoice_id' => 'integer',
        'is_sandbox' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(EInvoiceStatusHistory::class, 'e_invoice_id');
    }
}
