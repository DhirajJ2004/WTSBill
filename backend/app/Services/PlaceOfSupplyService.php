<?php

namespace App\Services;

class PlaceOfSupplyService
{
    /**
     * Standard Indian GST State Codes mapping
     */
    public static function getStateCodeMap(): array
    {
        return [
            '01' => 'Jammu and Kashmir',
            '02' => 'Himachal Pradesh',
            '03' => 'Punjab',
            '04' => 'Chandigarh',
            '05' => 'Uttarakhand',
            '06' => 'Haryana',
            '07' => 'Delhi',
            '08' => 'Rajasthan',
            '09' => 'Uttar Pradesh',
            '10' => 'Bihar',
            '11' => 'Sikkim',
            '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland',
            '14' => 'Manipur',
            '15' => 'Mizoram',
            '16' => 'Tripura',
            '17' => 'Meghalaya',
            '18' => 'Assam',
            '19' => 'West Bengal',
            '20' => 'Jharkhand',
            '21' => 'Odisha',
            '22' => 'Chhattisgarh',
            '23' => 'Madhya Pradesh',
            '24' => 'Gujarat',
            '25' => 'Daman and Diu',
            '26' => 'Dadra and Nagar Haveli',
            '27' => 'Maharashtra',
            '28' => 'Andhra Pradesh (Old)',
            '29' => 'Karnataka',
            '30' => 'Goa',
            '31' => 'Lakshadweep',
            '32' => 'Kerala',
            '33' => 'Tamil Nadu',
            '34' => 'Puducherry',
            '35' => 'Andaman and Nicobar Islands',
            '36' => 'Telangana',
            '37' => 'Andhra Pradesh',
            '38' => 'Ladakh',
            '97' => 'Other Territory',
            '99' => 'Centre Jurisdiction',
        ];
    }

    /**
     * Normalize state code to 2-digit string
     */
    public static function normalizeStateCode(?string $codeOrName): string
    {
        if (empty($codeOrName)) {
            return '27'; // Default Maharashtra
        }
        $clean = trim($codeOrName);
        if (is_numeric($clean)) {
            return str_pad($clean, 2, '0', STR_PAD_LEFT);
        }

        $map = static::getStateCodeMap();
        $nameLower = strtolower($clean);
        foreach ($map as $code => $stateName) {
            if (strtolower($stateName) === $nameLower || str_contains($nameLower, strtolower($stateName))) {
                return $code;
            }
        }
        return '27';
    }

    /**
     * Get State Name from State Code
     */
    public static function getStateName(string $code): string
    {
        $map = static::getStateCodeMap();
        $code = str_pad($code, 2, '0', STR_PAD_LEFT);
        return $map[$code] ?? 'Other';
    }

    /**
     * Determine whether supply is Intra-State or Inter-State based on Place of Supply
     */
    public static function determineSupplyType(string $sellerStateCode, string $placeOfSupplyStateCode, bool $isSez = false, bool $isExport = false): string
    {
        if ($isExport || $isSez) {
            return 'INTER_STATE'; // Exports and SEZ supplies are treated as Inter-State under IGST Act
        }

        $sellerCode = static::normalizeStateCode($sellerStateCode);
        $posCode = static::normalizeStateCode($placeOfSupplyStateCode);

        return ($sellerCode === $posCode) ? 'INTRA_STATE' : 'INTER_STATE';
    }
}
