<?php
/**
 * WTSBill ERP — Vendor Purchase Bills & Accounts Payable
 * White + Blue Professional Redesign
 *
 * @var array $purchases
 * @var array $suppliers
 * @var array $bankAccounts
 * @var array $kpis
 * @var array $totals
 * @var array $meta
 */
?>

<!-- 1. Breadcrumb -->
<div class="breadcrumb-wrap">
    <a href="/dashboard">Home</a>
    <span class="breadcrumb-separator">/</span>
    <a href="/purchases/bills">Purchases</a>
    <span class="breadcrumb-separator">/</span>
    <span style="color: var(--text-main); font-weight: 500;">Bills</span>
</div>

<!-- 2. Page Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-main); letter-spacing: -0.02em;">Purchase Bills</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-muted);">
            Vendor invoices, ITC reconciliation, due dates and supplier payments
        </p>
    </div>
    <div class="page-actions" style="display: flex; gap: 0.75rem; align-items: center;">
        <!-- Export Dropdown -->
        <div style="position: relative; display: inline-block;">
            <button type="button" class="btn btn-secondary" onclick="window.toggleExportDropdown(event)" style="border-radius: 8px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export ▾
            </button>
            <div id="export-dropdown-menu" style="display: none; position: absolute; right: 0; top: 105%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 12px rgba(15,23,42,0.08); z-index: 100; min-width: 150px; padding: 0.35rem 0;">
                <a href="#" onclick="window.triggerExport('csv'); return false;" style="display: block; padding: 0.5rem 1rem; color: var(--text-main); text-decoration: none; font-size: 0.8125rem;">Export as CSV</a>
                <a href="#" onclick="window.triggerExport('excel'); return false;" style="display: block; padding: 0.5rem 1rem; color: var(--text-main); text-decoration: none; font-size: 0.8125rem;">Export as Excel</a>
                <a href="#" onclick="window.printBills(); return false;" style="display: block; padding: 0.5rem 1rem; color: var(--text-main); text-decoration: none; font-size: 0.8125rem;">Print / Save PDF</a>
            </div>
        </div>

        <!-- Primary Action -->
        <button type="button" class="btn btn-primary" onclick="window.openPurchaseModal()" style="border-radius: 8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            + Record Purchase Bill
        </button>
    </div>
</div>

<!-- 3. KPI Cards (4 White Cards with Blue Accent Icon) -->
<div class="kpi-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- 1. Total Payable -->
    <div class="card kpi-card" onclick="window.applyKpiFilter('UNPAID')" style="cursor: pointer; padding: 1.25rem; transition: transform 0.15s ease, box-shadow 0.15s ease; border-left: 4px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Total Payable</div>
                <div id="kpi-total-payable" class="tabular-nums" style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-top: 0.4rem;">
                    <?= format_currency((float)($kpis['total_payable'] ?? 0)) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">All unpaid & partial balances</div>
            </div>
            <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary-soft); display: flex; align-items: center; justify-content: center; color: var(--primary); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
            </div>
        </div>
    </div>

    <!-- 2. Overdue -->
    <div class="card kpi-card" onclick="window.applyKpiFilter('OVERDUE')" style="cursor: pointer; padding: 1.25rem; transition: transform 0.15s ease, box-shadow 0.15s ease; border-left: 4px solid var(--danger);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Overdue</div>
                <div id="kpi-overdue" class="tabular-nums" style="font-size: 1.5rem; font-weight: 700; color: var(--danger); margin-top: 0.4rem;">
                    <?= format_currency((float)($kpis['overdue_amount'] ?? 0)) ?>
                </div>
                <div id="kpi-overdue-count" style="font-size: 0.75rem; color: var(--danger); margin-top: 0.25rem; font-weight: 600;">
                    <?= (int)($kpis['overdue_count'] ?? 0) ?> <?= (int)($kpis['overdue_count'] ?? 0) === 1 ? 'bill' : 'bills' ?> overdue
                </div>
            </div>
            <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--danger-soft); display: flex; align-items: center; justify-content: center; color: var(--danger); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
        </div>
    </div>

    <!-- 3. Due in Next 7 Days -->
    <div class="card kpi-card" onclick="window.applyKpiFilter('DUE7')" style="cursor: pointer; padding: 1.25rem; transition: transform 0.15s ease, box-shadow 0.15s ease; border-left: 4px solid var(--warning);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Due in Next 7 Days</div>
                <div id="kpi-due7" class="tabular-nums" style="font-size: 1.5rem; font-weight: 700; color: var(--warning); margin-top: 0.4rem;">
                    <?= format_currency((float)($kpis['due_7_days_amount'] ?? 0)) ?>
                </div>
                <div id="kpi-due7-count" style="font-size: 0.75rem; color: var(--warning); margin-top: 0.25rem; font-weight: 600;">
                    <?= (int)($kpis['due_7_days_count'] ?? 0) ?> <?= (int)($kpis['due_7_days_count'] ?? 0) === 1 ? 'bill' : 'bills' ?> due soon
                </div>
            </div>
            <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--warning-soft); display: flex; align-items: center; justify-content: center; color: var(--warning); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>
    </div>

    <!-- 4. Paid This Month -->
    <div class="card kpi-card" onclick="window.applyKpiFilter('PAID')" style="cursor: pointer; padding: 1.25rem; transition: transform 0.15s ease, box-shadow 0.15s ease; border-left: 4px solid var(--success);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Paid This Month</div>
                <div id="kpi-paid-month" class="tabular-nums" style="font-size: 1.5rem; font-weight: 700; color: var(--success); margin-top: 0.4rem;">
                    <?= format_currency((float)($kpis['paid_this_month'] ?? 0)) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Disbursements recorded</div>
            </div>
            <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--success-soft); display: flex; align-items: center; justify-content: center; color: var(--success); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- 4. Filter Bar (Above Table) -->
<div class="card filter-bar" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between;">
        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; flex: 1;">
            <!-- Search -->
            <div style="position: relative; min-width: 220px; flex: 1; max-width: 320px;">
                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" id="filter-search" class="form-control" placeholder="Search bill #, vendor invoice, supplier..." style="padding-left: 2.2rem; width: 100%;">
            </div>

            <!-- Supplier Filter -->
            <select id="filter-supplier" class="form-select" style="min-width: 180px;">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers ?? [] as $s): ?>
                    <option value="<?= e((string)$s['id']) ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Status Filter -->
            <select id="filter-status" class="form-select" style="min-width: 150px;">
                <option value="ALL">All Statuses</option>
                <option value="UNPAID">Unpaid</option>
                <option value="PARTIALLY PAID">Partially Paid</option>
                <option value="PAID">Paid</option>
                <option value="OVERDUE">Overdue</option>
            </select>

            <!-- Date Range -->
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <input type="date" id="filter-from" class="form-control" title="From Date" style="width: 140px; font-size: 0.8125rem;">
                <span style="color: var(--text-muted); font-size: 0.8125rem;">to</span>
                <input type="date" id="filter-to" class="form-control" title="To Date" style="width: 140px; font-size: 0.8125rem;">
            </div>
        </div>

        <div>
            <button type="button" class="btn btn-light" onclick="window.clearFilters()" style="border-radius: 8px; font-size: 0.8125rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Clear Filters
            </button>
        </div>
    </div>
