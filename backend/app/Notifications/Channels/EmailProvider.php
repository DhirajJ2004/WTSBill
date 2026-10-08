<?php

namespace App\Notifications\Channels;

use App\Models\BusinessCommunicationSetting;

class EmailProvider implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'EMAIL';
    }

    public function isConfigured(int $companyId): bool
    {
        $setting = BusinessCommunicationSetting::where('company_id', $companyId)->first();
        return $setting ? (bool)$setting->email_configured : false;
    }

    public function send(int $companyId, string $recipient, ?string $subject, string $content, array $metadata = []): array
    {
        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'FAILED',
                'error' => "Invalid email recipient address: '{$recipient}'",
            ];
        }

        // Provider-independent execution: in simulated / sandbox mode unless custom SMTP is integrated
        $messageId = 'EML-' . date('YmdHis') . '-' . substr(md5($recipient . $content . uniqid()), 0, 10);

        return [
            'success' => true,
            'provider_message_id' => $messageId,
            'status' => 'SENT',
            'error' => null,
        ];
    }
}
