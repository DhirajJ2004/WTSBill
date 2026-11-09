<?php
$title = $title ?? 'Credit Notes & Sales Returns';
$creditNotes = $creditNotes ?? [];
$customers = $customers ?? [];

$totalCreditAmount = 0.0;
foreach ($creditNotes as $cn) {
    $totalCreditAmount += (float)($cn['total_amount'] ?? $cn['amount'] ?? 0);
}
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <a href="/sales/invoices" style="color: #235347; text-decoration: none;">Sales</a>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">Credit Notes</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">Credit Notes (Sales Returns)</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Manage sales returns, GST rate corrections, item damage reversals, and customer credits.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="/sales/invoices" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #163832; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">
                Tax Invoices
            </a>
            <a href="/sales/credit-notes/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; text-decoration: none; font-size: 0.85rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                <span>+ Create Credit Note</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.15rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #163832; text-transform: uppercase;">Total Credit Notes</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #051F20; margin-top: 0.35rem;"><?= count($creditNotes) ?></div>
            <div style="font-size: 0.72rem; color: #235347; margin-top: 0.25rem;">Issued to customers</div>
        </div>

        <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.15rem; border-left: 4px solid #235347;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #235347; text-transform: uppercase;">Total Return Value</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #051F20; margin-top: 0.35rem;">₹ <?= number_format($totalCreditAmount, 2) ?></div>
            <div style="font-size: 0.72rem; color: #163832; margin-top: 0.25rem;">Customer credit / receivable offset</div>
        </div>
    </div>

    <!-- Credit Notes Table -->
    <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; overflow: hidden;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <h2 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0;">Credit Note Register</h2>
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search CN #, customer or invoice..." style="padding: 0.45rem 0.75rem; border: 1px solid #8EB69B; border-radius: 6px; font-size: 0.82rem; background: #FFFFFF; color: #051F20;">
                <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; background: #235347; color: #FFFFFF; border: none; border-radius: 6px; font-size: 0.82rem; cursor: pointer;">Search</button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                <thead>
                    <tr style="background: #F4FAF6; border-bottom: 1px solid #8EB69B;">
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">CN NUMBER</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">DATE</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">ORIGINAL INVOICE</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">CUSTOMER</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">REASON</th>
                        <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; color: #051F20;">AMOUNT</th>
                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; color: #051F20;">STATUS</th>
                        <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; color: #051F20;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($creditNotes)): ?>
                        <?php foreach ($creditNotes as $cn): ?>
                            <tr style="border-bottom: 1px solid #E8F5EB;">
                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: #235347;">
                                    <?= htmlspecialchars($cn['credit_note_number']) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #163832;">
                                    <?= htmlspecialchars(date('d-m-Y', strtotime($cn['credit_note_date']))) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #051F20; font-weight: 500;">
                                    <?= htmlspecialchars($cn['invoice_number'] ?? 'Direct Adjustment') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #051F20; font-weight: 600;">
                                    <?= htmlspecialchars($cn['customer_name'] ?? 'Customer') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #163832; font-size: 0.8rem;">
                                    <?= htmlspecialchars($cn['reason'] ?? 'Goods Return') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #051F20;">
                                    ₹ <?= number_format((float)($cn['total_amount'] ?? $cn['amount'] ?? 0), 2) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <span class="badge" style="background: #DAF1DE; color: #235347; border: 1px solid #8EB69B; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                                        <?= htmlspecialchars(strtoupper($cn['status'] ?? 'ISSUED')) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                                    <a href="/sales/credit-notes/<?= (int)$cn['id'] ?>/edit" class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; border: 1px solid #8EB69B; background: #FFFFFF; color: #163832; border-radius: 4px; text-decoration: none; margin-right: 4px;">Edit</a>
                                    <a href="/sales/credit-notes/<?= (int)$cn['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; border: 1px solid #8EB69B; background: #DAF1DE; color: #235347; border-radius: 4px; text-decoration: none; font-weight: 600;">Print</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: #163832;">
                                <div style="font-weight: 600; font-size: 1rem; margin-bottom: 0.5rem;">No credit notes recorded yet</div>
                                <p style="font-size: 0.85rem; color: #8EB69B; margin-bottom: 1rem;">Record a sales return or issue a credit note against an existing invoice.</p>
                                <a href="/sales/credit-notes/create" class="btn btn-primary" style="display: inline-block; padding: 0.5rem 1.25rem; background: #235347; color: #FFFFFF; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">+ Create First Credit Note</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

