<?php

namespace App\Exports\Generators;

use App\Exports\Security\FormulaInjectionSanitizer;

class ExcelExportGenerator
{
    /**
     * Generate HTML-based spreadsheet format readable by Microsoft Excel, LibreOffice and Google Sheets.
     */
    public static function generate(string $title, array $headers, array $rows): string
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html .= '<style>
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
            th { background-color: #1e293b; color: #ffffff; font-weight: bold; padding: 10px; border: 1px solid #cbd5e1; text-align: left; }
            td { padding: 8px; border: 1px solid #cbd5e1; font-size: 13px; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .title-row { font-size: 16px; font-weight: bold; color: #0f172a; padding: 12px 0; }
        </style></head><body>';

        $html .= '<table>';
        $html .= '<thead>';
        $html .= '<tr><td colspan="' . count($headers) . '" class="title-row">' . htmlspecialchars($title) . '</td></tr>';
        $html .= '<tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $val) {
                $sanitized = FormulaInjectionSanitizer::sanitize($val);
                $html .= '<td>' . htmlspecialchars((string)$sanitized) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';
        return $html;
    }
}
