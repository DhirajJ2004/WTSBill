<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class RecurringTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'recurring_templates';

    protected $fillable = [
        'company_id',
        'branch_id',
        'template_number',
        'transaction_type',
        'party_type',
        'party_id',
        'title',
        'payload_json',
        'amount',
        'frequency',
        'month_end_policy',
        'custom_cron',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'generation_mode',
        'status',
        'version',
        'created_by',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'amount' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'last_run_date' => 'date',
        'version' => 'integer',
    ];

    public function runs()
    {
        return $this->hasMany(RecurringRunLog::class, 'template_id');
    }
}
