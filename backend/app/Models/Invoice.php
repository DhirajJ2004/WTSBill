<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToBranchAndFY;

class Invoice extends Model
{
    use SoftDeletes, BelongsToBranchAndFY;

    protected $table = 'invoices';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'invoice_number',
        'reference_po_number',
        'invoice_date',
        'due_date',
        'place_of_supply',
        'is_igst',
        'sub_total',
        'discount_rate',
        'discount_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'round_off',
        'grand_total',
        'amount_paid',
        'amount_due',
        'status',
        'payment_status',
        'payment_mode',
        'notes',
        'terms_and_conditions',
        'customer_name',
        'customer_gstin',
        'billing_address',
        'shipping_address',
        'created_by',
        'updated_by',
        'source_quotation_id',
        'source_sales_order_id',
        'source_delivery_challan_id',
    ];

    protected $casts = [
        'is_igst' => 'boolean',
        'sub_total' => 'float',
        'discount_amount' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
        'amount_paid' => 'float',
        'amount_due' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'document_id')->where('document_type', 'INVOICE');
    }
}
