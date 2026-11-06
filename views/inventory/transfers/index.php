<?php
$title = $title ?? 'Stock Transfers & Logistics';
$transfers = $transfers ?? [];
$warehouses = $warehouses ?? [];
$currentStatus = $currentStatus ?? 'ALL';
$counts = $counts ?? [];

$statusColors = [
    'PENDING' => ['bg' => '#fef3c7', 'text' => '#b45309'],
    'APPROVED' => ['bg' => '#e0e7ff', 'text' => '#4338ca'],
    'DISPATCHED' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
    'RECEIVED' => ['bg' => '#ecfdf5', 'text' => '#059669'],
    'CANCELLED' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/inventory/stock" style="color: #64748b; text-decoration: none;">Inventory</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Stock Transfers</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Inter-Warehouse & Inter-Branch Transfers</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Multi-step logistics workflow: Pending &rarr; Approved &rarr; Dispatched &rarr; Received in destination stock.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/inventory/warehouses" class="btn" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Warehouses
            </a>
            <a href="/inventory/transfers/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #2563eb; color: #fff; text-decoration: none; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                + New Stock Transfer
            </a>
        </div>
    </div>

    <!-- Status Pills Navigation Bar -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px;">
        <?php 
        $statuses = ['ALL', 'PENDING', 'APPROVED', 'DISPATCHED', 'RECEIVED', 'CANCELLED'];
        foreach ($statuses as $st): 
            $isActive = ($currentStatus === $st);
            $cnt = $counts[$st] ?? 0;
        ?>
            <a href="/inventory/transfers?status=<?= $st ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 9999px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; <?= $isActive ? 'background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2);' : 'background: #fff; color: #475569; border: 1px solid #e2e8f0;' ?>">
                <span><?= $st === 'ALL' ? 'All Transfers' : ucfirst(strtolower($st)) ?></span>
                <span style="font-size: 11px; padding: 1px 7px; border-radius: 9999px; <?= $isActive ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                    <?= $cnt ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Transfer Listings Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Transfer Orders (<?= count($transfers) ?>)</h2>
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatus) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search transfer # or ref..." style="padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                <button type="submit" style="padding: 7px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;">Filter</button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Transfer Order #</th>
                        <th style="padding: 14px 20px;">Transfer Date</th>
                        <th style="padding: 14px 20px;">Route (From &rarr; To)</th>
                        <th style="padding: 14px 20px; text-align: right;">Line Items</th>
                        <th style="padding: 14px 20px; text-align: right;">Total Qty</th>
                        <th style="padding: 14px 20px; text-align: center;">Logistics Status</th>
                        <th style="padding: 14px 20px;">Created By</th>
                        <th style="padding: 14px 20px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($transfers)): ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
                                No stock transfer orders found matching this status. Click <strong>+ New Stock Transfer</strong> to create a dispatch.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $tr): 
                            $st = $tr['status'];
                            $colors = $statusColors[$st] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                <td style="padding: 14px 20px;">
                                    <a href="/inventory/transfers/<?= (int)$tr['id'] ?>" style="font-weight: 600; color: #2563eb; text-decoration: none; font-family: monospace; font-size: 14px;">
                                        <?= htmlspecialchars($tr['transfer_number']) ?>
                                    </a>
                                    <?php if (!empty($tr['reference_no'])): ?>
                                        <div style="font-size: 12px; color: #64748b;">Ref: <?= htmlspecialchars($tr['reference_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 20px; color: #334155;">
                                    <?= date('d M Y', strtotime($tr['transfer_date'])) ?>
                                </td>
                                <td style="padding: 14px 20px;">
                                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 500; color: #1e293b;">
                                        <span><?= htmlspecialchars($tr['from_warehouse_name'] ?? 'Warehouse') ?></span>
                                        <svg width="14" height="14" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        <span style="color: #2563eb;"><?= htmlspecialchars($tr['to_warehouse_name'] ?? 'Warehouse') ?></span>
                                    </div>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                    <?= (int)$tr['total_items'] ?> item(s)
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #0f172a;">
                                    <?= number_format((float)$tr['total_quantity'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 4px 12px; font-size: 12px; font-weight: 700; border-radius: 9999px; background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 20px; font-size: 13px; color: #64748b;">
                                    <?= htmlspecialchars($tr['created_by'] ?? 'Admin') ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="/inventory/transfers/<?= (int)$tr['id'] ?>" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #f1f5f9; color: #334155; text-decoration: none; border: 1px solid #cbd5e1;">
                                            Manage
                                        </a>
                                        <a href="/inventory/transfers/<?= (int)$tr['id'] ?>/print" target="_blank" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #fff; color: #475569; text-decoration: none; border: 1px solid #cbd5e1;">
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

