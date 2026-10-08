<?php

namespace App\Imports\Services;

use App\Models\ImportJob;
use App\Models\ImportRowLog;
use App\Imports\Validators\CustomerImportValidator;
use App\Imports\Validators\SupplierImportValidator;
use App\Imports\Validators\ProductImportValidator;
use App\Imports\Validators\CategoryImportValidator;
use App\Imports\Validators\OpeningStockImportValidator;
use App\Imports\Validators\OpeningBalanceImportValidator;
use App\Imports\Validators\PriceListImportValidator;
use App\Imports\Processors\CustomerImportProcessor;
use App\Imports\Processors\SupplierImportProcessor;
use App\Imports\Processors\ProductImportProcessor;
use App\Imports\Processors\CategoryImportProcessor;
use App\Imports\Processors\OpeningStockImportProcessor;
use App\Imports\Processors\OpeningBalanceImportProcessor;
use App\Imports\Processors\PriceListImportProcessor;
use App\Imports\Mappers\ColumnAutoMapper;
use App\Audit\Services\AuditTrailService;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;
use InvalidArgumentException;

class ImportService
{
    private static function getValidator(string $dataType)
    {
        return match (strtoupper($dataType)) {
            'CUSTOMERS' => new CustomerImportValidator(),
            'SUPPLIERS' => new SupplierImportValidator(),
            'PRODUCTS' => new ProductImportValidator(),
            'CATEGORIES' => new CategoryImportValidator(),
            'OPENING_STOCK' => new OpeningStockImportValidator(),
            'OPENING_BALANCES' => new OpeningBalanceImportValidator(),
            'PRICE_LISTS' => new PriceListImportValidator(),
            default => throw new InvalidArgumentException("Unsupported import data type: {$dataType}"),
        };
    }

