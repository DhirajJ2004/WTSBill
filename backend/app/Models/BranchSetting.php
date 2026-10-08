<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BranchSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'branch_settings';

    protected $fillable = [
        'company_id',
        'branch_id',
        'invoice_prefix',
        'quotation_prefix',
        'purchase_prefix',
        'receipt_prefix',
        'default_warehouse_id',
        'logo_url',
        'terms_conditions',
        'bank_account_id',
        'settings_json',
    ];

    protected $casts = [
        'settings_json' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}
