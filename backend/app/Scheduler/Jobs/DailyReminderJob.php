<?php

namespace App\Scheduler\Jobs;

use App\Notifications\Services\ReminderService;

class DailyReminderJob
{
    public static function run(int $companyId, ?string $date = null): array
    {
        return ReminderService::processDailyReminders($companyId, $date);
    }
}
