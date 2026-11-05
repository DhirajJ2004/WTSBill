<?php
/**
 * WTSBill ERP — Complete 360-Degree Customer Profile & Transaction History
 *
 * @var array $customer
 * @var array $stats
 * @var array $invoices
 * @var array $outstandingInvoices
 * @var array $payments
 * @var array $quotations
 * @var array $creditNotes
 * @var array $ledger
 * @var array $activities
 * @var array $bankAccounts
 */

$cid = (int)$customer['id'];
?>

<div class="page-header customer-profile-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title" style="margin: 0;"><?= e($customer['name']) ?></h1>
            <span class="badge badge-primary" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= e($customer['customer_code']) ?>
            </span>
            <span class="badge badge-<?= $customer['status'] === 'ACTIVE' ? 'success' : 'secondary' ?>" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= e($customer['status']) ?>
            </span>
            <span class="badge badge-info" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= e($customer['customer_type'] ?: 'Business') ?>
            </span>
        </div>
        <p class="page-subtitle" style="margin-top: 0.25rem;">
            <?= e($customer['assigned_branch']) ?> &bull; Registered on <?= e($customer['registration_date']) ?>
        </p>
    </div>
    
    <!-- Profile Actions -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/sales/invoices/create?customer_id=<?= $cid ?>" class="btn btn-primary" style="text-decoration: none;">
            + Create Invoice
        </a>
        <a href="/sales/quotations/create?customer_id=<?= $cid ?>" class="btn btn-secondary" style="text-decoration: none;">
            + Create Quotation
        </a>
        <?php if (\WTSBill\Auth\Gate::can('payments.create') && (float)($stats['outstanding_balance'] ?? 0) > 0.001): ?>
            <button type="button" class="btn btn-success" onclick="openProfilePaymentModal()">
                + Record Payment
            </button>
        <?php endif; ?>
        <a href="/customers/<?= $cid ?>/statement/csv" class="btn btn-secondary" style="text-decoration: none;">
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
        <button type="button" class="btn btn-secondary" onclick="openShareStatementModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 3px;">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
            Share
        </button>
    </div>
</div>

