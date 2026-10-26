<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Session;

/**
 * Print Output and Document Rendering Parity Test (Chunk 20).
 * Verifies print CSS isolation, A4 page layout rules,
 * currency translation to words (Indian numbering system), and printable templates.
 */
class PrintOutputParityTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);

        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root . '/public/assets/css/app.css');

        // 1. @media print CSS Isolation Rules
        $assert('PRINTPAR-01', 'Print media query @media print exists in app.css', 
            str_contains($css, '@media print'));

        $assert('PRINTPAR-02', 'Print styles hide navigation sidebar, header, footer, toasts, and action buttons', 
            str_contains($css, '.erp-sidebar, .erp-header, .erp-footer, .toast-container, .btn, .no-print') &&
            str_contains($css, 'display: none !important'));

        $assert('PRINTPAR-03', 'Print layout resets body background to white and text to solid black for high contrast', 
            str_contains($css, 'background-color: #ffffff !important') &&
            str_contains($css, 'color: #000000 !important'));

        // 2. Number to Words Conversion (Indian Currency System)
        $words1 = number_to_words_inr(125000.50);
        $assert('PRINTPAR-04', 'number_to_words_inr converts 1,25,000.50 to Lakhs and Paise correctly', 
            $words1 === 'Rupees One Lakh Twenty Five Thousand and Fifty Paise Only');

        $words2 = number_to_words_inr(4500.00);
        $assert('PRINTPAR-05', 'number_to_words_inr converts 4,500.00 to Thousand correctly', 
            $words2 === 'Rupees Four Thousand Five Hundred Only');

        $words3 = number_to_words_inr(15000000.00);
        $assert('PRINTPAR-06', 'number_to_words_inr converts 1.5 Crores correctly', 
            $words3 === 'Rupees One Crore Fifty Lakh Only');

        // 3. Currency Symbol & Formatting
        $formatted = format_currency(1234567.89);
        $assert('PRINTPAR-07', 'format_currency outputs standard Indian Rupee prefix and 2 decimals', 
            str_starts_with($formatted, '₹') && str_contains($formatted, '1,234,567.89'));

        // Clean up
        Session::clear();
    }
}
