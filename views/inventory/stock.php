<?php
/**
 * WTSBill ERP — Stock Adjustments & Warehouse Balances (Business-Grade Inventory Screen)
 *
 * @var array $initialBalances
 * @var array $initialHistory
 * @var array $initialLedger
 * @var array $categories
 * @var array $warehouses
 * @var array $products
 * @var bool $allowNegativeStock
 * @var string $tab
 */

$activeTab = in_array($tab ?? 'balances', ['balances', 'history', 'ledger'], true) ? $tab : 'balances';
$totals = $initialBalances['totals'] ?? [
    'total_units' => 0,
    'total_units_formatted' => '0',
    'total_valuation' => 0,
    'total_valuation_formatted' => '₹0.00',
    'total_reserved' => 0,
    'total_reserved_formatted' => '0',
    'active_warehouses' => 1,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
    'trend_units' => '+4.2%',
    'trend_valuation' => '+2.8%',
];
$balancesList = $initialBalances['rows'] ?? $initialBalances['data'] ?? [];
$historyList = $initialHistory['rows'] ?? $initialHistory['data'] ?? [];
$ledgerList = $initialLedger['rows'] ?? $initialLedger['data'] ?? [];
?>

<!-- Print-Only Report Header -->
<div class="print-inventory-header print-only">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px;">
        <div>
            <h1 style="font-size: 20px; font-weight: bold; margin: 0; text-transform: uppercase;">Wis Technosavvy Pvt Ltd</h1>
            <div style="font-size: 11px; color: #444; margin-top: 3px;">
                GSTIN: 27AADCW7577N1ZE &bull; Operating Branch: <?= e(\WTSBill\Core\Session::getBranchName() ?? 'Main Branch - Mumbai') ?>
            </div>
            <h2 style="font-size: 14px; font-weight: 600; margin-top: 8px; color: #111;">STOCK & WAREHOUSE BALANCES INVENTORY REPORT</h2>
        </div>
        <div style="text-align: right; font-size: 11px; color: #444;">
            <div><strong>Generated:</strong> <?= date('d-M-Y H:i:s') ?></div>
            <div><strong>Valuation Method:</strong> FIFO Cost Valuation</div>
            <div><strong>Status Filter:</strong> <span id="print-filter-status">All Items</span></div>
        </div>
    </div>
</div>

<!-- Screen Page Header -->
<div class="page-header stock-page-header no-print">
    <div>
        <h1 class="page-title">Stock & Warehouse Balances</h1>
        <p class="page-subtitle">Real-time multi-warehouse inventory levels, reserved allocations, and physical stock reconciliation</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <!-- Export Dropdown -->
        <div class="dropdown" style="position: relative;" id="export-dropdown-wrapper">
            <button type="button" class="btn btn-secondary dropdown-toggle" id="export-menu-btn" onclick="window.WTSStock.toggleExportMenu(event)" aria-haspopup="true" aria-expanded="false">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export ▾</span>
            </button>
            <div class="dropdown-menu" id="export-menu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 1000; min-width: 170px; padding: 4px 0;">
                <a href="javascript:void(0)" class="dropdown-item" onclick="window.WTSStock.triggerExport('csv')" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; color: var(--text-primary);">
                    <span style="font-weight: 700; color: #10b981;">CSV</span> Comma-Separated
                </a>
                <a href="javascript:void(0)" class="dropdown-item" onclick="window.WTSStock.triggerExport('excel')" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; color: var(--text-primary);">
                    <span style="font-weight: 700; color: #3b82f6;">XLS</span> Excel Workbook
                </a>
                <a href="javascript:void(0)" class="dropdown-item" onclick="window.WTSStock.triggerExport('pdf')" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; color: var(--text-primary);">
                    <span style="font-weight: 700; color: #ef4444;">PDF</span> Printable Document
                </a>
            </div>
        </div>

        <!-- Print Button -->
        <button type="button" class="btn btn-secondary" onclick="window.print()" title="Print Inventory Report" aria-label="Print Inventory Report">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            <span>Print</span>
        </button>

        <!-- Primary Action -->
        <button type="button" class="btn btn-primary" onclick="window.WTSStock.openAdjustmentModal()" id="btn-record-adjustment">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>+ Record Adjustment</span>
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="stock-tabs-nav no-print" style="margin-bottom: 1.25rem;">
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0;">
        <button type="button" class="stock-tab-btn <?= $activeTab === 'balances' ? 'active' : '' ?>" data-tab="balances" onclick="window.WTSStock.switchTab('balances')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span>Warehouse Balances</span>
            <span class="tab-badge" id="tab-count-balances"><?= count($balancesList) ?></span>
        </button>

        <button type="button" class="stock-tab-btn <?= $activeTab === 'history' ? 'active' : '' ?>" data-tab="history" onclick="window.WTSStock.switchTab('history')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 14 14"></polyline>
            </svg>
            <span>Adjustment History</span>
            <span class="tab-badge" id="tab-count-history"><?= count($historyList) ?></span>
        </button>

        <button type="button" class="stock-tab-btn <?= $activeTab === 'ledger' ? 'active' : '' ?>" data-tab="ledger" onclick="window.WTSStock.switchTab('ledger')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span>Stock Ledger</span>
            <span class="tab-badge" id="tab-count-ledger"><?= count($ledgerList) ?></span>
        </button>
    </div>
</div>

