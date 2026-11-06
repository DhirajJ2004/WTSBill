<?php
$title = $title ?? 'Warehouse Details';
$warehouse = $warehouse ?? [];
$stock = $stock ?? [];
$branches = $branches ?? [];

$totalStockUnits = 0;
$totalValuation = 0.0;
foreach ($stock as $s) {
    $totalStockUnits += (float)($s['quantity'] ?? 0);
    $totalValuation += (float)($s['stock_value'] ?? 0);
}
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Breadcrumb & Actions -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/inventory/warehouses" style="color: #64748b; text-decoration: none;">Warehouses</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;"><?= htmlspecialchars($warehouse['name']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                <?= htmlspecialchars($warehouse['name']) ?>
                <?php if (!empty($warehouse['is_primary'])): ?>
                    <span style="font-size: 12px; font-weight: 600; background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 9999px;">PRIMARY</span>
                <?php endif; ?>
            </h1>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/inventory/transfers/create?from_warehouse_id=<?= (int)$warehouse['id'] ?>" class="btn btn-outline-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid #3b82f6; color: #3b82f6; background: #fff;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Initiate Transfer Out
            </a>
            <button type="button" onclick="openEditWarehouseModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; cursor: pointer;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Facility
            </button>
        </div>
    </div>

    <!-- Facility Metadata Card -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Facility Code</div>
                <div style="font-size: 15px; font-weight: 600; color: #0f172a; margin-top: 4px; font-family: monospace;"><?= htmlspecialchars($warehouse['code'] ?? '—') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Branch Assignment</div>
                <div style="font-size: 15px; font-weight: 600; color: #0f172a; margin-top: 4px;"><?= htmlspecialchars($warehouse['branch_name'] ?? 'Main Branch') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Manager & Contact</div>
                <div style="font-size: 15px; font-weight: 600; color: #0f172a; margin-top: 4px;"><?= htmlspecialchars($warehouse['manager_name'] ?? '—') ?></div>
                <div style="font-size: 13px; color: #64748b;"><?= htmlspecialchars($warehouse['phone'] ?? '—') ?></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Physical Location</div>
                <div style="font-size: 14px; color: #334155; margin-top: 4px;"><?= htmlspecialchars($warehouse['address'] ?: ($warehouse['city'] ?? '—')) ?></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Valuation</div>
                <div style="font-size: 18px; font-weight: 700; color: #059669; margin-top: 4px;">₹<?= number_format($totalValuation, 2) ?></div>
                <div style="font-size: 12px; color: #64748b;"><?= number_format($totalStockUnits, 0) ?> units in storage</div>
            </div>
        </div>
    </div>

    <!-- Inventory Stock Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Stock Balances in this Facility (<?= count($stock) ?> items)</h2>
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search SKU or name..." style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                <button type="submit" style="padding: 6px 12px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; cursor: pointer;">Search</button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Product Name / SKU</th>
                        <th style="padding: 14px 20px;">Category</th>
                        <th style="padding: 14px 20px; text-align: right;">Quantity on Hand</th>
                        <th style="padding: 14px 20px; text-align: right;">Available</th>
                        <th style="padding: 14px 20px; text-align: right;">In-Transit</th>
                        <th style="padding: 14px 20px; text-align: right;">Cost Price</th>
                        <th style="padding: 14px 20px; text-align: right;">Total Value</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($stock)): ?>
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #94a3b8;">
                                No stock records recorded in this warehouse. Use <strong>Stock Transfers</strong> or <strong>Goods Receipts</strong> to allocate stock.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['product_name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">SKU: <span style="font-family: monospace;"><?= htmlspecialchars($row['sku'] ?? '—') ?></span></div>
                                </td>
                                <td style="padding: 14px 20px; color: #475569;"><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #0f172a;">
                                    <?= number_format((float)$row['quantity'], 2) ?> <?= htmlspecialchars($row['unit'] ?? '') ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #059669;">
                                    <?= number_format((float)$row['available_quantity'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #d97706;">
                                    <?= number_format((float)($row['in_transit_quantity'] ?? 0), 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; color: #475569;">
                                    ₹<?= number_format((float)$row['unit_cost'], 2) ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #1e293b;">
                                    ₹<?= number_format((float)$row['stock_value'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Warehouse Modal -->
<div id="editWarehouseModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; width: 100%; max-width: 580px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; margin: 16px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Edit Warehouse Details</h3>
            <button type="button" onclick="closeEditWarehouseModal()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form action="/inventory/warehouses/<?= (int)$warehouse['id'] ?>/update" method="POST" style="padding: 24px;">
            <?= csrf_field() ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Warehouse Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($warehouse['name']) ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Code</label>
                    <input type="text" name="code" value="<?= htmlspecialchars($warehouse['code'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Branch Assignment</label>
                    <select name="branch_id" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)$warehouse['branch_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($warehouse['city'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Address</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;"><?= htmlspecialchars($warehouse['address'] ?? '') ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Manager Name</label>
                    <input type="text" name="manager_name" value="<?= htmlspecialchars($warehouse['manager_name'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($warehouse['phone'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 24px; display: flex; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="is_primary" value="1" <?= !empty($warehouse['is_primary']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                    Primary Warehouse
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($warehouse['is_active']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                    Active
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeEditWarehouseModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer;">Update Warehouse</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditWarehouseModal() {
    document.getElementById('editWarehouseModal').style.display = 'flex';
}
function closeEditWarehouseModal() {
    document.getElementById('editWarehouseModal').style.display = 'none';
}
</script>

