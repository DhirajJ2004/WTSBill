<?php

namespace App\Services;

class PaymentGatewayService
{
    /**
     * Get active Payment Gateway instance
     */
    public static function getGateway(string $provider = 'SIMULATION', bool $forceSandbox = false): PaymentGatewayInterface
    {
        $env = $_ENV['PAYMENT_GATEWAY_ENV'] ?? 'SANDBOX';
        if ($forceSandbox || strtoupper($env) === 'SANDBOX' || strtoupper($provider) === 'SIMULATION') {
            return new SandboxPaymentGateway();
        }

        return new ProductionPaymentGateway($provider);
    }
}
