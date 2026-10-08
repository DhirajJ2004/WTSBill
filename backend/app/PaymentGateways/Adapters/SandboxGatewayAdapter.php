<?php

namespace App\PaymentGateways\Adapters;

use App\PaymentGateways\Interfaces\PaymentGatewayProviderInterface;

class SandboxGatewayAdapter implements PaymentGatewayProviderInterface
{
    public function getProviderName(): string
    {
        return 'SANDBOX';
    }

    public function createPaymentLink(int $companyId, float $amount, string $description, array $metadata = []): array
    {
        $orderId = 'order_sbx_' . bin2hex(random_bytes(8));
        return [
            'success' => true,
            'gateway_order_id' => $orderId,
            'amount' => $amount,
            'checkout_url' => "https://sandbox.wtsbill.in/pay/{$orderId}",
        ];
    }

    public function verifyPayment(int $companyId, string $paymentId, string $orderId, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, 'sandbox_secret_key_123');
        return hash_equals($expectedSignature, $signature);
    }

    public function refundPayment(int $companyId, string $paymentId, float $amount, string $reason): array
    {
        return [
            'success' => true,
            'refund_id' => 'rfnd_sbx_' . bin2hex(random_bytes(8)),
            'amount' => $amount,
            'status' => 'PROCESSED',
        ];
    }

    public function parseWebhook(string $rawPayload, string $signature, string $secret): ?array
    {
        $expected = hash_hmac('sha256', $rawPayload, $secret);
        if (!hash_equals($expected, $signature)) {
            return null; // Invalid signature
        }

        $decoded = json_decode($rawPayload, true);
        return $decoded ?: null;
    }
}
