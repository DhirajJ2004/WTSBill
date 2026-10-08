<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberSetting extends Model
{
    protected $table = 'document_number_settings';

    protected $fillable = [
        'company_id',
        'branch_id',
        'financial_year',
        'document_type',
        'prefix',
        'starting_number',
        'current_number',
    ];
}
