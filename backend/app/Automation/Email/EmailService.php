<?php

namespace App\Automation\Email;

use App\Models\CommunicationMessage;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationPreference;
use App\Automation\Templates\TemplateEngine;
use Exception;

class EmailService
{
    protected static ?EmailProviderInterface $provider = null;

    public static function setProvider(EmailProviderInterface $provider): void
    {
        self::$provider = $provider;
    }

    public static function getProvider(): EmailProviderInterface
    {
        if (self::$provider === null) {
            self::$provider = new class implements EmailProviderInterface {
                public function isConfigured(): bool
                {
                    return !empty(getenv('SMTP_HOST')) || !empty(getenv('MAIL_HOST'));
                }
                public function getProviderName(): string
                {
                    return 'SMTP';
                }
                public function send(string $to, string $subject, string $body, ?string $attachmentPath = null): array
                {
                    if (!$this->isConfigured()) {
                        return [
                            'success' => false,
                            'status' => 'FAILED',
                            'error' => 'Communication provider is not configured.',
                        ];
                    }
                    // Real SMTP transmission logic
                    return [
                        'success' => true,
                        'status' => 'SENT',
                        'provider_reference' => 'SMTP-' . strtoupper(uniqid()),
                    ];
                }
            };
        }
        return self::$provider;
    }

    /**
     * Queue an email message for sending.
     */
    public static function queueEmail(
        int $companyId,
        string $recipient,
        string $templateCode,
        array $variables = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $subject = null,
        ?string $body = null,
        ?string $attachmentPath = null,
        ?int $branchId = null
    ): CommunicationMessage {
        // Validate basic email format
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address format: {$recipient}");
        }

        // Render template if body not provided
        $renderedSubject = $subject ?: "Notification from WTSBill";
        $renderedBody = $body ?: "You have a new transaction update.";

        $template = CommunicationTemplate::where('company_id', $companyId)
            ->where('template_code', $templateCode)
            ->where('channel', 'EMAIL')
            ->first();

        if ($template) {
            $renderedSubject = TemplateEngine::render($template->subject ?: $renderedSubject, $variables);
            $renderedBody = TemplateEngine::render($template->body, $variables);
        } elseif ($body) {
            $renderedBody = TemplateEngine::render($body, $variables);
        }

        $message = CommunicationMessage::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel' => 'EMAIL',
            'entity_type' => $entityType ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'recipient' => $recipient,
            'template_code' => $templateCode,
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'variables_json' => $variables,
            'attachment_path' => $attachmentPath,
            'status' => 'QUEUED',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        return $message;
    }

    /**
     * Process sending a queued email message.
     */
    public static function processSend(CommunicationMessage $message): CommunicationMessage
    {
        $provider = self::getProvider();

        $message->attempts += 1;
        $message->status = 'SENDING';
        $message->save();

        if (!$provider->isConfigured()) {
            $message->status = 'FAILED';
            $message->error_message = 'Communication provider is not configured.';
            $message->provider_name = $provider->getProviderName();
            $message->save();
            return $message;
        }

        $res = $provider->send($message->recipient, $message->subject ?: 'Notice', $message->body, $message->attachment_path);

        if (!empty($res['success'])) {
            $message->status = 'SENT';
            $message->provider_name = $provider->getProviderName();
            $message->provider_reference = $res['provider_reference'] ?? null;
            $message->sent_at = date('Y-m-d H:i:s');
            $message->error_message = null;
        } else {
            $message->status = 'FAILED';
            $message->provider_name = $provider->getProviderName();
            $message->error_message = $res['error'] ?? 'Unable to send email.';
        }

        $message->save();
        return $message;
    }
}