    /**
     * Initialize import job with raw parsed rows.
     */
    public static function createImportJob(
        int $companyId,
        string $dataType,
        array $rawRows,
        string $fileName,
        ?int $branchId = null,
        ?string $userName = 'System'
    ): ImportJob {
        $correlationId = AuditTrailService::generateCorrelationId('IMP');

        $job = ImportJob::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'data_type' => strtoupper($dataType),
            'file_name' => $fileName,
            'file_type' => str_ends_with(strtolower($fileName), '.xlsx') ? 'XLSX' : 'CSV',
            'file_size' => strlen(json_encode($rawRows)),
            'total_rows' => count($rawRows),
            'status' => 'UPLOADED',
            'correlation_id' => $correlationId,
            'created_by' => $userName,
        ]);

        // Auto-suggest initial mappings from first row keys
        if (!empty($rawRows)) {
            $firstKeys = array_keys($rawRows[0]);
            $suggested = ColumnAutoMapper::suggestMappings($dataType, $firstKeys);
            $job->update(['mapping_config_json' => $suggested]);
        }

        // Store raw rows in row logs
        foreach ($rawRows as $idx => $row) {
            ImportRowLog::create([
                'import_job_id' => $job->id,
                'row_index' => $idx + 1,
                'status' => 'VALID',
                'raw_data_json' => $row,
            ]);
        }

        AuditTrailService::log(
            $companyId,
            'IMPORT_INITIATED',
            'IMPORT_JOB',
            $job->id,
            "Import job #{$job->id} initialized for {$dataType} ({$job->total_rows} rows)",
            null,
            ['data_type' => $dataType, 'total_rows' => $job->total_rows],
            null,
            1,
            $userName,
            $branchId,
            $correlationId
        );

        return $job;
    }

    /**
     * Preview and validate import job using custom column mapping.
     */
    public static function previewImport(int $importJobId, array $columnMapping): array
    {
        $job = ImportJob::with('rowLogs')->findOrFail($importJobId);
        $validator = self::getValidator($job->data_type);

        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $previewRows = [];

        foreach ($job->rowLogs as $rowLog) {
            $raw = $rowLog->raw_data_json ?: [];
            $mapped = [];

            foreach ($columnMapping as $sourceCol => $targetField) {
                if ($targetField && isset($raw[$sourceCol])) {
                    $mapped[$targetField] = $raw[$sourceCol];
                }
            }

            $validation = $validator->validateRow($job->company_id, $mapped, $rowLog->row_index);

            // Update row log
            $rowLog->update([
                'status' => $validation['status'],
                'parsed_data_json' => $validation['parsed'],
                'issues_json' => [
                    'issues' => $validation['issues'],
                    'existing_id' => $validation['existing_id'] ?? null,
                    'is_duplicate' => $validation['is_duplicate'] ?? false,
                ],
            ]);

            match ($validation['status']) {
                'VALID' => $validCount++,
                'WARNING' => $warningCount++,
                'ERROR' => $errorCount++,
                'DUPLICATE' => $duplicateCount++,
                default => null,
            };

            if (count($previewRows) < 100) {
                $previewRows[] = [
                    'row_index' => $rowLog->row_index,
                    'status' => $validation['status'],
                    'data' => $validation['parsed'],
                    'issues' => $validation['issues'],
                ];
            }
        }

        $summary = [
            'total_rows' => $job->total_rows,
            'valid_rows' => $validCount,
            'warning_rows' => $warningCount,
            'error_rows' => $errorCount,
            'duplicate_rows' => $duplicateCount,
        ];

        $job->update([
            'status' => 'VALIDATED',
            'valid_rows' => $validCount,
            'warning_rows' => $warningCount,
            'error_rows' => $errorCount,
            'duplicate_rows' => $duplicateCount,
            'mapping_config_json' => $columnMapping,
            'validation_summary_json' => $summary,
        ]);

        return [
            'job_id' => $job->id,
            'status' => 'VALIDATED',
            'summary' => $summary,
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * Confirm and execute transaction-safe import commit.
     */
    public static function confirmAndProcess(int $importJobId, string $duplicateAction = 'SKIP', ?string $userName = 'System'): array
    {
        return DB::transaction(function () use ($importJobId, $duplicateAction, $userName) {
            $job = ImportJob::lockForUpdate()->findOrFail($importJobId);
            $companyId = $job->company_id;

            $validLogs = ImportRowLog::where('import_job_id', $job->id)
                ->whereIn('status', ['VALID', 'WARNING', 'DUPLICATE'])
                ->get();

            $parsedList = $validLogs->map(fn($l) => [
                'parsed' => $l->parsed_data_json,
                'is_duplicate' => ($l->status === 'DUPLICATE'),
                'existing_id' => is_array($l->issues_json) ? ($l->issues_json['existing_id'] ?? null) : null,
            ])->toArray();

            $result = match ($job->data_type) {
                'CUSTOMERS' => CustomerImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'SUPPLIERS' => SupplierImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'PRODUCTS' => ProductImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'CATEGORIES' => CategoryImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'OPENING_STOCK' => OpeningStockImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'OPENING_BALANCES' => OpeningBalanceImportProcessor::process($companyId, $parsedList, $duplicateAction),
                'PRICE_LISTS' => PriceListImportProcessor::process($companyId, $parsedList, $duplicateAction),
                default => throw new RuntimeException("Unknown processor for {$job->data_type}"),
            };

            $job->update([
                'status' => 'COMPLETED',
                'duplicate_action' => $duplicateAction,
                'processed_rows' => ($result['imported'] ?? 0) + ($result['updated'] ?? 0),
                'completed_at' => \Carbon\Carbon::now(),
            ]);

            AuditTrailService::log(
                $companyId,
                'IMPORT_COMMITTED',
                'IMPORT_JOB',
                $job->id,
                "Committed {$job->data_type} import: {$result['imported']} imported, {$result['updated']} updated, {$result['skipped']} skipped",
                null,
                $result,
                null,
                1,
                $userName,
                $job->branch_id,
                $job->correlation_id
            );

            return [
                'job_id' => $job->id,
                'status' => 'COMPLETED',
                'imported' => $result['imported'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'failed' => $job->error_rows,
            ];
        });
    }

    /**
     * Generate downloadable CSV error report for an import job.
     */
    public static function generateErrorReport(int $importJobId): ?string
    {
        $job = ImportJob::findOrFail($importJobId);
        $errorLogs = ImportRowLog::where('import_job_id', $job->id)
            ->where('status', 'ERROR')
            ->get();

        if ($errorLogs->isEmpty()) {
            return null;
        }

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['Row #', 'Status', 'Field', 'Error Message', 'Suggested Fix', 'Original Data']);

        foreach ($errorLogs as $log) {
            $issues = is_array($log->issues_json) && isset($log->issues_json['issues'])
                ? $log->issues_json['issues']
                : ($log->issues_json ?: []);
            $rawJson = json_encode($log->raw_data_json);
            foreach ($issues as $issue) {
                if (($issue['type'] ?? '') === 'ERROR') {
                    fputcsv($fp, [
                        $log->row_index,
                        $log->status,
                        $issue['field'] ?? 'General',
                        $issue['message'] ?? 'Validation failure',
                        $issue['suggested_fix'] ?? 'Fix invalid format',
                        $rawJson,
                    ]);
                }
            }
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
