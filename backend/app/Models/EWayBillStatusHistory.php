<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EWayBillStatusHistory extends Model
{
    protected $table = 'e_way_bill_status_history';

    protected $fillable = [
        'e_way_bill_id',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'e_way_bill_id' => 'integer',
    ];

    public function eWayBill()
    {
        return $this->belongsTo(EWayBill::class, 'e_way_bill_id');
    }
}
