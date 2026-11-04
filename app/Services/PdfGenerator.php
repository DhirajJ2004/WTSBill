<?php

declare(strict_types=1);

namespace WTSBill\Services;

/**
 * Pure PHP High-Performance Standard PDF-1.4 Generator.
 * Generates valid, self-contained, standalone PDF documents for Tax Invoices and Quotations
 * with zero external dependencies.
 */
class PdfGenerator
{
    /**
     * Generate a complete, compliant PDF-1.4 for a Tax Invoice.
     */
    public static function generateInvoicePdf(array $invoice, array $company, array $items = []): string
    {
        $docNumber = $invoice['invoice_number'] ?? ('INV-' . ($invoice['id'] ?? '000'));
        $docDate = $invoice['invoice_date'] ?? date('Y-m-d');
        $customerName = $invoice['customer_name'] ?? 'Valued Customer';
        $customerGstin = $invoice['customer_gstin'] ?? 'URP';
        $customerPhone = $invoice['customer_phone'] ?? '';
        $companyName = $company['name'] ?? 'WTSBill Enterprise';
        $companyGstin = $company['gstin'] ?? 'Not Specified';
        $grandTotal = number_format((float)($invoice['grand_total'] ?? 0), 2);
        $amountPaid = number_format((float)($invoice['amount_paid'] ?? 0), 2);
        $amountDue = number_format((float)($invoice['amount_due'] ?? ($invoice['grand_total'] ?? 0)), 2);
        $status = strtoupper((string)($invoice['status'] ?? 'UNPAID'));

        $lines = [];
        $lines[] = "TAX INVOICE";
        $lines[] = "==========================================================";
        $lines[] = "Issuer:       " . self::sanitize($companyName);
        $lines[] = "GSTIN:        " . self::sanitize($companyGstin);
        $lines[] = "Invoice No:   " . self::sanitize($docNumber);
        $lines[] = "Invoice Date: " . self::sanitize($docDate);
        $lines[] = "Payment Status: " . self::sanitize($status);
        $lines[] = "----------------------------------------------------------";
        $lines[] = "Billed To:    " . self::sanitize($customerName);
        $lines[] = "Customer GST: " . self::sanitize($customerGstin);
        if ($customerPhone) {
            $lines[] = "Phone:        " . self::sanitize($customerPhone);
        }
        $lines[] = "----------------------------------------------------------";
        $lines[] = sprintf("%-4s %-26s %-6s %-10s %-10s", "#", "Item Description", "Qty", "Rate", "Amount");
        $lines[] = "----------------------------------------------------------";

        $idx = 1;
        foreach ($items as $item) {
            $name = substr((string)($item['product_name'] ?? ($item['item_name'] ?? 'Item')), 0, 24);
            $qty = (string)($item['quantity'] ?? '1');
            $rate = number_format((float)($item['unit_price'] ?? 0), 2);
            $total = number_format((float)($item['total_amount'] ?? ($item['total'] ?? 0)), 2);
            $lines[] = sprintf("%-4d %-26s %-6s %-10s %-10s", $idx++, self::sanitize($name), $qty, $rate, $total);
        }
        if (empty($items)) {
            $lines[] = sprintf("%-4d %-26s %-6s %-10s %-10s", 1, "Standard Billing Service", "1", $grandTotal, $grandTotal);
        }

        $lines[] = "----------------------------------------------------------";
        $lines[] = sprintf("%40s: INR %s", "Grand Total", $grandTotal);
        $lines[] = sprintf("%40s: INR %s", "Total Paid", $amountPaid);
        $lines[] = sprintf("%40s: INR %s", "Balance Outstanding", $amountDue);
        $lines[] = "==========================================================";
        $lines[] = "Computer Generated Invoice - Powered by WTSBill ERP";

        return self::buildPdfDocument("Tax Invoice - " . $docNumber, $lines);
    }

    /**
     * Generate a complete, compliant PDF-1.4 for a Quotation.
     */
    public static function generateQuotationPdf(array $quotation, array $company, array $items = []): string
    {
        $docNumber = $quotation['quotation_number'] ?? ('QT-' . ($quotation['id'] ?? '000'));
        $docDate = $quotation['quotation_date'] ?? date('Y-m-d');
        $validUntil = $quotation['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
        $customerName = $quotation['customer_name'] ?? 'Valued Customer';
        $companyName = $company['name'] ?? 'WTSBill Enterprise';
        $companyGstin = $company['gstin'] ?? 'Not Specified';
        $grandTotal = number_format((float)($quotation['grand_total'] ?? 0), 2);
        $status = strtoupper((string)($quotation['status'] ?? 'SENT'));

        $lines = [];
        $lines[] = "COMMERCIAL SALES QUOTATION";
        $lines[] = "==========================================================";
        $lines[] = "Issuer:         " . self::sanitize($companyName);
        $lines[] = "GSTIN:          " . self::sanitize($companyGstin);
        $lines[] = "Quotation No:   " . self::sanitize($docNumber);
        $lines[] = "Quotation Date: " . self::sanitize($docDate);
        $lines[] = "Valid Until:    " . self::sanitize($validUntil);
        $lines[] = "Status:         " . self::sanitize($status);
        $lines[] = "----------------------------------------------------------";
        $lines[] = "Client Name:    " . self::sanitize($customerName);
        $lines[] = "----------------------------------------------------------";
        $lines[] = sprintf("%-4s %-26s %-6s %-10s %-10s", "#", "Item Description", "Qty", "Rate", "Amount");
        $lines[] = "----------------------------------------------------------";

        $idx = 1;
        foreach ($items as $item) {
            $name = substr((string)($item['product_name'] ?? ($item['item_name'] ?? 'Item')), 0, 24);
            $qty = (string)($item['quantity'] ?? '1');
            $rate = number_format((float)($item['unit_price'] ?? 0), 2);
            $total = number_format((float)($item['total_amount'] ?? ($item['total'] ?? 0)), 2);
            $lines[] = sprintf("%-4d %-26s %-6s %-10s %-10s", $idx++, self::sanitize($name), $qty, $rate, $total);
        }
        if (empty($items)) {
            $lines[] = sprintf("%-4d %-26s %-6s %-10s %-10s", 1, "Estimated Products / Services", "1", $grandTotal, $grandTotal);
        }

        $lines[] = "----------------------------------------------------------";
        $lines[] = sprintf("%40s: INR %s", "Quotation Total", $grandTotal);
        $lines[] = "==========================================================";
        $lines[] = "Thank you for your business. Terms & Conditions apply.";
        $lines[] = "Computer Generated Quotation - Powered by WTSBill ERP";

        return self::buildPdfDocument("Quotation - " . $docNumber, $lines);
    }

    /**
     * Assemble text lines into standard binary PDF-1.4 format.
     */
    private static function buildPdfDocument(string $title, array $lines): string
    {
        $stream = "BT\n/F1 10 Tf\n20 TL\n50 780 Td\n";
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= "({$escaped}) '\n";
        }
        $stream .= "ET";
        $streamLen = strlen($stream);

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
        $objects[4] = "<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
        $objects[6] = "<< /Title (" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title) . ") /Producer (WTSBill ERP PDF Engine) >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$obj}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R /Info 6 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private static function sanitize(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }
}

