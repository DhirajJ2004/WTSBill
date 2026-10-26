<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape HTML output safely.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return \App\Core\Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get application configuration value using dot notation.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Core\Config::get($key, $default);
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format monetary values with 2 decimal precision (Indian Rupee default).
     */
    function format_currency(float|int|string|null $amount): string
    {
        $num = (float) ($amount ?? 0);
        return '₹ ' . number_format($num, 2, '.', ',');
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date to standard Indian ERP format (DD-MM-YYYY).
     */
    function format_date(?string $date, string $format = 'd-m-Y'): string
    {
        if (empty($date)) {
            return '-';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '-';
    }
}

if (!function_exists('number_to_words_inr')) {
    /**
     * Convert currency amounts to Indian numbering system words (Crores, Lakhs, Thousands).
     */
    function number_to_words_inr(float|int $amount): string
    {
        $amount = round((float) $amount, 2);
        if ($amount == 0) {
            return 'Rupees Zero Only';
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $convertBelowThousand = function (int $n) use ($units, $tens): string {
            $words = '';
            if ($n >= 100) {
                $words .= $units[(int) ($n / 100)] . ' Hundred ';
                $n %= 100;
            }
            if ($n >= 20) {
                $words .= $tens[(int) ($n / 10)] . ' ';
                $n %= 10;
            }
            if ($n > 0) {
                $words .= $units[$n] . ' ';
            }
            return trim($words);
        };

        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $parts = [];
        if ($rupees >= 10000000) {
            $crores = (int) floor($rupees / 10000000);
            $parts[] = $convertBelowThousand($crores) . ' Crore';
            $rupees %= 10000000;
        }
        if ($rupees >= 100000) {
            $lakhs = (int) floor($rupees / 100000);
            $parts[] = $convertBelowThousand($lakhs) . ' Lakh';
            $rupees %= 100000;
        }
        if ($rupees >= 1000) {
            $thousands = (int) floor($rupees / 1000);
            $parts[] = $convertBelowThousand($thousands) . ' Thousand';
            $rupees %= 1000;
        }
        if ($rupees > 0) {
            $parts[] = $convertBelowThousand($rupees);
        }

        $result = 'Rupees ' . implode(' ', array_filter($parts));
        if ($paise > 0) {
            $result .= ' and ' . $convertBelowThousand($paise) . ' Paise';
        }
        return trim($result) . ' Only';
    }
}
