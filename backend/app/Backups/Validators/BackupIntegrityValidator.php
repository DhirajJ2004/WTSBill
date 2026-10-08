<?php

namespace App\Backups\Validators;

class BackupIntegrityValidator
{
    /**
     * Calculate SHA-256 checksum for content.
     */
    public static function calculateChecksum(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Validate backup integrity and schema compatibility.
     */
    public static function validate(string $content, string $expectedChecksum): array
    {
        $issues = [];
        $actualChecksum = self::calculateChecksum($content);

        if ($actualChecksum !== $expectedChecksum) {
            $issues[] = "Checksum mismatch: expected {$expectedChecksum}, got {$actualChecksum}";
            return [
                'is_valid' => false,
                'checksum_match' => false,
                'schema_compatible' => false,
                'issues' => $issues,
                'parsed' => null,
            ];
        }

        $decoded = json_decode($content, true);
        if (!$decoded || !isset($decoded['metadata']) || !isset($decoded['data'])) {
            $issues[] = 'Invalid backup structure: missing metadata or data nodes.';
            return [
                'is_valid' => false,
                'checksum_match' => true,
                'schema_compatible' => false,
                'issues' => $issues,
                'parsed' => null,
            ];
        }

        $schemaVersion = $decoded['metadata']['schema_version'] ?? 'unknown';
        $isSchemaCompatible = version_compare($schemaVersion, '20.0', '>=');

        if (!$isSchemaCompatible) {
            $issues[] = "Incompatible schema version: '{$schemaVersion}'. Minimum supported version is 20.0.";
        }

        return [
            'is_valid' => empty($issues),
            'checksum_match' => true,
            'schema_compatible' => $isSchemaCompatible,
            'schema_version' => $schemaVersion,
            'metadata' => $decoded['metadata'],
            'tables_count' => count($decoded['data']),
            'issues' => $issues,
            'parsed' => $decoded,
        ];
    }
}
