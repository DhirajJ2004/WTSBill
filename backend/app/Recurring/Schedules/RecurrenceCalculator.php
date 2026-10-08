<?php

namespace App\Recurring\Schedules;

use Carbon\Carbon;
use InvalidArgumentException;

class RecurrenceCalculator
{
    /**
     * Compute next run date after a given date based on frequency and month-end policy.
     */
    public static function computeNextRunDate(
        Carbon $currentDate,
        string $frequency,
        int $intervalCount = 1,
        string $monthEndPolicy = 'LAST_VALID_DAY',
        ?Carbon $endDate = null
    ): ?Carbon {
        $frequency = strtoupper($frequency);
        $next = $currentDate->copy();
        $isMonthEnd = $currentDate->isLastOfMonth();

        switch ($frequency) {
            case 'DAILY':
                $next->addDays($intervalCount);
                break;

            case 'WEEKLY':
                $next->addWeeks($intervalCount);
                break;

            case 'MONTHLY':
                $next->addMonthsNoOverflow($intervalCount);
                if ($monthEndPolicy === 'LAST_DAY_OF_MONTH' || ($isMonthEnd && $monthEndPolicy === 'LAST_VALID_DAY')) {
                    $next->endOfMonth();
                }
                break;

            case 'QUARTERLY':
                $next->addMonthsNoOverflow(3 * $intervalCount);
                if ($monthEndPolicy === 'LAST_DAY_OF_MONTH' || ($isMonthEnd && $monthEndPolicy === 'LAST_VALID_DAY')) {
                    $next->endOfMonth();
                }
                break;

            case 'HALF_YEARLY':
                $next->addMonthsNoOverflow(6 * $intervalCount);
                if ($monthEndPolicy === 'LAST_DAY_OF_MONTH' || ($isMonthEnd && $monthEndPolicy === 'LAST_VALID_DAY')) {
                    $next->endOfMonth();
                }
                break;

            case 'YEARLY':
                $next->addYearsNoOverflow($intervalCount);
                if ($monthEndPolicy === 'LAST_DAY_OF_MONTH' || ($isMonthEnd && $monthEndPolicy === 'LAST_VALID_DAY')) {
                    $next->endOfMonth();
                }
                break;

            case 'CUSTOM':
                $next->addDays($intervalCount);
                break;

            default:
                throw new InvalidArgumentException("Unknown recurrence frequency: {$frequency}");
        }

        // If next date exceeds end date, return null (schedule completed)
        if ($endDate && $next->gt($endDate)) {
            return null;
        }

        return $next;
    }

    /**
     * Preview next N occurrences.
     */
    public static function previewOccurrences(
        string $startDate,
        string $frequency,
        int $intervalCount = 1,
        string $monthEndPolicy = 'LAST_VALID_DAY',
        ?string $endDate = null,
        int $count = 3
    ): array {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = $endDate ? Carbon::parse($endDate) : null;

        $dates[] = $current->toDateString();

        for ($i = 1; $i < $count; $i++) {
            $next = self::computeNextRunDate($current, $frequency, $intervalCount, $monthEndPolicy, $end);
            if (!$next) break;
            $dates[] = $next->toDateString();
            $current = $next;
        }

        return $dates;
    }
}
