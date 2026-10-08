<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class RecurringInvoice extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'recurring_invoices';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'template_name',
        'frequency',
        'start_date',
        'end_date',
        'next_invoice_date',
        'last_generated_date',
        'status',
        'payment_terms',
        'sub_total',
        'total_tax',
        'grand_total',
        'notes',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(RecurringInvoiceItem::class);
    }
}
