<?php

namespace App\Automation\Reminders;

use App\Models\Reminder;
use App\Models\Invoice;
use App\Automation\Notifications\NotificationEngine;
use App\Automation\Email\EmailService;
use App\Automation\WhatsApp\WhatsAppService;
use Exception;

class ReminderEngine
{
    /**
     * Create or schedule a reminder
     */
    public static function createReminder(
        int $companyId,
        string $reminderType,
        string $entityType,
        int $entityId,
        ?string $recipient = null,
        ?string $dueDate = null,
        string $scheduleType = 'BEFORE_DUE',
        int $offsetDays = 0,
        string $frequency = 'ONCE',
        int $maxCount = 3,
        string $stopCondition = 'ON_PAID',
        string $priority = 'NORMAL',
        ?string $notes = null,
        ?int $branchId = null
    ): Reminder {
        $baseDate = $dueDate ?: date('Y-m-d');
        $reminderDate = self::calculateReminderDate($baseDate, $scheduleType, $offsetDays);

        return Reminder::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'reminder_type' => $reminderType,
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'recipient' => $recipient,
            'due_date' => $dueDate,
            'reminder_date' => $reminderDate,
            'schedule_type' => $scheduleType,
            'offset_days' => $offsetDays,
            'frequency' => $frequency,
            'max_count' => $maxCount,
            'sent_count' => 0,
            'stop_condition' => $stopCondition,
            'status' => 'SCHEDULED',
            'priority' => strtoupper($priority),
            'notes' => $notes,
        ]);
    }

    /**
     * Calculate reminder trigger date
     */
    public static function calculateReminderDate(string $baseDate, string $scheduleType, int $offsetDays): string
    {
        $time = strtotime($baseDate);
        switch (strtoupper($scheduleType)) {
            case 'BEFORE_DUE':
                return date('Y-m-d', strtotime("-{$offsetDays} days", $time));
            case 'AFTER_DUE':
                return date('Y-m-d', strtotime("+{$offsetDays} days", $time));
            case 'ON_DUE':
            default:
                return $baseDate;
        }
    }

    /**
     * Process due reminders for a company
     */
    public static function processDueReminders(int $companyId, ?string $asOfDate = null): array
    {
        $today = $asOfDate ?: date('Y-m-d');

        $dueReminders = Reminder::where('company_id', $companyId)
            ->whereIn('status', ['SCHEDULED', 'PENDING'])
            ->whereDate('reminder_date', '<=', $today)
            ->get();

        $processed = [];

        foreach ($dueReminders as $rem) {
            // Check Stop Conditions
            if (self::shouldStopReminder($rem)) {
                $rem->status = 'COMPLETED';
                $rem->notes = ($rem->notes ? $rem->notes . " | " : "") . "Auto-stopped because entity satisfied stop condition ({$rem->stop_condition}).";
                $rem->save();
                $processed[] = ['id' => $rem->id, 'status' => 'STOPPED'];
                continue;
            }

            // Check Max Count
            if ($rem->sent_count >= $rem->max_count) {
                $rem->status = 'COMPLETED';
                $rem->save();
                $processed[] = ['id' => $rem->id, 'status' => 'LIMIT_REACHED'];
                continue;
            }

            // Execute reminder notification
            NotificationEngine::createNotification(
                companyId: $companyId,
                category: 'payments',
                title: "Reminder: {$rem->reminder_type}",
                message: $rem->notes ?: "Due reminder for {$rem->entity_type} #{$rem->entity_id}",
                entityType: $rem->entity_type,
                entityId: $rem->entity_id,
                priority: $rem->priority
            );

            $rem->sent_count += 1;

            if ($rem->frequency === 'ONCE' || $rem->sent_count >= $rem->max_count) {
                $rem->status = 'SENT';
            } else {
                // Schedule next occurrence based on frequency
                $daysToAdd = 1;
                if ($rem->frequency === 'WEEKLY') $daysToAdd = 7;
                elseif ($rem->frequency === 'EVERY_X_DAYS') $daysToAdd = max(1, $rem->offset_days);

                $curDateStr = $rem->reminder_date instanceof \DateTimeInterface ? $rem->reminder_date->format('Y-m-d') : strval($rem->reminder_date);
                $rem->reminder_date = date('Y-m-d', strtotime("+{$daysToAdd} days", strtotime($curDateStr)));
            }

            $rem->save();
            $processed[] = ['id' => $rem->id, 'status' => 'SENT', 'count' => $rem->sent_count];
        }

        return $processed;
    }

    /**
     * Check if reminder stop condition is met
     */
    protected static function shouldStopReminder(Reminder $rem): bool
    {
        if ($rem->stop_condition === 'ON_PAID' && $rem->entity_type === 'INVOICE' && $rem->entity_id) {
            $inv = Invoice::where('company_id', $rem->company_id)->find($rem->entity_id);
            if ($inv && in_array($inv->status, ['PAID', 'CANCELLED'])) {
                return true;
            }
        }
        return false;
    }
}
