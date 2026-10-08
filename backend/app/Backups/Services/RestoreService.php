<?php

namespace App\Backups\Services;

use App\Models\BackupRecord;
use App\Models\RestoreLog;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Cheque;
use App\Models\DocumentNumberingConfig;
use App\Backups\Validators\BackupIntegrityValidator;
use App\Audit\Services\AuditTrailService;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;

class RestoreService
{
    /**
     * Preview and validate backup before restore.
     */
    public static function validateForRestore(int $backupId): array
    {
        $backup = BackupRecord::findOrFail($backupId);
        if (!file_exists($backup->file_path)) {
            throw new RuntimeException("Backup file '{$backup->file_name}' not found on storage disk.");
        }

        $content = file_get_contents($backup->file_path);
        $validation = BackupIntegrityValidator::validate($content, $backup->checksum);

        if (!$validation['is_valid']) {
            throw new RuntimeException("Restore validation failed: " . implode(', ', $validation['issues']));
        }

        return [
            'backup_id' => $backup->id,
            'company_id' => $backup->company_id,
            'app_version' => $backup->app_version,
            'schema_version' => $backup->schema_version,
            'checksum_verified' => true,
            'schema_compatible' => $validation['schema_compatible'],
            'created_at' => $backup->created_at->toIso8601String(),
            'file_size' => $backup->file_size,
            'tables_included' => array_keys($validation['parsed']['data'] ?? []),
        ];
    }

    /**
     * Execute full danger-zone restore with pre-restore safety backup.
     */
    public static function executeRestore(
        int $backupId,
        string $restoreMode = 'BUSINESS',
        ?string $userName = 'Admin',
        ?string $ipAddress = '127.0.0.1'
    ): array {
        $backup = BackupRecord::findOrFail($backupId);
        $companyId = $backup->company_id;

        // 1. Validate integrity
        $validation = self::validateForRestore($backupId);

        // 2. CREATE MANDATORY SAFETY BACKUP of current state
        $safetyBackup = BackupService::createBackup($companyId, 'SAFETY', 7, 'Pre-Restore Automated Safety');

        $restoreLog = RestoreLog::create([
            'company_id' => $companyId,
            'backup_record_id' => $backup->id,
            'safety_backup_id' => $safetyBackup->id,
            'restore_mode' => strtoupper($restoreMode),
            'status' => 'IN_PROGRESS',
            'initiated_by' => $userName,
            'ip_address' => $ipAddress,
            'checksum_verified' => true,
            'schema_compatible' => true,
        ]);

        try {
            DB::transaction(function () use ($backup, $companyId, $restoreLog) {
                $content = file_get_contents($backup->file_path);
                $dump = json_decode($content, true);
                $data = $dump['data'] ?? [];

                $restoredTables = [];

                // Restore tables safely
                if (isset($data['categories'])) {
                    foreach ($data['categories'] as $row) {
                        Category::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $companyId, 'id' => $row['id']],
                            $row
                        );
                    }
                    $restoredTables[] = 'categories';
                }

                if (isset($data['products'])) {
                    foreach ($data['products'] as $row) {
                        Product::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $companyId, 'id' => $row['id']],
                            $row
                        );
                    }
                    $restoredTables[] = 'products';
                }

                if (isset($data['customers'])) {
                    foreach ($data['customers'] as $row) {
                        Customer::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $companyId, 'id' => $row['id']],
                            $row
                        );
                    }
                    $restoredTables[] = 'customers';
                }

                if (isset($data['suppliers'])) {
                    foreach ($data['suppliers'] as $row) {
                        Supplier::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $companyId, 'id' => $row['id']],
                            $row
                        );
                    }
                    $restoredTables[] = 'suppliers';
                }

                if (isset($data['document_numbering_configs'])) {
                    foreach ($data['document_numbering_configs'] as $row) {
                        DocumentNumberingConfig::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $companyId, 'id' => $row['id']],
                            $row
                        );
                    }
                    $restoredTables[] = 'document_numbering_configs';
                }

                $restoreLog->update([
                    'status' => 'COMPLETED',
                    'tables_restored_json' => $restoredTables,
                    'completed_at' => \Carbon\Carbon::now(),
                ]);
            });

            AuditTrailService::log(
                $companyId,
                'RESTORE_COMPLETED',
                'RESTORE_LOG',
                $restoreLog->id,
                "Restored business data from Backup #{$backup->id} (Safety Backup #{$safetyBackup->id} created)",
                null,
                ['backup_id' => $backup->id, 'safety_backup_id' => $safetyBackup->id],
                null,
                1,
                $userName,
                null,
                null,
                'SUCCESS',
                'CRITICAL'
            );

            return [
                'restore_log_id' => $restoreLog->id,
                'status' => 'COMPLETED',
                'safety_backup_id' => $safetyBackup->id,
                'tables_restored' => $restoreLog->tables_restored_json,
            ];
        } catch (\Throwable $e) {
            $restoreLog->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'completed_at' => \Carbon\Carbon::now(),
            ]);

            AuditTrailService::log(
                $companyId,
                'RESTORE_FAILED',
                'RESTORE_LOG',
                $restoreLog->id,
                "Restore failed: {$e->getMessage()}",
                null,
                ['error' => $e->getMessage()],
                null,
                1,
                $userName,
                null,
                null,
                'FAILED',
                'CRITICAL'
            );

            throw new RuntimeException("Restore failed: {$e->getMessage()}", 0, $e);
        }
    }
}
