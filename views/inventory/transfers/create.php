<?php
$title = $title ?? 'New Stock Transfer Order';
$warehouses = $warehouses ?? [];
$products = $products ?? [];
$selectedFromWh = (int)($_GET['from_warehouse_id'] ?? 0);
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
            <a href="/inventory/transfers" style="color: #64748b; text-decoration: none;">Stock Transfers</a>
            <span>/</span>
            <span style="color: #1e293b; font-weight: 500;">New Transfer Order</span>
        </div>
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Initiate Stock Transfer</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Create an inter-warehouse or inter-branch transfer dispatch.</p>
    </div>

    <form action="/inventory/transfers/create" method="POST" id="transferForm">
        <?= csrf_field() ?>

        <!-- Route & Header Card -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 20px 0;">Logistics & Route Information</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Source Warehouse (Dispatch From) <span style="color: #ef4444;">*</span></label>
                    <select name="from_warehouse_id" id="from_warehouse_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- Select Source Facility --</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int)$w['id'] ?>" <?= ($selectedFromWh === (int)$w['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($w['name']) ?> (<?= htmlspecialchars($w['code'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Destination Warehouse (Receive At) <span style="color: #ef4444;">*</span></label>
                    <select name="to_warehouse_id" id="to_warehouse_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- Select Destination Facility --</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int)$w['id'] ?>">
                                <?= htmlspecialchars($w['name']) ?> (<?= htmlspecialchars($w['code'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Transfer Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="transfer_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Reference / Gate Pass #</label>
                    <input type="text" name="reference_no" placeholder="e.g. GP-2026-901" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-top: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Logistics Notes / Vehicle / Driver Details</label>
                <textarea name="notes" rows="2" placeholder="Driver name, vehicle registration number, dispatch instructions..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-top: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="auto_approve" value="1" checked style="width: 16px; height: 16px;">
                    Approve automatically upon creation (Skip pending review)
                </label>
            </div>
        </div>

        <!-- Line Items Card -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Transfer Items</h2>
                <button type="button" onclick="addItemRow()" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600; color: #2563eb; cursor: pointer;">
                    + Add Product Row
                </button>
            </div>

            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;" id="itemsTable">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                        <th style="padding: 10px 14px; width: 45%;">Product Description</th>
                        <th style="padding: 10px 14px; width: 20%;">Transfer Qty</th>
                        <th style="padding: 10px 14px; width: 15%;">Unit</th>
                        <th style="padding: 10px 14px; width: 15%;">Notes</th>
                        <th style="padding: 10px 14px; width: 5%; text-align: center;"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px 14px;">
                            <select name="items[0][product_id]" required onchange="handleProductChange(this, 0)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                                <option value="">-- Choose Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-unit="<?= htmlspecialchars($p['unit'] ?? 'PCS') ?>" data-stock="<?= (float)$p['current_stock'] ?>">
                                        <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku'] ?? '—') ?> | Total Stock: <?= (float)$p['current_stock'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="padding: 12px 14px;">
                            <input type="number" name="items[0][quantity]" step="0.01" min="0.01" required placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </td>
                        <td style="padding: 12px 14px;">
                            <input type="text" name="items[0][unit]" id="unit_0" value="PCS" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </td>
                        <td style="padding: 12px 14px;">
                            <input type="text" name="items[0][notes]" placeholder="Batch / Lot info" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            <button type="button" onclick="removeRow(this)" style="background: none; border: none; color: #ef4444; font-size: 18px; cursor: pointer;">&times;</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Form Actions -->
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="/inventory/transfers" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; text-decoration: none;">Cancel</a>
            <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer;">Generate Transfer Order</button>
        </div>
    </form>
</div>

<script>
let rowCount = 1;
const productOptions = `<?php foreach ($products as $p): ?>
    <option value="<?= (int)$p['id'] ?>" data-unit="<?= htmlspecialchars($p['unit'] ?? 'PCS') ?>" data-stock="<?= (float)$p['current_stock'] ?>">
        <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku'] ?? '—') ?> | Total Stock: <?= (float)$p['current_stock'] ?>)
    </option>
<?php endforeach; ?>`;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #f1f5f9';
    tr.innerHTML = `
        <td style="padding: 12px 14px;">
            <select name="items[${rowCount}][product_id]" required onchange="handleProductChange(this, ${rowCount})" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                <option value="">-- Choose Product --</option>
                ${productOptions}
            </select>
        </td>
        <td style="padding: 12px 14px;">
            <input type="number" name="items[${rowCount}][quantity]" step="0.01" min="0.01" required placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        </td>
        <td style="padding: 12px 14px;">
            <input type="text" name="items[${rowCount}][unit]" id="unit_${rowCount}" value="PCS" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        </td>
        <td style="padding: 12px 14px;">
            <input type="text" name="items[${rowCount}][notes]" placeholder="Batch / Lot info" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        </td>
        <td style="padding: 12px 14px; text-align: center;">
            <button type="button" onclick="removeRow(this)" style="background: none; border: none; color: #ef4444; font-size: 18px; cursor: pointer;">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);
    rowCount++;
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
    } else {
        showTransferError('Transfer order must have at least one product row.');
    }
}

function showTransferError(msg) {
    let errBox = document.getElementById('transferClientError');
    if (!errBox) {
        errBox = document.createElement('div');
        errBox.id = 'transferClientError';
        errBox.style.cssText = 'background: #fef2f2; border: 1px solid #f87171; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px;';
        const form = document.getElementById('transferForm');
        form.insertBefore(errBox, form.firstChild);
    }
    errBox.textContent = msg;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function handleProductChange(select, index) {
    const opt = select.options[select.selectedIndex];
    const unitInput = document.getElementById(`unit_${index}`);
    if (unitInput && opt.dataset.unit) {
        unitInput.value = opt.dataset.unit;
    }
}

document.getElementById('transferForm').addEventListener('submit', function(e) {
    const fromWh = document.getElementById('from_warehouse_id').value;
    const toWh = document.getElementById('to_warehouse_id').value;
    if (fromWh === toWh) {
        e.preventDefault();
        showTransferError('Source and destination warehouses cannot be the same.');
    }
});
</script>
