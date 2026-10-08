<?php

namespace App\Scheduler\Jobs;

use App\Recurring\Services\RecurringTransactionService;

class RecurringTransactionJob
{
    public static function run(int $companyId, ?string $date = null): array
    {
        return RecurringTransactionService::processDueRecurringTransactions($companyId, $date);
    }
}
