<?php

namespace App\Notifications\Services;

use App\Models\NotificationLog;
use App\Models\BusinessCommunicationSetting;
use App\Notifications\Channels\ChannelAdapterInterface;
use App\Notifications\Channels\InAppNotificationProvider;
use App\Notifications\Channels\EmailProvider;
use App\Notifications\Channels\WhatsAppProvider;
use App\Notifications\Channels\SMSProvider;
use Carbon\Carbon;
use Exception;

class NotificationDeliveryService
{
    protected static array $adapters = [];

    public static function getAdapter(string $channel): ChannelAdapterInterface
    {
        $channel = strtoupper($channel);
        if (!isset(self::$adapters[$channel])) {
            self::$adapters[$channel] = match ($channel) {
                'IN_APP' => new InAppNotificationProvider(),
                'EMAIL' => new EmailProvider(),
                'WHATSAPP' => new WhatsAppProvider(),
                'SMS' => new SMSProvider(),
                default => throw new Exception("Unsupported notification channel: {$channel}"),
            };
        }
        return self::$adapters[$channel];
    }

    /**
     * Check if currently in configured quiet hours for business.
     */
    public static function isQuietHours(int $companyId): bool
    {
        $setting = BusinessCommunicationSetting::where('company_id', $companyId)->first();
        if (!$setting || empty($setting->quiet_hours_start) || empty($setting->quiet_hours_end)) {
            return false;
        }

        $tz = $setting->timezone ?: 'Asia/Kolkata';
        $now = Carbon::now($tz);
        $currentTime = $now->format('H:i');

        $start = $setting->quiet_hours_start;
        $end = $setting->quiet_hours_end;

        if ($start > $end) {
            // Overnight quiet hours (e.g. 21:00 to 09:00)
            return ($currentTime >= $start || $currentTime <= $end);
        }

        return ($currentTime >= $start && $currentTime <= $end);
    }

    /**
     * Dispatch notification with idempotency check, logging, and retry backoff.
     */
    public static function deliver(
        int $companyId,
        string $eventType,
        string $entityType,
        int $entityId,
        string $channel,
        ?string $recipient,
        ?string $subject,
        string $content,
        ?int $templateId = null,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): NotificationLog {
        $channel = strtoupper($channel);

        // 1. Idempotency Check: Prevent duplicate sends
        $log = null;
        if ($idempotencyKey) {
            $existing = NotificationLog::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (in_array($existing->status, ['SENT', 'DELIVERED', 'PROCESSING', 'PENDING'])) {
                    return $existing;
                }
                $log = $existing;
                $log->attempt_count = $log->attempt_count + 1;
                $log->status = 'PROCESSING';
                $log->save();
            }
        }

        // 2. Quiet Hours check: Defer external channels (Email, WhatsApp, SMS) during quiet hours, but keep IN_APP
        if ($channel !== 'IN_APP' && self::isQuietHours($companyId)) {
            if ($log) {
                $log->update(['status' => 'PENDING', 'last_error' => 'Deferred: Currently within business quiet hours']);
                return $log;
            }
            return NotificationLog::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'channel' => $channel,
                'recipient' => $recipient,
                'template_id' => $templateId,
                'status' => 'PENDING',
                'idempotency_key' => $idempotencyKey,
                'last_error' => 'Deferred: Currently within business quiet hours',
                'attempt_count' => 0,
            ]);
        }

        // 3. Create or update log entry in PROCESSING status
        if (!$log) {
            $log = NotificationLog::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'channel' => $channel,
                'recipient' => $recipient,
                'template_id' => $templateId,
                'status' => 'PROCESSING',
                'idempotency_key' => $idempotencyKey,
                'attempt_count' => 1,
            ]);
        }

        try {
            $adapter = self::getAdapter($channel);
            $response = $adapter->send($companyId, $recipient ?: '', $subject, $content, array_merge($metadata, [
                'branch_id' => $branchId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]));

            if ($response['success']) {
                $log->update([
                    'status' => $response['status'] ?: 'SENT',
                    'provider_message_id' => $response['provider_message_id'] ?? null,
                    'sent_at' => Carbon::now(),
                    'last_error' => null,
                ]);
            } else {
                $log->update([
                    'status' => 'FAILED',
                    'last_error' => $response['error'] ?? 'Unknown delivery failure',
                ]);
            }
        } catch (Exception $e) {
            $log->update([
                'status' => 'FAILED',
                'last_error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Retry failed notification.
     */
    public static function retry(int $logId): NotificationLog
    {
        $log = NotificationLog::findOrFail($logId);
        if ($log->status === 'SENT' || $log->status === 'DELIVERED') {
            return $log;
        }

        $log->attempt_count = $log->attempt_count + 1;
        $log->status = 'PROCESSING';
        $log->save();

        try {
            $adapter = self::getAdapter($log->channel);
            $response = $adapter->send($log->company_id, $log->recipient ?: '', null, 'Retrying notification', [
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
            ]);

            if ($response['success']) {
                $log->update([
                    'status' => $response['status'] ?: 'SENT',
                    'provider_message_id' => $response['provider_message_id'] ?? null,
                    'sent_at' => Carbon::now(),
                    'last_error' => null,
                ]);
            } else {
                $log->update([
                    'status' => 'FAILED',
                    'last_error' => $response['error'] ?? 'Retry attempt failed',
                ]);
            }
        } catch (Exception $e) {
            $log->update([
                'status' => 'FAILED',
                'last_error' => $e->getMessage(),
            ]);
        }

        return $log;
    }
}
