<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\EWayBill;
use App\Models\EWayBillStatusHistory;
use App\Models\GSTConfiguration;
use App\Models\Company;

class EWayBillService
{
    /**
     * Check if an invoice is eligible for E-Way Bill generation
     */
    public static function checkEligibility(Invoice $invoice): array
    {
        $config = GSTConfigurationService::getOrCreateConfiguration($invoice->company_id, $invoice->branch_id);

        $reasons = [];
        $isEligible = true;

        if ($invoice->status !== 'POSTED') {
            $isEligible = false;
            $reasons[] = 'Invoice must be POSTED to generate an E-Way Bill.';
        }

        // Check value threshold (Default ₹50,000)
        $threshold = floatval($config->eway_threshold ?: 50000.0);
        if (floatval($invoice->grand_total) < $threshold) {
            $isEligible = false;
            $reasons[] = "Invoice total (₹{$invoice->grand_total}) is below the standard E-Way Bill threshold of ₹{$threshold}.";
        }

        // Check if invoice contains physical goods (not purely 99... SAC services)
        $items = $invoice->items;
        $hasGoods = false;
        if ($items && $items->count() > 0) {
            foreach ($items as $it) {
                $hsn = trim($it->hsn_code ?? ($it->hsn_sac ?? ''));
                if (!str_starts_with($hsn, '99')) {
                    $hasGoods = true;
                    break;
                }
            }
        }

        if (!$hasGoods) {
            $isEligible = false;
            $reasons[] = 'E-Way Bill is not applicable for pure Service transactions (SAC codes starting with 99).';
        }

        return [
            'is_eligible' => $isEligible,
            'reasons' => $reasons,
            'threshold' => $threshold,
            'environment' => $config->api_environment,
            'is_sandbox' => ($config->api_environment !== 'PRODUCTION'),
        ];
    }

