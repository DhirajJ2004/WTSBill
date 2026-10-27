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

if (!function_exists('amount_to_words_inr')) {
    /**
     * Convert currency amounts to Indian numbering system words matching GST invoice format:
     * e.g. "Two Thousand Two Hundred And Forty Two Rupees Only"
     */
    function amount_to_words_inr(float|int|string|null $amount): string
    {
        $amount = round((float) ($amount ?? 0), 2);
        if ($amount == 0.0) {
            return 'Rupees Zero Only';
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $convertBelowThousand = function (int $n) use ($units, $tens): string {
            $words = '';
            if ($n >= 100) {
                $words .= $units[(int) ($n / 100)] . ' Hundred';
                $n %= 100;
                if ($n > 0) {
                    $words .= ' And ';
                }
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

        $result = implode(' ', array_filter($parts)) . ' Rupees';
        if ($paise > 0) {
            $result .= ' and ' . $convertBelowThousand($paise) . ' Paise';
        }
        return trim($result) . ' Only';
    }
}

if (!function_exists('format_inr_amount')) {
    /**
     * Format monetary values with Indian numbering grouping (e.g. 1,77,885.00 or 2,242).
     */
    function format_inr_amount(float|int|string|null $amount, bool $withSymbol = false, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }
        $num = (float) $amount;
        $isNegative = $num < -0.0001;
        $abs = abs($num);

        $parts = explode('.', sprintf('%.' . $decimals . 'f', $abs));
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? '';

        if (strlen($integerPart) > 3) {
            $lastThree = substr($integerPart, -3);
            $remaining = substr($integerPart, 0, -3);
            $groups = [];
            while (strlen($remaining) > 2) {
                $groups[] = substr($remaining, -2);
                $remaining = substr($remaining, 0, -2);
            }
            if ($remaining !== '') {
                $groups[] = $remaining;
            }
            $formattedInteger = implode(',', array_reverse($groups)) . ',' . $lastThree;
        } else {
            $formattedInteger = $integerPart;
        }

        $res = $formattedInteger;
        if ($decimals > 0 && $decimalPart !== '') {
            $res .= '.' . $decimalPart;
        }

        $sign = $isNegative ? '-' : '';
        $prefix = $withSymbol ? '₹ ' : '';
        return $sign . $prefix . $res;
    }
}

if (!function_exists('gst_state_info')) {
    /**
     * Resolve Indian GST 2-digit state code and state name.
     */
    function gst_state_info(?string $input): array
    {
        $map = [
            '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
            '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
            '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
            '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
            '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam',
            '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
            '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
            '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra',
            '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep',
            '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
            '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        ];

        $trimmed = trim((string) ($input ?? ''));
        if (isset($map[$trimmed])) {
            return ['code' => $trimmed, 'name' => $map[$trimmed]];
        }

        // Try lookup by state name
        foreach ($map as $c => $n) {
            if (strcasecmp($trimmed, $n) === 0) {
                return ['code' => $c, 'name' => $n];
            }
        }

        // Default Maharashtra 27
        return ['code' => '27', 'name' => 'Maharashtra'];
    }
}

