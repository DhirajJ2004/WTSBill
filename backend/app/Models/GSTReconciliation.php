<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GSTReconciliation extends Model
{
    use BelongsToTenant;

    protected $table = 'gst_reconciliation';

    protected $fillable = [
        'company_id',
        'financial_year',
        'period_month',
        'return_type', // GSTR2B, GSTR2A, GSTR1
        'status', // IN_PROGRESS, COMPLETED
        'total_portal_records',
        'total_books_records',
        'matched_count',
        'partial_match_count',
        'mismatch_count',
        'books_only_count',
        'portal_only_count',
        'imported_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'total_portal_records' => 'integer',
        'total_books_records' => 'integer',
        'matched_count' => 'integer',
        'partial_match_count' => 'integer',
        'mismatch_count' => 'integer',
        'books_only_count' => 'integer',
        'portal_only_count' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(GSTReconciliationItem::class, 'reconciliation_id');
    }
}
