<?php
/**
 * WTSBill ERP — Delivery Challan Printable Template
 *
 * @var array $challan
 * @var array $items
 * @var array $branding
 * @var array $company
 */

$comp = $company ?? [];
$b = $branding ?? [];
$ch = $challan ?? [];
$items = $items ?? [];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan — <?= e($ch['challan_number'] ?? 'DC') ?></title>
    <link rel="icon" type="image/png" href="<?= e($logoUrl) ?>">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #051F20;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.4;
        }
        .challan-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #CBD5E1;
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
        .challan-title {
            text-align: right;
            vertical-align: top;
        }
        .challan-title h1 {
            margin: 0;
            font-size: 22px;
            color: #235347;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .challan-meta-pill {
            display: inline-block;
            background: #DAF1DE;
            color: #0B2B26;
            font-weight: 700;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 4px;
            margin-top: 6px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
            border-top: 2px solid #DAF1DE;
            border-bottom: 2px solid #DAF1DE;
            padding: 15px 0;
        }
        .info-box h4 {
            margin: 0 0 6px 0;
            font-size: 11px;
            text-transform: uppercase;
            color: #4B5563;
            letter-spacing: 0.04em;
        }
        .info-box strong {
            font-size: 14px;
            color: #051F20;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .items-table th {
            background: #0B2B26;
            color: #ffffff;
            text-align: left;
            padding: 9px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 12px;
        }
        .items-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .signatory-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #CBD5E1;
        }
        .signatory-box {
            text-align: center;
            width: 200px;
        }
        .sig-img {
            max-height: 50px;
            max-width: 150px;
            object-fit: contain;
            margin-bottom: 5px;
        }
        .stamp-img {
            max-height: 60px;
            max-width: 80px;
            object-fit: contain;
        }
        .no-print-bar {
            max-width: 800px;
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
            .challan-container { box-shadow: none; border: none; padding: 0; }
            .no-print-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="/sales/challans" class="btn btn-back">← Back to Delivery Challans</a>
        <button onclick="window.print()" class="btn btn-print">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Delivery Challan
        </button>
    </div>

    <div class="challan-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="vertical-align: top; width: 60%;">
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?= e($logoUrl) ?>" alt="Logo" class="company-logo"><br>
                    <?php endif; ?>
                    <strong style="font-size: 15px; color: #051F20;"><?= e($compName) ?></strong><br>
                    <span style="color: #4B5563; font-size: 11px;">
                        <?= e($compAddress) ?><?= !empty($compCity) ? ', ' . e($compCity) : '' ?>, <?= e($compState) ?> <?= e($compPincode) ?><br>
                        <?php if ($compGstin): ?>GSTIN: <strong><?= e($compGstin) ?></strong> &bull; <?php endif; ?>
                        Phone: <?= e($compPhone ?: '—') ?>
                    </span>
                </td>
                <td class="challan-title">
                    <h1>Delivery Challan</h1>
                    <div class="challan-meta-pill"><?= e($ch['challan_number'] ?? 'DC') ?></div>
                    <div style="margin-top: 6px; font-size: 12px; color: #4B5563;">
                        Date: <strong><?= date('d M Y', strtotime($ch['challan_date'] ?? 'now')) ?></strong><br>
                        <?php if (!empty($ch['invoice_number'])): ?>
                            Linked Invoice: <strong style="color: #235347;"><?= e($ch['invoice_number']) ?></strong><br>
                        <?php endif; ?>
                        Status: <strong style="color: #10B981;"><?= e($ch['status'] ?? 'DELIVERED') ?></strong>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Consignee & Dispatch Information -->
        <div class="info-grid">
            <div class="info-box">
                <h4>Consignee / Deliver To:</h4>
                <strong><?= e($ch['customer_name'] ?? 'Direct Client') ?></strong><br>
                <span style="color: #4B5563; font-size: 12px;">
                    <?= nl2br(e($ch['delivery_address'] ?: ($ch['customer_address'] ?? 'Standard Site Delivery'))) ?><br>
                    <?php if (!empty($ch['customer_gstin'])): ?>GSTIN: <strong><?= e($ch['customer_gstin']) ?></strong><br><?php endif; ?>
                    <?php if (!empty($ch['customer_phone'])): ?>Contact: <?= e($ch['customer_phone']) ?><?php endif; ?>
                </span>
            </div>
            <div class="info-box">
                <h4>Transport &amp; Dispatch Logistics:</h4>
                <span style="font-size: 12px; color: #051F20;">
                    Transport Details: <strong><?= e($ch['transport_details'] ?: 'Road Transport') ?></strong><br>
                    Dispatch Date: <strong><?= date('d M Y', strtotime($ch['challan_date'] ?? 'now')) ?></strong><br>
                    Goods Type: <strong>Finished Commercial Goods</strong><br>
                    Dispatch Notes: <em><?= e($ch['notes'] ?: 'Materials dispatched in good condition') ?></em>
                </span>
            </div>
        </div>

        <!-- Dispatched Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>Item Description &amp; Specifications</th>
                    <th style="width: 100px; text-align: right;">Quantity</th>
                    <th style="width: 70px; text-align: center;">Unit</th>
                    <th style="width: 110px; text-align: right;">Unit Rate</th>
                    <th style="width: 120px; text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php $idx = 1; $totalQty = 0; $grandTotal = 0; ?>
                    <?php foreach ($items as $item): ?>
                        <?php 
                        $qty = (float)($item['quantity'] ?? 0);
                        $rate = (float)($item['unit_price'] ?? 0);
                        $amt = (float)($item['total_amount'] ?? ($qty * $rate));
                        $totalQty += $qty;
                        $grandTotal += $amt;
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748B;"><?= $idx++ ?></td>
                            <td>
                                <strong><?= e($item['item_name']) ?></strong>
                                <?php if (!empty($item['sku'])): ?>
                                    <div style="font-size: 10px; color: #64748B;">SKU: <?= e($item['sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700;"><?= number_format($qty, 2) ?></td>
                            <td style="text-align: center; color: #4B5563;"><?= e($item['unit'] ?: 'Pcs') ?></td>
                            <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
                            <td style="text-align: right; font-weight: 600;">₹<?= number_format($amt, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: #F1F5F9; font-weight: 700;">
                        <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 11px;">Total Dispatched Quantity:</td>
                        <td style="text-align: right; font-size: 13px; color: #235347;"><?= number_format($totalQty, 2) ?></td>
                        <td></td>
                        <td style="text-align: right; text-transform: uppercase; font-size: 11px;">Total Value:</td>
                        <td style="text-align: right; font-size: 13px; color: #235347;">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 1.5rem; color: #64748B;">
                            Standard dispatched materials per Invoice <?= e($ch['invoice_number'] ?? '') ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Terms & Declarations -->
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 10px 14px; border-radius: 6px; font-size: 11px; color: #4B5563; margin-bottom: 20px;">
            <strong>Declaration:</strong> Goods are being dispatched for delivery at site against invoice. Received above goods in good order and condition.
        </div>

        <!-- Signatory Block -->
        <div class="signatory-section">
            <div class="signatory-box">
                <div style="height: 60px; display: flex; align-items: flex-end; justify-content: center;">
                    <span style="border-bottom: 1px solid #CBD5E1; width: 140px; display: block;"></span>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #051F20; margin-top: 6px;">Receiver's Signature &amp; Stamp</div>
            </div>

            <div class="signatory-box">
                <div style="height: 60px; display: flex; align-items: flex-end; justify-content: center; gap: 8px;">
                    <?php if (!empty($stampUrl)): ?>
                        <img src="<?= e($stampUrl) ?>" alt="Stamp" class="stamp-img">
                    <?php endif; ?>
                    <?php if (!empty($sigUrl)): ?>
                        <img src="<?= e($sigUrl) ?>" alt="Signature" class="sig-img">
                    <?php endif; ?>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #051F20; margin-top: 6px;">
                    For <?= e($compName) ?><br>
                    <span style="font-weight: 400; color: #64748B;">Authorized Signatory</span>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

