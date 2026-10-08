<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class MigrationProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'migration_profiles';

    protected $fillable = [
        'company_id',
        'name',
        'data_type',
        'source_software',
        'mapping_json',
        'created_by',
    ];

    protected $casts = [
        'mapping_json' => 'array',
    ];
}