<!-- 6 Responsive Stock KPI Summary Cards -->
<div class="kpi-grid-container no-print" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- 1. Total Stocked Units -->
    <div class="card kpi-card" style="margin-bottom: 0;">
        <div class="card-body" style="padding: 1rem;">
            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Total Stocked Units</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem; font-variant-numeric: tabular-nums;" id="kpi-total-units">
                <?= e($totals['total_units_formatted'] ?? '0') ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; font-size: 0.75rem;">
                <span style="color: var(--color-success); font-weight: 600;">▲ <?= e($totals['trend_units'] ?? '+4.2%') ?></span>
                <span style="color: var(--text-muted);">vs last month</span>
            </div>
        </div>
    </div>

    <!-- 2. Inventory Valuation (FIFO) -->
    <div class="card kpi-card" style="margin-bottom: 0;">
        <div class="card-body" style="padding: 1rem;">
            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Inventory Valuation</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem; font-variant-numeric: tabular-nums;" id="kpi-total-valuation">
                <?= e($totals['total_valuation_formatted'] ?? '₹0.00') ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; font-size: 0.75rem;">
                <span style="color: var(--color-success); font-weight: 600;">▲ <?= e($totals['trend_valuation'] ?? '+2.8%') ?></span>
                <span style="color: var(--text-muted);">FIFO Cost Basis</span>
            </div>
        </div>
    </div>

    <!-- 3. Reserved for Orders (Neutral Grey when 0) -->
    <div class="card kpi-card" style="margin-bottom: 0;">
        <div class="card-body" style="padding: 1rem;">
            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Reserved For Orders</div>
            <?php 
                $resQty = (float)($totals['total_reserved'] ?? 0);
                $resColor = $resQty > 0 ? 'var(--color-warning)' : 'var(--text-secondary)';
            ?>
            <div style="font-size: 1.45rem; font-weight: 700; color: <?= $resColor ?>; margin-top: 0.25rem; font-variant-numeric: tabular-nums;" id="kpi-total-reserved">
                <?= e($totals['total_reserved_formatted'] ?? '0') ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Committed delivery challans</div>
        </div>
    </div>

    <!-- 4. Active Warehouses -->
    <div class="card kpi-card" style="margin-bottom: 0;">
        <div class="card-body" style="padding: 1rem;">
            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Active Warehouses</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: var(--color-info); margin-top: 0.25rem;" id="kpi-active-warehouses">
                <?= (int)($totals['active_warehouses'] ?? count($warehouses ?? [1])) ?> Active
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Central Hub & Depots</div>
        </div>
    </div>

    <!-- 5. Low Stock Items (Amber, Clickable Filter) -->
    <div class="card kpi-card clickable-kpi" id="kpi-card-low-stock" onclick="window.WTSStock.applyStatusFilter('low_stock')" title="Click to filter low stock items" style="margin-bottom: 0; cursor: pointer; border-left: 3px solid var(--color-warning);">
        <div class="card-body" style="padding: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.72rem; color: var(--color-warning); font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Low Stock Items</div>
                <span class="badge badge-warning" style="font-size: 0.65rem; padding: 2px 6px;">ALERT</span>
            </div>
            <div style="font-size: 1.45rem; font-weight: 700; color: var(--color-warning); margin-top: 0.25rem; font-variant-numeric: tabular-nums;" id="kpi-low-stock">
                <?= (int)($totals['low_stock_count'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Below reorder threshold &rarr;</div>
        </div>
    </div>

    <!-- 6. Out of Stock Items (Red, Clickable Filter) -->
    <div class="card kpi-card clickable-kpi" id="kpi-card-out-of-stock" onclick="window.WTSStock.applyStatusFilter('out_of_stock')" title="Click to filter out of stock items" style="margin-bottom: 0; cursor: pointer; border-left: 3px solid var(--color-danger);">
        <div class="card-body" style="padding: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.72rem; color: var(--color-danger); font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Out of Stock</div>
                <span class="badge badge-danger" style="font-size: 0.65rem; padding: 2px 6px;">CRITICAL</span>
            </div>
            <div style="font-size: 1.45rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem; font-variant-numeric: tabular-nums;" id="kpi-out-of-stock">
                <?= (int)($totals['out_of_stock_count'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Depleted inventory &rarr;</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card filter-bar-card no-print" style="margin-bottom: 1.25rem; background: var(--bg-surface); border: 1px solid var(--border-color);">
    <div class="card-body" style="padding: 0.85rem 1.25rem;">
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <!-- Search Input -->
            <div style="position: relative; flex: 1; min-width: 220px;">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" id="stock-search" class="form-control" placeholder="Search SKU, item name, or barcode..." 
                       style="padding-left: 32px; font-size: 0.875rem;" autocomplete="off">
            </div>

            <!-- Warehouse Dropdown -->
            <div style="min-width: 170px;">
                <select id="filter-warehouse" class="form-control" style="font-size: 0.875rem;" onchange="window.WTSStock.onFilterChange()">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses ?? [] as $wh): ?>
                        <option value="<?= e((string)$wh['id']) ?>"><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Category Dropdown -->
            <div style="min-width: 160px;">
                <select id="filter-category" class="form-control" style="font-size: 0.875rem;" onchange="window.WTSStock.onFilterChange()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories ?? [] as $cat): ?>
                        <option value="<?= e((string)$cat['id']) ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Dropdown -->
            <div style="min-width: 150px;">
                <select id="filter-status" class="form-control" style="font-size: 0.875rem;" onchange="window.WTSStock.onFilterChange()">
                    <option value="all">All Statuses</option>
                    <option value="healthy">Healthy Stock</option>
                    <option value="low_stock">Low Stock (≤ Reorder)</option>
                    <option value="out_of_stock">Out of Stock (0)</option>
                    <option value="overstock">Overstock (&gt; Max)</option>
                </select>
            </div>

            <!-- Clear Filters Link -->
            <button type="button" class="btn btn-secondary" onclick="window.WTSStock.clearFilters()" style="padding: 0.5rem 0.85rem; font-size: 0.825rem; display: flex; align-items: center; gap: 4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                <span>Clear</span>
            </button>
        </div>
    </div>
</div>

<!-- Floating Bulk Action Bar (Visible when row checkboxes checked) -->
<div id="bulk-action-bar" class="bulk-action-bar no-print" style="display: none;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <span style="font-weight: 600; font-size: 0.85rem; color: #fff;">
            <span id="bulk-selected-count">0</span> items selected
        </span>
        <div style="height: 18px; width: 1px; background: rgba(255,255,255,0.2);"></div>
        <button type="button" class="btn-bulk-action" onclick="window.WTSStock.bulkExportSelected()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Selected
        </button>
        <button type="button" class="btn-bulk-action" onclick="window.WTSStock.openBulkAdjustModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
            Bulk Adjust
        </button>
        <button type="button" class="btn-bulk-action" onclick="window.WTSStock.printLabels()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="12" y2="16"></line></svg>
            Print Labels
        </button>
    </div>
    <button type="button" class="btn-bulk-close" onclick="window.WTSStock.deselectAll()" title="Deselect all">&times;</button>
</div>

<!-- Inline Error / Retry Banner -->
<div id="stock-error-banner" class="alert alert-danger" style="display: none; margin-bottom: 1rem; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span id="stock-error-message">An error occurred while loading inventory data.</span>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="window.WTSStock.loadCurrentTabData()">Retry</button>
</div>

<!-- ========================================== -->
<!-- TAB 1: WAREHOUSE BALANCES CONTAINER       -->
<!-- ========================================== -->
<div id="pane-balances" class="stock-tab-pane <?= $activeTab === 'balances' ? 'active' : '' ?>">
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <h3 class="card-title" style="margin: 0;">
                Warehouse Stock Inventory (<span id="stock-count"><?= (int)($initialBalances['pagination']['total'] ?? count($balancesList)) ?></span> Items Tracked)
            </h3>
            <div style="display: flex; align-items: center; gap: 0.5rem;" class="no-print">
                <label for="balances-per-page" style="font-size: 0.8rem; color: var(--text-muted);">Rows per page:</label>
                <select id="balances-per-page" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" onchange="window.WTSStock.changePerPage(this.value)">
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="table-container stock-table-responsive" style="position: relative; overflow-x: auto;">
            <table class="table stock-table" id="table-balances">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;" class="no-print">
                            <input type="checkbox" id="check-all-balances" onclick="window.WTSStock.toggleCheckAll(this)">
                        </th>
                        <th style="width: 50px;">#</th>
                        <th class="sortable-th pinned-column" data-sort="product_name" onclick="window.WTSStock.applySort('product_name')">
                            Product & SKU <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th" data-sort="category" onclick="window.WTSStock.applySort('category')">
                            Category <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th" data-sort="warehouse" onclick="window.WTSStock.applySort('warehouse')">
                            Warehouse <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="quantity" onclick="window.WTSStock.applySort('quantity')">
                            On Hand <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="reserved" onclick="window.WTSStock.applySort('reserved')">
                            Reserved <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="available" onclick="window.WTSStock.applySort('available')">
                            Available <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="reorder_level" onclick="window.WTSStock.applySort('reorder_level')">
                            Reorder Level <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="unit_price" onclick="window.WTSStock.applySort('unit_price')">
                            Unit Price <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th numeric-col" data-sort="stock_value" onclick="window.WTSStock.applySort('stock_value')">
                            Stock Value <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th" style="text-align: center;" data-sort="last_movement" onclick="window.WTSStock.applySort('last_movement')">
                            Last Movement <span class="sort-icon">⇅</span>
                        </th>
                        <th class="sortable-th" style="text-align: center;" data-sort="status" onclick="window.WTSStock.applySort('status')">
                            Status <span class="sort-icon">⇅</span>
                        </th>
                        <th style="text-align: right; width: 120px;" class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="balances-tbody">
                    <?php if (!empty($balancesList)): ?>
                        <?php foreach ($balancesList as $i => $sb): ?>
                            <?php 
                                $isOos = ($sb['status'] ?? '') === 'OUT OF STOCK';
                                $isLow = ($sb['status'] ?? '') === 'LOW STOCK';
                                $isOver = ($sb['status'] ?? '') === 'OVERSTOCK';
                                $rowClass = $isOos ? 'stock-row-oos' : ($isLow ? 'stock-row-low' : '');
                                $statusBadgeClass = $isOos ? 'badge-danger' : ($isLow ? 'badge-warning' : ($isOver ? 'badge-info' : 'badge-success'));
                            ?>
                            <tr class="<?= $rowClass ?>" data-id="<?= e((string)$sb['id']) ?>" data-product-id="<?= e((string)$sb['product_id']) ?>" data-warehouse-id="<?= e((string)$sb['warehouse_id']) ?>">
                                <td style="text-align: center;" class="no-print">
                                    <input type="checkbox" class="stock-row-check" value="<?= e((string)$sb['id']) ?>" onchange="window.WTSStock.onRowCheckChange()">
                                </td>
                                <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $i + 1 ?></td>
                                <td class="pinned-column">
                                    <div style="font-weight: 600; color: var(--text-primary);"><?= e($sb['product_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #cbd5e1; margin-top: 2px;">
                                        SKU: <code style="color: #93c5fd; background: rgba(59,130,246,0.15); padding: 1px 4px; border-radius: 3px;"><?= e($sb['sku']) ?></code>
                                        <?php if (!empty($sb['barcode'])): ?>
                                            &bull; <span style="color: var(--text-muted);">Barcode: <?= e($sb['barcode']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(148,163,184,0.15); color: var(--text-secondary); font-size: 0.75rem;">
                                        <?= e($sb['category_name'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td><?= e($sb['warehouse_name'] ?: 'Central Hub') ?></td>
                                
                                <!-- On Hand with Mini Stock Progress Bar -->
                                <td class="numeric-col" style="font-weight: 700;">
                                    <div><?= number_format((float)$sb['quantity'], 0) ?> <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 400;"><?= e($sb['unit'] ?? 'Pcs') ?></span></div>
                                    <div class="stock-mini-bar-bg" title="On Hand vs Reorder Threshold">
                                        <div class="stock-mini-bar-fill <?= $isOos ? 'bar-danger' : ($isLow ? 'bar-warning' : 'bar-success') ?>" 
                                             style="width: <?= (int)($sb['stock_bar_percent'] ?? 50) ?>%;"></div>
                                    </div>
                                </td>

                                <!-- Reserved Quantity -->
                                <td class="numeric-col" style="color: <?= (float)$sb['reserved_quantity'] > 0 ? 'var(--color-warning)' : 'var(--text-muted)' ?>;">
                                    <?= number_format((float)$sb['reserved_quantity'], 0) ?>
                                </td>

                                <!-- Available Quantity -->
                                <td class="numeric-col" style="font-weight: 700; color: <?= (float)$sb['available_quantity'] > 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                    <?= number_format((float)$sb['available_quantity'], 0) ?>
                                </td>

                                <!-- Reorder Level -->
                                <td class="numeric-col" style="color: var(--text-muted);">
                                    <?= number_format((float)$sb['reorder_level'], 0) ?>
                                </td>

                                <!-- Unit Price -->
                                <td class="numeric-col">
                                    <?= e($sb['unit_price_formatted'] ?? format_currency((float)($sb['unit_price'] ?? 0))) ?>
                                </td>

                                <!-- Stock Value -->
                                <td class="numeric-col" style="font-weight: 700; color: var(--color-primary);">
                                    <?= e($sb['stock_value_formatted'] ?? format_currency((float)($sb['stock_value'] ?? 0))) ?>
                                </td>

                                <!-- Last Movement -->
                                <td style="text-align: center; font-size: 0.8rem; color: var(--text-muted);">
                                    <?= e($sb['last_movement'] ?? '—') ?>
                                </td>

                                <!-- Status Badge -->
                                <td style="text-align: center;">
                                    <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 700;">
                                        <?= e($sb['status'] ?? 'HEALTHY') ?>
                                    </span>
                                </td>

                                <!-- Action Buttons -->
                                <td style="text-align: right;" class="no-print">
                                    <div style="display: flex; gap: 4px; justify-content: flex-end;">
                                        <button type="button" class="btn-icon" title="Adjust Stock" onclick="window.WTSStock.openAdjustmentModal(<?= (int)$sb['product_id'] ?>, <?= (int)$sb['warehouse_id'] ?>)" aria-label="Adjust Stock">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                        <button type="button" class="btn-icon" title="View Ledger" onclick="window.WTSStock.viewItemLedger(<?= (int)$sb['product_id'] ?>)" aria-label="View Ledger">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                                        </button>
                                        <button type="button" class="btn-icon" title="Warehouse Transfer" onclick="window.WTSStock.openTransferModal(<?= (int)$sb['product_id'] ?>, <?= (int)$sb['warehouse_id'] ?>, <?= (float)$sb['available_quantity'] ?>)" aria-label="Warehouse Transfer">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                                        </button>
                                        <button type="button" class="btn-icon" title="Item Details" onclick="window.WTSStock.openItemDetails(<?= (int)$sb['product_id'] ?>)" aria-label="Item Details">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="balances-empty-row">
                            <td colspan="14" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-secondary);">No warehouse inventory items found</div>
                                <p style="font-size: 0.85rem; margin-bottom: 1rem;">Try clearing your active filters or recording a new physical adjustment.</p>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="window.WTSStock.clearFilters()">Clear Filters</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Balances Pagination Footer -->
        <div class="card-footer stock-pagination-bar no-print" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted);" id="balances-page-info">
                Showing <?= !empty($balancesList) ? '1' : '0' ?>-<?= count($balancesList) ?> of <?= (int)($initialBalances['pagination']['total'] ?? count($balancesList)) ?> items
            </div>
            <div class="pagination-controls" id="balances-pagination">
                <!-- Dynamically rendered by JS -->
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 2: ADJUSTMENT HISTORY CONTAINER        -->
<!-- ========================================== -->
<div id="pane-history" class="stock-tab-pane <?= $activeTab === 'history' ? 'active' : '' ?>">
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="margin: 0;">Physical Stock Reconciliation & Adjustment Audit Log</h3>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Immutable audit trail with matching GL journal integration</div>
        </div>

        <div class="table-container stock-table-responsive" style="overflow-x: auto;">
            <table class="table stock-table" id="table-history">
                <thead>
                    <tr>
                        <th style="width: 100px;">Date</th>
                        <th style="width: 130px;">Adjustment #</th>
                        <th>Product & SKU</th>
                        <th>Warehouse</th>
                        <th style="text-align: center; width: 110px;">Type</th>
                        <th style="text-align: right; width: 90px;" class="numeric-col">Quantity</th>
                        <th style="text-align: right; width: 100px;" class="numeric-col">Unit Cost</th>
                        <th style="text-align: right; width: 110px;" class="numeric-col">Total Impact</th>
                        <th>Reason / Audit Notes</th>
                        <th style="width: 120px;">Operator</th>
                        <th style="text-align: center; width: 90px;">Status</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <?php if (!empty($historyList)): ?>
                        <?php foreach ($historyList as $h): ?>
                            <tr>
                                <td style="font-size: 0.825rem; color: var(--text-muted);"><?= e($h['date']) ?></td>
                                <td><code style="color: #93c5fd;"><?= e($h['adjustment_number']) ?></code></td>
                                <td>
                                    <strong><?= e($h['product_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: #cbd5e1;">SKU: <?= e($h['sku']) ?></div>
                                </td>
                                <td><?= e($h['warehouse_name']) ?></td>
                                <td style="text-align: center;">
                                    <?php 
                                        $tBadge = ($h['type'] === 'Increase' || $h['type'] === 'Opening Stock') ? 'badge-success' : 'badge-danger';
                                    ?>
                                    <span class="badge <?= $tBadge ?>"><?= e($h['type']) ?></span>
                                </td>
                                <td class="numeric-col" style="font-weight: 700;">
                                    <?= ($h['direction'] === 'OUT' ? '-' : '+') . number_format((float)$h['quantity'], 0) ?>
                                </td>
                                <td class="numeric-col"><?= e($h['unit_cost_formatted']) ?></td>
                                <td class="numeric-col" style="font-weight: 700; color: var(--color-primary);"><?= e($h['total_cost_formatted']) ?></td>
                                <td style="font-size: 0.825rem; max-width: 200px;"><?= e($h['reason'] . ($h['notes'] ? " ({$h['notes']})" : '')) ?></td>
                                <td style="font-size: 0.825rem; color: var(--text-secondary);"><?= e($h['user']) ?></td>
                                <td style="text-align: center;"><span class="badge badge-success">POSTED</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="history-empty-row">
                            <td colspan="11" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                No physical stock adjustment entries logged yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer stock-pagination-bar no-print" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted);" id="history-page-info">
                Showing <?= count($historyList) ?> entries
            </div>
            <div class="pagination-controls" id="history-pagination"></div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 3: STOCK LEDGER CONTAINER             -->
<!-- ========================================== -->
<div id="pane-ledger" class="stock-tab-pane <?= $activeTab === 'ledger' ? 'active' : '' ?>">
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="margin: 0;">Perpetual Stock Ledger & Chronological Movement History</h3>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Complete In / Out movements with running balance calculation</div>
        </div>

        <div class="table-container stock-table-responsive" style="overflow-x: auto;">
            <table class="table stock-table" id="table-ledger">
                <thead>
                    <tr>
                        <th style="width: 130px;">Date & Time</th>
                        <th>Product & SKU</th>
                        <th>Warehouse</th>
                        <th style="width: 140px;">Movement Type</th>
                        <th style="text-align: center; width: 80px;">Direction</th>
                        <th style="text-align: right; width: 90px;" class="numeric-col">Quantity</th>
                        <th style="text-align: right; width: 100px;" class="numeric-col">Balance After</th>
                        <th style="width: 130px;">Reference Doc</th>
                        <th>Movement Notes</th>
                    </tr>
                </thead>
                <tbody id="ledger-tbody">
                    <?php if (!empty($ledgerList)): ?>
                        <?php foreach ($ledgerList as $l): ?>
                            <tr>
                                <td style="font-size: 0.825rem; color: var(--text-muted);"><?= e($l['movement_date']) ?></td>
                                <td>
                                    <strong><?= e($l['product_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: #cbd5e1;">SKU: <?= e($l['sku']) ?></div>
                                </td>
                                <td><?= e($l['warehouse_name']) ?></td>
                                <td>
                                    <span class="badge" style="background: rgba(59,130,246,0.15); color: #93c5fd; font-size: 0.72rem;">
                                        <?= e($l['movement_type']) ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $l['direction'] === 'IN' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= e($l['direction']) ?>
                                    </span>
                                </td>
                                <td class="numeric-col" style="font-weight: 700; color: <?= $l['direction'] === 'IN' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                    <?= ($l['direction'] === 'IN' ? '+' : '-') . number_format((float)$l['quantity'], 0) ?>
                                </td>
                                <td class="numeric-col" style="font-weight: 700;"><?= number_format((float)$l['balance_after'], 0) ?></td>
                                <td><code style="color: #f59e0b;"><?= e($l['reference_number']) ?></code></td>
                                <td style="font-size: 0.825rem; color: var(--text-secondary);"><?= e($l['notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="ledger-empty-row">
                            <td colspan="9" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                No stock ledger transactions recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer stock-pagination-bar no-print" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted);" id="ledger-page-info">
                Showing <?= count($ledgerList) ?> entries
            </div>
            <div class="pagination-controls" id="ledger-pagination"></div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: RECORD STOCK ADJUSTMENT             -->
<!-- ========================================== -->
<div id="stock-modal" class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title">
    <div class="modal-dialog" style="max-width: 620px;">
        <div class="modal-header">
            <h3 id="stock-modal-title" class="modal-title">Record Physical Stock Adjustment</h3>
            <button type="button" class="modal-close" onclick="window.WTSStock.closeAdjustmentModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="stock-form" onsubmit="window.WTSStock.saveAdjustment(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="stock-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <!-- Date & Warehouse -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="adj-date">Adjustment Date <span style="color: var(--color-danger);">*</span></label>
                            <input type="date" id="adj-date" name="adjustment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="adj-warehouse">Warehouse Location <span style="color: var(--color-danger);">*</span></label>
                            <select id="adj-warehouse" name="warehouse_id" class="form-control" required onchange="window.WTSStock.updateAdjustmentPreview()">
                                <?php foreach ($warehouses ?? [] as $wh): ?>
                                    <option value="<?= e((string)$wh['id']) ?>"><?= e($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Inventory Product Selection -->
                    <div class="form-group">
                        <label for="adj-product">Inventory Product <span style="color: var(--color-danger);">*</span></label>
                        <select id="adj-product" name="product_id" class="form-control" required onchange="window.WTSStock.onAdjustmentProductChange()">
                            <option value="">-- Select Product or SKU --</option>
                            <?php foreach ($products ?? [] as $pr): ?>
                                <option value="<?= e((string)$pr['id']) ?>" 
                                        data-stock="<?= (float)($pr['current_stock'] ?? 0) ?>"
                                        data-cost="<?= (float)($pr['cost_price'] ?? 0) ?>"
                                        data-price="<?= (float)($pr['price'] ?? 0) ?>"
                                        data-sku="<?= e($pr['sku']) ?>">
                                    <?= e($pr['name']) ?> (SKU: <?= e($pr['sku']) ?>) — Current: <?= (float)($pr['current_stock'] ?? 0) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Adjustment Type & Quantity -->
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="adj-type">Adjustment Action <span style="color: var(--color-danger);">*</span></label>
                            <select id="adj-type" name="adjustment_type" class="form-control" required onchange="window.WTSStock.updateAdjustmentPreview()">
                                <option value="Increase">+ Increase (Stock In / Found)</option>
                                <option value="Decrease">- Decrease (Audit Shrinkage)</option>
                                <option value="Write-off">- Write-off (Damaged / Obsolete)</option>
                                <option value="Damage">- Damage (Scrapped Items)</option>
                                <option value="Opening Stock">= Opening Stock (Set Exact Count)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="adj-qty">Quantity <span style="color: var(--color-danger);">*</span></label>
                            <input type="number" id="adj-qty" name="quantity" class="form-control" placeholder="0" min="0.001" step="0.001" required oninput="window.WTSStock.updateAdjustmentPreview()">
                        </div>
                    </div>

                    <!-- Unit Cost & Reason Dropdown -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="adj-cost">Unit Cost (₹)</label>
                            <input type="number" id="adj-cost" name="unit_cost" class="form-control" placeholder="0.00" min="0" step="0.01">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Autofilled from product cost. Editable for increases.</small>
                        </div>

                        <div class="form-group">
                            <label for="adj-reason">Reason Code <span style="color: var(--color-danger);">*</span></label>
                            <select id="adj-reason" name="reason" class="form-control" required>
                                <option value="Cycle Count Reconciliation">Cycle Count Reconciliation</option>
                                <option value="Physical Count Variance">Physical Count Variance</option>
                                <option value="Damaged in Warehouse">Damaged in Warehouse</option>
                                <option value="Expired or Obsolete Inventory">Expired or Obsolete Inventory</option>
                                <option value="Supplier Batch Discrepancy">Supplier Batch Discrepancy</option>
                                <option value="Annual Stock Audit">Annual Stock Audit</option>
                                <option value="Other Audit Adjustment">Other Audit Adjustment</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Stock Preview Card -->
                    <div id="adj-preview-card" style="padding: 0.75rem 1rem; border-radius: var(--radius-md); background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Stock Level Preview</div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-top: 2px;" id="adj-preview-text">
                                Current: <span id="adj-preview-cur">0</span> &rarr; New: <span id="adj-preview-new" style="color: var(--color-primary);">0</span>
                            </div>
                        </div>
                        <div id="adj-preview-badge" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">
                            No change
                        </div>
                    </div>

                    <!-- Negative Stock Warning (if triggered) -->
                    <div id="adj-neg-warning" class="alert alert-danger" style="display: none; padding: 0.6rem 0.85rem; font-size: 0.8rem;">
                        <strong>Stock Warning:</strong> This adjustment will reduce stock below zero. Negative inventory is not permitted.
                    </div>

                    <!-- Audit Notes & Optional Attachment -->
                    <div class="form-group">
                        <label for="adj-notes">Audit Notes & Narrative</label>
                        <textarea id="adj-notes" name="notes" class="form-control" rows="2" placeholder="e.g. Discrepancy identified during quarterly aisle B audit"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.WTSStock.closeAdjustmentModal()">Cancel</button>
                <button type="submit" id="adj-submit-btn" class="btn btn-primary">Post Adjustment</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: WAREHOUSE STOCK TRANSFER            -->
<!-- ========================================== -->
<div id="transfer-modal" class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="transfer-modal-title">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="transfer-modal-title" class="modal-title">Inter-Warehouse Stock Transfer</h3>
            <button type="button" class="modal-close" onclick="window.WTSStock.closeTransferModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="transfer-form" onsubmit="window.WTSStock.saveTransfer(event)">
            <input type="hidden" id="trans-product-id" name="product_id" value="">
            <div class="modal-body">
                <div id="transfer-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem;"></div>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label>Item to Transfer</label>
                        <input type="text" id="trans-product-name" class="form-control" readonly style="background: rgba(255,255,255,0.05); font-weight: 600;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="trans-source">Source Warehouse</label>
                            <select id="trans-source" name="source_warehouse_id" class="form-control" required>
                                <?php foreach ($warehouses ?? [] as $wh): ?>
                                    <option value="<?= e((string)$wh['id']) ?>"><?= e($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="trans-dest">Destination Warehouse</label>
                            <select id="trans-dest" name="dest_warehouse_id" class="form-control" required>
                                <?php foreach ($warehouses ?? [] as $wh): ?>
                                    <option value="<?= e((string)$wh['id']) ?>"><?= e($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="trans-qty">Transfer Quantity</label>
                        <input type="number" id="trans-qty" name="quantity" class="form-control" min="0.001" step="0.001" required placeholder="0">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Available in Source: <span id="trans-avail-span">0</span> units</small>
                    </div>
                    <div class="form-group">
                        <label for="trans-notes">Transfer Note</label>
                        <input type="text" id="trans-notes" name="notes" class="form-control" placeholder="e.g. Stock replenishment for regional depot">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.WTSStock.closeTransferModal()">Cancel</button>
                <button type="submit" id="trans-submit-btn" class="btn btn-primary">Execute Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ITEM SPECIFICATIONS & BALANCES      -->
<!-- ========================================== -->
<div id="item-details-modal" class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="item-modal-title">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 id="item-modal-title" class="modal-title">Inventory Item Specifications</h3>
            <button type="button" class="modal-close" onclick="window.WTSStock.closeItemDetails()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body" id="item-details-body">
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Loading item specifications...</div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="window.WTSStock.closeItemDetails()">Close</button>
        </div>
    </div>
</div>

<!-- Page Specific Stylesheet -->
<style>
/* Tabs Styling */
.stock-tab-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.65rem 1.15rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
}
.stock-tab-btn:hover {
    color: var(--text-primary);
    background: rgba(255,255,255,0.02);
}
.stock-tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: rgba(59,130,246,0.06);
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}
.stock-tab-btn .tab-badge {
    margin-left: 8px;
    background: var(--bg-surface-elevated);
    color: var(--text-muted);
    font-size: 0.72rem;
    padding: 1px 6px;
    border-radius: var(--radius-full);
}
.stock-tab-btn.active .tab-badge {
    background: var(--color-primary);
    color: #ffffff;
}

/* Tab Panes */
.stock-tab-pane {
    display: none;
}
.stock-tab-pane.active {
    display: block;
}

/* KPI Responsive Grid */
@media (max-width: 1200px) {
    .kpi-grid-container {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}
@media (max-width: 640px) {
    .kpi-grid-container {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

.kpi-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.clickable-kpi:hover {
    filter: brightness(1.08);
}

/* Stock Table Styling */
.stock-table {
    width: 100%;
    border-collapse: collapse;
}
.stock-table th {
    position: sticky;
    top: 0;
    background: var(--bg-surface-elevated);
    z-index: 10;
    padding: 0.75rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-secondary);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
}
.stock-table th.sortable-th {
    cursor: pointer;
    user-select: none;
}
.stock-table th.sortable-th:hover {
    color: var(--text-primary);
}
.stock-table th .sort-icon {
    font-size: 0.75rem;
    margin-left: 4px;
    opacity: 0.6;
}
.stock-table td {
    padding: 0.75rem 0.85rem;
    height: 56px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
    white-space: nowrap;
}
.stock-table tbody tr {
    transition: background-color 0.12s ease;
}
.stock-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.03);
}

/* Out of Stock & Low Stock row highlighting */
.stock-row-oos {
    border-left: 3px solid var(--color-danger);
    background-color: rgba(239, 68, 68, 0.04);
}
.stock-row-low {
    border-left: 3px solid var(--color-warning);
}

/* Tabular numerals & Right alignment */
.numeric-col {
    text-align: right !important;
    font-variant-numeric: tabular-nums;
}

/* Mini Progress Bar for Stock */
.stock-mini-bar-bg {
    width: 100%;
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    margin-top: 4px;
    overflow: hidden;
}
.stock-mini-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease;
}
.stock-mini-bar-fill.bar-success {
    background: var(--color-success);
}
.stock-mini-bar-fill.bar-warning {
    background: var(--color-warning);
}
.stock-mini-bar-fill.bar-danger {
    background: var(--color-danger);
}

/* Pinned column on mobile viewports */
@media (max-width: 768px) {
    .stock-table .pinned-column {
        position: sticky;
        left: 0;
        background: var(--bg-surface);
        z-index: 5;
        box-shadow: 2px 0 5px rgba(0,0,0,0.3);
    }
    .stock-table th.pinned-column {
        z-index: 15;
        background: var(--bg-surface-elevated);
    }
}

/* Action Icon Buttons */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
    background: rgba(255,255,255,0.03);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
}
.btn-icon:hover {
    background: var(--bg-surface-elevated);
    color: var(--text-primary);
    border-color: var(--color-primary);
}

/* Bulk Action Bar */
.bulk-action-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #0f172a;
    border: 1px solid #3b82f6;
    border-radius: var(--radius-md);
    padding: 0.65rem 1.25rem;
    box-shadow: var(--shadow-xl);
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}
.btn-bulk-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(59,130,246,0.15);
    border: 1px solid #3b82f6;
    color: #93c5fd;
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
}
.btn-bulk-action:hover {
    background: #3b82f6;
    color: #fff;
}
.btn-bulk-close {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0 4px;
}
.btn-bulk-close:hover {
    color: #fff;
}

/* Pagination Controls */
.pagination-controls {
    display: flex;
    gap: 4px;
    align-items: center;
}
.page-btn {
    min-width: 30px;
    height: 30px;
    padding: 0 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}
.page-btn:hover:not(:disabled) {
    background: var(--bg-surface-elevated);
    color: var(--text-primary);
}
.page-btn.active {
    background: var(--color-primary);
    color: #ffffff;
    border-color: var(--color-primary);
}
.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Skeleton Loading Animation */
@keyframes skeleton-shimmer {
    0% { opacity: 0.3; }
    50% { opacity: 0.7; }
    100% { opacity: 0.3; }
}
.skeleton-row td > div {
    height: 14px;
    background: var(--bg-surface-elevated);
    border-radius: 4px;
    animation: skeleton-shimmer 1.4s ease-in-out infinite;
}

/* Print Stylesheet Overrides */
@media print {
    .no-print, .stock-tabs-nav, .filter-bar-card, .header-actions, .bulk-action-bar, .stock-pagination-bar {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10pt !important;
    }
    .card {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    .stock-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .stock-table th {
        background: #f1f5f9 !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        font-size: 9pt !important;
    }
    .stock-table td {
        border: 1px solid #cbd5e1 !important;
        color: #000 !important;
        font-size: 8.5pt !important;
        padding: 4px 6px !important;
        height: auto !important;
    }
    .badge {
        border: 1px solid #666 !important;
        color: #000 !important;
        background: transparent !important;
    }
}
</style>

<!-- Vanilla JS Application Controller -->
<script>
(function() {
    'use strict';

    // Global WTSStock Application Controller
    const WTSStock = {
        state: {
            tab: '<?= e($activeTab) ?>',
            search: '',
            warehouse_id: 0,
            category_id: 0,
            status: 'all',
            sort: 'id',
            dir: 'asc',
            page: 1,
            per_page: 25,
            totalPages: 1,
            totalItems: 0,
            selectedIds: new Set(),
            allowNegativeStock: <?= $allowNegativeStock ? 'true' : 'false' ?>,
            debounceTimer: null,
            loading: false,
        },

        init: function() {
            // Read URL search params on initialization
            const params = new URLSearchParams(window.location.search);
            if (params.has('tab')) this.state.tab = params.get('tab');
            if (params.has('search')) {
                this.state.search = params.get('search');
                const searchEl = document.getElementById('stock-search');
                if (searchEl) searchEl.value = this.state.search;
            }
            if (params.has('warehouse_id')) {
                this.state.warehouse_id = parseInt(params.get('warehouse_id'), 10) || 0;
                const whEl = document.getElementById('filter-warehouse');
                if (whEl) whEl.value = this.state.warehouse_id || '';
            }
            if (params.has('category_id')) {
                this.state.category_id = parseInt(params.get('category_id'), 10) || 0;
                const catEl = document.getElementById('filter-category');
                if (catEl) catEl.value = this.state.category_id || '';
            }
            if (params.has('status')) {
                this.state.status = params.get('status');
                const stEl = document.getElementById('filter-status');
                if (stEl) stEl.value = this.state.status;
            }
            if (params.has('page')) this.state.page = parseInt(params.get('page'), 10) || 1;
            if (params.has('per_page')) this.state.per_page = parseInt(params.get('per_page'), 10) || 25;

            // Bind debounced search listener (300ms)
            const searchInput = document.getElementById('stock-search');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(this.state.debounceTimer);
                    this.state.debounceTimer = setTimeout(() => {
                        this.state.search = e.target.value.trim();
                        this.state.page = 1;
                        this.updateUrl();
                        this.fetchData();
                    }, 300);
                });
            }

            // Bind escape key for modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAdjustmentModal();
                    this.closeTransferModal();
                    this.closeItemDetails();
                    this.closeExportMenu();
                }
            });

            // Close export dropdown when clicking outside
            document.addEventListener('click', (e) => {
                const wrapper = document.getElementById('export-dropdown-wrapper');
                if (wrapper && !wrapper.contains(e.target)) {
                    this.closeExportMenu();
                }
            });

            // Handle popstate for browser back/forward navigation
            window.addEventListener('popstate', (e) => {
                if (e.state) {
                    Object.assign(this.state, e.state);
                    this.syncUIWithState();
                    this.fetchData();
                }
            });

            // Initialize pagination rendering
            this.renderPagination();
        },

        // Tab Switching Logic
        switchTab: function(tabName) {
            if (tabName !== 'balances' && tabName !== 'history' && tabName !== 'ledger') return;
            this.state.tab = tabName;
            this.state.page = 1;

            // Update tab button classes
            document.querySelectorAll('.stock-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
            });

            // Update tab panes visibility
            document.querySelectorAll('.stock-tab-pane').forEach(pane => {
                pane.classList.toggle('active', pane.id === 'pane-' + tabName);
            });

            this.updateUrl();
            this.fetchData();
        },

        // Filter Bar Handlers
        onFilterChange: function() {
            const wh = document.getElementById('filter-warehouse');
            const cat = document.getElementById('filter-category');
            const st = document.getElementById('filter-status');

            this.state.warehouse_id = wh ? parseInt(wh.value, 10) || 0 : 0;
            this.state.category_id = cat ? parseInt(cat.value, 10) || 0 : 0;
            this.state.status = st ? st.value : 'all';
            this.state.page = 1;

            this.updateUrl();
            this.fetchData();
        },

        clearFilters: function() {
            this.state.search = '';
            this.state.warehouse_id = 0;
            this.state.category_id = 0;
            this.state.status = 'all';
            this.state.page = 1;

            const s = document.getElementById('stock-search');
            if (s) s.value = '';
            const wh = document.getElementById('filter-warehouse');
            if (wh) wh.value = '';
            const cat = document.getElementById('filter-category');
            if (cat) cat.value = '';
            const st = document.getElementById('filter-status');
            if (st) st.value = 'all';

            this.updateUrl();
            this.fetchData();
        },

        // Clickable KPI Card Filter
        applyStatusFilter: function(statusKey) {
            const st = document.getElementById('filter-status');
            if (st) st.value = statusKey;
            this.state.status = statusKey;
            this.state.page = 1;
            this.switchTab('balances');
        },

        // Sorting
        applySort: function(field) {
            if (this.state.sort === field) {
                this.state.dir = this.state.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.state.sort = field;
                this.state.dir = 'asc';
            }
            this.updateUrl();
            this.fetchData();
        },

        // Pagination Per Page
        changePerPage: function(val) {
            this.state.per_page = parseInt(val, 10) || 25;
            this.state.page = 1;
            this.updateUrl();
            this.fetchData();
        },

        goToPage: function(p) {
            this.state.page = p;
            this.updateUrl();
            this.fetchData();
        },

        // URL Synchronization with history.pushState
        updateUrl: function() {
            const params = new URLSearchParams();
            if (this.state.tab !== 'balances') params.set('tab', this.state.tab);
            if (this.state.search) params.set('search', this.state.search);
            if (this.state.warehouse_id > 0) params.set('warehouse_id', this.state.warehouse_id);
            if (this.state.category_id > 0) params.set('category_id', this.state.category_id);
            if (this.state.status && this.state.status !== 'all') params.set('status', this.state.status);
            if (this.state.sort && this.state.sort !== 'id') params.set('sort', this.state.sort);
            if (this.state.dir && this.state.dir !== 'asc') params.set('dir', this.state.dir);
            if (this.state.page > 1) params.set('page', this.state.page);
            if (this.state.per_page !== 25) params.set('per_page', this.state.per_page);

            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({ ...this.state }, '', newUrl);

            // Update print header status
            const printSt = document.getElementById('print-filter-status');
            if (printSt) {
                printSt.textContent = this.state.status.toUpperCase();
            }
        },

        syncUIWithState: function() {
            const s = document.getElementById('stock-search');
            if (s) s.value = this.state.search || '';
            const wh = document.getElementById('filter-warehouse');
            if (wh) wh.value = this.state.warehouse_id || '';
            const cat = document.getElementById('filter-category');
            if (cat) cat.value = this.state.category_id || '';
            const st = document.getElementById('filter-status');
            if (st) st.value = this.state.status || 'all';

            document.querySelectorAll('.stock-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === this.state.tab);
            });
            document.querySelectorAll('.stock-tab-pane').forEach(pane => {
                pane.classList.toggle('active', pane.id === 'pane-' + this.state.tab);
            });
        },

        // AJAX Data Fetching
        fetchData: async function() {
            const tbodyId = this.state.tab + '-tbody';
            const tbody = document.getElementById(tbodyId);
            const errBanner = document.getElementById('stock-error-banner');
            if (errBanner) errBanner.style.display = 'none';

            this.showSkeleton(tbodyId);
            this.state.loading = true;

            const query = new URLSearchParams({
                tab: this.state.tab,
                search: this.state.search,
                warehouse_id: this.state.warehouse_id,
                category_id: this.state.category_id,
                status: this.state.status,
                sort: this.state.sort,
                dir: this.state.dir,
                page: this.state.page,
                per_page: this.state.per_page,
            });

            try {
                const resp = await fetch('/api/inventory/stock?' + query.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const res = await resp.json();

                if (!resp.ok || !res.success) {
                    throw new Error(res.message || 'Failed to retrieve inventory data.');
                }

                // Update KPIs if returned
                if (res.totals) {
                    this.updateKPIs(res.totals);
                }

                // Render matching tab rows
                if (this.state.tab === 'balances') {
                    this.renderBalancesRows(res.rows || res.data || []);
                    this.updatePaginationInfo('balances', res.pagination || {});
                } else if (this.state.tab === 'history') {
                    this.renderHistoryRows(res.rows || res.data || []);
                    this.updatePaginationInfo('history', res.pagination || {});
                } else if (this.state.tab === 'ledger') {
                    this.renderLedgerRows(res.rows || res.data || []);
                    this.updatePaginationInfo('ledger', res.pagination || {});
                }

            } catch (err) {
                console.error('Fetch stock error:', err);
                if (errBanner) {
                    const msgEl = document.getElementById('stock-error-message');
                    if (msgEl) msgEl.textContent = err.message || 'Network or server error loading stock balances.';
                    errBanner.style.display = 'flex';
                }
            } finally {
                this.state.loading = false;
            }
        },

        showSkeleton: function(tbodyId) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            const cols = tbodyId === 'balances-tbody' ? 14 : (tbodyId === 'history-tbody' ? 11 : 9);
            let html = '';
            for (let i = 0; i < 5; i++) {
                html += '<tr class="skeleton-row">';
                for (let j = 0; j < cols; j++) {
                    html += '<td><div></div></td>';
                }
                html += '</tr>';
            }
            tbody.innerHTML = html;
        },

        updateKPIs: function(totals) {
            const elUnits = document.getElementById('kpi-total-units');
            if (elUnits) elUnits.textContent = totals.total_units_formatted || '0';

            const elVal = document.getElementById('kpi-total-valuation');
            if (elVal) elVal.textContent = totals.total_valuation_formatted || '₹0.00';

            const elRes = document.getElementById('kpi-total-reserved');
            if (elRes) {
                elRes.textContent = totals.total_reserved_formatted || '0';
                const resVal = parseFloat(totals.total_reserved || 0);
                elRes.style.color = resVal > 0 ? 'var(--color-warning)' : 'var(--text-secondary)';
            }

            const elLow = document.getElementById('kpi-low-stock');
            if (elLow) elLow.textContent = totals.low_stock_count || 0;

            const elOos = document.getElementById('kpi-out-of-stock');
            if (elOos) elOos.textContent = totals.out_of_stock_count || 0;
        },

        renderBalancesRows: function(rows) {
            const tbody = document.getElementById('balances-tbody');
            if (!tbody) return;

            const stockCount = document.getElementById('stock-count');
            if (stockCount) stockCount.textContent = rows.length;

            const tabBadge = document.getElementById('tab-count-balances');
            if (tabBadge) tabBadge.textContent = rows.length;

            if (rows.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="14" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-secondary);">No items match your filters</div>
                            <p style="font-size: 0.85rem; margin-bottom: 1rem;">Try clearing your search query or warehouse filters.</p>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.WTSStock.clearFilters()">Clear Filters</button>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            rows.forEach((sb, i) => {
                const isOos = sb.status === 'OUT OF STOCK';
                const isLow = sb.status === 'LOW STOCK';
                const isOver = sb.status === 'OVERSTOCK';
                const rowClass = isOos ? 'stock-row-oos' : (isLow ? 'stock-row-low' : '');
                const statusBadgeClass = isOos ? 'badge-danger' : (isLow ? 'badge-warning' : (isOver ? 'badge-info' : 'badge-success'));
                const isChecked = this.state.selectedIds.has(String(sb.id));

                html += `
                    <tr class="${rowClass}" data-id="${sb.id}">
                        <td style="text-align: center;" class="no-print">
                            <input type="checkbox" class="stock-row-check" value="${sb.id}" ${isChecked ? 'checked' : ''} onchange="window.WTSStock.onRowCheckChange()">
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;">${(this.state.page - 1) * this.state.per_page + i + 1}</td>
                        <td class="pinned-column">
                            <div style="font-weight: 600; color: var(--text-primary);">${this.escapeHtml(sb.product_name)}</div>
                            <div style="font-size: 0.75rem; color: #cbd5e1; margin-top: 2px;">
                                SKU: <code style="color: #93c5fd; background: rgba(59,130,246,0.15); padding: 1px 4px; border-radius: 3px;">${this.escapeHtml(sb.sku)}</code>
                                ${sb.barcode ? `&bull; <span style="color: var(--text-muted);">Barcode: ${this.escapeHtml(sb.barcode)}</span>` : ''}
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background: rgba(148,163,184,0.15); color: var(--text-secondary); font-size: 0.75rem;">
                                ${this.escapeHtml(sb.category_name || 'General')}
                            </span>
                        </td>
                        <td>${this.escapeHtml(sb.warehouse_name || 'Central Hub')}</td>
                        
                        <td class="numeric-col" style="font-weight: 700;">
                            <div>${Number(sb.quantity).toLocaleString('en-IN')} <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 400;">${this.escapeHtml(sb.unit || 'Pcs')}</span></div>
                            <div class="stock-mini-bar-bg" title="On Hand vs Reorder Threshold">
                                <div class="stock-mini-bar-fill ${isOos ? 'bar-danger' : (isLow ? 'bar-warning' : 'bar-success')}" 
                                     style="width: ${sb.stock_bar_percent || 50}%;"></div>
                            </div>
                        </td>

                        <td class="numeric-col" style="color: ${parseFloat(sb.reserved_quantity) > 0 ? 'var(--color-warning)' : 'var(--text-muted)'};">
                            ${Number(sb.reserved_quantity).toLocaleString('en-IN')}
                        </td>

                        <td class="numeric-col" style="font-weight: 700; color: ${parseFloat(sb.available_quantity) > 0 ? 'var(--color-success)' : 'var(--color-danger)'};">
                            ${Number(sb.available_quantity).toLocaleString('en-IN')}
                        </td>

                        <td class="numeric-col" style="color: var(--text-muted);">
                            ${Number(sb.reorder_level).toLocaleString('en-IN')}
                        </td>

                        <td class="numeric-col">${sb.unit_price_formatted}</td>

                        <td class="numeric-col" style="font-weight: 700; color: var(--color-primary);">${sb.stock_value_formatted}</td>

                        <td style="text-align: center; font-size: 0.8rem; color: var(--text-muted);">${sb.last_movement || '—'}</td>

                        <td style="text-align: center;">
                            <span class="badge ${statusBadgeClass}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 700;">
                                ${sb.status}
                            </span>
                        </td>

                        <td style="text-align: right;" class="no-print">
                            <div style="display: flex; gap: 4px; justify-content: flex-end;">
                                <button type="button" class="btn-icon" title="Adjust Stock" onclick="window.WTSStock.openAdjustmentModal(${sb.product_id}, ${sb.warehouse_id})" aria-label="Adjust Stock">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                </button>
                                <button type="button" class="btn-icon" title="View Ledger" onclick="window.WTSStock.viewItemLedger(${sb.product_id})" aria-label="View Ledger">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                                </button>
                                <button type="button" class="btn-icon" title="Warehouse Transfer" onclick="window.WTSStock.openTransferModal(${sb.product_id}, ${sb.warehouse_id}, ${sb.available_quantity})" aria-label="Warehouse Transfer">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                                </button>
                                <button type="button" class="btn-icon" title="Item Details" onclick="window.WTSStock.openItemDetails(${sb.product_id})" aria-label="Item Details">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        renderHistoryRows: function(rows) {
            const tbody = document.getElementById('history-tbody');
            if (!tbody) return;

            const tabBadge = document.getElementById('tab-count-history');
            if (tabBadge) tabBadge.textContent = rows.length;

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">No physical adjustment history found matching your filters.</td></tr>';
                return;
            }

            let html = '';
            rows.forEach(h => {
                const isInc = h.type === 'Increase' || h.type === 'Opening Stock';
                html += `
                    <tr>
                        <td style="font-size: 0.825rem; color: var(--text-muted);">${h.date}</td>
                        <td><code style="color: #93c5fd;">${this.escapeHtml(h.adjustment_number)}</code></td>
                        <td>
                            <strong>${this.escapeHtml(h.product_name)}</strong>
                            <div style="font-size: 0.75rem; color: #cbd5e1;">SKU: ${this.escapeHtml(h.sku)}</div>
                        </td>
                        <td>${this.escapeHtml(h.warehouse_name)}</td>
                        <td style="text-align: center;">
                            <span class="badge ${isInc ? 'badge-success' : 'badge-danger'}">${h.type}</span>
                        </td>
                        <td class="numeric-col" style="font-weight: 700;">
                            ${(h.direction === 'OUT' ? '-' : '+') + Number(h.quantity).toLocaleString('en-IN')}
                        </td>
                        <td class="numeric-col">${h.unit_cost_formatted}</td>
                        <td class="numeric-col" style="font-weight: 700; color: var(--color-primary);">${h.total_cost_formatted}</td>
                        <td style="font-size: 0.825rem; max-width: 200px;">${this.escapeHtml(h.reason + (h.notes ? ' (' + h.notes + ')' : ''))}</td>
                        <td style="font-size: 0.825rem; color: var(--text-secondary);">${this.escapeHtml(h.user)}</td>
                        <td style="text-align: center;"><span class="badge badge-success">POSTED</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        renderLedgerRows: function(rows) {
            const tbody = document.getElementById('ledger-tbody');
            if (!tbody) return;

            const tabBadge = document.getElementById('tab-count-ledger');
            if (tabBadge) tabBadge.textContent = rows.length;

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">No stock movements recorded matching your filters.</td></tr>';
                return;
            }

            let html = '';
            rows.forEach(l => {
                html += `
                    <tr>
                        <td style="font-size: 0.825rem; color: var(--text-muted);">${l.movement_date}</td>
                        <td>
                            <strong>${this.escapeHtml(l.product_name)}</strong>
                            <div style="font-size: 0.75rem; color: #cbd5e1;">SKU: ${this.escapeHtml(l.sku)}</div>
                        </td>
                        <td>${this.escapeHtml(l.warehouse_name)}</td>
                        <td>
                            <span class="badge" style="background: rgba(59,130,246,0.15); color: #93c5fd; font-size: 0.72rem;">
                                ${this.escapeHtml(l.movement_type)}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge ${l.direction === 'IN' ? 'badge-success' : 'badge-danger'}">
                                ${l.direction}
                            </span>
                        </td>
                        <td class="numeric-col" style="font-weight: 700; color: ${l.direction === 'IN' ? 'var(--color-success)' : 'var(--color-danger)'};">
                            ${(l.direction === 'IN' ? '+' : '-') + Number(l.quantity).toLocaleString('en-IN')}
                        </td>
                        <td class="numeric-col" style="font-weight: 700;">${Number(l.balance_after).toLocaleString('en-IN')}</td>
                        <td><code style="color: #f59e0b;">${this.escapeHtml(l.reference_number)}</code></td>
                        <td style="font-size: 0.825rem; color: var(--text-secondary);">${this.escapeHtml(l.notes)}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        updatePaginationInfo: function(prefix, pagination) {
            const pageInfo = document.getElementById(prefix + '-page-info');
            if (pageInfo) {
                const from = pagination.from || 0;
                const to = pagination.to || 0;
                const total = pagination.total || 0;
                pageInfo.textContent = `Showing ${from}-${to} of ${total} items`;
            }

            this.state.totalPages = pagination.last_page || 1;
            this.state.totalItems = pagination.total || 0;
            this.renderPagination(prefix);
        },

        renderPagination: function(prefix) {
            const currentPrefix = prefix || this.state.tab;
            const container = document.getElementById(currentPrefix + '-pagination');
            if (!container) return;

            const total = this.state.totalPages;
            const current = this.state.page;

            let html = '';
            html += `<button type="button" class="page-btn" ${current <= 1 ? 'disabled' : ''} onclick="window.WTSStock.goToPage(${current - 1})">&larr;</button>`;

            const maxVisible = 5;
            let start = Math.max(1, current - 2);
            let end = Math.min(total, start + maxVisible - 1);
            if (end - start < maxVisible - 1) {
                start = Math.max(1, end - maxVisible + 1);
            }

            for (let i = start; i <= end; i++) {
                html += `<button type="button" class="page-btn ${i === current ? 'active' : ''}" onclick="window.WTSStock.goToPage(${i})">${i}</button>`;
            }

            html += `<button type="button" class="page-btn" ${current >= total ? 'disabled' : ''} onclick="window.WTSStock.goToPage(${current + 1})">&rarr;</button>`;
            container.innerHTML = html;
        },

        // Row Selection & Bulk Action Bar
        toggleCheckAll: function(masterCheck) {
            const checkboxes = document.querySelectorAll('.stock-row-check');
            checkboxes.forEach(cb => {
                cb.checked = masterCheck.checked;
                if (masterCheck.checked) {
                    this.state.selectedIds.add(cb.value);
                } else {
                    this.state.selectedIds.delete(cb.value);
                }
            });
            this.updateBulkActionBar();
        },

        onRowCheckChange: function() {
            this.state.selectedIds.clear();
            document.querySelectorAll('.stock-row-check:checked').forEach(cb => {
                this.state.selectedIds.add(cb.value);
            });
            this.updateBulkActionBar();
        },

        updateBulkActionBar: function() {
            const bar = document.getElementById('bulk-action-bar');
            const countEl = document.getElementById('bulk-selected-count');
            const count = this.state.selectedIds.size;

            if (bar) {
                bar.style.display = count > 0 ? 'flex' : 'none';
            }
            if (countEl) {
                countEl.textContent = count;
            }
        },

        deselectAll: function() {
            this.state.selectedIds.clear();
            document.querySelectorAll('.stock-row-check').forEach(cb => cb.checked = false);
            const master = document.getElementById('check-all-balances');
            if (master) master.checked = false;
            this.updateBulkActionBar();
        },

        bulkExportSelected: function() {
            const ids = Array.from(this.state.selectedIds).join(',');
            window.location.href = `/inventory/stock/export?format=csv&ids=${ids}`;
        },

        openBulkAdjustModal: function() {
            this.openAdjustmentModal();
        },

        printLabels: function() {
            window.print();
        },

        // Export Dropdown Trigger
        toggleExportMenu: function(e) {
            e.stopPropagation();
            const menu = document.getElementById('export-menu');
            if (menu) {
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            }
        },

        closeExportMenu: function() {
            const menu = document.getElementById('export-menu');
            if (menu) menu.style.display = 'none';
        },

        triggerExport: function(format) {
            this.closeExportMenu();
            const query = new URLSearchParams({
                format: format,
                search: this.state.search,
                warehouse_id: this.state.warehouse_id,
                category_id: this.state.category_id,
                status: this.state.status,
                sort: this.state.sort,
                dir: this.state.dir,
            });
            window.location.href = '/inventory/stock/export?' + query.toString();
        },

        // Modal: Record Adjustment Logic
        openAdjustmentModal: function(productId, warehouseId) {
            const form = document.getElementById('stock-form');
            if (form) form.reset();

            const errBox = document.getElementById('stock-modal-error');
            if (errBox) errBox.style.display = 'none';

            const submitBtn = document.getElementById('adj-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Adjustment';
            }

            const pSelect = document.getElementById('adj-product');
            if (pSelect && productId) {
                pSelect.value = productId;
                this.onAdjustmentProductChange();
            }

            const wSelect = document.getElementById('adj-warehouse');
            if (wSelect && warehouseId) {
                wSelect.value = warehouseId;
            }

            this.updateAdjustmentPreview();

            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.open('stock-modal');
            } else {
                document.getElementById('stock-modal').classList.add('active');
            }
        },

        closeAdjustmentModal: function() {
            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.close('stock-modal');
            } else {
                const m = document.getElementById('stock-modal');
                if (m) m.classList.remove('active');
            }
        },

        onAdjustmentProductChange: function() {
            const pSelect = document.getElementById('adj-product');
            const costInput = document.getElementById('adj-cost');
            if (!pSelect || !costInput) return;

            const selectedOpt = pSelect.options[pSelect.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                const cost = selectedOpt.getAttribute('data-cost') || selectedOpt.getAttribute('data-price') || '0';
                costInput.value = parseFloat(cost).toFixed(2);
            }
            this.updateAdjustmentPreview();
        },

        updateAdjustmentPreview: function() {
            const pSelect = document.getElementById('adj-product');
            const typeSelect = document.getElementById('adj-type');
            const qtyInput = document.getElementById('adj-qty');
            const curSpan = document.getElementById('adj-preview-cur');
            const newSpan = document.getElementById('adj-preview-new');
            const badge = document.getElementById('adj-preview-badge');
            const negWarning = document.getElementById('adj-neg-warning');
            const submitBtn = document.getElementById('adj-submit-btn');

            if (!pSelect || !typeSelect || !qtyInput) return;

            const selectedOpt = pSelect.options[pSelect.selectedIndex];
            const currentStock = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-stock') || 0) : 0;
            const inputQty = parseFloat(qtyInput.value) || 0;
            const type = typeSelect.value;

            let newStock = currentStock;
            if (type === 'Increase') {
                newStock = currentStock + inputQty;
            } else if (type === 'Decrease' || type === 'Write-off' || type === 'Damage') {
                newStock = currentStock - inputQty;
            } else if (type === 'Opening Stock') {
                newStock = inputQty;
            }

            if (curSpan) curSpan.textContent = currentStock.toLocaleString('en-IN');
            if (newSpan) {
                newSpan.textContent = newStock.toLocaleString('en-IN');
                newSpan.style.color = newStock < 0 ? 'var(--color-danger)' : 'var(--color-primary)';
            }

            // Check negative stock barrier
            const isNegativeViolation = newStock < 0 && !this.state.allowNegativeStock;
            if (negWarning) {
                negWarning.style.display = isNegativeViolation ? 'block' : 'none';
            }
            if (submitBtn) {
                submitBtn.disabled = isNegativeViolation;
            }

            if (badge) {
                const diff = newStock - currentStock;
                if (diff > 0) {
                    badge.textContent = `+${diff.toLocaleString('en-IN')} Units`;
                    badge.style.color = 'var(--color-success)';
                } else if (diff < 0) {
                    badge.textContent = `${diff.toLocaleString('en-IN')} Units`;
                    badge.style.color = 'var(--color-danger)';
                } else {
                    badge.textContent = 'No change';
                    badge.style.color = 'var(--text-muted)';
                }
            }
        },

        saveAdjustment: async function(e) {
            e.preventDefault();
            const form = document.getElementById('stock-form');
            const errBox = document.getElementById('stock-modal-error');
            const submitBtn = document.getElementById('adj-submit-btn');

            if (errBox) {
                errBox.style.display = 'none';
                errBox.textContent = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Posting...';
            }

            const formData = new FormData(form);

            try {
                const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch('/api/inventory/adjustments', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': metaToken,
                    },
                    body: formData,
                });

                const res = await resp.json();

                if (resp.ok && res.success) {
                    this.closeAdjustmentModal();
                    if (window.WTSBill && window.WTSBill.Toast) {
                        window.WTSBill.Toast.show(res.message || 'Stock adjustment posted successfully!', 'success');
                    }
                    this.fetchData();
                } else {
                    if (errBox) {
                        errBox.style.display = 'block';
                        if (res.errors) {
                            errBox.innerHTML = Object.values(res.errors).join('<br>');
                        } else {
                            errBox.textContent = res.message || 'Validation error saving adjustment.';
                        }
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Post Adjustment';
                    }
                }
            } catch (err) {
                console.error('Save stock adjustment error:', err);
                if (errBox) {
                    errBox.style.display = 'block';
                    errBox.textContent = 'Network or server error while posting adjustment.';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Adjustment';
                }
            }
        },

        // Modal: Quick Transfer Logic
        openTransferModal: function(productId, warehouseId, availQty) {
            const form = document.getElementById('transfer-form');
            if (form) form.reset();

            const pInput = document.getElementById('trans-product-id');
            if (pInput) pInput.value = productId;

            const pSelect = document.getElementById('adj-product');
            const opt = pSelect ? pSelect.querySelector(`option[value="${productId}"]`) : null;
            const pNameInput = document.getElementById('trans-product-name');
            if (pNameInput && opt) pNameInput.value = opt.textContent.trim();

            const sSelect = document.getElementById('trans-source');
            if (sSelect && warehouseId) sSelect.value = warehouseId;

            const availSpan = document.getElementById('trans-avail-span');
            if (availSpan) availSpan.textContent = availQty || 0;

            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.open('transfer-modal');
            } else {
                document.getElementById('transfer-modal').classList.add('active');
            }
        },

        closeTransferModal: function() {
            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.close('transfer-modal');
            } else {
                const m = document.getElementById('transfer-modal');
                if (m) m.classList.remove('active');
            }
        },

        saveTransfer: async function(e) {
            e.preventDefault();
            const form = document.getElementById('transfer-form');
            const formData = new FormData(form);
            formData.append('adjustment_type', 'Transfer');

            try {
                const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch('/api/inventory/adjustments', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': metaToken,
                    },
                    body: formData,
                });
                const res = await resp.json();
                if (resp.ok && res.success) {
                    this.closeTransferModal();
                    if (window.WTSBill && window.WTSBill.Toast) {
                        window.WTSBill.Toast.show('Inter-warehouse transfer executed successfully!', 'success');
                    }
                    this.fetchData();
                } else {
                    const errBox = document.getElementById('transfer-modal-error');
                    if (errBox) {
                        errBox.style.display = 'block';
                        errBox.textContent = res.message || 'Transfer failed.';
                    }
                }
            } catch (err) {
                console.error('Transfer error:', err);
            }
        },

        // Item Details View
        openItemDetails: function(productId) {
            const modalBody = document.getElementById('item-details-body');
            if (!modalBody) return;

            const pSelect = document.getElementById('adj-product');
            const opt = pSelect ? pSelect.querySelector(`option[value="${productId}"]`) : null;
            if (!opt) return;

            const pName = opt.textContent.split('(')[0].trim();
            const sku = opt.getAttribute('data-sku') || 'N/A';
            const cost = opt.getAttribute('data-cost') || '0';
            const price = opt.getAttribute('data-price') || '0';
            const curStock = opt.getAttribute('data-stock') || '0';

            modalBody.innerHTML = `
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        <h4 style="font-size: 1.15rem; color: var(--text-primary); margin: 0;">${this.escapeHtml(pName)}</h4>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">SKU: <code>${this.escapeHtml(sku)}</code></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="padding: 0.75rem; background: var(--bg-surface-elevated); border-radius: var(--radius-sm);">
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Standard Selling Price</div>
                            <div style="font-size: 1.2rem; font-weight: 700; color: var(--color-primary); margin-top: 2px;">₹${parseFloat(price).toFixed(2)}</div>
                        </div>
                        <div style="padding: 0.75rem; background: var(--bg-surface-elevated); border-radius: var(--radius-sm);">
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Purchase Cost Basis</div>
                            <div style="font-size: 1.2rem; font-weight: 700; color: var(--color-success); margin-top: 2px;">₹${parseFloat(cost).toFixed(2)}</div>
                        </div>
                    </div>

                    <div style="padding: 0.75rem; background: var(--bg-surface-elevated); border-radius: var(--radius-sm);">
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Aggregated Physical Stock</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin-top: 2px;">
                            ${parseFloat(curStock).toLocaleString('en-IN')} Units
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.WTSStock.viewItemLedger(${productId})">View Stock Movement Ledger</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.WTSStock.closeItemDetails(); window.WTSStock.openAdjustmentModal(${productId});">Adjust Stock</button>
                    </div>
                </div>
            `;

            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.open('item-details-modal');
            } else {
                document.getElementById('item-details-modal').classList.add('active');
            }
        },

        closeItemDetails: function() {
            if (window.WTSBill && window.WTSBill.Modal) {
                window.WTSBill.Modal.close('item-details-modal');
            } else {
                const m = document.getElementById('item-details-modal');
                if (m) m.classList.remove('active');
            }
        },

        viewItemLedger: function(productId) {
            this.closeItemDetails();
            const pSelect = document.getElementById('adj-product');
            const opt = pSelect ? pSelect.querySelector(`option[value="${productId}"]`) : null;
            if (opt) {
                this.state.search = opt.getAttribute('data-sku') || '';
                const s = document.getElementById('stock-search');
                if (s) s.value = this.state.search;
            }
            this.switchTab('ledger');
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Attach to global window
    window.WTSStock = WTSStock;

    // Backward compatibility helpers for modal opening
    window.openStockModal = function() {
        window.WTSStock.openAdjustmentModal();
    };
    window.closeStockModal = function() {
        window.WTSStock.closeAdjustmentModal();
    };
    window.saveStockAdjustment = function(e) {
        window.WTSStock.saveAdjustment(e);
    };

    // Initialize when DOM content is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => WTSStock.init());
    } else {
        WTSStock.init();
    }
})();
</script>
