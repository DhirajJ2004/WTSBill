<?php

declare(strict_types=1);

/**
 * WTSBill ERP — Automated Background Scheduler CLI Runner.
 * Suitable for Linux cron jobs or Windows Task Scheduler.
 *
 * Usage:
 *   php bin/scheduler.php backup
 *   php bin/scheduler.php backup --weekly
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
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Command:   {$command}\n";
echo "--------------------------------------------------------\n";

switch ($command) {
    case 'backup':
        $type = isset($options['weekly']) ? 'SCHEDULED_WEEKLY' : 'MANUAL';
        echo "Starting {$type} backup for tenant company #{$companyId}...\n";
        try {
            $res = BackupService::createBackup($companyId, $type, 'CLI_SCHEDULER', 30);
            echo "[SUCCESS] Backup Created Successfully!\n";
            echo "File:     {$res['file_name']}\n";
            echo "Size:     " . number_format($res['file_size'] / 1024, 2) . " KB\n";
            echo "Checksum: {$res['checksum']}\n";
            exit(0);
        } catch (\Throwable $e) {
            echo "[ERROR] Backup Failed: " . $e->getMessage() . "\n";
            exit(1);
        }

    case 'cleanup-audit':
        $retentionDays = isset($options['retention']) ? (int)$options['retention'] : 7;
        echo "Running weekly audit log cleanup (Retention: {$retentionDays} days)...\n";
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
        echo "Executing full weekly automated maintenance...\n";
        $res = AuditCleanupService::runWeeklyCleanup($companyId, 7, 'CLI_WEEKLY_JOB');
        if ($res['success']) {
            echo "[SUCCESS] Full weekly maintenance finished successfully.\n";
            exit(0);
        } else {
            echo "[ERROR] Weekly maintenance failed: " . ($res['error'] ?? 'Unknown error') . "\n";
            exit(1);
        }

    default:
        echo "Available Commands:\n";
        echo "  backup [--weekly]              Create backup archive\n";
        echo "  cleanup-audit [--retention=7]  Safely archive & cleanup audit logs\n";
        echo "  run-all / weekly               Execute full weekly backup & audit purge\n";
        exit(0);
}

