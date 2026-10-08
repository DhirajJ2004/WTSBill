<?php

namespace App\Middleware;

use App\Auth\WorkspaceContext;
use App\Models\User;
use App\Models\Company;
use Exception;

class AuthorizationException extends Exception
{
    public int $statusCode;
    public array $response;

    public function __construct(string $message = 'Authentication required.', int $statusCode = 401)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = [
            'status' => 'error',
            'message' => $message,
            'code' => $statusCode
        ];
    }
}

class AuthMiddleware
{
    protected static ?User $currentUser = null;
    protected static ?int $companyId = null;
    protected static ?int $branchId = null;
    protected static ?string $userRole = null;
    protected static ?string $financialYear = null;

    public static function setContext(?User $user, ?int $companyId, ?int $branchId = null, ?string $userRole = null, ?string $financialYear = null): void
    {
        static::$currentUser = $user;
        static::$companyId = $companyId;
        static::$branchId = $branchId;
        static::$userRole = $userRole;
        if ($financialYear) {
            static::$financialYear = $financialYear;
        }
        if (class_exists('App\\Http\\Middleware\\AuthMiddleware')) {
            \App\Http\Middleware\AuthMiddleware::setContext($user, $companyId, $branchId, $userRole, $financialYear);
        }
    }

    public static function getTenantId(): int
    {
        if (static::$companyId !== null) {
            return (int)static::$companyId;
        }
        if (class_exists('App\\Http\\Middleware\\AuthMiddleware')) {
            return \App\Http\Middleware\AuthMiddleware::getTenantId();
        }
        return (int)($_SESSION['company_id'] ?? 1);
    }

    public static function getUser(): ?User
    {
        return static::$currentUser ?? (class_exists('App\\Http\\Middleware\\AuthMiddleware') ? \App\Http\Middleware\AuthMiddleware::getUser() : null);
    }

    public static function getBranchId(): ?int
    {
        return static::$branchId ?? (class_exists('App\\Http\\Middleware\\AuthMiddleware') ? \App\Http\Middleware\AuthMiddleware::getBranchId() : null);
    }

    public static function getFinancialYear(): ?string
    {
        return static::$financialYear ?? (class_exists('App\\Http\\Middleware\\AuthMiddleware') ? \App\Http\Middleware\AuthMiddleware::getFinancialYear() : '2026-2027');
    }

    public static function getUserRole(): ?string
    {
        return static::$userRole ?? (class_exists('App\\Http\\Middleware\\AuthMiddleware') ? \App\Http\Middleware\AuthMiddleware::getUserRole() : 'Admin');
    }

    public static function handle(bool $isApi = false): ?User
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

        // 1. Inactivity Session Timeout Check (7200 seconds / 2 hours)
        $authConfig = file_exists(__DIR__ . '/../../config/auth.php') ? require __DIR__ . '/../../config/auth.php' : ['session_lifetime' => 7200];
        $lifetime = $authConfig['session_lifetime'] ?? 7200;

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
            $_SESSION = [];
            @session_destroy();
            if ($isApi) {
                throw new AuthorizationException('Session expired due to inactivity. Please log in again.', 401);
            }
            header('Location: ' . (function_exists('url') ? url('/login?expired=1') : '/login?expired=1'));
            exit();
        }

        // 2. Identify User from Session or Bearer Token
        $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
        $user = null;

        if ($userId) {
            $user = User::find($userId);
        }

        // Check Bearer Token if API request
        if (!$user && $isApi) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
                $token = $m[1];
                $tokenRecord = \Illuminate\Database\Capsule\Manager::table('personal_access_tokens')
                    ->where('token', hash('sha256', $token))
                    ->first();
                if ($tokenRecord) {
                    $user = User::find($tokenRecord->tokenable_id);
                }
            }
        }

        if (!$user || (isset($user->status) && $user->status !== 'ACTIVE')) {
            if ($isApi) {
                throw new AuthorizationException('Authentication required to access this resource.', 401);
            }
            header('Location: ' . (function_exists('url') ? url('/login') : '/login'));
            exit();
        }

        $_SESSION['last_activity'] = time();

        // 3. Establish Tenant & Workspace Context
        $companyId = (int)($_SESSION['company_id'] ?? $user->current_company_id ?? 1);
        $company = Company::find($companyId);
        
        $branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null;
        $branch = $branchId ? \Illuminate\Database\Capsule\Manager::table('branches')->where('id', $branchId)->first() : null;
        
        $financialYear = $_SESSION['financial_year'] ?? (function_exists('current_financial_year') ? current_financial_year() : '2026-2027');
        $role = $user->role ?? 'Admin';

        static::setContext($user, $companyId, $branchId, $role, $financialYear);
        WorkspaceContext::setContext($user, $company ?: new Company(), $branch, $financialYear, $role);

        return $user;
    }
}
