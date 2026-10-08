<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    protected $table = 'backup_records';

    protected $fillable = [
        'company_id',
        'backup_type',
        'file_name',
        'file_path',
        'file_size',
        'checksum',
        'storage_location',
        'app_version',
        'schema_version',
        'status',
        'error_message',
        'metadata_json',
        'is_encrypted',
        'retention_days',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_encrypted' => 'boolean',
        'retention_days' => 'integer',
        'metadata_json' => 'array',
        'expires_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
