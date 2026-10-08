<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class RecurringTransactionRun extends Model
{
    use BelongsToTenant;

    protected $table = 'recurring_transaction_runs';

    protected $fillable = [
        'company_id',
        'recurring_transaction_id',
        'occurrence_date',
        'scheduled_at',
        'executed_at',
        'status', // SUCCESS, FAILED, SKIPPED
        'generated_entity_type',
        'generated_entity_id',
        'error_message',
        'attempt_count',
    ];

    protected $casts = [
        'occurrence_date' => 'date',
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }
}
