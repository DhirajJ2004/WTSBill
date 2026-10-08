<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ReportSavedView extends Model
{
    use BelongsToTenant;

    protected $table = 'report_saved_views';

    protected $fillable = [
        'company_id',
        'user_id',
        'report_key',
        'name',
        'filters_json',
        'columns_json',
        'sort_json',
        'is_default',
        'is_favorite',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'columns_json' => 'array',
        'sort_json' => 'array',
        'is_default' => 'boolean',
        'is_favorite' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
