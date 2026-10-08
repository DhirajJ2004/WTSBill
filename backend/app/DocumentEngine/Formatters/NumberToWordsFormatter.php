<?php

namespace App\DocumentEngine\Formatters;

class NumberToWordsFormatter
{
    private static array $units = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen'
    ];

    private static array $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    ];

    /**
     * Convert numeric amount to words in Indian Numbering Format (Rupees ... Only)
     */
    public static function convert(float $amount, string $currency = 'Rupees'): string
    {
        $amount = round($amount, 2);
        if ($amount == 0) {
            return "{$currency} Zero Only";
        }

        $isNegative = ($amount < 0);
        $amount = abs($amount);

        $rupees = (int)floor($amount);
        $paise = (int)round(($amount - $rupees) * 100);

        $words = [];
        if ($rupees > 0) {
            $words[] = static::convertWholeNumber($rupees);
        }

        $result = '';
        if (!empty($words)) {
            $result = $currency . ' ' . implode(' ', $words);
        }

        if ($paise > 0) {
            $paiseWords = static::convertGroup($paise);
            if (!empty($result)) {
                $result .= ' and ' . $paiseWords . ' Paise';
            } else {
                $result = $paiseWords . ' Paise';
            }
        }

        $result = trim($result) . ' Only';

        return ($isNegative ? 'Minus ' : '') . $result;
    }

    private static function convertWholeNumber(int $num): string
    {
        if ($num === 0) {
            return '';
        }

        $parts = [];

        // Crores (10^7)
        if ($num >= 10000000) {
            $crore = (int)floor($num / 10000000);
            $parts[] = static::convertWholeNumber($crore) . ' Crore';
            $num %= 10000000;
        }

        // Lakhs (10^5)
        if ($num >= 100000) {
            $lakh = (int)floor($num / 100000);
            $parts[] = static::convertGroup($lakh) . ' Lakh';
            $num %= 100000;
        }

        // Thousands (10^3)
        if ($num >= 1000) {
            $thousand = (int)floor($num / 1000);
            $parts[] = static::convertGroup($thousand) . ' Thousand';
            $num %= 1000;
        }

        // Hundreds (10^2)
        if ($num >= 100) {
            $hundred = (int)floor($num / 100);
            $parts[] = static::$units[$hundred] . ' Hundred';
            $num %= 100;
        }

        // Remainder tens/units
        if ($num > 0) {
            $parts[] = static::convertGroup($num);
        }

        return implode(' ', array_filter($parts));
    }

    private static function convertGroup(int $num): string
    {
        if ($num < 20) {
            return static::$units[$num];
        }

        $ten = (int)floor($num / 10);
        $unit = $num % 10;

        $word = static::$tens[$ten] ?? '';
        if ($unit > 0) {
            $word .= ' ' . static::$units[$unit];
        }

        return trim($word);
    }
}
