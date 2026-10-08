<?php

namespace App\Migrations\Services;

use App\Models\SchemaMigration;

class DataMigrationService
{
    /**
     * Get list of applied schema migrations.
     */
    public static function getMigrationHistory(): array
    {
        return SchemaMigration::orderBy('id', 'asc')->get()->toArray();
    }

    /**
     * Record a migration execution.
     */
    public static function recordMigration(string $version, string $name, int $batch = 1, int $executionTimeMs = 0, ?string $error = null): SchemaMigration
    {
        return SchemaMigration::updateOrCreate(
            ['version' => $version],
            [
                'name' => $name,
                'batch' => $batch,
                'status' => $error ? 'FAILED' : 'APPLIED',
                'execution_time_ms' => $executionTimeMs,
                'error_message' => $error,
                'applied_at' => \Carbon\Carbon::now(),
            ]
        );
    }
}
