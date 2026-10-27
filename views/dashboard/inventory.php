<?php
/**
 * WTSBill ERP — Inventory Dashboard (Inventory Manager View)
 * Exclusively displays warehouse stock levels, low stock, out of stock, and physical adjustments.
 * Zero financial amounts (P&L/Balance sheet/Tax) or admin widgets.
 *
 * @var array $metrics
 * @var array $criticalItems
 * @var array $recentAdjustments
 */

$title = 'Inventory Dashboard';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Warehouse & Inventory Operations</h1>
        <p class="page-subtitle">Real-time stock quantities, low stock alerts, valuation and physical adjustments</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="/inventory/stock?action=adjust" class="btn btn-primary">+ Record Adjustment</a>
        <a href="/inventory/stock" class="btn btn-secondary">Warehouse Balances</a>
    </div>
</div>

<!-- Stock KPIs (Zero profit/P&L metrics) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- 1. Total Units on Hand -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Stocked Units</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
                <?= number_format((float)($metrics['total_units'] ?? 0), 0) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Active physical inventory</div>
        </div>
    </div>

    <!-- 2. Low Stock Alerts -->
    <div class="card" style="margin-bottom: 0; border-left: 3px solid var(--color-warning);">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--color-warning); font-weight: 700; text-transform: uppercase;">Low Stock Items</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-warning); margin-top: 0.25rem;">
                <?= (int)($metrics['low_stock_count'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Items below reorder threshold</div>
        </div>
    </div>

    <!-- 3. Out of Stock -->
    <div class="card" style="margin-bottom: 0; border-left: 3px solid var(--color-danger);">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--color-danger); font-weight: 700; text-transform: uppercase;">Out of Stock</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem;">
                <?= (int)($metrics['out_of_stock_count'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Zero balance items</div>
        </div>
    </div>

    <!-- 4. Total Stock Valuation (FIFO Cost Basis) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Inventory Valuation (Cost)</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">
                <?= format_currency((float)($metrics['total_valuation'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">FIFO warehouse cost value</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Stock Alert Items Needing Reorder -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Critical Stock Attention Needed</h3>
            <a href="/inventory/stock" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Manage Stock &rarr;</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item & SKU</th>
                        <th style="text-align: right;">On Hand</th>
                        <th style="text-align: right;">Reorder Level</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($criticalItems)): ?>
                        <?php foreach ($criticalItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: <?= e($item['sku']) ?></div>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: <?= (float)$item['current_stock'] <= 0 ? 'var(--color-danger)' : 'var(--color-warning)' ?>;">
                                    <?= number_format((float)$item['current_stock'], 0) ?>
                                </td>
                                <td style="text-align: right; color: var(--text-muted);">
                                    <?= number_format((float)$item['reorder_level'], 0) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((float)$item['current_stock'] <= 0): ?>
                                        <span class="badge badge-danger">OUT OF STOCK</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">LOW STOCK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">All inventory levels are healthy! No low stock warnings.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Stock Reconciliation Adjustments -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Recent Stock Adjustments</h3>
            <a href="/inventory/stock?tab=history" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Adjustment Log &rarr;</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Adjustment #</th>
                        <th>Product</th>
                        <th style="text-align: center;">Type</th>
                        <th style="text-align: right;">Qty</th>
                        <th>Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentAdjustments)): ?>
                        <?php foreach ($recentAdjustments as $adj): ?>
                            <tr>
                                <td><code><?= e($adj['adjustment_number']) ?></code></td>
                                <td><strong><?= e($adj['product_name']) ?></strong></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= str_contains(strtolower($adj['type']), 'increase') ? 'success' : 'danger' ?>">
                                        <?= e($adj['type']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700;">
                                    <?= (str_contains(strtolower($adj['type']), 'increase') ? '+' : '-') . number_format((float)$adj['quantity'], 0) ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);"><?= e($adj['operator'] ?? 'Inventory Mgr') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No recent physical adjustments recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
