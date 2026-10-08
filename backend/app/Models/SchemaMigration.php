<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchemaMigration extends Model
{
    protected $table = 'schema_migrations';
    public $timestamps = false;

    protected $fillable = [
        'version',
        'name',
        'batch',
        'status',
        'execution_time_ms',
        'error_message',
        'applied_at',
    ];

    protected $casts = [
        'batch' => 'integer',
        'execution_time_ms' => 'integer',
        'applied_at' => 'datetime',
    ];
}
