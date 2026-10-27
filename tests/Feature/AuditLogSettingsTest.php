<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SettingsController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuditService;

/**
 * Feature Test: Enterprise Audit Logs in Settings for Administrators.
 * Validates role-based isolation (strictly admin-only), tab rendering,
 * query filtering, CSV export, automatic event recording, and 403 enforcement for staff.
 */
class AuditLogSettingsTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $controller = new SettingsController();
        $testLogId = 0;

        try {
            // -----------------------------------------------------------------
            // 1. NON-ADMIN ACCESS ENFORCEMENT (Role: STAFF)
            // -----------------------------------------------------------------
            Session::setTenantContext(1, 1);
            Session::set('user_role', 'STAFF');
            Session::set('role', 'STAFF');
            Session::set('auth_user', [
                'id' => 99,
                'name' => 'Regular Staff Member',
                'email' => 'staff@wtsbill.com',
                'role' => 'STAFF',
            ]);

            // Staff visiting /settings should NOT see the Audit Trail tab
            $staffReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/settings'], null);
            $staffRes = $controller->index($staffReq);
            $staffHtml = $staffRes->getContent();

            $assert(
                'AUDIT-01',
                'Non-administrator staff visiting /settings does NOT see the Audit Trail tab or section',
                !str_contains($staffHtml, '<button type="button" id="tab-btn-audit"') && !str_contains($staffHtml, '<div id="settings-sec-audit"')
            );

            // Staff requesting /api/settings/audit-logs receives 403 Forbidden
            $staffBlocked = false;
            try {
                $staffApiReq = new Request([], [], [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/api/settings/audit-logs',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
                ], null);
                $controller->auditLogs($staffApiReq);
            } catch (\WTSBill\Core\Exceptions\HttpException $e) {
                $staffBlocked = ($e->getStatusCode() === 403);
            }

            $assert(
                'AUDIT-02',
                'Non-administrator staff request to /api/settings/audit-logs is rejected with HTTP 403 Forbidden',
                $staffBlocked
            );

            // Staff requesting /settings/audit-logs/export receives 403 Forbidden
            $staffExportBlocked = false;
            try {
                $staffExpReq = new Request([], [], [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/settings/audit-logs/export',
                ], null);
                $controller->exportAuditLogs($staffExpReq);
            } catch (\WTSBill\Core\Exceptions\HttpException $e) {
                $staffExportBlocked = ($e->getStatusCode() === 403);
            }

            $assert(
                'AUDIT-03',
                'Non-administrator staff request to /settings/audit-logs/export is rejected with HTTP 403 Forbidden',
                $staffExportBlocked
            );

            // -----------------------------------------------------------------
            // 2. ADMINISTRATOR PRIVILEGED ACCESS (Role: ADMIN)
            // -----------------------------------------------------------------
            Session::clear();
            Session::setTenantContext(1, 1);
            Session::set('user_role', 'ADMIN');
            Session::set('role', 'ADMIN');
            Session::set('auth_user', [
                'id' => 1,
                'name' => 'Super Administrator',
                'email' => 'admin@wtsbill.com',
                'role' => 'ADMIN',
            ]);

            // Admin visiting /settings sees the Audit Trail tab and section
            $adminReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/settings'], null);
            $adminRes = $controller->index($adminReq);
            $adminHtml = $adminRes->getContent();

            $assert(
                'AUDIT-04',
                'Administrator visiting /settings renders Audit Trail tab button and audit section',
                str_contains($adminHtml, '<button type="button" id="tab-btn-audit"') && str_contains($adminHtml, '<div id="settings-sec-audit"')
            );

            // -----------------------------------------------------------------
            // 3. AUDIT SERVICE LOGGING & PERSISTENCE
            // -----------------------------------------------------------------
            $testLogId = AuditService::log(
                'UPDATE',
                'SETTINGS',
                1,
                'Test Admin modified GST Configuration for Compliance Verification',
                ['tax_mode' => 'standard'],
                ['tax_mode' => 'composition'],
                'INFO'
            );

            $assert(
                'AUDIT-05',
                'AuditService::log records entry in audit_logs table with action, entity, and correlation ID',
                $testLogId > 0
            );

            // -----------------------------------------------------------------
            // 4. ADMIN API ENDPOINT QUERY & FILTERING
            // -----------------------------------------------------------------
            $adminApiReq = new Request([
                'search' => 'Compliance Verification',
                'action' => 'UPDATE',
                'page' => '1',
                'per_page' => '10'
            ], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/settings/audit-logs?search=Compliance+Verification&action=UPDATE',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
            ], null);

            $adminApiRes = $controller->auditLogs($adminApiReq);
            $apiData = json_decode($adminApiRes->getContent(), true);

            $assert(
                'AUDIT-06',
                'GET /api/settings/audit-logs returns HTTP 200 with JSON rows and pagination metadata',
                $adminApiRes->getStatusCode() === 200 &&
                ($apiData['success'] ?? false) === true &&
                count($apiData['data'] ?? []) >= 1 &&
                isset($apiData['meta']['total'])
            );

            // -----------------------------------------------------------------
            // 5. AUTOMATIC LOGGING ON COMPANY PROFILE UPDATE
            // -----------------------------------------------------------------
            $compUpdateReq = new Request([], [
                '_csrf_token' => Session::getCsrfToken(),
                'name' => 'Wis Technosavvy Pvt Ltd',
                'legal_name' => 'Wis Technosavvy Private Limited',
                'gstin' => '27AADCW7577N1ZE',
                'pan' => 'AADCW7577N',
                'state' => 'Maharashtra',
                'state_code' => '27',
                'city' => 'Pune',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/settings/company/update',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
            ], null);

            $controller->updateCompany($compUpdateReq);

            $autoLogged = Database::selectOne(
                "SELECT id, action, entity_type, description FROM audit_logs WHERE company_id = 1 AND action = 'UPDATE' AND entity_type = 'COMPANY' ORDER BY id DESC LIMIT 1"
            );

            $assert(
                'AUDIT-07',
                'Updating Organization Profile automatically records an immutable audit log entry',
                !empty($autoLogged) && str_contains($autoLogged['description'], 'Organization Profile')
            );

            // -----------------------------------------------------------------
            // 6. CSV AUDIT LOG EXPORT
            // -----------------------------------------------------------------
            $exportReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/settings/audit-logs/export',
            ], null);

            $exportRes = $controller->exportAuditLogs($exportReq);
            $csvContent = $exportRes->getContent();

            $assert(
                'AUDIT-08',
                'GET /settings/audit-logs/export returns text/csv attachment with valid audit log data columns',
                $exportRes->getStatusCode() === 200 &&
                str_contains($exportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
                str_contains($csvContent, 'Log ID,Timestamp,User,Action,Entity Type')
            );

        } finally {
            // Clean up test audit log entries
            if ($testLogId > 0) {
                Database::delete('audit_logs', 'id = :id', ['id' => $testLogId]);
            }
            Database::delete('audit_logs', "description LIKE '%Compliance Verification%'");
        }
    }
}
