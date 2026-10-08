<?php

namespace App\DocumentEngine\Formatters;

class IndianCurrencyFormatter
{
    /**
     * Format number in Indian Lakhs/Crores grouping format: ₹ 1,50,000.00
     */
    public static function format(float $amount, bool $includeSymbol = true, string $symbol = '₹'): string
    {
        $isNegative = ($amount < 0);
        $amount = abs(round($amount, 2));

        $parts = explode('.', sprintf('%.2f', $amount));
        $whole = $parts[0];
        $fraction = $parts[1] ?? '00';

        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $restFormatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $formattedWhole = $restFormatted . ',' . $last3;
        } else {
            $formattedWhole = $whole;
        }

        $result = $formattedWhole . '.' . $fraction;

        if ($includeSymbol) {
            $result = $symbol . ' ' . $result;
        }

        return ($isNegative ? '-' : '') . $result;
    }
}
