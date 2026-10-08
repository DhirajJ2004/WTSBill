<?php

namespace App\Services;

use App\Models\Company;
use App\Models\GSTConfiguration;
use App\Models\GSTRate;
use App\Models\HsnSacMaster;

class GSTConfigurationService
{
    /**
     * Standard GST Rates in India
     */
    public static function getDefaultRates(): array
    {
        return [
            ['rate' => 0.00, 'cess_rate' => 0.00, 'description' => 'Nil Rated / Exempt (0%)'],
            ['rate' => 0.25, 'cess_rate' => 0.00, 'description' => 'Precious Stones (0.25%)'],
            ['rate' => 3.00, 'cess_rate' => 0.00, 'description' => 'Gold & Silver Jewellery (3%)'],
            ['rate' => 5.00, 'cess_rate' => 0.00, 'description' => 'Essential Goods (5%)'],
            ['rate' => 12.00, 'cess_rate' => 0.00, 'description' => 'Standard Rate 1 (12%)'],
            ['rate' => 18.00, 'cess_rate' => 0.00, 'description' => 'Standard Rate 2 (18%)'],
            ['rate' => 28.00, 'cess_rate' => 0.00, 'description' => 'Luxury / Demerit Goods (28%)'],
            ['rate' => 28.00, 'cess_rate' => 12.00, 'description' => 'Luxury / Vehicles with Cess (28% + 12% Cess)'],
        ];
    }

    /**
     * Standard Sample HSN / SAC codes
     */
    public static function getDefaultHsnSac(): array
    {
        return [
            ['code' => '8471', 'type' => 'HSN', 'description' => 'Automatic data processing machines (Computers, Laptops)', 'rate' => 18.00, 'cess' => 0.00],
            ['code' => '8517', 'type' => 'HSN', 'description' => 'Telephone sets, Smartphones & wireless equipment', 'rate' => 18.00, 'cess' => 0.00],
            ['code' => '998313', 'type' => 'SAC', 'description' => 'Information technology (IT) consulting and support services', 'rate' => 18.00, 'cess' => 0.00],
            ['code' => '998314', 'type' => 'SAC', 'description' => 'Internet telecommunication and network services', 'rate' => 18.00, 'cess' => 0.00],
            ['code' => '998222', 'type' => 'SAC', 'description' => 'Accounting, auditing and bookkeeping services', 'rate' => 18.00, 'cess' => 0.00],
            ['code' => '1006', 'type' => 'HSN', 'description' => 'Rice (Food Grains)', 'rate' => 5.00, 'cess' => 0.00],
            ['code' => '3004', 'type' => 'HSN', 'description' => 'Medicaments (Pharmaceutical Medicines)', 'rate' => 12.00, 'cess' => 0.00],
            ['code' => '8703', 'type' => 'HSN', 'description' => 'Motor cars and vehicles for transport of persons', 'rate' => 28.00, 'cess' => 12.00],
        ];
    }

    /**
     * Get or initialize business GST Configuration
     */
    public static function getOrCreateConfiguration(int $companyId, ?int $branchId = null): GSTConfiguration
    {
        $config = GSTConfiguration::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->first();

        if ($config) {
            return $config;
        }

        $company = Company::find($companyId);
        $state = $company ? ($company->state ?: 'Maharashtra') : 'Maharashtra';
        $stateCode = PlaceOfSupplyService::normalizeStateCode($company ? ($company->state_code ?: $state) : '27');
        $gstin = $company ? $company->gstin : null;
        $pan = $company ? ($company->pan ?: ($gstin ? substr($gstin, 2, 10) : null)) : null;

        return GSTConfiguration::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'gst_registered' => !empty($gstin),
            'gstin' => $gstin,
            'legal_business_name' => $company ? ($company->legal_name ?: $company->name) : 'Bharat Business',
            'trade_name' => $company ? $company->name : 'Bharat Business',
            'pan' => $pan,
            'registered_state' => $state,
            'state_code' => $stateCode,
            'tax_registration_type' => !empty($gstin) ? 'REGISTERED_REGULAR' : 'UNREGISTERED',
            'is_composition' => false,
            'default_place_of_supply' => $state,
            'einvoice_applicable' => false,
            'einvoice_threshold' => 50000000.00,
            'eway_bill_applicable' => true,
            'eway_threshold' => 50000.00,
            'filing_frequency' => 'MONTHLY',
            'api_environment' => 'SANDBOX',
        ]);
    }

    /**
     * Ensure default GST Rates exist
     */
    public static function ensureDefaultGSTRates(?int $companyId = null): array
    {
        $defaults = static::getDefaultRates();
        $results = [];

        foreach ($defaults as $item) {
            $query = GSTRate::where('rate', $item['rate'])->where('cess_rate', $item['cess_rate']);
            if ($companyId) {
                $query->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                });
            } else {
                $query->whereNull('company_id');
            }

            $rate = $query->first();
            if (!$rate) {
                $rate = GSTRate::create([
                    'company_id' => $companyId,
                    'rate' => $item['rate'],
                    'cess_rate' => $item['cess_rate'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'is_system_default' => true,
                ]);
            }
            $results[] = $rate;
        }

        return $results;
    }

    /**
     * Ensure default HSN/SAC master items exist
     */
    public static function ensureDefaultHsnSac(?int $companyId = null): array
    {
        $defaults = static::getDefaultHsnSac();
        $results = [];

        foreach ($defaults as $item) {
            $master = HsnSacMaster::where('code', $item['code'])->first();
            if (!$master) {
                $master = HsnSacMaster::create([
                    'company_id' => $companyId,
                    'code' => $item['code'],
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'default_gst_rate' => $item['rate'],
                    'default_cess_rate' => $item['cess'],
                    'is_active' => true,
                ]);
            }
            $results[] = $master;
        }

        return $results;
    }
}
