<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Logger;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Repositories\BranchRepository;
use WTSBill\Services\AuditService;

/**
 * Controller for Enterprise System Settings and Tenant Organization Profile.
 */
class SettingsController
{
    private BranchRepository $branchRepo;

    public function __construct(?BranchRepository $branchRepo = null)
    {
        $this->branchRepo = $branchRepo ?? new BranchRepository();
    }

    /**
     * Settings overview page with Organization Profile and Active Branches.
     * Route: GET /settings
     */
    public function index(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userRole = strtoupper((string)(Session::get('user_role') ?? (Session::get('role') ?? (Session::getUser()['role'] ?? (Session::get('auth_user')['role'] ?? 'STAFF')))));
        $isAdmin = in_array($userRole, ['ADMIN', 'SUPERADMIN', 'OWNER'], true);

        $company = Database::selectOne(
            "SELECT * FROM `companies` WHERE id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        if (!$company) {
            $company = Database::selectOne("SELECT * FROM `companies` ORDER BY id ASC LIMIT 1") ?? [];
        }

        $branches = $this->branchRepo->findAllByCompany($companyId);
        $allUsers = Database::select(
            "SELECT id, name, email, role FROM `users` WHERE (current_company_id = ? OR current_company_id IS NULL) AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC",
            [$companyId]
        );

        $activeBranchId = (int)(Session::getBranchId() ?? 1);

        $auditLogs = [];
        $auditSummary = ['total' => 0, 'today' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0];
        if ($isAdmin) {
            $auditData = AuditService::getLogs([], 1, 20);
            $auditLogs = $auditData['rows'];
            $totalRow = Database::selectOne("SELECT COUNT(*) as c FROM audit_logs WHERE company_id = ?", [$companyId]);
            $todayRow = Database::selectOne("SELECT COUNT(*) as c FROM audit_logs WHERE company_id = ? AND DATE(created_at) = CURDATE()", [$companyId]);
            $createsRow = Database::selectOne("SELECT COUNT(*) as c FROM audit_logs WHERE company_id = ? AND action = 'CREATE'", [$companyId]);
            $updatesRow = Database::selectOne("SELECT COUNT(*) as c FROM audit_logs WHERE company_id = ? AND action IN ('UPDATE', 'SETTINGS_CHANGE')", [$companyId]);
            $deletesRow = Database::selectOne("SELECT COUNT(*) as c FROM audit_logs WHERE company_id = ? AND action = 'DELETE'", [$companyId]);

            $auditSummary['total'] = (int)($totalRow['c'] ?? 0);
            $auditSummary['today'] = (int)($todayRow['c'] ?? 0);
            $auditSummary['creates'] = (int)($createsRow['c'] ?? 0);
            $auditSummary['updates'] = (int)($updatesRow['c'] ?? 0);
            $auditSummary['deletes'] = (int)($deletesRow['c'] ?? 0);
        }

        $html = View::renderWithLayout('settings/index', [
            'title' => 'System Settings',
            'currentUri' => '/settings',
            'company' => $company,
            'branches' => $branches,
            'allUsers' => $allUsers,
            'activeBranchId' => $activeBranchId,
            'isAdmin' => $isAdmin,
            'auditLogs' => $auditLogs,
            'auditSummary' => $auditSummary,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Fetch Organization Profile data as JSON for modal editing.
     * Route: GET /api/settings/company
     */
    public function getCompany(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $company = Database::selectOne(
            "SELECT * FROM `companies` WHERE id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        if (!$company) {
            return Response::json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        return Response::json([
            'success' => true,
            'company' => $company,
        ]);
    }

    /**
     * Update Organization Profile settings.
     * Route: POST /settings/company/update
     */
    public function updateCompany(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $name = trim((string)($payload['name'] ?? ''));
        $legalName = trim((string)($payload['legal_name'] ?? '')) ?: $name;
        $gstin = strtoupper(trim((string)($payload['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($payload['pan'] ?? '')));
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $addressLine1 = trim((string)($payload['address_line1'] ?? ''));
        $addressLine2 = trim((string)($payload['address_line2'] ?? ''));
        $city = trim((string)($payload['city'] ?? ''));
        $state = trim((string)($payload['state'] ?? 'Maharashtra'));
        $stateCode = trim((string)($payload['state_code'] ?? '27'));
        $pincode = trim((string)($payload['pincode'] ?? ''));
        $msmeNo = trim((string)($payload['msme_no'] ?? ''));
        $website = trim((string)($payload['website'] ?? ''));
        $upiId = trim((string)($payload['upi_id'] ?? ''));
        $termsAndConditions = trim((string)($payload['terms_and_conditions'] ?? ''));
        $financialYearStart = trim((string)($payload['financial_year_start'] ?? '04-01'));
        $currency = trim((string)($payload['currency'] ?? 'INR'));

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'Company name is required.';
        }

        if (!empty($gstin) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            $errors['gstin'] = 'Invalid GSTIN format (must be 15 alphanumeric characters).';
        }

        if (!empty($pan) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            $errors['pan'] = 'Invalid PAN format (must be 10 characters: 5 letters, 4 digits, 1 letter).';
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address format.';
        }

        if (!empty($errors)) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => reset($errors),
                    'errors' => $errors,
                ], 422);
            }
            Session::flash('error', reset($errors));
            return Response::redirect('/settings');
        }

        // Auto-extract PAN from GSTIN if PAN is blank and GSTIN is provided
        if (empty($pan) && strlen($gstin) === 15) {
            $pan = substr($gstin, 2, 10);
        }

        // Auto-extract state code from GSTIN if state code is blank
        if (empty($stateCode) && strlen($gstin) === 15) {
            $stateCode = substr($gstin, 0, 2);
        }

        try {
            Database::execute(
                "UPDATE `companies` SET 
                    `name` = :name,
                    `legal_name` = :legal_name,
                    `gstin` = :gstin,
                    `pan` = :pan,
                    `email` = :email,
                    `phone` = :phone,
                    `address_line1` = :address_line1,
                    `address_line2` = :address_line2,
                    `city` = :city,
                    `state` = :state,
                    `state_code` = :state_code,
                    `pincode` = :pincode,
                    `currency` = :currency,
                    `financial_year_start` = :financial_year_start,
                    `msme_no` = :msme_no,
                    `website` = :website,
                    `upi_id` = :upi_id,
                    `terms_and_conditions` = :terms_and_conditions,
                    `updated_at` = NOW()
                 WHERE `id` = :id",
                [
                    'name' => $name,
                    'legal_name' => $legalName,
                    'gstin' => $gstin ?: null,
                    'pan' => $pan ?: null,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'address_line1' => $addressLine1 ?: null,
                    'address_line2' => $addressLine2 ?: null,
                    'city' => $city ?: null,
                    'state' => $state ?: null,
                    'state_code' => $stateCode ?: '27',
                    'pincode' => $pincode ?: null,
                    'currency' => $currency ?: 'INR',
                    'financial_year_start' => $financialYearStart ?: '04-01',
                    'msme_no' => $msmeNo ?: null,
                    'website' => $website ?: null,
                    'upi_id' => $upiId ?: null,
                    'terms_and_conditions' => $termsAndConditions ?: null,
                    'id' => $companyId,
                ]
            );

            // Update session company name if changed
            Session::set('company_name', $name);

            // Record audit trail entry
            AuditService::log(
                'UPDATE',
                'COMPANY',
                $companyId,
                "Updated Organization Profile '{$legalName}' (GSTIN: " . ($gstin ?: 'None') . ")",
                null,
                ['name' => $name, 'legal_name' => $legalName, 'gstin' => $gstin],
                'INFO'
            );

            Logger::info("Company profile ID {$companyId} updated successfully.");

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Tenant organization profile updated successfully.',
                ]);
            }

            Session::flash('success', 'Tenant organization profile updated successfully.');
            return Response::redirect('/settings');
        } catch (\Throwable $e) {
            Logger::error("Failed to update company settings: " . $e->getMessage());

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update company settings: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update company settings: ' . $e->getMessage());
            return Response::redirect('/settings');
        }
    }

