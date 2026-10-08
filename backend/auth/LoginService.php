<?php

namespace App\Auth;

use App\Models\User;
use App\Models\Company;
use App\Services\AuditLogService;

class LoginService
{
    public static function attemptLogin(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !PasswordService::verify($password, $user->password)) {
            AuditLogService::log(
                0,
                null,
                'FAILED_LOGIN',
                'Auth',
                null,
                "Failed login attempt for email: {$email}"
            );
            return [
                'success' => false,
                'code' => 401,
                'message' => 'Invalid email or password.'
            ];
        }

        if ($user->status !== 'ACTIVE') {
            AuditLogService::log(
                $user->current_company_id ?: 0,
                $user->name,
                'FAILED_LOGIN_SUSPENDED',
                'User',
                $user->id,
                "Attempted login to suspended account: {$email}"
            );
            return [
                'success' => false,
                'code' => 403,
                'message' => 'Account is suspended or deactivated. Please contact Administrator.'
            ];
        }

        if (!$user->current_company_id) {
            $compRole = \Illuminate\Database\Capsule\Manager::table('user_roles')
                ->where('user_id', $user->id)
                ->first();
            if ($compRole && !empty($compRole->company_id)) {
                $user->current_company_id = $compRole->company_id;
                $user->save();
            }
        }

        // Generate Token
        $token = SessionService::createSession($user, 'login-session');
        $user->update(['last_login_at' => date('Y-m-d H:i:s')]);

        AuditLogService::log(
            $user->current_company_id ?: 0,
            $user->name,
            'LOGIN',
            'User',
            $user->id,
            "User {$user->email} logged in successfully."
        );

        $company = Company::find($user->current_company_id);

        return [
            'success' => true,
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'permissions' => \App\Services\PermissionManager::getPermissionsForRole($user->role),
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'gstin' => $company->gstin,
                'currency' => $company->currency,
            ] : null,
        ];
    }
}
