<?php

namespace App\Middleware;

use App\Auth\WorkspaceContext;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

class TenantMiddleware
{
    /**
     * Enforce and validate multi-tenant context on every protected request.
     * Never trust unvalidated browser parameters.
     *
     * @param bool $isApi
     * @return array Array containing validated tenant context ['company_id', 'branch_id', 'financial_year']
     * @throws AuthorizationException
     */
    public static function handle(bool $isApi = false): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            if ($isApi) {
                throw new AuthorizationException('Authentication required to establish tenant context.', 401);
            }
            header('Location: ' . (function_exists('url') ? url('/login') : '/login'));
            exit();
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];

        // 1. Resolve & Validate Company ID
        $requestedCompanyId = $headers['X-Company-Id'] ?? $headers['x-company-id'] ?? $_SERVER['HTTP_X_COMPANY_ID'] ?? $_GET['company_id'] ?? $_POST['company_id'] ?? $_SESSION['company_id'] ?? $_COOKIE['wts_company_id'] ?? null;
        
        $companyId = $requestedCompanyId !== null ? (int)$requestedCompanyId : null;

        if ($companyId !== null && $companyId > 0) {
            if (!WorkspaceContext::verifyCompanyMembership($userId, $companyId)) {
                if ($isApi) {
                    throw new AuthorizationException("Forbidden: User is not authorized to access Company #{$companyId}.", 403);
                }
                http_response_code(403);
                echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>You do not have permission to access Company #{$companyId}.</p><p><a href='" . (function_exists('url') ? url('/select-company') : '/select-company') . "'>Switch Company</a></p></body></html>";
                exit();
            }
        } else {
            $authorizedCompanies = WorkspaceContext::getAuthorizedCompanies($userId);
            if (empty($authorizedCompanies)) {
                if ($isApi) {
                    throw new AuthorizationException('Forbidden: No authorized companies found for this user.', 403);
                }
                http_response_code(403);
                echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>No active company accounts found for your profile.</p></body></html>";
                exit();
            }
            $companyId = (int)$authorizedCompanies[0]['id'];
        }

        $company = Company::find($companyId);
        if (!$company || (isset($company->status) && $company->status !== 'ACTIVE')) {
            if ($isApi) {
                throw new AuthorizationException("Forbidden: Company #{$companyId} is inactive or deleted.", 403);
            }
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>Company #{$companyId} is suspended or inaccessible.</p></body></html>";
            exit();
        }

        // 2. Resolve & Validate Branch ID
        $requestedBranchId = $headers['X-Branch-Id'] ?? $headers['x-branch-id'] ?? $_SERVER['HTTP_X_BRANCH_ID'] ?? $_GET['branch_id'] ?? $_POST['branch_id'] ?? $_SESSION['branch_id'] ?? $_COOKIE['wts_branch_id'] ?? null;
        
        $branchId = null;
        if ($requestedBranchId !== null && (int)$requestedBranchId > 0) {
            $candidateBranchId = (int)$requestedBranchId;
            if (!WorkspaceContext::verifyBranchAccess($userId, $companyId, $candidateBranchId)) {
                if ($isApi) {
                    throw new AuthorizationException("Forbidden: User lacks access to Branch #{$candidateBranchId}.", 403);
                }
                http_response_code(403);
                echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>You do not have access to Branch #{$candidateBranchId}.</p></body></html>";
                exit();
            }
            $branchId = $candidateBranchId;
        } else {
            $authorizedBranches = WorkspaceContext::getAuthorizedBranches($userId, $companyId);
            if (!empty($authorizedBranches)) {
                $branchId = (int)$authorizedBranches[0]['id'];
            }
        }

        $branchObj = $branchId ? DB::table('branches')->where('id', $branchId)->where('company_id', $companyId)->first() : null;

        // 3. Resolve & Validate Financial Year
        $requestedFY = $headers['X-Financial-Year'] ?? $headers['x-financial-year'] ?? $_SERVER['HTTP_X_FINANCIAL_YEAR'] ?? $_GET['financial_year'] ?? $_POST['financial_year'] ?? $_SESSION['financial_year'] ?? $_COOKIE['wts_financial_year'] ?? null;
        
        $financialYear = '2026-2027';
        if (!empty($requestedFY) && preg_match('/^\d{4}-\d{2,4}$/', $requestedFY)) {
            $financialYear = $requestedFY;
        } else {
            $month = (int)date('m');
            $year = (int)date('Y');
            $fyStart = $month >= 4 ? $year : $year - 1;
            $fyEnd = ($fyStart + 1) % 100;
            $financialYear = sprintf('%04d-%02d', $fyStart, $fyEnd);
        }

        // 4. Update session & context
        $_SESSION['company_id'] = $companyId;
        $_SESSION['company_name'] = $company->name;
        $_SESSION['branch_id'] = $branchId;
        $_SESSION['financial_year'] = $financialYear;

        $user = User::find($userId);
        if ($user) {
            $userRole = WorkspaceContext::getUserRoleForCompany($userId, $companyId);
            $perms = WorkspaceContext::getUserPermissionsForCompany($userId, $companyId);
            WorkspaceContext::setContext($user, $company, $branchObj, $financialYear, $userRole, $perms);
            
            if (class_exists('App\\Http\\Middleware\\AuthMiddleware')) {
                \App\Http\Middleware\AuthMiddleware::setContext($user, $companyId, $branchId, $userRole, $financialYear);
            }
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year' => $financialYear
        ];
    }

    /**
     * IDOR Guard: Verify that a given database record belongs to the active tenant.
     *
     * @param object|array $record
     * @param int|null $activeCompanyId
     * @throws AuthorizationException
     */
    public static function validateRecordTenant($record, ?int $activeCompanyId = null): void
    {
        $tenantId = $activeCompanyId ?? WorkspaceContext::getCompanyId();
        $recordCompanyId = is_array($record) ? ($record['company_id'] ?? null) : ($record->company_id ?? null);

        if ($recordCompanyId !== null && (int)$recordCompanyId !== (int)$tenantId) {
            throw new AuthorizationException("Forbidden: Target record does not belong to active company workspace.", 403);
        }
    }
}
