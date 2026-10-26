<?php
/**
 * WTSBill ERP — Professional Accountant-Grade Balance Sheet Statement
 *
 * @var array $statement
 * @var string $asOf
 * @var string $compare
 */

$company = $statement['company'] ?? [
    'name' => 'Wis Technosavvy Pvt Ltd',
    'gstin' => '27AADCW7577N1ZE',
];
$financialYear = $statement['financial_year'] ?? 'FY 2026-27';
$asOfFormatted = $statement['as_of_formatted'] ?? date('d F Y');
$isBalanced = (bool)($statement['balanced'] ?? true);
$diffFormatted = $statement['difference_formatted'] ?? '₹ 0.00';
$activeCompare = $statement['compare'] ?? 'none';
$hasComparison = ($activeCompare !== 'none');
?>

<!-- Print-Only Statement Header (Visible only when printed/PDF) -->
<div class="print-statement-header print-only">
    <div class="print-company-name"><?= e($company['name']) ?></div>
    <div class="print-company-meta">
        <span>GSTIN: <?= e($company['gstin']) ?></span> &bull; 
        <span><?= e($financialYear) ?></span> &bull; 
        <span>Branch: <?= e($statement['branch']['name'] ?? 'Main Branch') ?></span>
    </div>
    <h2 class="print-doc-title">BALANCE SHEET STATEMENT</h2>
    <div class="print-subtitle">
        As of <span id="print-asof-date"><?= e($asOfFormatted) ?></span>
        <?php if ($hasComparison && !empty($statement['compare_formatted'])): ?>
            (Comparative with <?= e($statement['compare_formatted']) ?>)
        <?php endif; ?>
    </div>
    <div class="print-status-bar">
        <?php if ($isBalanced): ?>
            <span class="print-status-badge print-status-ok">&check; BALANCED (Accounting Equation: Assets = Liabilities + Equity)</span>
        <?php else: ?>
            <span class="print-status-badge print-status-err">&times; UNBALANCED &bull; Variance: <?= e($diffFormatted) ?></span>
        <?php endif; ?>
        <span class="print-timestamp">Generated on: <?= e($statement['generated_at'] ?? date('d-M-Y H:i:s T')) ?></span>
    </div>
</div>

<!-- Screen Page Header with Title, Balance Indicator, Filters & Actions -->
<div class="page-header balance-sheet-header no-print">
    <div class="header-title-block">
        <div class="title-with-badge">
            <h1 class="page-title">Balance Sheet</h1>
            <!-- Requirement 4: Balance Check Indicator -->
            <div id="balance-badge-container">
                <?php if ($isBalanced): ?>
                    <span id="balance-check-badge" class="badge badge-success balance-badge" title="Accounting Equation verified: Assets = Liabilities + Equity (Tolerance ₹0.01)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Balanced</span>
                    </span>
                <?php else: ?>
                    <span id="balance-check-badge" class="badge badge-danger balance-badge has-tooltip" 
                          title="Assets and Liabilities+Equity do not match. Check unposted or unbalanced journal entries."
                          tabindex="0" role="tooltip">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <span>Difference: <?= e($diffFormatted) ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <p class="page-subtitle" id="statement-subtitle">
            As of <span id="subtitle-date-text"><?= e($asOfFormatted) ?></span> &bull; <?= e($financialYear) ?> &bull; Assets, Liabilities, and Equity verifying accounting balance equation
        </p>
    </div>

    <!-- Controls Toolbar (Period Selector, Comparison Toggle, Export & Print) -->
    <div class="balance-sheet-toolbar">
        <!-- Requirement 1: Period Selector -->
        <div class="toolbar-group period-selector-group">
            <label for="bs-preset-select" class="toolbar-label">Period</label>
            <select id="bs-preset-select" class="form-select toolbar-select" aria-label="Select Date Preset">
                <option value="today">Today</option>
                <option value="end_last_month">End of Last Month</option>
                <option value="end_quarter">End of Quarter</option>
                <option value="end_fy">End of Financial Year</option>
                <option value="custom">Custom Date</option>
            </select>
            <div class="asof-date-wrapper">
                <label for="bs-asof-date" class="sr-only">As of Date</label>
                <input type="date" id="bs-asof-date" class="form-control toolbar-date" value="<?= e($asOf) ?>" title="As of Date">
            </div>
        </div>

        <!-- Requirement 2: Comparison Toggle -->
        <div class="toolbar-group comparison-group">
            <label for="bs-compare-select" class="toolbar-label">Compare With</label>
            <select id="bs-compare-select" class="form-select toolbar-select" aria-label="Compare with prior period">
                <option value="none" <?= $activeCompare === 'none' ? 'selected' : '' ?>>None</option>
                <option value="prev_month" <?= $activeCompare === 'prev_month' ? 'selected' : '' ?>>Previous Month</option>
                <option value="prev_quarter" <?= $activeCompare === 'prev_quarter' ? 'selected' : '' ?>>Previous Quarter</option>
                <option value="prev_year" <?= $activeCompare === 'prev_year' ? 'selected' : '' ?>>Previous Year</option>
            </select>
        </div>

        <!-- Requirement 3: Export & Print Action Group -->
        <div class="toolbar-group action-buttons-group">
            <!-- Export Dropdown -->
            <div class="export-dropdown-wrapper" style="position: relative; display: inline-block;">
                <button type="button" id="bs-export-btn" class="btn btn-primary export-btn" 
                        aria-haspopup="true" aria-expanded="false" title="Export statement">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Export</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div id="bs-export-menu" class="dropdown-menu export-menu" style="min-width: 170px;">
                    <button type="button" class="dropdown-item" onclick="WTSBill.BalanceSheet.export('pdf')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span>PDF Document</span>
                    </button>
                    <button type="button" class="dropdown-item" onclick="WTSBill.BalanceSheet.export('xlsx')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="17"></line><line x1="16" y1="13" x2="8" y2="17"></line></svg>
                        <span>Excel Spreadsheet (.xlsx)</span>
                    </button>
                    <button type="button" class="dropdown-item" onclick="WTSBill.BalanceSheet.export('csv')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                        <span>CSV Data Stream</span>
                    </button>
                </div>
            </div>

            <!-- Print Button (Secondary) -->
            <button type="button" class="btn btn-secondary print-btn" onclick="window.print()" title="Print Statement (Ctrl+P)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Print</span>
            </button>
        </div>
    </div>
