<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ReportAuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'report_audit_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'user_name',
        'report_key',
        'action', // GENERATED, EXPORTED_CSV, EXPORTED_EXCEL, EXPORTED_PDF, PRINTED, VIEW_SAVED
        'filters_json',
        'ip_address',
    ];

    protected $casts = [
        'filters_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
