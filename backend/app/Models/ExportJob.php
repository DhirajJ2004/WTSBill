<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ExportJob extends Model
{
    use BelongsToTenant;

    protected $table = 'export_jobs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'data_type',
        'export_format',
        'columns_json',
        'filters_json',
        'file_name',
        'file_path',
        'file_size',
        'download_token',
        'download_token_expires_at',
        'status',
        'error_message',
        'total_records',
        'correlation_id',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'columns_json' => 'array',
        'filters_json' => 'array',
        'file_size' => 'integer',
        'total_records' => 'integer',
        'download_token_expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
