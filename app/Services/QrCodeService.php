<?php

declare(strict_types=1);

namespace WTSBill\Services;

/**
 * Server-Side Pure PHP QR Code Generator (ISO/IEC 18004 Model 2).
 * Generates vector SVG QR codes with zero external dependencies,
 * zero network requests, and zero Google APIs.
 */
class QrCodeService
{
    private const GF256_POLY = 0x11d; // x^8 + x^4 + x^3 + x^2 + 1
    private static array $exp = [];
    private static array $log = [];
    private static bool $gfInit = false;

    // Alignment pattern center positions for versions 1 to 10
    private static array $alignmentPatternPositions = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    // Version definitions for Error Correction Level M:
    // [totalCodewords, ecCodewordsPerBlock, numBlocksG1, dataCodewordsG1, numBlocksG2, dataCodewordsG2]
    private static array $versionSpecsM = [
        1  => ['total' => 26,  'ec' => 10, 'b1' => 1, 'd1' => 16, 'b2' => 0, 'd2' => 0],
        2  => ['total' => 44,  'ec' => 16, 'b1' => 1, 'd1' => 28, 'b2' => 0, 'd2' => 0],
        3  => ['total' => 70,  'ec' => 26, 'b1' => 1, 'd1' => 44, 'b2' => 0, 'd2' => 0],
        4  => ['total' => 100, 'ec' => 18, 'b1' => 2, 'd1' => 32, 'b2' => 0, 'd2' => 0],
        5  => ['total' => 134, 'ec' => 24, 'b1' => 2, 'd1' => 43, 'b2' => 0, 'd2' => 0],
        6  => ['total' => 172, 'ec' => 16, 'b1' => 4, 'd1' => 27, 'b2' => 0, 'd2' => 0],
        7  => ['total' => 196, 'ec' => 18, 'b1' => 4, 'd1' => 31, 'b2' => 0, 'd2' => 0],
        8  => ['total' => 242, 'ec' => 22, 'b1' => 2, 'd1' => 38, 'b2' => 2, 'd2' => 39],
        9  => ['total' => 292, 'ec' => 22, 'b1' => 3, 'd1' => 36, 'b2' => 2, 'd2' => 37],
        10 => ['total' => 346, 'ec' => 26, 'b1' => 4, 'd1' => 43, 'b2' => 1, 'd2' => 44],
    ];

    private static function initGf(): void
    {
        if (self::$gfInit) {
            return;
        }
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= self::GF256_POLY;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$gfInit = true;
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    private static function rsGenPoly(int $ecLen): array
    {
        $poly = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $factor = self::$exp[$i];
            $newPoly = array_fill(0, count($poly) + 1, 0);
            for ($j = 0; $j < count($poly); $j++) {
                $newPoly[$j] ^= $poly[$j];
                $newPoly[$j + 1] ^= self::gfMul($poly[$j], $factor);
            }
            $poly = $newPoly;
        }
        return $poly;
    }

    private static function rsComputeRemainder(array $data, int $ecLen): array
    {
        self::initGf();
        $poly = self::rsGenPoly($ecLen);
        $msg = array_pad($data, count($data) + $ecLen, 0);
        for ($i = 0; $i < count($data); $i++) {
            $lead = $msg[$i];
            if ($lead !== 0) {
                for ($j = 0; $j < count($poly); $j++) {
                    $msg[$i + $j] ^= self::gfMul($poly[$j], $lead);
                }
            }
        }
        return array_slice($msg, count($data));
    }

