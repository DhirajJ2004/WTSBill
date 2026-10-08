<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Company;
use App\Auth\SessionService;
use App\Auth\WorkspaceContext;
use App\Services\PermissionManager;
use Illuminate\Database\Capsule\Manager as DB;

if (!class_exists(__NAMESPACE__ . '\\AuthorizationException')) {
    class AuthorizationException extends \Exception {
        public int $statusCode;
        public array $response;

        public function __construct(string $message = 'Authentication required', int $statusCode = 401) {
            parent::__construct($message);
            $this->statusCode = $statusCode;
            $this->response = [
                'success' => false,
                'status' => 'error',
                'message' => $message,
            ];
        }
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
    }

    public static function getFinancialYear(): ?string
    {
        if (static::$financialYear === null) {
            try { static::authenticate(); } catch (\Throwable $e) {}
        }
        return static::$financialYear;
    }

    public static function getFinancialYearRange(): ?array
    {
        $fy = static::getFinancialYear();
        if (!$fy) {
            return null;
        }
        $parts = explode('-', $fy);
        if (count($parts) !== 2) {
            return null;
        }
        $startYear = (int)$parts[0];
        return [
            'start' => "{$startYear}-04-01",
            'end' => ($startYear + 1) . "-03-31"
        ];
    }

    public static function getUser(): ?User
    {
        if (static::$currentUser === null) {
            try { static::authenticate(); } catch (\Throwable $e) {}
        }
        return static::$currentUser;
    }

    public static function getTenantId(): int
    {
        if (static::$companyId === null) {
            static::authenticate();
        }
        if (static::$companyId === null) {
            throw new AuthorizationException('Forbidden: Active company workspace context missing.', 403);
        }
        return (int)static::$companyId;
    }

    public static function validateTenantAccess(?int $requestedCompanyId): int
    {
        $tenantId = static::getTenantId();
        if ($requestedCompanyId !== null && (int)$requestedCompanyId > 0 && (int)$requestedCompanyId !== $tenantId) {
            throw new AuthorizationException("Forbidden: Cannot access resources for another company (#{$requestedCompanyId}).", 403);
        }
        return $tenantId;
    }

    public static function validateBranchAccess(?int $requestedBranchId): ?int
    {
        if ($requestedBranchId !== null && (int)$requestedBranchId > 0) {
            $user = static::getUser();
            $companyId = static::getTenantId();
            if ($user && !WorkspaceContext::verifyBranchAccess($user->id, $companyId, (int)$requestedBranchId)) {
                throw new AuthorizationException("Forbidden: User lacks access to Branch #{$requestedBranchId}.", 403);
            }
            return (int)$requestedBranchId;
        }
        return static::getBranchId();
    }

    public static function validateWarehouseAccess(?int $requestedWarehouseId): int
    {
        $companyId = static::getTenantId();
        return WorkspaceContext::getAuthorizedWarehouseId($companyId, $requestedWarehouseId);
    }

    public static function getBranchId(): ?int
    {
        if (static::$branchId === null && static::$currentUser === null) {
            try { static::authenticate(); } catch (\Throwable $e) {}
        }
        return static::$branchId;
    }

    public static function getUserRole(): ?string
    {
        if (static::$userRole === null) {
            try { static::authenticate(); } catch (\Throwable $e) {}
        }
        return static::$userRole;
    }

    public static function authenticate(): User
    {
        if (static::$currentUser !== null && static::$companyId !== null) {
            return static::$currentUser;
        }

        $tokenStr = static::extractBearerToken();
        $user = null;

        if (!empty($tokenStr)) {
            $tokenModel = SessionService::validateSession($tokenStr);
            if (!$tokenModel) {
                throw new AuthorizationException('Unauthorized: Token expired or invalid.', 401);
            }
            $user = User::find($tokenModel->user_id);
        } else {
            // Check PHP web session if running in web context
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['user']['id'])) {
                $user = User::find((int)$_SESSION['user']['id']);
            }
        }

        if (!$user || $user->status !== 'ACTIVE') {
            throw new AuthorizationException('Authentication required. Missing, expired or invalid credentials.', 401);
        }

        // Determine Active Company Context
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $reqCompanyId = $headers['X-Company-Id'] ?? $headers['x-company-id'] ?? $_SERVER['HTTP_X_COMPANY_ID'] ?? null;
        if (!$reqCompanyId && !empty($_SESSION['company_id'])) {
            $reqCompanyId = $_SESSION['company_id'];
        }
        if (!$reqCompanyId && !empty($_COOKIE['wts_company_id'])) {
            $reqCompanyId = $_COOKIE['wts_company_id'];
        }
        $companyId = $reqCompanyId ? (int)$reqCompanyId : (int)$user->current_company_id;

        if (!$companyId) {
            $authorized = WorkspaceContext::getAuthorizedCompanies($user->id);
            $companyId = !empty($authorized) ? (int)$authorized[0]['id'] : 0;
        }

        if (!$companyId) {
            throw new AuthorizationException('Forbidden: No authorized company found for this user.', 403);
        }

        // Strict Multi-Tenant Membership Verification
        if (!WorkspaceContext::verifyCompanyMembership($user->id, $companyId)) {
            throw new AuthorizationException('Forbidden: User does not belong to the requested company.', 403);
        }

        $company = Company::find($companyId);
        if (!$company) {
            throw new AuthorizationException('Forbidden: Company not found.', 403);
        }
        if ($company->status !== 'ACTIVE') {
            throw new AuthorizationException("Forbidden: Business '{$company->name}' is suspended or archived.", 403);
        }

        // Determine Active Financial Year Context
        $reqFy = $headers['X-Financial-Year'] ?? $headers['x-financial-year'] ?? $_SERVER['HTTP_X_FINANCIAL_YEAR'] ?? null;
        if (!$reqFy && !empty($_SESSION['financial_year'])) {
            $reqFy = $_SESSION['financial_year'];
        }
        if (!$reqFy && !empty($_COOKIE['wts_financial_year'])) {
            $reqFy = $_COOKIE['wts_financial_year'];
        }
        if (!$reqFy) {
            $month = (int)date('m');
            $year = (int)date('Y');
            $fyStart = $month >= 4 ? $year : $year - 1;
            $fyEnd = ($fyStart + 1) % 100;
            $reqFy = sprintf('%04d-%02d', $fyStart, $fyEnd);
        }

        $roleName = WorkspaceContext::getUserRoleForCompany($user->id, $companyId);

        // Determine and Verify Branch Context
        $reqBranchId = $headers['X-Branch-Id'] ?? $headers['x-branch-id'] ?? $_SERVER['HTTP_X_BRANCH_ID'] ?? null;
        if (!$reqBranchId && !empty($_SESSION['branch_id'])) {
            $reqBranchId = $_SESSION['branch_id'];
        }

        $branchId = null;
        $branchObj = null;
        if ($reqBranchId) {
            $candidateBranchId = (int)$reqBranchId;
            if (!WorkspaceContext::verifyBranchAccess($user->id, $companyId, $candidateBranchId)) {
                throw new AuthorizationException('Forbidden: User lacks access to the selected branch.', 403);
            }
            $branchId = $candidateBranchId;
            $branchObj = DB::table('branches')->where('id', $branchId)->where('company_id', $companyId)->first();
        } else {
            $authBranches = WorkspaceContext::getAuthorizedBranches($user->id, $companyId);
            if (!empty($authBranches)) {
                $branchId = (int)$authBranches[0]['id'];
                $branchObj = (object)$authBranches[0];
            }
        }

        $permissions = [];
        $roleRecord = DB::table('roles')->where('company_id', $companyId)->where('name', $roleName)->first();
        if ($roleRecord) {
            $permissions = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('role_permissions.role_id', $roleRecord->id)
                ->pluck('permissions.slug')
                ->toArray();
        }

        $user->current_company_id = $companyId;
        $user->role = $roleName;

        WorkspaceContext::setContext($user, $company, $branchObj, $reqFy, $roleName, $permissions);
        static::$currentUser = $user;
        static::$companyId = $companyId;
        static::$branchId = $branchId;
        static::$userRole = $roleName;
        static::$financialYear = $reqFy;

        return $user;
    }

    public static function authorize(string $module, string $action): User
    {
        $user = static::authenticate();
        $roleName = static::$userRole;

        // Owner/Admin bypass
        if (strtolower($roleName) === 'owner' || strtolower($roleName) === 'admin') {
            return $user;
        }

        if (!PermissionManager::can($user, $module, $action)) {
            throw new AuthorizationException(
                "Forbidden: Role '{$roleName}' lacks permission to perform '{$action}' on '{$module}'.",
                403
            );
        }

        return $user;
    }

    public static function extractBearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        if (isset($headers['X-Api-Token']) || isset($_SERVER['HTTP_X_API_TOKEN'])) {
            return $headers['X-Api-Token'] ?? $_SERVER['HTTP_X_API_TOKEN'];
        }

        if (isset($_GET['api_token'])) {
            return $_GET['api_token'];
        }

        return null;
    }
}
