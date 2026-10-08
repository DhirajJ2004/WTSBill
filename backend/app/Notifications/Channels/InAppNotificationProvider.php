<?php

namespace App\Notifications\Channels;

use App\Models\InAppNotification;

class InAppNotificationProvider implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'IN_APP';
    }

    public function isConfigured(int $companyId): bool
    {
        return true; // Always active natively
    }

    public function send(int $companyId, string $recipient, ?string $subject, string $content, array $metadata = []): array
    {
        $userId = is_numeric($recipient) ? intval($recipient) : ($metadata['user_id'] ?? null);

        $notification = InAppNotification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'category' => $metadata['category'] ?? 'SYSTEM',
            'priority' => $metadata['priority'] ?? 'NORMAL',
            'title' => $subject ?: 'Notification',
            'message' => $content,
            'entity_type' => $metadata['entity_type'] ?? null,
            'entity_id' => $metadata['entity_id'] ?? null,
            'is_read' => false,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'INAPP-' . $notification->id,
            'status' => 'DELIVERED',
            'error' => null,
        ];
    }
}
