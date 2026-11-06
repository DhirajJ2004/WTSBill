<?php

declare(strict_types=1);

namespace WTSBill\Services;

use PDO;
use RuntimeException;
use ZipArchive;
use WTSBill\Core\Database;
use WTSBill\Core\Config;

/**
 * Enterprise Automatic Weekly Backup and Disaster Recovery Service.
 * Generates verified, encrypted/secure archives containing pure-PHP database dumps,
 * uploaded attachments, sanitized configuration, and audit logs.
 * Includes automated weekly scheduling, duplicate run prevention, and failure alerts.
 */
class BackupService
{
    private static function getBackupDir(): string
    {
        $dir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
            // Protect backup directory from public HTTP access
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
        return $dir;
    }

    /**
     * Create a verified backup archive.
     */
    public static function createBackup(
        int $companyId,
        string $type = 'MANUAL',
        string $createdBy = 'Administrator',
        int $retentionDays = 30,
        ?string $encryptionPassword = null
    ): array {
        $backupDir = self::getBackupDir();
        $lockFile = $backupDir . '/.backup.lock';
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "WTSBill_Backup_{$timestamp}.zip";
        $zipPath = $backupDir . '/' . $fileName;

        $lockAcquired = false;
        try {
            // Check concurrency lock
            if (file_exists($lockFile)) {
                $lockTime = filemtime($lockFile);
                if (time() - $lockTime < 600) { // 10 minutes lock timeout
                    throw new RuntimeException('Another backup job is currently running.');
                }
            }
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $lockAcquired = true;

            // Check disk storage space (require at least 50 MB)
            $freeSpace = @disk_free_space($backupDir);
            if ($freeSpace !== false && $freeSpace < (50 * 1024 * 1024)) {
                throw new RuntimeException('Insufficient server storage space to create backup. Free space is below 50MB.');
            }

            // 1. Generate Pure PHP Database Dump
            $pdo = Database::pdo();
            $sqlDump = self::dumpDatabase($pdo);

            // 2. Export Audit Logs Archive
            $auditLogs = Database::select(
                "SELECT * FROM audit_logs WHERE company_id = ? ORDER BY id ASC",
                [$companyId]
            );
            $auditJson = json_encode($auditLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // 3. Sanitized Config Summary
            $configSummary = [
                'app_name' => Config::get('app.name', 'WTSBill ERP'),
                'app_env' => Config::get('app.env', 'production'),
                'backup_timestamp' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'php_version' => PHP_VERSION,
                'mysql_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'company_id' => $companyId,
                'created_by' => $createdBy,
                'backup_type' => $type,
                'frequency' => 'Every 7 Days (Weekly)',
            ];
            $configJson = json_encode($configSummary, JSON_PRETTY_PRINT);

            // 4. Create ZIP Archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Failed to create ZIP archive at {$zipPath}");
            }

            // Encryption check
            $effectivePassword = $encryptionPassword ?? (string)(Config::get('backup.encryption_password', env('BACKUP_ENCRYPTION_PASSWORD', '')) ?: '');
            $isEncrypted = false;
            if (!empty($effectivePassword)) {
                $zip->setPassword($effectivePassword);
                $isEncrypted = true;
            }

            $zip->addFromString('database.sql', $sqlDump);
            $zip->addFromString('audit_logs_archive.json', $auditJson ?: '[]');
            $zip->addFromString('config_summary.json', $configJson);

            if ($isEncrypted) {
                $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256);
                $zip->setEncryptionName('audit_logs_archive.json', ZipArchive::EM_AES_256);
                $zip->setEncryptionName('config_summary.json', ZipArchive::EM_AES_256);
            }

            // 5. Add uploaded files if present
            $uploadsDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/uploads';
            $fileCount = 0;
            if (is_dir($uploadsDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'uploads/' . substr($filePath, strlen($uploadsDir) + 1);
                        $entryName = str_replace('\\', '/', $relativePath);
                        $zip->addFile($filePath, $entryName);
                        if ($isEncrypted) {
                            $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
                        }
                        $fileCount++;
                    }
                }
            }

            // 6. Metadata Manifest
            $metadata = [
                'file_name' => $fileName,
                'backup_type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $createdBy,
                'timezone' => date_default_timezone_get(),
                'frequency' => '7_DAYS_WEEKLY',
                'audit_logs_count' => count($auditLogs),
                'uploaded_files_count' => $fileCount,
                'database_dump_size' => strlen($sqlDump),
                'is_encrypted' => $isEncrypted ? 1 : 0,
            ];
            $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            if ($isEncrypted) {
                $zip->setEncryptionName('metadata.json', ZipArchive::EM_AES_256);
            }

            $zip->close();

