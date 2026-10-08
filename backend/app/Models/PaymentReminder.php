<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReminder extends Model
{
    protected $table = 'payment_reminders';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'invoice_id',
        'channel', // EMAIL, WHATSAPP, SMS
        'sent_date',
        'days_overdue',
        'outstanding_amount',
        'status', // PENDING, SENT, FAILED, DISMISSED
        'message_template_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'days_overdue' => 'integer',
        'outstanding_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
