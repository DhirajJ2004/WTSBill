<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Batch extends Model
{
    use BelongsToTenant;

    protected $table = 'batches';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'batch_number',
        'mfg_date',
        'expiry_date',
        'purchase_rate',
        'quantity',
        'status', // ACTIVE, EXPIRED, DEPLETED
    ];

    protected $casts = [
        'quantity' => 'float',
        'purchase_rate' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
