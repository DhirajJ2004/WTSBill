<?php
$title = $title ?? 'Customer Advances';
$advances = $advances ?? [];
$customers = $customers ?? [];
$counts = $counts ?? ['ALL' => 0, 'ACTIVE' => 0, 'EXHAUSTED' => 0];
$selectedStatus = $selectedStatus ?? 'ALL';

$totalAdvancePool = 0.0;
$totalRemainingPool = 0.0;
foreach ($advances as $adv) {
    $totalAdvancePool += (float)$adv['total_amount'];
    $totalRemainingPool += (float)$adv['remaining_amount'];
}
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Customer Advances</h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Manage unallocated customer deposits, advance vouchers, and invoice drawdown balances.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/reports/receivables-aging" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Receivables Aging
            </a>
            <a href="/payments/advances/create" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #2563eb; color: #fff; text-decoration: none; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Record Advance
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Advances Recorded</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 8px;">₹<?= number_format($totalAdvancePool, 2) ?></div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;"><?= count($advances) ?> total advance vouchers</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #16a34a; text-transform: uppercase;">Available Advance Balance</div>
            <div style="font-size: 24px; font-weight: 700; color: #16a34a; margin-top: 8px;">₹<?= number_format($totalRemainingPool, 2) ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;">Ready for invoice drawdown</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #0284c7; text-transform: uppercase;">Active Advances</div>
            <div style="font-size: 24px; font-weight: 700; color: #0284c7; margin-top: 8px;"><?= (int)($counts['ACTIVE'] ?? 0) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Holding unallocated balance</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Exhausted Advances</div>
            <div style="font-size: 24px; font-weight: 700; color: #64748b; margin-top: 8px;"><?= (int)($counts['EXHAUSTED'] ?? 0) ?></div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">100% applied to invoices</div>
        </div>
    </div>

    <!-- Status Pills & Search Filter -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php
            $tabs = [
                'ALL' => 'All Advances (' . ($counts['ALL'] ?? 0) . ')',
                'ACTIVE' => 'Active / Unapplied (' . ($counts['ACTIVE'] ?? 0) . ')',
                'EXHAUSTED' => 'Fully Exhausted (' . ($counts['EXHAUSTED'] ?? 0) . ')',
            ];
            foreach ($tabs as $k => $label):
                $active = ($selectedStatus === $k);
            ?>
                <a href="/payments/advances?status=<?= $k ?>" style="padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $active ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="/payments/advances" style="display: flex; gap: 8px; margin: 0;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($selectedStatus) ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search customer, voucher, note..." style="padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; width: 240px;">
            <button type="submit" style="padding: 8px 16px; border-radius: 8px; background: #334155; color: #fff; font-size: 13px; font-weight: 500; border: none; cursor: pointer;">
                Filter
            </button>
        </form>
    </div>

    <!-- Advances Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                    <th style="padding: 14px 20px;">Advance Date</th>
                    <th style="padding: 14px 20px;">Voucher No</th>
                    <th style="padding: 14px 20px;">Customer</th>
                    <th style="padding: 14px 20px;">Mode / Ref</th>
                    <th style="padding: 14px 20px; text-align: right;">Total Amount (₹)</th>
                    <th style="padding: 14px 20px; text-align: right;">Allocated (₹)</th>
                    <th style="padding: 14px 20px; text-align: right;">Remaining (₹)</th>
                    <th style="padding: 14px 20px; text-align: center;">Status</th>
                    <th style="padding: 14px 20px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody style="divide-y: 1px solid #f1f5f9;">
                <?php if (empty($advances)): ?>
                    <tr>
                        <td colspan="9" style="padding: 48px; text-align: center; color: #94a3b8;">
                            No customer advance payments found for the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($advances as $adv): 
                        $status = $adv['status'];
                        $isAct = ($status === 'ACTIVE');
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 20px; color: #1e293b; font-weight: 500;">
                                <?= date('d M Y', strtotime($adv['advance_date'])) ?>
                            </td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #2563eb;">
                                <a href="/payments/advances/<?= (int)$adv['id'] ?>" style="color: #2563eb; text-decoration: none;">
                                    <?= htmlspecialchars($adv['payment_number'] ?? 'ADV-' . $adv['id']) ?>
                                </a>
                            </td>
                            <td style="padding: 14px 20px;">
                                <a href="/customers/<?= (int)$adv['customer_id'] ?>" style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                    <?= htmlspecialchars($adv['customer_name']) ?>
                                </a>
                                <?php if ($adv['customer_phone']): ?>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($adv['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 20px; font-size: 13px; color: #475569;">
                                <span style="font-weight: 500;"><?= htmlspecialchars($adv['payment_mode'] ?: 'Direct') ?></span>
                                <?php if (!empty($adv['reference_no'])): ?>
                                    <div style="font-size: 11px; color: #94a3b8;">Ref: <?= htmlspecialchars($adv['reference_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                                ₹<?= number_format((float)$adv['total_amount'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; color: #64748b;">
                                ₹<?= number_format((float)$adv['allocated_amount'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: <?= (float)$adv['remaining_amount'] > 0 ? '#16a34a' : '#94a3b8' ?>;">
                                ₹<?= number_format((float)$adv['remaining_amount'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <span style="font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; <?= $isAct ? 'background: #dcfce7; color: #15803d;' : 'background: #f1f5f9; color: #64748b;' ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td style="padding: 14px 20px; text-align: right;">
                                <a href="/payments/advances/<?= (int)$adv['id'] ?>" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; <?= $isAct ? 'background: #eff6ff; color: #2563eb;' : 'background: #f8fafc; color: #475569; border: 1px solid #e2e8f0;' ?>">
                                    <?= $isAct ? 'Drawdown / Details' : 'View History' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

