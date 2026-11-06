<?php

declare(strict_types=1);

/**
 * WTSBill ERP — Automated Background Scheduler CLI Runner.
 * Suitable for Linux cron jobs or Windows Task Scheduler.
 *
 * Runs automatically every 7 days without requiring an administrator to log in.
 * Prevents duplicate backups if the scheduled task runs more than once within the 7-day interval.
 *
 * Usage:
 *   php bin/scheduler.php backup
 *   php bin/scheduler.php backup --weekly
 *   php bin/scheduler.php backup --force
 *   php bin/scheduler.php status
 *   php bin/scheduler.php cleanup-audit --retention=7
 *   php bin/scheduler.php run-all
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';
\WTSBill\Core\Env::load(ROOT_PATH . '/.env');
\WTSBill\Core\Config::loadFromDirectory(ROOT_PATH . '/config');

use WTSBill\Services\BackupService;
use WTSBill\Services\AuditCleanupService;
use WTSBill\Core\Database;

$command = $argv[1] ?? 'help';
$companyId = 1;

// Parse optional args
$options = [];
for ($i = 2; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $parts = explode('=', substr($argv[$i], 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

echo "========================================================\n";
echo "      WTSBill ERP — Task Scheduler CLI Engine          \n";
echo "========================================================\n";
echo "Timestamp:  " . date('Y-m-d H:i:s') . "\n";
echo "Timezone:   " . date_default_timezone_get() . "\n";
echo "Frequency:  Every 7 Days (Automatic Weekly)\n";
echo "Command:    {$command}\n";
echo "--------------------------------------------------------\n";

switch ($command) {
    case 'backup':
        $force = isset($options['force']);
        $isWeekly = isset($options['weekly']) || !isset($options['manual']);

        if ($isWeekly && !$force) {
            echo "Checking weekly backup schedule (Duplicate run prevention active)...\n";
            $res = BackupService::runScheduledWeeklyBackup($companyId, false, 'CLI_SCHEDULER');
            if (!empty($res['skipped'])) {
                echo "[SKIPPED] " . $res['message'] . "\n";
                echo "Tip: Pass --force to create a backup immediately.\n";
                exit(0);
            }
        } else {
            $type = $isWeekly ? 'SCHEDULED_WEEKLY' : 'MANUAL';
            echo "Starting {$type} backup for tenant company #{$companyId} (Force=" . ($force ? 'YES' : 'NO') . ")...\n";
            try {
                $res = BackupService::createBackupWithRetry($companyId, $type, 'CLI_SCHEDULER', 30, 3);
            } catch (\Throwable $e) {
                echo "[ERROR] Backup Failed: " . $e->getMessage() . "\n";
                exit(1);
            }
        }

        echo "[SUCCESS] Backup Created & Verified Successfully!\n";
        echo "File:       {$res['file_name']}\n";
        echo "Size:       " . number_format($res['file_size'] / 1024, 2) . " KB\n";
        echo "Checksum:   {$res['checksum']} (SHA-256)\n";
        echo "Status:     {$res['status']}\n";
        echo "Encrypted:  " . (!empty($res['is_encrypted']) ? 'YES (AES-256)' : 'NO') . "\n";
        exit(0);

    case 'status':
        $last = BackupService::getLastSuccessful($companyId);
        $next = BackupService::getNextScheduled($companyId);
        $isDue = BackupService::isBackupDue($companyId, 7);

        echo "Backup Schedule Status:\n";
        echo "  Frequency:       Every 7 Days (Once per week)\n";
        echo "  Server Timezone: " . date_default_timezone_get() . "\n";
        echo "  Last Successful: " . ($last ? ($last['file_name'] . ' (' . $last['created_at'] . ', ' . number_format($last['file_size'] / 1024, 1) . ' KB)') : 'Never') . "\n";
        echo "  Next Scheduled:  " . $next['human'] . " (" . $next['next_date'] . ")\n";
        echo "  Is Backup Due:   " . ($isDue ? 'YES (Pending run)' : 'NO (Up to date)') . "\n";
        echo "  Storage Root:    outside public web root (storage/backups/)\n";
        exit(0);

    case 'cleanup-audit':
        $retentionDays = isset($options['retention']) ? (int)$options['retention'] : 7;
        echo "Running weekly audit log retention & cleanup (Retention: {$retentionDays} days)...\n";
        $res = AuditCleanupService::runWeeklyCleanup($companyId, $retentionDays, 'CLI_SCHEDULER');
        if ($res['success']) {
            echo "[SUCCESS] Audit cleanup completed.\n";
            echo "Archived: {$res['records_archived']} records\n";
            echo "Deleted:  {$res['records_deleted']} records\n";
            echo "Backup:   {$res['backup_file']}\n";
            exit(0);
        } else {
            echo "[ERROR] Audit cleanup failed: " . ($res['error'] ?? 'Unknown error') . "\n";
            exit(1);
        }

    case 'run-all':
    case 'weekly':
        $force = isset($options['force']);
        echo "Executing automated weekly maintenance pipeline (Backup + Verify + Retention)...\n";
        if (!$force && !BackupService::isBackupDue($companyId, 7)) {
            $last = BackupService::getLastSuccessful($companyId);
            $next = BackupService::getNextScheduled($companyId);
            echo "[SKIPPED] Weekly maintenance not due yet.\n";
            echo "Last backup: " . ($last['created_at'] ?? 'N/A') . "\n";
            echo "Next run:    " . $next['human'] . "\n";
            exit(0);
        }

        $res = AuditCleanupService::runWeeklyCleanup($companyId, 7, 'CLI_WEEKLY_JOB');
        if ($res['success']) {
            echo "[SUCCESS] Weekly backup and audit log maintenance finished successfully.\n";
            echo "Archive:  {$res['backup_file']}\n";
            echo "Archived: {$res['records_archived']} audit records\n";
            echo "Purged:   {$res['records_deleted']} old records (exceeding 7 days)\n";
            exit(0);
        } else {
            echo "[ERROR] Weekly maintenance failed: " . ($res['error'] ?? 'Unknown error') . "\n";
            exit(1);
        }

    default:
        echo "Available Commands:\n";
        echo "  backup [--weekly] [--force]    Create backup archive (auto-prevents duplicates unless --force)\n";
        echo "  status                         Display current backup status, last backup, and next schedule\n";
        echo "  cleanup-audit [--retention=7]  Safely archive & cleanup audit logs\n";
        echo "  run-all / weekly [--force]     Execute weekly backup + integrity check + audit log retention\n";
        exit(0);
}
