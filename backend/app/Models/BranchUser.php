<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BranchUser extends Model
{
    use BelongsToTenant;

    protected $table = 'branch_users';

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'is_default',
        'can_switch',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'can_switch' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
