<?php

namespace App\Exports\Security;

class FormulaInjectionSanitizer
{
    private static array $dangerousPrefixes = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Sanitize cell value to prevent CSV / Spreadsheet Formula Injection (CSV Injection).
     */
    public static function sanitize($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed === '') {
            return $value;
        }

        $firstChar = $trimmed[0];
        if (in_array($firstChar, self::$dangerousPrefixes, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitize an entire associative array / row.
     */
    public static function sanitizeRow(array $row): array
    {
        $sanitized = [];
        foreach ($row as $k => $v) {
            $sanitized[$k] = self::sanitize($v);
        }
        return $sanitized;
    }
}