</div>

<!-- Bulk Actions Floating Bar (Hidden by default) -->
<div id="bulk-bar" class="card" style="display: none; padding: 0.75rem 1.25rem; margin-bottom: 1rem; background: var(--primary-soft); border-color: rgba(29, 78, 216, 0.2); align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-weight: 600; color: var(--primary); font-size: 0.875rem;">
            <span id="selected-count">0</span> bills selected
        </span>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-light" onclick="window.bulkExport()" style="font-size: 0.8125rem;">Export Selected</button>
        <button type="button" class="btn btn-primary" onclick="window.bulkMarkPaid()" style="font-size: 0.8125rem;">Record Bulk Payment</button>
    </div>
</div>

<!-- Inline Error Banner -->
<div id="bills-error-banner" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.85rem 1.25rem; border-radius: 8px; background: var(--danger-soft); border: 1px solid var(--danger); color: var(--danger); align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span id="bills-error-text">Failed to fetch purchase bills.</span>
    </div>
    <button type="button" class="btn btn-secondary" onclick="window.fetchBills()" style="padding: 0.25rem 0.65rem; font-size: 0.75rem;">Retry</button>
</div>

<!-- 5. Main Bills Table Card -->
<div class="card" style="margin-bottom: 1.5rem; overflow: hidden;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card);">
        <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-main);">
            Inward Vendor Bills (<span id="bills-count"><?= (int)($meta['total_items'] ?? count($purchases ?? [])) ?></span> Bills)
        </h3>
        <div style="font-size: 0.8125rem; color: var(--text-muted);" id="pagination-top-summary">
            Showing <?= (int)($meta['from'] ?? 1) ?>-<?= (int)($meta['to'] ?? count($purchases ?? [])) ?> of <?= (int)($meta['total_items'] ?? count($purchases ?? [])) ?> bills
        </div>
    </div>

    <div class="table-container" style="border: none; border-radius: 0; position: relative;">
        <div style="overflow-x: auto; max-width: 100%;">
            <table class="table" id="purchase-table">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="check-all" onclick="window.toggleSelectAll(this)" style="cursor: pointer;">
                        </th>
                        <th class="sortable" onclick="window.setSort('bill_number')" style="cursor: pointer; position: sticky; left: 0; z-index: 2; background: var(--primary-soft);">
                            Bill # <span class="sort-icon" id="sort-bill_number"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('supplier')" style="cursor: pointer;">
                            Supplier / Vendor <span class="sort-icon" id="sort-supplier"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('vendor_invoice')" style="cursor: pointer;">
                            Vendor Invoice # <span class="sort-icon" id="sort-vendor_invoice"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('bill_date')" style="cursor: pointer;">
                            Bill Date <span class="sort-icon" id="sort-bill_date"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('due_date')" style="cursor: pointer;">
                            Due Date <span class="sort-icon" id="sort-due_date"></span>
                        </th>
                        <th>Due In</th>
                        <th class="sortable" onclick="window.setSort('total')" style="cursor: pointer; text-align: right;">
                            Total Amount <span class="sort-icon" id="sort-total"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('paid')" style="cursor: pointer; text-align: right;">
                            Paid Amount <span class="sort-icon" id="sort-paid"></span>
                        </th>
                        <th class="sortable" onclick="window.setSort('balance')" style="cursor: pointer; text-align: right;">
                            Balance Due <span class="sort-icon" id="sort-balance"></span>
                        </th>
                        <th style="text-align: center;">Payment Status</th>
                        <th class="action-col" style="text-align: right; width: 60px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="purchase-table-body">
                    <?php if (!empty($purchases)): ?>
                        <?php foreach ($purchases as $p): ?>
                            <?php
                                $total = (float)$p['grand_total'];
                                $paid = (float)($p['amount_paid'] ?? 0);
                                $balance = (float)($p['balance_due'] ?? ($total - $paid));
                                $status = $p['computed_status'] ?? 'UNPAID';
                                $badgeClass = match($status) {
                                    'PAID' => 'badge-paid',
                                    'PARTIALLY PAID' => 'badge-partial',
                                    'OVERDUE' => 'badge-overdue',
                                    default => 'badge-unpaid'
                                };
                            ?>
                            <tr data-id="<?= e((string)$p['id']) ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="row-checkbox" value="<?= e((string)$p['id']) ?>" onchange="window.updateBulkSelection()" style="cursor: pointer;">
                                </td>
                                <td style="position: sticky; left: 0; z-index: 1; background: var(--bg-card);">
                                    <a href="#" onclick="window.openBillDrawer(<?= (int)$p['id'] ?>); return false;" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                        <?= e($p['purchase_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="font-weight: 500; color: var(--text-main);"><?= e($p['supplier_name']) ?></span>
                                </td>
                                <td>
                                    <code style="background: var(--border-light); padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.8125rem;"><?= e($p['vendor_invoice_number'] ?: '—') ?></code>
                                </td>
                                <td><?= e(date('d-m-Y', strtotime($p['purchase_date']))) ?></td>
                                <td><?= e(date('d-m-Y', strtotime($p['due_date']))) ?></td>
                                <td>
                                    <span class="<?= e($p['due_in_class'] ?? 'text-muted') ?>" style="font-size: 0.8125rem;">
                                        <?= e($p['due_in_text'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="amount tabular-nums" style="text-align: right; font-weight: 600;">
                                    <?= format_currency($total) ?>
                                </td>
                                <td class="amount tabular-nums" style="text-align: right; color: var(--success); font-weight: 500;">
                                    <?= format_currency($paid) ?>
                                </td>
                                <td class="amount tabular-nums" style="text-align: right; color: <?= $balance > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>; font-weight: 600;">
                                    <?= format_currency($balance) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-pill <?= $badgeClass ?>">
                                        <?= e($status) ?>
                                    </span>
                                </td>
                                <td class="action-col" style="text-align: right;">
                                    <div style="position: relative; display: inline-block;">
                                        <button type="button" class="btn btn-light" onclick="window.toggleRowMenu(event, <?= (int)$p['id'] ?>)" style="padding: 0.25rem 0.45rem; border-radius: 6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="1"></circle>
                                                <circle cx="12" cy="5" r="1"></circle>
                                                <circle cx="12" cy="19" r="1"></circle>
                                            </svg>
                                        </button>
                                        <div id="row-menu-<?= (int)$p['id'] ?>" class="row-menu-dropdown" style="display: none; position: absolute; right: 0; top: 100%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 14px rgba(15,23,42,0.1); z-index: 50; min-width: 170px; padding: 0.35rem 0; text-align: left;">
                                            <a href="#" onclick="window.openBillDrawer(<?= (int)$p['id'] ?>); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">View Details</a>
                                            <?php if ($balance > 0.001): ?>
                                                <a href="#" onclick="window.openPaymentModal(<?= (int)$p['id'] ?>, '<?= e($p['purchase_number']) ?>', <?= $balance ?>); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--primary); font-size: 0.8125rem; text-decoration: none; font-weight: 500;">Record Payment</a>
                                            <?php endif; ?>
                                            <a href="#" onclick="window.duplicateBill(<?= (int)$p['id'] ?>); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">Duplicate</a>
                                            <a href="#" onclick="window.printSingleBill(<?= (int)$p['id'] ?>); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">Download PDF</a>
                                            <div style="height: 1px; background: var(--border); margin: 0.25rem 0;"></div>
                                            <a href="#" onclick="window.deleteBill(<?= (int)$p['id'] ?>, <?= !empty($p['can_delete']) ? 'true' : 'false' ?>); return false;" style="display: block; padding: 0.45rem 1rem; color: <?= !empty($p['can_delete']) ? 'var(--danger)' : '#94a3b8' ?>; font-size: 0.8125rem; text-decoration: none; <?= empty($p['can_delete']) ? 'pointer-events: none; opacity: 0.5;' : '' ?>">Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Friendly Empty State -->
                        <tr id="no-bills-row">
                            <td colspan="12" style="text-align: center; padding: 3.5rem 1rem;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;">
                                    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-soft); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                    </div>
                                    <h4 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-main);">No purchase bills found</h4>
                                    <p style="margin: 0; font-size: 0.875rem; color: var(--text-muted); max-width: 380px;">
                                        There are no inward vendor bills matching your filters. Record your first bill to track ITC and payables.
                                    </p>
                                    <button type="button" class="btn btn-primary" onclick="window.openPurchaseModal()" style="margin-top: 0.5rem;">
                                        + Record Purchase Bill
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <!-- Totals Sticky/Footer Row -->
                <tfoot id="table-totals-foot">
                    <tr>
                        <td colspan="7" style="text-align: right; font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                            Filtered Totals:
                        </td>
                        <td id="foot-total-amount" class="amount tabular-nums" style="text-align: right; font-weight: 700;">
                            <?= format_currency((float)($totals['total_amount'] ?? 0)) ?>
                        </td>
                        <td id="foot-total-paid" class="amount tabular-nums" style="text-align: right; font-weight: 700; color: var(--success);">
                            <?= format_currency((float)($totals['total_paid'] ?? 0)) ?>
                        </td>
                        <td id="foot-total-balance" class="amount tabular-nums" style="text-align: right; font-weight: 700; color: var(--danger);">
                            <?= format_currency((float)($totals['total_balance'] ?? 0)) ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Server Pagination Controls -->
    <div class="pagination-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; border-top: 1px solid var(--border); background: var(--bg-card); flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
            <span>Rows per page:</span>
            <select id="pagination-per-page" class="form-select" onchange="window.changePerPage(this.value)" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8125rem;">
                <option value="25" <?= ((int)($meta['per_page'] ?? 25) === 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ((int)($meta['per_page'] ?? 25) === 50) ? 'selected' : '' ?>>50</option>
                <option value="100" <?= ((int)($meta['per_page'] ?? 25) === 100) ? 'selected' : '' ?>>100</option>
            </select>
            <span id="pagination-summary">
                Showing <?= (int)($meta['from'] ?? 1) ?>-<?= (int)($meta['to'] ?? count($purchases ?? [])) ?> of <?= (int)($meta['total_items'] ?? count($purchases ?? [])) ?> bills
            </span>
        </div>
        <div style="display: flex; gap: 0.35rem;" id="pagination-buttons">
            <button type="button" class="btn btn-light" id="btn-prev-page" onclick="window.changePage(currentPage - 1)" style="padding: 0.3rem 0.65rem; font-size: 0.8125rem;" <?= ((int)($meta['current_page'] ?? 1) <= 1) ? 'disabled' : '' ?>>
                &laquo; Prev
            </button>
            <span id="current-page-display" style="display: inline-flex; align-items: center; padding: 0 0.5rem; font-size: 0.8125rem; font-weight: 600; color: var(--text-main);">
                Page <?= (int)($meta['current_page'] ?? 1) ?> of <?= max(1, (int)($meta['total_pages'] ?? 1)) ?>
            </span>
            <button type="button" class="btn btn-light" id="btn-next-page" onclick="window.changePage(currentPage + 1)" style="padding: 0.3rem 0.65rem; font-size: 0.8125rem;" <?= ((int)($meta['current_page'] ?? 1) >= (int)($meta['total_pages'] ?? 1)) ? 'disabled' : '' ?>>
                Next &raquo;
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     6. RECORD PAYMENT MODAL (Double-Entry GL integration)
     ======================================================== -->
<div id="payment-modal" class="modal-backdrop-custom" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
    <div class="card" style="width: 500px; max-width: 92vw; margin: 4rem auto; padding: 0; box-shadow: 0 12px 32px rgba(15,23,42,0.18);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 id="payment-modal-title" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--text-main);">Record Vendor Payment</h3>
                <div id="payment-bill-ref" style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.15rem;">Bill: PB-0000</div>
            </div>
            <button type="button" class="modal-close" onclick="window.closePaymentModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form id="payment-form" onsubmit="window.savePayment(event)">
            <input type="hidden" id="pay-bill-id" name="bill_id" value="">
            <input type="hidden" id="pay-current-balance" value="0">

            <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div id="payment-modal-error" class="alert alert-danger" style="display: none; padding: 0.65rem 0.85rem; font-size: 0.8125rem; border-radius: 6px; background: var(--danger-soft); border: 1px solid var(--danger); color: var(--danger);"></div>

                <!-- Current Balance Chip -->
                <div style="background: var(--primary-soft); padding: 0.75rem 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.8125rem; color: var(--primary); font-weight: 500;">Current Balance Due:</span>
                    <span id="pay-balance-display" class="tabular-nums" style="font-weight: 700; color: var(--primary); font-size: 1rem;">₹0.00</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="pay-date" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Payment Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="pay-date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="pay-amount" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Amount (₹) <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="pay-amount" name="amount" class="form-control tabular-nums" step="0.01" min="0.01" oninput="window.calcLiveBalance()" required>
                    </div>
                </div>

                <!-- Live Remaining Balance Indicator -->
                <div style="font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; padding: 0 0.25rem;">
                    <span style="color: var(--text-muted);">Remaining after payment:</span>
                    <span id="pay-live-remaining" class="tabular-nums" style="font-weight: 600; color: var(--text-main);">₹0.00</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="pay-mode" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Payment Mode <span style="color: var(--danger);">*</span></label>
                        <select id="pay-mode" name="payment_mode" class="form-select" required>
                            <option value="BANK">Bank Transfer (NEFT/RTGS)</option>
                            <option value="UPI">UPI / QR</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="CASH">Cash in Hand</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pay-bank" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Bank Account</label>
                        <select id="pay-bank" name="bank_account_id" class="form-select">
                            <option value="">-- Main Bank Account --</option>
                            <?php foreach ($bankAccounts ?? [] as $ba): ?>
                                <option value="<?= e((string)$ba['id']) ?>">
                                    <?= e($ba['bank_name']) ?> (<?= e($ba['account_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pay-ref" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Reference / UTR / Cheque #</label>
                    <input type="text" id="pay-ref" name="reference_no" class="form-control" placeholder="e.g. UTR199842890 or Cheque #49281">
                </div>

                <div class="form-group">
                    <label for="pay-notes" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Notes</label>
                    <textarea id="pay-notes" name="notes" class="form-control" rows="2" placeholder="Disbursement remarks, authorization details"></textarea>
                </div>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.5rem; background: var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="window.closePaymentModal()">Cancel</button>
                <button type="submit" id="pay-submit-btn" class="btn btn-primary">Confirm & Post Journal</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     7. BILL DETAIL SLIDE-OVER DRAWER
     ======================================================== -->
<div id="bill-drawer-backdrop" class="modal-backdrop-custom" onclick="window.closeBillDrawer()"></div>
<div id="bill-drawer" class="slide-drawer">
    <!-- Drawer Header -->
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <h3 id="drawer-bill-num" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main);">PB-0000</h3>
                <span id="drawer-bill-status" class="badge-pill badge-unpaid">UNPAID</span>
            </div>
            <div id="drawer-supplier-name" style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">Vendor Name</div>
        </div>
        <button type="button" onclick="window.closeBillDrawer()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
    </div>

    <!-- Drawer Body -->
    <div style="padding: 1.5rem; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Meta Details Box -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: var(--primary-soft); padding: 1rem; border-radius: 8px;">
            <div>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Vendor Invoice #</span>
                <strong id="drawer-vendor-inv" style="font-size: 0.875rem; color: var(--text-main);">—</strong>
            </div>
            <div>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">GSTIN</span>
                <strong id="drawer-gstin" style="font-size: 0.875rem; color: var(--text-main);">—</strong>
            </div>
            <div>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Bill Date</span>
                <strong id="drawer-bill-date" style="font-size: 0.875rem; color: var(--text-main);">—</strong>
            </div>
            <div>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Due Date</span>
                <strong id="drawer-due-date" style="font-size: 0.875rem; color: var(--text-main);">—</strong>
            </div>
        </div>

        <!-- Line Items Section -->
        <div>
            <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-main); margin: 0 0 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">Line Items</h4>
            <div class="table-container" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                <table class="table" style="font-size: 0.8125rem;">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th style="text-align: right;">Qty</th>
                            <th style="text-align: right;">Rate</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="drawer-items-tbody">
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1rem;">No item rows.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tax & Reconciliation Summary -->
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
            <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-main); margin: 0 0 0.75rem; text-transform: uppercase; letter-spacing: 0.04em;">Taxes & ITC Reconciliation</h4>
            <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8125rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Taxable Sub-Total:</span>
                    <strong id="drawer-subtotal" class="tabular-nums">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">CGST + SGST (or IGST):</span>
                    <strong id="drawer-taxes" class="tabular-nums">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Input Tax Credit (ITC) Status:</span>
                    <span class="badge-pill badge-paid" style="font-size: 0.65rem;">ELIGIBLE (GSTR-2B MATCH)</span>
                </div>
                <div style="border-top: 1px solid var(--border); margin-top: 0.4rem; padding-top: 0.4rem; display: flex; justify-content: space-between; font-size: 0.9375rem;">
                    <span style="font-weight: 700; color: var(--text-main);">Grand Total:</span>
                    <strong id="drawer-grand-total" class="tabular-nums" style="color: var(--primary);">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                    <span style="color: var(--text-muted);">Balance Due:</span>
                    <strong id="drawer-balance-due" class="tabular-nums" style="color: var(--danger);">₹0.00</strong>
                </div>
            </div>
        </div>

        <!-- Payment History Section -->
        <div>
            <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-main); margin: 0 0 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">Disbursement History</h4>
            <div id="drawer-payments-wrap" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div style="color: var(--text-muted); font-size: 0.8125rem;">No payment records found.</div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.5rem; background: var(--bg-card);">
        <button type="button" class="btn btn-secondary" onclick="window.printSingleBill(activeDrawerBillId)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print
        </button>
        <button type="button" id="drawer-pay-btn" class="btn btn-primary" onclick="window.payFromDrawer()">
            Record Payment
        </button>
    </div>
</div>

<!-- ========================================================
     8. RECORD PURCHASE BILL MODAL (Inward Bill Capture)
     ======================================================== -->
<div id="purchase-modal" class="modal-backdrop-custom" role="dialog" aria-modal="true" aria-labelledby="purchase-modal-title">
    <div class="card" style="width: 580px; max-width: 92vw; margin: 3rem auto; padding: 0; box-shadow: 0 12px 32px rgba(15,23,42,0.18);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="purchase-modal-title" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--text-main);">Record Vendor Purchase Bill</h3>
            <button type="button" class="modal-close" onclick="window.closePurchaseModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form id="purchase-form" onsubmit="window.savePurchaseBill(event)">
            <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div id="purchase-modal-error" class="alert alert-danger" style="display: none; padding: 0.65rem 0.85rem; font-size: 0.8125rem; border-radius: 6px; background: var(--danger-soft); border: 1px solid var(--danger); color: var(--danger);"></div>

                <div class="form-group">
                    <label for="bill-supplier" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Supplier / Vendor <span style="color: var(--danger);">*</span></label>
                    <select id="bill-supplier" name="supplier_id" class="form-select" required autofocus>
                        <option value="">-- Select Vendor --</option>
                        <?php foreach ($suppliers ?? [] as $s): ?>
                            <option value="<?= e((string)$s['id']) ?>">
                                <?= e($s['name']) ?> <?= !empty($s['company_name']) ? ('(' . e($s['company_name']) . ')') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="bill-vendor-inv" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Vendor Invoice # <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="bill-vendor-inv" name="vendor_invoice_number" class="form-control" placeholder="e.g. ACME/24/881" required>
                    </div>

                    <div class="form-group">
                        <label for="bill-amount" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Total Bill Amount (₹) <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="bill-amount" name="amount" class="form-control tabular-nums" placeholder="0.00" step="0.01" min="1" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="bill-date" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Bill Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="bill-date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="bill-due" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Due Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="bill-due" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bill-notes" style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem; display: block;">Goods Received / Inward Notes</label>
                    <textarea id="bill-notes" name="notes" class="form-control" rows="2" placeholder="Material receipt details, PO references, and gate pass info"></textarea>
                </div>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.5rem; background: var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="window.closePurchaseModal()">Cancel</button>
                <button type="submit" id="purchase-submit-btn" class="btn btn-primary">Save Purchase Bill</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     CLIENT-SIDE JAVASCRIPT CONTROLLER
     ======================================================== -->
<script>
    let currentPage = <?= (int)($meta['current_page'] ?? 1) ?>;
    let totalPages = <?= max(1, (int)($meta['total_pages'] ?? 1)) ?>;
    let currentSort = 'id';
    let currentDir = 'desc';
    let activeDrawerBillId = null;
    let debounceTimer = null;
    let selectedBills = new Set();

    // Indian Currency Formatter (₹1,77,885.00)
    function formatINR(val) {
        const num = parseFloat(val) || 0;
        return '₹' + num.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Format Date DD-MM-YYYY
    function formatDateIN(dateStr) {
        if (!dateStr) return '—';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '-' + parts[1] + '-' + parts[0];
        }
        return dateStr;
    }

    // Sync Filter state to URL Query Parameters
    function syncUrlParams() {
        const params = new URLSearchParams();
        const search = document.getElementById('filter-search').value.trim();
        const sup = document.getElementById('filter-supplier').value;
        const status = document.getElementById('filter-status').value;
        const from = document.getElementById('filter-from').value;
        const to = document.getElementById('filter-to').value;
        const perPage = document.getElementById('pagination-per-page').value;

        if (search) params.set('search', search);
        if (sup) params.set('supplier_id', sup);
        if (status && status !== 'ALL') params.set('status', status);
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        if (currentSort !== 'id') params.set('sort', currentSort);
        if (currentDir !== 'desc') params.set('dir', currentDir);
        if (currentPage > 1) params.set('page', currentPage);
        if (perPage !== '25') params.set('per_page', perPage);

        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }

    // Debounced AJAX fetch
    function triggerFetchDebounced() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            fetchBills();
        }, 300);
    }

    // Render skeleton rows during loading
    function renderSkeletons() {
        const tbody = document.getElementById('purchase-table-body');
        let html = '';
        for (let i = 0; i < 5; i++) {
            html += `<tr>
                <td style="text-align: center;"><div class="skeleton-box" style="width: 16px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 100px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 140px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 90px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 75px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 75px; height: 16px;"></div></td>
                <td><div class="skeleton-box" style="width: 80px; height: 16px;"></div></td>
                <td style="text-align: right;"><div class="skeleton-box" style="width: 70px; height: 16px;"></div></td>
                <td style="text-align: right;"><div class="skeleton-box" style="width: 70px; height: 16px;"></div></td>
                <td style="text-align: right;"><div class="skeleton-box" style="width: 70px; height: 16px;"></div></td>
                <td style="text-align: center;"><div class="skeleton-box" style="width: 80px; height: 20px; border-radius: 999px;"></div></td>
                <td><div class="skeleton-box" style="width: 24px; height: 24px;"></div></td>
            </tr>`;
        }
        tbody.innerHTML = html;
    }

    // Main AJAX fetch
    async function fetchBills() {
        syncUrlParams();
        renderSkeletons();
        document.getElementById('bills-error-banner').style.display = 'none';

        const search = document.getElementById('filter-search').value.trim();
        const sup = document.getElementById('filter-supplier').value;
        const status = document.getElementById('filter-status').value;
        const from = document.getElementById('filter-from').value;
        const to = document.getElementById('filter-to').value;
        const perPage = document.getElementById('pagination-per-page').value;

        const params = new URLSearchParams({
            search: search,
            supplier_id: sup,
            status: status,
            from: from,
            to: to,
            sort: currentSort,
            dir: currentDir,
            page: currentPage,
            per_page: perPage
        });

        try {
            const resp = await fetch('/api/purchases/bills?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) throw new Error('Server returned HTTP ' + resp.status);

            const res = await resp.json();
            if (!res.success) throw new Error(res.message || 'Error fetching bills');

            renderTableRows(res.data);
            updateTotals(res.totals);
            updateKpis(res.kpis);
            updatePagination(res.meta);
        } catch (err) {
            console.error('Fetch bills error:', err);
            document.getElementById('bills-error-banner').style.display = 'flex';
            document.getElementById('bills-error-text').textContent = 'Failed to load purchase bills: ' + err.message;
        }
    }

    function renderTableRows(rows) {
        const tbody = document.getElementById('purchase-table-body');
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `<tr>
                <td colspan="12" style="text-align: center; padding: 3.5rem 1rem;">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-soft); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-main);">No purchase bills found</h4>
                        <p style="margin: 0; font-size: 0.875rem; color: var(--text-muted); max-width: 380px;">
                            There are no inward vendor bills matching your filters.
                        </p>
                        <button type="button" class="btn btn-primary" onclick="window.openPurchaseModal()" style="margin-top: 0.5rem;">
                            + Record Purchase Bill
                        </button>
                    </div>
                </td>
            </tr>`;
            return;
        }

        let html = '';
        rows.forEach(p => {
            const total = parseFloat(p.grand_total) || 0;
            const paid = parseFloat(p.amount_paid) || 0;
            const bal = parseFloat(p.balance_due) || 0;
            const status = p.computed_status || 'UNPAID';
            let badgeClass = 'badge-unpaid';
            if (status === 'PAID') badgeClass = 'badge-paid';
            else if (status === 'PARTIALLY PAID') badgeClass = 'badge-partial';
            else if (status === 'OVERDUE') badgeClass = 'badge-overdue';

            const isChecked = selectedBills.has(String(p.id)) ? 'checked' : '';

            html += `<tr data-id="${p.id}">
                <td style="text-align: center;">
                    <input type="checkbox" class="row-checkbox" value="${p.id}" ${isChecked} onchange="window.updateBulkSelection()" style="cursor: pointer;">
                </td>
                <td style="position: sticky; left: 0; z-index: 1; background: var(--bg-card);">
                    <a href="#" onclick="window.openBillDrawer(${p.id}); return false;" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        ${p.purchase_number}
                    </a>
                </td>
                <td><span style="font-weight: 500; color: var(--text-main);">${p.supplier_name}</span></td>
                <td><code style="background: var(--border-light); padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.8125rem;">${p.vendor_invoice_number || '—'}</code></td>
                <td>${formatDateIN(p.purchase_date)}</td>
                <td>${formatDateIN(p.due_date)}</td>
                <td><span class="${p.due_in_class || 'text-muted'}" style="font-size: 0.8125rem;">${p.due_in_text || '—'}</span></td>
                <td class="amount tabular-nums" style="text-align: right; font-weight: 600;">${formatINR(total)}</td>
                <td class="amount tabular-nums" style="text-align: right; color: var(--success); font-weight: 500;">${formatINR(paid)}</td>
                <td class="amount tabular-nums" style="text-align: right; color: ${bal > 0 ? 'var(--danger)' : 'var(--text-muted)'}; font-weight: 600;">${formatINR(bal)}</td>
                <td style="text-align: center;">
                    <span class="badge-pill ${badgeClass}">${status}</span>
                </td>
                <td class="action-col" style="text-align: right;">
                    <div style="position: relative; display: inline-block;">
                        <button type="button" class="btn btn-light" onclick="window.toggleRowMenu(event, ${p.id})" style="padding: 0.25rem 0.45rem; border-radius: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"></circle>
                                <circle cx="12" cy="5" r="1"></circle>
                                <circle cx="12" cy="19" r="1"></circle>
                            </svg>
                        </button>
                        <div id="row-menu-${p.id}" class="row-menu-dropdown" style="display: none; position: absolute; right: 0; top: 100%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 14px rgba(15,23,42,0.1); z-index: 50; min-width: 170px; padding: 0.35rem 0; text-align: left;">
                            <a href="#" onclick="window.openBillDrawer(${p.id}); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">View Details</a>
                            ${bal > 0.001 ? `<a href="#" onclick="window.openPaymentModal(${p.id}, '${p.purchase_number}', ${bal}); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--primary); font-size: 0.8125rem; text-decoration: none; font-weight: 500;">Record Payment</a>` : ''}
                            <a href="#" onclick="window.duplicateBill(${p.id}); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">Duplicate</a>
                            <a href="#" onclick="window.printSingleBill(${p.id}); return false;" style="display: block; padding: 0.45rem 1rem; color: var(--text-main); font-size: 0.8125rem; text-decoration: none;">Download PDF</a>
                            <div style="height: 1px; background: var(--border); margin: 0.25rem 0;"></div>
                            <a href="#" onclick="window.deleteBill(${p.id}, ${p.can_delete}); return false;" style="display: block; padding: 0.45rem 1rem; color: ${p.can_delete ? 'var(--danger)' : '#94a3b8'}; font-size: 0.8125rem; text-decoration: none; ${!p.can_delete ? 'pointer-events: none; opacity: 0.5;' : ''}">Delete</a>
                        </div>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function updateTotals(totals) {
        if (!totals) return;
        document.getElementById('foot-total-amount').textContent = formatINR(totals.total_amount);
        document.getElementById('foot-total-paid').textContent = formatINR(totals.total_paid);
        document.getElementById('foot-total-balance').textContent = formatINR(totals.total_balance);
    }

    function updateKpis(kpis) {
        if (!kpis) return;
        document.getElementById('kpi-total-payable').textContent = formatINR(kpis.total_payable);
        document.getElementById('kpi-overdue').textContent = formatINR(kpis.overdue_amount);
        document.getElementById('kpi-overdue-count').textContent = kpis.overdue_count + (kpis.overdue_count === 1 ? ' bill' : ' bills') + ' overdue';
        document.getElementById('kpi-due7').textContent = formatINR(kpis.due_7_days_amount);
        document.getElementById('kpi-due7-count').textContent = kpis.due_7_days_count + (kpis.due_7_days_count === 1 ? ' bill' : ' bills') + ' due soon';
        document.getElementById('kpi-paid-month').textContent = formatINR(kpis.paid_this_month);
    }

    function updatePagination(meta) {
        if (!meta) return;
        currentPage = meta.current_page;
        totalPages = meta.total_pages;

        document.getElementById('bills-count').textContent = meta.total_items;
        const summary = `Showing ${meta.from}-${meta.to} of ${meta.total_items} bills`;
        document.getElementById('pagination-summary').textContent = summary;
        document.getElementById('pagination-top-summary').textContent = summary;
        document.getElementById('current-page-display').textContent = `Page ${meta.current_page} of ${Math.max(1, meta.total_pages)}`;

        document.getElementById('btn-prev-page').disabled = (meta.current_page <= 1);
        document.getElementById('btn-next-page').disabled = (meta.current_page >= meta.total_pages);
    }

    // Pagination Click
    window.changePage = function(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        fetchBills();
    };

    window.changePerPage = function(val) {
        currentPage = 1;
        fetchBills();
    };

    // Sort Column
    window.setSort = function(col) {
        if (currentSort === col) {
            currentDir = (currentDir === 'asc') ? 'desc' : 'asc';
        } else {
            currentSort = col;
            currentDir = 'desc';
        }
        document.querySelectorAll('.sort-icon').forEach(el => el.textContent = '');
        const activeIcon = document.getElementById('sort-' + col);
        if (activeIcon) {
            activeIcon.textContent = currentDir === 'asc' ? ' ▲' : ' ▼';
        }
        currentPage = 1;
        fetchBills();
    };

    // KPI Card Click Filter
    window.applyKpiFilter = function(filterType) {
        const statusEl = document.getElementById('filter-status');
        const fromEl = document.getElementById('filter-from');
        const toEl = document.getElementById('filter-to');

        fromEl.value = '';
        toEl.value = '';

        if (filterType === 'DUE7') {
            statusEl.value = 'UNPAID';
            const today = new Date();
            const in7 = new Date();
            in7.setDate(today.getDate() + 7);
            fromEl.value = today.toISOString().split('T')[0];
            toEl.value = in7.toISOString().split('T')[0];
        } else {
            statusEl.value = filterType;
        }

        currentPage = 1;
        fetchBills();
    };

    // Clear Filters
    window.clearFilters = function() {
        document.getElementById('filter-search').value = '';
        document.getElementById('filter-supplier').value = '';
        document.getElementById('filter-status').value = 'ALL';
        document.getElementById('filter-from').value = '';
        document.getElementById('filter-to').value = '';
        currentPage = 1;
        fetchBills();
    };

    // Bulk selection handlers
    window.toggleSelectAll = function(master) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = master.checked;
            if (master.checked) {
                selectedBills.add(cb.value);
            } else {
                selectedBills.delete(cb.value);
            }
        });
        updateBulkBar();
    };

    window.updateBulkSelection = function() {
        selectedBills.clear();
        document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
            selectedBills.add(cb.value);
        });
        updateBulkBar();
    };

    function updateBulkBar() {
        const bulkBar = document.getElementById('bulk-bar');
        const countSpan = document.getElementById('selected-count');
        const count = selectedBills.size;
        countSpan.textContent = count;
        bulkBar.style.display = count > 0 ? 'flex' : 'none';
    }

    window.bulkExport = function() {
        window.triggerExport('csv');
    };

    window.bulkMarkPaid = function() {
        if (selectedBills.size === 0) return;
        const firstId = Array.from(selectedBills)[0];
        window.openBillDrawer(parseInt(firstId));
    };

    // Export Trigger
    window.toggleExportDropdown = function(e) {
        e.stopPropagation();
        const menu = document.getElementById('export-dropdown-menu');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    };

    window.triggerExport = function(format) {
        document.getElementById('export-dropdown-menu').style.display = 'none';
        const search = document.getElementById('filter-search').value.trim();
        const sup = document.getElementById('filter-supplier').value;
        const status = document.getElementById('filter-status').value;
        const from = document.getElementById('filter-from').value;
        const to = document.getElementById('filter-to').value;

        const params = new URLSearchParams({
            format: format,
            search: search,
            supplier_id: sup,
            status: status,
            from: from,
            to: to
        });
        window.location.href = '/purchases/bills/export?' + params.toString();
    };

    window.printBills = function() {
        document.getElementById('export-dropdown-menu').style.display = 'none';
        window.print();
    };

    // Row Actions Dropdown Toggle
    window.toggleRowMenu = function(e, id) {
        e.stopPropagation();
        document.querySelectorAll('.row-menu-dropdown').forEach(m => {
            if (m.id !== 'row-menu-' + id) m.style.display = 'none';
        });
        const menu = document.getElementById('row-menu-' + id);
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    };

    // Close open dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.row-menu-dropdown').forEach(m => m.style.display = 'none');
        const expMenu = document.getElementById('export-dropdown-menu');
        if (expMenu) expMenu.style.display = 'none';
    });

    // ==========================================
    // RECORD PAYMENT MODAL LOGIC
    // ==========================================
    window.openPaymentModal = function(billId, billNum, balance) {
        document.getElementById('payment-modal-error').style.display = 'none';
        document.getElementById('payment-modal-error').textContent = '';
        document.getElementById('pay-bill-id').value = billId;
        document.getElementById('pay-current-balance').value = balance;
        document.getElementById('payment-bill-ref').textContent = 'Bill: ' + billNum;
        document.getElementById('pay-balance-display').textContent = formatINR(balance);
        document.getElementById('pay-amount').value = balance.toFixed(2);
        document.getElementById('pay-amount').max = balance.toFixed(2);
        document.getElementById('pay-live-remaining').textContent = formatINR(0);
        document.getElementById('pay-submit-btn').disabled = false;
        document.getElementById('pay-submit-btn').textContent = 'Confirm & Post Journal';

        document.getElementById('payment-modal').style.display = 'block';
    };

    window.closePaymentModal = function() {
        document.getElementById('payment-modal').style.display = 'none';
    };

    window.calcLiveBalance = function() {
        const cur = parseFloat(document.getElementById('pay-current-balance').value) || 0;
        const entered = parseFloat(document.getElementById('pay-amount').value) || 0;
        const rem = Math.max(0, cur - entered);
        const remEl = document.getElementById('pay-live-remaining');
        remEl.textContent = formatINR(rem);

        const btn = document.getElementById('pay-submit-btn');
        if (entered > cur + 0.01) {
            remEl.textContent = 'Exceeds balance (' + formatINR(entered - cur) + ' over)';
            remEl.style.color = 'var(--danger)';
            btn.disabled = true;
        } else {
            remEl.style.color = 'var(--text-main)';
            btn.disabled = false;
        }
    };

    window.savePayment = async function(e) {
        e.preventDefault();
        const billId = document.getElementById('pay-bill-id').value;
        const errBox = document.getElementById('payment-modal-error');
        const btn = document.getElementById('pay-submit-btn');

        errBox.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Posting Journal...';

        const payload = {
            amount: parseFloat(document.getElementById('pay-amount').value),
            payment_date: document.getElementById('pay-date').value,
            payment_mode: document.getElementById('pay-mode').value,
            bank_account_id: document.getElementById('pay-bank').value || null,
            reference_no: document.getElementById('pay-ref').value.trim(),
            notes: document.getElementById('pay-notes').value.trim()
        };

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch(`/api/purchases/bills/${billId}/payments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(payload)
            });

            const res = await resp.json();
            if (resp.ok && res.success) {
                window.closePaymentModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(res.message || 'Payment recorded successfully!', 'success');
                }
                if (activeDrawerBillId) {
                    window.openBillDrawer(activeDrawerBillId);
                }
                fetchBills();
            } else {
                errBox.style.display = 'block';
                errBox.textContent = res.message || 'Failed to record payment.';
                btn.disabled = false;
                btn.textContent = 'Confirm & Post Journal';
            }
        } catch (err) {
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error while posting payment.';
            btn.disabled = false;
            btn.textContent = 'Confirm & Post Journal';
        }
    };

    // ==========================================
    // BILL DETAIL SLIDE-OVER DRAWER LOGIC
    // ==========================================
    window.openBillDrawer = async function(billId) {
        activeDrawerBillId = billId;
        const drawer = document.getElementById('bill-drawer');
        const backdrop = document.getElementById('bill-drawer-backdrop');

        backdrop.style.display = 'block';
        drawer.classList.add('open');

        // Reset fields
        document.getElementById('drawer-bill-num').textContent = 'Loading...';
        document.getElementById('drawer-supplier-name').textContent = '';
        document.getElementById('drawer-items-tbody').innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 1.5rem;"><div class="skeleton-box" style="width: 80%; height: 20px;"></div></td></tr>';

        try {
            const resp = await fetch(`/api/purchases/bills/${billId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const res = await resp.json();
            if (!res.success || !res.bill) throw new Error(res.message || 'Bill not found');

            const b = res.bill;
            document.getElementById('drawer-bill-num').textContent = b.purchase_number;
            document.getElementById('drawer-supplier-name').textContent = b.supplier_name;
            document.getElementById('drawer-vendor-inv').textContent = b.vendor_invoice_number || '—';
            document.getElementById('drawer-gstin').textContent = b.gstin || 'Unregistered / Direct';
            document.getElementById('drawer-bill-date').textContent = formatDateIN(b.purchase_date);
            document.getElementById('drawer-due-date').textContent = formatDateIN(b.due_date);

            const status = b.computed_status || 'UNPAID';
            const badgeEl = document.getElementById('drawer-bill-status');
            badgeEl.textContent = status;
            badgeEl.className = 'badge-pill ' + (status === 'PAID' ? 'badge-paid' : (status === 'PARTIALLY PAID' ? 'badge-partial' : (status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid')));

            // Line items
            const tbody = document.getElementById('drawer-items-tbody');
            if (b.items && b.items.length > 0) {
                let iHtml = '';
                b.items.forEach(it => {
                    iHtml += `<tr>
                        <td><strong>${it.item_name}</strong><br><small style="color: var(--text-muted);">HSN: ${it.hsn_sac || '—'}</small></td>
                        <td style="text-align: right;" class="tabular-nums">${it.quantity} ${it.unit || ''}</td>
                        <td style="text-align: right;" class="tabular-nums">${formatINR(it.unit_price)}</td>
                        <td style="text-align: right; font-weight: 600;" class="tabular-nums">${formatINR(it.total_amount)}</td>
                    </tr>`;
                });
                tbody.innerHTML = iHtml;
            } else {
                tbody.innerHTML = `<tr>
                    <td><strong>Procurement Supplies</strong><br><small style="color: var(--text-muted);">${b.notes || 'Inward goods & services'}</small></td>
                    <td style="text-align: right;" class="tabular-nums">1.00</td>
                    <td style="text-align: right;" class="tabular-nums">${formatINR(b.sub_total)}</td>
                    <td style="text-align: right; font-weight: 600;" class="tabular-nums">${formatINR(b.grand_total)}</td>
                </tr>`;
            }

            // Totals
            document.getElementById('drawer-subtotal').textContent = formatINR(b.sub_total);
            document.getElementById('drawer-taxes').textContent = formatINR(b.total_tax);
            document.getElementById('drawer-grand-total').textContent = formatINR(b.grand_total);
            document.getElementById('drawer-balance-due').textContent = formatINR(b.balance_due);

            // Payments History
            const pWrap = document.getElementById('drawer-payments-wrap');
            if (b.payments && b.payments.length > 0) {
                let pHtml = '';
                b.payments.forEach(py => {
                    pHtml += `<div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px;">
                        <div>
                            <div style="font-weight: 600; font-size: 0.8125rem; color: var(--text-main);">${py.payment_number}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">${formatDateIN(py.allocation_date)} &bull; ${py.payment_mode} ${py.reference_no ? '(' + py.reference_no + ')' : ''}</div>
                        </div>
                        <strong style="color: var(--success); font-size: 0.875rem;" class="tabular-nums">${formatINR(py.allocated_amount)}</strong>
                    </div>`;
                });
                pWrap.innerHTML = pHtml;
            } else {
                pWrap.innerHTML = '<div style="color: var(--text-muted); font-size: 0.8125rem;">No payment records found.</div>';
            }

            // Pay Button
            const payBtn = document.getElementById('drawer-pay-btn');
            if (parseFloat(b.balance_due) <= 0.001) {
                payBtn.disabled = true;
                payBtn.textContent = 'Fully Paid';
            } else {
                payBtn.disabled = false;
                payBtn.textContent = 'Record Payment';
            }
        } catch (err) {
            console.error('Drawer bill fetch error:', err);
        }
    };

    window.closeBillDrawer = function() {
        document.getElementById('bill-drawer').classList.remove('open');
        document.getElementById('bill-drawer-backdrop').style.display = 'none';
        activeDrawerBillId = null;
    };

    window.payFromDrawer = function() {
        if (!activeDrawerBillId) return;
        const billNum = document.getElementById('drawer-bill-num').textContent;
        const balStr = document.getElementById('drawer-balance-due').textContent.replace(/[^0-9.]/g, '');
        const bal = parseFloat(balStr) || 0;
        window.closeBillDrawer();
        window.openPaymentModal(activeDrawerBillId, billNum, bal);
    };

    // Print Single Bill
    window.printSingleBill = function(billId) {
        window.open('/purchases/bills/export?format=html&search=' + encodeURIComponent(billId), '_blank');
    };

    // Duplicate Bill
    window.duplicateBill = async function(billId) {
        if (!confirm('Duplicate this purchase bill as a new inward record?')) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const resp = await fetch(`/api/purchases/bills/${billId}/duplicate`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            });
            const res = await resp.json();
            if (resp.ok && res.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show('Bill duplicated successfully!', 'success');
                }
                fetchBills();
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(res.message || 'Failed to duplicate bill.', 'danger');
                }
            }
        } catch (err) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Error duplicating bill.', 'danger');
            }
        }
    };

    // Delete Bill
    window.deleteBill = async function(billId, canDelete) {
        if (!canDelete) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Cannot delete this purchase bill because payment records exist.', 'warning');
            }
            return;
        }
        if (!confirm('Are you sure you want to permanently delete this purchase bill?')) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const resp = await fetch(`/api/purchases/bills/${billId}/delete`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            });
            const res = await resp.json();
            if (resp.ok && res.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show('Purchase bill deleted successfully.', 'success');
                }
                fetchBills();
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(res.message || 'Failed to delete bill.', 'danger');
                }
            }
        } catch (err) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Error deleting bill.', 'danger');
            }
        }
    };

    // Record Inward Bill Modal
    window.openPurchaseModal = function() {
        const form = document.getElementById('purchase-form');
        form.reset();
        document.getElementById('purchase-modal-error').style.display = 'none';
        document.getElementById('purchase-submit-btn').disabled = false;
        document.getElementById('purchase-submit-btn').textContent = 'Save Purchase Bill';
        document.getElementById('purchase-modal').style.display = 'block';
    };

    window.closePurchaseModal = function() {
        document.getElementById('purchase-modal').style.display = 'none';
    };

    window.savePurchaseBill = async function(e) {
        e.preventDefault();
        const form = document.getElementById('purchase-form');
        const errBox = document.getElementById('purchase-modal-error');
        const submitBtn = document.getElementById('purchase-submit-btn');

        errBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/purchases/bills', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: formData
            });

            const result = await resp.json();
            if (resp.ok && result.success) {
                window.closePurchaseModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Purchase bill recorded successfully!', 'success');
                }
                fetchBills();
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to record purchase bill.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Purchase Bill';
            }
        } catch (err) {
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Purchase Bill';
        }
    };

    // Init Event Listeners on DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        // Debounced Filter inputs
        document.getElementById('filter-search').addEventListener('input', triggerFetchDebounced);
        document.getElementById('filter-supplier').addEventListener('change', () => { currentPage = 1; fetchBills(); });
        document.getElementById('filter-status').addEventListener('change', () => { currentPage = 1; fetchBills(); });
        document.getElementById('filter-from').addEventListener('change', () => { currentPage = 1; fetchBills(); });
        document.getElementById('filter-to').addEventListener('change', () => { currentPage = 1; fetchBills(); });

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.closePurchaseModal();
                window.closePaymentModal();
                window.closeBillDrawer();
            }
        });
    });
</script>
