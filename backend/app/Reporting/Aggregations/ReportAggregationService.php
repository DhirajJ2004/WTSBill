<?php

namespace App\Reporting\Aggregations;

class ReportAggregationService
{
    /**
     * Compute variance and percentage change safely.
     */
    public static function calculateGrowth(float $current, float $previous): array
    {
        $diff = $current - $previous;
        $pct = 0.0;

        if ($previous != 0.0) {
            $pct = round(($diff / abs($previous)) * 100, 2);
        } elseif ($current > 0) {
            $pct = 100.0;
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'difference' => $diff,
            'percentage' => $pct,
            'trend' => $diff >= 0 ? 'UP' : 'DOWN',
        ];
    }

    /**
     * Group items by key and sum a specific numeric field.
     */
    public static function sumByKey(array $items, string $groupKey, string $valueKey): array
    {
        $result = [];
        foreach ($items as $item) {
            $key = is_array($item) ? ($item[$groupKey] ?? 'Other') : ($item->{$groupKey} ?? 'Other');
            $val = is_array($item) ? floatval($item[$valueKey] ?? 0) : floatval($item->{$valueKey} ?? 0);

            if (!isset($result[$key])) {
                $result[$key] = 0.0;
            }
            $result[$key] += $val;
        }
        return $result;
    }

    /**
     * Group array items by a key.
     */
    public static function groupBy(array $items, string $groupKey): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $key = is_array($item) ? ($item[$groupKey] ?? 'Other') : ($item->{$groupKey} ?? 'Other');
            $grouped[$key][] = $item;
        }
        return $grouped;
    }
}
