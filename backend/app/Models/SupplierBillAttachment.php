<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBillAttachment extends Model
{
    protected $table = 'supplier_bill_attachments';

    protected $fillable = [
        'company_id',
        'purchase_id',
        'file_name',
        'file_type',
        'file_path',
        'uploaded_by',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
