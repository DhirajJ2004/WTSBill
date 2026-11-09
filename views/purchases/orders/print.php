<?php
$poCompanyId = (int)($order['company_id'] ?? 1);
$poBranchId = !empty($order['branch_id']) ? (int)$order['branch_id'] : null;
$branding = \WTSBill\Services\CompanyBrandingService::getCompanyBranding($poCompanyId, $poBranchId);
$companyLogoUrl = \WTSBill\Services\CompanyBrandingService::getCompanyLogo($poCompanyId, $poBranchId);
$showPoSignature = (bool)($branding['show_signature_purchase'] ?? true);
$showPoStamp = (bool)($branding['show_stamp_purchase'] ?? true);
$poSigUrl = $branding['signature_url'];
$poStampUrl = $branding['stamp_url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Purchase Order — <?= htmlspecialchars($order['po_number']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 10px;
        }
        .container {
            border: 1.5px solid #0f172a;
            padding: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #0f172a;
            text-align: right;
        }
        .company-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .meta-box {
            border: 1px solid #cbd5e1;
            padding: 8px;
            vertical-align: top;
            font-size: 10.5px;
            line-height: 1.5;
        }
        .meta-box h4 {
            margin: 0 0 4px 0;
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
        }
        .items-table {
            margin-top: 15px;
            border: 1px solid #0f172a;
        }
        .items-table th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 10px;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #94a3b8;
        }
        .items-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
        }
        .print-btn-bar {
            text-align: right;
            margin-bottom: 15px;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            .print-btn-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn-print" onclick="window.print()">Print Purchase Order</button>
    </div>

    <div class="container">
        <!-- Header -->
        <table>
            <tr>
                <td style="vertical-align: top; width: 60%;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                        <img src="<?= htmlspecialchars($companyLogoUrl) ?>" alt="Logo" style="max-height: 48px; max-width: 140px; object-fit: contain;">
                        <div class="company-name"><?= htmlspecialchars($company['name'] ?? 'WTSBill Enterprise') ?></div>
                    </div>
                    <div style="font-size: 10px; color: #475569; margin-top: 3px;">
                        <?= htmlspecialchars($company['address'] ?? '') ?><br>
                        <?= htmlspecialchars($company['city'] ?? '') ?>, <?= htmlspecialchars($company['state'] ?? '') ?> - <?= htmlspecialchars($company['pincode'] ?? '') ?><br>
                        GSTIN: <?= htmlspecialchars($company['gstin'] ?? 'N/A') ?> | Phone: <?= htmlspecialchars($company['phone'] ?? '') ?>
                    </div>
                </td>
                <td style="vertical-align: top; width: 40%; text-align: right;">
                    <div class="title">PURCHASE ORDER</div>
                    <div style="font-size: 13px; font-weight: 700; color: #2563eb; margin-top: 4px;">
                        <?= htmlspecialchars($order['po_number']) ?>
                    </div>
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                        Date: <?= date('d-m-Y', strtotime($order['po_date'])) ?>
                    </div>
                    <div style="font-size: 10px; color: #64748b;">
                        Delivery: <?= $order['expected_delivery'] ? date('d-m-Y', strtotime($order['expected_delivery'])) : 'Immediate' ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Vendor & Ship To -->
        <table style="margin-top: 15px;">
            <tr>
                <td class="meta-box" style="width: 50%;">
                    <h4>VENDOR / SUPPLIER</h4>
                    <strong><?= htmlspecialchars($order['supplier_name']) ?></strong><br>
                    <?= htmlspecialchars($order['supplier_address'] ?: '—') ?><br>
                    State: <?= htmlspecialchars($order['supplier_state'] ?: '—') ?><br>
                    GSTIN: <strong><?= htmlspecialchars($order['supplier_gstin'] ?: 'Unregistered') ?></strong><br>
                    Phone: <?= htmlspecialchars($order['supplier_phone'] ?: '—') ?>
                </td>
                <td class="meta-box" style="width: 50%;">
                    <h4>DELIVERY ADDRESS / SHIP TO</h4>
                    <strong><?= htmlspecialchars($company['name'] ?? 'WTSBill Enterprise') ?> (<?= htmlspecialchars($order['branch_name'] ?? 'Main Branch') ?>)</strong><br>
                    <?= htmlspecialchars($company['address'] ?? '') ?><br>
                    <?= htmlspecialchars($company['city'] ?? '') ?>, <?= htmlspecialchars($company['state'] ?? '') ?><br>
                    Contact: Purchase Department
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 45%; text-align: left;">Item Description</th>
                    <th style="width: 12%; text-align: center;">HSN/SAC</th>
                    <th style="width: 10%; text-align: right;">Quantity</th>
                    <th style="width: 13%; text-align: right;">Rate (₹)</th>
                    <th style="width: 15%; text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php $idx = 1; foreach ($order['items'] as $item): ?>
                    <tr>
                        <td style="text-align: center; color: #64748b;"><?= $idx++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                            <div style="font-size: 9px; color: #64748b;">SKU: <?= htmlspecialchars($item['sku'] ?? '—') ?></div>
                        </td>
                        <td style="text-align: center;"><?= htmlspecialchars($item['hsn_sac'] ?? '—') ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format((float)$item['quantity'], 2) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                        <td style="text-align: right;">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                        <td style="text-align: right; font-weight: 600;">₹<?= number_format((float)$item['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Table -->
        <table style="margin-top: 10px;">
            <tr>
                <td style="width: 60%; vertical-align: top; font-size: 10px; color: #475569; padding-right: 20px;">
                    <strong>Terms & Conditions:</strong><br>
                    <?= nl2br(htmlspecialchars($order['terms'] ?: "1. Supply as per specifications.\n2. Goods must be delivered within delivery schedule.\n3. Bill must reference this Purchase Order number.")) ?>
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <table style="font-size: 11px;">
                        <tr>
                            <td style="padding: 4px 0; color: #475569;">Subtotal:</td>
                            <td style="padding: 4px 0; text-align: right; font-weight: 600;">₹<?= number_format((float)$order['sub_total'], 2) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #475569;">Total Taxes (GST):</td>
                            <td style="padding: 4px 0; text-align: right; font-weight: 600;">₹<?= number_format((float)$order['total_tax'], 2) ?></td>
                        </tr>
                        <tr style="border-top: 1.5px solid #0f172a; font-weight: 700; font-size: 13px;">
                            <td style="padding: 6px 0;">Total Order Value:</td>
                            <td style="padding: 6px 0; text-align: right; color: #2563eb;">₹<?= number_format((float)$order['grand_total'], 2) ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Signature -->
        <table style="margin-top: 40px;">
            <tr>
                <td style="width: 50%; text-align: left; font-size: 10px;">
                    <div>Prepared By: <strong>Admin / Purchase Dept</strong></div>
                </td>
                <td style="width: 50%; text-align: right; font-size: 10px;">
                    <div style="display: inline-block; min-width: 180px; text-align: center;">
                        <div style="min-height: 45px; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 4px;">
                            <?php if ($showPoStamp && !empty($poStampUrl)): ?>
                                <img src="<?= htmlspecialchars($poStampUrl) ?>" alt="Stamp" style="max-height: <?= (int)$branding['stamp_height'] ?>px; max-width: <?= (int)$branding['stamp_width'] ?>px; object-fit: contain;">
                            <?php endif; ?>
                            <?php if ($showPoSignature && !empty($poSigUrl)): ?>
                                <img src="<?= htmlspecialchars($poSigUrl) ?>" alt="Signature" style="max-height: <?= (int)$branding['signature_height'] ?>px; max-width: <?= (int)$branding['signature_width'] ?>px; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px;">
                            <strong>Authorized Signatory</strong><br>
                            <?= htmlspecialchars($company['name'] ?? 'WTSBill Enterprise') ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

