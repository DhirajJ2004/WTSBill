<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Company;
use App\Auth\LoginService;
use App\Auth\LogoutService;
use App\Auth\RegistrationService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class AuthController
{
    public function login()
    {
        $input = get_json_input();
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            return response_json(['status' => 'error', 'message' => 'Email and password are required.'], 422);
        }

        $result = LoginService::attemptLogin($email, $password);

        if (!$result['success']) {
            return response_json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response_json([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $result['access_token'],
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'user' => $result['user'],
            'company' => $result['company'],
        ]);
    }

    public function logout()
    {
        $user = AuthMiddleware::authenticate();
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $rawToken = null;

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $rawToken = $matches[1];
        }

        LogoutService::logout($user, $rawToken);

        return response_json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function register()
    {
        $input = get_json_input();
        $result = RegistrationService::register($input);

        if (!$result['success']) {
            return response_json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response_json([
            'status' => 'success',
            'message' => 'Company registration successful.',
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'user' => $result['user'],
            'company' => $result['company'],
        ], 201);
    }

    public function forgotPassword()
    {
        $input = get_json_input();
        $email = trim($input['email'] ?? '');

        if (empty($email)) {
            return response_json(['status' => 'error', 'message' => 'Email is required.'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            // Uniform generic response to prevent account harvesting / enumeration
            return response_json([
                'status' => 'success',
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);
        }

        // Generate 32-byte cryptographically secure random token
        $resetToken = bin2hex(random_bytes(32));
        DB::table('password_resets')->where('email', $email)->delete();
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => hash('sha256', $resetToken),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Construct reset link and dispatch via email/log (never expose token in API response)
        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
        $resetUrl = "{$baseUrl}/reset-password?token=" . urlencode($resetToken) . '&email=' . urlencode($email);

        error_log("[SECURITY] Password reset link generated for {$email}: {$resetUrl}");

        AuditLogService::log(
            $user->current_company_id ?: 0,
            $user->name,
            'PASSWORD_RESET_REQUESTED',
            'User',
            $user->id,
            "Password reset link dispatched for user {$user->email}."
        );

        // Security requirement: DO NOT return reset_token in API response
        return response_json([
            'status' => 'success',
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword()
    {
        $input = get_json_input();
        $email = trim($input['email'] ?? '');
        $token = $input['token'] ?? '';
        $newPassword = $input['password'] ?? '';

        if (empty($email) || empty($token) || empty($newPassword)) {
            return response_json(['status' => 'error', 'message' => 'Email, reset token, and new password are required.'], 422);
        }

        if (strlen($newPassword) < 8) {
            return response_json(['status' => 'error', 'message' => 'Password must be at least 8 characters in length.'], 422);
        }

        $hashedToken = hash('sha256', $token);
        $record = DB::table('password_resets')
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return response_json(['status' => 'error', 'message' => 'Invalid or expired password reset token.'], 422);
        }

        // Enforce 60-minute token expiry
        $createdAt = strtotime($record->created_at);
        if (!$createdAt || (time() - $createdAt) > 3600) {
            DB::table('password_resets')->where('email', $email)->delete();
            return response_json(['status' => 'error', 'message' => 'Password reset token has expired. Please request a new one.'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            DB::table('password_resets')->where('email', $email)->delete();
            return response_json(['status' => 'error', 'message' => 'User account not found.'], 404);
        }

        // Store bcrypt hash
        $user->update(['password' => password_hash($newPassword, PASSWORD_BCRYPT)]);

        // Invalidate reset token (single-use)
        DB::table('password_resets')->where('email', $email)->delete();

        // Invalidate any active session tokens for this user
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        AuditLogService::log(
            $user->current_company_id ?: 0,
            $user->name,
            'PASSWORD_RESET',
            'User',
            $user->id,
            "Password reset completed for user {$user->email}."
        );

        return response_json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    public function changePassword()
    {
        $user = AuthMiddleware::authenticate();

        $input = get_json_input();
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return response_json(['status' => 'error', 'message' => 'Current password, new password, and confirmation are required.'], 422);
        }

        if (strlen($newPassword) < 6) {
            return response_json(['status' => 'error', 'message' => 'New password must be at least 6 characters.'], 422);
        }

        if ($newPassword !== $confirmPassword) {
            return response_json(['status' => 'error', 'message' => 'New password and confirmation do not match.'], 422);
        }

        if (!password_verify($currentPassword, $user->password)) {
            return response_json(['status' => 'error', 'message' => 'Current password is incorrect.'], 403);
        }

        $user->update(['password' => password_hash($newPassword, PASSWORD_BCRYPT)]);

        // Invalidate other sessions (keep current token active)
        $currentToken = AuthMiddleware::extractBearerToken();
        if ($currentToken) {
            $currentHash = hash('sha256', $currentToken);
            DB::table('personal_access_tokens')
                ->where('user_id', $user->id)
                ->where('token', '!=', $currentHash)
                ->delete();
        }

        AuditLogService::log(
            $user->current_company_id,
            $user->name,
            'PASSWORD_CHANGE',
            'User',
            $user->id,
            "User {$user->email} changed their password."
        );

        return response_json([
            'status' => 'success',
            'message' => 'Password changed successfully. Other active sessions have been invalidated.',
        ]);
    }

    public function me()
    {
        $user = AuthMiddleware::authenticate();
        $companyId = AuthMiddleware::getTenantId();
        $branchId = AuthMiddleware::getBranchId();
        $roleName = AuthMiddleware::getUserRole();

        $company = Company::find($companyId);
        $permissions = PermissionManager::getPermissionsForRole($roleName);

        // Fetch user's accessible companies
        $companies = DB::table('user_roles')
            ->join('companies', 'user_roles.company_id', '=', 'companies.id')
            ->where('user_roles.user_id', $user->id)
            ->select('companies.id', 'companies.name', 'companies.gstin', 'companies.currency')
            ->get();

        // Fetch user's accessible branches for the active company
        $branchesQuery = DB::table('user_branches')
            ->join('branches', 'user_branches.branch_id', '=', 'branches.id')
            ->where('user_branches.user_id', $user->id)
            ->where('user_branches.company_id', $companyId);

        if (strtolower($roleName) === 'owner' || strtolower($roleName) === 'admin') {
            // Owners/Admins see all company branches
            $branches = DB::table('branches')
                ->where('company_id', $companyId)
                ->select('id', 'name', 'branch_code', 'gstin')
                ->get();
        } else {
            $branches = $branchesQuery->select('branches.id', 'branches.name', 'branches.branch_code', 'branches.gstin')->get();
        }

        $activeBranch = DB::table('branches')->where('id', $branchId)->first();

        return response_json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleName,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'permissions' => $permissions,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'gstin' => $company->gstin,
                'pan' => $company->pan,
                'city' => $company->city,
                'state' => $company->state,
                'state_code' => $company->state_code,
                'currency' => $company->currency,
            ] : null,
            'branch' => $activeBranch ? [
                'id' => $activeBranch->id,
                'name' => $activeBranch->name,
                'branch_code' => $activeBranch->branch_code,
                'gstin' => $activeBranch->gstin,
            ] : null,
            'companies' => $companies,
            'branches' => $branches,
        ]);
    }

    public function company()
    {
        $user = AuthMiddleware::authenticate();
        $companyId = AuthMiddleware::getTenantId();
        $company = Company::find($companyId);

        return response_json([
            'status' => 'success',
            'company' => $company,
        ]);
    }

    public function listCompanies()
    {
        $user = AuthMiddleware::authenticate();

        $companies = DB::table('user_roles')
            ->join('companies', 'user_roles.company_id', '=', 'companies.id')
            ->where('user_roles.user_id', $user->id)
            ->select('companies.id', 'companies.name', 'companies.gstin', 'companies.legal_name', 'companies.currency')
            ->get();

        return response_json([
            'status' => 'success',
            'companies' => $companies,
        ]);
    }

    public function selectCompany()
    {
        $user = AuthMiddleware::authenticate();
        $input = get_json_input();
        $companyId = (int)($input['company_id'] ?? 0);

        if (!$companyId) {
            return response_json(['status' => 'error', 'message' => 'Company ID is required.'], 422);
        }

        // Verify user has access to company
        $hasAccess = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();

        if (!$hasAccess) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have access to this company.'], 403);
        }

        $user->update(['current_company_id' => $companyId]);

        AuditLogService::log(
            $companyId,
            $user->name,
            'COMPANY_SWITCH',
            'User',
            $user->id,
            "User switched active business context to company ID {$companyId}."
        );

        return response_json([
            'status' => 'success',
            'message' => 'Active company context updated successfully.',
        ]);
    }

    public function listBranches()
    {
        $user = AuthMiddleware::authenticate();
        $companyId = AuthMiddleware::getTenantId();
        $roleName = AuthMiddleware::getUserRole();

        if (strtolower($roleName) === 'owner' || strtolower($roleName) === 'admin') {
            $branches = DB::table('branches')
                ->where('company_id', $companyId)
                ->select('id', 'name', 'branch_code', 'gstin')
                ->get();
        } else {
            $branches = DB::table('user_branches')
                ->join('branches', 'user_branches.branch_id', '=', 'branches.id')
                ->where('user_branches.user_id', $user->id)
                ->where('user_branches.company_id', $companyId)
                ->select('branches.id', 'branches.name', 'branches.branch_code', 'branches.gstin')
                ->get();
        }

        return response_json([
            'status' => 'success',
            'branches' => $branches,
        ]);
    }
}
