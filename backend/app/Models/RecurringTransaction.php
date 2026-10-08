<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class RecurringTransaction extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'recurring_transactions';

    protected $fillable = [
        'company_id',
        'branch_id',
        'type', // SALES_INVOICE, PURCHASE_EXPENSE, PAYMENT, JOURNAL_ENTRY
        'name',
        'frequency', // DAILY, WEEKLY, MONTHLY, QUARTERLY, HALF_YEARLY, YEARLY, CUSTOM
        'interval_count',
        'month_end_policy', // LAST_VALID_DAY, LAST_DAY_OF_MONTH
        'missed_schedule_policy', // GENERATE_MISSED, GENERATE_NEXT_ONLY
        'pricing_policy', // PRESERVE_TEMPLATE_PRICE, USE_CURRENT_PRICE
        'start_date',
        'end_date',
        'next_run_at',
        'last_run_at',
        'total_occurrences',
        'remaining_occurrences',
        'status', // ACTIVE, PAUSED, COMPLETED, CANCELLED, NEEDS_ATTENTION
        'template_payload_json',
        'auto_post',
        'auto_send',
        'created_by',
    ];

    protected $casts = [
        'interval_count' => 'integer',
        'total_occurrences' => 'integer',
        'remaining_occurrences' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'date',
        'last_run_at' => 'date',
        'template_payload_json' => 'array',
        'auto_post' => 'boolean',
        'auto_send' => 'boolean',
    ];

    public function runs()
    {
        return $this->hasMany(RecurringTransactionRun::class, 'recurring_transaction_id');
    }

    public function histories()
    {
        return $this->hasMany(RecurringTransactionHistory::class, 'recurring_transaction_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
