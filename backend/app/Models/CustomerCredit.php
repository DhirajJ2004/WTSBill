<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerCredit extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_credits';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'credit_type', // OVERPAYMENT, ADVANCE, CREDIT_NOTE, REFUND_ADJUSTMENT
        'source_id',
        'amount',
        'applied_amount',
        'remaining_amount',
        'status', // ACTIVE, FULLY_APPLIED, CANCELLED
    ];

    protected $casts = [
        'amount' => 'float',
        'applied_amount' => 'float',
        'remaining_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
