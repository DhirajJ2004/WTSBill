<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\EInvoice;
use App\Models\EInvoiceStatusHistory;
use App\Models\GSTConfiguration;
use App\Models\Company;

class EInvoiceService
{
    /**
     * Check if an invoice is eligible for GST e-Invoicing
     */
    public static function checkEligibility(Invoice $invoice): array
    {
        $config = GSTConfigurationService::getOrCreateConfiguration($invoice->company_id, $invoice->branch_id);
        $customer = $invoice->customer;

        $reasons = [];
        $isEligible = true;

        if (!$config->gst_registered || empty($config->gstin)) {
            $isEligible = false;
            $reasons[] = 'Seller business is not GST registered or missing GSTIN.';
        }

        if ($invoice->status !== 'POSTED') {
            $isEligible = false;
            $reasons[] = 'Invoice must be in POSTED status to generate an e-Invoice.';
        }

        if (!$customer || empty($customer->gstin)) {
            $isEligible = false;
            $reasons[] = 'Customer is unregistered (B2C). e-Invoicing is mandatory for B2B transactions with valid customer GSTIN.';
        }

        return [
            'is_eligible' => $isEligible,
            'reasons' => $reasons,
            'environment' => $config->api_environment,
            'is_sandbox' => ($config->api_environment !== 'PRODUCTION'),
        ];
    }

    /**
     * Pre-validate invoice data before submitting to e-Invoice portal
     */
    public static function preValidate(Invoice $invoice): array
    {
        $errors = [];

        $company = Company::find($invoice->company_id);
        if (!$company || !GSTINValidationService::isFormatValid($company->gstin ?? '')) {
            $errors[] = 'Valid Seller GSTIN is required.';
        }

        $customer = $invoice->customer;
        if (!$customer || !GSTINValidationService::isFormatValid($customer->gstin ?? '')) {
            $errors[] = 'Valid Buyer GSTIN is required for B2B e-Invoicing.';
        }

        if (empty($invoice->invoice_number)) {
            $errors[] = 'Invoice document number is required.';
        }

        $items = $invoice->items;
        if (!$items || $items->count() === 0) {
            $errors[] = 'Invoice must contain at least one line item.';
        } else {
            foreach ($items as $idx => $it) {
                $hsn = trim($it->hsn_code ?? ($it->hsn_sac ?? ''));
                if (empty($hsn)) {
                    $errors[] = "Line item #" . ($idx + 1) . " is missing HSN/SAC code.";
                }
                if (floatval($it->taxable_amount ?? 0) < 0) {
                    $errors[] = "Line item #" . ($idx + 1) . " has invalid negative taxable amount.";
                }
            }
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Generate e-Invoice IRN & QR
     */
    public static function generateEInvoice(Invoice $invoice, ?string $userName = 'Admin'): EInvoice
    {
        $config = GSTConfigurationService::getOrCreateConfiguration($invoice->company_id, $invoice->branch_id);
        $isSandbox = ($config->api_environment !== 'PRODUCTION');

        $eligibility = static::checkEligibility($invoice);
        if (!$eligibility['is_eligible']) {
            throw new \InvalidArgumentException('Invoice not eligible for e-Invoicing: ' . implode(' ', $eligibility['reasons']));
        }

        $validation = static::preValidate($invoice);
        if (!$validation['is_valid']) {
            throw new \InvalidArgumentException('e-Invoice pre-validation failed: ' . implode(' ', $validation['errors']));
        }

        // Prepare Snapshot and Payload
        $snapshot = GSTSnapshotService::createSnapshotForInvoice($invoice);
        $payload = EInvoicePayloadBuilder::buildPayload($invoice, $snapshot);

        // Provider Dispatch
        $provider = $isSandbox ? new SandboxGSTProvider() : new ProductionGSTProvider();
        $res = $provider->generateIRN($payload, $isSandbox);

        if (!$res['success']) {
            $eInvoice = EInvoice::updateOrCreate(
                ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id],
                [
                    'branch_id' => $invoice->branch_id,
                    'status' => 'FAILED',
                    'is_sandbox' => $isSandbox,
                    'error_message' => $res['error_message'] ?? 'Generation failed',
                ]
            );

            EInvoiceStatusHistory::create([
                'e_invoice_id' => $eInvoice->id,
                'status' => 'FAILED',
                'remarks' => $res['error_message'] ?? 'Generation failed',
                'created_by' => $userName,
            ]);

            throw new \RuntimeException('e-Invoice generation failed: ' . ($res['error_message'] ?? 'Service error'));
        }

        $eInvoice = EInvoice::updateOrCreate(
            ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id],
            [
                'branch_id' => $invoice->branch_id,
                'irn' => $res['irn'],
                'ack_no' => $res['ack_no'],
                'ack_date' => $res['ack_date'],
                'signed_invoice' => $res['signed_invoice'] ?? null,
                'signed_qr_data' => $res['signed_qr_data'] ?? null,
                'qr_code_url' => $res['qr_code_url'] ?? null,
                'status' => 'GENERATED',
                'is_sandbox' => $isSandbox,
                'error_message' => null,
                'generated_by' => $userName,
                'generated_at' => date('Y-m-d H:i:s'),
            ]
        );

        EInvoiceStatusHistory::create([
            'e_invoice_id' => $eInvoice->id,
            'status' => 'GENERATED',
            'remarks' => $isSandbox ? 'Generated in SANDBOX mode' : 'Generated via live Government Portal',
            'created_by' => $userName,
        ]);

        AuditLogService::log(
            $invoice->company_id,
            $userName,
            'EINVOICE_GENERATE',
            'EInvoice',
            $eInvoice->id,
            "Generated e-Invoice for Invoice #{$invoice->invoice_number} [IRN: " . substr($eInvoice->irn, 0, 16) . "...] [Mode: " . ($isSandbox ? 'SANDBOX' : 'LIVE') . "]"
        );

        return $eInvoice;
    }

