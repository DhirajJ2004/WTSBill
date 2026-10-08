<?php

namespace App\DocumentEngine\Sharing;

interface EmailProviderInterface
{
    public function sendDocument(string $toEmail, string $subject, string $body, string $attachmentPdfContent, string $attachmentFileName): array;
}
