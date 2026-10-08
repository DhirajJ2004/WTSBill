<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChequeEvent extends Model
{
    protected $table = 'cheque_events';

    protected $fillable = [
        'company_id',
        'cheque_id',
        'event_type', // RECEIVED, DEPOSITED, CLEARED, BOUNCED, CANCELLED
        'from_status',
        'to_status',
        'event_date',
        'notes',
        'performed_by',
        'created_by',
        'details_json',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function cheque()
    {
        return $this->belongsTo(Cheque::class, 'cheque_id');
    }
}
