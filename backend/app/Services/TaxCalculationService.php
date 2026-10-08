<?php

namespace App\Services;

class TaxCalculationService
{
    /**
     * Calculate GST for a single line item
     */
    public static function calculateLineTax(
        float $taxableAmount,
        float $gstRate,
        float $cessRate,
        string $supplyType, // INTRA_STATE, INTER_STATE
        bool $isReverseCharge = false
    ): array {
        $taxable = TaxRoundingService::round($taxableAmount);
        $rate = floatval($gstRate);
        $cess = floatval($cessRate);

        $cgstRate = 0.0;
        $cgstAmount = 0.0;
        $sgstRate = 0.0;
        $sgstAmount = 0.0;
        $igstRate = 0.0;
        $igstAmount = 0.0;

        if ($supplyType === 'INTRA_STATE') {
            $cgstRate = $rate / 2.0;
            $sgstRate = $rate / 2.0;
            $cgstAmount = TaxRoundingService::round($taxable * ($cgstRate / 100.0));
            $sgstAmount = TaxRoundingService::round($taxable * ($sgstRate / 100.0));
        } else {
            $igstRate = $rate;
            $igstAmount = TaxRoundingService::round($taxable * ($igstRate / 100.0));
        }

        $cessAmount = ($cess > 0) ? TaxRoundingService::round($taxable * ($cess / 100.0)) : 0.00;
        $totalTax = TaxRoundingService::round($cgstAmount + $sgstAmount + $igstAmount + $cessAmount);
        $totalAmount = TaxRoundingService::round($taxable + $totalTax);

        return [
            'taxable_amount' => $taxable,
            'gst_rate' => $rate,
            'supply_type' => $supplyType,
            'is_reverse_charge' => $isReverseCharge,
            'cgst_rate' => $cgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_rate' => $sgstRate,
            'sgst_amount' => $sgstAmount,
            'igst_rate' => $igstRate,
            'igst_amount' => $igstAmount,
            'cess_rate' => $cess,
            'cess_amount' => $cessAmount,
            'total_tax' => $totalTax,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Calculate entire document tax summary from line items
     */
    public static function calculateDocumentTax(
        array $items,
        string $sellerStateCode,
        string $placeOfSupplyStateCode,
        bool $isReverseCharge = false,
        bool $isExport = false,
        bool $isSez = false,
        float $documentDiscount = 0.00
    ): array {
        $supplyType = PlaceOfSupplyService::determineSupplyType($sellerStateCode, $placeOfSupplyStateCode, $isSez, $isExport);

        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalCess = 0.0;
        $calculatedLines = [];

        foreach ($items as $idx => $item) {
            $qty = floatval($item['quantity'] ?? 1);
            $unitPrice = floatval($item['unit_price'] ?? ($item['price'] ?? 0));
            $lineDiscount = floatval($item['discount_amount'] ?? 0);
            $discountPct = floatval($item['discount_percentage'] ?? ($item['discount_rate'] ?? 0));
            if ($discountPct > 0) {
                $lineDiscount = ($qty * $unitPrice) * ($discountPct / 100.0);
            }

            $lineTaxable = TaxRoundingService::round(($qty * $unitPrice) - $lineDiscount);
            $rate = isset($item['gst_rate']) ? floatval($item['gst_rate']) : (isset($item['tax_rate']) ? floatval($item['tax_rate']) : 0.0);
            $cess = floatval($item['cess_rate'] ?? 0.0);

            $taxRes = static::calculateLineTax($lineTaxable, $rate, $cess, $supplyType, $isReverseCharge);

            $calculatedLines[] = array_merge($item, [
                'item_name' => $item['item_name'] ?? ($item['name'] ?? 'Item'),
                'hsn_sac' => $item['hsn_sac'] ?? ($item['hsn_code'] ?? ''),
                'hsn_code' => $item['hsn_code'] ?? ($item['hsn_sac'] ?? ''),
                'unit' => $item['unit'] ?? 'Pcs',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountPct,
                'discount_amount' => $lineDiscount,
                'taxable_amount' => $taxRes['taxable_amount'],
                'taxable_value' => $taxRes['taxable_amount'],
                'gst_rate' => $rate,
                'cgst_rate' => $taxRes['cgst_rate'],
                'cgst_amount' => $taxRes['cgst_amount'],
                'sgst_rate' => $taxRes['sgst_rate'],
                'sgst_amount' => $taxRes['sgst_amount'],
                'igst_rate' => $taxRes['igst_rate'],
                'igst_amount' => $taxRes['igst_amount'],
                'cess_rate' => $taxRes['cess_rate'],
                'cess_amount' => $taxRes['cess_amount'],
                'total_tax' => $taxRes['total_tax'],
                'total_amount' => $taxRes['total_amount'],
            ]);

            $totalTaxable += $taxRes['taxable_amount'];
            $totalCgst += $taxRes['cgst_amount'];
            $totalSgst += $taxRes['sgst_amount'];
            $totalIgst += $taxRes['igst_amount'];
            $totalCess += $taxRes['cess_amount'];
        }

        $totalTaxable = TaxRoundingService::round($totalTaxable - $documentDiscount);
        $totalCgst = TaxRoundingService::round($totalCgst);
        $totalSgst = TaxRoundingService::round($totalSgst);
        $totalIgst = TaxRoundingService::round($totalIgst);
        $totalCess = TaxRoundingService::round($totalCess);
        $totalTax = TaxRoundingService::round($totalCgst + $totalSgst + $totalIgst + $totalCess);
        $grandTotal = TaxRoundingService::round($totalTaxable + $totalTax);

        return [
            'seller_state_code' => PlaceOfSupplyService::normalizeStateCode($sellerStateCode),
            'place_of_supply' => PlaceOfSupplyService::normalizeStateCode($placeOfSupplyStateCode),
            'supply_type' => $supplyType,
            'is_reverse_charge' => $isReverseCharge,
            'taxable_amount' => $totalTaxable,
            'taxable_value' => $totalTaxable,
            'cgst_amount' => $totalCgst,
            'sgst_amount' => $totalSgst,
            'igst_amount' => $totalIgst,
            'cess_amount' => $totalCess,
            'total_tax_amount' => $totalTax,
            'grand_total' => $grandTotal,
            'lines' => $calculatedLines,
            'items' => $calculatedLines,
        ];
    }

    /**
     * Backward-compatible calculateDocument helper
     */
    public static function calculateDocument(array $items, bool $isInterState = false, float $discountRate = 0.0, float $discountAmount = 0.0): array
    {
        $sellerState = '27';
        $buyerState = $isInterState ? '29' : '27';
        $res = static::calculateDocumentTax($items, $sellerState, $buyerState);
        $res['taxable_value'] = $res['taxable_amount'];
        $res['sub_total'] = $res['taxable_amount'];
        $res['discount_amount'] = $discountAmount;
        $res['total_tax'] = $res['total_tax_amount'];
        $res['round_off'] = 0.00;
        $res['items'] = $res['lines'];
        return $res;
    }
}