</div>

<!-- Balance Sheet Dual-Card Statement Grid (Requirement 6: Equal height & baseline alignment) -->
<div class="balance-sheet-container">
    <div class="balance-sheet-grid">
        <!-- ======================= LEFT: TOTAL ASSETS ======================= -->
        <div class="card statement-card assets-card" id="assets-card">
            <div class="card-header statement-card-header assets-header">
                <div class="header-left-part">
                    <span class="card-category-indicator indicator-asset"></span>
                    <h3 class="card-title">TOTAL ASSETS</h3>
                </div>
                <div class="header-amount-block">
                    <span class="statement-hero-amount tabular-nums" id="hero-assets-amount"><?= e($statement['assets']['total_formatted']) ?></span>
                    <?php if ($hasComparison): ?>
                        <div class="hero-delta-badge delta-<?= e($statement['assets']['change_direction']) ?>" id="hero-assets-delta">
                            <?= $statement['assets']['change_direction'] === 'up' ? '▲' : ($statement['assets']['change_direction'] === 'down' ? '▼' : '•') ?>
                            <?= e($statement['assets']['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$statement['assets']['change_percent']) ?>)
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body statement-card-body">
                <div class="statement-table-wrapper" id="assets-table-container">
                    <table class="table statement-table" id="assets-table">
                        <thead>
                            <tr class="statement-col-headers">
                                <th class="col-particulars">Account Category / Ledger</th>
                                <th class="col-amount tabular-nums" id="col-assets-current">Current (<?= e($asOfFormatted) ?>)</th>
                                <?php if ($hasComparison): ?>
                                    <th class="col-prev tabular-nums" id="col-assets-previous">Previous (<?= e($statement['compare_formatted'] ?? '') ?>)</th>
                                    <th class="col-change tabular-nums" id="col-assets-change">Change</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="assets-tbody">
                            <?php foreach ($statement['assets']['groups'] as $grp): ?>
                                <tr class="statement-group-header-row">
                                    <td colspan="<?= $hasComparison ? '4' : '2' ?>" class="group-title-cell">
                                        <?= e($grp['title']) ?>
                                    </td>
                                </tr>
                                <?php foreach ($grp['accounts'] as $acc): ?>
                                    <tr class="statement-row">
                                        <td class="cell-name">
                                            <span class="account-bullet">•</span>
                                            <span><?= e($acc['name']) ?></span>
                                            <span class="account-code-tag"><?= e($acc['code']) ?></span>
                                        </td>
                                        <td class="cell-amount tabular-nums"><?= e($acc['amount_formatted']) ?></td>
                                        <?php if ($hasComparison): ?>
                                            <td class="cell-prev tabular-nums"><?= e($acc['previous_amount_formatted']) ?></td>
                                            <td class="cell-change tabular-nums delta-asset-<?= e($acc['change_direction']) ?>">
                                                <?= $acc['change_direction'] === 'up' ? '▲' : ($acc['change_direction'] === 'down' ? '▼' : '•') ?>
                                                <?= e($acc['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$acc['change_percent']) ?>)
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="statement-subtotal-row">
                                    <td class="cell-subtotal-label">Subtotal <?= e($grp['title']) ?></td>
                                    <td class="cell-subtotal-amt tabular-nums"><?= e($statement['assets']['groups'][0]['subtotal_formatted'] ?? format_currency((float)$grp['subtotal'])) ?></td>
                                    <?php if ($hasComparison): ?>
                                        <td class="cell-subtotal-prev tabular-nums"><?= format_currency((float)$grp['previous_subtotal']) ?></td>
                                        <td class="cell-subtotal-change tabular-nums delta-asset-<?= e($grp['change_direction']) ?>">
                                            <?= $grp['change_direction'] === 'up' ? '▲' : ($grp['change_direction'] === 'down' ? '▼' : '•') ?>
                                            <?= e($grp['change_amount_formatted']) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Aligned Total Row (Pinned to bottom of card) -->
                <div class="statement-total-bar assets-total-bar" id="assets-total-bar">
                    <div class="total-bar-label">Total Assets</div>
                    <div class="total-bar-values tabular-nums">
                        <span class="total-main-amt text-primary" id="footer-assets-total"><?= e($statement['assets']['total_formatted']) ?></span>
                        <?php if ($hasComparison): ?>
                            <div class="total-sub-variance delta-<?= e($statement['assets']['change_direction']) ?>" id="footer-assets-variance">
                                <?= $statement['assets']['change_direction'] === 'up' ? '▲' : ($statement['assets']['change_direction'] === 'down' ? '▼' : '•') ?>
                                <?= e($statement['assets']['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$statement['assets']['change_percent']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= RIGHT: LIABILITIES & EQUITY ======================= -->
        <div class="card statement-card liab-card" id="liab-card">
            <div class="card-header statement-card-header liab-header">
                <div class="header-left-part">
                    <span class="card-category-indicator indicator-liab"></span>
                    <h3 class="card-title">LIABILITIES &amp; EQUITY</h3>
                </div>
                <div class="header-amount-block">
                    <span class="statement-hero-amount tabular-nums" id="hero-liab-amount"><?= e($statement['liabilities_equity']['total_formatted']) ?></span>
                    <?php if ($hasComparison): ?>
                        <div class="hero-delta-badge delta-neutral" id="hero-liab-delta">
                            <?= $statement['liabilities_equity']['change_direction'] === 'up' ? '▲' : ($statement['liabilities_equity']['change_direction'] === 'down' ? '▼' : '•') ?>
                            <?= e($statement['liabilities_equity']['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$statement['liabilities_equity']['change_percent']) ?>)
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body statement-card-body">
                <div class="statement-table-wrapper" id="liab-table-container">
                    <table class="table statement-table" id="liab-table">
                        <thead>
                            <tr class="statement-col-headers">
                                <th class="col-particulars">Account Category / Ledger</th>
                                <th class="col-amount tabular-nums" id="col-liab-current">Current (<?= e($asOfFormatted) ?>)</th>
                                <?php if ($hasComparison): ?>
                                    <th class="col-prev tabular-nums" id="col-liab-previous">Previous (<?= e($statement['compare_formatted'] ?? '') ?>)</th>
                                    <th class="col-change tabular-nums" id="col-liab-change">Change</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="liab-tbody">
                            <?php foreach ($statement['liabilities_equity']['groups'] as $grp): ?>
                                <tr class="statement-group-header-row">
                                    <td colspan="<?= $hasComparison ? '4' : '2' ?>" class="group-title-cell">
                                        <?= e($grp['title']) ?>
                                    </td>
                                </tr>
                                <?php foreach ($grp['accounts'] as $acc): ?>
                                    <tr class="statement-row">
                                        <td class="cell-name">
                                            <span class="account-bullet">•</span>
                                            <span><?= e($acc['name']) ?></span>
                                            <?php if ($acc['code'] !== 'RE-NP'): ?>
                                                <span class="account-code-tag"><?= e($acc['code']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cell-amount tabular-nums"><?= e($acc['amount_formatted']) ?></td>
                                        <?php if ($hasComparison): ?>
                                            <td class="cell-prev tabular-nums"><?= e($acc['previous_amount_formatted']) ?></td>
                                            <td class="cell-change tabular-nums delta-neutral">
                                                <?= $acc['change_direction'] === 'up' ? '▲' : ($acc['change_direction'] === 'down' ? '▼' : '•') ?>
                                                <?= e($acc['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$acc['change_percent']) ?>)
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="statement-subtotal-row">
                                    <td class="cell-subtotal-label">Subtotal <?= e($grp['title']) ?></td>
                                    <td class="cell-subtotal-amt tabular-nums"><?= format_currency((float)$grp['subtotal']) ?></td>
                                    <?php if ($hasComparison): ?>
                                        <td class="cell-subtotal-prev tabular-nums"><?= format_currency((float)$grp['previous_subtotal']) ?></td>
                                        <td class="cell-subtotal-change tabular-nums delta-neutral">
                                            <?= $grp['change_direction'] === 'up' ? '▲' : ($grp['change_direction'] === 'down' ? '▼' : '•') ?>
                                            <?= e($grp['change_amount_formatted']) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Aligned Total Row (Pinned to bottom of card) -->
                <div class="statement-total-bar liab-total-bar" id="liab-total-bar">
                    <div class="total-bar-label">Total Liabilities &amp; Equity</div>
                    <div class="total-bar-values tabular-nums">
                        <span class="total-main-amt text-success" id="footer-liab-total"><?= e($statement['liabilities_equity']['total_formatted']) ?></span>
                        <?php if ($hasComparison): ?>
                            <div class="total-sub-variance delta-neutral" id="footer-liab-variance">
                                <?= $statement['liabilities_equity']['change_direction'] === 'up' ? '▲' : ($statement['liabilities_equity']['change_direction'] === 'down' ? '▼' : '•') ?>
                                <?= e($statement['liabilities_equity']['change_amount_formatted']) ?> (<?= sprintf('%.1f%%', (float)$statement['liabilities_equity']['change_percent']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scoped Stylesheet for Balance Sheet Statement & Print Layout -->
<style>
/* Layout & Toolbar */
.balance-sheet-header {
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-start;
}
.title-with-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.balance-sheet-toolbar {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.toolbar-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.toolbar-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    margin: 0;
}
.toolbar-select {
    padding: 0.4rem 0.65rem;
    font-size: 0.825rem;
    height: 36px;
    background-color: var(--bg-surface);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    cursor: pointer;
}
.toolbar-date {
    padding: 0.35rem 0.6rem;
    font-size: 0.825rem;
    height: 36px;
    width: 140px;
    background-color: var(--bg-surface);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
}

/* Balance Check Indicator Badge */
.balance-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.3rem 0.65rem;
    border-radius: 9999px;
    letter-spacing: 0.02em;
    transition: all 0.2s ease;
    cursor: default;
}
.balance-badge.has-tooltip {
    cursor: help;
    border: 1px solid rgba(239, 68, 68, 0.4);
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
    animation: pulseBadge 2s infinite;
}
@keyframes pulseBadge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.75; }
}

/* Two-Card Grid System (Requirement 6: Equal Height & Responsive) */
.balance-sheet-container {
    max-width: 1400px;
    margin: 0 auto;
}
.balance-sheet-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    align-items: stretch;
}
@media (max-width: 991px) {
    .balance-sheet-grid {
        grid-template-columns: 1fr;
    }
}
.statement-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    margin-bottom: 0;
    border: 1px solid var(--border-color);
    background-color: var(--bg-surface);
}
.statement-card-header {
    padding: 1rem 1.25rem;
    background-color: rgba(15, 23, 42, 0.4);
    border-bottom: 2px solid var(--border-color);
}
.assets-header { border-bottom-color: var(--color-primary); }
.liab-header { border-bottom-color: var(--color-success); }
.header-left-part {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-category-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.indicator-asset { background: var(--color-primary); box-shadow: 0 0 8px var(--color-primary); }
.indicator-liab { background: var(--color-success); box-shadow: 0 0 8px var(--color-success); }
.statement-hero-amount {
    font-size: 1.25rem;
    font-weight: 700;
}
.assets-header .statement-hero-amount { color: var(--color-primary); }
.liab-header .statement-hero-amount { color: var(--color-success); }
.header-amount-block {
    text-align: right;
}
.hero-delta-badge {
    font-size: 0.7rem;
    font-weight: 600;
    margin-top: 0.15rem;
}

/* Card Body & Table Alignment */
.statement-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 0;
}
.statement-table-wrapper {
    flex: 1;
    overflow-x: auto;
}
.statement-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
}
.statement-table th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid var(--border-color);
    padding: 0.65rem 1rem;
}
.statement-table td {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    font-size: 0.85rem;
}
.col-particulars { text-align: left; }
.col-amount, .col-prev, .col-change { text-align: right; }

/* Row Hover & Typography */
.statement-row {
    transition: background-color 0.15s ease;
}
.statement-row:hover {
    background-color: rgba(255, 255, 255, 0.05);
}
.cell-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.account-bullet { color: var(--text-muted); font-size: 0.9rem; }
.account-code-tag {
    font-size: 0.65rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.06);
    color: var(--text-muted);
    font-family: monospace;
}
.cell-amount, .cell-prev, .cell-change {
    text-align: right;
}
.tabular-nums {
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum";
}

/* Group Headers & Subtotals */
.statement-group-header-row td {
    background: rgba(255, 255, 255, 0.02);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
    padding: 0.75rem 1rem 0.4rem 1rem;
    border-top: 1px solid var(--border-color);
}
.statement-subtotal-row td {
    font-weight: 600;
    background: rgba(0, 0, 0, 0.15);
    border-top: 1px dashed var(--border-color);
    border-bottom: 1px solid var(--border-color);
    padding: 0.55rem 1rem;
}
.cell-subtotal-label { color: var(--text-secondary); font-size: 0.8rem; }
.cell-subtotal-amt { text-align: right; font-weight: 700; }
.cell-subtotal-prev, .cell-subtotal-change { text-align: right; }

/* Bottom Pinned Total Bar (Requirement 6: Same Baseline) */
.statement-total-bar {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.25rem;
    background: rgba(0, 0, 0, 0.25);
    border-top: 2px solid var(--border-color);
}
.assets-total-bar { border-top-color: var(--color-primary); }
.liab-total-bar { border-top-color: var(--color-success); }
.total-bar-label {
    font-size: 0.95rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.total-bar-values {
    text-align: right;
}
.total-main-amt {
    font-size: 1.15rem;
    font-weight: 700;
    display: block;
}
.total-sub-variance {
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 0.1rem;
}

/* Comparison Variance Indicators */
.delta-asset-up, .delta-up { color: #10b981; }
.delta-asset-down, .delta-down { color: #ef4444; }
.delta-neutral { color: #94a3b8; }

/* Skeleton Loading Animation */
.skeleton-line {
    height: 14px;
    background: linear-gradient(90deg, rgba(255,255,255,0.04) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.04) 75%);
    background-size: 200% 100%;
    animation: skeletonShimmer 1.5s infinite;
    border-radius: 4px;
    display: inline-block;
}
@keyframes skeletonShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Print Only Styles */
.print-only { display: none; }
@media print {
    .no-print, .erp-sidebar, .erp-header, .erp-footer, .toast-container, .btn {
        display: none !important;
    }
    .print-only { display: block !important; }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10pt;
    }
    .erp-main, .erp-content, .balance-sheet-container {
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
    }
    .print-statement-header {
        text-align: center;
        border-bottom: 2px solid #000000;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .print-company-name {
        font-size: 16pt;
        font-weight: 700;
        text-transform: uppercase;
    }
    .print-company-meta {
        font-size: 9pt;
        color: #333333;
        margin-top: 2px;
    }
    .print-doc-title {
        font-size: 14pt;
        font-weight: 700;
        margin: 8px 0 2px 0;
        letter-spacing: 0.5px;
    }
    .print-subtitle { font-size: 10pt; font-weight: 600; }
    .print-status-bar {
        display: flex;
        justify-content: space-between;
        font-size: 8.5pt;
        margin-top: 6px;
        border-top: 1px dotted #999999;
        padding-top: 4px;
    }
    .print-status-ok { color: #15803d; font-weight: 700; }
    .print-status-err { color: #b91c1c; font-weight: 700; }
    .balance-sheet-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 15px !important;
    }
    .statement-card {
        border: 1px solid #000000 !important;
        background: #ffffff !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    .statement-card-header {
        background: #f1f5f9 !important;
        border-bottom: 1px solid #000000 !important;
    }
    .statement-card-header h3, .statement-hero-amount {
        color: #000000 !important;
    }
    .statement-table th {
        background: #e2e8f0 !important;
        color: #000000 !important;
        border-bottom: 1px solid #000000 !important;
    }
    .statement-table td {
        color: #000000 !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .statement-group-header-row td {
        background: #f8fafc !important;
        color: #000000 !important;
        font-weight: 700 !important;
    }
    .statement-subtotal-row td {
        background: #f1f5f9 !important;
        color: #000000 !important;
        border-top: 1px solid #666666 !important;
        border-bottom: 1px solid #666666 !important;
    }
    .statement-total-bar {
        background: #e2e8f0 !important;
        border-top: 2px solid #000000 !important;
        color: #000000 !important;
    }
    .statement-total-bar .total-main-amt {
        color: #000000 !important;
    }
}
</style>

<!-- Vanilla JavaScript Client Script for AJAX Period Selection & Exports -->
<script>
(function() {
    'use strict';

    // Balance Sheet Client Controller
    const BalanceSheet = {
        state: {
            asOf: '<?= e($asOf) ?>',
            compare: '<?= e($activeCompare) ?>',
            loading: false
        },

        init: function() {
            this.bindEvents();
            this.syncPresetSelect();
        },

        bindEvents: function() {
            const presetSelect = document.getElementById('bs-preset-select');
            const asOfInput = document.getElementById('bs-asof-date');
            const compareSelect = document.getElementById('bs-compare-select');
            const exportBtn = document.getElementById('bs-export-btn');
            const exportMenu = document.getElementById('bs-export-menu');

            // 1. Preset change
            if (presetSelect) {
                presetSelect.addEventListener('change', (e) => {
                    const preset = e.target.value;
                    if (preset !== 'custom') {
                        const targetDate = this.calculatePresetDate(preset);
                        if (targetDate) {
                            asOfInput.value = targetDate;
                            this.updateState({ asOf: targetDate });
                        }
                    }
                });
            }

            // 2. Direct date change
            if (asOfInput) {
                asOfInput.addEventListener('change', (e) => {
                    if (e.target.value) {
                        this.updateState({ asOf: e.target.value });
                        this.syncPresetSelect();
                    }
                });
            }

            // 3. Comparison toggle change
            if (compareSelect) {
                compareSelect.addEventListener('change', (e) => {
                    this.updateState({ compare: e.target.value });
                });
            }

            // 4. Export dropdown toggle
            if (exportBtn && exportMenu) {
                exportBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    exportMenu.classList.toggle('show');
                });

                document.addEventListener('click', () => {
                    exportMenu.classList.remove('show');
                });
            }

            // 5. Handle browser back/forward buttons
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.asOf) {
                    asOfInput.value = e.state.asOf;
                    if (compareSelect) compareSelect.value = e.state.compare || 'none';
                    this.fetchData(e.state.asOf, e.state.compare || 'none', false);
                }
            });
        },

        calculatePresetDate: function(preset) {
            const today = new Date();
            const y = today.getFullYear();
            const m = today.getMonth(); // 0-indexed (0=Jan, 9=Oct)

            switch (preset) {
                case 'today':
                    return this.formatDateYMD(today);

                case 'end_last_month': {
                    // Last day of prior month
                    const lastDayPrev = new Date(y, m, 0);
                    return this.formatDateYMD(lastDayPrev);
                }

                case 'end_quarter': {
                    // End of the most recently ended calendar quarter
                    // Q1: Mar 31, Q2: Jun 30, Q3: Sep 30, Q4: Dec 31
                    let qEndMonth;
                    let qYear = y;
                    if (m >= 0 && m <= 2) {
                        qEndMonth = 11; qYear = y - 1; // Prior year Dec 31
                    } else if (m >= 3 && m <= 5) {
                        qEndMonth = 2; // Mar 31
                    } else if (m >= 6 && m <= 8) {
                        qEndMonth = 5; // Jun 30
                    } else {
                        qEndMonth = 8; // Sep 30
                    }
                    const qEndDate = new Date(qYear, qEndMonth + 1, 0);
                    return this.formatDateYMD(qEndDate);
                }

                case 'end_fy': {
                    // Indian FY: Apr 1 – Mar 31
                    // If month >= Apr (3), FY ends on Mar 31 of next year; else Mar 31 of current year.
                    const fyEndYear = (m >= 3) ? (y + 1) : y;
                    return `${fyEndYear}-03-31`;
                }

                default:
                    return null;
            }
        },

        formatDateYMD: function(d) {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        },

        syncPresetSelect: function() {
            const select = document.getElementById('bs-preset-select');
            const asOf = document.getElementById('bs-asof-date').value;
            if (!select) return;

            const today = this.calculatePresetDate('today');
            const endLastMonth = this.calculatePresetDate('end_last_month');
            const endQuarter = this.calculatePresetDate('end_quarter');
            const endFy = this.calculatePresetDate('end_fy');

            if (asOf === today) select.value = 'today';
            else if (asOf === endLastMonth) select.value = 'end_last_month';
            else if (asOf === endQuarter) select.value = 'end_quarter';
            else if (asOf === endFy) select.value = 'end_fy';
            else select.value = 'custom';
        },

        updateState: function(newProps) {
            this.state = Object.assign(this.state, newProps);
            this.fetchData(this.state.asOf, this.state.compare, true);
        },

        fetchData: function(asOf, compare, pushHistory) {
            if (this.state.loading) return;
            this.state.loading = true;

            // Update URL query string without reloading page
            if (pushHistory) {
                const params = new URLSearchParams();
                if (asOf) params.set('as_of', asOf);
                if (compare && compare !== 'none') params.set('compare', compare);
                const newUrl = `${window.location.pathname}?${params.toString()}`;
                window.history.pushState({ asOf: asOf, compare: compare }, '', newUrl);
            }

            // Show loading skeleton in cards
            this.renderSkeleton();

            const apiUrl = `/api/reports/balance-sheet?as_of=${encodeURIComponent(asOf)}&compare=${encodeURIComponent(compare)}`;
            fetch(apiUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                this.state.loading = false;
                this.renderStatement(data);
            })
            .catch(err => {
                this.state.loading = false;
                if (window.WTSBill && WTSBill.Toast) {
                    WTSBill.Toast.show(`Failed to reload balance sheet: ${err.message}`, 'danger');
                } else {
                    console.error('Balance sheet reload failed:', err);
                }
            });
        },

        renderSkeleton: function() {
            const skeletonRow = (cols) => `
                <tr class="statement-row">
                    <td><span class="skeleton-line" style="width: 65%;"></span></td>
                    <td style="text-align: right;"><span class="skeleton-line" style="width: 80px;"></span></td>
                    ${cols === 4 ? `
                        <td style="text-align: right;"><span class="skeleton-line" style="width: 80px;"></span></td>
                        <td style="text-align: right;"><span class="skeleton-line" style="width: 70px;"></span></td>
                    ` : ''}
                </tr>
            `;

            const hasCompare = (this.state.compare !== 'none');
            const cols = hasCompare ? 4 : 2;

            const assetsTbody = document.getElementById('assets-tbody');
            const liabTbody = document.getElementById('liab-tbody');
            if (assetsTbody) {
                assetsTbody.innerHTML = skeletonRow(cols) + skeletonRow(cols) + skeletonRow(cols);
            }
            if (liabTbody) {
                liabTbody.innerHTML = skeletonRow(cols) + skeletonRow(cols) + skeletonRow(cols);
            }
        },

        renderStatement: function(data) {
            const hasCompare = (data.compare !== 'none');

            // 1. Update Subtitle
            const subEl = document.getElementById('subtitle-date-text');
            if (subEl) subEl.textContent = data.as_of_formatted;

            const printAsOf = document.getElementById('print-asof-date');
            if (printAsOf) printAsOf.textContent = data.as_of_formatted;

            // 2. Update Balance Check Indicator Badge
            const badgeContainer = document.getElementById('balance-badge-container');
            if (badgeContainer) {
                if (data.balanced) {
                    badgeContainer.innerHTML = `
                        <span id="balance-check-badge" class="badge badge-success balance-badge" title="Accounting Equation verified: Assets = Liabilities + Equity (Tolerance ₹0.01)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Balanced</span>
                        </span>
                    `;
                } else {
                    badgeContainer.innerHTML = `
                        <span id="balance-check-badge" class="badge badge-danger balance-badge has-tooltip" 
                              title="Assets and Liabilities+Equity do not match. Check unposted or unbalanced journal entries."
                              tabindex="0" role="tooltip">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            <span>Difference: ${data.difference_formatted}</span>
                        </span>
                    `;
                }
            }

            // 3. Update Hero Headers
            const heroAssetsAmt = document.getElementById('hero-assets-amount');
            if (heroAssetsAmt) heroAssetsAmt.textContent = data.assets.total_formatted;

            const heroAssetsDelta = document.getElementById('hero-assets-delta');
            if (heroAssetsDelta) {
                if (hasCompare) {
                    const arrow = data.assets.change_direction === 'up' ? '▲' : (data.assets.change_direction === 'down' ? '▼' : '•');
                    heroAssetsDelta.className = `hero-delta-badge delta-${data.assets.change_direction}`;
                    heroAssetsDelta.textContent = `${arrow} ${data.assets.change_amount_formatted} (${data.assets.change_percent.toFixed(1)}%)`;
                    heroAssetsDelta.style.display = 'block';
                } else {
                    heroAssetsDelta.style.display = 'none';
                }
            }

            const heroLiabAmt = document.getElementById('hero-liab-amount');
            if (heroLiabAmt) heroLiabAmt.textContent = data.liabilities_equity.total_formatted;

            const heroLiabDelta = document.getElementById('hero-liab-delta');
            if (heroLiabDelta) {
                if (hasCompare) {
                    const arrow = data.liabilities_equity.change_direction === 'up' ? '▲' : (data.liabilities_equity.change_direction === 'down' ? '▼' : '•');
                    heroLiabDelta.className = `hero-delta-badge delta-neutral`;
                    heroLiabDelta.textContent = `${arrow} ${data.liabilities_equity.change_amount_formatted} (${data.liabilities_equity.change_percent.toFixed(1)}%)`;
                    heroLiabDelta.style.display = 'block';
                } else {
                    heroLiabDelta.style.display = 'none';
                }
            }

            // 4. Update Tables Column Headers
            const updateTableHeaders = (tableId, currentText, prevText) => {
                const thead = document.querySelector(`#${tableId} thead`);
                if (!thead) return;
                if (hasCompare) {
                    thead.innerHTML = `
                        <tr class="statement-col-headers">
                            <th class="col-particulars">Account Category / Ledger</th>
                            <th class="col-amount tabular-nums">Current (${currentText})</th>
                            <th class="col-prev tabular-nums">Previous (${prevText || ''})</th>
                            <th class="col-change tabular-nums">Change</th>
                        </tr>
                    `;
                } else {
                    thead.innerHTML = `
                        <tr class="statement-col-headers">
                            <th class="col-particulars">Account Category / Ledger</th>
                            <th class="col-amount tabular-nums">Amount (INR)</th>
                        </tr>
                    `;
                }
            };
            updateTableHeaders('assets-table', data.as_of_formatted, data.compare_formatted);
            updateTableHeaders('liab-table', data.as_of_formatted, data.compare_formatted);

            // 5. Render Assets Table Rows
            const assetsTbody = document.getElementById('assets-tbody');
            if (assetsTbody) {
                assetsTbody.innerHTML = this.buildTableRowsHtml(data.assets.groups, hasCompare, true);
            }

            // 6. Render Liabilities & Equity Table Rows
            const liabTbody = document.getElementById('liab-tbody');
            if (liabTbody) {
                liabTbody.innerHTML = this.buildTableRowsHtml(data.liabilities_equity.groups, hasCompare, false);
            }

            // 7. Update Footer Totals Bars
            const footerAssetsTotal = document.getElementById('footer-assets-total');
            if (footerAssetsTotal) footerAssetsTotal.textContent = data.assets.total_formatted;

            const footerAssetsVar = document.getElementById('footer-assets-variance');
            if (footerAssetsVar) {
                if (hasCompare) {
                    const arrow = data.assets.change_direction === 'up' ? '▲' : (data.assets.change_direction === 'down' ? '▼' : '•');
                    footerAssetsVar.className = `total-sub-variance delta-${data.assets.change_direction}`;
                    footerAssetsVar.textContent = `${arrow} ${data.assets.change_amount_formatted} (${data.assets.change_percent.toFixed(1)}%)`;
                    footerAssetsVar.style.display = 'block';
                } else {
                    footerAssetsVar.style.display = 'none';
                }
            }

            const footerLiabTotal = document.getElementById('footer-liab-total');
            if (footerLiabTotal) footerLiabTotal.textContent = data.liabilities_equity.total_formatted;

            const footerLiabVar = document.getElementById('footer-liab-variance');
            if (footerLiabVar) {
                if (hasCompare) {
                    const arrow = data.liabilities_equity.change_direction === 'up' ? '▲' : (data.liabilities_equity.change_direction === 'down' ? '▼' : '•');
                    footerLiabVar.className = `total-sub-variance delta-neutral`;
                    footerLiabVar.textContent = `${arrow} ${data.liabilities_equity.change_amount_formatted} (${data.liabilities_equity.change_percent.toFixed(1)}%)`;
                    footerLiabVar.style.display = 'block';
                } else {
                    footerLiabVar.style.display = 'none';
                }
            }
        },

        buildTableRowsHtml: function(groups, hasCompare, isAsset) {
            let html = '';
            groups.forEach(grp => {
                html += `
                    <tr class="statement-group-header-row">
                        <td colspan="${hasCompare ? '4' : '2'}" class="group-title-cell">${this.escape(grp.title)}</td>
                    </tr>
                `;

                grp.accounts.forEach(acc => {
                    const arrow = acc.change_direction === 'up' ? '▲' : (acc.change_direction === 'down' ? '▼' : '•');
                    const deltaClass = isAsset ? `delta-asset-${acc.change_direction}` : `delta-neutral`;
                    const codeHtml = (acc.code && acc.code !== 'RE-NP') ? `<span class="account-code-tag">${this.escape(acc.code)}</span>` : '';

                    html += `
                        <tr class="statement-row">
                            <td class="cell-name">
                                <span class="account-bullet">•</span>
                                <span>${this.escape(acc.name)}</span>
                                ${codeHtml}
                            </td>
                            <td class="cell-amount tabular-nums">${this.escape(acc.amount_formatted)}</td>
                            ${hasCompare ? `
                                <td class="cell-prev tabular-nums">${this.escape(acc.previous_amount_formatted)}</td>
                                <td class="cell-change tabular-nums ${deltaClass}">
                                    ${arrow} ${this.escape(acc.change_amount_formatted)} (${acc.change_percent.toFixed(1)}%)
                                </td>
                            ` : ''}
                        </tr>
                    `;
                });

                // Subtotal Row
                const subArrow = grp.change_direction === 'up' ? '▲' : (grp.change_direction === 'down' ? '▼' : '•');
                const subDeltaClass = isAsset ? `delta-asset-${grp.change_direction}` : `delta-neutral`;
                html += `
                    <tr class="statement-subtotal-row">
                        <td class="cell-subtotal-label">Subtotal ${this.escape(grp.title)}</td>
                        <td class="cell-subtotal-amt tabular-nums">${this.formatRupee(grp.subtotal)}</td>
                        ${hasCompare ? `
                            <td class="cell-subtotal-prev tabular-nums">${this.formatRupee(grp.previous_subtotal)}</td>
                            <td class="cell-subtotal-change tabular-nums ${subDeltaClass}">
                                ${subArrow} ${this.escape(grp.change_amount_formatted)}
                            </td>
                        ` : ''}
                    </tr>
                `;
            });
            return html;
        },

        export: function(format) {
            const asOf = this.state.asOf || document.getElementById('bs-asof-date').value;
            const compare = this.state.compare || document.getElementById('bs-compare-select').value;

            if (format === 'pdf') {
                // Open dedicated printable view in new window or trigger print
                window.open(`/reports/balance-sheet/export?format=pdf&as_of=${encodeURIComponent(asOf)}&compare=${encodeURIComponent(compare)}`, '_blank');
            } else {
                // Trigger download for Excel (.xlsx) or CSV
                const exportUrl = `/reports/balance-sheet/export?format=${encodeURIComponent(format)}&as_of=${encodeURIComponent(asOf)}&compare=${encodeURIComponent(compare)}`;
                window.location.href = exportUrl;
            }
        },

        formatRupee: function(num) {
            const isNegative = num < -0.001;
            const abs = Math.abs(num);
            const parts = abs.toFixed(2).split('.');
            let integerPart = parts[0];
            const decimalPart = parts[1];

            if (integerPart.length > 3) {
                const lastThree = integerPart.substring(integerPart.length - 3);
                const remaining = integerPart.substring(0, integerPart.length - 3);
                const groups = [];
                for (let i = remaining.length; i > 0; i -= 2) {
                    const start = Math.max(0, i - 2);
                    groups.unshift(remaining.substring(start, i));
                }
                integerPart = groups.join(',') + ',' + lastThree;
            }

            const formatted = `₹ ${integerPart}.${decimalPart}`;
            return isNegative ? `(${formatted})` : formatted;
        },

        escape: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Expose on WTSBill global namespace
    window.WTSBill = window.WTSBill || {};
    window.WTSBill.BalanceSheet = BalanceSheet;

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => BalanceSheet.init());
    } else {
        BalanceSheet.init();
    }
})();
</script>
