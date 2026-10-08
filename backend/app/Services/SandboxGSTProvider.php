<?php

namespace App\Services;

class SandboxGSTProvider implements GSTProviderInterface
{
    /**
     * Deterministic Sandbox IRN generation simulation
     */
    public function generateIRN(array $payload, bool $isSandbox = true): array
    {
        $sellerGstin = $payload['SellerDtls']['Gstin'] ?? '27TESTS1234F1Z5';
        $docNo = $payload['DocDtls']['No'] ?? 'INV-001';
        $docDt = $payload['DocDtls']['Dt'] ?? date('d/m/Y');
        $docTyp = $payload['DocDtls']['Typ'] ?? 'INV';

        // Deterministic SHA-256 IRN hash
        $irnSeed = "{$sellerGstin}:{$docTyp}:{$docNo}:{$docDt}";
        $irn = hash('sha256', $irnSeed);
        $ackNo = '1' . str_pad(mt_rand(1000000000, 9999999999), 15, '0', STR_PAD_LEFT);
        $ackDate = date('Y-m-d H:i:s');

        // Signed QR mock string (containing basic metadata for QR display)
        $qrData = json_encode([
            'sellerGstin' => $sellerGstin,
            'buyerGstin' => $payload['BuyerDtls']['Gstin'] ?? 'UNREGISTERED',
            'docNo' => $docNo,
            'docDt' => $docDt,
            'totVal' => $payload['ValDtls']['TotInvVal'] ?? 0,
            'irn' => $irn,
            'ackNo' => $ackNo,
            'mode' => 'SANDBOX_SIMULATION',
        ]);

        return [
            'success' => true,
            'status' => 'GENERATED',
            'is_sandbox' => true,
            'irn' => $irn,
            'ack_no' => $ackNo,
            'ack_date' => $ackDate,
            'signed_invoice' => base64_encode(json_encode($payload)),
            'signed_qr_data' => base64_encode($qrData),
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($irn),
            'info_message' => 'E-Invoice generated in SANDBOX TEST MODE.',
        ];
    }

    /**
     * Sandbox IRN cancellation simulation
     */
    public function cancelIRN(string $irn, string $reason, string $remarks, bool $isSandbox = true): array
    {
        return [
            'success' => true,
            'status' => 'CANCELLED',
            'is_sandbox' => true,
            'irn' => $irn,
            'cancel_date' => date('Y-m-d H:i:s'),
            'info_message' => 'IRN cancelled successfully in SANDBOX mode.',
        ];
    }

    /**
     * Deterministic Sandbox E-Way Bill generation simulation
     */
    public function generateEWayBill(array $payload, bool $isSandbox = true): array
    {
        $ewbNo = '27' . str_pad(mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        $ewbDate = date('Y-m-d H:i:s');
        $validUntil = date('Y-m-d 23:59:59', strtotime('+1 day'));

        return [
            'success' => true,
            'status' => 'GENERATED',
            'is_sandbox' => true,
            'ewb_number' => $ewbNo,
            'ewb_date' => $ewbDate,
            'valid_until' => $validUntil,
            'info_message' => 'E-Way Bill generated in SANDBOX TEST MODE.',
        ];
    }

    /**
     * Sandbox E-Way Bill cancellation simulation
     */
    public function cancelEWayBill(string $ewbNo, string $reason, string $remarks, bool $isSandbox = true): array
    {
        return [
            'success' => true,
            'status' => 'CANCELLED',
            'is_sandbox' => true,
            'ewb_number' => $ewbNo,
            'cancel_date' => date('Y-m-d H:i:s'),
            'info_message' => 'E-Way Bill cancelled successfully in SANDBOX mode.',
        ];
    }
}
