<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\GSTDocumentSnapshot;
use App\Models\Company;

class EInvoicePayloadBuilder
{
    /**
     * Build Government Schema v1.1 compliant JSON payload
     */
    public static function buildPayload(Invoice $invoice, ?GSTDocumentSnapshot $snapshot = null): array
    {
        if (!$snapshot) {
            $snapshot = GSTSnapshotService::createSnapshotForInvoice($invoice);
        }

        $company = Company::find($invoice->company_id);
        $customer = $invoice->customer;

        $docDate = date('d/m/Y', strtotime($invoice->invoice_date ?: date('Y-m-d')));
        $sellerStateCode = PlaceOfSupplyService::normalizeStateCode($snapshot->seller_state_code);
        $buyerStateCode = PlaceOfSupplyService::normalizeStateCode($snapshot->buyer_state_code);
        $pos = PlaceOfSupplyService::normalizeStateCode($snapshot->place_of_supply);

        $itemList = [];
        $slNo = 1;
        $lines = $snapshot->lines;

        foreach ($lines as $ln) {
            $hsn = $ln['hsn_code'] ?? ($ln['hsn_sac'] ?? '8471');
            $qty = floatval($ln['quantity'] ?? 1);
            $unitPrice = floatval($ln['unit_price'] ?? ($ln['price'] ?? 0));
            $taxable = floatval($ln['taxable_amount'] ?? ($qty * $unitPrice));
            $gstRate = floatval($ln['gst_rate'] ?? 18.0);
            $cgst = floatval($ln['cgst_amount'] ?? 0);
            $sgst = floatval($ln['sgst_amount'] ?? 0);
            $igst = floatval($ln['igst_amount'] ?? 0);
            $cess = floatval($ln['cess_amount'] ?? 0);
            $totItemVal = floatval($ln['total_amount'] ?? ($taxable + $cgst + $sgst + $igst + $cess));

            $itemList[] = [
                'SlNo' => (string)$slNo++,
                'PrdDesc' => substr($ln['product_name'] ?? 'Commercial Product', 0, 100),
                'IsServc' => strlen($hsn) === 6 ? 'Y' : 'N',
                'HsnCd' => $hsn,
                'Qty' => $qty,
                'Unit' => 'NOS',
                'UnitPrice' => $unitPrice,
                'TotAmt' => round($qty * $unitPrice, 2),
                'Discount' => floatval($ln['discount_amount'] ?? 0),
                'AssAmt' => $taxable,
                'GstRt' => $gstRate,
                'IgstAmt' => $igst,
                'CgstAmt' => $cgst,
                'SgstAmt' => $sgst,
                'CesRt' => floatval($ln['cess_rate'] ?? 0),
                'CesAmt' => $cess,
                'TotItemVal' => $totItemVal,
            ];
        }

        $taxableTotal = floatval($snapshot->taxable_amount);
        $cgstTotal = floatval($snapshot->cgst_amount);
        $sgstTotal = floatval($snapshot->sgst_amount);
        $igstTotal = floatval($snapshot->igst_amount);
        $cessTotal = floatval($snapshot->cess_amount);
        $grandTotal = floatval($snapshot->total_document_value);
        $roundOff = round($grandTotal - ($taxableTotal + $cgstTotal + $sgstTotal + $igstTotal + $cessTotal), 2);

        return [
            'Version' => '1.1',
            'TranDtls' => [
                'TaxSch' => 'GST',
                'SupTyp' => $snapshot->gst_category === 'B2B' ? 'B2B' : 'B2C',
                'RegRev' => $snapshot->is_reverse_charge ? 'Y' : 'N',
                'EcmGstin' => null,
                'IgstOnIntra' => 'N',
            ],
            'DocDtls' => [
                'Typ' => 'INV',
                'No' => $invoice->invoice_number,
                'Dt' => $docDate,
            ],
            'SellerDtls' => [
                'Gstin' => $snapshot->seller_gstin ?: ($company ? $company->gstin : '27TESTS1234F1Z5'),
                'LglNm' => $company ? ($company->legal_name ?: $company->name) : 'Bharat Seller Pvt Ltd',
                'TrdNm' => $company ? $company->name : 'Bharat Seller',
                'Addr1' => $company ? ($company->address_line1 ?: 'Industrial Estate') : 'Industrial Area',
                'Loc' => $company ? ($company->city ?: 'Mumbai') : 'Mumbai',
                'Pin' => intval($company ? ($company->pincode ?: 400001) : 400001),
                'Stcd' => $sellerStateCode,
            ],
            'BuyerDtls' => [
                'Gstin' => $snapshot->buyer_gstin ?: '27TESTB1234F1Z5',
                'LglNm' => $customer ? $customer->name : 'Buyer Entity',
                'TrdNm' => $customer ? $customer->name : 'Buyer Entity',
                'Pos' => $pos,
                'Addr1' => $customer ? ($customer->billing_address ?: 'Commercial Street') : 'Commercial Street',
                'Loc' => $customer ? ($customer->city ?: 'Pune') : 'Pune',
                'Pin' => intval($customer ? ($customer->pincode ?: 411001) : 411001),
                'Stcd' => $buyerStateCode,
            ],
            'ItemList' => $itemList,
            'ValDtls' => [
                'AssVal' => $taxableTotal,
                'CgstVal' => $cgstTotal,
                'SgstVal' => $sgstTotal,
                'IgstVal' => $igstTotal,
                'CesVal' => $cessTotal,
                'RndOffAmt' => $roundOff,
                'TotInvVal' => $grandTotal,
            ],
        ];
    }
}
