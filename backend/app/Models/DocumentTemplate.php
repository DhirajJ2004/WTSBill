<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class DocumentTemplate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'document_templates';

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type',
        'template_key',
        'template_name',
        'paper_size',
        'orientation',
        'brand_color',
        'accent_color',
        'font_family',
        'logo_position',
        'watermark_enabled',
        'watermark_text',
        'watermark_opacity',
        'show_bank_details',
        'show_upi_qr',
        'show_signature',
        'show_stamp',
        'show_hsn_summary',
        'show_tax_breakdown',
        'show_amount_in_words',
        'show_terms',
        'default_terms',
        'default_notes',
        'custom_css',
        'config_json',
        'is_default',
        'is_system',
        'version',
    ];

    protected $casts = [
        'watermark_enabled' => 'boolean',
        'watermark_opacity' => 'float',
        'show_bank_details' => 'boolean',
        'show_upi_qr' => 'boolean',
        'show_signature' => 'boolean',
        'show_stamp' => 'boolean',
        'show_hsn_summary' => 'boolean',
        'show_tax_breakdown' => 'boolean',
        'show_amount_in_words' => 'boolean',
        'show_terms' => 'boolean',
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'version' => 'integer',
        'config_json' => 'array',
    ];

    public function versions()
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id');
    }
}
