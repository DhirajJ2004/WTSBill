<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SupplierGroup extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_groups';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'status',
    ];
}
