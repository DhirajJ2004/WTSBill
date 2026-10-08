<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GeneratedDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'generated_documents';

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type',
        'source_type',
        'source_id',
        'document_number',
        'document_date',
        'template_id',
        'version',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'checksum_hash',
        'snapshot_json',
        'is_frozen',
        'generated_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'version' => 'integer',
        'file_size' => 'integer',
        'is_frozen' => 'boolean',
        'snapshot_json' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function shares()
    {
        return $this->hasMany(DocumentShare::class, 'generated_document_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(DocumentAuditLog::class, 'generated_document_id');
    }
}
