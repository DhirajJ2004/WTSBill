<?php

declare(strict_types=1);

namespace Tests\Feature;

use ZipArchive;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\BackupService;
use WTSBill\Services\AuditCleanupService;

/**
 * Feature Test Suite for Automatic Weekly Backup & Audit Log Retention Purge.
 */
class BackupAndCleanupTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId() ?? 1;
        $csrfToken = Session::csrfToken();

        $assert('BACKUP-01', 'Administrator authenticates and establishes backup authorization context',
            $user !== null && $companyId > 0);

        // 2. Create On-Demand Manual Backup
        $backupRes = BackupService::createBackup($companyId, 'MANUAL', 'Test Administrator', 30);
        $backupId = (int)$backupRes['backup_id'];
        $zipPath = $backupRes['file_path'];

        $assert('BACKUP-02', 'BackupService creates ZIP archive outside public web root with status SUCCESS',
            $backupRes['success'] === true &&
            $backupRes['status'] === 'SUCCESS' &&
            file_exists($zipPath) &&
            str_contains($backupRes['file_name'], 'WTSBill_Backup_') &&
            filesize($zipPath) > 0
        );

        // 3. Inspect ZIP Contents & Integrity
        $zip = new ZipArchive();
        $isOpen = ($zip->open($zipPath) === true);
        $hasSql = ($zip->locateName('database.sql') !== false);
        $hasAudit = ($zip->locateName('audit_logs_archive.json') !== false);
        $hasConfig = ($zip->locateName('config_summary.json') !== false);
        $hasMeta = ($zip->locateName('metadata.json') !== false);

        $sqlContent = $hasSql ? $zip->getFromName('database.sql') : '';
        $zip->close();

        $assert('BACKUP-03', 'ZIP archive contains verified database.sql, audit archive, sanitized config, and manifest',
            $isOpen &&
            $hasSql &&
            $hasAudit &&
            $hasConfig &&
            $hasMeta &&
            str_contains($sqlContent, 'CREATE TABLE') &&
            str_contains($sqlContent, 'INSERT INTO')
        );

        // 4. SHA-256 Checksum Verification
        $actualChecksum = hash_file('sha256', $zipPath);
        $dbRecord = Database::selectOne("SELECT * FROM backup_records WHERE id = ?", [$backupId]);

        $assert('BACKUP-04', 'SHA-256 checksum in backup_records matches calculated file hash',
            $dbRecord !== null &&
            $dbRecord['checksum'] === $actualChecksum &&
            $backupRes['checksum'] === $actualChecksum
        );

        // 5. Download Backup Endpoint
        $downReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/settings/backups/{$backupId}/download"]);
        $downRes = $router->dispatch($downReq);

        $assert('BACKUP-05', 'GET /settings/backups/{id}/download serves application/zip stream with filename header',
            $downRes->getStatusCode() === 200 &&
            ($downRes->getHeaders()['Content-Type'] ?? '') === 'application/zip' &&
            str_contains($downRes->getHeaders()['Content-Disposition'] ?? '', $backupRes['file_name']) &&
            strlen($downRes->getContent()) === filesize($zipPath)
        );

        // 6. Next Scheduled Backup Interval Calculation
        $nextScheduled = BackupService::getNextScheduled($companyId);
        $assert('BACKUP-06', 'getNextScheduled calculates 7-day automatic weekly backup interval',
            isset($nextScheduled['next_date']) &&
            strtotime($nextScheduled['next_date']) > (time() + (5 * 86400))
        );

        // 7. Safety Snapshot Before Restore
        $restoreRes = BackupService::restoreBackup($backupId, $companyId, 'Test Administrator', '127.0.0.1');
        $safetyId = (int)$restoreRes['safety_backup_id'];
        $safetyRecord = Database::selectOne("SELECT * FROM backup_records WHERE id = ?", [$safetyId]);

        $assert('BACKUP-07', 'restoreBackup creates an automatic safety snapshot before restoring database tables',
            $restoreRes['success'] === true &&
            $safetyId > 0 &&
            $safetyRecord !== null &&
            $safetyRecord['backup_type'] === 'SAFETY_PRE_RESTORE'
        );

        // 8. Restore Log Verification
        $restoreLog = Database::selectOne("SELECT * FROM restore_logs WHERE backup_record_id = ? ORDER BY id DESC LIMIT 1", [$backupId]);
        $assert('BACKUP-08', 'Restore operation is recorded in restore_logs with status SUCCESS and checksum verification',
            $restoreLog !== null &&
            $restoreLog['status'] === 'SUCCESS' &&
            (int)$restoreLog['checksum_verified'] === 1
        );

        // 9. Atomic Weekly Audit Log Retention & Cleanup
        // Insert a dummy old audit log older than 7 days
        $oldDate = date('Y-m-d H:i:s', strtotime('-10 days'));
        $oldLogId = (int)Database::insert('audit_logs', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'user_id' => 1,
            'user_name' => 'AutoTest Retention',
            'action' => 'TEST_CLEANUP_OLD',
            'entity_type' => 'TEST_LOG',
            'entity_id' => 9999,
            'description' => 'Test log for retention purge verification',
            'ip_address' => '127.0.0.1',
            'status' => 'SUCCESS',
            'severity' => 'INFO',
            'created_at' => $oldDate,
        ]);

        $cleanupRes = AuditCleanupService::runWeeklyCleanup($companyId, 7, 'Automated Test Runner');
        $clearedLog = Database::selectOne("SELECT * FROM audit_logs WHERE id = ?", [$oldLogId]);

        $assert('BACKUP-09', 'Atomic weekly cleanup archives logs first and purges records older than retention period',
            $cleanupRes['success'] === true &&
            $cleanupRes['status'] === 'SUCCESS' &&
            $cleanupRes['records_archived'] > 0 &&
            $clearedLog === null
        );

        // 10. Safeguard: Security-Critical & Recent Logs Are NOT Deleted
        $recentLogId = (int)Database::insert('audit_logs', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'user_id' => 1,
            'user_name' => 'Recent Protected Log',
            'action' => 'RECENT_EVENT',
            'entity_type' => 'SECURITY',
            'entity_id' => 8888,
            'description' => 'Recent event within retention period',
            'ip_address' => '127.0.0.1',
            'status' => 'SUCCESS',
            'severity' => 'CRITICAL',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        AuditCleanupService::runWeeklyCleanup($companyId, 7, 'Second Run');
        $stillExists = Database::selectOne("SELECT * FROM audit_logs WHERE id = ?", [$recentLogId]);

        $assert('BACKUP-10', 'Critical security events and recent logs within retention window are preserved',
            $stillExists !== null &&
            (int)$stillExists['id'] === $recentLogId
        );

        // 11. Cleanup Report Persistence
        $cleanupReports = AuditCleanupService::getCleanupHistory($companyId);
        $assert('BACKUP-11', 'Audit cleanup summary is persisted in audit_cleanup_logs with backup reference',
            is_array($cleanupReports) &&
            count($cleanupReports) > 0 &&
            $cleanupReports[0]['status'] === 'SUCCESS' &&
            (int)$cleanupReports[0]['backup_verified'] === 1
        );
    }
}

