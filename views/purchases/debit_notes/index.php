<?php
$title = $title ?? 'Debit Notes & Purchase Returns';
$debitNotes = $debitNotes ?? [];
$suppliers = $suppliers ?? [];

$totalAmount = 0.0;
foreach ($debitNotes as $dn) {
    $totalAmount += (float)($dn['amount'] ?? 0);
}
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/purchases/bills" style="color: #64748b; text-decoration: none;">Purchases</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Debit Notes</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Debit Notes & Purchase Returns</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Manage purchase return adjustments, damaged goods reversals, and supplier credit deductions.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/purchases/orders" class="btn" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff;">
                Purchase Orders
            </a>
            <a href="/purchases/debit-notes/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #2563eb; color: #fff; text-decoration: none; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                + Issue Debit Note
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Debit Notes</div>
            <div style="font-size: 28px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?= count($debitNotes) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Issued to suppliers</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; border-left: 4px solid #ef4444;">
            <div style="font-size: 12px; font-weight: 600; color: #dc2626; text-transform: uppercase;">Total Return Value</div>
            <div style="font-size: 28px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format($totalAmount, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Supplier liability reduction</div>
        </div>
    </div>

    <!-- Debit Notes List -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Debit Note Register</h2>
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search DN # or supplier..." style="padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                <button type="submit" style="padding: 7px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; cursor: pointer;">Search</button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Debit Note #</th>
                        <th style="padding: 14px 20px;">Date</th>
                        <th style="padding: 14px 20px;">Supplier</th>
                        <th style="padding: 14px 20px;">Original Bill #</th>
                        <th style="padding: 14px 20px;">Reason</th>
                        <th style="padding: 14px 20px; text-align: right;">Amount (₹)</th>
                        <th style="padding: 14px 20px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($debitNotes)): ?>
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #94a3b8;">
                                No debit notes recorded. Click <strong>+ Issue Debit Note</strong> to record a purchase return.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($debitNotes as $dn): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px; font-weight: 600; color: #dc2626; font-family: monospace;">
                                    <?= htmlspecialchars($dn['debit_note_number']) ?>
                                </td>
                                <td style="padding: 14px 20px; color: #334155;">
                                    <?= date('d M Y', strtotime($dn['debit_note_date'])) ?>
                                </td>
                                <td style="padding: 14px 20px;">
                                    <a href="/suppliers/<?= (int)$dn['supplier_id'] ?>" style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                        <?= htmlspecialchars($dn['supplier_name'] ?? 'Supplier') ?>
                                    </a>
                                </td>
                                <td style="padding: 14px 20px; color: #64748b; font-family: monospace;">
                                    <?= htmlspecialchars($dn['original_purchase_number'] ?: '—') ?>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;">
                                    <?= htmlspecialchars($dn['reason'] ?? 'Goods Damaged') ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #dc2626;">
                                    ₹<?= number_format((float)$dn['amount'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #ecfdf5; color: #059669;">
                                        <?= htmlspecialchars($dn['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

