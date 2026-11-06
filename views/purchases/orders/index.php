<?php
$title = $title ?? 'Purchase Orders (PO)';
$orders = $orders ?? [];
$suppliers = $suppliers ?? [];
$currentStatus = $currentStatus ?? 'ALL';
$counts = $counts ?? [];

$statusColors = [
    'PENDING' => ['bg' => '#fef3c7', 'text' => '#b45309'],
    'APPROVED' => ['bg' => '#e0e7ff', 'text' => '#4338ca'],
    'PARTIAL' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
    'RECEIVED' => ['bg' => '#ecfdf5', 'text' => '#059669'],
    'CANCELLED' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/purchases/bills" style="color: #64748b; text-decoration: none;">Purchases</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Purchase Orders</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Purchase Orders & Procurement</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Procurement workflow: PO &rarr; Goods Receipt (GRN) &rarr; Inward Vendor Bill &rarr; Payment.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/purchases/debit-notes" class="btn" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Debit Notes
            </a>
            <a href="/purchases/orders/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #2563eb; color: #fff; text-decoration: none; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                + Create Purchase Order
            </a>
        </div>
    </div>

    <!-- Status Pills Bar -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px;">
        <?php 
        $statuses = ['ALL', 'PENDING', 'APPROVED', 'PARTIAL', 'RECEIVED', 'CANCELLED'];
        foreach ($statuses as $st): 
            $isActive = ($currentStatus === $st);
            $cnt = $counts[$st] ?? 0;
        ?>
            <a href="/purchases/orders?status=<?= $st ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 9999px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $isActive ? 'background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2);' : 'background: #fff; color: #475569; border: 1px solid #e2e8f0;' ?>">
                <span><?= $st === 'ALL' ? 'All Orders' : ucfirst(strtolower($st)) ?></span>
                <span style="font-size: 11px; padding: 1px 7px; border-radius: 9999px; <?= $isActive ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                    <?= $cnt ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- PO List Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Purchase Order Register (<?= count($orders) ?>)</h2>
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatus) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search PO # or supplier..." style="padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                <button type="submit" style="padding: 7px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;">Search</button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">PO #</th>
                        <th style="padding: 14px 20px;">Date</th>
                        <th style="padding: 14px 20px;">Supplier / Vendor</th>
                        <th style="padding: 14px 20px; text-align: right;">Total Items</th>
                        <th style="padding: 14px 20px; text-align: right;">Ordered / Received</th>
                        <th style="padding: 14px 20px; text-align: right;">Grand Total</th>
                        <th style="padding: 14px 20px; text-align: center;">Status</th>
                        <th style="padding: 14px 20px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
                                No Purchase Orders found. Click <strong>+ Create Purchase Order</strong> to raise an order.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $po): 
                            $st = $po['status'];
                            $colors = $statusColors[$st] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                <td style="padding: 14px 20px;">
                                    <a href="/purchases/orders/<?= (int)$po['id'] ?>" style="font-weight: 600; color: #2563eb; text-decoration: none; font-family: monospace;">
                                        <?= htmlspecialchars($po['po_number']) ?>
                                    </a>
                                    <?php if (!empty($po['reference_no'])): ?>
                                        <div style="font-size: 12px; color: #64748b;">Ref: <?= htmlspecialchars($po['reference_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 20px; color: #334155;">
                                    <?= date('d M Y', strtotime($po['po_date'])) ?>
                                </td>
                                <td style="padding: 14px 20px;">
                                    <a href="/suppliers/<?= (int)$po['supplier_id'] ?>" style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                        <?= htmlspecialchars($po['supplier_name'] ?? 'Supplier') ?>
                                    </a>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($po['branch_name'] ?? 'Main Branch') ?></div>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                    <?= (int)$po['item_count'] ?> item(s)
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 500;">
                                    <span style="color: #059669;"><?= number_format((float)$po['total_received'], 0) ?></span> / 
                                    <span style="color: #1e293b;"><?= number_format((float)$po['total_ordered'], 0) ?></span>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                                    ₹<?= number_format((float)$po['grand_total'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 4px 12px; font-size: 12px; font-weight: 700; border-radius: 9999px; background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 20px; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="/purchases/orders/<?= (int)$po['id'] ?>" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #f1f5f9; color: #334155; text-decoration: none; border: 1px solid #cbd5e1;">
                                            Manage
                                        </a>
                                        <a href="/purchases/orders/<?= (int)$po['id'] ?>/print" target="_blank" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #fff; color: #475569; text-decoration: none; border: 1px solid #cbd5e1;">
                                            Print
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

