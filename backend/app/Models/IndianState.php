<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndianState extends Model
{
    protected $table = 'indian_states';

    protected $fillable = [
        'name',
        'code',
    ];
}
