<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class DocumentShare extends Model
{
    use BelongsToTenant;

    protected $table = 'document_shares';

    protected $fillable = [
        'company_id',
        'generated_document_id',
        'share_token',
        'channel', // LINK, EMAIL, WHATSAPP, DOWNLOAD
        'recipient_name',
        'recipient_contact',
        'allow_download',
        'allow_print',
        'password_hash',
        'access_count',
        'max_access_count',
        'expires_at',
        'is_revoked',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'allow_download' => 'boolean',
        'allow_print' => 'boolean',
        'access_count' => 'integer',
        'max_access_count' => 'integer',
        'expires_at' => 'datetime',
        'is_revoked' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function isValid(): bool
    {
        if ($this->is_revoked) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_access_count && $this->access_count >= $this->max_access_count) return false;
        return true;
    }
}
