<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class DocumentAuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'document_audit_logs';

    protected $fillable = [
        'company_id',
        'generated_document_id',
        'action', // GENERATED, VIEWED, DOWNLOADED, PRINTED, SHARED, EMAILED, WHATSAPP_SENT, LINK_OPENED, REVOKED
        'user_name',
        'ip_address',
        'user_agent',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
