<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BankStatementImport extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_statement_imports';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'file_name',
        'import_format',
        'mapping_config_json',
        'total_rows',
        'imported_rows',
        'duplicate_rows',
        'status',
    ];

    protected $casts = [
        'mapping_config_json' => 'array',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'duplicate_rows' => 'integer',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function rows()
    {
        return $this->hasMany(BankStatementRow::class, 'import_id');
    }
}
