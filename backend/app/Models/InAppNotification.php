<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class InAppNotification extends Model
{
    use BelongsToTenant;

    protected $table = 'in_app_notifications';

    protected $fillable = [
        'company_id',
        'user_id',
        'category',
        'type',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'action_url',
        'read_status',
        'priority',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
