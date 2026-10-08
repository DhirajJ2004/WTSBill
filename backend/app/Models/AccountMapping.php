<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AccountMapping extends Model
{
    use BelongsToTenant;

    protected $table = 'account_mappings';

    protected $fillable = [
        'company_id',
        'mapping_key',
        'account_id',
        'description',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'account_id' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
