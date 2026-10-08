<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'user_id',
        'company_id',
        'token',
        'name',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function generateForUser(User $user, string $name = 'api-token', ?string $expiresAt = null): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $companyId = $user->current_company_id;
        if (!$companyId) {
            $companyId = \Illuminate\Database\Capsule\Manager::table('user_roles')
                ->where('user_id', $user->id)
                ->value('company_id');
            if ($companyId) {
                $user->current_company_id = $companyId;
                $user->save();
            }
        }

        static::create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'token' => $hashedToken,
            'name' => $name,
            'expires_at' => $expiresAt ?? date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return $rawToken;
    }

    public static function findToken(string $rawToken): ?self
    {
        $hashedToken = hash('sha256', $rawToken);
        return static::where('token', $hashedToken)->first();
    }
}
