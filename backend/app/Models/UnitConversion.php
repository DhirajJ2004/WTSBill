<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class UnitConversion extends Model
{
    use BelongsToTenant;

    protected $table = 'unit_conversions';

    protected $fillable = [
        'company_id',
        'from_unit_id',
        'to_unit_id',
        'conversion_factor',
    ];

    protected $casts = [
        'from_unit_id' => 'integer',
        'to_unit_id' => 'integer',
        'conversion_factor' => 'float',
    ];

    public function fromUnit()
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