    /**
     * Validate transport metadata
     */
    public static function validateTransportDetails(array $data): array
    {
        $errors = [];
        $mode = strtoupper(trim($data['transport_mode'] ?? 'ROAD'));

        if (!in_array($mode, ['ROAD', 'RAIL', 'AIR', 'SHIP'], true)) {
            $errors[] = 'Invalid transport mode. Expected ROAD, RAIL, AIR, or SHIP.';
        }

        if ($mode === 'ROAD') {
            $vehNo = trim($data['vehicle_no'] ?? '');
            $transId = trim($data['transporter_id'] ?? '');
            if (empty($vehNo) && empty($transId)) {
                $errors[] = 'Either Vehicle Number or Transporter ID (GSTIN) is required for ROAD transport.';
            }
        } else {
            $docNo = trim($data['transport_doc_no'] ?? '');
            if (empty($docNo)) {
                $errors[] = "Transport Document Number (RR/Airway Bill/Bill of Lading) is required for {$mode} transport.";
            }
        }

        $fromPin = trim($data['from_pincode'] ?? '');
        $toPin = trim($data['to_pincode'] ?? '');
        if (!empty($fromPin) && !preg_match('/^[1-9][0-9]{5}$/', $fromPin)) {
            $errors[] = 'From Pincode must be a 6-digit Indian Postal Code.';
        }
        if (!empty($toPin) && !preg_match('/^[1-9][0-9]{5}$/', $toPin)) {
            $errors[] = 'To Pincode must be a 6-digit Indian Postal Code.';
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Generate E-Way Bill
     */
    public static function generateEWayBill(Invoice $invoice, array $transportData, ?string $userName = 'Admin'): EWayBill
    {
        $config = GSTConfigurationService::getOrCreateConfiguration($invoice->company_id, $invoice->branch_id);
        $isSandbox = ($config->api_environment !== 'PRODUCTION');

        $eligibility = static::checkEligibility($invoice);
        if (!$eligibility['is_eligible']) {
            throw new \InvalidArgumentException('Invoice not eligible for E-Way Bill: ' . implode(' ', $eligibility['reasons']));
        }

        $transValidation = static::validateTransportDetails($transportData);
        if (!$transValidation['is_valid']) {
            throw new \InvalidArgumentException('Transport validation failed: ' . implode(' ', $transValidation['errors']));
        }

        $mode = strtoupper(trim($transportData['transport_mode'] ?? 'ROAD'));
        $provider = $isSandbox ? new SandboxGSTProvider() : new ProductionGSTProvider();

        $payload = [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'grand_total' => $invoice->grand_total,
            'transport_mode' => $mode,
            'vehicle_no' => $transportData['vehicle_no'] ?? null,
            'transporter_id' => $transportData['transporter_id'] ?? null,
            'from_pincode' => $transportData['from_pincode'] ?? null,
            'to_pincode' => $transportData['to_pincode'] ?? null,
        ];

        $res = $provider->generateEWayBill($payload, $isSandbox);

        if (!$res['success']) {
            throw new \RuntimeException('E-Way Bill generation failed: ' . ($res['error_message'] ?? 'Service error'));
        }

        $ewb = EWayBill::create([
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'ewb_number' => $res['ewb_number'],
            'ewb_date' => $res['ewb_date'],
            'valid_until' => $res['valid_until'],
            'status' => 'GENERATED',
            'transport_mode' => $mode,
            'transporter_id' => $transportData['transporter_id'] ?? null,
            'transporter_name' => $transportData['transporter_name'] ?? null,
            'transport_doc_no' => $transportData['transport_doc_no'] ?? null,
            'transport_doc_date' => $transportData['transport_doc_date'] ?? null,
            'vehicle_no' => $transportData['vehicle_no'] ?? null,
            'vehicle_type' => $transportData['vehicle_type'] ?? 'REGULAR',
            'from_pincode' => $transportData['from_pincode'] ?? null,
            'to_pincode' => $transportData['to_pincode'] ?? null,
            'distance_km' => intval($transportData['distance_km'] ?? 50),
            'is_sandbox' => $isSandbox,
            'generated_by' => $userName,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        EWayBillStatusHistory::create([
            'e_way_bill_id' => $ewb->id,
            'status' => 'GENERATED',
            'remarks' => $isSandbox ? 'Generated in SANDBOX mode' : 'Generated via live Government Portal',
            'created_by' => $userName,
        ]);

        AuditLogService::log(
            $invoice->company_id,
            $userName,
            'EWAYBILL_GENERATE',
            'EWayBill',
            $ewb->id,
            "Generated E-Way Bill #{$ewb->ewb_number} for Invoice #{$invoice->invoice_number} [Mode: " . ($isSandbox ? 'SANDBOX' : 'LIVE') . "]"
        );

        return $ewb;
    }

    /**
     * Cancel an existing E-Way Bill
     */
    public static function cancelEWayBill(int $ewayBillId, string $reason, string $remarks, ?string $userName = 'Admin'): EWayBill
    {
        $ewb = EWayBill::findOrFail($ewayBillId);

        if ($ewb->status === 'CANCELLED') {
            throw new \InvalidArgumentException('E-Way Bill is already cancelled.');
        }
        if ($ewb->status !== 'GENERATED') {
            throw new \InvalidArgumentException('Only active GENERATED E-Way Bills can be cancelled.');
        }

        $isSandbox = (bool)$ewb->is_sandbox;
        $provider = $isSandbox ? new SandboxGSTProvider() : new ProductionGSTProvider();
        $res = $provider->cancelEWayBill($ewb->ewb_number, $reason, $remarks, $isSandbox);

        if (!$res['success']) {
            throw new \RuntimeException('Failed to cancel E-Way Bill: ' . ($res['error_message'] ?? 'Service error'));
        }

        $ewb->update([
            'status' => 'CANCELLED',
            'cancel_reason' => $reason,
            'cancel_remarks' => $remarks,
            'cancel_date' => date('Y-m-d H:i:s'),
        ]);

        EWayBillStatusHistory::create([
            'e_way_bill_id' => $ewb->id,
            'status' => 'CANCELLED',
            'remarks' => "Reason: {$reason} - {$remarks}",
            'created_by' => $userName,
        ]);

        AuditLogService::log(
            $ewb->company_id,
            $userName,
            'EWAYBILL_CANCEL',
            'EWayBill',
            $ewb->id,
            "Cancelled E-Way Bill #{$ewb->ewb_number}: {$reason} - {$remarks}"
        );

        return $ewb;
    }
}
