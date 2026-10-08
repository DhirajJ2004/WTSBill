<?php

namespace App\Automation\WhatsApp;

interface WhatsAppProviderInterface
{
    public function send(string $phone, string $message, ?string $documentUrl = null): array;
    public function isConfigured(): bool;
    public function getProviderName(): string;
}
