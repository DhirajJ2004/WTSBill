<?php
/**
 * WTSBill ERP — General Ledger View
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">General Ledger</h1>
        <p class="page-subtitle">Detailed transactional audit trail, running account balances, and double-entry postings</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <select class="form-control" style="width: 220px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
            <option>1001 - HDFC Bank Account</option>
            <option>1002 - Accounts Receivable</option>
            <option>2001 - Accounts Payable</option>
            <option>4001 - Sales Revenue</option>
        </select>
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print Statement</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ledger Statement: 1001 &mdash; HDFC Bank Current Account</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference #</th>
                    <th>Description</th>
                    <th style="text-align: right;">Debit (INR)</th>
                    <th style="text-align: right;">Credit (INR)</th>
                    <th style="text-align: right;">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>2026-10-01</td>
                    <td><code>JE-2026-0001</code></td>
                    <td>Opening Balance Deposit</td>
                    <td style="text-align: right; color: var(--color-primary); font-weight: 600;">₹ 5,00,000.00</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; font-weight: 700;">₹ 5,00,000.00 Dr</td>
                </tr>
                <tr>
                    <td>2026-10-08</td>
                    <td><code>INV-2026-0001</code></td>
                    <td>Customer Payment: Tata Steel Limited</td>
                    <td style="text-align: right; color: var(--color-primary); font-weight: 600;">₹ 1,18,000.00</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; font-weight: 700;">₹ 6,18,000.00 Dr</td>
                </tr>
                <tr>
                    <td>2026-10-10</td>
                    <td><code>PUR-2026-0001</code></td>
                    <td>Vendor Payout: Bharat Raw Metals Ltd</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; color: var(--color-danger); font-weight: 600;">₹ 75,200.00</td>
                    <td style="text-align: right; font-weight: 700;">₹ 5,42,800.00 Dr</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="font-weight: 700; background: var(--bg-surface);">
                    <td colspan="3">Closing Statement Balance</td>
                    <td style="text-align: right; color: var(--color-primary);">₹ 6,18,000.00</td>
                    <td style="text-align: right; color: var(--color-danger);">₹ 75,200.00</td>
                    <td style="text-align: right; color: var(--color-success); font-size: 1.05rem;">₹ 5,42,800.00 Dr</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
