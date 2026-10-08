<?php

namespace App\Services;

class TaxRoundingService
{
    /**
     * Standard 2-decimal half-up rounding
     */
    public static function round(float $amount, int $precision = 2): float
    {
        return round($amount, $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * Round line tax and document tax consistently
     */
    public static function roundTax(float $tax): float
    {
        return static::round($tax, 2);
    }
}