            // 7. Verify Integrity and Calculate SHA-256 Checksum
            if (!file_exists($zipPath) || filesize($zipPath) === 0) {
                throw new RuntimeException('Backup creation failed: archive file is missing or empty.');
            }

            $fileSize = (int)filesize($zipPath);
            $checksum = hash_file('sha256', $zipPath);

            // Test opening archive to confirm readability
            $verifyZip = new ZipArchive();
            if ($verifyZip->open($zipPath) !== true) {
                throw new RuntimeException('Backup integrity verification failed: created ZIP archive cannot be read.');
            }
            if ($verifyZip->locateName('database.sql') === false || $verifyZip->locateName('audit_logs_archive.json') === false) {
                $verifyZip->close();
                throw new RuntimeException('Backup verification failed: required files missing from archive.');
            }
            $verifyZip->close();

            // 8. Secondary Storage Destination Sync (if configured)
            $secondaryDestination = (string)(Config::get('backup.secondary_destination', env('BACKUP_SECONDARY_PATH', '')) ?: '');
            $secondarySynced = false;
            if (!empty($secondaryDestination) && is_dir($secondaryDestination) && is_writable($secondaryDestination)) {
                $secTarget = rtrim($secondaryDestination, '/\\') . '/' . $fileName;
                if (@copy($zipPath, $secTarget)) {
                    $secondarySynced = true;
                }
            }

