<?php
$title = $title ?? 'GST Outward Sales Register';
$register = $register ?? [];
$summary = $register['summary'] ?? [];
$invoices = $register['invoices'] ?? [];
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;
$selectedSupplyType = $selectedSupplyType ?? 'ALL';
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/gst" style="color: #64748b; text-decoration: none;">GST Compliance</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Sales Register</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">GST Outward Sales Register</h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Comprehensive outward tax invoice register with B2B/B2C classification and tax breakdowns.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/reports/gst-sales-register/export?from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&branch_id=<?= (int)$selectedBranch ?>&supply_type=<?= urlencode($selectedSupplyType) ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </a>
            <a href="/reports/gstr-3b" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                GSTR-3B Summary
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                Print Register
            </button>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Invoices</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;"><?= (int)($summary['total_invoices'] ?? 0) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">B2B: <?= (int)($summary['b2b_count'] ?? 0) ?> | B2C: <?= (int)($summary['b2c_count'] ?? 0) ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Taxable Turnover</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($summary['total_taxable'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Net assessable value</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Output GST Liability</div>
            <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-top: 6px;">₹<?= number_format((float)($summary['total_tax'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #2563eb; margin-top: 4px;">CGST + SGST + IGST</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">Gross Sales Revenue</div>
            <div style="font-size: 24px; font-weight: 700; color: #059669; margin-top: 6px;">₹<?= number_format((float)($summary['total_grand'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #059669; margin-top: 4px;">Total Invoice Value</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/reports/gst-sales-register" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">From Date</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">To Date</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Supply Category</label>
                <select name="supply_type" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 150px;">
                    <option value="ALL" <?= $selectedSupplyType === 'ALL' ? 'selected' : '' ?>>All Supplies</option>
                    <option value="B2B" <?= $selectedSupplyType === 'B2B' ? 'selected' : '' ?>>B2B (Registered)</option>
                    <option value="B2C" <?= $selectedSupplyType === 'B2C' ? 'selected' : '' ?>>B2C (Consumers)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Branch</label>
                <select name="branch_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 160px;">
                    <option value="0">Consolidated (All Branches)</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$selectedBranch === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" style="padding: 8px 18px; border-radius: 8px; background: #2563eb; color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Filter Register
                </button>
            </div>
        </form>
    </div>

    <!-- Register Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Invoice No</th>
                    <th style="padding: 12px 16px;">Date</th>
                    <th style="padding: 12px 16px;">Customer &amp; GSTIN</th>
                    <th style="padding: 12px 16px;">Category</th>
                    <th style="padding: 12px 16px; text-align: right;">Taxable (₹)</th>
                    <th style="padding: 12px 16px; text-align: right;">CGST (₹)</th>
                    <th style="padding: 12px 16px; text-align: right;">SGST (₹)</th>
                    <th style="padding: 12px 16px; text-align: right;">IGST (₹)</th>
                    <th style="padding: 12px 16px; text-align: right;">Total Tax (₹)</th>
                    <th style="padding: 12px 16px; text-align: right;">Grand Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="10" style="padding: 32px; text-align: center; color: #94a3b8;">
                            No sales invoices found for the selected period and criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-weight: 600;">
                                <a href="/sales/invoices/<?= (int)$inv['id'] ?>" style="color: #2563eb; text-decoration: none;">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px; color: #64748b;">
                                <?= date('d-M-Y', strtotime($inv['invoice_date'])) ?>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 500; color: #1e293b;">
                                    <?php if (!empty($inv['customer_id'])): ?>
                                        <a href="/customers/<?= (int)$inv['customer_id'] ?>/profile" style="color: #1e293b; text-decoration: none;">
                                            <?= htmlspecialchars($inv['customer_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($inv['customer_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 11px; font-family: monospace; color: #64748b;">
                                    <?= !empty($inv['customer_gstin']) ? htmlspecialchars($inv['customer_gstin']) : 'Unregistered' ?>
                                </div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; <?= $inv['b2b_type'] === 'B2B' ? 'background: #dbeafe; color: #1e40af;' : 'background: #f1f5f9; color: #475569;' ?>">
                                    <?= htmlspecialchars($inv['b2b_type']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 500;">
                                ₹<?= number_format((float)$inv['taxable_value'], 2) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; color: #64748b;">
                                <?= (float)$inv['cgst_amount'] > 0 ? '₹' . number_format((float)$inv['cgst_amount'], 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; color: #64748b;">
                                <?= (float)$inv['sgst_amount'] > 0 ? '₹' . number_format((float)$inv['sgst_amount'], 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; color: #64748b;">
                                <?= (float)$inv['igst_amount'] > 0 ? '₹' . number_format((float)$inv['igst_amount'], 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #2563eb;">
                                ₹<?= number_format((float)$inv['total_tax'], 2) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #0f172a;">
                                ₹<?= number_format((float)$inv['grand_total'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: 700; font-size: 13px;">
                    <td colspan="4" style="padding: 14px 16px; color: #0f172a;">
                        TOTAL REGISTER SUMMARY (<?= (int)($summary['total_invoices'] ?? 0) ?> INVOICES)
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #0f172a;">
                        ₹<?= number_format((float)($summary['total_taxable'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #64748b;">
                        ₹<?= number_format((float)($summary['total_cgst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #64748b;">
                        ₹<?= number_format((float)($summary['total_sgst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #64748b;">
                        ₹<?= number_format((float)($summary['total_igst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #2563eb;">
                        ₹<?= number_format((float)($summary['total_tax'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #059669; font-size: 14px;">
                        ₹<?= number_format((float)($summary['total_grand'] ?? 0), 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
@media print {
    body { background: #fff !important; }
    .content-wrapper { padding: 0 !important; }
    button, a, form { display: none !important; }
}
</style>

