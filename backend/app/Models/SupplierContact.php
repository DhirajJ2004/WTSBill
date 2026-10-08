<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SupplierContact extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_contacts';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'name',
        'phone',
        'email',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
