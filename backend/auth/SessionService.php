<?php

namespace App\Auth;

use App\Models\User;
use App\Models\PersonalAccessToken;

class SessionService
{
    public static function createSession(User $user, string $name = 'login-session'): string
    {
        return PersonalAccessToken::generateForUser($user, $name);
    }

    public static function validateSession(string $rawToken): ?PersonalAccessToken
    {
        $tokenModel = PersonalAccessToken::findToken($rawToken);
        if (!$tokenModel) {
            return null;
        }

        if ($tokenModel->expires_at) {
            $expiryTimestamp = is_numeric($tokenModel->expires_at) 
                ? (int)$tokenModel->expires_at 
                : ($tokenModel->expires_at instanceof \DateTimeInterface 
                    ? $tokenModel->expires_at->getTimestamp() 
                    : strtotime((string)$tokenModel->expires_at));
            if ($expiryTimestamp && $expiryTimestamp < time()) {
                return null;
            }
        }

        $tokenModel->update(['last_used_at' => date('Y-m-d H:i:s')]);
        return $tokenModel;
    }

    public static function revokeSession(string $rawToken): void
    {
        $hashedToken = hash('sha256', $rawToken);
        PersonalAccessToken::where('token', $hashedToken)->delete();
    }
}
