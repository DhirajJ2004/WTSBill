<?php
$pageTitle = 'Reports & Tax Analytics - WTS LEDGER PRO';
$currentRoute = 'reports';

$activeTab = $_GET['tab'] ?? 'gst';
if ($activeTab === 'financial') $activeTab = 'financial-pl';

require_once __DIR__ . '/../db_helper.php';
$companyId = get_current_company_id();
$financialYear = get_current_financial_year();
$invoices = get_invoices();
$expenseRows = get_expenses();
$purchaseRows = [];
$bankBalance = 0.0;
try {
    $purchaseRows = \Illuminate\Database\Capsule\Manager::table('purchases')->where('company_id', $companyId)->get()->all();
    $bankBalance = (float) \Illuminate\Database\Capsule\Manager::table('bank_accounts')->where('company_id', $companyId)->sum('current_balance');
} catch (\Throwable $e) {
    $purchaseRows = [];
}
$totalSales = array_sum(array_map(static fn($invoice) => (float)($invoice['grand_total'] ?? 0), $invoices));
$taxableTurnover = array_sum(array_map(static fn($invoice) => (float)($invoice['sub_total'] ?? 0), $invoices));
$cgstTotal = array_sum(array_map(static fn($invoice) => (float)($invoice['cgst_amount'] ?? 0), $invoices));
$sgstTotal = array_sum(array_map(static fn($invoice) => (float)($invoice['sgst_amount'] ?? 0), $invoices));
$igstTotal = array_sum(array_map(static fn($invoice) => (float)($invoice['igst_amount'] ?? 0), $invoices));
$totalTax = $cgstTotal + $sgstTotal + $igstTotal;
$totalPurchases = array_sum(array_map(static fn($purchase) => (float)($purchase->grand_total ?? 0), $purchaseRows));
$totalExpenses = array_sum(array_map(static fn($expense) => (float)($expense['amount'] ?? 0), $expenseRows));
$grossProfit = $totalSales - $totalPurchases;
$netProfit = $grossProfit - $totalExpenses;
$receivables = array_sum(array_map(static fn($invoice) => (float)($invoice['amount_due'] ?? 0), $invoices));
$payables = array_sum(array_map(static fn($purchase) => (float)($purchase->amount_due ?? $purchase->grand_total ?? 0), $purchaseRows));
$inventoryValue = 0.0;
$salesByMonth = [];
$agingByCustomer = [];
foreach ($invoices as $invoice) {
    $invoiceDate = $invoice['invoice_date'] ?? null;
    if ($invoiceDate) {
        $month = date('M Y', strtotime($invoiceDate));
        $salesByMonth[$month] = ($salesByMonth[$month] ?? 0) + (float)($invoice['grand_total'] ?? 0);
    }
    $due = (float)($invoice['amount_due'] ?? 0);
    if ($due <= 0) continue;
    $name = $invoice['customer_name'] ?? '';
    $daysLate = max(0, (int)floor((time() - strtotime($invoice['due_date'] ?? 'now')) / 86400));
    $bucket = $daysLate <= 15 ? 0 : ($daysLate <= 30 ? 1 : ($daysLate <= 60 ? 2 : 3));
    $agingByCustomer[$name][$bucket] = ($agingByCustomer[$name][$bucket] ?? 0) + $due;
}
try {
    $inventoryValue = (float) \Illuminate\Database\Capsule\Manager::table('products')
        ->where('company_id', $companyId)
        ->sum(\Illuminate\Database\Capsule\Manager::raw('current_stock * purchase_price'));
} catch (\Throwable $e) {
    $inventoryValue = 0.0;
}
$totalAssets = $bankBalance + $receivables + $inventoryValue;
$totalLiabilities = $payables;
$equity = $totalAssets - $totalLiabilities;

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <!-- Reports Header -->
        <div class="workspace-header-row">
            <div class="workspace-header-left">
                <h1 class="workspace-title">Compliance, GST &amp; Financial Analytics</h1>
                <p class="workspace-subtitle">Official GSTR-1, GSTR-3B audit summaries, and Profit &amp; Loss statements.</p>
            </div>
            <div class="workspace-header-right">
                <button class="header-filter-btn" onclick="exportGSTCSV()">
                    <i class="fa-solid fa-file-csv filter-icon"></i>
                    <span>Export GSTR-1 CSV</span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="subnav-tabs-wrapper">
            <div class="subnav-tabs" id="reportsTabsNav">
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'gst' ? 'active' : '' ?>" data-tab="gst" onclick="switchTab('reportsTabsNav', 'tab-gst-summary', event)">GST Tax Summary (GSTR-1 / 3B)</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'aging' ? 'active' : '' ?>" data-tab="aging" onclick="switchTab('reportsTabsNav', 'tab-aging-receivables', event)">Aging Receivables</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'sales' ? 'active' : '' ?>" data-tab="sales" onclick="switchTab('reportsTabsNav', 'tab-sales-service', event); renderServiceChart();">Sales &amp; Service Revenue</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'financial-pl' ? 'active' : '' ?>" data-tab="financial" onclick="switchTab('reportsTabsNav', 'tab-financial-pl', event)">Profit &amp; Loss Statement</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'balance' ? 'active' : '' ?>" data-tab="balance" onclick="switchTab('reportsTabsNav', 'tab-balance-sheet', event)">Balance Sheet</a>
            </div>
        </div>

        <div class="reports-tabs-container">
            <!-- TAB 1: GST TAX SUMMARY -->
            <div id="tab-gst-summary" class="subnav-pane <?= $activeTab === 'gst' ? 'active' : '' ?>" style="<?= $activeTab === 'gst' ? '' : 'display: none;' ?>">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Sales Tax Summary (<?= htmlspecialchars($financialYear) ?>)</h3>
                        <span class="badge-status badge-status-paid">PORTAL READY</span>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>TAX SLAB / COMPONENT</th>
                                    <th>TAXABLE TURNOVER (₹)</th>
                                    <th>CGST COLLECTED (₹)</th>
                                    <th>SGST COLLECTED (₹)</th>
                                    <th>IGST COLLECTED (₹)</th>
                                    <th>TOTAL TAX LIABILITY (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($invoices): ?>
                                    <tr>
                                        <td><strong>Recorded Invoices</strong></td>
                                        <td>₹<?= number_format($taxableTurnover, 2) ?></td>
                                        <td>₹<?= number_format($cgstTotal, 2) ?></td>
                                        <td>₹<?= number_format($sgstTotal, 2) ?></td>
                                        <td>₹<?= number_format($igstTotal, 2) ?></td>
                                        <td class="text-green font-bold">₹<?= number_format($totalTax, 2) ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No invoice tax data found in the database.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: AGING RECEIVABLES -->
            <div id="tab-aging-receivables" class="subnav-pane <?= $activeTab === 'aging' ? 'active' : '' ?>" style="<?= $activeTab === 'aging' ? '' : 'display: none;' ?>">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Customer Receivables Aging Schedule</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>CLIENT NAME</th>
                                    <th>CURRENT (0-15 DAYS)</th>
                                    <th>16 - 30 DAYS</th>
                                    <th>31 - 60 DAYS</th>
                                    <th>60+ DAYS OVERDUE</th>
                                    <th>TOTAL OUTSTANDING</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agingByCustomer as $customerName => $buckets):
                                    $buckets = array_replace([0, 0, 0, 0], $buckets);
                                    $customerTotal = array_sum($buckets);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($customerName ?: 'Unspecified customer') ?></strong></td>
                                        <?php foreach ($buckets as $bucketAmount): ?><td>₹<?= number_format($bucketAmount, 2) ?></td><?php endforeach; ?>
                                        <td class="text-coral font-bold">₹<?= number_format($customerTotal, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$agingByCustomer): ?><tr><td colspan="6" class="text-center">No outstanding invoices found in the database.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SALES & SERVICE REVENUE -->
            <div id="tab-sales-service" class="subnav-pane <?= $activeTab === 'sales' ? 'active' : '' ?>" style="<?= $activeTab === 'sales' ? '' : 'display: none;' ?>">
                <div class="white-card">
                    <h3 class="card-head-title">Revenue Distribution by Service &amp; Product Families</h3>
                    <p class="card-head-sub">Year-to-date sales composition</p>
                    <div style="height: 260px; position: relative; margin-top: 18px;">
                        <canvas id="serviceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PROFIT & LOSS -->
            <div id="tab-financial-pl" class="subnav-pane <?= $activeTab === 'financial-pl' ? 'active' : '' ?>" style="<?= $activeTab === 'financial-pl' ? '' : 'display: none;' ?>">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Profit &amp; Loss (<?= htmlspecialchars($financialYear) ?>)</h3>
                        <span class="health-badge <?= $netProfit >= 0 ? 'badge-green' : 'badge-red' ?>"><?= $netProfit >= 0 ? 'SURPLUS' : 'DEFICIT' ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>PARTICULARS</th>
                                    <th class="text-right">AMOUNT (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($invoices || $purchaseRows || $expenseRows): ?>
                                    <tr><td><strong>Recorded Invoice Sales</strong></td><td class="text-right">₹<?= number_format($totalSales, 2) ?></td></tr>
                                    <tr><td>Less: Recorded Purchases</td><td class="text-right">₹<?= number_format($totalPurchases, 2) ?></td></tr>
                                    <tr style="background: #f8fafc; font-weight: 700;"><td>GROSS PROFIT</td><td class="text-right">₹<?= number_format($grossProfit, 2) ?></td></tr>
                                    <tr><td>Less: Recorded Expenses</td><td class="text-right">₹<?= number_format($totalExpenses, 2) ?></td></tr>
                                    <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid #0f172a;"><td>NET PROFIT / (LOSS)</td><td class="text-right">₹<?= number_format($netProfit, 2) ?></td></tr>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center">No financial transactions found in the database.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: BALANCE SHEET -->
            <div id="tab-balance-sheet" class="subnav-pane <?= $activeTab === 'balance' ? 'active' : '' ?>" style="<?= $activeTab === 'balance' ? '' : 'display: none;' ?>">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Statement of Financial Position (Balance Sheet)</h3>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
                        <div style="border-right: 1px solid #e2e8f0; padding: 18px;">
                            <h4 style="font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">LIABILITIES &amp; CAPITAL</h4>
                            <div class="health-item-row"><span>Trade Payables</span><strong>₹<?= number_format($payables, 2) ?></strong></div>
                            <div class="health-item-row"><span>Net Assets / Equity</span><strong>₹<?= number_format($equity, 2) ?></strong></div>
                            <div class="health-item-row font-bold" style="border-top: 2px solid #0f172a; margin-top: 10px;"><span>TOTAL LIABILITIES &amp; EQUITY</span><strong>₹<?= number_format($totalLiabilities + $equity, 2) ?></strong></div>
                        </div>
                        <div style="padding: 18px;">
                            <h4 style="font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">ASSETS &amp; RECEIVABLES</h4>
                            <div class="health-item-row"><span>Cash &amp; Bank Balances</span><strong>₹<?= number_format($bankBalance, 2) ?></strong></div>
                            <div class="health-item-row"><span>Trade Receivables</span><strong>₹<?= number_format($receivables, 2) ?></strong></div>
                            <div class="health-item-row"><span>Inventory Value</span><strong>₹<?= number_format($inventoryValue, 2) ?></strong></div>
                            <div class="health-item-row font-bold" style="border-top: 2px solid #0f172a; margin-top: 10px;"><span>TOTAL ASSETS</span><strong>₹<?= number_format($totalAssets, 2) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Footer -->
        <div class="app-global-footer">
            Developed &amp; Maintained by <a href="<?= url('/dashboard') ?>" class="footer-wts-link">WTS ERP</a> &copy; 2026 All rights reserved.
        </div>
    </main>
</div>

<script>
function exportGSTCSV() {
    let csv = "Record Type,Taxable Turnover,CGST,SGST,IGST,Total Tax\nRecorded Invoices,<?= number_format($taxableTurnover, 2, '.', '') ?>,<?= number_format($cgstTotal, 2, '.', '') ?>,<?= number_format($sgstTotal, 2, '.', '') ?>,<?= number_format($igstTotal, 2, '.', '') ?>,<?= number_format($totalTax, 2, '.', '') ?>\n";
    let blob = new Blob([csv], { type: 'text/csv' });
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = "GSTR-1_Report.csv";
    link.click();
    showToast('Exported GSTR-1 CSV report', 'success');
}

let chartRendered = false;
function renderServiceChart() {
    if (chartRendered) return;
    const canvas = document.getElementById('serviceChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($salesByMonth), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            datasets: [{
                data: <?= json_encode(array_values($salesByMonth), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                backgroundColor: ['#2563eb', '#10b981', '#06b6d4', '#f59e0b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    chartRendered = true;
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById('tab-sales-service').style.display !== 'none') {
        renderServiceChart();
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
