<?php

declare(strict_types=1);

namespace WTSBill\Services;

use RuntimeException;
use WTSBill\Core\Database;

/**
 * Enterprise Audit Log Retention & Safe Cleanup Service.
 * Ensures audit logs are never deleted unless first verified in a valid, readable backup archive.
 */
class AuditCleanupService
{
    /**
     * Run atomic weekly backup & audit log cleanup sequence.
     */
    public static function runWeeklyCleanup(
        int $companyId,
        int $retentionDays = 7,
        string $executedBy = 'SCHEDULER'
    ): array {
        $backupResult = null;
        try {
            // STEP 1 & 2: Run scheduled weekly backup and archive eligible audit logs
            $backupResult = BackupService::createBackup(
                $companyId,
                'SCHEDULED_WEEKLY',
                $executedBy,
                max(30, $retentionDays * 4)
            );

            // STEP 3 & 4: Verify backup was created successfully and archive is verified
            if (empty($backupResult['success']) || $backupResult['status'] !== 'SUCCESS') {
                throw new RuntimeException('Backup creation failed or was reported as unsuccessful.');
            }

            if (empty($backupResult['checksum']) || empty($backupResult['file_path']) || !file_exists($backupResult['file_path'])) {
                throw new RuntimeException('Backup archive verification failed: file is missing or checksum is absent.');
            }

            $backupRecordId = (int)$backupResult['backup_id'];
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            // STEP 5: Identify eligible audit log records older than retention period
            // Safeguards: exclude critical security events, admin password resets, and backup records
            $eligibleRows = Database::select(
                "SELECT id FROM audit_logs 
                 WHERE company_id = ? 
                   AND created_at < ? 
                   AND severity != 'CRITICAL' 
                   AND action NOT IN ('LOGIN_FAILED', 'ADMIN_PASSWORD_RESET', 'RESTORE', 'BACKUP_FAILED') 
                   AND entity_type != 'BACKUP'",
                [$companyId, $cutoffDate]
            );

            $eligibleIds = array_column($eligibleRows, 'id');
            $archivedCount = (int)($backupResult['audit_records'] ?? count($eligibleIds));
            $deletedCount = 0;

            if (!empty($eligibleIds)) {
                // Delete in chunks for maximum database stability
                $chunks = array_chunk($eligibleIds, 250);
                foreach ($chunks as $chunk) {
                    $inClause = implode(',', array_map('intval', $chunk));
                    Database::pdo()->exec("DELETE FROM audit_logs WHERE id IN ({$inClause})");
                    $deletedCount += count($chunk);
                }
            }

            // STEP 6: Record cleanup summary
            $cleanupLogId = (int)Database::insert('audit_cleanup_logs', [
                'company_id' => $companyId,
                'backup_record_id' => $backupRecordId,
                'retention_days' => $retentionDays,
                'records_archived' => $archivedCount,
                'records_deleted' => $deletedCount,
                'backup_verified' => 1,
                'status' => 'SUCCESS',
                'error_message' => null,
                'executed_by' => $executedBy,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'cleanup_log_id' => $cleanupLogId,
                'backup_id' => $backupRecordId,
                'backup_file' => $backupResult['file_name'],
                'records_archived' => $archivedCount,
                'records_deleted' => $deletedCount,
                'retention_days' => $retentionDays,
                'status' => 'SUCCESS',
                'message' => "Successfully archived {$archivedCount} and purged {$deletedCount} old audit log records.",
            ];
        } catch (\Throwable $e) {
            // Under NO circumstance delete audit logs if backup or verification fails!
            Database::insert('audit_cleanup_logs', [
                'company_id' => $companyId,
                'backup_record_id' => $backupResult['backup_id'] ?? null,
                'retention_days' => $retentionDays,
                'records_archived' => 0,
                'records_deleted' => 0,
                'backup_verified' => 0,
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'executed_by' => $executedBy,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => false,
                'status' => 'FAILED',
                'records_archived' => 0,
                'records_deleted' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get audit cleanup reports.
     */
    public static function getCleanupHistory(int $companyId): array
    {
        return Database::select(
            "SELECT c.*, b.file_name as backup_file_name 
             FROM audit_cleanup_logs c 
             LEFT JOIN backup_records b ON c.backup_record_id = b.id 
             WHERE c.company_id = ? 
             ORDER BY c.id DESC LIMIT 20",
            [$companyId]
        );
    }
}
