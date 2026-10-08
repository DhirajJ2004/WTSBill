<?php

namespace App\Services;

use App\Auth\WorkspaceContext;
use App\Models\Company;
use App\Models\User;
use App\Middleware\AuthorizationException;
use App\Automation\Email\EmailService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class AuthService
{
    /**
     * Start session with secure cookie parameters.
     */
    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
            session_set_cookie_params([
                'lifetime' => 7200,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            @session_start();
        }
    }

    /**
     * Authenticate user with password_verify and session regeneration.
     */
    public static function login(string $identifier, string $password, bool $remember = false): array
    {
        self::startSecureSession();

        $identifier = trim($identifier);
        $password = trim($password);

        if (empty($identifier) || empty($password)) {
            return ['success' => false, 'message' => 'Please enter both your email/username and password.'];
        }

        $dbUser = DB::table('users')->where('email', $identifier)->first();

        if (!$dbUser || !password_verify($password, $dbUser->password)) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (isset($dbUser->status) && $dbUser->status !== 'ACTIVE' && isset($dbUser->is_active) && !$dbUser->is_active) {
            return ['success' => false, 'message' => 'Your account has been deactivated. Please contact your administrator.'];
        }

        // Session regeneration against fixation attacks
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $userObj = [
            'id' => (int)$dbUser->id,
            'name' => $dbUser->name,
            'email' => $dbUser->email,
            'role' => $dbUser->role ?? 'ADMIN',
            'avatar' => strtoupper(substr($dbUser->name, 0, 1) . substr(strrchr($dbUser->name, ' ') ?: $dbUser->name, 1, 1))
        ];

        $_SESSION['user'] = $userObj;
        $_SESSION['user_id'] = (int)$dbUser->id;
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_activity'] = time();

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        if (!headers_sent()) {
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                setcookie('wts_remember', $dbUser->id . ':' . $rememberToken, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                setcookie('wts_remember', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }

        // Update last login timestamp
        try {
            DB::table('users')->where('id', $dbUser->id)->update(['last_login_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {}

        return [
            'success' => true,
            'user' => $userObj,
            'redirect' => function_exists('url') ? url('/select-company') : '/select-company'
        ];
    }

    /**
     * Terminate user session and clear tenant cookies.
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION = [];
        @session_destroy();

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        if (!headers_sent()) {
            foreach (['wts_company_id', 'wts_branch_id', 'wts_financial_year', 'wts_remember'] as $cName) {
                setcookie($cName, '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params["path"],
                    'domain' => $params["domain"],
                    'secure' => $params["secure"],
                    'httponly' => $params["httponly"],
                    'samesite' => $params["samesite"] ?? 'Lax'
                ]);
            }
        }
    }

    /**
     * Initiate password reset process.
     */
    public static function forgotPassword(string $email): array
    {
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please provide a valid email address.'];
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $rawToken = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);

            DB::table('password_resets')->where('email', $email)->delete();
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => $hashedToken,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $resetUrl = (function_exists('url') ? url('/reset-password') : '/reset-password') . '?token=' . urlencode($rawToken) . '&email=' . urlencode($email);

            if (class_exists('App\\Automation\\Email\\EmailService')) {
                try {
                    $provider = EmailService::getProvider();
                    if ($provider->isConfigured()) {
                        $subject = "Password Reset Request - WTSBill ERP";
                        $body = "Hello {$user->name},\n\nA request was made to reset the password for your WTSBill account.\n\nClick the link below:\n{$resetUrl}\n\nThis link will expire in 60 minutes.";
                        $msg = EmailService::queueEmail(
                            (int)($user->current_company_id ?: 1),
                            $email,
                            'PASSWORD_RESET',
                            ['user_name' => $user->name, 'reset_url' => $resetUrl],
                            'USER',
                            (int)$user->id,
                            $subject,
                            $body
                        );
                        EmailService::processSend($msg);
                    }
                } catch (\Throwable $e) {
                    error_log("[FORGOT PASSWORD EMAIL] " . $e->getMessage());
                }
            }

            if (class_exists('App\\Services\\AuditLogService')) {
                AuditLogService::log(
                    (int)($user->current_company_id ?: 0),
                    $user->name,
                    'PASSWORD_RESET_REQUESTED',
                    'User',
                    (int)$user->id,
                    "Password reset initiated for {$email}."
                );
            }
        }

        return [
            'success' => true,
            'message' => 'If an account exists for this email, password reset instructions have been processed.'
        ];
    }

    /**
     * Reset password with bcrypt hash and token validation.
     */
    public static function resetPassword(string $email, string $rawToken, string $newPassword, string $confirmation): array
    {
        $email = trim($email);
        $rawToken = trim($rawToken);

        if (empty($email) || empty($rawToken)) {
            return ['success' => false, 'message' => 'Missing password reset token or email address.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters in length.'];
        }

        if ($newPassword !== $confirmation) {
            return ['success' => false, 'message' => 'Passwords do not match. Please verify your entries.'];
        }

        $hashedToken = hash('sha256', $rawToken);
        $record = DB::table('password_resets')
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return ['success' => false, 'message' => 'This password reset link is invalid or has already been used.'];
        }

        $createdAt = strtotime($record->created_at);
        if (!$createdAt || (time() - $createdAt) > 3600) {
            DB::table('password_resets')->where('email', $email)->delete();
            return ['success' => false, 'message' => 'This password reset link has expired (valid for 60 minutes).'];
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return ['success' => false, 'message' => 'User account could not be found.'];
        }

        // Apply new bcrypt password hash
        $user->update([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT)
        ]);

        // Invalidate reset token and any existing personal access tokens
        DB::table('password_resets')->where('email', $email)->delete();
        try {
            DB::table('personal_access_tokens')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {}

        if (class_exists('App\\Services\\AuditLogService')) {
            AuditLogService::log(
                (int)($user->current_company_id ?: 0),
                $user->name,
                'PASSWORD_RESET_COMPLETED',
                'User',
                (int)$user->id,
                "Password reset completed for {$email}."
            );
        }

        return ['success' => true, 'message' => 'Your password has been reset successfully. Please sign in with your new password.'];
    }

    /**
     * Select Company, Branch, and Financial Year Workspace Context.
     */
    public static function selectWorkspace(int $userId, int $companyId, ?int $branchId = null, ?string $financialYear = null): array
    {
        self::startSecureSession();

        if ($userId <= 0) {
            throw new AuthorizationException('Authentication required to select workspace.', 401);
        }

        if (!WorkspaceContext::verifyCompanyMembership($userId, $companyId)) {
            throw new AuthorizationException("Forbidden: User #{$userId} is not authorized to access Company #{$companyId}.", 403);
        }

        $branches = WorkspaceContext::getAuthorizedBranches($userId, $companyId);
        if ($branchId !== null && $branchId > 0) {
            if (!WorkspaceContext::verifyBranchAccess($userId, $companyId, $branchId)) {
                throw new AuthorizationException("Forbidden: User lacks access to Branch #{$branchId}.", 403);
            }
        } else {
            $branchId = !empty($branches) ? (int)$branches[0]['id'] : null;
        }

        $company = Company::find($companyId);
        if (!$company || (isset($company->status) && $company->status !== 'ACTIVE')) {
            throw new AuthorizationException("Forbidden: Company #{$companyId} is inactive or deleted.", 403);
        }

        if (empty($financialYear) || !preg_match('/^\d{4}-\d{2,4}$/', $financialYear)) {
            $month = (int)date('m');
            $year = (int)date('Y');
            $fyStart = $month >= 4 ? $year : $year - 1;
            $fyEnd = ($fyStart + 1) % 100;
            $financialYear = sprintf('%04d-%02d', $fyStart, $fyEnd);
        }

        $userRole = WorkspaceContext::getUserRoleForCompany($userId, $companyId) ?: 'ADMIN';
        $perms = WorkspaceContext::getUserPermissionsForCompany($userId, $companyId);
        $branchObj = $branchId ? DB::table('branches')->where('id', $branchId)->where('company_id', $companyId)->first() : null;

        $_SESSION['company_id'] = $companyId;
        $_SESSION['company_name'] = $company->name;
        $_SESSION['company_gstin'] = $company->gstin ?? '';
        $_SESSION['branch_id'] = $branchId;
        $_SESSION['financial_year'] = $financialYear;
        $_SESSION['user_role'] = $userRole;
        $_SESSION['permissions'] = $perms;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        if (!headers_sent()) {
            setcookie('wts_company_id', (string)$companyId, ['expires' => time() + 86400 * 30, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
            setcookie('wts_branch_id', (string)$branchId, ['expires' => time() + 86400 * 30, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
            setcookie('wts_financial_year', (string)$financialYear, ['expires' => time() + 86400 * 30, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
        }

        $user = User::find($userId);
        if ($user) {
            WorkspaceContext::setContext($user, $company, $branchObj, $financialYear, $userRole, $perms);
            if (class_exists('App\\Http\\Middleware\\AuthMiddleware')) {
                \App\Http\Middleware\AuthMiddleware::setContext($user, $companyId, $branchId, $userRole, $financialYear);
            }
        }

        return [
            'success' => true,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year' => $financialYear,
            'redirect' => function_exists('url') ? url('/dashboard') : '/dashboard'
        ];
    }
}
