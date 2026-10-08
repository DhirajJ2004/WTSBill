<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class EWayBill extends Model
{
    use BelongsToTenant;

    protected $table = 'e_way_bills';

    protected $fillable = [
        'company_id',
        'branch_id',
        'invoice_id',
        'ewb_number',
        'ewb_date',
        'valid_until',
        'status', // NOT_REQUIRED, ELIGIBLE, READY, SUBMITTED, GENERATED, FAILED, CANCELLED, EXPIRED
        'transport_mode', // ROAD, RAIL, AIR, SHIP
        'transporter_id',
        'transporter_name',
        'transport_doc_no',
        'transport_doc_date',
        'vehicle_no',
        'vehicle_type', // REGULAR, OVER_DIMENSIONAL
        'from_pincode',
        'to_pincode',
        'distance_km',
        'is_sandbox',
        'error_message',
        'cancel_reason',
        'cancel_remarks',
        'cancel_date',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'invoice_id' => 'integer',
        'distance_km' => 'integer',
        'is_sandbox' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(EWayBillStatusHistory::class, 'e_way_bill_id');
    }
}
