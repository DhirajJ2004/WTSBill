<?php

namespace App\Reporting\Filters;

use Carbon\Carbon;
use InvalidArgumentException;

class ReportFilterService
{
    /**
     * Resolve date range based on shortcut or custom dates.
     * Respects Indian Financial Year (1 April - 31 March).
     */
    public static function resolveDateRange(array $filters): array
    {
        $shortcut = strtoupper($filters['date_shortcut'] ?? ($filters['period'] ?? 'THIS_MONTH'));
        $now = Carbon::now();

        $startDate = null;
        $endDate = null;

        switch ($shortcut) {
            case 'TODAY':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;

            case 'YESTERDAY':
                $startDate = $now->copy()->subDay()->startOfDay();
                $endDate = $now->copy()->subDay()->endOfDay();
                break;

            case 'THIS_WEEK':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;

            case 'LAST_WEEK':
                $startDate = $now->copy()->subWeek()->startOfWeek();
                $endDate = $now->copy()->subWeek()->endOfWeek();
                break;

            case 'THIS_MONTH':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;

            case 'LAST_MONTH':
                $startDate = $now->copy()->subMonth()->startOfMonth();
                $endDate = $now->copy()->subMonth()->endOfMonth();
                break;

            case 'THIS_QUARTER':
                $startDate = $now->copy()->startOfQuarter();
                $endDate = $now->copy()->endOfQuarter();
                break;

            case 'THIS_FY':
            case 'THIS_FINANCIAL_YEAR':
            case 'THIS_YEAR':
                // Indian FY starts April 1
                $currentYear = $now->year;
                if ($now->month < 4) {
                    $startDate = Carbon::create($currentYear - 1, 4, 1)->startOfDay();
                    $endDate = Carbon::create($currentYear, 3, 31)->endOfDay();
                } else {
                    $startDate = Carbon::create($currentYear, 4, 1)->startOfDay();
                    $endDate = Carbon::create($currentYear + 1, 3, 31)->endOfDay();
                }
                break;

            case 'LAST_FY':
            case 'LAST_FINANCIAL_YEAR':
            case 'LAST_YEAR':
                $currentYear = $now->year;
                if ($now->month < 4) {
                    $startDate = Carbon::create($currentYear - 2, 4, 1)->startOfDay();
                    $endDate = Carbon::create($currentYear - 1, 3, 31)->endOfDay();
                } else {
                    $startDate = Carbon::create($currentYear - 1, 4, 1)->startOfDay();
                    $endDate = Carbon::create($currentYear, 3, 31)->endOfDay();
                }
                break;

            case 'ALL':
            case 'ALL_TIME':
                $startDate = Carbon::create(2000, 1, 1)->startOfDay();
                $endDate = Carbon::create(2099, 12, 31)->endOfDay();
                break;

            case 'CUSTOM':
            default:
                if (!empty($filters['date_from']) || !empty($filters['start_date'])) {
                    $fromStr = $filters['date_from'] ?? $filters['start_date'];
                    $startDate = Carbon::parse($fromStr)->startOfDay();
                } else {
                    $startDate = $now->copy()->startOfMonth();
                }

                if (!empty($filters['date_to']) || !empty($filters['end_date'])) {
                    $toStr = $filters['date_to'] ?? $filters['end_date'];
                    $endDate = Carbon::parse($toStr)->endOfDay();
                } else {
                    $endDate = $now->copy()->endOfMonth();
                }
                break;
        }

        // Validation: start date cannot be after end date
        if ($startDate->gt($endDate)) {
            throw new InvalidArgumentException("Start date ({$startDate->toDateString()}) cannot be after end date ({$endDate->toDateString()}).");
        }

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'start_datetime' => $startDate->toDateTimeString(),
            'end_datetime' => $endDate->toDateTimeString(),
            'shortcut' => $shortcut,
            'label' => self::getPeriodLabel($shortcut, $startDate, $endDate),
        ];
    }

    /**
     * Compute comparative previous period range (e.g. previous month or previous FY).
     */
    public static function resolveComparisonPeriod(string $startDateStr, string $endDateStr, string $mode = 'PREVIOUS_PERIOD'): array
    {
        $start = Carbon::parse($startDateStr);
        $end = Carbon::parse($endDateStr);
        $diffInDays = $start->diffInDays($end) + 1;

        if ($mode === 'PREVIOUS_YEAR') {
            $prevStart = $start->copy()->subYear();
            $prevEnd = $end->copy()->subYear();
        } else {
            // Default: exact previous duration
            $prevEnd = $start->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($diffInDays - 1)->startOfDay();
        }

        return [
            'start_date' => $prevStart->toDateString(),
            'end_date' => $prevEnd->toDateString(),
            'label' => "Previous ({$prevStart->format('d M Y')} - {$prevEnd->format('d M Y')})",
        ];
    }

    public static function getPeriodLabel(string $shortcut, Carbon $start, Carbon $end): string
    {
        switch ($shortcut) {
            case 'TODAY':
                return 'Today (' . $start->format('d M Y') . ')';
            case 'THIS_MONTH':
                return $start->format('F Y');
            case 'LAST_MONTH':
                return $start->format('F Y');
            case 'THIS_FY':
                $startYr = $start->format('y');
                $endYr = $end->format('y');
                return "FY 20{$startYr}-{$endYr}";
            default:
                return $start->format('d M Y') . ' — ' . $end->format('d M Y');
        }
    }
}
