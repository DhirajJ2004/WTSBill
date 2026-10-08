<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class DocumentTemplateVersion extends Model
{
    use BelongsToTenant;

    protected $table = 'document_template_versions';

    protected $fillable = [
        'company_id',
        'template_id',
        'version_number',
        'config_json',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'config_json' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
