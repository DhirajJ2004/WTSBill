<?php
$title = $title ?? 'GSTR-3B Monthly Return Summary';
$summary = $summary ?? [];
$t3 = $summary['table_3_1'] ?? [];
$t4 = $summary['table_4'] ?? [];
$t6 = $summary['table_6_1'] ?? [];
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;
$returnPeriod = $summary['return_period'] ?? date('mY');

$outTaxable = $t3['outward_taxable'] ?? [];
$netItc = $t4['net_itc'] ?? [];
$netPayable = $t6['net_tax_payable'] ?? [];
$excessItc = $t6['excess_itc_cforward'] ?? [];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/gst" style="color: #64748b; text-decoration: none;">GST Compliance</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">GSTR-3B Monthly Return</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span>FORM GSTR-3B Tax Summary</span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; background: #e0e7ff; color: #3730a3;">
                    Period: <?= htmlspecialchars($returnPeriod) ?>
                </span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Summary return of outward supplies, input tax credit (ITC) claims, and net tax payable computation.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/reports/gstr-3b/export?format=json&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&branch_id=<?= (int)$selectedBranch ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #0284c7; color: #fff; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Portal JSON
            </a>
            <a href="/reports/gstr-3b/export?format=csv&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&branch_id=<?= (int)$selectedBranch ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                Export CSV
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                Print Form
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/reports/gstr-3b" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">From Date</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">To Date</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Branch Scope</label>
                <select name="branch_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 180px;">
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
                    Compute GSTR-3B
                </button>
            </div>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Total Outward Tax Liability</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($outTaxable['total_tax'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Table 3.1 Total Tax Liability</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">Net Eligible ITC Available</div>
            <div style="font-size: 24px; font-weight: 700; color: #059669; margin-top: 6px;">₹<?= number_format((float)($netItc['total_tax'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Table 4(C) Net Input Credit</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase;">Net Tax Payable via Cash</div>
            <div style="font-size: 24px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format((float)($netPayable['total'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Table 6.1 Challan Cash Payment</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Excess ITC Carried Forward</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($excessItc['total'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Credit Balance to Next Month</div>
        </div>
    </div>

    <!-- Table 3.1: Details of Outward Supplies -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 15px;">
            3.1 Details of Outward Supplies and Inward Supplies Liable to Reverse Charge
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 12px 20px;">Nature of Supplies</th>
                    <th style="padding: 12px 20px; text-align: right;">Total Taxable Value (₹)</th>
                    <th style="padding: 12px 20px; text-align: right;">Integrated Tax (IGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">Central Tax (CGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">State/UT Tax (SGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">Total Tax (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 14px 20px; font-weight: 600; color: #1e293b;">
                        (a) Outward Taxable supplies (other than zero rated, nil rated and exempted)
                    </td>
                    <td style="padding: 14px 20px; text-align: right; font-weight: 600;">
                        ₹<?= number_format((float)($outTaxable['taxable_value'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right;">
                        ₹<?= number_format((float)($outTaxable['igst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right;">
                        ₹<?= number_format((float)($outTaxable['cgst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right;">
                        ₹<?= number_format((float)($outTaxable['sgst'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #2563eb;">
                        ₹<?= number_format((float)($outTaxable['total_tax'] ?? 0), 2) ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; color: #64748b;">(b) Outward Taxable supplies (zero rated / exports)</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">—</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">—</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; color: #64748b;">(c) Other Outward supplies (Nil rated, exempted)</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">—</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">—</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">—</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; color: #64748b;">(d) Inward supplies (liable to reverse charge)</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                    <td style="padding: 12px 20px; text-align: right; color: #94a3b8;">₹0.00</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Table 4: Eligible Input Tax Credit (ITC) -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 15px;">
            4. Eligible Input Tax Credit (ITC)
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 12px 20px;">ITC Details</th>
                    <th style="padding: 12px 20px; text-align: right;">Integrated Tax (IGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">Central Tax (CGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">State/UT Tax (SGST)</th>
                    <th style="padding: 12px 20px; text-align: right;">Total ITC (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php $itcAvail = $t4['itc_available'] ?? []; ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">
                        (A)(5) All Other ITC (Inward supplies from registered suppliers)
                    </td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($itcAvail['igst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($itcAvail['cgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($itcAvail['sgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: #059669;">
                        ₹<?= number_format((float)($itcAvail['total_tax'] ?? 0), 2) ?>
                    </td>
                </tr>
                <?php $itcRev = $t4['itc_reversed'] ?? []; ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; color: #64748b;">
                        (B) ITC Reversed (Debit Notes to Suppliers & Purchase Returns)
                    </td>
                    <td style="padding: 12px 20px; text-align: right; color: #dc2626;">- ₹<?= number_format((float)($itcRev['igst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #dc2626;">- ₹<?= number_format((float)($itcRev['cgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #dc2626;">- ₹<?= number_format((float)($itcRev['sgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: #dc2626;">
                        - ₹<?= number_format((float)($itcRev['total_tax'] ?? 0), 2) ?>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0;">
                    <td style="padding: 14px 20px; color: #0f172a;">
                        (C) Net ITC Available (4(A) - 4(B))
                    </td>
                    <td style="padding: 14px 20px; text-align: right; color: #059669;">₹<?= number_format((float)($netItc['igst'] ?? 0), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #059669;">₹<?= number_format((float)($netItc['cgst'] ?? 0), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #059669;">₹<?= number_format((float)($netItc['sgst'] ?? 0), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #059669; font-size: 14px;">
                        ₹<?= number_format((float)($netItc['total_tax'] ?? 0), 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Table 6.1: Payment of Tax -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 15px;">
            6.1 Payment of Tax (Net Tax Payable After ITC Setoff)
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 12px 20px;">Tax Head</th>
                    <th style="padding: 12px 20px; text-align: right;">Total Tax Liability</th>
                    <th style="padding: 12px 20px; text-align: right;">Paid Through ITC</th>
                    <th style="padding: 12px 20px; text-align: right;">Net Tax Paid in Cash</th>
                    <th style="padding: 12px 20px; text-align: right;">Excess ITC Carried Forward</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">Integrated Tax (IGST)</td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($outTaxable['igst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #059669;">₹<?= number_format(min((float)($outTaxable['igst'] ?? 0), (float)($netItc['igst'] ?? 0)), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: #dc2626;">₹<?= number_format((float)($netPayable['igst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #2563eb;">₹<?= number_format((float)($excessItc['igst'] ?? 0), 2) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">Central Tax (CGST)</td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($outTaxable['cgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #059669;">₹<?= number_format(min((float)($outTaxable['cgst'] ?? 0), (float)($netItc['cgst'] ?? 0)), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: #dc2626;">₹<?= number_format((float)($netPayable['cgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #2563eb;">₹<?= number_format((float)($excessItc['cgst'] ?? 0), 2) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">State/UT Tax (SGST)</td>
                    <td style="padding: 12px 20px; text-align: right;">₹<?= number_format((float)($outTaxable['sgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #059669;">₹<?= number_format(min((float)($outTaxable['sgst'] ?? 0), (float)($netItc['sgst'] ?? 0)), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: #dc2626;">₹<?= number_format((float)($netPayable['sgst'] ?? 0), 2) ?></td>
                    <td style="padding: 12px 20px; text-align: right; color: #2563eb;">₹<?= number_format((float)($excessItc['sgst'] ?? 0), 2) ?></td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0;">
                    <td style="padding: 14px 20px; color: #0f172a;">TOTAL STATUTORY OFFSETS</td>
                    <td style="padding: 14px 20px; text-align: right;">₹<?= number_format((float)($outTaxable['total_tax'] ?? 0), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #059669;">₹<?= number_format(min((float)($outTaxable['total_tax'] ?? 0), (float)($netItc['total_tax'] ?? 0)), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #dc2626; font-size: 14px;">₹<?= number_format((float)($netPayable['total'] ?? 0), 2) ?></td>
                    <td style="padding: 14px 20px; text-align: right; color: #2563eb; font-size: 14px;">₹<?= number_format((float)($excessItc['total'] ?? 0), 2) ?></td>
                </tr>
            </tbody>
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

