<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GSTReconciliationItem extends Model
{
    use BelongsToTenant;

    protected $table = 'gst_reconciliation_items';

    protected $fillable = [
        'company_id',
        'reconciliation_id',
        'gstin',
        'party_name',
        'invoice_number',
        'invoice_date',
        'books_taxable',
        'portal_taxable',
        'books_tax',
        'portal_tax',
        'taxable_diff',
        'tax_diff',
        'cgst_diff',
        'sgst_diff',
        'igst_diff',
        'cess_diff',
        'match_status', // MATCHED, PARTIAL_MATCH, MISMATCH, BOOKS_ONLY, PORTAL_ONLY, EXCLUDED
        'action_taken', // ACCEPT, REJECT, REVIEWED, EXCLUDE, NONE
        'action_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'reconciliation_id' => 'integer',
        'books_taxable' => 'float',
        'portal_taxable' => 'float',
        'books_tax' => 'float',
        'portal_tax' => 'float',
        'taxable_diff' => 'float',
        'tax_diff' => 'float',
        'cgst_diff' => 'float',
        'sgst_diff' => 'float',
        'igst_diff' => 'float',
        'cess_diff' => 'float',
    ];

    public function reconciliation()
    {
        return $this->belongsTo(GSTReconciliation::class, 'reconciliation_id');
    }
}
