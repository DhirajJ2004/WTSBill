<?php

namespace App\Services;

interface GSTProviderInterface
{
    /**
     * Submit e-Invoice payload to generate IRN & signed QR
     */
    public function generateIRN(array $payload, bool $isSandbox = true): array;

    /**
     * Cancel an active IRN
     */
    public function cancelIRN(string $irn, string $reason, string $remarks, bool $isSandbox = true): array;

    /**
     * Generate E-Way Bill
     */
    public function generateEWayBill(array $payload, bool $isSandbox = true): array;

    /**
     * Cancel an active E-Way Bill
     */
    public function cancelEWayBill(string $ewbNo, string $reason, string $remarks, bool $isSandbox = true): array;
}
