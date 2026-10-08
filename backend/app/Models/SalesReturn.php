<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class SalesReturn extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'sales_returns';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'invoice_id',
        'return_number',
        'return_date',
        'reason',
        'stock_impact',
        'sub_total',
        'total_tax',
        'grand_total',
        'refund_amount',
        'tax_reversal_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'stock_impact' => 'boolean',
        'sub_total' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
        'refund_amount' => 'float',
        'tax_reversal_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
