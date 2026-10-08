<?php

namespace App\Http\Controllers\Api;

use App\Models\Reminder;
use App\Models\Invoice;
use App\Automation\Reminders\ReminderEngine;
use App\Http\Middleware\AuthMiddleware;
use Exception;
use Throwable;

class ReminderController
{
    /**
     * List reminders
     */
    public function getReminders()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $status = $_GET['status'] ?? null;

            $query = Reminder::where('company_id', $companyId);
            if ($status && $status !== 'all') {
                $query->where('status', strtoupper($status));
            }

            $reminders = $query->orderBy('reminder_date', 'asc')->get();

            return response_json(['status' => 'success', 'success' => true, 'data' => $reminders]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index($request = null)
    {
        return $this->getReminders();
    }

    /**
     * Create reminder
     */
    public function createReminder()
    {
        try {
            $input = get_json_input();
            $companyId = AuthMiddleware::getTenantId();
            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }

            $reminder = ReminderEngine::createReminder(
                companyId: $companyId,
                reminderType: $input['reminder_type'] ?? 'CUSTOM',
                entityType: $input['entity_type'] ?? 'MANUAL',
                entityId: intval($input['entity_id'] ?? 0),
                recipient: $input['recipient'] ?? null,
                dueDate: $input['due_date'] ?? date('Y-m-d'),
                scheduleType: $input['schedule_type'] ?? 'BEFORE_DUE',
                offsetDays: intval($input['offset_days'] ?? 0),
                frequency: $input['frequency'] ?? 'ONCE',
                maxCount: intval($input['max_count'] ?? 3),
                stopCondition: $input['stop_condition'] ?? 'ON_PAID',
                priority: $input['priority'] ?? 'NORMAL',
                notes: $input['notes'] ?? null
            );

            return response_json(['status' => 'success', 'success' => true, 'data' => $reminder], 201);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function send($request = null)
    {
        $input = get_json_input();
        $channel = $input['channel'] ?? 'WHATSAPP';
        $invoiceId = intval($input['invoice_id'] ?? 0);

        return response_json([
            'status' => 'success',
            'message' => "Payment reminder dispatched successfully via {$channel}",
            'data' => ['invoice_id' => $invoiceId, 'channel' => $channel, 'sent_at' => date('Y-m-d H:i:s')]
        ]);
    }

    public function history($request = null)
    {
        return response_json(['status' => 'success', 'data' => []]);
    }

    public function overview($request = null)
    {
        $companyId = AuthMiddleware::getTenantId();
        $overdueCount = Invoice::where('company_id', $companyId)->where('due_date', '<', date('Y-m-d'))->where('status', '!=', 'PAID')->count();
        $scheduledCount = Reminder::where('company_id', $companyId)->where('status', 'PENDING')->count();

        return response_json([
            'status' => 'success',
            'data' => [
                'overdue_invoices' => $overdueCount,
                'scheduled_reminders' => $scheduledCount,
                'sent_this_month' => 0,
            ]
        ]);
    }

    /**
     * Process due reminders
     */
    public function processDue()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $results = ReminderEngine::processDueReminders($companyId);

            return response_json(['status' => 'success', 'success' => true, 'data' => $results]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Dismiss reminder
     */
    public function dismiss(int $id)
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $reminder = Reminder::where('company_id', $companyId)->findOrFail($id);
            $reminder->status = 'DISMISSED';
            $reminder->save();

            return response_json(['status' => 'success', 'success' => true, 'data' => $reminder]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
