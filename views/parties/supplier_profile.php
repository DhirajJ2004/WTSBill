<?php
/**
 * WTSBill ERP — Complete 360-Degree Supplier Profile & Vendor Ledger
 *
 * @var array $supplier
 * @var array $stats
 * @var array $bills
 * @var array $outstandingBills
 * @var array $payments
 * @var array $debitNotes
 * @var array $ledger
 * @var array $bankAccounts
 */

$sid = (int)$supplier['id'];
?>

<div class="page-header supplier-profile-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title" style="margin: 0;"><?= e($supplier['name']) ?></h1>
            <span class="badge badge-primary" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= e($supplier['supplier_code']) ?>
            </span>
            <span class="badge badge-<?= $supplier['status'] === 'ACTIVE' ? 'success' : 'secondary' ?>" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= e($supplier['status']) ?>
            </span>
        </div>
        <p class="page-subtitle" style="margin-top: 0.25rem;">
            <?= e($supplier['assigned_branch']) ?> &bull; Registered on <?= e($supplier['created_at'] ? date('d-M-Y', strtotime((string)$supplier['created_at'])) : '—') ?>
        </p>
    </div>
    
    <!-- Profile Actions -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/purchases/bills?supplier_id=<?= $sid ?>" class="btn btn-primary" style="text-decoration: none;">
            + Record Purchase Bill
        </a>
        <a href="/suppliers/<?= $sid ?>/statement/csv" class="btn btn-secondary" style="text-decoration: none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 3px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Statement CSV
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 3px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print
        </button>
    </div>
</div>

