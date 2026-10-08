<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchAndFY;

class Expense extends Model
{
    use BelongsToBranchAndFY;

    protected $table = 'expenses';

    protected $fillable = [
        'company_id',
        'branch_id',
        'expense_number',
        'category',
        'payee',
        'expense_date',
        'amount',
        'tax_amount',
        'payment_mode',
        'gstin',
        'is_itc_eligible',
        'reference_no',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
        'tax_amount' => 'float',
        'is_itc_eligible' => 'boolean',
    ];
}
