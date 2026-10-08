<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToBranchAndFY;

class Purchase extends Model
{
    use SoftDeletes, BelongsToBranchAndFY;

    protected $table = 'purchases';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'purchase_order_id',
        'purchase_number',
        'vendor_invoice_number',
        'purchase_date',
        'due_date',
        'reference_no',
        'place_of_supply',
        'sub_total',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'freight_amount',
        'freight_taxable',
        'additional_charges',
        'additional_charges_taxable',
        'discount_rate',
        'discount_amount',
        'round_off',
        'grand_total',
        'amount_paid',
        'amount_due',
        'status',
        'payment_status',
        'notes',
        'internal_remarks',
        'supplier_name',
        'supplier_gstin',
        'billing_address',
        'shipping_address',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sub_total' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'total_tax' => 'float',
        'freight_amount' => 'float',
        'freight_taxable' => 'boolean',
        'additional_charges' => 'float',
        'additional_charges_taxable' => 'boolean',
        'discount_rate' => 'float',
        'discount_amount' => 'float',
        'round_off' => 'float',
        'grand_total' => 'float',
        'amount_paid' => 'float',
        'amount_due' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(SupplierBillAttachment::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'document_id')->where('document_type', 'PURCHASE_INVOICE');
    }
}

