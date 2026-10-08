<?php

namespace App\DocumentEngine\Sharing;

class MockWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendMessage(string $phoneNumber, string $message, ?string $documentUrl = null): array
    {
        return [
            'status' => 'success',
            'provider' => 'MOCK_WHATSAPP',
            'message_id' => 'wamsg_' . bin2hex(random_bytes(8)),
            'phone' => $phoneNumber,
            'message' => $message,
            'document_url' => $documentUrl,
            'sent_at' => date('Y-m-d H:i:s'),
            'is_sandbox' => true,
        ];
    }
}
