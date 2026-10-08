<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\GSTDocumentSnapshot;
use App\Models\Company;

class GSTSnapshotService
{
    /**
     * Create or retrieve immutable tax snapshot for a Sales Invoice
     */
    public static function createSnapshotForInvoice(Invoice $invoice): GSTDocumentSnapshot
    {
        $existing = GSTDocumentSnapshot::where('company_id', $invoice->company_id)
            ->where('document_type', 'INVOICE')
            ->where('document_id', $invoice->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $company = Company::find($invoice->company_id);
        $sellerGstin = $company ? $company->gstin : null;
        $sellerState = $company ? ($company->state_code ?: '27') : '27';

        $customer = $invoice->customer;
        $buyerGstin = $customer ? $customer->gstin : null;
        $buyerState = $customer ? ($customer->state_code ?: ($customer->state ?: '27')) : '27';
        $pos = $invoice->place_of_supply ?: $buyerState;

        $supplyType = PlaceOfSupplyService::determineSupplyType($sellerState, $pos);
        $isB2B = !empty($buyerGstin);

        // B2B, B2CL (Interstate > 2.5L unregistered), B2CS (small consumer)
        $category = 'B2CS';
        if ($isB2B) {
            $category = 'B2B';
        } elseif ($supplyType === 'INTER_STATE' && floatval($invoice->grand_total) > 250000.0) {
            $category = 'B2CL';
        }

        $lines = $invoice->items ? $invoice->items->toArray() : [];

        return GSTDocumentSnapshot::create([
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'document_date' => $invoice->invoice_date ?: date('Y-m-d'),
            'seller_gstin' => $sellerGstin,
            'seller_state_code' => PlaceOfSupplyService::normalizeStateCode($sellerState),
            'buyer_gstin' => $buyerGstin,
            'buyer_state_code' => PlaceOfSupplyService::normalizeStateCode($buyerState),
            'place_of_supply' => PlaceOfSupplyService::normalizeStateCode($pos),
            'supply_type' => $supplyType,
            'gst_category' => $category,
            'is_reverse_charge' => (bool)$invoice->is_reverse_charge,
            'taxable_amount' => floatval($invoice->sub_total ?? ($invoice->taxable_amount ?? 0)),
            'cgst_amount' => floatval($invoice->cgst_amount ?? 0),
            'sgst_amount' => floatval($invoice->sgst_amount ?? 0),
            'igst_amount' => floatval($invoice->igst_amount ?? 0),
            'cess_amount' => floatval($invoice->cess_amount ?? 0),
            'total_tax_amount' => floatval(($invoice->cgst_amount ?? 0) + ($invoice->sgst_amount ?? 0) + ($invoice->igst_amount ?? 0) + ($invoice->cess_amount ?? 0)),
            'total_document_value' => floatval($invoice->grand_total),
            'lines_snapshot_json' => json_encode($lines),
        ]);
    }

    /**
     * Create or retrieve immutable tax snapshot for a Purchase Bill
     */
    public static function createSnapshotForPurchase(Purchase $purchase): GSTDocumentSnapshot
    {
        $existing = GSTDocumentSnapshot::where('company_id', $purchase->company_id)
            ->where('document_type', 'PURCHASE')
            ->where('document_id', $purchase->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $company = Company::find($purchase->company_id);
        $buyerGstin = $company ? $company->gstin : null;
        $buyerState = $company ? ($company->state_code ?: '27') : '27';

        $supplier = $purchase->supplier;
        $sellerGstin = $supplier ? $supplier->gstin : null;
        $sellerState = $supplier ? ($supplier->state_code ?: ($supplier->state ?: '27')) : '27';
        $pos = $buyerState;

        $supplyType = PlaceOfSupplyService::determineSupplyType($sellerState, $pos);
        $lines = $purchase->items ? $purchase->items->toArray() : [];

        return GSTDocumentSnapshot::create([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'document_type' => 'PURCHASE',
            'document_id' => $purchase->id,
            'document_number' => $purchase->purchase_number,
            'document_date' => $purchase->purchase_date ?: date('Y-m-d'),
            'seller_gstin' => $sellerGstin,
            'seller_state_code' => PlaceOfSupplyService::normalizeStateCode($sellerState),
            'buyer_gstin' => $buyerGstin,
            'buyer_state_code' => PlaceOfSupplyService::normalizeStateCode($buyerState),
            'place_of_supply' => PlaceOfSupplyService::normalizeStateCode($pos),
            'supply_type' => $supplyType,
            'gst_category' => 'B2B',
            'is_reverse_charge' => (bool)$purchase->is_reverse_charge,
            'taxable_amount' => floatval($purchase->sub_total ?? ($purchase->taxable_amount ?? 0)),
            'cgst_amount' => floatval($purchase->cgst_amount ?? 0),
            'sgst_amount' => floatval($purchase->sgst_amount ?? 0),
            'igst_amount' => floatval($purchase->igst_amount ?? 0),
            'cess_amount' => floatval($purchase->cess_amount ?? 0),
            'total_tax_amount' => floatval(($purchase->cgst_amount ?? 0) + ($purchase->sgst_amount ?? 0) + ($purchase->igst_amount ?? 0) + ($purchase->cess_amount ?? 0)),
            'total_document_value' => floatval($purchase->grand_total),
            'lines_snapshot_json' => json_encode($lines),
        ]);
    }

    /**
     * Create or retrieve immutable tax snapshot for a Sales Credit Note
     */
    public static function createSnapshotForCreditNote(CreditNote $cn): GSTDocumentSnapshot
    {
        $existing = GSTDocumentSnapshot::where('company_id', $cn->company_id)
            ->where('document_type', 'CREDIT_NOTE')
            ->where('document_id', $cn->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $customer = $cn->customer;
        $buyerGstin = $customer ? $customer->gstin : null;
        $category = !empty($buyerGstin) ? 'CDNR' : 'CDNUR';

        return GSTDocumentSnapshot::create([
            'company_id' => $cn->company_id,
            'branch_id' => $cn->branch_id,
            'document_type' => 'CREDIT_NOTE',
            'document_id' => $cn->id,
            'document_number' => $cn->credit_note_number,
            'document_date' => $cn->credit_note_date ?: date('Y-m-d'),
            'seller_gstin' => null,
            'seller_state_code' => '27',
            'buyer_gstin' => $buyerGstin,
            'buyer_state_code' => '27',
            'place_of_supply' => '27',
            'supply_type' => 'INTRA_STATE',
            'gst_category' => $category,
            'is_reverse_charge' => false,
            'taxable_amount' => floatval($cn->taxable_amount ?: $cn->amount),
            'cgst_amount' => floatval($cn->cgst_amount ?? 0),
            'sgst_amount' => floatval($cn->sgst_amount ?? 0),
            'igst_amount' => floatval($cn->igst_amount ?? 0),
            'cess_amount' => floatval($cn->cess_amount ?? 0),
            'total_tax_amount' => floatval(($cn->cgst_amount ?? 0) + ($cn->sgst_amount ?? 0) + ($cn->igst_amount ?? 0) + ($cn->cess_amount ?? 0)),
            'total_document_value' => floatval($cn->amount),
            'lines_snapshot_json' => json_encode([]),
        ]);
    }

    /**
     * Create or retrieve immutable tax snapshot for a Purchase Debit Note
     */
    public static function createSnapshotForDebitNote(DebitNote $dn): GSTDocumentSnapshot
    {
        $existing = GSTDocumentSnapshot::where('company_id', $dn->company_id)
            ->where('document_type', 'DEBIT_NOTE')
            ->where('document_id', $dn->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return GSTDocumentSnapshot::create([
            'company_id' => $dn->company_id,
            'branch_id' => $dn->branch_id,
            'document_type' => 'DEBIT_NOTE',
            'document_id' => $dn->id,
            'document_number' => $dn->debit_note_number,
            'document_date' => $dn->debit_note_date ?: date('Y-m-d'),
            'seller_gstin' => null,
            'seller_state_code' => '27',
            'buyer_gstin' => null,
            'buyer_state_code' => '27',
            'place_of_supply' => '27',
            'supply_type' => 'INTRA_STATE',
            'gst_category' => 'CDNR',
            'is_reverse_charge' => false,
            'taxable_amount' => floatval($dn->taxable_amount ?: $dn->amount),
            'cgst_amount' => floatval($dn->cgst_amount ?? 0),
            'sgst_amount' => floatval($dn->sgst_amount ?? 0),
            'igst_amount' => floatval($dn->igst_amount ?? 0),
            'cess_amount' => floatval($dn->cess_amount ?? 0),
            'total_tax_amount' => floatval(($dn->cgst_amount ?? 0) + ($dn->sgst_amount ?? 0) + ($dn->igst_amount ?? 0) + ($dn->cess_amount ?? 0)),
            'total_document_value' => floatval($dn->amount),
            'lines_snapshot_json' => json_encode([]),
        ]);
    }
}
