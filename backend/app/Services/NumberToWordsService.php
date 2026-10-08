<?php

namespace App\Services;

class NumberToWordsService
{
    /**
     * Convert float/numeric value to Indian currency words format.
     * Example: 15420.50 -> "Fifteen Thousand Four Hundred Twenty Rupees and Fifty Paise Only"
     * Example: 10000000 -> "One Crore Rupees Only"
     */
    public static function toIndianWords(float $number): string
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $decimal = (int) $decimal;
        $words = [
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
            13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
            70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        ];

        if ($no == 0) {
            $rupees = 'Zero';
        } else {
            $digits_length = strlen((string) $no);
            $i = 0;
            $str = [];
            $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
            while ($i < $digits_length) {
                $divider = ($i == 2) ? 10 : 100;
                $numberPart = $no % $divider;
                $no = (int) ($no / $divider);
                $i += ($divider == 10) ? 1 : 2;
                if ($numberPart) {
                    $plural = (($counter = count($str)) && $numberPart > 9) ? '' : '';
                    $hundred = ($counter == 1 && $str[0]) ? ' and ' : '';
                    $str[] = ($numberPart < 21) ? $words[$numberPart] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                        : $words[floor($numberPart / 10) * 10] . ' ' . $words[$numberPart % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
                } else {
                    $str[] = null;
                }
            }
            $rupees = implode('', array_reverse($str));
        }

        $paise = '';
        if ($decimal > 0) {
            if ($decimal < 21) {
                $paisePart = $words[$decimal];
            } else {
                $paisePart = $words[floor($decimal / 10) * 10] . ' ' . $words[$decimal % 10];
            }
            $paise = ' and ' . trim($paisePart) . ' Paise';
        }

        return trim(preg_replace('/\s+/', ' ', trim($rupees) . ' Rupees' . $paise . ' Only'));
    }
}
