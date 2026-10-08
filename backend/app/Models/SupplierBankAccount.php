<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SupplierBankAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_bank_accounts';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'bank_name',
        'account_name',
        'account_number',
        'ifsc_code',
        'branch_name',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
    ];

    protected $hidden = [
        'account_number',
    ];

    protected $appends = [
        'masked_account_number',
    ];

    public function getMaskedAccountNumberAttribute()
    {
        $num = $this->account_number ?? '';
        $len = strlen($num);
        if ($len <= 4) {
            return $num;
        }
        return str_repeat('*', $len - 4) . substr($num, -4);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
