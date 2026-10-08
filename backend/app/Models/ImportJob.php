<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ImportJob extends Model
{
    use BelongsToTenant;

    protected $table = 'import_jobs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'data_type',
        'file_name',
        'file_type',
        'file_path',
        'file_size',
        'total_rows',
        'valid_rows',
        'warning_rows',
        'error_rows',
        'duplicate_rows',
        'processed_rows',
        'duplicate_action',
        'mapping_config_json',
        'validation_summary_json',
        'status',
        'error_message',
        'error_report_path',
        'correlation_id',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'warning_rows' => 'integer',
        'error_rows' => 'integer',
        'duplicate_rows' => 'integer',
        'processed_rows' => 'integer',
        'mapping_config_json' => 'array',
        'validation_summary_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function rowLogs()
    {
        return $this->hasMany(ImportRowLog::class, 'import_job_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
