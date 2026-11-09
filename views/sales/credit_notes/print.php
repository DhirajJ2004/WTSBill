<?php
$cn = $creditNote ?? [];
$company = $company ?? [];
$customer = $customer ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Credit Note <?= htmlspecialchars($cn['credit_note_number'] ?? 'CN-2026-0001') ?> — WTSBill ERP</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; margin: 0; padding: 20px; background: #fff; color: #000; }
        .cn-box { border: 1.5px solid #000; max-width: 800px; margin: 0 auto; padding: 0; }
        .cn-header { display: flex; justify-content: space-between; border-bottom: 1.5px solid #000; padding: 12px 16px; background: #fff; }
        .cn-title { text-align: right; }
        .cn-title h2 { margin: 0; font-size: 14pt; letter-spacing: 0.5px; }
        .info-grid { display: flex; border-bottom: 1px solid #000; }
        .info-col { width: 50%; padding: 10px 14px; }
        .info-col:first-child { border-right: 1px solid #000; }
        .info-title { font-weight: bold; font-size: 8pt; text-transform: uppercase; margin-bottom: 4px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eee; border-bottom: 1px solid #000; padding: 6px 8px; font-size: 8.5pt; text-align: left; }
        td { padding: 6px 8px; font-size: 8.5pt; border-bottom: 1px solid #eee; }
        .totals-row td { font-weight: bold; background: #f9f9f9; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .footer-note { padding: 12px 16px; display: flex; justify-content: space-between; align-items: flex-end; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="max-width: 800px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="/sales/credit-notes" style="color: #235347; text-decoration: none; font-weight: bold;">&larr; Back to Credit Notes</a>
        <button onclick="window.print()" style="padding: 6px 16px; background: #235347; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Print Credit Note</button>
    </div>

    <div class="cn-box">
        <div class="cn-header">
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 12pt;"><?= htmlspecialchars($company['name'] ?? 'WIS TECHNOSAVVY PVT LTD') ?></h3>
                <div style="font-size: 8pt; color: #333; line-height: 1.35;">
                    <?= htmlspecialchars($company['address'] ?? 'Bandra Kurla Complex, Mumbai, Maharashtra, 411041') ?><br>
                    GSTIN: <?= htmlspecialchars($company['gstin'] ?? '27AADCW7577N1ZE') ?> | Phone: <?= htmlspecialchars($company['phone'] ?? '020 47252364') ?>
                </div>
            </div>
            <div class="cn-title">
                <h2>CREDIT NOTE</h2>
                <div style="font-size: 8pt; margin-top: 4px;">Original for Recipient</div>
                <div style="font-size: 8.5pt; font-weight: bold; margin-top: 4px;">CN #: <?= htmlspecialchars($cn['credit_note_number'] ?? 'CN-2026-0001') ?></div>
                <div style="font-size: 8pt;">Date: <?= htmlspecialchars(date('d/m/Y', strtotime($cn['credit_note_date'] ?? date('Y-m-d')))) ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-col">
                <div class="info-title">ISSUED TO (CUSTOMER)</div>
                <div style="font-weight: bold; font-size: 9.5pt;"><?= htmlspecialchars($customer['name'] ?? $cn['customer_name'] ?? 'Direct Customer') ?></div>
                <div style="font-size: 8pt; color: #333; line-height: 1.35; margin-top: 3px;">
                    <?= htmlspecialchars($customer['address'] ?? 'Customer Location, Maharashtra') ?><br>
                    GSTIN: <?= htmlspecialchars($customer['gstin'] ?? '27BBBBB1234C1Z5') ?><br>
                    Phone: <?= htmlspecialchars($customer['phone'] ?? '-') ?>
                </div>
            </div>
            <div class="info-col">
                <div class="info-title">INVOICE & ADJUSTMENT DETAILS</div>
                <div style="font-size: 8pt; line-height: 1.45;">
                    <div><strong>Original Invoice #:</strong> <?= htmlspecialchars($cn['invoice_number'] ?? 'N/A') ?></div>
                    <div><strong>Reason:</strong> <?= htmlspecialchars($cn['reason'] ?? 'Goods Return') ?></div>
                    <div><strong>Status:</strong> <?= htmlspecialchars(strtoupper($cn['status'] ?? 'ISSUED')) ?></div>
                    <?php if (!empty($cn['notes'])): ?>
                        <div style="margin-top: 4px; font-style: italic;">Note: <?= htmlspecialchars($cn['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">S.NO.</th>
                    <th style="width: 52%;">PARTICULARS / DESCRIPTION</th>
                    <th style="width: 15%; text-align: right;">TAXABLE (₹)</th>
                    <th style="width: 10%; text-align: right;">GST %</th>
                    <th style="width: 15%; text-align: right;">CREDIT VALUE (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <div style="font-weight: bold;"><?= htmlspecialchars($cn['reason'] ?? 'Sales Return Adjustment') ?></div>
                        <div style="font-size: 7.5pt; color: #555;">Credit adjustment against invoice <?= htmlspecialchars($cn['invoice_number'] ?? '-') ?></div>
                    </td>
                    <td style="text-align: right;"><?= number_format((float)($cn['taxable_amount'] ?? $cn['amount'] ?? 0), 2) ?></td>
                    <td style="text-align: right;"><?= (int)($cn['gst_rate'] ?? 18) ?>%</td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format((float)($cn['total_amount'] ?? $cn['amount'] ?? 0), 2) ?></td>
                </tr>
                <tr class="totals-row">
                    <td colspan="2" style="padding-left: 12px;">TOTAL CREDIT ADJUSTMENT</td>
                    <td style="text-align: right;">₹ <?= number_format((float)($cn['taxable_amount'] ?? $cn['amount'] ?? 0), 2) ?></td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right;">₹ <?= number_format((float)($cn['total_amount'] ?? $cn['amount'] ?? 0), 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="footer-note">
            <div style="font-size: 7.5pt; color: #555;">
                This credit note reduces the outward tax liability and adjusts the customer's ledger balance.<br>
                Computer Generated Document — WTSBill ERP
            </div>
            <div style="text-align: right;">
                <div style="font-size: 8pt; font-weight: bold;">For <?= htmlspecialchars($company['name'] ?? 'WIS TECHNOSAVVY PVT LTD') ?></div>
                <div style="margin-top: 35px; border-top: 1px solid #333; font-size: 7.5pt; padding-top: 2px;">Authorized Signatory</div>
            </div>
        </div>
    </div>
</body>
</html>

