<?php

namespace App\Auth;

use App\Services\CompanyProvisioningService;

class RegistrationService
{
    /**
     * Atomically register a new tenant and owner user.
     * Delegates to centralized CompanyProvisioningService to ensure single authoritative provisioning logic.
     *
     * @param array $input
     * @return array
     */
    public static function register(array $input): array
    {
        return CompanyProvisioningService::provisionCompany($input);
    }
}
