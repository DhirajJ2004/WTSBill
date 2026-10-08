<?php

namespace App\PaymentGateways\Interfaces;

interface PaymentGatewayProviderInterface
{
    public function getProviderName(): string;

    /**
     * Create payment link or order on gateway.
     */
    public function createPaymentLink(int $companyId, float $amount, string $description, array $metadata = []): array;

    /**
     * Verify payment signature or status from gateway.
     */
    public function verifyPayment(int $companyId, string $paymentId, string $orderId, string $signature): bool;

    /**
     * Issue refund through gateway.
     */
    public function refundPayment(int $companyId, string $paymentId, float $amount, string $reason): array;

    /**
     * Verify webhook signature and extract standardized event.
     */
    public function parseWebhook(string $rawPayload, string $signature, string $secret): ?array;
}
