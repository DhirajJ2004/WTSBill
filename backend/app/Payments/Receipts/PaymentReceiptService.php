<?php

namespace App\Payments\Receipts;

use App\Models\Payment;
use App\Models\Company;
use App\Services\DocumentService;
use App\Services\DocumentNumberingService;

class PaymentReceiptService
{
    /**
     * Generate payment receipt document via centralized Document Engine.
     */
    public static function generateReceiptDocument(Payment $payment): array
    {
        $company = Company::find($payment->company_id);
        $customer = $payment->customer;
        $supplier = $payment->supplier;
        $party = $customer ?: $supplier;

        $allocations = $payment->allocations()->with('invoice')->get();
        $invoiceDetails = [];

        foreach ($allocations as $alloc) {
            $inv = $alloc->invoice;
            $invoiceDetails[] = [
                'invoice_number' => $inv?->invoice_number ?: "INV-{$alloc->document_id}",
                'invoice_date' => $inv?->invoice_date,
                'allocated_amount' => $alloc->allocated_amount,
                'invoice_total' => $inv?->grand_total,
                'balance_remaining' => $inv?->amount_due,
            ];
        }

        $receiptData = [
            'document_type' => 'PAYMENT_RECEIPT',
            'receipt_number' => $payment->receipt_number ?: $payment->payment_number,
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date,
            'amount' => $payment->amount,
            'payment_mode' => $payment->payment_mode,
            'reference_number' => $payment->reference_number ?: $payment->transaction_reference,
            'party_name' => $party?->name ?: 'Party',
            'party_phone' => $party?->phone,
            'party_email' => $party?->email,
            'party_gstin' => $party?->gstin,
            'company_name' => $company?->name ?: 'WTSBill Business',
            'company_gstin' => $company?->gstin,
            'allocations' => $invoiceDetails,
            'unallocated_amount' => $payment->unallocated_amount,
            'notes' => $payment->notes,
        ];

        return $receiptData;
    }
}
