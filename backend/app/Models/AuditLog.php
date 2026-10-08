<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'correlation_id',
        'before_data_json',
        'after_data_json',
        'metadata_json',
        'ip_address',
        'user_agent',
        'status', // SUCCESS, FAILED
        'severity', // INFO, WARNING, CRITICAL
    ];

    protected $casts = [
        'before_data_json' => 'array',
        'after_data_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
