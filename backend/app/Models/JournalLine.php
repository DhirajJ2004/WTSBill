<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class JournalLine extends Model
{
    use BelongsToTenant;

    protected $table = 'journal_lines';

    protected $fillable = [
        'company_id',
        'branch_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'party_type', // CUSTOMER, SUPPLIER
        'party_id',
        'product_id',
        'cost_center',
        'tax_category',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'party_id' => 'integer',
        'product_id' => 'integer',
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
