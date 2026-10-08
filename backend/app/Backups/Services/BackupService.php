<?php

namespace App\Backups\Services;

use App\Models\BackupRecord;
use App\Backups\Providers\DatabaseBackupProvider;
use App\Backups\Validators\BackupIntegrityValidator;
use App\Audit\Services\AuditTrailService;
use Carbon\Carbon;
use RuntimeException;

class BackupService
{
    /**
     * Create a verified backup for a company.
     */
    public static function createBackup(
        int $companyId,
        string $backupType = 'MANUAL',
        int $retentionDays = 30,
        ?string $userName = 'Admin'
    ): BackupRecord {
        $dump = DatabaseBackupProvider::createCompanyDump($companyId);
        $json = json_encode($dump, JSON_PRETTY_PRINT);
        $checksum = BackupIntegrityValidator::calculateChecksum($json);

        // Verification check immediately after generation
        $validation = BackupIntegrityValidator::validate($json, $checksum);
        if (!$validation['is_valid']) {
            throw new RuntimeException("Backup integrity validation failed: " . implode(', ', $validation['issues']));
        }

        $fileName = 'backup_company_' . $companyId . '_' . date('Ymd_His') . '.json';
        $storageDir = __DIR__ . '/../../../../storage/backups';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        $filePath = $storageDir . '/' . $fileName;
        file_put_contents($filePath, $json);

        $record = BackupRecord::create([
            'company_id' => $companyId,
            'backup_type' => strtoupper($backupType),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => strlen($json),
            'checksum' => $checksum,
            'storage_location' => 'LOCAL',
            'app_version' => $dump['metadata']['app_version'] ?? '1.0.0',
            'schema_version' => $dump['metadata']['schema_version'] ?? '23.0',
            'status' => 'COMPLETED',
            'metadata_json' => $dump['metadata'],
            'is_encrypted' => false,
            'retention_days' => $retentionDays,
            'expires_at' => Carbon::now()->addDays($retentionDays),
            'created_by' => $userName,
        ]);

        AuditTrailService::log(
            $companyId,
            'BACKUP_CREATED',
            'BACKUP_RECORD',
            $record->id,
            "Created {$backupType} backup #{$record->id} ({$record->file_size} bytes, SHA-256: " . substr($checksum, 0, 16) . "...) with {$retentionDays} days retention",
            null,
            ['backup_type' => $backupType, 'file_size' => $record->file_size, 'checksum' => $checksum],
            null,
            1,
            $userName
        );

        return $record;
    }

    /**
     * List active backups for a company.
     */
    public static function listBackups(int $companyId): array
    {
        return BackupRecord::where('company_id', $companyId)
            ->whereNotIn('status', ['DELETED'])
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Re-verify an existing backup record.
     */
    public static function verifyBackup(int $backupId): array
    {
        $backup = BackupRecord::findOrFail($backupId);
        if (!file_exists($backup->file_path)) {
            return [
                'is_valid' => false,
                'error' => "Backup file '{$backup->file_name}' not found on storage disk.",
            ];
        }

        $content = file_get_contents($backup->file_path);
        return BackupIntegrityValidator::validate($content, $backup->checksum);
    }

    /**
     * Mark and clean up expired backups according to retention policy.
     */
    public static function cleanExpiredBackups(int $companyId): int
    {
        $expired = BackupRecord::where('company_id', $companyId)
            ->where('status', 'COMPLETED')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expired as $b) {
            $b->update(['status' => 'EXPIRED']);
            AuditTrailService::log(
                $companyId,
                'BACKUP_EXPIRED',
                'BACKUP_RECORD',
                $b->id,
                "Backup #{$b->id} reached end of retention period and marked EXPIRED",
                null,
                null,
                null,
                1,
                'System Cron'
            );
            $count++;
        }
        return $count;
    }
}
