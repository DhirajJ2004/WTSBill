<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class RecurringTransactionHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'recurring_transaction_histories';

    protected $fillable = [
        'company_id',
        'recurring_transaction_id',
        'action',
        'performed_by_user_id',
        'performed_by_name',
        'details_json',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
