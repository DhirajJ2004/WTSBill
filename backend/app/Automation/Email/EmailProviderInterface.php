<?php

namespace App\Automation\Email;

interface EmailProviderInterface
{
    public function send(string $to, string $subject, string $body, ?string $attachmentPath = null): array;
    public function isConfigured(): bool;
    public function getProviderName(): string;
}
