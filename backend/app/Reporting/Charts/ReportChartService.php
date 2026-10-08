<?php

namespace App\Reporting\Charts;

class ReportChartService
{
    /**
     * Build Line Chart dataset for time-series.
     */
    public static function buildLineChart(string $title, array $labels, array $series, array $options = []): array
    {
        $tableRows = [];
        foreach ($labels as $idx => $label) {
            $row = ['label' => $label];
            foreach ($series as $s) {
                $row[$s['name']] = $s['data'][$idx] ?? 0;
            }
            $tableRows[] = $row;
        }

        return [
            'type' => 'line',
            'title' => $title,
            'labels' => $labels,
            'series' => $series, // array of ['name' => 'Sales', 'data' => [100, 200...], 'color' => '#2563eb']
            'options' => $options,
            'data_table' => $tableRows,
        ];
    }

    /**
     * Build Bar Chart dataset.
     */
    public static function buildBarChart(string $title, array $labels, array $series, array $options = []): array
    {
        $tableRows = [];
        foreach ($labels as $idx => $label) {
            $row = ['category' => $label];
            foreach ($series as $s) {
                $row[$s['name']] = $s['data'][$idx] ?? 0;
            }
            $tableRows[] = $row;
        }

        return [
            'type' => 'bar',
            'title' => $title,
            'labels' => $labels,
            'series' => $series,
            'options' => $options,
            'data_table' => $tableRows,
        ];
    }

    /**
     * Build Donut / Pie Chart dataset.
     */
    public static function buildDonutChart(string $title, array $items, string $labelKey = 'name', string $valueKey = 'value'): array
    {
        $labels = [];
        $values = [];
        $total = 0.0;

        foreach ($items as $it) {
            $lbl = is_array($it) ? $it[$labelKey] : $it->{$labelKey};
            $val = is_array($it) ? floatval($it[$valueKey]) : floatval($it->{$valueKey});
            $labels[] = $lbl;
            $values[] = $val;
            $total += $val;
        }

        $tableRows = [];
        foreach ($labels as $idx => $lbl) {
            $val = $values[$idx];
            $pct = $total > 0 ? round(($val / $total) * 100, 1) : 0;
            $tableRows[] = [
                'name' => $lbl,
                'value' => $val,
                'percentage' => $pct . '%',
            ];
        }

        return [
            'type' => 'donut',
            'title' => $title,
            'labels' => $labels,
            'values' => $values,
            'total' => $total,
            'data_table' => $tableRows,
        ];
    }
}
