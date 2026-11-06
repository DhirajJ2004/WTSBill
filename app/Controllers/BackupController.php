<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Services\BackupService;
use WTSBill\Services\AuditCleanupService;

/**
 * Controller for Enterprise Backup Management, Safe Disaster Recovery, and Audit Purge.
 */
class BackupController
{
    /**
     * Trigger on-demand manual backup creation.
     * Route: POST /settings/backups/create
     */
    public function create(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Session::getUser() ?? Session::get('auth_user') ?? ['name' => 'Administrator'];
        $userName = (string)($user['name'] ?? 'Administrator');

        $input = $req->isJson() ? $req->json() : $req->all();
        $type = (string)($input['type'] ?? 'MANUAL');
        $retentionDays = max(1, min(365, (int)($input['retention_days'] ?? 30)));
        $encryptionPassword = !empty($input['encryption_password']) ? (string)$input['encryption_password'] : null;

        try {
            $res = BackupService::createBackup($companyId, $type, $userName, $retentionDays, $encryptionPassword);
            return Response::json([
                'success' => true,
                'backup' => $res,
                'message' => "Backup archive {$res['file_name']} created and verified successfully.",
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Backup creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger automated weekly backup with duplicate run prevention.
     * Route: POST /settings/backups/scheduled-run
     */
    public function runScheduled(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Session::getUser() ?? Session::get('auth_user') ?? ['name' => 'Administrator'];
        $userName = (string)($user['name'] ?? 'Administrator');

        $input = $req->isJson() ? $req->json() : $req->all();
        $force = !empty($input['force']);

        try {
            $res = BackupService::runScheduledWeeklyBackup($companyId, $force, $userName);
            return Response::json([
                'success' => true,
                'data' => $res,
                'message' => $res['message'] ?? 'Weekly backup processed successfully.',
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Scheduled backup execution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Securely download backup ZIP archive.
     * Route: GET /settings/backups/{id}/download
     */
    public function download(Request $req, int $id): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $backup = Database::selectOne(
            "SELECT * FROM backup_records WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );

        if (!$backup || !file_exists($backup['file_path'])) {
            return new Response('Backup file not found or has been removed.', 404);
        }

        $filePath = $backup['file_path'];
        $fileName = $backup['file_name'];
        $fileSize = (int)$backup['file_size'];

        $content = file_get_contents($filePath);
        return new Response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => (string)$fileSize,
        ]);
    }

    /**
     * Restore database from backup with pre-restore safety snapshot.
     * Route: POST /settings/backups/{id}/restore
     */
    public function restore(Request $req, mixed $id = null): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Session::getUser() ?? Session::get('auth_user') ?? ['name' => 'Administrator'];
        $userName = (string)($user['name'] ?? 'Administrator');
        $ip = $req->ip() ?? '127.0.0.1';

        $backupId = (int)($id ?? 0);
        if ($backupId <= 0) {
            $input = $req->isJson() ? $req->json() : $req->all();
            $backupId = (int)($input['backup_id'] ?? $input['id'] ?? 0);
        }

        $input = $req->isJson() ? $req->json() : $req->all();
        $confirm = $input['confirm'] ?? false;
        if (!$confirm) {
            return Response::json([
                'success' => false,
                'message' => 'Explicit confirmation is required to restore a database backup.',
            ], 422);
        }

        $encryptionPassword = !empty($input['encryption_password']) ? (string)$input['encryption_password'] : null;

        try {
            $result = BackupService::restoreBackup($backupId, $companyId, $userName, $ip, $encryptionPassword);
            return Response::json([
                'success' => true,
                'result' => $result,
                'message' => 'Database successfully restored. Safety snapshot was created before applying changes.',
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Restore operation aborted: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete backup archive with confirmation.
     * Route: POST /settings/backups/{id}/delete
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Session::getUser() ?? Session::get('auth_user') ?? ['name' => 'Administrator'];
        $userName = (string)($user['name'] ?? 'Administrator');

        $backupId = (int)($id ?? 0);
        if ($backupId <= 0) {
            $input = $req->isJson() ? $req->json() : $req->all();
            $backupId = (int)($input['backup_id'] ?? $input['id'] ?? 0);
        }

        try {
            $result = BackupService::deleteBackup($backupId, $companyId, $userName);
            return Response::json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Run audit log cleanup after verified backup.
     * Route: POST /settings/audit-logs/cleanup
     */
    public function cleanupAudit(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $user = Session::getUser() ?? Session::get('auth_user') ?? ['name' => 'Administrator'];
        $userName = (string)($user['name'] ?? 'Administrator');

        $input = $req->isJson() ? $req->json() : $req->all();
        $retentionDays = max(1, min(365, (int)($input['retention_days'] ?? 7)));

        $result = AuditCleanupService::runWeeklyCleanup($companyId, $retentionDays, $userName);
        if ($result['success']) {
            return Response::json([
                'success' => true,
                'data' => $result,
                'message' => $result['message'],
            ]);
        }

        return Response::json([
            'success' => false,
            'message' => 'Audit cleanup failed: ' . ($result['error'] ?? 'Unknown error'),
        ], 500);
    }

    /**
     * Get backup history and next scheduled backup.
     * Route: GET /api/settings/backups
     */
    public function listBackups(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $backups = BackupService::getHistory($companyId);
        $next = BackupService::getNextScheduled($companyId);
        $last = BackupService::getLastSuccessful($companyId);
        $cleanups = AuditCleanupService::getCleanupHistory($companyId);

        return Response::json([
            'success' => true,
            'backups' => $backups,
            'last_successful' => $last,
            'next_scheduled' => $next,
            'frequency' => 'Every 7 Days (Automatic)',
            'cleanup_reports' => $cleanups,
        ]);
    }

    private function ensureAdmin(): void
    {
        $userRole = strtoupper((string)(Session::get('user_role') ?? (Session::get('role') ?? (Session::getUser()['role'] ?? (Session::get('auth_user')['role'] ?? 'STAFF')))));
        if (!in_array($userRole, ['ADMIN', 'SUPERADMIN', 'OWNER'], true)) {
            throw new \WTSBill\Core\Exceptions\HttpException(403, 'Unauthorized. Administrator permissions required.');
        }
    }
}
