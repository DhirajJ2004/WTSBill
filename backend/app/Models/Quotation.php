<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class Quotation extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'quotations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'reference_no',
        'billing_address',
        'shipping_address',
        'sub_total',
        'discount_amount',
        'taxable_value',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'round_off',
        'grand_total',
        'status',
        'notes',
        'terms',
        'converted_to_invoice',
        'converted_invoice_id',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'discount_amount' => 'float',
        'taxable_value' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'total_tax' => 'float',
        'round_off' => 'float',
        'grand_total' => 'float',
        'converted_to_invoice' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
