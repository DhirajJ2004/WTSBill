<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PaymentLink extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_links';

    protected $fillable = [
        'company_id',
        'branch_id',
        'link_id',
        'invoice_id',
        'token',
        'amount',
        'original_invoice_amount',
        'is_partial_allowed',
        'min_amount',
        'status', // CREATED, ACTIVE, OPENED, PAYMENT_PENDING, PAID, EXPIRED, CANCELLED
        'expires_at',
        'description',
        'paid_at',
        'payment_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'original_invoice_amount' => 'float',
        'is_partial_allowed' => 'boolean',
        'min_amount' => 'float',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function events()
    {
        return $this->hasMany(PaymentLinkEvent::class, 'payment_link_id');
    }
}
