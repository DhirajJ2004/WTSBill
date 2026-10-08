<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Tag extends Model
{
    use BelongsToTenant;

    protected $table = 'tags';

    protected $fillable = [
        'company_id',
        'name',
        'color',
    ];
}
