<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GSTDocumentSnapshot extends Model
{
    use BelongsToTenant;

    protected $table = 'gst_document_snapshots';

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type', // INVOICE, PURCHASE, CREDIT_NOTE, DEBIT_NOTE
        'document_id',
        'document_number',
        'document_date',
        'seller_gstin',
        'seller_state_code',
        'buyer_gstin',
        'buyer_state_code',
        'place_of_supply',
        'supply_type', // INTRA_STATE, INTER_STATE
        'gst_category', // B2B, B2C, B2CL, B2CS, EXP, SEZ, DEEMED_EXP, CDNR, CDNUR
        'is_reverse_charge',
        'taxable_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'cess_amount',
        'total_tax_amount',
        'total_document_value',
        'lines_snapshot_json',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'document_id' => 'integer',
        'is_reverse_charge' => 'boolean',
        'taxable_amount' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'cess_amount' => 'float',
        'total_tax_amount' => 'float',
        'total_document_value' => 'float',
    ];

    public function getLinesAttribute(): array
    {
        if (empty($this->lines_snapshot_json)) {
            return [];
        }
        return is_array($this->lines_snapshot_json) ? $this->lines_snapshot_json : (json_decode($this->lines_snapshot_json, true) ?: []);
    }
}
