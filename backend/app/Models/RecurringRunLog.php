<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class RecurringRunLog extends Model
{
    use BelongsToTenant;

    protected $table = 'recurring_run_logs';

    protected $fillable = [
        'company_id',
        'template_id',
        'run_id',
        'run_date',
        'generated_entity_type',
        'generated_entity_id',
        'generation_mode',
        'status',
        'error_message',
    ];

    protected $casts = [
        'run_date' => 'date',
    ];

    public function template()
    {
        return $this->belongsTo(RecurringTemplate::class, 'template_id');
    }
}
