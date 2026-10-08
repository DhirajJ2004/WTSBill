<?php

namespace App\DocumentEngine\Sharing;

interface WhatsAppProviderInterface
{
    public function sendMessage(string $phoneNumber, string $message, ?string $documentUrl = null): array;
}
