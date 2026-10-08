<?php

namespace App\DocumentEngine\Renderers;

use App\DocumentEngine\Models\DocumentModel;
use App\DocumentEngine\Formatters\IndianCurrencyFormatter;
use App\Models\DocumentTemplate;

class ThermalDocumentRenderer
{
    public static function render(DocumentModel $doc, ?DocumentTemplate $template = null): string
    {
        $company = $doc->company;
        $party = $doc->party;
        $totals = $doc->totals;

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($doc->documentNumber) ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            width: 74mm;
            margin: 0 auto;
            padding: 4px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .divider { border-bottom: 1px dashed #000; margin: 4px 0; }
        .double-divider { border-bottom: 2px solid #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        td, th { padding: 2px 0; }
        .receipt-header { text-align: center; margin-bottom: 6px; }
        .receipt-header h2 { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .receipt-header p { font-size: 10px; }
        @media print {
            body { width: 100%; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt-header">
        <h2><?= htmlspecialchars($company['name'] ?? 'Bharat Store') ?></h2>
        <?php if (!empty($company['address_line1'])): ?>
            <p><?= htmlspecialchars($company['address_line1']) ?></p>
        <?php endif; ?>
        <?php if (!empty($company['gstin'])): ?>
            <p>GSTIN: <?= htmlspecialchars($company['gstin']) ?></p>
        <?php endif; ?>
        <?php if (!empty($company['phone'])): ?>
            <p>Ph: <?= htmlspecialchars($company['phone']) ?></p>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <div style="font-size: 10px;">
        <div><strong><?= htmlspecialchars($doc->title) ?></strong></div>
        <div>No: <?= htmlspecialchars($doc->documentNumber) ?></div>
        <div>Date: <?= htmlspecialchars($doc->documentDate) ?></div>
        <?php if ($party): ?>
            <div>Cust: <?= htmlspecialchars($party['name'] ?? 'Walk-in') ?></div>
            <?php if (!empty($party['phone'])): ?><div>Ph: <?= htmlspecialchars($party['phone']) ?></div><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th style="text-align: left; width: 45%;">Item</th>
                <th class="text-right" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 20%;">Rate</th>
                <th class="text-right" style="width: 20%;">Amt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($doc->items as $item): ?>
                <tr>
                    <td style="text-align: left;"><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td class="text-right"><?= htmlspecialchars($item['quantity'] ?? '1') ?></td>
                    <td class="text-right"><?= sprintf('%.2f', floatval($item['unit_price'] ?? 0)) ?></td>
                    <td class="text-right"><?= sprintf('%.2f', floatval($item['total_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td>Taxable Amount:</td>
            <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['taxable_amount'] ?? 0)) ?></td>
        </tr>
        <?php if (floatval($totals['cgst_amount'] ?? 0) > 0): ?>
            <tr>
                <td>CGST + SGST:</td>
                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['cgst_amount']) + floatval($totals['sgst_amount'])) ?></td>
            </tr>
        <?php endif; ?>
        <?php if (floatval($totals['igst_amount'] ?? 0) > 0): ?>
            <tr>
                <td>IGST:</td>
                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['igst_amount'])) ?></td>
            </tr>
        <?php endif; ?>
        <tr class="font-bold" style="font-size: 12px;">
            <td>NET TOTAL:</td>
            <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['grand_total'] ?? 0)) ?></td>
        </tr>
    </table>

    <div class="double-divider"></div>

    <div class="text-center" style="font-size: 10px; margin-top: 6px;">
        <p>Thank you for your business!</p>
        <p style="font-size: 8px; color: #555;"><?= date('d-m-Y H:i') ?></p>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
