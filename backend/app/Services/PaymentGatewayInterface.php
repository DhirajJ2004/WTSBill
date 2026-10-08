<?php

namespace App\Services;

interface PaymentGatewayInterface
{
    /**
     * Create a payment link with the gateway provider
     */
    public function createPaymentLink(array $data): array;

    /**
     * Get payment status from gateway provider
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Verify payment signature (HMAC-SHA256)
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature, ?string $secret = null): bool;

    /**
     * Process refund via gateway
     */
    public function refundPayment(string $paymentId, float $amount, ?string $reason = null): array;

    /**
     * Check if provider is currently in Sandbox/Simulation mode
     */
    public function isSandbox(): bool;
}
