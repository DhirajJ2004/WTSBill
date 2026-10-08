<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SupplierAddress extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_addresses';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'state_code',
        'pincode',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
