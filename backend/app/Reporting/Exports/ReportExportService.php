<?php

namespace App\Reporting\Exports;

use App\DocumentEngine\Services\DocumentService;

class ReportExportService
{
    /**
     * Generate CSV string with UTF-8 BOM.
     */
    public static function exportToCsv(array $columns, array $rows, string $title = 'Report'): string
    {
        $fp = fopen('php://temp', 'r+');

        // Add UTF-8 BOM for Excel compatibility with special characters
        fputs($fp, "\xEF\xBB\xBF");

        // Header titles
        $headers = [];
        foreach ($columns as $col) {
            $headers[] = $col['label'] ?? $col['key'];
        }
        fputcsv($fp, $headers);

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $key = $col['key'];
                $val = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                $line[] = (string)$val;
            }
            fputcsv($fp, $line);
        }

        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        return $csvContent;
    }

    /**
     * Generate Excel XML / HTML table stream.
     */
    public static function exportToExcel(array $columns, array $rows, string $title = 'Report'): string
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
        $html .= '<x:Name>' . htmlspecialchars($title) . '</x:Name>';
        $html .= '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
        $html .= '<body>';
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        $html .= '<p>Generated on: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<table border="1" style="border-collapse: collapse;">';

        // Headers
        $html .= '<tr style="background-color: #1e3a8a; color: #ffffff; font-weight: bold;">';
        foreach ($columns as $col) {
            $html .= '<th style="padding: 8px 12px;">' . htmlspecialchars($col['label'] ?? $col['key']) . '</th>';
        }
        $html .= '</tr>';

        // Rows
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $key = $col['key'];
                $val = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                $type = $col['type'] ?? 'text';

                if ($type === 'currency' || $type === 'number') {
                    $html .= '<td style="padding: 6px 10px; text-align: right;" mso-number-format="0.00">' . htmlspecialchars((string)$val) . '</td>';
                } else {
                    $html .= '<td style="padding: 6px 10px;">' . htmlspecialchars((string)$val) . '</td>';
                }
            }
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';
        return $html;
    }
}
