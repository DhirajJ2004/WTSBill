<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class StockTransferItem extends Model
{
    use BelongsToTenant;

    protected $table = 'stock_transfer_items';

    protected $fillable = [
        'company_id',
        'transfer_id',
        'product_id',
        'quantity',
        'quantity_sent',
        'quantity_received',
        'unit',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'quantity_sent' => 'float',
        'quantity_received' => 'float',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
