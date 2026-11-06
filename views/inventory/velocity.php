<?php
$title = $title ?? 'Stock Velocity & Inventory Health';
$velocity = $velocity ?? [];
$lowStock = $lowStock ?? [];
$days = $days ?? 30;

$summary = $velocity['summary'] ?? [
    'total_products' => 0,
    'fast_moving_count' => 0,
    'slow_moving_count' => 0,
    'non_moving_count' => 0,
];
$fast = $velocity['fast_moving'] ?? [];
$slow = $velocity['slow_moving'] ?? [];
$nonMoving = $velocity['non_moving'] ?? [];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/inventory/stock" style="color: #64748b; text-decoration: none;">Inventory</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Velocity & Replenishment</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Stock Velocity & Replenishment Analytics</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Classifies products by sales velocity (Fast, Slow, Non-Moving) and surfaces critical low-stock alerts.</p>
        </div>

        <!-- Days Selector -->
        <div style="display: flex; align-items: center; gap: 8px; background: #fff; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 8px;">
            <span style="font-size: 13px; font-weight: 600; color: #475569;">Analysis Period:</span>
            <?php foreach ([15, 30, 60, 90, 180] as $d): ?>
                <a href="/inventory/velocity?days=<?= $d ?>" style="padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= ($days === $d) ? 'background: #2563eb; color: #fff;' : 'color: #64748b;' ?>">
                    <?= $d ?>D
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Active SKUs</div>
            <div style="font-size: 28px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?= (int)$summary['total_products'] ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">In catalog</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; border-left: 4px solid #10b981;">
            <div style="font-size: 12px; font-weight: 600; color: #059669; text-transform: uppercase;">Fast-Moving (>10 sold)</div>
            <div style="font-size: 28px; font-weight: 700; color: #059669; margin-top: 6px;"><?= (int)$summary['fast_moving_count'] ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">High turnover velocity</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 12px; font-weight: 600; color: #d97706; text-transform: uppercase;">Slow-Moving (1-10 sold)</div>
            <div style="font-size: 28px; font-weight: 700; color: #d97706; margin-top: 6px;"><?= (int)$summary['slow_moving_count'] ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Moderate turnover</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; border-left: 4px solid #ef4444;">
            <div style="font-size: 12px; font-weight: 600; color: #dc2626; text-transform: uppercase;">Non-Moving / Dead Stock</div>
            <div style="font-size: 28px; font-weight: 700; color: #dc2626; margin-top: 6px;"><?= (int)$summary['non_moving_count'] ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">0 units sold in <?= $days ?> days</div>
        </div>
    </div>

    <!-- Low Stock Alert Banner if any -->
    <?php if (!empty($lowStock)): ?>
        <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg width="24" height="24" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <strong style="color: #92400e; font-size: 15px;">Low Stock Replenishment Alert:</strong>
                    <span style="color: #b45309; font-size: 14px; margin-left: 4px;"><?= count($lowStock) ?> product(s) are currently at or below their reorder threshold.</span>
                </div>
            </div>
            <a href="#lowStockSection" style="padding: 6px 14px; background: #d97706; color: #fff; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none;">View Low Stock Items</a>
        </div>
    <?php endif; ?>

    <!-- Velocity Tables Tabs -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 24px;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 16px; border-bottom: 1px solid #e2e8f0;">
            <button type="button" onclick="switchVelocityTab('fast')" id="tabBtn_fast" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; background: #ecfdf5; color: #059669;">
                Fast Moving (<?= count($fast) ?>)
            </button>
            <button type="button" onclick="switchVelocityTab('slow')" id="tabBtn_slow" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; background: #f1f5f9; color: #475569;">
                Slow Moving (<?= count($slow) ?>)
            </button>
            <button type="button" onclick="switchVelocityTab('non')" id="tabBtn_non" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; background: #f1f5f9; color: #475569;">
                Non-Moving / Stagnant (<?= count($nonMoving) ?>)
            </button>
        </div>

        <!-- Fast Moving Table -->
        <div id="tabContent_fast" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Product Name / SKU</th>
                        <th style="padding: 14px 20px;">Category</th>
                        <th style="padding: 14px 20px; text-align: right;">Units Sold (<?= $days ?>D)</th>
                        <th style="padding: 14px 20px; text-align: right;">Sales Revenue</th>
                        <th style="padding: 14px 20px; text-align: right;">Stock on Hand</th>
                        <th style="padding: 14px 20px; text-align: center;">Velocity Status</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($fast)): ?>
                        <tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No fast-moving items in this timeframe.</td></tr>
                    <?php else: ?>
                        <?php foreach ($fast as $p): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">SKU: <span style="font-family: monospace;"><?= htmlspecialchars($p['sku'] ?? '—') ?></span></div>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #059669; font-size: 15px;">
                                    <?= number_format((float)$p['units_sold'], 0) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #1e293b;">
                                    ₹<?= number_format((float)$p['total_revenue'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                    <?= number_format((float)$p['current_stock'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #ecfdf5; color: #059669;">FAST</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Slow Moving Table -->
        <div id="tabContent_slow" style="display: none; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Product Name / SKU</th>
                        <th style="padding: 14px 20px;">Category</th>
                        <th style="padding: 14px 20px; text-align: right;">Units Sold (<?= $days ?>D)</th>
                        <th style="padding: 14px 20px; text-align: right;">Sales Revenue</th>
                        <th style="padding: 14px 20px; text-align: right;">Stock on Hand</th>
                        <th style="padding: 14px 20px; text-align: center;">Velocity Status</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($slow)): ?>
                        <tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No slow-moving items in this timeframe.</td></tr>
                    <?php else: ?>
                        <?php foreach ($slow as $p): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">SKU: <span style="font-family: monospace;"><?= htmlspecialchars($p['sku'] ?? '—') ?></span></div>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #d97706;">
                                    <?= number_format((float)$p['units_sold'], 0) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #1e293b;">
                                    ₹<?= number_format((float)$p['total_revenue'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                    <?= number_format((float)$p['current_stock'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #fef3c7; color: #b45309;">SLOW</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Non-Moving Table -->
        <div id="tabContent_non" style="display: none; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Product Name / SKU</th>
                        <th style="padding: 14px 20px;">Category</th>
                        <th style="padding: 14px 20px; text-align: right;">Units Sold (<?= $days ?>D)</th>
                        <th style="padding: 14px 20px; text-align: right;">Stock on Hand</th>
                        <th style="padding: 14px 20px; text-align: center;">Velocity Status</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($nonMoving)): ?>
                        <tr><td colspan="5" style="padding: 30px; text-align: center; color: #94a3b8;">No non-moving items.</td></tr>
                    <?php else: ?>
                        <?php foreach ($nonMoving as $p): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">SKU: <span style="font-family: monospace;"><?= htmlspecialchars($p['sku'] ?? '—') ?></span></div>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                <td style="padding: 14px 20px; text-align: right; color: #94a3b8;">
                                    0
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #dc2626;">
                                    <?= number_format((float)$p['current_stock'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #fef2f2; color: #dc2626;">STAGNANT</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Section -->
    <div id="lowStockSection" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Low Stock & Reorder Alert Register (<?= count($lowStock) ?> items)</h2>
            <a href="/purchases/orders/create" style="padding: 6px 14px; background: #2563eb; color: #fff; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none;">+ Create Purchase Order</a>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Product Name / SKU</th>
                        <th style="padding: 14px 20px;">Category</th>
                        <th style="padding: 14px 20px; text-align: right;">Current Stock</th>
                        <th style="padding: 14px 20px; text-align: right;">Reorder Threshold</th>
                        <th style="padding: 14px 20px; text-align: right;">Unit Cost</th>
                        <th style="padding: 14px 20px; text-align: center;">Urgency</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($lowStock)): ?>
                        <tr><td colspan="6" style="padding: 30px; text-align: center; color: #10b981;">All items are above their reorder thresholds!</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStock as $row): 
                            $curr = (float)$row['current_stock'];
                            $thresh = (float)$row['reorder_threshold'];
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">SKU: <span style="font-family: monospace;"><?= htmlspecialchars($row['sku'] ?? '—') ?></span></div>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;"><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: <?= $curr <= 0 ? '#dc2626' : '#d97706' ?>;">
                                    <?= number_format($curr, 2) ?> <?= htmlspecialchars($row['unit'] ?? '') ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #64748b;">
                                    <?= number_format($thresh, 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #334155;">
                                    ₹<?= number_format((float)$row['unit_cost'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <?php if ($curr <= 0): ?>
                                        <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #fef2f2; color: #dc2626;">OUT OF STOCK</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 9999px; background: #fffbeb; color: #b45309;">REORDER</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchVelocityTab(tab) {
    ['fast', 'slow', 'non'].forEach(t => {
        document.getElementById(`tabContent_${t}`).style.display = (t === tab) ? 'block' : 'none';
        const btn = document.getElementById(`tabBtn_${t}`);
        if (t === tab) {
            btn.style.background = '#ecfdf5';
            btn.style.color = '#059669';
        } else {
            btn.style.background = '#f1f5f9';
            btn.style.color = '#475569';
        }
    });
}
</script>

