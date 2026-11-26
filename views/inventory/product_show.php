<?php
/**
 * WTSBill ERP — Product Details & Specification View
 *
 * @var array $product
 * @var array $categories
 * @var array $units
 */
$title = $title ?? ($product['name'] . ' — Product Details');
$salesPrice = (float)($product['sales_price'] ?? ($product['selling_price'] ?? 0));
$purchasePrice = (float)($product['purchase_price'] ?? 0);
$margin = $salesPrice - $purchasePrice;
$marginPct = $salesPrice > 0 ? round(($margin / $salesPrice) * 100, 1) : 0;
$stock = (float)($product['current_stock'] ?? 0);
$minStock = (float)($product['min_stock_alert'] ?? 0);
$isActive = (bool)($product['is_active'] ?? 1);
$unit = htmlspecialchars(strtoupper($product['unit'] ?? 'PCS'));
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Breadcrumb Navigation -->
    <div style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 6px;">
            <a href="/products" style="color: #64748b; text-decoration: none;">Product Catalog</a>
            <span>/</span>
            <a href="/items" style="color: #64748b; text-decoration: none;">Items Hub</a>
            <span>/</span>
            <span style="color: #1e293b; font-weight: 600;">Product #<?= (int)$product['id'] ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <h1 style="font-size: 24px; font-weight: 700; color: #1E293B; margin: 0;"><?= htmlspecialchars($product['name']) ?></h1>
                    <span class="badge" style="background: <?= $isActive ? '#EBF5FC' : '#FEE2E2' ?>; color: <?= $isActive ? '#355872' : '#991B1B' ?>; border: 1px solid <?= $isActive ? '#7AAACE' : '#FCA5A5' ?>; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                        <?= $isActive ? '● ACTIVE' : '○ INACTIVE' ?>
                    </span>
                    <span class="badge" style="background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px;">
                        <?= htmlspecialchars($product['product_type'] ?? 'Goods') ?>
                    </span>
                </div>
                <div style="font-size: 13px; color: #64748b; margin-top: 6px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <?php if (!empty($product['sku'])): ?>
                        <span>SKU: <strong style="color: #0f172a; font-family: monospace;"><?= htmlspecialchars($product['sku']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($product['barcode'])): ?>
                        <span>Barcode: <strong style="color: #0f172a; font-family: monospace;"><?= htmlspecialchars($product['barcode']) ?></strong></span>
                    <?php endif; ?>
                    <span>Category: <strong style="color: #355872;"><?= htmlspecialchars($product['category_name'] ?? 'General') ?></strong></span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="/products" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #334155; background: #fff; text-decoration: none;">
                    &larr; Back to Catalog
                </a>
                <button type="button" class="btn btn-primary" onclick="openEditModal()" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; background: #355872; color: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(53, 88, 114,0.25);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span>Edit Product</span>
                </button>
                <?php if (\WTSBill\Auth\Gate::can('items.delete') || \WTSBill\Auth\Gate::role() === 'admin'): ?>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentProduct()" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 13px; font-weight: 600; color: #DC2626; background: #FEF2F2; cursor: pointer;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Delete</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4 KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <!-- Selling Price -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border-left: 4px solid #355872;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Selling Price (Rate)</div>
            <div style="font-size: 26px; font-weight: 700; color: #1E293B; margin-top: 6px;">₹ <?= number_format($salesPrice, 2) ?></div>
            <div style="font-size: 12px; color: #2A485E; margin-top: 4px;">Per <?= $unit ?> (Tax Exclusive)</div>
        </div>

        <!-- Purchase Price & Margin -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border-left: 4px solid #0284c7;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Purchase Cost</div>
            <div style="font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 6px;">₹ <?= number_format($purchasePrice, 2) ?></div>
            <div style="font-size: 12px; color: #0284c7; font-weight: 600; margin-top: 4px;">Margin: ₹ <?= number_format($margin, 2) ?> (<?= $marginPct ?>%)</div>
        </div>

        <!-- Current Stock -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border-left: 4px solid <?= $stock <= $minStock ? '#ef4444' : '#10b981' ?>;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Current Stock</div>
            <div style="font-size: 26px; font-weight: 700; color: <?= $stock <= $minStock ? '#b91c1c' : '#047857' ?>; margin-top: 6px;">
                <?= number_format($stock, 2) ?> <?= $unit ?>
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                <?= $stock <= $minStock ? '⚠️ Low Stock (Alert at ' . number_format($minStock, 0) . ')' : '✓ In Stock (Alert at ' . number_format($minStock, 0) . ')' ?>
            </div>
        </div>

        <!-- GST & HSN/SAC -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border-left: 4px solid #8b5cf6;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">GST Tax Rate</div>
            <div style="font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?= (float)($product['tax_rate'] ?? 18) ?>%</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">HSN / SAC: <code><?= htmlspecialchars($product['hsn_sac'] ?? ($product['hsn_code'] ?? '85444999')) ?></code></div>
        </div>
    </div>

    <!-- Details Sections (2 Columns) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <!-- Left: Commercial & Product Info -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="padding: 16px 20px; background: #253E52; color: #fff; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #EBF5FC;">General & Commercial Specifications</h3>
                <span style="font-size: 12px; color: #7AAACE;">Master Data</span>
            </div>
            <div style="padding: 20px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tbody>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b; width: 45%;">Item / Product Name</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;"><?= htmlspecialchars($product['name']) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">SKU Code</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right; font-family: monospace;"><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Category</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #355872; text-align: right;"><?= htmlspecialchars($product['category_name'] ?? 'General') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">HSN / SAC Code</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right; font-family: monospace;"><?= htmlspecialchars($product['hsn_sac'] ?? 'N/A') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Base Unit of Measure</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;"><?= htmlspecialchars($product['unit'] ?? 'Pcs') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Barcode / EAN</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right; font-family: monospace;"><?= htmlspecialchars($product['barcode'] ?? 'N/A') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Tax Treatment</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= (!empty($product['is_tax_inclusive'])) ? 'Tax Inclusive' : 'Tax Exclusive (GST @ ' . (float)($product['tax_rate'] ?? 18) . '%)' ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #64748b;">Registered On</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;"><?= !empty($product['created_at']) ? date('d M Y, h:i A', strtotime($product['created_at'])) : 'N/A' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Inventory & Stock Control Policies -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="padding: 16px 20px; background: #253E52; color: #fff; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #EBF5FC;">Inventory Tracking & Reorder Settings</h3>
                <span style="font-size: 12px; color: #7AAACE;">Stock Controls</span>
            </div>
            <div style="padding: 20px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tbody>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b; width: 45%;">Inventory Tracking</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #047857; text-align: right;">
                                <?= (!empty($product['track_inventory'])) ? '✓ Enabled' : 'Disabled' ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Valuation Method</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= htmlspecialchars(str_replace('_', ' ', (string)($product['valuation_method'] ?? 'Weighted Average'))) ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Minimum Stock Alert</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= number_format((float)($product['min_stock_alert'] ?? 0), 2) ?> <?= $unit ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Reorder Level</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= number_format((float)($product['reorder_level'] ?? 0), 2) ?> <?= $unit ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Reorder Quantity</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= number_format((float)($product['reorder_quantity'] ?? 0), 2) ?> <?= $unit ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Max Inventory Level</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= number_format((float)($product['max_level'] ?? 0), 2) ?> <?= $unit ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 0; color: #64748b;">Allow Negative Stock</td>
                            <td style="padding: 10px 0; font-weight: 600; color: <?= !empty($product['allow_negative_stock']) ? '#b91c1c' : '#475569' ?>; text-align: right;">
                                <?= (!empty($product['allow_negative_stock'])) ? 'Yes' : 'No (Strict Guard)' ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #64748b;">Batch / Serial Tracking</td>
                            <td style="padding: 10px 0; font-weight: 600; color: #0f172a; text-align: right;">
                                <?= (!empty($product['has_batch']) ? 'Batch ' : '') . (!empty($product['has_serial']) ? 'Serial' : 'Standard') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Edit Modal -->