<!-- Customer Information Summary Block -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); padding: 0.85rem 1.25rem;">
        <h3 class="card-title" style="font-size: 0.95rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);">
            Customer Master Details &amp; Compliance Information
        </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Company / Trade Name</small>
                <strong><?= e($customer['company_name'] ?: $customer['name']) ?></strong>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">GSTIN</small>
                <code><?= e($customer['gstin']) ?></code>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">PAN Number</small>
                <code><?= e($customer['pan'] ?: '—') ?></code>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Primary Phone</small>
                <a href="tel:<?= e($customer['phone']) ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 600;">
                    <?= e($customer['phone']) ?>
                </a>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Email Address</small>
                <a href="mailto:<?= e($customer['email']) ?>" style="color: var(--color-primary); text-decoration: none;">
                    <?= e($customer['email']) ?>
                </a>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Credit Terms &amp; Limit</small>
                <span><?= (int)($customer['payment_terms_days'] ?? 30) ?> Days &bull; Limit: <?= format_currency((float)($customer['credit_limit'] ?? 0)) ?></span>
            </div>
            <div style="grid-column: span 2;">
                <small style="color: var(--color-muted); display: block; font-size: 0.75rem; text-transform: uppercase;">Billing &amp; Shipping Address</small>
                <span><?= e($customer['billing_address']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary KPI Dashboard Cards (Section 2.2) -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Total Invoiced -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-primary, #0ea5e9);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Total Invoiced</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-heading);">
            <?= e($stats['total_invoiced_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($invoices) ?> Total Invoices</div>
    </div>

    <!-- Total Paid -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-success, #10b981);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Total Paid</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-success, #10b981);">
            <?= e($stats['total_paid_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($payments) ?> Payments Recorded</div>
    </div>

    <!-- Outstanding Balance -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid <?= (float)$stats['outstanding_balance'] > 0 ? 'var(--color-danger, #ef4444)' : 'var(--color-success, #10b981)' ?>;">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Outstanding Balance</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: <?= (float)$stats['outstanding_balance'] > 0 ? 'var(--color-danger, #ef4444)' : 'var(--color-success, #10b981)' ?>;">
            <?= e($stats['outstanding_balance_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($outstandingInvoices) ?> Unsettled Invoices</div>
    </div>

    <!-- Overdue Amount -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-warning, #f59e0b);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Overdue Amount</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: <?= (float)$stats['overdue_amount'] > 0 ? 'var(--color-warning, #f59e0b)' : 'var(--color-muted)' ?>;">
            <?= e($stats['overdue_amount_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">Past Agreed Due Date</div>
    </div>

    <!-- Total Quotations -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid #8b5cf6;">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Total Quotations</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-heading);">
            <?= (int)$stats['total_quotations'] ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">
            <?= (int)$stats['accepted_quotations'] ?> Accepted &bull; <?= (int)$stats['pending_quotations'] ?> Pending
        </div>
    </div>

    <!-- Total Credit Notes -->
    <div class="card" style="padding: 1.15rem; border-left: 4px solid #64748b;">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-muted); margin-bottom: 0.35rem;">Credit Adjustments</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-heading);">
            <?= e($stats['total_credit_notes_formatted']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;"><?= count($creditNotes) ?> Credit Notes Issued</div>
    </div>
</div>

<!-- Section 2.4: Outstanding Balance Details Table -->
<?php if (!empty($outstandingInvoices)): ?>
    <div class="card" style="margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.3);">
        <div class="card-header" style="background: rgba(239, 68, 68, 0.05); border-bottom: 1px solid rgba(239, 68, 68, 0.2); display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge badge-danger">Unsettled</span>
                <h3 class="card-title" style="margin: 0; color: var(--color-danger, #ef4444); font-size: 1rem;">
                    Outstanding Invoices (<?= count($outstandingInvoices) ?> Invoices &bull; <?= e($stats['outstanding_balance_formatted']) ?>)
                </h3>
            </div>
            <?php if (\WTSBill\Auth\Gate::can('payments.create')): ?>
                <button type="button" class="btn btn-success" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;" onclick="openProfilePaymentModal()">
                    + Record Payment
                </button>
            <?php endif; ?>
        </div>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Original Amount</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th style="text-align: right;">Remaining Balance</th>
                        <th style="text-align: center;">Days Overdue</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outstandingInvoices as $oInv): ?>
                        <tr>
                            <td>
                                <a href="/sales/invoices/<?= (int)$oInv['id'] ?>/print" target="_blank" style="color: var(--color-primary); font-weight: 700; text-decoration: underline;">
                                    <?= e($oInv['invoice_number']) ?>
                                </a>
                            </td>
                            <td><?= e(format_date($oInv['invoice_date'])) ?></td>
                            <td><?= e(format_date($oInv['due_date'] ?: $oInv['invoice_date'])) ?></td>
                            <td style="text-align: right;" class="tabular-nums"><?= e($oInv['grand_total_formatted']) ?></td>
                            <td style="text-align: right; color: var(--color-success);" class="tabular-nums"><?= e($oInv['amount_paid_formatted']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-danger);" class="tabular-nums"><?= e($oInv['amount_due_formatted']) ?></td>
                            <td style="text-align: center;">
                                <?php if ($oInv['days_overdue'] > 0): ?>
                                    <span class="badge badge-danger" style="font-size: 0.75rem;">
                                        <?= (int)$oInv['days_overdue'] ?> days overdue
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-info" style="font-size: 0.75rem;">Within terms</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $oInv['status'] === 'PARTIAL' ? 'warning' : 'danger' ?>">
                                    <?= e($oInv['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if (\WTSBill\Auth\Gate::can('payments.create')): ?>
                                    <button type="button" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" 
                                            onclick="openInvoiceSpecificPayment(<?= (int)$oInv['id'] ?>, '<?= e(addslashes($oInv['invoice_number'])) ?>', <?= (float)$oInv['amount_due'] ?>)">
                                        + Pay
                                    </button>
                                <?php endif; ?>
                                <a href="/sales/invoices/<?= (int)$oInv['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none;">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Section 2.3: 7 Tabs Inside Customer Profile -->
<div class="card" id="customer-tabs-card">
    <!-- Tab Navigation Bar -->
    <div class="card-header" style="padding: 0; background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0);">
        <div class="customer-tabs-nav" style="display: flex; gap: 0; overflow-x: auto; border-bottom: none;" role="tablist">
            <button type="button" class="profile-tab-btn active" data-tab="tab-overview" onclick="switchProfileTab('tab-overview')">
                Overview
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-invoices" onclick="switchProfileTab('tab-invoices')">
                Invoices (<?= count($invoices) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-payments" onclick="switchProfileTab('tab-payments')">
                Payments (<?= count($payments) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-quotations" onclick="switchProfileTab('tab-quotations')">
                Quotations (<?= count($quotations) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-credit-notes" onclick="switchProfileTab('tab-credit-notes')">
                Credit Notes (<?= count($creditNotes) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-ledger" onclick="switchProfileTab('tab-ledger')">
                Ledger / Transactions (<?= count($ledger) ?>)
            </button>
            <button type="button" class="profile-tab-btn" data-tab="tab-activities" onclick="switchProfileTab('tab-activities')">
                Activity History (<?= count($activities) ?>)
            </button>
        </div>
    </div>

    <!-- Tab 1: Overview -->
    <div id="tab-overview" class="profile-tab-pane" style="display: block; padding: 1.5rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Recent Invoices -->
            <div>
                <h4 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 0.95rem; text-transform: uppercase; color: var(--color-muted);">
                    Recent Invoices
                </h4>
                <div class="table-container">
                    <table class="table" style="width: 100%; font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th style="text-align: right;">Amount</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($invoices, 0, 5) as $rInv): ?>
                                <tr>
                                    <td>
                                        <a href="/sales/invoices/<?= (int)$rInv['id'] ?>/print" target="_blank" style="color: var(--color-primary); font-weight: 600;">
                                            <?= e($rInv['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_date($rInv['invoice_date'])) ?></td>
                                    <td style="text-align: right;" class="tabular-nums"><?= e($rInv['grand_total_formatted']) ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-<?= $rInv['status'] === 'PAID' ? 'success' : ($rInv['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>" style="font-size: 0.7rem;">
                                            <?= e($rInv['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payments -->
            <div>
                <h4 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 0.95rem; text-transform: uppercase; color: var(--color-muted);">
                    Recent Payments Received
                </h4>
                <div class="table-container">
                    <table class="table" style="width: 100%; font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Mode</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--color-muted); padding: 1.5rem;">No payments recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($payments, 0, 5) as $rPmt): ?>
                                    <tr>
                                        <td><strong><?= e($rPmt['receipt_number']) ?></strong></td>
                                        <td><?= e(format_date($rPmt['payment_date'])) ?></td>
                                        <td><span class="badge badge-secondary" style="font-size: 0.7rem;"><?= e($rPmt['payment_mode']) ?></span></td>
                                        <td style="text-align: right; color: var(--color-success); font-weight: 600;" class="tabular-nums">
                                            <?= e($rPmt['amount_formatted']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Invoices -->
    <div id="tab-invoices" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Invoice Amount</th>
                        <th style="text-align: right;">Paid Amount</th>
                        <th style="text-align: right;">Outstanding Amount</th>
                        <th style="text-align: center;">Payment Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--color-muted);">No invoices generated for this customer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td>
                                    <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" style="color: var(--color-primary); font-weight: 700;">
                                        <?= e($inv['invoice_number']) ?>
                                    </a>
                                </td>
                                <td><?= e(format_date($inv['invoice_date'])) ?></td>
                                <td><?= e(format_date($inv['due_date'] ?: $inv['invoice_date'])) ?></td>
                                <td style="text-align: right; font-weight: 600;" class="tabular-nums"><?= e($inv['grand_total_formatted']) ?></td>
                                <td style="text-align: right; color: var(--color-success);" class="tabular-nums"><?= e($inv['amount_paid_formatted']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: <?= (float)$inv['amount_due'] > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;" class="tabular-nums">
                                    <?= e($inv['amount_due_formatted']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $inv['status'] === 'PAID' ? 'success' : ($inv['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>">
                                        <?= e($inv['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if (\WTSBill\Auth\Gate::can('payments.create') && (float)$inv['amount_due'] > 0.001): ?>
                                        <button type="button" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="openInvoiceSpecificPayment(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>', <?= (float)$inv['amount_due'] ?>)">+ Pay</button>
                                    <?php endif; ?>
                                    <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Payments -->
    <div id="tab-payments" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Payment Date</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Payment Mode</th>
                        <th>Transaction Reference</th>
                        <th>Related Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-muted);">No payment receipts recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pmt): ?>
                            <tr>
                                <td><strong><?= e($pmt['receipt_number']) ?></strong></td>
                                <td><?= e(format_date($pmt['payment_date'])) ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-success);" class="tabular-nums"><?= e($pmt['amount_formatted']) ?></td>
                                <td><span class="badge badge-secondary"><?= e($pmt['payment_mode']) ?></span></td>
                                <td><code><?= e($pmt['reference_number']) ?></code></td>
                                <td>
                                    <?php if (!empty($pmt['invoice_number'])): ?>
                                        <a href="/sales/invoices/<?= (int)$pmt['invoice_id'] ?>/print" target="_blank" style="color: var(--color-primary); text-decoration: underline;">
                                            <?= e($pmt['invoice_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 4: Quotations -->
    <div id="tab-quotations" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Quotation Number</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th style="text-align: right;">Total Amount</th>
                        <th style="text-align: center;">Quotation Status</th>
                        <th>Converted Invoice Reference</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotations)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--color-muted);">No quotations drafted for this customer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($quotations as $q): ?>
                            <tr>
                                <td><strong><?= e($q['quotation_number']) ?></strong></td>
                                <td><?= e(format_date($q['quotation_date'])) ?></td>
                                <td><?= e($q['valid_until'] ? format_date($q['valid_until']) : '—') ?></td>
                                <td style="text-align: right; font-weight: 600;" class="tabular-nums"><?= e($q['grand_total_formatted']) ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $q['status'] === 'CONVERTED' ? 'success' : ($q['status'] === 'ACCEPTED' ? 'primary' : 'secondary') ?>">
                                        <?= e($q['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($q['converted_invoice_number'])): ?>
                                        <a href="/sales/invoices/<?= (int)$q['converted_invoice_id'] ?>/print" target="_blank" style="color: var(--color-primary); font-weight: 600;">
                                            <?= e($q['converted_invoice_number']) ?>
                                        </a>
                                        <?php if (!empty($q['linked_invoice_status'])): ?>
                                            <span class="badge badge-<?= $q['linked_invoice_status'] === 'PAID' ? 'success' : ($q['linked_invoice_status'] === 'PARTIAL' ? 'warning' : 'danger') ?>" style="font-size: 0.7rem; margin-left: 4px;">
                                                <?= e($q['linked_invoice_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/sales/quotations/<?= (int)$q['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 5: Credit Notes and Adjustments -->
    <div id="tab-credit-notes" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Credit Note Number</th>
                        <th>Date</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Reason</th>
                        <th>Related Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($creditNotes)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--color-muted);">No credit notes or adjustments issued.</td></tr>
                    <?php else: ?>
                        <?php foreach ($creditNotes as $cn): ?>
                            <tr>
                                <td><strong><?= e($cn['credit_note_number']) ?></strong></td>
                                <td><?= e(format_date($cn['credit_note_date'])) ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-danger);" class="tabular-nums"><?= e($cn['amount_formatted']) ?></td>
                                <td><?= e($cn['reason'] ?: 'Commercial Discount / Sales Return') ?></td>
                                <td>
                                    <?php if (!empty($cn['invoice_number'])): ?>
                                        <a href="/sales/invoices/<?= (int)$cn['invoice_id'] ?>/print" target="_blank" style="color: var(--color-primary);">
                                            <?= e($cn['invoice_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 6: Transactions / Ledger -->
    <div id="tab-ledger" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h4 style="margin: 0;">Customer Running Ledger</h4>
                <small style="color: var(--color-muted);">Complete double-entry audit of all billings and settlement receipts</small>
            </div>
            <a href="/customers/<?= $cid ?>/statement/csv" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.65rem; text-decoration: none;">
                Export Ledger (CSV)
            </a>
        </div>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher Number</th>
                        <th>Transaction Type</th>
                        <th>Description</th>
                        <th style="text-align: right;">Debit (INR)</th>
                        <th style="text-align: right;">Credit (INR)</th>
                        <th style="text-align: right;">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--color-muted);">No ledger transactions recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $lRow): ?>
                            <tr>
                                <td><?= e(format_date($lRow['date'])) ?></td>
                                <td><strong><?= e($lRow['voucher_number']) ?></strong></td>
                                <td><span class="badge badge-secondary" style="font-size: 0.75rem;"><?= e($lRow['type']) ?></span></td>
                                <td><?= e($lRow['description']) ?></td>
                                <td style="text-align: right; font-weight: 600; color: <?= $lRow['debit'] > 0 ? 'var(--color-primary)' : 'inherit' ?>;" class="tabular-nums"><?= e($lRow['debit_formatted']) ?></td>
                                <td style="text-align: right; font-weight: 600; color: <?= $lRow['credit'] > 0 ? 'var(--color-success)' : 'inherit' ?>;" class="tabular-nums"><?= e($lRow['credit_formatted']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-heading);" class="tabular-nums"><?= e($lRow['running_balance_formatted']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 7: Activity History -->
    <div id="tab-activities" class="profile-tab-pane" style="display: none; padding: 1.25rem;">
        <div class="table-container">
            <table class="table" style="width: 100%; font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>User</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-muted);">No activity audit logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <tr>
                                <td><?= e(date('d-M-Y H:i:s', strtotime((string)$act['created_at']))) ?></td>
                                <td><span class="badge badge-info" style="font-size: 0.7rem;"><?= e($act['action']) ?></span></td>
                                <td><code><?= e($act['entity_type']) ?> #<?= (int)$act['entity_id'] ?></code></td>
                                <td style="max-width: 350px; white-space: normal;"><?= e($act['description']) ?></td>
                                <td><strong><?= e($act['user_name'] ?: 'System') ?></strong></td>
                                <td><small><?= e($act['ip_address']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record Payment for Customer -->
<div id="profilePaymentModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;" onclick="if(event.target === this) closeProfilePaymentModal()">
    <div class="card" style="width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #e2e8f0); padding: 1rem 1.25rem;">
            <h3 class="card-title" style="margin: 0; font-size: 1.1rem;">Record Customer Payment</h3>
            <button type="button" class="btn btn-secondary" onclick="closeProfilePaymentModal()" style="padding: 0.2rem 0.5rem;">&times;</button>
        </div>
        <form method="POST" action="/payments" id="profilePaymentForm" style="padding: 1.25rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="customer_id" value="<?= $cid ?>">
            <input type="hidden" name="party_type" value="CUSTOMER">
            <input type="hidden" name="party_id" value="<?= $cid ?>">
            <input type="hidden" name="redirect_to" value="/customers/<?= $cid ?>">

            <div style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.85rem;">Customer</label>
                <input type="text" class="form-control" value="<?= e($customer['name']) ?> (<?= e($customer['customer_code']) ?>)" readonly style="background: var(--bg-surface, #f8fafc);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="pay-invoice-select" class="form-label" style="font-size: 0.85rem;">Apply To Invoice</label>
                <select name="invoice_id" id="pay-invoice-select" class="form-control" onchange="onInvoiceSelectChange(this)">
                    <option value="">&mdash; On-Account Payment (No Specific Invoice) &mdash;</option>
                    <?php foreach ($outstandingInvoices as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" data-due="<?= (float)$o['amount_due'] ?>">
                            <?= e($o['invoice_number']) ?> &mdash; Due: <?= format_currency((float)$o['amount_due']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label for="pay-amount" class="form-label" style="font-size: 0.85rem;">Payment Amount (INR) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="pay-amount" class="form-control" required value="<?= (float)($outstandingInvoices[0]['amount_due'] ?? 0) ?>">
                </div>
                <div>
                    <label for="pay-date" class="form-label" style="font-size: 0.85rem;">Payment Date *</label>
                    <input type="date" name="payment_date" id="pay-date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label for="pay-mode" class="form-label" style="font-size: 0.85rem;">Payment Mode *</label>
                    <select name="payment_mode" id="pay-mode" class="form-control" required>
                        <option value="UPI">UPI</option>
                        <option value="BANK">Bank Transfer / NEFT / RTGS</option>
                        <option value="CASH">Cash</option>
                        <option value="CHEQUE">Cheque</option>
                        <option value="CARD">Credit/Debit Card</option>
                    </select>
                </div>
                <div>
                    <label for="pay-bank-account" class="form-label" style="font-size: 0.85rem;">Deposit Account</label>
                    <select name="bank_account_id" id="pay-bank-account" class="form-control">
                        <?php foreach (($bankAccounts ?? []) as $ba): ?>
                            <option value="<?= (int)$ba['id'] ?>">
                                <?= e($ba['bank_name']) ?> (<?= e($ba['account_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="pay-ref" class="form-label" style="font-size: 0.85rem;">Reference / UTR / Cheque #</label>
                <input type="text" name="reference_number" id="pay-ref" class="form-control" placeholder="e.g. UTR12345678, UPI Ref #">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label for="pay-notes" class="form-label" style="font-size: 0.85rem;">Remarks / Notes</label>
                <input type="text" name="notes" id="pay-notes" class="form-control" placeholder="Payment remarks">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid var(--border-color, #e2e8f0); padding-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeProfilePaymentModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Share Customer Statement -->
<div id="shareStatementModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;" onclick="if(event.target === this) closeShareStatementModal()">
    <div class="card" style="width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #e2e8f0); padding: 1rem 1.25rem;">
            <h3 class="card-title" style="margin: 0; font-size: 1.1rem;">Share Account Statement</h3>
            <button type="button" class="btn btn-secondary" onclick="closeShareStatementModal()" style="padding: 0.2rem 0.5rem;">&times;</button>
        </div>
        <div style="padding: 1.25rem;">
            <p style="margin-top: 0; font-size: 0.9rem; color: var(--color-muted);">
                Share a comprehensive financial statement with <strong><?= e($customer['name']) ?></strong>.
            </p>

            <div class="card" style="background: var(--bg-surface, #f8fafc); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div><strong>Outstanding Balance:</strong> <?= e($stats['outstanding_balance_formatted']) ?></div>
                <div><strong>Total Invoiced:</strong> <?= e($stats['total_invoiced_formatted']) ?></div>
                <div><strong>Total Received:</strong> <?= e($stats['total_paid_formatted']) ?></div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- WhatsApp Option -->
                <a id="btn-whatsapp-share" href="https://wa.me/<?= preg_replace('/\D/', '', $customer['phone'] ?? '') ?>?text=<?= urlencode("Hello {$customer['name']},\n\nHere is your account statement summary from Wis Technosavvy Pvt Ltd:\nTotal Invoiced: {$stats['total_invoiced_formatted']}\nTotal Paid: {$stats['total_paid_formatted']}\nOutstanding Balance: {$stats['outstanding_balance_formatted']}\n\nPlease settle any outstanding dues at your earliest convenience.\n\nThank you!") ?>" target="_blank" class="btn btn-success" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    Send Statement via WhatsApp
                </a>

                <!-- Email Option -->
                <a href="mailto:<?= e($customer['email']) ?>?subject=<?= urlencode("Account Statement — {$customer['name']} — WTSBill") ?>&body=<?= urlencode("Hello {$customer['name']},\n\nPlease find your account statement summary below:\n\nTotal Invoiced: {$stats['total_invoiced_formatted']}\nTotal Received: {$stats['total_paid_formatted']}\nOutstanding Balance: {$stats['outstanding_balance_formatted']}\n\nThank you,\nWis Technosavvy Pvt Ltd") ?>" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Send Statement via Email
                </a>

                <!-- CSV Statement Download -->
                <a href="/customers/<?= $cid ?>/statement/csv" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    Download Statement Spreadsheet (.csv)
                </a>
            </div>
        </div>
        <div class="card-footer" style="display: flex; justify-content: flex-end; padding: 0.85rem 1.25rem; border-top: 1px solid var(--border-color, #e2e8f0);">
            <button type="button" class="btn btn-secondary" onclick="closeShareStatementModal()">Close</button>
        </div>
    </div>
</div>

<style>
.profile-tab-btn {
    background: none;
    border: none;
    padding: 0.85rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.profile-tab-btn:hover {
    color: var(--color-heading);
    background: rgba(0, 0, 0, 0.02);
}
.profile-tab-btn.active {
    color: var(--color-primary, #0ea5e9);
    border-bottom-color: var(--color-primary, #0ea5e9);
    background: transparent;
}
@media print {
    .customer-profile-header .btn,
    .customer-tabs-nav,
    #profilePaymentModal,
    #shareStatementModal {
        display: none !important;
    }
    .profile-tab-pane {
        display: block !important;
    }
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

function openProfilePaymentModal() {
    const m = document.getElementById('profilePaymentModal');
    if (m) m.style.display = 'flex';
}
function closeProfilePaymentModal() {
    const m = document.getElementById('profilePaymentModal');
    if (m) m.style.display = 'none';
}

function openInvoiceSpecificPayment(invId, invNumber, dueAmt) {
    const select = document.getElementById('pay-invoice-select');
    const amtInput = document.getElementById('pay-amount');
    if (select) select.value = String(invId);
    if (amtInput) amtInput.value = parseFloat(dueAmt).toFixed(2);
    openProfilePaymentModal();
}

function onInvoiceSelectChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const due = opt.getAttribute('data-due');
    if (due) {
        document.getElementById('pay-amount').value = parseFloat(due).toFixed(2);
    }
}

function openShareStatementModal() {
    const m = document.getElementById('shareStatementModal');
    if (m) m.style.display = 'flex';
}
function closeShareStatementModal() {
    const m = document.getElementById('shareStatementModal');
    if (m) m.style.display = 'none';
}
</script>

