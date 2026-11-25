<?php
/**
 * WTSBill ERP — Credit Note Printable Template
 * Official GST Tax Credit Note format with Company Branding, Line Items, and GST Tax Breakdown.
 *
 * @var array $creditNote
 * @var array $items
 * @var array $company
 * @var array $branding
 */

$cn = $creditNote ?? [];
$items = $items ?? [];
$comp = $company ?? [];
$b = $branding ?? [];

$compName = $comp['legal_name'] ?? ($comp['name'] ?? 'Wis Technosavvy Pvt Ltd');
$compGstin = $comp['gstin'] ?? '';
$compPhone = $comp['phone'] ?? '';
$compEmail = $comp['email'] ?? '';
$compAddress = trim(($comp['address_line1'] ?? '') . ' ' . ($comp['address_line2'] ?? ''));
$compCity = $comp['city'] ?? '';
$compState = $comp['state'] ?? 'Maharashtra';
$compPincode = $comp['pincode'] ?? '';

$logoUrl = $b['logo_url'] ?? '/assets/images/logo.png';
$sigUrl = $b['signature_url'] ?? null;
$stampUrl = $b['stamp_url'] ?? null;

$subTotal = (float)($cn['sub_total'] ?? 0);
$totalTax = (float)($cn['total_tax'] ?? 0);
$cgstRev = (float)($cn['cgst_reversal'] ?? 0);
$sgstRev = (float)($cn['sgst_reversal'] ?? 0);
$igstRev = (float)($cn['igst_reversal'] ?? 0);
$grandTotal = (float)($cn['amount'] ?? ($subTotal + $totalTax));

