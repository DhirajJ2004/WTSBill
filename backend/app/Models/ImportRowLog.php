<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRowLog extends Model
{
    protected $table = 'import_row_logs';

    protected $fillable = [
        'import_job_id',
        'row_index',
        'status', // VALID, WARNING, ERROR, DUPLICATE
        'raw_data_json',
        'parsed_data_json',
        'issues_json',
    ];

    protected $casts = [
        'row_index' => 'integer',
        'raw_data_json' => 'array',
        'parsed_data_json' => 'array',
        'issues_json' => 'array',
    ];

    public function importJob()
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
