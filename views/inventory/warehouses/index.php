<?php
$title = $title ?? 'Warehouses & Godowns';
$warehouses = $warehouses ?? [];
$branches = $branches ?? [];

$totalWh = count($warehouses);
$totalUnits = 0;
$totalVal = 0.0;
$primaryWhName = 'None';

foreach ($warehouses as $w) {
    $totalUnits += (float)($w['total_quantity'] ?? 0);
    $totalVal += (float)($w['total_valuation'] ?? 0);
    if (!empty($w['is_primary'])) {
        $primaryWhName = $w['name'];
    }
}
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Warehouses & Godowns</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Multi-warehouse stock tracking, godown allocation, and physical location management.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="/inventory/transfers" class="btn btn-outline-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid #3b82f6; color: #3b82f6; background: #fff;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Stock Transfers
            </a>
            <button type="button" onclick="openAddWarehouseModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; background: #2563eb; color: #fff; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                + Add Warehouse
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Warehouses</div>
            <div style="font-size: 28px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?= $totalWh ?></div>
            <div style="font-size: 12px; color: #10b981; margin-top: 4px;">Active Storage Facilities</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Primary Warehouse</div>
            <div style="font-size: 20px; font-weight: 700; color: #2563eb; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($primaryWhName) ?>">
                <?= htmlspecialchars($primaryWhName) ?>
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Default fulfillment hub</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Units Stored</div>
            <div style="font-size: 28px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?= number_format($totalUnits, 0) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Across all locations</div>
        </div>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Stored Stock Valuation</div>
            <div style="font-size: 28px; font-weight: 700; color: #059669; margin-top: 6px;">₹<?= number_format($totalVal, 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">At cost/purchase rates</div>
        </div>
    </div>

    <!-- Warehouse List Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Warehouse Catalog</h2>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 14px 20px;">Code / Name</th>
                        <th style="padding: 14px 20px;">Branch & City</th>
                        <th style="padding: 14px 20px;">Manager & Contact</th>
                        <th style="padding: 14px 20px; text-align: right;">Unique Items</th>
                        <th style="padding: 14px 20px; text-align: right;">Total Units</th>
                        <th style="padding: 14px 20px; text-align: right;">Stock Valuation</th>
                        <th style="padding: 14px 20px; text-align: center;">Status</th>
                        <th style="padding: 14px 20px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid #f1f5f9;">
                    <?php if (empty($warehouses)): ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
                                No warehouses found. Click <strong>+ Add Warehouse</strong> to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                <td style="padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <a href="/inventory/warehouses/<?= (int)$wh['id'] ?>" style="font-weight: 600; color: #2563eb; text-decoration: none;">
                                            <?= htmlspecialchars($wh['name']) ?>
                                        </a>
                                        <?php if (!empty($wh['is_primary'])): ?>
                                            <span style="font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 9999px;">PRIMARY</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                        Code: <span style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($wh['code'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; color: #334155;">
                                    <div><?= htmlspecialchars($wh['branch_name'] ?? 'Main Branch') ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($wh['city'] ?? '—') ?></div>
                                </td>
                                <td style="padding: 16px 20px; color: #334155;">
                                    <div><?= htmlspecialchars($wh['manager_name'] ?? '—') ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($wh['phone'] ?? '—') ?></div>
                                </td>
                                <td style="padding: 16px 20px; text-align: right; font-weight: 600; color: #1e293b;">
                                    <?= number_format((int)$wh['product_count']) ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: right; font-weight: 600; color: #1e293b;">
                                    <?= number_format((float)$wh['total_quantity'], 0) ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: right; font-weight: 600; color: #059669;">
                                    ₹<?= number_format((float)$wh['total_valuation'], 2) ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: center;">
                                    <?php if (!empty($wh['is_active'])): ?>
                                        <span style="display: inline-block; padding: 3px 10px; font-size: 12px; font-weight: 600; border-radius: 9999px; background: #ecfdf5; color: #059669;">Active</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 3px 10px; font-size: 12px; font-weight: 600; border-radius: 9999px; background: #fef2f2; color: #dc2626;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="/inventory/warehouses/<?= (int)$wh['id'] ?>" class="btn btn-sm" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #f1f5f9; color: #334155; text-decoration: none; border: 1px solid #cbd5e1;">
                                            View Stock
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Warehouse -->
<div id="addWarehouseModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; width: 100%; max-width: 580px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; margin: 16px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Add New Warehouse / Godown</h3>
            <button type="button" onclick="closeAddWarehouseModal()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form action="/inventory/warehouses" method="POST" style="padding: 24px;">
            <?= csrf_field() ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Warehouse Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Central Godown - Bhiwandi" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Code</label>
                    <input type="text" name="code" placeholder="e.g. WH-BHW" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Branch Assignment</label>
                    <select name="branch_id" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">City</label>
                    <input type="text" name="city" placeholder="e.g. Mumbai" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Address</label>
                <textarea name="address" rows="2" placeholder="Full address of the facility" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Manager Name</label>
                    <input type="text" name="manager_name" placeholder="e.g. Rajesh Patil" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Phone / Mobile</label>
                    <input type="text" name="phone" placeholder="e.g. +91 98200 12345" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 24px; display: flex; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="is_primary" value="1" style="width: 16px; height: 16px;">
                    Set as Primary Warehouse
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" checked style="width: 16px; height: 16px;">
                    Active Status
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeAddWarehouseModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer;">Save Warehouse</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddWarehouseModal() {
    document.getElementById('addWarehouseModal').style.display = 'flex';
}
function closeAddWarehouseModal() {
    document.getElementById('addWarehouseModal').style.display = 'none';
}
</script>

