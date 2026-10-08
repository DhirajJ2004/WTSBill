<?php

namespace App\Migrations\Profiles;

use App\Models\MigrationProfile;

class MigrationProfileService
{
    /**
     * Save a reusable migration profile.
     */
    public static function saveProfile(
        int $companyId,
        string $name,
        string $dataType,
        string $sourceSoftware,
        array $mapping,
        ?string $userName = 'Admin'
    ): MigrationProfile {
        return MigrationProfile::updateOrCreate(
            [
                'company_id' => $companyId,
                'name' => $name,
                'data_type' => strtoupper($dataType),
            ],
            [
                'source_software' => strtoupper($sourceSoftware),
                'mapping_json' => $mapping,
                'created_by' => $userName,
            ]
        );
    }

    /**
     * List migration profiles for a company.
     */
    public static function listProfiles(int $companyId, ?string $dataType = null): array
    {
        $q = MigrationProfile::where('company_id', $companyId);
        if ($dataType) {
            $q->where('data_type', strtoupper($dataType));
        }
        return $q->get()->toArray();
    }
}
