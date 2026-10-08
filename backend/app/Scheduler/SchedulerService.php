<?php

namespace App\Scheduler;

use App\Scheduler\Jobs\DailyReminderJob;
use App\Scheduler\Jobs\RecurringTransactionJob;
use App\Scheduler\Jobs\StockAlertJob;
use App\Scheduler\Jobs\BatchExpiryJob;
use App\Models\SchedulerJob;
use Carbon\Carbon;
use Exception;

class SchedulerService
{
    /**
     * Run all active scheduler jobs for a business with distributed locking.
     */
    public static function runAll(int $companyId, ?string $asOfDate = null): array
    {
        $results = [];

        // 1. Daily Reminders Job
        if (SchedulerLockManager::acquireLock($companyId, 'DAILY_REMINDERS', 300)) {
            try {
                $results['daily_reminders'] = DailyReminderJob::run($companyId, $asOfDate);
                SchedulerLockManager::releaseLock($companyId, 'DAILY_REMINDERS', true);
            } catch (Exception $e) {
                SchedulerLockManager::releaseLock($companyId, 'DAILY_REMINDERS', false, $e->getMessage());
                $results['daily_reminders'] = ['error' => $e->getMessage()];
            }
        } else {
            $results['daily_reminders'] = ['status' => 'SKIPPED_LOCKED'];
        }

        // 2. Recurring Transactions Job
        if (SchedulerLockManager::acquireLock($companyId, 'RECURRING_TRANSACTIONS', 300)) {
            try {
                $results['recurring_transactions'] = RecurringTransactionJob::run($companyId, $asOfDate);
                SchedulerLockManager::releaseLock($companyId, 'RECURRING_TRANSACTIONS', true);
            } catch (Exception $e) {
                SchedulerLockManager::releaseLock($companyId, 'RECURRING_TRANSACTIONS', false, $e->getMessage());
                $results['recurring_transactions'] = ['error' => $e->getMessage()];
            }
        } else {
            $results['recurring_transactions'] = ['status' => 'SKIPPED_LOCKED'];
        }

        // 3. Stock Alerts Job
        if (SchedulerLockManager::acquireLock($companyId, 'STOCK_ALERTS', 300)) {
            try {
                $results['stock_alerts'] = StockAlertJob::run($companyId);
                SchedulerLockManager::releaseLock($companyId, 'STOCK_ALERTS', true);
            } catch (Exception $e) {
                SchedulerLockManager::releaseLock($companyId, 'STOCK_ALERTS', false, $e->getMessage());
                $results['stock_alerts'] = ['error' => $e->getMessage()];
            }
        } else {
            $results['stock_alerts'] = ['status' => 'SKIPPED_LOCKED'];
        }

        // 4. Batch Expiry Job
        if (SchedulerLockManager::acquireLock($companyId, 'BATCH_EXPIRY', 300)) {
            try {
                $results['batch_expiry'] = BatchExpiryJob::run($companyId);
                SchedulerLockManager::releaseLock($companyId, 'BATCH_EXPIRY', true);
            } catch (Exception $e) {
                SchedulerLockManager::releaseLock($companyId, 'BATCH_EXPIRY', false, $e->getMessage());
                $results['batch_expiry'] = ['error' => $e->getMessage()];
            }
        } else {
            $results['batch_expiry'] = ['status' => 'SKIPPED_LOCKED'];
        }

        return $results;
    }

    /**
     * Get scheduler health status.
     */
    public static function getHealthStatus(int $companyId): array
    {
        $jobs = SchedulerJob::where('company_id', $companyId)->get();

        $completedCount = $jobs->where('status', 'COMPLETED')->count();
        $failedCount = $jobs->where('status', 'FAILED')->count();
        $processingCount = $jobs->where('status', 'PROCESSING')->count();

        $lastSuccessful = $jobs->where('status', 'COMPLETED')->sortByDesc('completed_at')->first();

        return [
            'is_running' => true,
            'total_registered_jobs' => $jobs->count(),
            'completed_jobs' => $completedCount,
            'failed_jobs' => $failedCount,
            'processing_jobs' => $processingCount,
            'last_successful_run' => $lastSuccessful?->completed_at,
            'jobs' => $jobs,
        ];
    }
}
