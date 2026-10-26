<?php
/**
 * WTSBill ERP — Profit & Loss Statement View
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Profit & Loss Statement</h1>
        <p class="page-subtitle">FY 2026-27 &bull; Operating revenue, Cost of Goods Sold (COGS), operating expenses, and net profit</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print Statement</button>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Statement of Financial Performance (01-Apr-2026 to 31-Mar-2027)</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color);">
                    <th>Account Category</th>
                    <th style="text-align: right;">Amount (INR)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="font-weight: 700; color: var(--color-success); background: var(--bg-surface);">
                    <td>1. Revenue from Operations (Gross Sales)</td>
                    <td style="text-align: right;">₹ 14,25,890.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Domestic Taxable Invoices</td>
                    <td style="text-align: right;">₹ 14,25,890.00</td>
                </tr>
                <tr style="font-weight: 700; color: var(--color-danger); background: var(--bg-surface);">
                    <td>2. Cost of Goods Sold (COGS)</td>
                    <td style="text-align: right;">₹ 6,12,400.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Raw Materials Consumed</td>
                    <td style="text-align: right;">₹ 4,80,000.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Direct Manufacturing Freight & Labor</td>
                    <td style="text-align: right;">₹ 1,32,400.00</td>
                </tr>
                <tr style="font-weight: 700; border-top: 1px solid var(--border-color); font-size: 1.05rem;">
                    <td>GROSS PROFIT (1 - 2)</td>
                    <td style="text-align: right; color: var(--color-primary);">₹ 8,13,490.00</td>
                </tr>
                <tr style="font-weight: 700; color: var(--color-warning); background: var(--bg-surface);">
                    <td>3. Operating & Administrative Expenses</td>
                    <td style="text-align: right;">₹ 1,23,950.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Rent & Premises</td>
                    <td style="text-align: right;">₹ 85,000.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Electricity & Utilities</td>
                    <td style="text-align: right;">₹ 24,150.00</td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem; color: var(--text-muted);">&bull; Office & Miscellaneous</td>
                    <td style="text-align: right;">₹ 14,800.00</td>
                </tr>
                <tr style="font-weight: 700; border-top: 2px solid var(--border-color); font-size: 1.15rem; background: var(--bg-surface);">
                    <td style="color: var(--color-success);">NET PROFIT (Before Tax)</td>
                    <td style="text-align: right; color: var(--color-success);">₹ 6,89,540.00</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
