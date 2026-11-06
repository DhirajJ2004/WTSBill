<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Order — <?= e($order['order_number']) ?></title>
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
        .order-container {
            border: 1.5px solid #0f172a;
            padding: 15px;
        }
        .header-table, .meta-table, .items-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
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
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer-note {
            margin-top: 20px;
            font-size: 9.5px;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
        }
        .sign-table {
            width: 100%;
            margin-top: 30px;
        }
        .sign-table td {
            width: 50%;
            vertical-align: bottom;
            font-size: 10.5px;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 12px; text-align: right;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #0284c7; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Print Order</button>
        <button onclick="window.close()" style="padding: 6px 12px; background: #64748b; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 6px;">Close</button>
    </div>

    <div class="order-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="company-name"><?= e($company['name'] ?? 'WTSBill Enterprise') ?></div>
                    <div style="font-size: 10px; color: #475569; margin-top: 3px; line-height: 1.4;">
                        <?= e($branch['address'] ?? ($company['address'] ?? '')) ?><br>
                        <?= e($branch['city'] ?? ($company['city'] ?? '')) ?>, <?= e($branch['state'] ?? ($company['state'] ?? '')) ?> - <?= e($branch['pincode'] ?? ($company['pincode'] ?? '')) ?><br>
                        <strong>GSTIN:</strong> <?= e($branch['gstin'] ?? ($company['gstin'] ?? '—')) ?> &bull; <strong>Phone:</strong> <?= e($branch['phone'] ?? ($company['phone'] ?? '—')) ?>
                    </div>
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <div class="header-title">SALES ORDER</div>
                    <div style="text-align: right; font-size: 11px; margin-top: 4px;">
                        <strong>Order Number:</strong> <?= e($order['order_number']) ?><br>
                        <strong>Order Date:</strong> <?= e(format_date($order['order_date'])) ?><br>
                        <strong>Exp. Delivery:</strong> <?= !empty($order['expected_delivery']) ? e(format_date($order['expected_delivery'])) : '—' ?><br>
                        <strong>Ref / PO #:</strong> <?= e($order['reference_no'] ?: '—') ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Buyer Meta Grid -->
        <table class="meta-table" style="margin-top: 12px;">
            <tr>
                <td class="meta-box" style="width: 50%;">
                    <h4>Customer / Buyer Details</h4>
                    <strong><?= e($order['customer_name']) ?></strong><br>
                    <?php if (!empty($order['customer_company'])): ?>
                        <?= e($order['customer_company']) ?><br>
                    <?php endif; ?>
                    <?= e($order['customer_address']) ?><br>
                    <?= e($order['customer_city']) ?>, <?= e($order['customer_state']) ?> - <?= e($order['customer_pincode']) ?><br>
                    <strong>GSTIN:</strong> <?= e($order['customer_gstin'] ?: 'UNREGISTERED') ?><br>
                    <strong>Phone:</strong> <?= e($order['customer_phone'] ?: '—') ?>
                </td>
                <td class="meta-box" style="width: 50%;">
                    <h4>Commercial Order Status</h4>
                    <strong>Status:</strong> <?= e($order['status']) ?><br>
                    <strong>Fulfillment:</strong> <?= e($order['fulfillment_status'] ?: 'UNFULFILLED') ?><br>
                    <strong>Operating Branch:</strong> <?= e($order['branch_name'] ?: 'Main Branch') ?><br>
                    <?php if (!empty($order['notes'])): ?>
                        <div style="margin-top: 4px; font-size: 9.5px; color: #475569;">
                            <strong>Instructions:</strong> <?= e($order['notes']) ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Item Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 42%;">Description of Goods</th>
                    <th style="width: 12%;">HSN/SAC</th>
                    <th style="width: 10%;" class="text-right">Qty</th>
                    <th style="width: 14%;" class="text-right">Rate (₹)</th>
                    <th style="width: 8%;" class="text-right">GST %</th>
                    <th style="width: 14%;" class="text-right">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $idx => $it): ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td>
                            <strong><?= e($it['item_name']) ?></strong>
                        </td>
                        <td><?= e($it['hsn_sac'] ?: '—') ?></td>
                        <td class="text-right"><?= (float)$it['quantity'] ?> <?= e($it['unit'] ?: 'Pcs') ?></td>
                        <td class="text-right"><?= number_format((float)$it['unit_price'], 2) ?></td>
                        <td class="text-right"><?= (float)$it['gst_rate'] ?>%</td>
                        <td class="text-right" style="font-weight: 600;"><?= number_format((float)$it['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Table -->
        <table style="width: 100%; margin-top: 10px;">
            <tr>
                <td style="width: 60%; vertical-align: top; font-size: 10px; color: #64748b;">
                    <strong>Notice:</strong> This document is a commercial Sales Order confirmation. It confirms product specifications, agreed rates, and scheduled delivery windows. Statutory GST tax invoices will be issued upon fulfillment.
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <table class="totals-table" style="font-size: 11px;">
                        <tr>
                            <td style="padding: 3px 6px;">Taxable Subtotal:</td>
                            <td class="text-right" style="padding: 3px 6px; font-weight: 600;">₹ <?= number_format((float)$order['sub_total'], 2) ?></td>
                        </tr>
                        <?php if ((float)$order['discount_amount'] > 0): ?>
                            <tr>
                                <td style="padding: 3px 6px; color: #16a34a;">Discount:</td>
                                <td class="text-right" style="padding: 3px 6px; color: #16a34a;">- ₹ <?= number_format((float)$order['discount_amount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 3px 6px;">Total Tax (GST):</td>
                            <td class="text-right" style="padding: 3px 6px; font-weight: 600;">₹ <?= number_format((float)$order['total_tax'], 2) ?></td>
                        </tr>
                        <tr style="border-top: 1.5px solid #0f172a; border-bottom: 1.5px solid #0f172a; font-size: 13px;">
                            <td style="padding: 6px; font-weight: 800;">ORDER TOTAL:</td>
                            <td class="text-right" style="padding: 6px; font-weight: 800;">₹ <?= number_format((float)$order['grand_total'], 2) ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Signatures -->
        <table class="sign-table">
            <tr>
                <td>
                    Customer Acceptance Signature
                </td>
                <td style="text-align: right;">
                    For <strong><?= e($company['name'] ?? 'WTSBill Enterprise') ?></strong><br><br><br>
                    Authorized Signatory
                </td>
            </tr>
        </table>

        <div class="footer-note">
            Generated on <?= date('d-M-Y H:i') ?> &bull; WTSBill ERP Sales Order Fulfillment Engine
        </div>
    </div>
</body>
</html>

