<?php
/**
 * WTSBill ERP — GSTR-1 Tax Compliance Report View
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">GSTR-1 Tax Compliance & Returns</h1>
        <p class="page-subtitle">Outward supplies summary for GST portal filing (B2B Tax Invoices, B2C Small, HSN Summary, and Nil-Rated)</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="/reports/gst/export?format=json" class="btn btn-secondary" download>Export GSTN JSON</a>
        <a href="/reports/gst/export?format=csv" class="btn btn-primary" download>Export Excel / CSV</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">GSTR-1 Section Summary &mdash; Wis Technosavvy Pvt Ltd (GSTIN: 27AADCW7577N1ZE)</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>GSTR-1 Section</th>
                    <th>Count</th>
                    <th style="text-align: right;">Taxable Value</th>
                    <th style="text-align: right;">IGST</th>
                    <th style="text-align: right;">CGST</th>
                    <th style="text-align: right;">SGST</th>
                    <th style="text-align: right;">Total Tax</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>4A - B2B Invoices (Registered Buyers)</strong></td>
                    <td>2</td>
                    <td style="text-align: right; font-weight: 600;">₹ 1,72,200.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 15,498.00</td>
                    <td style="text-align: right;">₹ 15,498.00</td>
                    <td style="text-align: right; font-weight: 700; color: var(--color-info);">₹ 30,996.00</td>
                </tr>
                <tr>
                    <td><strong>7 - B2C (Others / Small Unregistered)</strong></td>
                    <td>0</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                </tr>
                <tr>
                    <td><strong>9B - Credit / Debit Notes</strong></td>
                    <td>1</td>
                    <td style="text-align: right;">₹ 10,000.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 900.00</td>
                    <td style="text-align: right;">₹ 900.00</td>
                    <td style="text-align: right; font-weight: 700; color: var(--color-warning);">₹ 1,800.00</td>
                </tr>
                <tr>
                    <td><strong>12 - HSN-wise Summary of Outward Supplies</strong></td>
                    <td>2</td>
                    <td style="text-align: right; font-weight: 600;">₹ 1,72,200.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 15,498.00</td>
                    <td style="text-align: right;">₹ 15,498.00</td>
                    <td style="text-align: right; font-weight: 700; color: var(--color-info);">₹ 30,996.00</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="font-weight: 700; background: var(--bg-surface); font-size: 1.05rem;">
                    <td>Total Outward Taxable Supplies</td>
                    <td>3</td>
                    <td style="text-align: right;">₹ 1,82,200.00</td>
                    <td style="text-align: right;">₹ 0.00</td>
                    <td style="text-align: right;">₹ 16,398.00</td>
                    <td style="text-align: right;">₹ 16,398.00</td>
                    <td style="text-align: right; color: var(--color-success);">₹ 32,796.00</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
