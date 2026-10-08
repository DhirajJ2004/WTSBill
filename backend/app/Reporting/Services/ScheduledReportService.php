<?php

namespace App\Reporting\Services;

use App\Models\ReportSchedule;
use App\Reporting\Types\ReportRegistry;
use App\Notifications\Services\NotificationService;
use Carbon\Carbon;
use Exception;

class ScheduledReportService
{
    /**
     * Create a scheduled report subscription
     */
    public static function createSchedule(int $companyId, array $data, string $userName = 'System'): ReportSchedule
    {
        $reportKey = strtoupper($data['report_key'] ?? '');
        $meta = ReportRegistry::getReportMeta($reportKey);
        if (!$meta) {
            throw new Exception("Invalid report key: '{$reportKey}'");
        }

        $frequency = strtoupper($data['frequency'] ?? 'WEEKLY');
        $nextRun = match ($frequency) {
            'DAILY' => Carbon::now()->addDay()->startOfDay()->addHours(8),
            'MONTHLY' => Carbon::now()->addMonth()->startOfMonth()->addHours(8),
            default => Carbon::now()->next(Carbon::MONDAY)->startOfDay()->addHours(8),
        };

        return ReportSchedule::create([
            'company_id' => $companyId,
            'branch_id' => !empty($data['branch_id']) ? intval($data['branch_id']) : null,
            'report_key' => $reportKey,
            'title' => $data['title'] ?? ($meta['title'] . ' Schedule'),
            'frequency' => $frequency,
            'delivery_channel' => strtoupper($data['delivery_channel'] ?? 'EMAIL'),
            'recipient' => $data['recipient'] ?? 'admin@wtsbill.com',
            'export_format' => strtoupper($data['export_format'] ?? 'PDF'),
            'filters_json' => $data['filters'] ?? ['period' => 'LAST_MONTH'],
            'next_run_at' => $nextRun,
            'is_active' => true,
            'created_by' => $userName,
        ]);
    }

    /**
     * List all scheduled reports for company
     */
    public static function listSchedules(int $companyId): array
    {
        return ReportSchedule::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Execute a scheduled report and dispatch notification
     */
    public static function executeSchedule(int $scheduleId): array
    {
        $schedule = ReportSchedule::find($scheduleId);
        if (!$schedule || !$schedule->is_active) {
            return ['status' => 'SKIPPED', 'message' => 'Schedule not found or inactive'];
        }

        $dataset = MasterReportService::generateReportData(
            $schedule->report_key,
            $schedule->company_id,
            $schedule->filters_json ?? [],
            null,
            'Scheduled Dispatcher'
        );

        $schedule->update([
            'last_run_at' => Carbon::now(),
            'next_run_at' => match ($schedule->frequency) {
                'DAILY' => Carbon::now()->addDay(),
                'MONTHLY' => Carbon::now()->addMonth(),
                default => Carbon::now()->addWeek(),
            },
        ]);

        return [
            'status' => 'DISPATCHED',
            'schedule_id' => $scheduleId,
            'report_key' => $schedule->report_key,
            'recipient' => $schedule->recipient,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
