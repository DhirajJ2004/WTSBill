<?php
$title = $title ?? 'Transfer Details';
$transfer = $transfer ?? [];
$items = $transfer['items'] ?? [];
$st = $transfer['status'];

$statusColors = [
    'PENDING' => ['bg' => '#fef3c7', 'text' => '#b45309'],
    'APPROVED' => ['bg' => '#e0e7ff', 'text' => '#4338ca'],
    'DISPATCHED' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
    'RECEIVED' => ['bg' => '#ecfdf5', 'text' => '#059669'],
    'CANCELLED' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
];
$colors = $statusColors[$st] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Breadcrumb & Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/inventory/transfers" style="color: #64748b; text-decoration: none;">Stock Transfers</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;"><?= htmlspecialchars($transfer['transfer_number']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span><?= htmlspecialchars($transfer['transfer_number']) ?></span>
                <span style="font-size: 13px; font-weight: 700; padding: 4px 14px; border-radius: 9999px; background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                    <?= htmlspecialchars($st) ?>
                </span>
            </h1>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/inventory/transfers/<?= (int)$transfer['id'] ?>/print" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Gate Pass
            </a>

            <?php if ($st === 'PENDING'): ?>
                <form action="/inventory/transfers/<?= (int)$transfer['id'] ?>/approve" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #4f46e5; color: #fff; border: none; cursor: pointer;">
                        Approve Transfer
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array($st, ['PENDING', 'APPROVED'], true)): ?>
                <form action="/inventory/transfers/<?= (int)$transfer['id'] ?>/dispatch" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #0284c7; color: #fff; border: none; cursor: pointer;" onclick="return confirm('Deduct stock from source warehouse and mark goods in transit?');">
                        Dispatch Goods
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($st === 'DISPATCHED'): ?>
                <form action="/inventory/transfers/<?= (int)$transfer['id'] ?>/receive" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #059669; color: #fff; border: none; cursor: pointer;" onclick="return confirm('Confirm receipt and add all items to destination warehouse stock?');">
                        Confirm & Receive Stock
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array($st, ['PENDING', 'APPROVED', 'DISPATCHED'], true)): ?>
                <form action="/inventory/transfers/<?= (int)$transfer['id'] ?>/cancel" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to cancel this transfer?');">
                    <?= csrf_field() ?>
                    <button type="submit" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; cursor: pointer;">
                        Cancel Transfer
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Route Overview Cards -->
    <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: center; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Source Facility (From)</div>
            <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 4px;">
                <?= htmlspecialchars($transfer['from_warehouse_name'] ?? 'Warehouse') ?>
            </div>
            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">
                Branch: <?= htmlspecialchars($transfer['from_branch_name'] ?? '—') ?> | City: <?= htmlspecialchars($transfer['from_city'] ?? '—') ?>
            </div>
        </div>

        <div style="text-align: center; color: #2563eb; font-size: 24px;">
            &rarr;
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Destination Facility (To)</div>
            <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 4px;">
                <?= htmlspecialchars($transfer['to_warehouse_name'] ?? 'Warehouse') ?>
            </div>
            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">
                Branch: <?= htmlspecialchars($transfer['to_branch_name'] ?? '—') ?> | City: <?= htmlspecialchars($transfer['to_city'] ?? '—') ?>
            </div>
        </div>
    </div>

    <!-- Logistics Audit & Metadata Card -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
            <div>
                <div style="font-size: 12px; color: #64748b;">Transfer Date</div>
                <div style="font-weight: 600; color: #1e293b;"><?= date('d M Y', strtotime($transfer['transfer_date'])) ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Reference / Gate Pass</div>
                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($transfer['reference_no'] ?: '—') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Created By</div>
                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($transfer['created_by'] ?: 'Admin') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Approved By</div>
                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($transfer['approved_by'] ?: '—') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Dispatched By</div>
                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($transfer['dispatched_by'] ?: '—') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Received By</div>
                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($transfer['received_by'] ?: '—') ?></div>
            </div>
        </div>
        <?php if (!empty($transfer['notes'])): ?>
            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #475569;">
                <strong>Notes:</strong> <?= nl2br(htmlspecialchars($transfer['notes'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Line Items Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Dispatched Items & Stock Counts</h2>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                    <th style="padding: 14px 20px;">#</th>
                    <th style="padding: 14px 20px;">Product Description</th>
                    <th style="padding: 14px 20px;">SKU</th>
                    <th style="padding: 14px 20px; text-align: right;">Quantity Sent</th>
                    <th style="padding: 14px 20px; text-align: right;">Quantity Received</th>
                    <th style="padding: 14px 20px;">Unit</th>
                    <th style="padding: 14px 20px;">Item Notes</th>
                </tr>
            </thead>
            <tbody style="divide-y: 1px solid #f1f5f9;">
                <?php $idx = 1; foreach ($items as $item): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 14px 20px; color: #94a3b8;"><?= $idx++ ?></td>
                        <td style="padding: 14px 20px; font-weight: 600; color: #1e293b;">
                            <?= htmlspecialchars($item['product_name']) ?>
                        </td>
                        <td style="padding: 14px 20px; color: #64748b; font-family: monospace;">
                            <?= htmlspecialchars($item['sku'] ?? '—') ?>
                        </td>
                        <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #0284c7;">
                            <?= number_format((float)$item['quantity_sent'], 2) ?>
                        </td>
                        <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #059669;">
                            <?= number_format((float)$item['quantity_received'], 2) ?>
                        </td>
                        <td style="padding: 14px 20px; color: #475569;">
                            <?= htmlspecialchars($item['unit'] ?? 'PCS') ?>
                        </td>
                        <td style="padding: 14px 20px; color: #64748b; font-size: 13px;">
                            <?= htmlspecialchars($item['notes'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

