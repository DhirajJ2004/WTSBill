<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class DocumentNumberingConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'document_numbering_configs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type',
        'prefix',
        'suffix',
        'starting_number',
        'current_number',
        'number_padding',
        'reset_frequency',
        'is_active',
    ];

    protected $casts = [
        'starting_number' => 'integer',
        'current_number' => 'integer',
        'number_padding' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
