<?php
$title = $title ?? 'Purchase Order Details';
$order = $order ?? [];
$items = $order['items'] ?? [];
$warehouses = $warehouses ?? [];
$st = $order['status'];

$statusColors = [
    'PENDING' => ['bg' => '#fef3c7', 'text' => '#b45309'],
    'APPROVED' => ['bg' => '#e0e7ff', 'text' => '#4338ca'],
    'PARTIAL' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
    'RECEIVED' => ['bg' => '#ecfdf5', 'text' => '#059669'],
    'CANCELLED' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
];
$colors = $statusColors[$st] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/purchases/orders" style="color: #64748b; text-decoration: none;">Purchase Orders</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;"><?= htmlspecialchars($order['po_number']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span><?= htmlspecialchars($order['po_number']) ?></span>
                <span style="font-size: 13px; font-weight: 700; padding: 4px 14px; border-radius: 9999px; background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                    <?= htmlspecialchars($st) ?>
                </span>
            </h1>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/purchases/orders/<?= (int)$order['id'] ?>/print" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print PO
            </a>

            <?php if ($st === 'PENDING'): ?>
                <form action="/purchases/orders/<?= (int)$order['id'] ?>/approve" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #4f46e5; color: #fff; border: none; cursor: pointer;">
                        Approve PO
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array($st, ['APPROVED', 'PARTIAL'], true)): ?>
                <button type="button" onclick="openGrnModal()" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #0284c7; color: #fff; border: none; cursor: pointer;">
                    Receive Goods (GRN)
                </button>

                <form action="/purchases/orders/<?= (int)$order['id'] ?>/convert-to-bill" method="POST" style="margin: 0;" onsubmit="return confirm('Convert this PO into an official Inward Purchase Bill?');">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #059669; color: #fff; border: none; cursor: pointer;">
                        Convert to Purchase Bill
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metadata Cards -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
        <!-- Supplier & Terms Info -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Supplier Details</div>
            <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 4px;">
                <a href="/suppliers/<?= (int)$order['supplier_id'] ?>" style="color: #2563eb; text-decoration: none;">
                    <?= htmlspecialchars($order['supplier_name']) ?>
                </a>
            </div>
            <div style="font-size: 13px; color: #475569; margin-top: 4px; line-height: 1.5;">
                GSTIN: <strong><?= htmlspecialchars($order['supplier_gstin'] ?: 'Unregistered') ?></strong><br>
                Phone: <?= htmlspecialchars($order['supplier_phone'] ?: '—') ?> | Email: <?= htmlspecialchars($order['supplier_email'] ?: '—') ?><br>
                Address: <?= htmlspecialchars($order['supplier_address'] ?: '—') ?>
            </div>
        </div>

        <!-- Order Summary & Delivery -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Order Schedule</div>
            <div style="font-size: 13px; color: #334155; margin-top: 8px; line-height: 1.8;">
                <div>PO Date: <strong><?= date('d M Y', strtotime($order['po_date'])) ?></strong></div>
                <div>Expected: <strong><?= $order['expected_delivery'] ? date('d M Y', strtotime($order['expected_delivery'])) : 'Immediate' ?></strong></div>
                <div>Branch: <strong><?= htmlspecialchars($order['branch_name'] ?? 'Main Branch') ?></strong></div>
                <div>Grand Total: <strong style="font-size: 16px; color: #059669;">₹<?= number_format((float)$order['grand_total'], 2) ?></strong></div>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Ordered Items &amp; Fulfillment Progress</h2>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">#</th>
                        <th style="padding: 14px 20px;">Product Description</th>
                        <th style="padding: 14px 20px;">SKU / HSN</th>
                        <th style="padding: 14px 20px; text-align: right;">Ordered Qty</th>
                        <th style="padding: 14px 20px; text-align: right;">Received Qty</th>
                        <th style="padding: 14px 20px; text-align: right;">Pending Qty</th>
                        <th style="padding: 14px 20px; text-align: right;">Unit Price (₹)</th>
                        <th style="padding: 14px 20px; text-align: right;">Tax (₹)</th>
                        <th style="padding: 14px 20px; text-align: right;">Total Amount (₹)</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php 
                    $idx = 1; 
                    foreach ($items as $item): 
                        $ord = (float)$item['quantity'];
                        $rec = (float)$item['received_quantity'];
                        $pend = max(0, $ord - $rec);
                        $tax = (float)($item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount']);
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 20px; color: #94a3b8;"><?= $idx++ ?></td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #1e293b;">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </td>
                            <td style="padding: 14px 20px; color: #64748b; font-family: monospace; font-size: 13px;">
                                <?= htmlspecialchars($item['sku'] ?: ($item['hsn_sac'] ?? '—')) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #0f172a;">
                                <?= number_format($ord, 2) ?> <?= htmlspecialchars($item['unit'] ?? '') ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #059669;">
                                <?= number_format($rec, 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: <?= $pend > 0 ? '#d97706' : '#64748b' ?>;">
                                <?= number_format($pend, 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                ₹<?= number_format((float)$item['unit_price'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; color: #64748b;">
                                ₹<?= number_format($tax, 2) ?> (<?= (float)$item['gst_rate'] ?>%)
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                                ₹<?= number_format((float)$item['total_amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Goods Receipt Note (GRN) -->
<div id="grnModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; width: 100%; max-width: 640px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; margin: 16px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Goods Receipt Note (GRN)</h3>
            <button type="button" onclick="closeGrnModal()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form action="/purchases/orders/<?= (int)$order['id'] ?>/receive-goods" method="POST" style="padding: 24px;">
            <?= csrf_field() ?>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Receiving Warehouse <span style="color: #ef4444;">*</span></label>
                <select name="warehouse_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 10px;">Enter Received Quantities:</label>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                            <th style="padding: 8px 10px;">Product</th>
                            <th style="padding: 8px 10px; text-align: right;">Ordered</th>
                            <th style="padding: 8px 10px; text-align: right;">Already Rcvd</th>
                            <th style="padding: 8px 10px; width: 140px; text-align: right;">Receive Now</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $ord = (float)$item['quantity'];
                            $rec = (float)$item['received_quantity'];
                            $pending = max(0, $ord - $rec);
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 8px 10px; font-weight: 600;"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td style="padding: 8px 10px; text-align: right;"><?= number_format($ord, 2) ?></td>
                                <td style="padding: 8px 10px; text-align: right; color: #059669;"><?= number_format($rec, 2) ?></td>
                                <td style="padding: 8px 10px; text-align: right;">
                                    <input type="number" name="receipts[<?= (int)$item['id'] ?>]" value="<?= $pending ?>" min="0" max="<?= $pending ?>" step="0.01" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; text-align: right; box-sizing: border-box;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeGrnModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #0284c7; color: #fff; font-weight: 600; cursor: pointer;">Confirm GRN & Update Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGrnModal() {
    document.getElementById('grnModal').style.display = 'flex';
}
function closeGrnModal() {
    document.getElementById('grnModal').style.display = 'none';
}
</script>