<!-- Supplier Master Information Summary Block -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); padding: 0.85rem 1.25rem;">
        <h3 class="card-title" style="font-size: 0.95rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);">
            Supplier Master Details &amp; Compliance Information
        </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Company / Trade Name</small>
                <strong><?= e($supplier['company_name'] ?: $supplier['name']) ?></strong>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">GSTIN</small>
                <code><?= e($supplier['gstin']) ?></code>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">PAN Number</small>
                <code><?= e($supplier['pan'] ?: '—') ?></code>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Primary Phone</small>
                <a href="tel:<?= e($supplier['phone']) ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 600;">
                    <?= e($supplier['phone']) ?>
                </a>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Email Address</small>
                <a href="mailto:<?= e($supplier['email']) ?>" style="color: var(--color-primary); text-decoration: none;">
                    <?= e($supplier['email']) ?>
                </a>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Payment Terms</small>
                <span><?= (int)($supplier['payment_terms_days'] ?? 30) ?> Days</span>
            </div>
            <?php if (!empty($supplier['contact_person'])): ?>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Contact Person</small>
                <span><?= e($supplier['contact_person']) ?></span>
            </div>
            <?php endif; ?>
            <div style="grid-column: span 2;">
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Registered Address</small>
                <span><?= e($supplier['address']) ?></span>
            </div>
            <?php if (!empty($supplier['notes'])): ?>
            <div style="grid-column: span 2;">
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Supplier Notes</small>
                <div style="font-size: 0.85rem; background: var(--bg-surface, #f8fafc); padding: 0.5rem 0.75rem; border-radius: 4px; border: 1px solid var(--border-color, #e2e8f0);"><?= nl2br(e($supplier['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Financial Summary KPI Dashboard Cards -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Total Inward Billed -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-primary, #0ea5e9);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Total Inward Billed</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-heading);">
            <?= e($stats['total_billed_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($bills) ?> Total Inward Bills</div>
    </div>

    <!-- Total Paid -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-success, #10b981);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Total Paid / Disbursed</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-success, #10b981);">
            <?= e($stats['total_paid_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($payments) ?> Disbursements Recorded</div>
    </div>

    <!-- Outstanding Payable -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid <?= (float)$stats['outstanding_balance'] > 0 ? 'var(--color-danger, #ef4444)' : 'var(--color-success, #10b981)' ?>;">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Current Payable Due</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: <?= (float)$stats['outstanding_balance'] > 0 ? 'var(--color-danger, #ef4444)' : 'var(--color-success, #10b981)' ?>;">
            <?= e($stats['outstanding_balance_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($outstandingBills) ?> Unsettled Inward Bills</div>
    </div>

    <!-- Overdue Amount -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-warning, #f59e0b);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Overdue Payable</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: <?= (float)$stats['overdue_amount'] > 0 ? 'var(--color-warning, #f59e0b)' : 'var(--color-muted)' ?>;">
            <?= e($stats['overdue_amount_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">Past Due Date</div>
    </div>
</div>

<!-- Outstanding Bills Alert / Action Section -->
<?php if (!empty($outstandingBills)): ?>
    <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--border-color, #e2e8f0);">
        <div class="card-header" style="background: #fff8eb; border-bottom: 1px solid #fed7aa; padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
                <h3 class="card-title" style="font-size: 0.95rem; margin: 0; color: #9a3412;">
                    Outstanding Inward Purchase Bills (<?= count($outstandingBills) ?>)
                </h3>
            </div>
            <span style="font-size: 0.85rem; font-weight: 700; color: #9a3412;">
                Total Due: <?= e($stats['outstanding_balance_formatted']) ?>
            </span>
        </div>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Vendor Inv #</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Total Amount</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th style="text-align: right;">Balance Due</th>
                        <th style="text-align: center;">Days Overdue</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outstandingBills as $oBill): ?>
                        <tr>
                            <td>
                                <strong><?= e($oBill['purchase_number'] ?: ('BILL-' . $oBill['id'])) ?></strong>
                            </td>
                            <td><code><?= e($oBill['vendor_invoice_number'] ?: '—') ?></code></td>
                            <td><?= e(format_date($oBill['purchase_date'])) ?></td>
                            <td><?= e(format_date($oBill['due_date'] ?: $oBill['purchase_date'])) ?></td>
                            <td style="text-align: right;" class="tabular-nums"><?= e($oBill['grand_total_formatted']) ?></td>
                            <td style="text-align: right; color: var(--color-success);" class="tabular-nums"><?= e($oBill['amount_paid_formatted']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-danger);" class="tabular-nums"><?= e($oBill['amount_due_formatted']) ?></td>
                            <td style="text-align: center;">
                                <?php if ($oBill['days_overdue'] > 0): ?>
                                    <span class="badge badge-danger" style="font-size: 0.75rem;">
                                        <?= (int)$oBill['days_overdue'] ?> days overdue
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-info" style="font-size: 0.75rem;">Within terms</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $oBill['status'] === 'PARTIAL' ? 'warning' : 'danger' ?>">
                                    <?= e($oBill['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Tabs Inside Supplier Profile -->
<div class="card" id="supplier-tabs-card">
    <div class="card-header" style="padding: 0; background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0);">
        <div class="supplier-tabs-nav" style="display: flex; gap: 0; overflow-x: auto; border-bottom: none;" role="tablist">
            <button type="button" class="profile-tab-btn active" data-tab="tab-overview" onclick="switchProfileTab('tab-overview')">
                Overview
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-bills" onclick="switchProfileTab('tab-bills')">
                Purchase Bills (<?= count($bills) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-payments" onclick="switchProfileTab('tab-payments')">
                Disbursements (<?= count($payments) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-debit-notes" onclick="switchProfileTab('tab-debit-notes')">
                Debit Notes (<?= count($debitNotes) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-ledger" onclick="switchProfileTab('tab-ledger')">
                Supplier Ledger (<?= count($ledger) ?>)
            </button>
        </div>
    </div>

    <!-- Tab 1: Overview -->
    <div id="tab-overview" class="profile-tab-pane" style="display: block; padding: 1.5rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Recent Inward Bills -->
            <div>
                <h4 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 0.95rem; text-transform: uppercase; color: var(--color-muted);">
                    Recent Inward Bills
                </h4>
                <div class="table-container">
                    <table class="table" style="width: 100%; font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Date</th>
                                <th style="text-align: right;">Amount</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($bills, 0, 5) as $rBill): ?>
                                <tr>
                                    <td><strong><?= e($rBill['purchase_number'] ?: ('BILL-' . $rBill['id'])) ?></strong></td>
                                    <td><?= e(format_date($rBill['purchase_date'])) ?></td>
                                    <td style="text-align: right;" class="tabular-nums"><?= e($rBill['grand_total_formatted']) ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-<?= $rBill['status'] === 'PAID' ? 'success' : ($rBill['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>" style="font-size: 0.7rem;">
                                            <?= e($rBill['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bills)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--color-muted); padding: 1.5rem;">No inward bills recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payments -->
            <div>
                <h4 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 0.95rem; text-transform: uppercase; color: var(--color-muted);">
                    Recent Disbursements
                </h4>
                <div class="table-container">
                    <table class="table" style="width: 100%; font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Date</th>
                                <th>Mode</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($payments, 0, 5) as $rPmt): ?>
                                <tr>
                                    <td><strong><?= e($rPmt['payment_number']) ?></strong></td>
                                    <td><?= e(format_date($rPmt['payment_date'])) ?></td>
                                    <td><span class="badge badge-secondary" style="font-size: 0.7rem;"><?= e($rPmt['payment_mode']) ?></span></td>
                                    <td style="text-align: right; color: var(--color-success); font-weight: 600;" class="tabular-nums"><?= e($rPmt['amount_formatted']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--color-muted); padding: 1.5rem;">No disbursements recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Purchase Bills -->
    <div id="tab-bills" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Vendor Inv #</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Grand Total</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th style="text-align: right;">Amount Due</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--color-muted);">No purchase bills found for this supplier.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td><strong><?= e($b['purchase_number'] ?: ('BILL-' . $b['id'])) ?></strong></td>
                                <td><code><?= e($b['vendor_invoice_number'] ?: '—') ?></code></td>
                                <td><?= e(format_date($b['purchase_date'])) ?></td>
                                <td><?= e(format_date($b['due_date'] ?: $b['purchase_date'])) ?></td>
                                <td style="text-align: right;" class="tabular-nums"><?= e($b['grand_total_formatted']) ?></td>
                                <td style="text-align: right; color: var(--color-success);" class="tabular-nums"><?= e($b['amount_paid_formatted']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: <?= (float)$b['amount_due'] > 0 ? 'var(--color-danger)' : 'var(--color-muted)' ?>;" class="tabular-nums">
                                    <?= e($b['amount_due_formatted']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $b['status'] === 'PAID' ? 'success' : ($b['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Disbursements / Payments -->
    <div id="tab-payments" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Disbursement #</th>
                        <th>Date</th>
                        <th>Payment Mode</th>
                        <th>Reference / UTR</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-muted);">No payment records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><strong><?= e($p['payment_number']) ?></strong></td>
                                <td><?= e(format_date($p['payment_date'])) ?></td>
                                <td><span class="badge badge-secondary"><?= e($p['payment_mode']) ?></span></td>
                                <td><code><?= e($p['reference_number']) ?></code></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-success);" class="tabular-nums"><?= e($p['amount_formatted']) ?></td>
                                <td style="text-align: center;"><span class="badge badge-success"><?= e($p['status'] ?: 'COMPLETED') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 4: Debit Notes -->
    <div id="tab-debit-notes" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Debit Note #</th>
                        <th>Date</th>
                        <th>Reason / Notes</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debitNotes)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--color-muted);">No debit notes recorded for this supplier.</td></tr>
                    <?php else: ?>
                        <?php foreach ($debitNotes as $dn): ?>
                            <tr>
                                <td><strong><?= e($dn['debit_note_number']) ?></strong></td>
                                <td><?= e(format_date($dn['debit_note_date'])) ?></td>
                                <td><?= e($dn['reason'] ?: 'Purchase Return / Adjustment') ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-primary);" class="tabular-nums"><?= e($dn['amount_formatted']) ?></td>
                                <td style="text-align: center;"><span class="badge badge-info"><?= e($dn['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 5: Supplier Ledger -->
    <div id="tab-ledger" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: var(--color-muted);">
                Chronological statement showing inward bills (Credit / Liability increase) and payments &amp; returns (Debit / Liability decrease).
            </div>
            <a href="/suppliers/<?= $sid ?>/statement/csv" class="btn btn-secondary" style="font-size: 0.85rem; text-decoration: none;">
                Download Statement (CSV)
            </a>
        </div>
        <div class="table-container">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Voucher #</th>
                        <th>Ref #</th>
                        <th>Description</th>
                        <th style="text-align: right;">Debit (Disbursement)</th>
                        <th style="text-align: right;">Credit (Inward Bill)</th>
                        <th style="text-align: right;">Running Balance (Payable)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--color-muted);">No ledger entries recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $row): ?>
                            <tr>
                                <td><?= e(format_date($row['date'])) ?></td>
                                <td><span class="badge badge-secondary" style="font-size: 0.7rem;"><?= e($row['type']) ?></span></td>
                                <td><strong><?= e($row['voucher_no']) ?></strong></td>
                                <td><code><?= e($row['ref_no']) ?></code></td>
                                <td><?= e($row['description']) ?></td>
                                <td style="text-align: right; color: var(--color-success);" class="tabular-nums"><?= e($row['debit_formatted']) ?></td>
                                <td style="text-align: right; color: var(--color-danger);" class="tabular-nums"><?= e($row['credit_formatted']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: <?= (float)$row['running_balance'] > 0 ? 'var(--color-danger)' : 'var(--color-heading)' ?>;" class="tabular-nums">
                                    <?= e($row['running_balance_formatted']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.profile-tab-btn {
    padding: 0.75rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-muted);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.profile-tab-btn:hover {
    color: var(--color-primary);
}
.profile-tab-btn.active {
    color: var(--color-primary);
    border-bottom: 2px solid var(--color-primary);
    background: var(--bg-card, #ffffff);
}
</style>

<script>
function switchProfileTab(tabId) {
    document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.profile-tab-pane').forEach(p => p.style.display = 'none');

    const btn = document.querySelector(`.profile-tab-btn[data-tab="${tabId}"]`);
    const pane = document.getElementById(tabId);
    if (btn) btn.classList.add('active');
    if (pane) pane.style.display = 'block';
}
</script>