    public static function encode(string $text): array
    {
        self::initGf();
        $rawBytes = array_values(unpack('C*', $text));
        $len = count($rawBytes);

        // Find minimum version
        $version = 1;
        for ($v = 1; $v <= 10; $v++) {
            $spec = self::$versionSpecsM[$v];
            $totalDataBytes = ($spec['b1'] * $spec['d1']) + ($spec['b2'] * $spec['d2']);
            $charCountBits = ($v <= 9) ? 8 : 16;
            $availBytes = (int)floor(($totalDataBytes * 8 - (4 + $charCountBits)) / 8);
            if ($availBytes >= $len) {
                $version = $v;
                break;
            }
        }

        $spec = self::$versionSpecsM[$version];
        $totalDataBytes = ($spec['b1'] * $spec['d1']) + ($spec['b2'] * $spec['d2']);
        $charCountBits = ($version <= 9) ? 8 : 16;

        // Build bitstream
        $bits = '';
        $bits .= '0100'; // Byte mode indicator
        $bits .= str_pad(decbin($len), $charCountBits, '0', STR_PAD_LEFT);
        foreach ($rawBytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        // Terminator up to 4 zeroes
        $remainingBits = ($totalDataBytes * 8) - strlen($bits);
        $termLen = min(4, max(0, $remainingBits));
        $bits .= str_repeat('0', $termLen);

        // Pad to byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Pad codewords
        $padBytes = ['11101100', '00010001'];
        $padIdx = 0;
        while (strlen($bits) < $totalDataBytes * 8) {
            $bits .= $padBytes[$padIdx % 2];
            $padIdx++;
        }

        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }

        // Split into blocks and compute EC
        $blocksData = [];
        $blocksEc = [];
        $offset = 0;
        for ($b = 0; $b < $spec['b1']; $b++) {
            $blk = array_slice($dataCodewords, $offset, $spec['d1']);
            $blocksData[] = $blk;
            $blocksEc[] = self::rsComputeRemainder($blk, $spec['ec']);
            $offset += $spec['d1'];
        }
        for ($b = 0; $b < $spec['b2']; $b++) {
            $blk = array_slice($dataCodewords, $offset, $spec['d2']);
            $blocksData[] = $blk;
            $blocksEc[] = self::rsComputeRemainder($blk, $spec['ec']);
            $offset += $spec['d2'];
        }

        // Interleave data
        $finalCodewords = [];
        $maxDataLen = max($spec['d1'], $spec['d2']);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocksData as $blk) {
                if ($i < count($blk)) {
                    $finalCodewords[] = $blk[$i];
                }
            }
        }
        // Interleave EC
        for ($i = 0; $i < $spec['ec']; $i++) {
            foreach ($blocksEc as $blk) {
                if ($i < count($blk)) {
                    $finalCodewords[] = $blk[$i];
                }
            }
        }

        // Construct Matrix
        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        // Place Finder Patterns
        $setFinder = function (int $r, int $c) use (&$matrix, &$isFunction, $size) {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $row = $r + $dr;
                    $col = $c + $dc;
                    if ($row >= 0 && $row < $size && $col >= 0 && $col < $size) {
                        $isFunc = ($dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6);
                        $isFunction[$row][$col] = true;
                        if ($isFunc) {
                            $matrix[$row][$col] = ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6 || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)) ? 1 : 0;
                        } else {
                            $matrix[$row][$col] = 0; // Separator
                        }
                    }
                }
            }
        };

        $setFinder(0, 0);
        $setFinder(0, $size - 7);
        $setFinder($size - 7, 0);

        // Alignment patterns
        $alignPos = self::$alignmentPatternPositions[$version];
        $count = count($alignPos);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $ar = $alignPos[$i];
                $ac = $alignPos[$j];
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $row = $ar + $dr;
                        $col = $ac + $dc;
                        $isFunction[$row][$col] = true;
                        $matrix[$row][$col] = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0)) ? 1 : 0;
                    }
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
                $isFunction[6][$i] = true;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
                $isFunction[$i][6] = true;
            }
        }

        // Dark module
        $matrix[4 * $version + 9][8] = 1;
        $isFunction[4 * $version + 9][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $isFunction[8][$i] = true;
            $isFunction[$i][8] = true;
        }

        // Place codewords bits in zig-zag
        $bitArray = [];
        foreach ($finalCodewords as $cw) {
            $bitStr = str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
            for ($b = 0; $b < 8; $b++) {
                $bitArray[] = (int)$bitStr[$b];
            }
        }

        $bitIdx = 0;
        $numBits = count($bitArray);
        $col = $size - 1;
        $up = true;

        while ($col > 0) {
            if ($col === 6) {
                $col--; // skip vertical timing column
            }
            $rowList = $up ? range($size - 1, 0) : range(0, $size - 1);
            foreach ($rowList as $r) {
                for ($c = $col; $c >= $col - 1; $c--) {
                    if (!$isFunction[$r][$c]) {
                        $val = ($bitIdx < $numBits) ? $bitArray[$bitIdx++] : 0;
                        $matrix[$r][$c] = $val;
                    }
                }
            }
            $up = !$up;
            $col -= 2;
        }

        // Apply Mask Pattern (Mask 0: (row + col) % 2 === 0)
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$isFunction[$r][$c]) {
                    if (($r + $c) % 2 === 0) {
                        $matrix[$r][$c] ^= 1;
                    }
                }
            }
        }

        // Format info bits for Level M (00) and Mask 0 (000) -> 00000
        // Standard BCH code XORed with 0x5412 = 101010000010010
        $formatBits = '101010000010010';
        $matrix[8][0] = (int)$formatBits[0];
        $matrix[8][1] = (int)$formatBits[1];
        $matrix[8][2] = (int)$formatBits[2];
        $matrix[8][3] = (int)$formatBits[3];
        $matrix[8][4] = (int)$formatBits[4];
        $matrix[8][5] = (int)$formatBits[5];
        $matrix[8][7] = (int)$formatBits[6];
        $matrix[8][8] = (int)$formatBits[7];
        $matrix[7][8] = (int)$formatBits[8];
        $matrix[5][8] = (int)$formatBits[9];
        $matrix[4][8] = (int)$formatBits[10];
        $matrix[3][8] = (int)$formatBits[11];
        $matrix[2][8] = (int)$formatBits[12];
        $matrix[1][8] = (int)$formatBits[13];
        $matrix[0][8] = (int)$formatBits[14];

        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = (int)$formatBits[$i];
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 8 + $i] = (int)$formatBits[7 + $i];
        }

        return ['size' => $size, 'matrix' => $matrix];
    }

    /**
     * Generate standalone SVG markup for any UPI or string payload.
     */
    public static function generateSvg(string $text, int $displaySize = 100): string
    {
        if (trim($text) === '') {
            return '';
        }

        $qr = self::encode($text);
        $size = $qr['size'];
        $matrix = $qr['matrix'];
        $margin = 2;
        $totalSize = $size + ($margin * 2);

        $path = '';
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $path .= "M{$x},{$y}h1v1h-1z ";
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalSize . ' ' . $totalSize . '" width="' . $displaySize . '" height="' . $displaySize . '" shape-rendering="crispEdges" aria-label="UPI Payment QR Code">'
             . '<rect width="' . $totalSize . '" height="' . $totalSize . '" fill="#ffffff"/>'
             . '<path d="' . rtrim($path) . '" fill="#000000"/>'
             . '</svg>';
    }

    /**
     * Build standard UPI deep link string.
     */
    public static function buildUpiPayload(
        string $vpa,
        string $payeeName,
        float $amount,
        string $invoiceNumber
    ): string {
        $pa = trim($vpa);
        $pn = trim($payeeName);
        $am = sprintf('%.2f', max(0, $amount));
        $cu = 'INR';
        $tn = trim($invoiceNumber);

        return "upi://pay?pa={$pa}&pn=" . rawurlencode($pn) . "&am={$am}&cu={$cu}&tn=" . rawurlencode($tn);
    }
}
