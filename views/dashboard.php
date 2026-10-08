<?php
$pageTitle = 'Dashboard - WTS LEDGER PRO';
$currentRoute = 'dashboard';

require_once __DIR__ . '/db_helper.php';
$currentUser = get_current_user_info();
if (!empty($_GET['company_id'])) {
    ensure_company_access((int)$_GET['company_id']);
}
$currentCompany = get_current_company();
if (!empty($currentUser) && $currentCompany === null) {
    header('Location: ' . url('/select-company'));
    exit();
}

$companyId = get_current_company_id();
$currentBranch = get_current_branch();
$currentFy = get_current_financial_year();
$metrics = get_db_metrics();

// Monthly Sales & Purchases Trend Data for Charts
$salesTrend = [];
$purchaseTrend = [];
try {
    $salesTrendRows = DB::table('invoices')
        ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as month_key, SUM(grand_total) as total")
        ->where('company_id', $companyId)
        ->where('status', '!=', 'cancelled')
        ->groupBy('month_key')
        ->orderBy('month_key')
        ->limit(6)
        ->get();
    foreach ($salesTrendRows as $r) {
        $salesTrend[$r->month_key] = (float) $r->total;
    }

    $purchaseTrendRows = DB::table('purchases')
        ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') as month_key, SUM(grand_total) as total")
        ->where('company_id', $companyId)
        ->groupBy('month_key')
        ->orderBy('month_key')
        ->limit(6)
        ->get();
    foreach ($purchaseTrendRows as $r) {
        $purchaseTrend[$r->month_key] = (float) $r->total;
    }
} catch (\Throwable $e) {
}

// Generate unified 6-month timeline labels
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $mKey = date('Y-m', strtotime("-$i months"));
    $months[$mKey] = date('M Y', strtotime("-$i months"));
}
$chartLabels = array_values($months);
$chartSalesData = [];
$chartPurchasesData = [];
foreach (array_keys($months) as $mKey) {
    $chartSalesData[] = $salesTrend[$mKey] ?? 0;
    $chartPurchasesData[] = $purchaseTrend[$mKey] ?? 0;
}

// Recent Invoices (Limit 6)
$recentInvoices = [];
try {
    $recentInvoices = DB::table('invoices')
        ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
        ->where('invoices.company_id', $companyId)
        ->select('invoices.*', 'customers.name as customer_name')
        ->orderBy('invoices.id', 'desc')
        ->limit(6)
        ->get();
} catch (\Throwable $e) {
    $recentInvoices = [];
}

// Recent Payments (Limit 6)
$recentPayments = [];
try {
    $recentPayments = DB::table('payments')
        ->leftJoin('customers', function ($join) {
            $join->on('payments.party_id', '=', 'customers.id')
                 ->where('payments.party_type', '=', 'CUSTOMER');
        })
        ->where('payments.company_id', $companyId)
        ->select('payments.*', 'customers.name as party_name')
        ->orderBy('payments.id', 'desc')
        ->limit(6)
        ->get();
} catch (\Throwable $e) {
    $recentPayments = [];
}

// Low Stock Alerts (Limit 4)
$lowStockItems = [];
try {
    $lowStockItems = DB::table('products')
        ->where('company_id', $companyId)
        ->whereColumn('current_stock', '<=', 'min_stock_alert')
        ->orderBy('current_stock', 'asc')
        ->limit(4)
        ->get();
} catch (\Throwable $e) {
    $lowStockItems = [];
}

// Overdue Invoices Alerts (Limit 4)
$overdueInvoices = [];
try {
    $overdueInvoices = DB::table('invoices')
        ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
        ->where('invoices.company_id', $companyId)
        ->whereIn('invoices.status', ['overdue', 'pending', 'UNPAID'])
        ->where('invoices.due_date', '<', date('Y-m-d'))
        ->select('invoices.*', 'customers.name as customer_name')
        ->orderBy('invoices.due_date', 'asc')
        ->limit(4)
        ->get();
} catch (\Throwable $e) {
    $overdueInvoices = [];
}

