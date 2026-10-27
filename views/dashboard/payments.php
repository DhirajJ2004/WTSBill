<?php
/**
 * WTSBill ERP — Payments & Collections Dashboard (Accountant View)
 * Exclusively displays financial flow, receivables, payables, recent collections and overdue items.
 *
 * @var array $metrics
 * @var array $recentPayments
 * @var array $overdueInvoices
 */

$title = 'Payments Dashboard';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Payments & Financial Operations</h1>
        <p class="page-subtitle">Real-time overview of accounts receivable, accounts payable, collections, and overdue invoices</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="/payments" class="btn btn-primary">+ Record Payment / Receipt</a>
        <a href="/sales/invoices/create" class="btn btn-secondary">+ New Invoice</a>
    </div>
</div>

<!-- Financial Flow KPIs -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- 1. Receivables -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Accounts Receivable</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-warning); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['receivables'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Outstanding customer dues</div>
        </div>
    </div>

    <!-- 2. Payables -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Accounts Payable</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['payables'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Pending liabilities & bills</div>
        </div>
    </div>

    <!-- 3. Collections Received -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Received (Collections)</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['total_received'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Inward payments cleared</div>
        </div>
    </div>

    <!-- 4. Disbursements Paid -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Paid (Disbursements)</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-info); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['total_paid'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Outward payments settled</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Recent Payments & Receipts -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Recent Payments & Receipts</h3>
            <a href="/payments" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">View All &rarr;</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentPayments)): ?>
                        <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td><strong><?= e($p['payment_number'] ?? $p['receipt_number'] ?? ('#' . $p['id'])) ?></strong></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);"><?= e(format_date($p['payment_date'])) ?></td>
                                <td>
                                    <?php $isReceipt = strtoupper($p['payment_type'] ?? '') === 'RECEIPT'; ?>
                                    <span class="badge badge-<?= $isReceipt ? 'success' : 'info' ?>">
                                        <?= $isReceipt ? 'RECEIPT (IN)' : 'PAYMENT (OUT)' ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.8rem;"><?= e($p['payment_mode'] ?? 'Bank') ?></td>
                                <td style="text-align: right; font-weight: 600; color: <?= $isReceipt ? 'var(--color-success)' : 'var(--text-primary)' ?>;">
                                    <?= ($isReceipt ? '+' : '-') . format_currency((float)$p['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No recent payment records.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending / Overdue Invoices -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Pending & Overdue Invoices</h3>
            <a href="/sales/invoices" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">View Invoices &rarr;</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Due Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($overdueInvoices)): ?>
                        <?php foreach ($overdueInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <a href="/sales/invoices/<?= (int)$inv['id'] ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">
                                        <?= e($inv['invoice_number']) ?>
                                    </a>
                                </td>
                                <td><?= e($inv['customer_name'] ?? 'Direct Customer') ?></td>
                                <td style="font-size: 0.8rem; color: var(--color-danger);"><?= e(format_date($inv['due_date'] ?? $inv['invoice_date'])) ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-danger);">
                                    <?= format_currency((float)$inv['amount_due']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">All invoices are settled. No outstanding dues!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
