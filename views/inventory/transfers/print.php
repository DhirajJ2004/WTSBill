<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Transfer Note — <?= htmlspecialchars($transfer['transfer_number']) ?></title>
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
        <button class="btn-print" onclick="window.print()">Print Gate Pass / Transfer Note</button>
    </div>

    <div class="container">
        <!-- Header -->
        <table>
            <tr>
                <td style="vertical-align: top; width: 60%;">
                    <div class="company-name"><?= htmlspecialchars($company['name'] ?? 'WTSBill Enterprise') ?></div>
                    <div style="font-size: 10px; color: #475569; margin-top: 3px;">
                        <?= htmlspecialchars($company['address'] ?? '') ?><br>
                        <?= htmlspecialchars($company['city'] ?? '') ?>, <?= htmlspecialchars($company['state'] ?? '') ?> - <?= htmlspecialchars($company['pincode'] ?? '') ?><br>
                        GSTIN: <?= htmlspecialchars($company['gstin'] ?? 'N/A') ?> | Phone: <?= htmlspecialchars($company['phone'] ?? '') ?>
                    </div>
                </td>
                <td style="vertical-align: top; width: 40%; text-align: right;">
                    <div class="title">STOCK TRANSFER NOTE</div>
                    <div style="font-size: 12px; font-weight: 700; color: #0284c7; margin-top: 4px;">
                        <?= htmlspecialchars($transfer['transfer_number']) ?>
                    </div>
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                        Date: <?= date('d-m-Y', strtotime($transfer['transfer_date'])) ?>
                    </div>
                    <div style="font-size: 10px; color: #64748b;">
                        Status: <strong><?= htmlspecialchars($transfer['status']) ?></strong>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Route Details -->
        <table style="margin-top: 15px;">
            <tr>
                <td class="meta-box" style="width: 50%;">
                    <h4>DISPATCHED FROM (SOURCE)</h4>
                    <strong><?= htmlspecialchars($transfer['from_warehouse_name'] ?? 'Warehouse') ?></strong><br>
                    Facility Code: <?= htmlspecialchars($transfer['from_warehouse_code'] ?? '—') ?><br>
                    Branch: <?= htmlspecialchars($transfer['from_branch_name'] ?? 'Main Branch') ?><br>
                    City: <?= htmlspecialchars($transfer['from_city'] ?? '—') ?>
                </td>
                <td class="meta-box" style="width: 50%;">
                    <h4>DESTINATION (RECEIVE AT)</h4>
                    <strong><?= htmlspecialchars($transfer['to_warehouse_name'] ?? 'Warehouse') ?></strong><br>
                    Facility Code: <?= htmlspecialchars($transfer['to_warehouse_code'] ?? '—') ?><br>
                    Branch: <?= htmlspecialchars($transfer['to_branch_name'] ?? 'Main Branch') ?><br>
                    City: <?= htmlspecialchars($transfer['to_city'] ?? '—') ?>
                </td>
            </tr>
        </table>

        <?php if (!empty($transfer['reference_no']) || !empty($transfer['notes'])): ?>
            <div style="margin-top: 10px; padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; color: #334155;">
                <?php if (!empty($transfer['reference_no'])): ?>
                    <strong>Gate Pass / Vehicle Ref:</strong> <?= htmlspecialchars($transfer['reference_no']) ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <strong>Instructions:</strong> <?= htmlspecialchars($transfer['notes'] ?? 'Stock transfer for branch replenishment') ?>
            </div>
        <?php endif; ?>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 45%; text-align: left;">Product Item Description</th>
                    <th style="width: 15%; text-align: left;">SKU</th>
                    <th style="width: 10%; text-align: center;">HSN/SAC</th>
                    <th style="width: 12%; text-align: right;">Qty Dispatched</th>
                    <th style="width: 13%; text-align: center;">Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalQty = 0; $idx = 1; foreach ($transfer['items'] as $item): 
                    $qty = (float)$item['quantity'];
                    $totalQty += $qty;
                ?>
                    <tr>
                        <td style="text-align: center; color: #64748b;"><?= $idx++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                            <?php if (!empty($item['notes'])): ?>
                                <div style="font-size: 9px; color: #64748b;"><?= htmlspecialchars($item['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: monospace;"><?= htmlspecialchars($item['sku'] ?? '—') ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($item['hsn_sac'] ?? '—') ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($qty, 2) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($item['unit'] ?? 'PCS') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #f8fafc; font-weight: 700;">
                    <td colspan="4" style="text-align: right; padding: 6px 8px;">TOTAL DISPATCHED UNITS:</td>
                    <td style="text-align: right; padding: 6px 8px;"><?= number_format($totalQty, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures & Acknowledgment -->
        <table style="margin-top: 40px;">
            <tr>
                <td style="width: 25%; text-align: center; font-size: 10px;">
                    <div style="border-top: 1px solid #94a3b8; padding-top: 5px; margin: 0 10px;">
                        <strong>Prepared By</strong><br>
                        <?= htmlspecialchars($transfer['created_by'] ?: 'Admin') ?>
                    </div>
                </td>
                <td style="width: 25%; text-align: center; font-size: 10px;">
                    <div style="border-top: 1px solid #94a3b8; padding-top: 5px; margin: 0 10px;">
                        <strong>Approved By</strong><br>
                        <?= htmlspecialchars($transfer['approved_by'] ?: 'Manager') ?>
                    </div>
                </td>
                <td style="width: 25%; text-align: center; font-size: 10px;">
                    <div style="border-top: 1px solid #94a3b8; padding-top: 5px; margin: 0 10px;">
                        <strong>Dispatched / Driver</strong><br>
                        Signature & Stamp
                    </div>
                </td>
                <td style="width: 25%; text-align: center; font-size: 10px;">
                    <div style="border-top: 1px solid #94a3b8; padding-top: 5px; margin: 0 10px;">
                        <strong>Destination Receiver</strong><br>
                        Signature & Date
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