<div id="product-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 680px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #253E52; color: #fff;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #EBF5FC;">Edit Product</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 22px; color: #7AAACE; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form id="edit-product-form" onsubmit="submitEditProduct(event)" style="padding: 24px;">
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <div id="modal-error-box" style="display: none; padding: 10px 14px; background: #fee2e2; color: #991b1b; border-radius: 6px; font-size: 13px; margin-bottom: 16px;"></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Product Name *</label>
                    <input type="text" name="name" id="f-name" value="<?= htmlspecialchars($product['name']) ?>" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">SKU / Item Code *</label>
                    <input type="text" name="sku" id="f-sku" value="<?= htmlspecialchars($product['sku']) ?>" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Category</label>
                    <select name="category_id" id="f-category" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- General / None --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ((int)($product['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Selling Price (₹) *</label>
                    <input type="number" step="0.01" name="selling_price" id="f-sales-price" value="<?= $salesPrice ?>" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Purchase Price (₹)</label>
                    <input type="number" step="0.01" name="purchase_price" id="f-purchase-price" value="<?= $purchasePrice ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Unit of Measure</label>
                    <input type="text" name="unit" id="f-unit" value="<?= htmlspecialchars($product['unit'] ?? 'Pcs') ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">HSN / SAC Code</label>
                    <input type="text" name="hsn_sac" id="f-hsn" value="<?= htmlspecialchars($product['hsn_sac'] ?? '') ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">GST Rate (%)</label>
                    <input type="number" step="0.01" name="tax_rate" id="f-tax-rate" value="<?= (float)($product['tax_rate'] ?? 18) ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px;">Min Stock Alert</label>
                    <input type="number" step="0.01" name="min_stock_alert" id="f-min-stock" value="<?= $minStock ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                <button type="button" onclick="closeEditModal()" style="padding: 9px 18px; border: 1px solid #cbd5e1; background: #fff; color: #475569; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">Cancel</button>
                <button type="submit" id="btn-save-edit" style="padding: 9px 24px; border: none; background: #355872; color: #fff; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal() {
    document.getElementById('product-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('product-modal').style.display = 'none';
}
function submitEditProduct(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-edit');
    const errBox = document.getElementById('modal-error-box');
    errBox.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const form = document.getElementById('edit-product-form');
    const formData = new FormData(form);
    const payload = {};
    formData.forEach((val, key) => { payload[key] = val; });

    fetch('/products/<?= (int)$product['id'] ?>/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        if (data.success) {
            window.location.reload();
        } else {
            errBox.textContent = data.message || 'Failed to update product.';
            errBox.style.display = 'block';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        errBox.textContent = 'Network error occurred while saving product.';
        errBox.style.display = 'block';
    });
}
function deleteCurrentProduct() {
    if (!confirm('Are you sure you want to delete product "<?= e(addslashes($product['name'])) ?>"? This will remove it from the catalog.')) {
        return;
    }
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('_token', metaToken);

    fetch('/products/<?= (int)$product['id'] ?>/delete', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': metaToken
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/items';
        } else {
            alert(data.message || 'Failed to delete product.');
        }
    })
    .catch(err => {
        alert('Network error occurred while deleting product.');
    });
}
</script>

