<?php
$title = $title ?? 'Executive BI & Business Analytics';
$dashboard = $dashboard ?? [];
$metrics = $dashboard['metrics'] ?? [];
$topProducts = $dashboard['top_products'] ?? [];
$topCustomers = $dashboard['top_customers'] ?? [];
$trends = $dashboard['trends'] ?? [];
$branchPerf = $dashboard['branches'] ?? [];
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;

$totalRevenue = (float)($metrics['total_revenue'] ?? 0);
$grossProfit = (float)($metrics['gross_profit'] ?? 0);
$grossMarginPct = (float)($metrics['gross_margin_pct'] ?? 0);
$operatingExpenses = (float)($metrics['operating_expenses'] ?? 0);
$netProfit = (float)($metrics['net_profit'] ?? 0);
$netMarginPct = (float)($metrics['net_margin_pct'] ?? 0);

// Find max revenue for trend chart scaling
$maxTrendVal = 1000.0;
foreach ($trends as $t) {
    if ((float)$t['revenue'] > $maxTrendVal) $maxTrendVal = (float)$t['revenue'];
    if ((float)$t['expense'] > $maxTrendVal) $maxTrendVal = (float)$t['expense'];
}
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/dashboard" style="color: #64748b; text-decoration: none;">Dashboard</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Executive Business Intelligence</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span>Executive BI &amp; Analytics</span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; <?= $netProfit >= 0 ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
                    <?= $netProfit >= 0 ? 'NET PROFITABLE' : 'OPERATING DEFICIT' ?>
                </span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Executive business performance dashboard with margin analytics, revenue trends, and party volume benchmarks.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/analytics/bi/export?from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&branch_id=<?= (int)$selectedBranch ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                Print Report
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/analytics/bi" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
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
                    Update Analytics
                </button>
            </div>
        </form>
    </div>

    <!-- Executive KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Total Invoiced Revenue</div>
            <div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 6px;">₹<?= number_format($totalRevenue, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?= (int)($metrics['invoice_count'] ?? 0) ?> active tax invoices</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">Gross Profit Margin</div>
            <div style="font-size: 24px; font-weight: 700; color: #059669; margin-top: 6px;">₹<?= number_format($grossProfit, 2) ?></div>
            <div style="font-size: 12px; color: #059669; margin-top: 4px; font-weight: 600;"><?= number_format($grossMarginPct, 1) ?>% gross margin</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase;">Operating Expenses</div>
            <div style="font-size: 24px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format($operatingExpenses, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Administrative overhead</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #4338ca; text-transform: uppercase;">Net Profit / Loss</div>
            <div style="font-size: 24px; font-weight: 700; color: <?= $netProfit >= 0 ? '#15803d' : '#b91c1c' ?>; margin-top: 6px;">
                <?= $netProfit >= 0 ? '+' : '' ?>₹<?= number_format($netProfit, 2) ?>
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 600;"><?= number_format($netMarginPct, 1) ?>% net margin</div>
        </div>
    </div>

    <!-- Monthly Trend & Branch Grids -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Left: 6-Month Visual Bar Chart -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Monthly Revenue vs Expense Trends</h2>
                <div style="display: flex; gap: 16px; font-size: 12px; font-weight: 600;">
                    <span style="display: flex; align-items: center; gap: 6px; color: #2563eb;">
                        <span style="display: inline-block; width: 12px; height: 12px; background: #2563eb; border-radius: 3px;"></span> Revenue
                    </span>
                    <span style="display: flex; align-items: center; gap: 6px; color: #dc2626;">
                        <span style="display: inline-block; width: 12px; height: 12px; background: #dc2626; border-radius: 3px;"></span> Expenses
                    </span>
                </div>
            </div>

            <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 200px; padding-top: 20px; border-bottom: 1px solid #e2e8f0; gap: 12px;">
                <?php foreach ($trends as $tr): 
                    $revH = $maxTrendVal > 0 ? max(8, round(($tr['revenue'] / $maxTrendVal) * 160)) : 8;
                    $expH = $maxTrendVal > 0 ? max(8, round(($tr['expense'] / $maxTrendVal) * 160)) : 8;
                ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <div style="display: flex; align-items: flex-end; gap: 4px; height: 160px;">
                            <div title="Revenue: ₹<?= number_format($tr['revenue'], 2) ?>" style="width: 18px; height: <?= (int)$revH ?>px; background: #2563eb; border-radius: 4px 4px 0 0; transition: height 0.3s;"></div>
                            <div title="Expense: ₹<?= number_format($tr['expense'], 2) ?>" style="width: 18px; height: <?= (int)$expH ?>px; background: #f87171; border-radius: 4px 4px 0 0; transition: height 0.3s;"></div>
                        </div>
                        <span style="font-size: 11px; color: #64748b; font-weight: 600;"><?= htmlspecialchars($tr['month']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Branch Performance Grid -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0;">Branch Performance</h2>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($branchPerf as $bp): 
                    $branchSales = (float)$bp['total_sales'];
                    $bPct = $totalRevenue > 0 ? round(($branchSales / $totalRevenue) * 100) : 0;
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                            <span><?= htmlspecialchars($bp['branch_name']) ?></span>
                            <span>₹<?= number_format($branchSales, 2) ?> (<?= (int)$bPct ?>%)</span>
                        </div>
                        <div style="height: 8px; width: 100%; background: #f1f5f9; border-radius: 9999px; overflow: hidden;">
                            <div style="height: 100%; width: <?= (int)$bPct ?>%; background: #2563eb; border-radius: 9999px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top Products & Top Customers Tables -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Top Products -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 15px;">
                Top 5 Best-Selling Products
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 10px 16px;">Product / SKU</th>
                        <th style="padding: 10px 16px; text-align: right;">Units Sold</th>
                        <th style="padding: 10px 16px; text-align: right;">Sales Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" style="padding: 24px; text-align: center; color: #94a3b8;">No sales records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $tp): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($tp['item_name']) ?></div>
                                    <div style="font-size: 11px; font-family: monospace; color: #64748b;"><?= htmlspecialchars($tp['sku']) ?></div>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #475569;">
                                    <?= number_format((float)$tp['units_sold'], 0) ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #2563eb;">
                                    ₹<?= number_format((float)$tp['gross_sales'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Customers -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 15px;">
                Top 5 Customer Accounts
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 10px 16px;">Customer</th>
                        <th style="padding: 10px 16px; text-align: right;">Invoices</th>
                        <th style="padding: 10px 16px; text-align: right;">Billed Amount</th>
                        <th style="padding: 10px 16px; text-align: right;">Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topCustomers)): ?>
                        <tr><td colspan="4" style="padding: 24px; text-align: center; color: #94a3b8;">No customer records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topCustomers as $tc): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 600; color: #1e293b;">
                                        <?php if (!empty($tc['customer_id'])): ?>
                                            <a href="/customers/<?= (int)$tc['customer_id'] ?>/profile" style="color: #1e293b; text-decoration: none;">
                                                <?= htmlspecialchars($tc['customer_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($tc['customer_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($tc['city']) ?></div>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; color: #475569; font-weight: 600;">
                                    <?= (int)$tc['invoice_count'] ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #059669;">
                                    ₹<?= number_format((float)$tc['total_billed'], 2) ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= (float)$tc['total_due'] > 0 ? '#dc2626' : '#64748b' ?>;">
                                    ₹<?= number_format((float)$tc['total_due'], 2) ?>
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
@media print {
    body { background: #fff !important; }
    .content-wrapper { padding: 0 !important; }
    button, a, form { display: none !important; }
}
</style>