$profitMargin = $metrics['total_sales'] > 0 ? ($metrics['net_profit'] / $metrics['total_sales']) * 100 : 0;

// Dynamic Greeting
$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

include __DIR__ . '/layout/header.php';
include __DIR__ . '/layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/layout/navbar.php'; ?>

    <main class="content-area dashboard-container">
        <!-- Top Header & Filter Controls -->
        <div class="workspace-header-row" style="margin-bottom: 20px;">
            <div class="workspace-header-left">
                <div class="workspace-pretitle" style="letter-spacing: 0.05em; font-size: 11px; font-weight: 700; color: var(--primary);">DASHBOARD</div>
                <h1 class="workspace-title" style="font-size: 22px; font-weight: 700; color: var(--text); margin: 2px 0;">
                    <?= $greeting ?><?= empty($currentUser['name']) ? '' : ', ' . htmlspecialchars($currentUser['name']) ?>
                </h1>
                <p class="workspace-subtitle" style="font-size: 13px; color: var(--text-muted); margin: 0;">
                    <span style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($currentCompany['name'] ?? 'WTS Company') ?></span>
                    <span style="margin: 0 6px;">•</span>
                    <span><?= htmlspecialchars($currentBranch['name'] ?? 'Main Branch') ?></span>
                    <span style="margin: 0 6px;">•</span>
                    <span class="badge badge-neutral" style="font-size: 11px; padding: 2px 6px;"><?= htmlspecialchars($currentFy) ?></span>
                </p>
            </div>
            <div class="workspace-header-right" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <div class="dropdown-wrapper">
                    <button class="btn btn-secondary btn-sm" type="button">
                        <i class="fa-regular fa-calendar text-muted"></i>
                        <span>This FY (<?= htmlspecialchars($currentFy) ?>)</span>
                    </button>
                </div>
                <div class="dropdown-wrapper">
                    <button class="btn btn-secondary btn-sm" type="button">
                        <i class="fa-solid fa-code-branch text-muted"></i>
                        <span><?= htmlspecialchars($currentBranch['name'] ?? 'All Branches') ?></span>
                    </button>
                </div>
                <button class="btn btn-secondary btn-sm" type="button" onclick="exportTableToCSV('recentInvoicesTable', 'Recent_Invoices.csv')">
                    <i class="fa-solid fa-download text-muted"></i>
                    <span>Export</span>
                </button>
                <a href="<?= url('/create-invoice') ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i>
                    <span>+ New Invoice</span>
                </a>
            </div>
        </div>

        <!-- 6 KPI Metric Cards Row (Clean, High-Density SaaS Metrics) -->
        <div class="dashboard-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
            <!-- 1. Revenue / Sales -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Revenue (Sales)</div>
                <div class="stat-card-value font-mono" style="font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 4px;">₹<?= number_format($metrics['total_sales'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <span class="badge badge-paid" style="font-size: 11px; padding: 1px 5px;"><i class="fa-solid fa-arrow-trend-up"></i> Active</span>
                    <span style="color: var(--text-muted); font-size: 11.5px;">vs prev month</span>
                </div>
            </div>

            <!-- 2. Purchases (COGS) -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Total Purchases</div>
                <div class="stat-card-value font-mono" style="font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 4px;">₹<?= number_format($metrics['total_purchases'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <span style="color: var(--text-muted); font-size: 11.5px;">Procured procurement</span>
                </div>
            </div>

            <!-- 3. Receivables -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Receivables (Due)</div>
                <div class="stat-card-value font-mono text-amber" style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">₹<?= number_format($metrics['receivables'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <span class="badge badge-pending" style="font-size: 11px; padding: 1px 5px;"><?= number_format($metrics['invoice_count']) ?> Invoices</span>
                    <span style="color: var(--text-muted); font-size: 11.5px;">Pending</span>
                </div>
            </div>

            <!-- 4. Payables -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Payables (Due)</div>
                <div class="stat-card-value font-mono" style="font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 4px;">₹<?= number_format($metrics['payables'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <span style="color: var(--text-muted); font-size: 11.5px;">Supplier Outstandings</span>
                </div>
            </div>

            <!-- 5. Net Operating Profit -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Net Profit</div>
                <div class="stat-card-value font-mono text-emerald" style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">₹<?= number_format($metrics['net_profit'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <span class="badge badge-paid" style="font-size: 11px; padding: 1px 5px;"><?= number_format($profitMargin, 1) ?>% Margin</span>
                </div>
            </div>

            <!-- 6. Inventory Asset Value -->
            <div class="stat-card" style="padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.15s ease;">
                <div class="stat-card-label" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;">Inventory Value</div>
                <div class="stat-card-value font-mono" style="font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 4px;">₹<?= number_format($metrics['inventory_value'], 2) ?></div>
                <div class="stat-card-trend" style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                    <?php if ($metrics['low_stock_count'] > 0): ?>
                        <span class="badge badge-danger" style="font-size: 11px; padding: 1px 5px;"><?= (int) $metrics['low_stock_count'] ?> Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-paid" style="font-size: 11px; padding: 1px 5px;">Optimal Stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Compact Bar -->
        <div class="white-card" style="padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);">
            <div style="font-size: 12.5px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-bolt text-primary"></i>
                <span>Quick Operational Actions:</span>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?= url('/create-invoice') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-file-invoice text-blue"></i>
                    <span>+ Invoice</span>
                </a>
                <a href="<?= url('/create-quotation') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-regular fa-file-lines text-purple"></i>
                    <span>+ Quotation</span>
                </a>
                <a href="<?= url('/purchases') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-cart-shopping text-teal"></i>
                    <span>+ Purchase</span>
                </a>
                <a href="<?= url('/payments') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-regular fa-credit-card text-emerald"></i>
                    <span>+ Record Payment</span>
                </a>
                <a href="<?= url('/expenses') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-receipt text-coral"></i>
                    <span>+ Record Expense</span>
                </a>
                <a href="<?= url('/inventory') ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-box text-amber"></i>
                    <span>+ Product</span>
                </a>
            </div>
        </div>

        <!-- Charts Row: Revenue Trend (Left) & Sales vs Purchases (Right) -->
        <div class="dashboard-charts-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 20px;">
            <!-- Primary Chart: Sales & Revenue Trend -->
            <div class="white-card" style="padding: 18px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                    <div>
                        <h2 style="font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 2px 0;">Revenue &amp; Sales Trend</h2>
                        <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">Monthly invoiced sales trajectory across recent periods</p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; font-size: 12px;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="width: 10px; height: 10px; border-radius: 50%; background: #2563eb; display: inline-block;"></span>
                            <span style="color: var(--text-secondary); font-weight: 500;">Sales (₹)</span>
                        </div>
                        <span class="badge badge-neutral font-mono" style="font-size: 11px;">MoM View</span>
                    </div>
                </div>

                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <!-- Secondary Chart: Sales vs Purchases Comparison -->
            <div class="white-card" style="padding: 18px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                    <div>
                        <h2 style="font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 2px 0;">Sales vs Purchases</h2>
                        <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">Inflow vs outflow balance</p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 11.5px;">
                        <span style="color: #2563eb; font-weight: 600;">■ Sales</span>
                        <span style="color: #94a3b8; font-weight: 600;">■ Purchases</span>
                    </div>
                </div>

                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Business Alerts & Operational Health Row -->
        <?php if (!empty($lowStockItems) || !empty($overdueInvoices)): ?>
            <div class="dashboard-alerts-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <!-- 1. Low Stock Alerts -->
                <div class="white-card" style="padding: 16px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-triangle-exclamation text-amber" style="font-size: 15px;"></i>
                            <h3 style="font-size: 13.5px; font-weight: 700; color: var(--text); margin: 0;">Low Stock Warning</h3>
                        </div>
                        <a href="<?= url('/inventory') ?>" style="font-size: 12px; font-weight: 600; color: var(--primary);">View Inventory &rarr;</a>
                    </div>
                    <?php if (!empty($lowStockItems)): ?>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($lowStockItems as $prod): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: var(--surface-secondary); border-radius: var(--radius-md); font-size: 12.5px;">
                                    <div>
                                        <span style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($prod->name) ?></span>
                                        <span style="color: var(--text-muted); font-size: 11px; margin-left: 6px;">(SKU: <?= htmlspecialchars($prod->sku ?? 'N/A') ?>)</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="badge badge-danger font-mono" style="font-size: 11px;"><?= (float)$prod->current_stock ?> in stock</span>
                                        <span style="font-size: 11px; color: var(--text-muted);">Min: <?= (float)$prod->min_stock_alert ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12.5px; color: var(--text-muted); padding: 10px 0;">All inventory stocks are within safe thresholds.</div>
                    <?php endif; ?>
                </div>

                <!-- 2. Overdue Receivables Alerts -->
                <div class="white-card" style="padding: 16px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-regular fa-clock text-coral" style="font-size: 15px;"></i>
                            <h3 style="font-size: 13.5px; font-weight: 700; color: var(--text); margin: 0;">Overdue Invoices</h3>
                        </div>
                        <a href="<?= url('/sales/invoices') ?>" style="font-size: 12px; font-weight: 600; color: var(--primary);">View Invoices &rarr;</a>
                    </div>
                    <?php if (!empty($overdueInvoices)): ?>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($overdueInvoices as $inv): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: var(--surface-secondary); border-radius: var(--radius-md); font-size: 12.5px;">
                                    <div>
                                        <span style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($inv->invoice_number) ?></span>
                                        <span style="color: var(--text-muted); font-size: 11.5px; margin-left: 6px;"><?= htmlspecialchars($inv->customer_name ?? 'Customer') ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="font-mono font-bold text-coral" style="font-size: 12px;">₹<?= number_format((float)($inv->amount_due ?? $inv->grand_total), 2) ?></span>
                                        <span class="badge badge-overdue" style="font-size: 10.5px;">Due <?= date('d M', strtotime($inv->due_date)) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12.5px; color: var(--text-muted); padding: 10px 0;">No overdue invoices detected. All client accounts in good standing.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Transactions Section: Recent Invoices & Recent Payments (Dense ERP Tables) -->
        <div class="dashboard-transactions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
            <!-- Left: Recent Sales Invoices -->
            <div class="white-card" style="padding: 18px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div>
                        <h2 style="font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 2px 0;">Recent Sales Invoices</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Latest billing and customer tax invoices</p>
                    </div>
                    <a href="<?= url('/sales/invoices') ?>" class="btn btn-secondary btn-sm" style="font-size: 12px;">View All &rarr;</a>
                </div>

                <div class="table-responsive" style="margin: 0 -20px -18px -20px;">
                    <table class="table" id="recentInvoicesTable" style="width: 100%; margin: 0;">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentInvoices)): ?>
                                <?php foreach ($recentInvoices as $inv): 
                                    $st = strtolower($inv->status ?? 'draft');
                                    $badgeClass = 'badge-draft';
                                    if ($st === 'paid') $badgeClass = 'badge-paid';
                                    elseif ($st === 'pending' || $st === 'unpaid') $badgeClass = 'badge-pending';
                                    elseif ($st === 'overdue') $badgeClass = 'badge-overdue';
                                    elseif ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('/print-invoice?id=' . $inv->id) ?>" target="_blank" style="font-weight: 600; color: var(--primary);">
                                                <?= htmlspecialchars($inv->invoice_number) ?>
                                            </a>
                                        </td>
                                        <td style="max-width: 130px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($inv->customer_name ?? 'Direct Customer') ?>
                                        </td>
                                        <td style="font-size: 12px; color: var(--text-muted);">
                                            <?= date('d M Y', strtotime($inv->invoice_date ?? 'now')) ?>
                                        </td>
                                        <td class="text-right font-mono font-bold" style="font-size: 12.5px;">
                                            ₹<?= number_format((float)($inv->grand_total ?? 0), 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $badgeClass ?>"><?= strtoupper($st) ?></span>
                                        </td>
                                        <td class="text-right">
                                            <a href="<?= url('/print-invoice?id=' . $inv->id) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Print Invoice">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 24px; color: var(--text-muted);">
                                        No sales invoices recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Recent Payments & Receipts -->
            <div class="white-card" style="padding: 18px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div>
                        <h2 style="font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 2px 0;">Recent Collections &amp; Payments</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Latest settled bank and cash transaction receipts</p>
                    </div>
                    <a href="<?= url('/payments') ?>" class="btn btn-secondary btn-sm" style="font-size: 12px;">View All &rarr;</a>
                </div>

                <div class="table-responsive" style="margin: 0 -20px -18px -20px;">
                    <table class="table" style="width: 100%; margin: 0;">
                        <thead>
                            <tr>
                                <th>Receipt / Ref</th>
                                <th>Party</th>
                                <th>Date</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Mode</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentPayments)): ?>
                                <?php foreach ($recentPayments as $pay): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--text);">
                                            <?= htmlspecialchars($pay->payment_number ?? ('REC-' . $pay->id)) ?>
                                        </td>
                                        <td style="max-width: 130px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($pay->party_name ?? 'Walk-in Client') ?>
                                        </td>
                                        <td style="font-size: 12px; color: var(--text-muted);">
                                            <?= date('d M Y', strtotime($pay->payment_date ?? 'now')) ?>
                                        </td>
                                        <td class="text-right font-mono font-bold text-emerald" style="font-size: 12.5px;">
                                            ₹<?= number_format((float)($pay->amount ?? 0), 2) ?>
                                        </td>
                                        <td class="text-center" style="font-size: 11.5px;">
                                            <span class="badge badge-neutral"><?= strtoupper($pay->payment_method ?? 'BANK') ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-paid">CLEARED</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 24px; color: var(--text-muted);">
                                        No customer payments recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
document.addEventListener("DOMContentLoaded", function () {
    const chartLabels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const chartSales = <?= json_encode($chartSalesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const chartPurchases = <?= json_encode($chartPurchasesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // 1. Primary Line / Area Chart: Revenue Trend
    const salesCtx = document.getElementById('salesTrendChart')?.getContext('2d');
    if (salesCtx && typeof Chart !== 'undefined') {
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.18)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Sales Revenue',
                    data: chartSales,
                    borderColor: '#2563eb',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#ffffff',
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function (ctx) {
                                return ' Sales: ₹' + Number(ctx.parsed.y).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 11, weight: '500' } }
                    },
                    y: {
                        min: 0,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10.5 },
                            callback: function (val) {
                                return '₹' + Number(val).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Secondary Bar Chart: Sales vs Purchases Comparison
    const compCtx = document.getElementById('comparisonChart')?.getContext('2d');
    if (compCtx && typeof Chart !== 'undefined') {
        new Chart(compCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Sales',
                        data: chartSales,
                        backgroundColor: '#2563eb',
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    },
                    {
                        label: 'Purchases',
                        data: chartPurchases,
                        backgroundColor: '#cbd5e1',
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#ffffff',
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ₹' + Number(ctx.parsed.y).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 10.5 } }
                    },
                    y: {
                        min: 0,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10 },
                            callback: function (val) {
                                return '₹' + Number(val).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>