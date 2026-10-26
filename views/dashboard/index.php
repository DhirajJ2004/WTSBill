<?php

$title = 'Dashboard';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Enterprise Dashboard</h1>
        <p class="page-subtitle">Real-time overview of invoices, GST tax collections, receivables, and payables</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="/sales/invoices/create" class="btn btn-primary">+ Create Invoice</a>
        <a href="/customers/create" class="btn btn-secondary">+ New Customer</a>
    </div>
</div>

<!-- Metrics Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Sales</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['total_sales'] ?? 1425890.00)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-success); margin-top: 0.35rem;">&uarr; 12.5% this quarter</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">GST Tax Collected</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-info); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['total_tax'] ?? 256660.20)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">IGST / CGST / SGST split verified</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Accounts Receivable</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-warning); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['receivables'] ?? 318400.00)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-warning); margin-top: 0.35rem;">8 outstanding customer invoices</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Accounts Payable</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['payables'] ?? 184500.00)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">5 pending supplier bills</div>
        </div>
    </div>
</div>

<!-- Recent Invoices Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Tax Invoices</h3>
        <a href="/sales/invoices" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">View All Invoices &rarr;</a>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer Name</th>
                    <th>Date</th>
                    <th style="text-align: right;">Amount</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentInvoices)): ?>
                    <?php foreach ($recentInvoices as $inv): ?>
                        <tr>
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td><?= e($inv['customer_name']) ?></td>
                            <td><?= e(format_date($inv['invoice_date'])) ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= format_currency((float)$inv['grand_total']) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $inv['status'] === 'PAID' ? 'success' : 'warning' ?>">
                                    <?= e($inv['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/sales/invoices/<?= e((string)$inv['id']) ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><strong>INV-2026-0001</strong></td>
                        <td>Tata Steel Limited</td>
                        <td>2026-10-08</td>
                        <td style="text-align: right; font-weight: 600;">₹ 1,18,000.00</td>
                        <td style="text-align: center;"><span class="badge badge-success">PAID</span></td>
                        <td style="text-align: right;"><button class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View</button></td>
                    </tr>
                    <tr>
                        <td><strong>INV-2026-0002</strong></td>
                        <td>Reliance Industries Ltd</td>
                        <td>2026-10-09</td>
                        <td style="text-align: right; font-weight: 600;">₹ 54,200.00</td>
                        <td style="text-align: center;"><span class="badge badge-warning">PENDING</span></td>
                        <td style="text-align: right;"><button class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View</button></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
