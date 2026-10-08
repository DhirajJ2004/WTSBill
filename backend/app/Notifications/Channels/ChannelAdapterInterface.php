<?php

namespace App\Notifications\Channels;

interface ChannelAdapterInterface
{
    /**
     * Get unique channel name (IN_APP, EMAIL, WHATSAPP, SMS).
     */
    public function getChannelName(): string;

    /**
     * Validate if the channel is configured for the company.
     */
    public function isConfigured(int $companyId): bool;

    /**
     * Send notification through the channel adapter.
     *
     * @param int $companyId
     * @param string $recipient
     * @param string|null $subject
     * @param string $content
     * @param array $metadata
     * @return array ['success' => bool, 'provider_message_id' => string|null, 'error' => string|null, 'status' => string]
     */
    public function send(int $companyId, string $recipient, ?string $subject, string $content, array $metadata = []): array;
}
