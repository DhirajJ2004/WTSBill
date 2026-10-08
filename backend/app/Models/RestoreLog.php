<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreLog extends Model
{
    protected $table = 'restore_logs';

    protected $fillable = [
        'company_id',
        'backup_record_id',
        'safety_backup_id',
        'restore_mode',
        'status',
        'initiated_by',
        'ip_address',
        'checksum_verified',
        'schema_compatible',
        'tables_restored_json',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'checksum_verified' => 'boolean',
        'schema_compatible' => 'boolean',
        'tables_restored_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function backupRecord()
    {
        return $this->belongsTo(BackupRecord::class, 'backup_record_id');
    }

    public function safetyBackup()
    {
        return $this->belongsTo(BackupRecord::class, 'safety_backup_id');
    }
}
