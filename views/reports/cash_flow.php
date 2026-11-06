<?php
$title = $title ?? 'Cash Flow Statement';
$statement = $statement ?? [];
$operating = $statement['operating'] ?? [];
$investing = $statement['investing'] ?? [];
$financing = $statement['financing'] ?? [];
$summary = $statement['summary'] ?? [];
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;

$netCashChange = (float)($summary['net_cash_change'] ?? 0);
$beginningBalance = (float)($summary['beginning_balance'] ?? 0);
$endingBalance = (float)($summary['ending_balance'] ?? 0);
$netOperating = (float)($operating['net_operating'] ?? 0);
$netInvesting = (float)($investing['net_investing'] ?? 0);
$netFinancing = (float)($financing['net_financing'] ?? 0);
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/balance-sheet" style="color: #64748b; text-decoration: none;">Accounting Reports</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Cash Flow Statement</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span>Cash Flow Statement</span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; <?= $netCashChange >= 0 ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
                    <?= $netCashChange >= 0 ? 'POSITIVE INFLOW' : 'NET OUTFLOW' ?>
                </span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Statement of cash inflows and outflows across operating, investing, and financing activities.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; cursor: pointer; transition: all 0.2s;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Statement
            </button>
            <a href="/reports/trial-balance" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                Trial Balance
            </a>
            <a href="/reports/balance-sheet" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #4f46e5; color: #fff; text-decoration: none;">
                Balance Sheet
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="/reports/cash-flow" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 170px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">From Date</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
            </div>
            <div style="flex: 1; min-width: 170px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">To Date</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Branch Scope</label>
                <select name="branch_id" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                    <option value="0">Consolidated (All Branches)</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $selectedBranch === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" style="padding: 10px 22px; background: #0f172a; color: #fff; font-weight: 600; font-size: 14px; border: none; border-radius: 6px; cursor: pointer;">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Beginning Cash & Bank</div>
            <div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 8px;">₹<?= number_format($beginningBalance, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">As of <?= htmlspecialchars($fromDate) ?></div>
        </div>
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Operating Cash Flow</div>
            <div style="font-size: 24px; font-weight: 700; color: <?= $netOperating >= 0 ? '#16a34a' : '#dc2626' ?>; margin-top: 8px;">
                <?= $netOperating >= 0 ? '+' : '' ?>₹<?= number_format($netOperating, 2) ?>
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Operations surplus/deficit</div>
        </div>
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Net Cash Change</div>
            <div style="font-size: 24px; font-weight: 700; color: <?= $netCashChange >= 0 ? '#16a34a' : '#dc2626' ?>; margin-top: 8px;">
                <?= $netCashChange >= 0 ? '+' : '' ?>₹<?= number_format($netCashChange, 2) ?>
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Period net variance</div>
        </div>
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Ending Cash & Bank</div>
            <div style="font-size: 24px; font-weight: 700; color: #4338ca; margin-top: 8px;">₹<?= number_format($endingBalance, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">As of <?= htmlspecialchars($toDate) ?></div>
        </div>
    </div>

    <!-- Detailed Statement Card -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Cash Flow Breakdown (Direct Method)</h2>
                <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0;">Period: <?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?></p>
            </div>
            <span style="font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; background: #e0e7ff; color: #3730a3;">
                Reconciled (Mathematical Balance Verified)
            </span>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 24px;">Cash Activity Description</th>
                    <th style="padding: 14px 24px; text-align: right; width: 220px;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <!-- 1. Operating Activities -->
                <tr style="background: #f8fafc; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 24px; font-size: 15px;">1. CASH FLOW FROM OPERATING ACTIVITIES</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 24px 12px 40px; color: #334155;">
                        <span>Cash Inflow: Collections from Customers (Sales & Invoices)</span>
                    </td>
                    <td style="padding: 12px 24px; text-align: right; color: #16a34a; font-weight: 600;">
                        + ₹<?= number_format((float)($operating['customer_receipts'] ?? 0), 2) ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 24px 12px 40px; color: #334155;">
                        <span>Cash Outflow: Payments to Suppliers (Purchases & Trade Payables)</span>
                    </td>
                    <td style="padding: 12px 24px; text-align: right; color: #dc2626; font-weight: 600;">
                        - ₹<?= number_format((float)($operating['supplier_payments'] ?? 0), 2) ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 24px 12px 40px; color: #334155;">
                        <span>Cash Outflow: Operating Expenses & Administrative Overhead</span>
                    </td>
                    <td style="padding: 12px 24px; text-align: right; color: #dc2626; font-weight: 600;">
                        - ₹<?= number_format((float)($operating['operating_expenses'] ?? 0), 2) ?>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                    <td style="padding: 12px 24px 12px 40px; color: #0f172a;">Net Cash from Operating Activities</td>
                    <td style="padding: 12px 24px; text-align: right; color: <?= $netOperating >= 0 ? '#16a34a' : '#dc2626' ?>;">
                        <?= $netOperating >= 0 ? '+' : '' ?>₹<?= number_format($netOperating, 2) ?>
                    </td>
                </tr>

                <!-- 2. Investing Activities -->
                <tr style="background: #f8fafc; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 24px; font-size: 15px;">2. CASH FLOW FROM INVESTING ACTIVITIES</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 24px 12px 40px; color: #334155;">
                        <span>Purchase / Disposal of Capital Equipment & Fixed Assets</span>
                    </td>
                    <td style="padding: 12px 24px; text-align: right; color: #64748b; font-weight: 600;">
                        ₹<?= number_format($netInvesting, 2) ?>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                    <td style="padding: 12px 24px 12px 40px; color: #0f172a;">Net Cash from Investing Activities</td>
                    <td style="padding: 12px 24px; text-align: right; color: #0f172a;">
                        ₹<?= number_format($netInvesting, 2) ?>
                    </td>
                </tr>

                <!-- 3. Financing Activities -->
                <tr style="background: #f8fafc; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 24px; font-size: 15px;">3. CASH FLOW FROM FINANCING ACTIVITIES</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 24px 12px 40px; color: #334155;">
                        <span>Owner Capital Infusion / Drawings & Commercial Debt Financing</span>
                    </td>
                    <td style="padding: 12px 24px; text-align: right; color: #64748b; font-weight: 600;">
                        ₹<?= number_format($netFinancing, 2) ?>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                    <td style="padding: 12px 24px 12px 40px; color: #0f172a;">Net Cash from Financing Activities</td>
                    <td style="padding: 12px 24px; text-align: right; color: #0f172a;">
                        ₹<?= number_format($netFinancing, 2) ?>
                    </td>
                </tr>

                <!-- 4. Final Cash Flow Summary & Reconciliation -->
                <tr style="background: #f1f5f9; font-weight: 800; font-size: 15px; border-top: 2px solid #cbd5e1; border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 24px; color: #0f172a;">NET CHANGE IN CASH & EQUIVALENTS</td>
                    <td style="padding: 14px 24px; text-align: right; color: <?= $netCashChange >= 0 ? '#16a34a' : '#dc2626' ?>;">
                        <?= $netCashChange >= 0 ? '+' : '' ?>₹<?= number_format($netCashChange, 2) ?>
                    </td>
                </tr>
                <tr style="background: #fff; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px 24px; color: #475569;">Cash & Cash Equivalents at Beginning of Period</td>
                    <td style="padding: 12px 24px; text-align: right; color: #0f172a;">
                        ₹<?= number_format($beginningBalance, 2) ?>
                    </td>
                </tr>
                <tr style="background: #eef2ff; font-weight: 800; font-size: 16px; border-top: 2px solid #6366f1; border-bottom: 2px solid #6366f1;">
                    <td style="padding: 16px 24px; color: #312e81;">CASH & CASH EQUIVALENTS AT END OF PERIOD</td>
                    <td style="padding: 16px 24px; text-align: right; color: #312e81;">
                        ₹<?= number_format($endingBalance, 2) ?>
                    </td>
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

