<?php

namespace App\Notifications\Channels;

use App\Models\BusinessCommunicationSetting;

class SMSProvider implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'SMS';
    }

    public function isConfigured(int $companyId): bool
    {
        $setting = BusinessCommunicationSetting::where('company_id', $companyId)->first();
        return $setting ? (bool)$setting->sms_configured : false;
    }

    public function send(int $companyId, string $recipient, ?string $subject, string $content, array $metadata = []): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $recipient);
        if (strlen($cleanPhone) < 10) {
            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'FAILED',
                'error' => "Invalid SMS phone number: '{$recipient}'",
            ];
        }

        $messageId = 'SMS-' . date('YmdHis') . '-' . substr(md5($recipient . uniqid()), 0, 10);

        return [
            'success' => true,
            'provider_message_id' => $messageId,
            'status' => 'SENT',
            'error' => null,
        ];
    }
}
