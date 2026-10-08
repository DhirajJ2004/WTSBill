<?php

namespace App\Exports\Generators;

use App\Exports\Security\FormulaInjectionSanitizer;

class CsvExportGenerator
{
    /**
     * Generate UTF-8 encoded CSV string with formula sanitization.
     */
    public static function generate(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        // Add UTF-8 Byte Order Mark for Excel compatibility
        fputs($fp, "\xEF\xBB\xBF");

        fputcsv($fp, $headers);

        foreach ($rows as $row) {
            $sanitized = FormulaInjectionSanitizer::sanitizeRow(array_values($row));
            fputcsv($fp, $sanitized);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