    /**
     * Cancel an existing e-Invoice IRN
     */
    public static function cancelEInvoice(int $invoiceId, string $reason, string $remarks, ?string $userName = 'Admin'): EInvoice
    {
        $eInvoice = EInvoice::where('invoice_id', $invoiceId)->firstOrFail();

        if ($eInvoice->status === 'CANCELLED') {
            throw new \InvalidArgumentException('e-Invoice IRN is already cancelled.');
        }
        if ($eInvoice->status !== 'GENERATED') {
            throw new \InvalidArgumentException('Only active GENERATED e-Invoices can be cancelled.');
        }

        $isSandbox = (bool)$eInvoice->is_sandbox;
        $provider = $isSandbox ? new SandboxGSTProvider() : new ProductionGSTProvider();
        $res = $provider->cancelIRN($eInvoice->irn, $reason, $remarks, $isSandbox);

        if (!$res['success']) {
            throw new \RuntimeException('Failed to cancel IRN: ' . ($res['error_message'] ?? 'Service error'));
        }

        $eInvoice->update([
            'status' => 'CANCELLED',
            'cancel_reason' => $reason,
            'cancel_remarks' => $remarks,
            'cancel_date' => date('Y-m-d H:i:s'),
        ]);

        EInvoiceStatusHistory::create([
            'e_invoice_id' => $eInvoice->id,
            'status' => 'CANCELLED',
            'remarks' => "Reason: {$reason} - {$remarks}",
            'created_by' => $userName,
        ]);

        AuditLogService::log(
            $eInvoice->company_id,
            $userName,
            'EINVOICE_CANCEL',
            'EInvoice',
            $eInvoice->id,
            "Cancelled e-Invoice IRN for Invoice #{$invoiceId}: {$reason} - {$remarks}"
        );

        return $eInvoice;
    }
}
