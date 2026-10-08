<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CustomerAdvance extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_advances';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'payment_id',
        'advance_date',
        'total_amount',
        'allocated_amount',
        'remaining_amount',
        'status', // ACTIVE, FULLY_UTILIZED, REFUNDED
        'notes',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'total_amount' => 'float',
        'allocated_amount' => 'float',
        'remaining_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