    /**
     * Fetch audit logs filtered via query params.
     * Route: GET /settings/audit-logs or GET /api/settings/audit-logs
     */
    public function auditLogs(Request $req): Response
    {
        $this->ensureAdmin();

        $filters = [
            'search' => trim((string)($req->query('search') ?? '')),
            'action' => trim((string)($req->query('action') ?? '')),
            'entity_type' => trim((string)($req->query('entity_type') ?? '')),
            'severity' => trim((string)($req->query('severity') ?? '')),
            'date_from' => trim((string)($req->query('date_from') ?? '')),
            'date_to' => trim((string)($req->query('date_to') ?? '')),
        ];

        $page = max(1, (int)($req->query('page') ?? 1));
        $perPage = min(100, max(5, (int)($req->query('per_page') ?? 20)));

        $data = AuditService::getLogs($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data' => $data['rows'],
            'meta' => [
                'total' => $data['total'],
                'page' => $data['page'],
                'per_page' => $data['per_page'],
                'last_page' => $data['last_page'],
            ],
        ]);
    }

    /**
     * Export audit logs to CSV for compliance and administrative reporting.
     * Route: GET /settings/audit-logs/export
     */
    public function exportAuditLogs(Request $req): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $rows = Database::select(
            "SELECT id, created_at, user_name, action, entity_type, entity_id, description, ip_address, status, severity 
             FROM audit_logs 
             WHERE company_id = ? 
             ORDER BY id DESC LIMIT 2000",
            [$companyId]
        );

        $csv = "Log ID,Timestamp,User,Action,Entity Type,Entity ID,Description,IP Address,Status,Severity\n";
        foreach ($rows as $r) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $r['id'],
                '"' . str_replace('"', '""', (string)$r['created_at']) . '"',
                '"' . str_replace('"', '""', (string)$r['user_name']) . '"',
                '"' . str_replace('"', '""', (string)$r['action']) . '"',
                '"' . str_replace('"', '""', (string)$r['entity_type']) . '"',
                $r['entity_id'] ?: '',
                '"' . str_replace('"', '""', (string)$r['description']) . '"',
                '"' . str_replace('"', '""', (string)$r['ip_address']) . '"',
                '"' . str_replace('"', '""', (string)$r['status']) . '"',
                '"' . str_replace('"', '""', (string)$r['severity']) . '"'
            );
        }

        return new Response(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"',
            ]
        );
    }

    private function ensureAdmin(): void
    {
        $userRole = strtoupper((string)(Session::get('user_role') ?? (Session::get('role') ?? (Session::getUser()['role'] ?? (Session::get('auth_user')['role'] ?? 'STAFF')))));
        if (!in_array($userRole, ['ADMIN', 'SUPERADMIN', 'OWNER'], true)) {
            throw new \WTSBill\Core\Exceptions\HttpException(403, 'Unauthorized. Administrator permissions required.');
        }
    }

    private function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