if ($subTotal <= 0 && $grandTotal > 0) {
    // If credit note was recorded with flat amount
    $subTotal = round($grandTotal / 1.18, 2);
    $totalTax = $grandTotal - $subTotal;
    $cgstRev = round($totalTax / 2, 2);
    $sgstRev = $totalTax - $cgstRev;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note — <?= htmlspecialchars($cn['credit_note_number'] ?? 'CN') ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($logoUrl) ?>">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #051F20;
            background: #F1F5F9;
            margin: 0;
            padding: 20px;
            line-height: 1.4;
        }
        .cn-wrapper {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .company-logo {
            max-height: 55px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .cn-title-block {
            text-align: right;
            vertical-align: top;
        }
        .cn-title-block h1 {
            margin: 0;
            font-size: 22px;
            color: #235347;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cn-number-pill {
            display: inline-block;
            background: #DAF1DE;
            color: #0B2B26;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 4px;
            margin-top: 6px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 22px;
            border-top: 2px solid #DAF1DE;
            border-bottom: 2px solid #DAF1DE;
            padding: 14px 0;
        }
        .info-col h4 {
            margin: 0 0 6px 0;
            font-size: 11px;
            text-transform: uppercase;
            color: #4B5563;
            letter-spacing: 0.04em;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #0B2B26;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 12px;
        }
        .items-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .tax-breakdown-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .tax-breakdown-table th {
            background: #F1F5F9;
            color: #4B5563;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #E2E8F0;
        }
        .tax-breakdown-table td {
            padding: 6px 8px;
            border: 1px solid #E2E8F0;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .totals-table tr.grand-total-row td {
            background: #DAF1DE;
            color: #051F20;
            font-size: 14px;
            font-weight: 700;
            border-top: 2px solid #235347;
            border-bottom: 2px solid #235347;
        }
        .signatory-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px dashed #CBD5E1;
        }
        .signatory-box {
            text-align: center;
            width: 220px;
        }
        .sig-img {
            max-height: 50px;
            max-width: 140px;
            object-fit: contain;
        }
        .stamp-img {
            max-height: 60px;
            max-width: 80px;
            object-fit: contain;
        }
        .no-print-bar {
            max-width: 820px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-print { background: #235347; color: #ffffff; border: none; }
        .btn-back { background: #ffffff; color: #051F20; border: 1px solid #CBD5E1; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .cn-wrapper { box-shadow: none; border: none; padding: 0; }
            .no-print-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="/sales/credit-notes" class="btn btn-back">← Back to Credit Notes</a>
        <button onclick="window.print()" class="btn btn-print">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Credit Note
        </button>
    </div>

    <div class="cn-wrapper">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="vertical-align: top; width: 60%;">
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="company-logo"><br>
                    <?php endif; ?>
                    <strong style="font-size: 15px; color: #051F20;"><?= htmlspecialchars($compName) ?></strong><br>
                    <span style="color: #4B5563; font-size: 11px;">
                        <?= htmlspecialchars($compAddress) ?><?= !empty($compCity) ? ', ' . htmlspecialchars($compCity) : '' ?>, <?= htmlspecialchars($compState) ?> <?= htmlspecialchars($compPincode) ?><br>
                        <?php if ($compGstin): ?>GSTIN: <strong><?= htmlspecialchars($compGstin) ?></strong> &bull; <?php endif; ?>
                        Phone: <?= htmlspecialchars($compPhone ?: '—') ?>
                    </span>
                </td>
                <td class="cn-title-block">
                    <h1>Tax Credit Note</h1>
                    <div style="font-size: 11px; color: #4B5563; text-transform: uppercase;">Original for Recipient</div>
                    <div class="cn-number-pill"><?= htmlspecialchars($cn['credit_note_number'] ?? 'CN') ?></div>
                    <div style="margin-top: 6px; font-size: 12px; color: #4B5563;">
                        Credit Note Date: <strong><?= date('d M Y', strtotime($cn['credit_note_date'] ?? 'now')) ?></strong><br>
                        Original Invoice: <strong style="color: #235347;"><?= htmlspecialchars($cn['invoice_number'] ?? 'N/A') ?></strong><br>
                        Status: <strong style="color: #10B981;"><?= htmlspecialchars(strtoupper($cn['status'] ?? 'APPROVED')) ?></strong>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Details Grid -->
        <div class="details-grid">
            <div class="info-col">
                <h4>Issued To (Customer / Buyer):</h4>
                <strong style="font-size: 14px; color: #051F20;"><?= htmlspecialchars($cn['customer_name'] ?? 'Direct Customer') ?></strong><br>
                <span style="color: #4B5563; font-size: 12px;">
                    <?php if (!empty($cn['customer_company']) && $cn['customer_company'] !== $cn['customer_name']): ?>
                        <?= htmlspecialchars($cn['customer_company']) ?><br>
                    <?php endif; ?>
                    <?= nl2br(htmlspecialchars($cn['customer_address'] ?: 'Customer Registered Address')) ?><br>
                    <?= htmlspecialchars($cn['customer_city'] ?? '') ?> <?= htmlspecialchars($cn['customer_state'] ?? 'Maharashtra') ?> <?= htmlspecialchars($cn['customer_pincode'] ?? '') ?><br>
                    <?php if (!empty($cn['customer_gstin'])): ?>GSTIN: <strong><?= htmlspecialchars($cn['customer_gstin']) ?></strong><br><?php endif; ?>
                    <?php if (!empty($cn['customer_phone'])): ?>Contact: <?= htmlspecialchars($cn['customer_phone']) ?><?php endif; ?>
                </span>
            </div>

            <div class="info-col">
                <h4>Adjustment &amp; Reversal Details:</h4>
                <span style="font-size: 12px; color: #051F20;">
                    Reason for Issuance: <strong><?= htmlspecialchars($cn['reason'] ?: 'Sales Return / Commercial Adjustment') ?></strong><br>
                    Original Tax Invoice #: <strong><?= htmlspecialchars($cn['invoice_number'] ?? 'N/A') ?></strong><br>
                    Place of Supply: <strong><?= htmlspecialchars($cn['customer_state'] ?? 'Maharashtra') ?></strong><br>
                    <?php if (!empty($cn['notes'])): ?>
                        Internal Notes: <em><?= htmlspecialchars($cn['notes']) ?></em>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Particulars & Line Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">#</th>
                    <th>Item Description &amp; Particulars</th>
                    <th style="width: 70px; text-align: right;">Qty</th>
                    <th style="width: 50px; text-align: center;">Unit</th>
                    <th style="width: 85px; text-align: right;">Rate</th>
                    <th style="width: 95px; text-align: right;">Taxable (₹)</th>
                    <th style="width: 55px; text-align: right;">GST</th>
                    <th style="width: 100px; text-align: right;">Total Credit (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php $idx = 1; ?>
                    <?php foreach ($items as $item): ?>
                        <?php 
                        $iQty = (float)($item['quantity'] ?? 1);
                        $iRate = (float)($item['unit_price'] ?? 0);
                        $iTaxable = (float)($item['taxable_value'] ?? ($iQty * $iRate));
                        $iGstRate = (float)($item['gst_rate'] ?? 18);
                        $iAmt = (float)($item['amount'] ?? ($iTaxable * (1 + $iGstRate / 100)));
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748B;"><?= $idx++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if (!empty($item['sku'])): ?>
                                    <span style="font-size: 10px; color: #64748B;">(SKU: <?= htmlspecialchars($item['sku']) ?>)</span>
                                <?php endif; ?>
                                <?php if (!empty($item['hsn_sac'])): ?>
                                    <div style="font-size: 10px; color: #64748B;">HSN/SAC: <?= htmlspecialchars($item['hsn_sac']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format($iQty, 2) ?></td>
                            <td style="text-align: center; color: #4B5563;"><?= htmlspecialchars($item['unit'] ?: 'Pcs') ?></td>
                            <td style="text-align: right;">₹<?= number_format($iRate, 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($iTaxable, 2) ?></td>
                            <td style="text-align: right;"><?= (int)$iGstRate ?>%</td>
                            <td style="text-align: right; font-weight: 700;">₹<?= number_format($iAmt, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td style="text-align: center; color: #64748B;">1</td>
                        <td>
                            <strong><?= htmlspecialchars($cn['reason'] ?: 'Sales Return / Commercial Adjustment') ?></strong>
                            <div style="font-size: 10px; color: #64748B;">Credit reversal against Tax Invoice #<?= htmlspecialchars($cn['invoice_number'] ?? 'N/A') ?></div>
                        </td>
                        <td style="text-align: right; font-weight: 600;">1.00</td>
                        <td style="text-align: center; color: #4B5563;">Lot</td>
                        <td style="text-align: right;">₹<?= number_format($subTotal, 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($subTotal, 2) ?></td>
                        <td style="text-align: right;">18%</td>
                        <td style="text-align: right; font-weight: 700;">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Summary & GST Breakdown -->
        <div class="summary-grid">
            <div>
                <table class="tax-breakdown-table">
                    <thead>
                        <tr>
                            <th>GST Component</th>
                            <th style="text-align: right;">Reversal Rate</th>
                            <th style="text-align: right;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($igstRev > 0): ?>
                            <tr>
                                <td>Integrated GST (IGST)</td>
                                <td style="text-align: right;">18.00%</td>
                                <td style="text-align: right; font-weight: 600;">₹<?= number_format($igstRev, 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td>Central GST (CGST)</td>
                                <td style="text-align: right;">9.00%</td>
                                <td style="text-align: right; font-weight: 600;">₹<?= number_format($cgstRev, 2) ?></td>
                            </tr>
                            <tr>
                                <td>State GST (SGST)</td>
                                <td style="text-align: right;">9.00%</td>
                                <td style="text-align: right; font-weight: 600;">₹<?= number_format($sgstRev, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr style="background: #F8FAFC; font-weight: 700;">
                            <td>Total Reversible Tax</td>
                            <td></td>
                            <td style="text-align: right; color: #235347;">₹<?= number_format($totalTax, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <table class="totals-table">
                    <tr>
                        <td style="color: #4B5563;">Taxable Subtotal:</td>
                        <td style="text-align: right; font-weight: 600;">₹<?= number_format($subTotal, 2) ?></td>
                    </tr>
                    <tr>
                        <td style="color: #4B5563;">Total GST Reversal:</td>
                        <td style="text-align: right; font-weight: 600;">₹<?= number_format($totalTax, 2) ?></td>
                    </tr>
                    <tr class="grand-total-row">
                        <td>Net Credit Total:</td>
                        <td style="text-align: right;">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Declarations -->
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 8px 12px; border-radius: 6px; font-size: 11px; color: #4B5563; margin-top: 18px;">
            <strong>Statutory Declaration:</strong> This Credit Note is issued in accordance with the provisions of Section 34 of the CGST Act, 2017. The corresponding GST liability reversal has been adjusted.
        </div>

        <!-- Signatory Block -->
        <div class="signatory-section">
            <div class="signatory-box">
                <div style="height: 55px; display: flex; align-items: flex-end; justify-content: center;">
                    <span style="border-bottom: 1px solid #CBD5E1; width: 150px; display: block;"></span>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #051F20; margin-top: 6px;">Receiver's Acknowledgment</div>
            </div>

            <div class="signatory-box">
                <div style="height: 55px; display: flex; align-items: flex-end; justify-content: center; gap: 8px;">
                    <?php if (!empty($stampUrl)): ?>
                        <img src="<?= htmlspecialchars($stampUrl) ?>" alt="Stamp" class="stamp-img">
                    <?php endif; ?>
                    <?php if (!empty($sigUrl)): ?>
                        <img src="<?= htmlspecialchars($sigUrl) ?>" alt="Signature" class="sig-img">
                    <?php endif; ?>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #051F20; margin-top: 6px;">
                    For <?= htmlspecialchars($compName) ?><br>
                    <span style="font-weight: 400; color: #64748B;">Authorized Signatory</span>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