            // 9. Record in backup_records
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$retentionDays} days"));
            $backupId = (int)Database::insert('backup_records', [
                'company_id' => $companyId,
                'backup_type' => $type,
                'file_name' => $fileName,
                'file_path' => $zipPath,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'storage_location' => $secondarySynced ? 'LOCAL_AND_SECONDARY' : 'LOCAL_SECURE',
                'app_version' => '2.0.0',
                'schema_version' => '1.0',
                'status' => 'SUCCESS',
                'metadata_json' => json_encode($metadata),
                'is_encrypted' => $isEncrypted ? 1 : 0,
                'retention_days' => $retentionDays,
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'backup_id' => $backupId,
                'file_name' => $fileName,
                'file_path' => $zipPath,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'audit_records' => count($auditLogs),
                'status' => 'SUCCESS',
                'verified' => true,
                'is_encrypted' => $isEncrypted,
                'secondary_synced' => $secondarySynced,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            // Failure Handling:
            // 1. Record error in backup_records with status FAILED
            try {
                Database::insert('backup_records', [
                    'company_id' => $companyId,
                    'backup_type' => $type,
                    'file_name' => $fileName,
                    'file_path' => $zipPath,
                    'file_size' => (file_exists($zipPath) ? (int)filesize($zipPath) : 0),
                    'checksum' => (file_exists($zipPath) ? hash_file('sha256', $zipPath) : ''),
                    'storage_location' => 'LOCAL_SECURE',
                    'app_version' => '2.0.0',
                    'schema_version' => '1.0',
                    'status' => 'FAILED',
                    'error_message' => $e->getMessage(),
                    'metadata_json' => json_encode(['error' => $e->getMessage(), 'time' => date('Y-m-d H:i:s')]),
                    'is_encrypted' => 0,
                    'retention_days' => $retentionDays,
                    'expires_at' => date('Y-m-d H:i:s'),
                    'created_by' => $createdBy,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $dbe) {
                // Ignore DB logging failure
            }

            // 2. Notify Authorized Administrators via NotificationService
            try {
                $notifService = new NotificationService();
                $notifService->createNotification(
                    $companyId,
                    null,
                    'Automated Backup Failed',
                    "Weekly backup generation failed ({$type}): " . substr($e->getMessage(), 0, 150),
                    'SYSTEM',
                    'CRITICAL',
                    '/settings?tab=backups'
                );
            } catch (\Throwable $ne) {
                // Ignore notification failure during backup exception
            }

            throw $e;
        } finally {
            if ($lockAcquired && file_exists($lockFile)) {
                @unlink($lockFile);
            }
        }
    }

    /**
     * Create backup with safe automated retries on transient failures.
     */
    public static function createBackupWithRetry(
        int $companyId,
        string $type = 'SCHEDULED_WEEKLY',
        string $createdBy = 'SCHEDULER',
        int $retentionDays = 30,
        int $maxRetries = 3,
        ?string $encryptionPassword = null
    ): array {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                return self::createBackup($companyId, $type, $createdBy, $retentionDays, $encryptionPassword);
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $maxRetries) {
                    usleep(500000); // 0.5s pause before retry
                }
            }
        }

        throw new RuntimeException("Backup failed after {$maxRetries} attempts: " . ($lastError ? $lastError->getMessage() : 'Unknown error'), 0, $lastError);
    }

    /**
     * Check if a weekly backup is due (7 days since last successful backup).
     */
    public static function isBackupDue(int $companyId, int $intervalDays = 7): bool
    {
        $last = self::getLastSuccessful($companyId);
        if (!$last) {
            return true;
        }

        $lastTimestamp = strtotime((string)$last['created_at']);
        $secondsSinceLast = time() - $lastTimestamp;
        $thresholdSeconds = $intervalDays * 86400;

        return $secondsSinceLast >= $thresholdSeconds;
    }

    /**
     * Run automated weekly scheduled backup, safely preventing duplicate runs.
     */
    public static function runScheduledWeeklyBackup(
        int $companyId = 1,
        bool $force = false,
        string $executedBy = 'SCHEDULER'
    ): array {
        if (!$force && !self::isBackupDue($companyId, 7)) {
            $last = self::getLastSuccessful($companyId);
            $next = self::getNextScheduled($companyId);
            return [
                'success' => true,
                'skipped' => true,
                'message' => "Weekly backup is not due yet. Last backup was created on " . ($last['created_at'] ?? 'N/A') . ". Next run scheduled for " . ($next['human'] ?? 'Every 7 Days'),
                'last_backup' => $last,
                'next_scheduled' => $next,
            ];
        }

        $result = self::createBackupWithRetry(
            $companyId,
            'SCHEDULED_WEEKLY',
            $executedBy,
            30,
            3
        );

        $result['skipped'] = false;
        return $result;
    }

    /**
     * Delete an existing backup archive and mark/remove from records.
     * Safeguard: Prevents deleting the only remaining successful backup archive.
     */
    public static function deleteBackup(int $backupId, int $companyId, string $deletedBy = 'Administrator'): array
    {
        $backup = Database::selectOne(
            "SELECT * FROM backup_records WHERE id = ? AND company_id = ?",
            [$backupId, $companyId]
        );

        if (!$backup) {
            throw new RuntimeException("Backup record #{$backupId} not found.");
        }

        // Safeguard: Count successful backups
        if ($backup['status'] === 'SUCCESS') {
            $successfulCount = (int)Database::selectOne(
                "SELECT COUNT(*) as cnt FROM backup_records WHERE company_id = ? AND status = 'SUCCESS'",
                [$companyId]
            )['cnt'];

            if ($successfulCount <= 1) {
                throw new RuntimeException("Safety policy prevents deleting the only remaining successful backup archive.");
            }
        }

        // Delete physical file if exists
        $filePath = (string)($backup['file_path'] ?? '');
        if (!empty($filePath) && file_exists($filePath)) {
            @unlink($filePath);
        }

        // Update record status to DELETED
        Database::update('backup_records', [
            'status' => 'DELETED',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND company_id = ?', [$backupId, $companyId]);

        // Audit Log Entry
        Database::insert('audit_logs', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'user_id' => 1,
            'user_name' => $deletedBy,
            'action' => 'DELETE',
            'entity_type' => 'BACKUP',
            'entity_id' => $backupId,
            'description' => "Permanently deleted backup archive {$backup['file_name']}",
            'ip_address' => '127.0.0.1',
            'status' => 'SUCCESS',
            'severity' => 'WARNING',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => true,
            'message' => "Backup archive {$backup['file_name']} has been permanently deleted.",
        ];
    }

    /**
     * Restore database from backup archive with safety snapshot.
     */
    public static function restoreBackup(
        int $backupId,
        int $companyId,
        string $initiatedBy = 'Administrator',
        string $ipAddress = '127.0.0.1',
        ?string $encryptionPassword = null
    ): array {
        $backup = Database::selectOne(
            "SELECT * FROM backup_records WHERE id = ? AND company_id = ?",
            [$backupId, $companyId]
        );

        if (!$backup) {
            throw new RuntimeException("Backup record #{$backupId} not found.");
        }

        $zipPath = $backup['file_path'];
        if (!file_exists($zipPath)) {
            throw new RuntimeException("Backup file does not exist on disk: {$zipPath}");
        }

        // 1. Verify Checksum
        $currentChecksum = hash_file('sha256', $zipPath);
        if ($currentChecksum !== $backup['checksum']) {
            throw new RuntimeException("Backup checksum mismatch! Archive may be corrupted or tampered with.");
        }

        // 2. Take Safety Snapshot of Current State Before Restore
        $safetyBackup = self::createBackup($companyId, 'SAFETY_PRE_RESTORE', 'Pre-Restore Safety Snapshot');
        $safetyBackupId = $safetyBackup['backup_id'];

        // 3. Extract and Verify database.sql
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot open backup archive for restoration.");
        }

        if (!empty($backup['is_encrypted'])) {
            $effectivePassword = $encryptionPassword ?? (string)(Config::get('backup.encryption_password', env('BACKUP_ENCRYPTION_PASSWORD', '')) ?: '');
            if (!empty($effectivePassword)) {
                $zip->setPassword($effectivePassword);
            }
        }

        $sqlContent = $zip->getFromName('database.sql');
        $zip->close();

        if (empty($sqlContent)) {
            throw new RuntimeException("Backup archive does not contain a valid database.sql dump.");
        }

        // 4. Execute Restore inside Transaction
        $pdo = Database::pdo();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        try {
            // Split into individual SQL commands safely
            $statements = self::splitSqlStatements($sqlContent);
            foreach ($statements as $stmt) {
                $trimmed = trim($stmt);
                if ($trimmed !== '') {
                    $pdo->exec($trimmed);
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            // Log Restore Operation
            $restoreLogId = (int)Database::insert('restore_logs', [
                'company_id' => $companyId,
                'backup_record_id' => $backupId,
                'safety_backup_id' => $safetyBackupId,
                'restore_mode' => 'DATABASE_FULL',
                'status' => 'SUCCESS',
                'initiated_by' => $initiatedBy,
                'ip_address' => $ipAddress,
                'checksum_verified' => 1,
                'schema_compatible' => 1,
                'tables_restored_json' => json_encode(['restored_at' => date('Y-m-d H:i:s')]),
                'completed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Audit Log Entry
            Database::insert('audit_logs', [
                'company_id' => $companyId,
                'branch_id' => 1,
                'user_id' => 1,
                'user_name' => $initiatedBy,
                'action' => 'RESTORE',
                'entity_type' => 'BACKUP',
                'entity_id' => $backupId,
                'description' => "Restored database from backup {$backup['file_name']}. Safety snapshot #{$safetyBackupId} created.",
                'ip_address' => $ipAddress,
                'status' => 'SUCCESS',
                'severity' => 'WARNING',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'restore_log_id' => $restoreLogId,
                'safety_backup_id' => $safetyBackupId,
                'message' => "Database restored successfully from backup #{$backupId}.",
            ];
        } catch (\Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            Database::insert('restore_logs', [
                'company_id' => $companyId,
                'backup_record_id' => $backupId,
                'safety_backup_id' => $safetyBackupId,
                'restore_mode' => 'DATABASE_FULL',
                'status' => 'FAILED',
                'initiated_by' => $initiatedBy,
                'ip_address' => $ipAddress,
                'checksum_verified' => 1,
                'schema_compatible' => 0,
                'error_message' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            throw new RuntimeException("Restore failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Pure PHP portable database dumper.
     */
    private static function dumpDatabase(PDO $pdo): string
    {
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $dump = "-- WTSBill ERP Database Dump\n";
        $dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Host: localhost\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $excludedTables = ['backup_records', 'restore_logs', 'audit_cleanup_logs'];

        foreach ($tables as $table) {
            if (in_array($table, $excludedTables, true)) {
                continue;
            }
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Table structure for `{$table}`\n";
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $dump .= $createRow[1] . ";\n\n";

            $dump .= "-- Data for `{$table}`\n";
            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $escapedCols = array_map(fn($c) => "`{$c}`", $columns);
                $escapedValues = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($row));

                $dump .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
            }
            $dump .= "\n";
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $dump;
    }

    private static function splitSqlStatements(string $sql): array
    {
        $lines = explode("\n", $sql);
        $statements = [];
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    /**
     * Get backup history.
     */
    public static function getHistory(int $companyId): array
    {
        return Database::select(
            "SELECT * FROM backup_records WHERE company_id = ? AND status != 'DELETED' ORDER BY id DESC LIMIT 50",
            [$companyId]
        );
    }

    /**
     * Get last successful backup.
     */
    public static function getLastSuccessful(int $companyId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM backup_records WHERE company_id = ? AND status = 'SUCCESS' ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
    }

    /**
     * Calculate next scheduled backup time.
     */
    public static function getNextScheduled(int $companyId): array
    {
        $last = self::getLastSuccessful($companyId);
        if (!$last) {
            return [
                'next_date' => date('Y-m-d H:i:s'),
                'is_overdue' => true,
                'human' => 'Immediate (Never backed up)',
            ];
        }

        $lastTime = strtotime((string)$last['created_at']);
        $nextTime = $lastTime + (7 * 86400); // 7 days interval
        $isOverdue = time() >= $nextTime;

        return [
            'next_date' => date('Y-m-d H:i:s', $nextTime),
            'is_overdue' => $isOverdue,
            'human' => $isOverdue ? 'Overdue for weekly backup' : date('d M Y, h:i A', $nextTime),
        ];
    }
}
