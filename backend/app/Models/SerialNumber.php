<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SerialNumber extends Model
{
    use BelongsToTenant;

    protected $table = 'serial_numbers';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'purchase_id',
        'invoice_id',
        'serial_number',
        'status', // AVAILABLE, RESERVED, SOLD, RETURNED, DAMAGED, LOST
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
