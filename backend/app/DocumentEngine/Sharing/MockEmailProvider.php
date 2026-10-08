<?php

namespace App\DocumentEngine\Sharing;

class MockEmailProvider implements EmailProviderInterface
{
    public function sendDocument(string $toEmail, string $subject, string $body, string $attachmentPdfContent, string $attachmentFileName): array
    {
        return [
            'status' => 'success',
            'provider' => 'MOCK_EMAIL',
            'message_id' => 'msg_mail_' . bin2hex(random_bytes(8)),
            'recipient' => $toEmail,
            'subject' => $subject,
            'attachment' => $attachmentFileName,
            'attachment_size' => strlen($attachmentPdfContent),
            'sent_at' => date('Y-m-d H:i:s'),
            'is_sandbox' => true,
        ];
    }
}
