<?php

namespace App\Services;

class GSTService
{
    /**
     * Standard Indian GST rate slabs.
     */
    public const VALID_RATES = [0.0, 5.0, 12.0, 18.0, 28.0];

    /**
     * Determine tax parameters using:
     * Product tax configuration + Customer type + GSTIN + Place of Supply + Company state
     */
    public static function determineTaxParameters(
        $product,
        $customer,
        $company,
        ?string $explicitPos = null,
        ?float $explicitRate = null,
        bool $isLut = false
    ): array {
        // 1. Company State Code (from branch/company GSTIN or state_code)
        $companyGstin = strtoupper(trim((string)($company->gstin ?? '')));
        if (strlen($companyGstin) >= 2 && is_numeric(substr($companyGstin, 0, 2))) {
            $sellerStateCode = substr($companyGstin, 0, 2);
        } else {
            $sellerStateCode = PlaceOfSupplyService::normalizeStateCode($company->state_code ?? ($company->state ?? '27'));
        }

        // 2. Customer GSTIN & Customer State
        $customerGstin = strtoupper(trim((string)($customer->gstin ?? '')));
        $customerStateCode = null;
        if (strlen($customerGstin) >= 2 && is_numeric(substr($customerGstin, 0, 2))) {
            $customerStateCode = substr($customerGstin, 0, 2);
        }
        if (!$customerStateCode && !empty($customer->state_code)) {
            $customerStateCode = PlaceOfSupplyService::normalizeStateCode($customer->state_code);
        }
        if (!$customerStateCode && !empty($customer->state)) {
            $customerStateCode = PlaceOfSupplyService::normalizeStateCode($customer->state);
        }

        // 3. Customer Type
        $rawCustType = strtoupper(trim((string)($customer->customer_type ?? 'REGULAR')));
        $isSez = in_array($rawCustType, ['SEZ', 'SEZ_DEVELOPER', 'SEZ_UNIT'], true);
        $isExport = in_array($rawCustType, ['EXPORT', 'OVERSEAS', 'DEEMED_EXPORT'], true);

        // 4. Place of Supply (POS)
        if (!empty($explicitPos)) {
            $posStateCode = PlaceOfSupplyService::normalizeStateCode($explicitPos);
        } elseif (!empty($customer->place_of_supply)) {
            $posStateCode = PlaceOfSupplyService::normalizeStateCode($customer->place_of_supply);
        } elseif ($customerStateCode) {
            $posStateCode = $customerStateCode;
        } else {
            $posStateCode = $sellerStateCode; // Default to local supply for unregistered walk-in
        }

        // 5. Supply Type (Intra-state vs Inter-state)
        // Under Section 7(5)(b) of the IGST Act, supplies to SEZ are always Inter-State.
        // Export of goods/services is treated as Inter-State under Section 7(5)(a).
        if ($isExport || $isSez) {
            $supplyType = 'INTER_STATE';
            $isIgst = true;
        } elseif ($sellerStateCode === $posStateCode) {
            $supplyType = 'INTRA_STATE';
            $isIgst = false;
        } else {
            $supplyType = 'INTER_STATE';
            $isIgst = true;
        }

        // 6. Product Tax Configuration
        // Priority: explicit requested rate (if in valid slabs) > product tax_rate > product gst_rate > 0.0%
        $taxRate = null;
        if ($explicitRate !== null && in_array(floatval($explicitRate), self::VALID_RATES, true)) {
            $taxRate = floatval($explicitRate);
        } elseif (isset($product->tax_rate) && is_numeric($product->tax_rate)) {
            $taxRate = floatval($product->tax_rate);
        } elseif (isset($product->gst_rate) && is_numeric($product->gst_rate)) {
            $taxRate = floatval($product->gst_rate);
        } else {
            $taxRate = 0.0; // Never assume 18%; default to 0% if unspecified
        }

        // If export / SEZ under LUT / Bond, GST rate is 0%
        if (($isExport || $isSez) && $isLut) {
            $taxRate = 0.0;
        }

        // Rate split
        if ($isIgst) {
            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = $taxRate;
        } else {
            $cgstRate = $taxRate / 2.0;
            $sgstRate = $taxRate / 2.0;
            $igstRate = 0.0;
        }

        return [
            'seller_state_code' => $sellerStateCode,
            'customer_state_code' => $customerStateCode ?: $sellerStateCode,
            'place_of_supply' => $posStateCode,
            'supply_type' => $supplyType,
            'is_igst' => $isIgst,
            'customer_type' => $rawCustType,
            'is_sez' => $isSez,
            'is_export' => $isExport,
            'is_lut' => $isLut,
            'gst_rate' => $taxRate,
            'cgst_rate' => $cgstRate,
            'sgst_rate' => $sgstRate,
            'igst_rate' => $igstRate,
        ];
    }

    /**
     * Calculate tax for a line item with rounding.
     */
    public static function calculateLineTax(
        float $taxableAmount,
        float $gstRate,
        bool $isIgst,
        float $cessRate = 0.0
    ): array {
        $taxable = round($taxableAmount, 2);
        $rate = floatval($gstRate);

        if ($isIgst) {
            $cgstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstRate = 0.0;
            $sgstAmount = 0.0;
            $igstRate = $rate;
            $igstAmount = round($taxable * ($igstRate / 100.0), 2);
        } else {
            $cgstRate = $rate / 2.0;
            $cgstAmount = round($taxable * ($cgstRate / 100.0), 2);
            $sgstRate = $rate / 2.0;
            $sgstAmount = round($taxable * ($sgstRate / 100.0), 2);
            $igstRate = 0.0;
            $igstAmount = 0.0;
        }

        $cessAmount = ($cessRate > 0) ? round($taxable * ($cessRate / 100.0), 2) : 0.00;
        $totalTax = round($cgstAmount + $sgstAmount + $igstAmount + $cessAmount, 2);
        $lineTotal = round($taxable + $totalTax, 2);

        return [
            'taxable_amount' => $taxable,
            'gst_rate' => $rate,
            'is_igst' => $isIgst,
            'cgst_rate' => $cgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_rate' => $sgstRate,
            'sgst_amount' => $sgstAmount,
            'igst_rate' => $igstRate,
            'igst_amount' => $igstAmount,
            'cess_rate' => $cessRate,
            'cess_amount' => $cessAmount,
            'total_tax' => $totalTax,
            'total_amount' => $lineTotal,
        ];
    }
}
