<?php

namespace App\Scheduler;

use App\Models\SchedulerJob;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;

class SchedulerLockManager
{
    /**
     * Acquire distributed lock for a scheduler job.
     */
    public static function acquireLock(int $companyId, string $jobKey, int $lockDurationSeconds = 300, ?string $workerId = 'worker-1'): bool
    {
        $now = Carbon::now();
        $lockUntil = $now->copy()->addSeconds($lockDurationSeconds);

        return DB::transaction(function () use ($companyId, $jobKey, $now, $lockUntil, $workerId) {
            $job = SchedulerJob::where('company_id', $companyId)->where('job_key', $jobKey)->first();

            if (!$job) {
                SchedulerJob::create([
                    'company_id' => $companyId,
                    'job_key' => $jobKey,
                    'job_type' => $jobKey,
                    'status' => 'PROCESSING',
                    'started_at' => $now,
                    'locked_until' => $lockUntil,
                    'locked_by' => $workerId,
                    'attempt_count' => 1,
                ]);
                return true;
            }

            // If currently locked and not expired, cannot acquire
            if ($job->status === 'PROCESSING' && $job->locked_until && Carbon::parse($job->locked_until)->gt($now)) {
                return false;
            }

            // Lock acquired
            $job->update([
                'status' => 'PROCESSING',
                'started_at' => $now,
                'locked_until' => $lockUntil,
                'locked_by' => $workerId,
                'attempt_count' => $job->attempt_count + 1,
            ]);

            return true;
        });
    }

    /**
     * Release distributed lock.
     */
    public static function releaseLock(int $companyId, string $jobKey, bool $success = true, ?string $errorMessage = null): void
    {
        $job = SchedulerJob::where('company_id', $companyId)->where('job_key', $jobKey)->first();
        if ($job) {
            $job->update([
                'status' => $success ? 'COMPLETED' : 'FAILED',
                'completed_at' => Carbon::now(),
                'locked_until' => null,
                'locked_by' => null,
                'last_error' => $errorMessage,
            ]);
        }
    }
}
