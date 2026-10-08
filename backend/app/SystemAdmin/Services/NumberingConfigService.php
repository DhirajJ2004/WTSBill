<?php

namespace App\SystemAdmin\Services;

use App\Models\DocumentNumberingConfig;
use App\Audit\Services\AuditTrailService;
use RuntimeException;

class NumberingConfigService
{
    private static array $defaultConfigs = [
        'INVOICE' => ['prefix' => 'INV-', 'padding' => 4],
        'PURCHASE_INVOICE' => ['prefix' => 'PUR-', 'padding' => 4],
        'QUOTATION' => ['prefix' => 'QTN-', 'padding' => 4],
        'SALES_ORDER' => ['prefix' => 'SO-', 'padding' => 4],
        'CREDIT_NOTE' => ['prefix' => 'CN-', 'padding' => 4],
        'DEBIT_NOTE' => ['prefix' => 'DN-', 'padding' => 4],
        'PAYMENT_RECEIPT' => ['prefix' => 'RCP-', 'padding' => 4],
        'PURCHASE_ORDER' => ['prefix' => 'PO-', 'padding' => 4],
        'DELIVERY_CHALLAN' => ['prefix' => 'DC-', 'padding' => 4],
        'JOURNAL' => ['prefix' => 'JV-', 'padding' => 4],
        'EXPENSE' => ['prefix' => 'EXP-', 'padding' => 4],
        'PAYMENT' => ['prefix' => 'PAY-', 'padding' => 4],
    ];

    /**
     * Get or initialize numbering configurations for a company.
     */
    public static function getConfigurations(int $companyId, ?int $branchId = null): array
    {
        $configs = DocumentNumberingConfig::where('company_id', $companyId)->get();
        if ($configs->isEmpty()) {
            foreach (self::$defaultConfigs as $type => $def) {
                DocumentNumberingConfig::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'document_type' => $type,
                    'prefix' => $def['prefix'],
                    'starting_number' => 1,
                    'current_number' => 0,
                    'number_padding' => $def['padding'],
                    'reset_frequency' => 'YEARLY',
                    'is_active' => true,
                ]);
            }
            $configs = DocumentNumberingConfig::where('company_id', $companyId)->get();
        }

        return $configs->toArray();
    }

    /**
     * Generate the next document number in sequence safely.
     */
    public static function getNextNumber(int $companyId, string $documentType, ?int $branchId = null): string
    {
        $type = strtoupper($documentType);
        $config = DocumentNumberingConfig::where('company_id', $companyId)
            ->where('document_type', $type)
            ->first();

        if (!$config) {
            $def = self::$defaultConfigs[$type] ?? ['prefix' => strtoupper(substr($type, 0, 3)) . '-', 'padding' => 4];
            $config = DocumentNumberingConfig::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => $type,
                'prefix' => $def['prefix'],
                'starting_number' => 1,
                'current_number' => 0,
                'number_padding' => $def['padding'],
                'reset_frequency' => 'YEARLY',
                'is_active' => true,
            ]);
        }

        $nextVal = max($config->starting_number, $config->current_number + 1);
        $config->update(['current_number' => $nextVal]);

        $padded = str_pad((string)$nextVal, $config->number_padding, '0', STR_PAD_LEFT);
        return $config->prefix . $padded . ($config->suffix ?: '');
    }

    /**
     * Update configuration settings for a document type.
     */
    public static function updateConfiguration(int $companyId, string $documentType, array $data, ?string $userName = 'Admin'): DocumentNumberingConfig
    {
        $type = strtoupper($documentType);
        $config = DocumentNumberingConfig::where('company_id', $companyId)
            ->where('document_type', $type)
            ->firstOrFail();

        $before = $config->toArray();
        $config->update(array_filter([
            'prefix' => $data['prefix'] ?? $config->prefix,
            'suffix' => $data['suffix'] ?? $config->suffix,
            'starting_number' => $data['starting_number'] ?? $config->starting_number,
            'number_padding' => $data['number_padding'] ?? $config->number_padding,
            'reset_frequency' => $data['reset_frequency'] ?? $config->reset_frequency,
            'is_active' => $data['is_active'] ?? $config->is_active,
        ]));

        AuditTrailService::log(
            $companyId,
            'NUMBERING_CONFIG_UPDATED',
            'DOCUMENT_NUMBERING_CONFIG',
            $config->id,
            "Updated numbering pattern for {$type} (Prefix: '{$config->prefix}')",
            $before,
            $config->toArray(),
            null,
            1,
            $userName
        );

        return $config;
    }
}
