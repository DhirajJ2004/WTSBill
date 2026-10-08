<?php

namespace App\Services;

use App\Models\AccountingPeriod;

class PeriodService
{
    /**
     * Check if a given date falls inside a locked accounting period.
     */
    public static function isDateLocked(int $companyId, string $date): bool
    {
        return AccountingPeriod::where('company_id', $companyId)
            ->where('is_locked', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Validate that posting date is not locked.
     */
    public static function assertNotLocked(int $companyId, string $date): void
    {
        if (static::isDateLocked($companyId, $date)) {
            throw new \InvalidArgumentException("Cannot post transactions on {$date}: The accounting period is locked.");
        }
    }

    /**
     * Lock an accounting period.
     */
    public static function lockPeriod(int $companyId, string $financialYear, string $periodName, string $startDate, string $endDate, string $reason, ?string $userName = 'Admin'): AccountingPeriod
    {
        $period = AccountingPeriod::updateOrCreate(
            ['company_id' => $companyId, 'financial_year' => $financialYear, 'period_name' => $periodName],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_locked' => true,
                'locked_by' => $userName,
                'locked_at' => date('Y-m-d H:i:s'),
                'lock_reason' => $reason,
            ]
        );

        AuditLogService::log(
            $companyId,
            $userName,
            'PERIOD_LOCK',
            'AccountingPeriod',
            $period->id,
            "Locked accounting period '{$periodName}' ({$startDate} to {$endDate}) for FY {$financialYear}: {$reason}"
        );

        return $period;
    }

    /**
     * Unlock an accounting period.
     */
    public static function unlockPeriod(int $companyId, int $periodId, ?string $userName = 'Admin'): AccountingPeriod
    {
        $period = AccountingPeriod::where('company_id', $companyId)->findOrFail($periodId);

        $period->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
            'lock_reason' => 'Unlocked by authorized user',
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'PERIOD_UNLOCK',
            'AccountingPeriod',
            $period->id,
            "Unlocked accounting period '{$period->period_name}' for FY {$period->financial_year}"
        );

        return $period;
    }

    /**
     * List all accounting periods.
     */
    public static function getPeriods(int $companyId, ?string $financialYear = null): array
    {
        $query = AccountingPeriod::where('company_id', $companyId);
        if ($financialYear) {
            $query->where('financial_year', $financialYear);
        }
        return $query->orderBy('start_date', 'asc')->get()->toArray();
    }
}
