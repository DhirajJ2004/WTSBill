<?php
/**
 * WTSBill ERP — Sales Order Details View
 *
 * @var array $order
 */

$oid = (int)$order['id'];
?>

<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title" style="margin: 0;"><?= e($order['order_number']) ?></h1>
            <span class="badge badge-<?= $order['status'] === 'COMPLETED' ? 'success' : ($order['status'] === 'CANCELLED' ? 'danger' : 'primary') ?>">
                <?= e($order['status']) ?>
            </span>
            <span class="badge badge-<?= $order['fulfillment_status'] === 'FULFILLED' ? 'success' : 'secondary' ?>">
                <?= e($order['fulfillment_status'] ?: 'UNFULFILLED') ?>
            </span>
        </div>
        <p class="page-subtitle" style="margin-top: 0.25rem;">
            Order Date: <?= e(format_date($order['order_date'])) ?> &bull; Branch: <?= e($order['branch_name'] ?: 'Main Branch') ?>
        </p>
    </div>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/sales/orders" class="btn btn-secondary" style="text-decoration: none;">&larr; All Orders</a>
        <a href="/sales/orders/<?= $oid ?>/print" target="_blank" class="btn btn-secondary" style="text-decoration: none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 3px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Order
        </a>

        <?php if (empty($order['linked_invoice']) && $order['status'] !== 'CANCELLED'): ?>
            <form method="POST" action="/sales/orders/<?= $oid ?>/convert-to-invoice" style="display: inline;" onsubmit="return confirm('Generate confirmed GST Tax Invoice for this Sales Order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">+ Generate Tax Invoice</button>
            </form>
        <?php endif; ?>

        <?php if (empty($order['linked_challan']) && $order['status'] !== 'CANCELLED' && $order['status'] !== 'COMPLETED'): ?>
            <form method="POST" action="/sales/orders/<?= $oid ?>/convert-to-challan" style="display: inline;" onsubmit="return confirm('Generate Delivery Challan for this Sales Order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success">+ Create Delivery Challan</button>
            </form>
        <?php endif; ?>

        <?php if (empty($order['linked_invoice']) && \WTSBill\Auth\Gate::can('quotations.delete')): ?>
            <form method="POST" action="/sales/orders/<?= $oid ?>/delete" style="display: inline;" onsubmit="return confirm('Permanently delete this sales order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger" style="color: var(--color-danger); border-color: var(--color-danger);">Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Traceability & Lineage Notice -->
<?php if (!empty($order['linked_invoice']) || !empty($order['linked_challan']) || !empty($order['source_quotation_number'])): ?>
    <div class="card" style="margin-bottom: 1.25rem; background: var(--bg-surface, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); padding: 1rem 1.25rem;">
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: center; font-size: 0.85rem;">
            <?php if (!empty($order['source_quotation_number'])): ?>
                <div>
                    <span style="color: var(--color-muted);">Source Quotation:</span>
                    <a href="/sales/quotations/<?= (int)$order['source_quotation_id'] ?>/print" target="_blank" style="color: var(--color-primary); font-weight: 700; text-decoration: underline;">
                        <?= e($order['source_quotation_number']) ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['linked_challan'])): ?>
                <div>
                    <span style="color: var(--color-muted);">Delivery Challan:</span>
                    <strong><?= e($order['linked_challan']['challan_number']) ?></strong>
                    <span class="badge badge-info" style="font-size: 0.7rem;"><?= e($order['linked_challan']['status']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['linked_invoice'])): ?>
                <div>
                    <span style="color: var(--color-muted);">Generated Tax Invoice:</span>
                    <a href="/sales/invoices/<?= (int)$order['linked_invoice']['id'] ?>/print" target="_blank" style="color: var(--color-success); font-weight: 700; text-decoration: underline;">
                        <?= e($order['linked_invoice']['invoice_number']) ?>
                    </a>
                    <span class="badge badge-success" style="font-size: 0.7rem;">PAID/ACTIVE</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Customer & Delivery Meta Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Customer Details Card -->
    <div class="card" style="padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.85rem; text-transform: uppercase; color: var(--color-muted);">Customer &amp; Billing Information</h4>
        <div style="font-size: 0.9rem; line-height: 1.6;">
            <strong>
                <a href="/customers/<?= (int)$order['customer_id'] ?>" style="color: inherit; text-decoration: none;">
                    <?= e($order['customer_name']) ?>
                </a>
            </strong><br>
            <?php if (!empty($order['customer_company'])): ?>
                <?= e($order['customer_company']) ?><br>
            <?php endif; ?>
            <?= e($order['customer_address']) ?><br>
            <?= e($order['customer_city']) ?>, <?= e($order['customer_state']) ?> - <?= e($order['customer_pincode']) ?><br>
            <strong>GSTIN:</strong> <code><?= e($order['customer_gstin'] ?: 'UNREGISTERED') ?></code><br>
            <strong>Phone:</strong> <?= e($order['customer_phone'] ?: '—') ?>
        </div>
    </div>

    <!-- Order Metadata Card -->
    <div class="card" style="padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.85rem; text-transform: uppercase; color: var(--color-muted);">Order Fulfillment Details</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.85rem;">
            <div>
                <small style="color: var(--color-muted); display: block;">Order Date</small>
                <strong><?= e(format_date($order['order_date'])) ?></strong>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block;">Expected Delivery</small>
                <strong><?= !empty($order['expected_delivery']) ? e(format_date($order['expected_delivery'])) : '—' ?></strong>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block;">Customer Ref / PO</small>
                <code><?= e($order['reference_no'] ?: '—') ?></code>
            </div>
            <div>
                <small style="color: var(--color-muted); display: block;">Operating Branch</small>
                <span><?= e($order['branch_name'] ?: 'Main Branch') ?></span>
            </div>
        </div>
        <?php if (!empty($order['notes'])): ?>
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color, #e2e8f0); font-size: 0.85rem;">
                <small style="color: var(--color-muted); display: block;">Notes / Instructions</small>
                <div><?= nl2br(e($order['notes'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Line Items Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); padding: 0.85rem 1.25rem;">
        <h3 class="card-title" style="margin: 0; font-size: 0.95rem; text-transform: uppercase; color: var(--color-muted);">
            Ordered Products &amp; Quantities
        </h3>
    </div>
    <div class="table-container">
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th>Product / Description</th>
                    <th>HSN/SAC</th>
                    <th style="text-align: right;">Quantity</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Taxable Amount</th>
                    <th style="text-align: right;">GST Rate</th>
                    <th style="text-align: right;">Tax Amount</th>
                    <th style="text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $idx => $it): ?>
                    <?php
                    $lineTax = (float)$it['cgst_amount'] + (float)$it['sgst_amount'] + (float)$it['igst_amount'];
                    ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <strong><?= e($it['item_name']) ?></strong>
                            <?php if (!empty($it['catalog_product_sku'])): ?>
                                <span style="font-size: 0.75rem; color: var(--color-muted);">(SKU: <?= e($it['catalog_product_sku']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($it['hsn_sac'] ?: '—') ?></code></td>
                        <td style="text-align: right;" class="tabular-nums">
                            <strong><?= (float)$it['quantity'] ?></strong> <?= e($it['unit'] ?: 'Pcs') ?>
                        </td>
                        <td style="text-align: right;" class="tabular-nums"><?= format_currency((float)$it['unit_price']) ?></td>
                        <td style="text-align: right;" class="tabular-nums"><?= format_currency((float)$it['taxable_value']) ?></td>
                        <td style="text-align: right;"><?= (float)$it['gst_rate'] ?>%</td>
                        <td style="text-align: right;" class="tabular-nums"><?= format_currency($lineTax) ?></td>
                        <td style="text-align: right; font-weight: 700;" class="tabular-nums"><?= format_currency((float)$it['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Order Financial Summary -->
    <div style="padding: 1.25rem; border-top: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: flex-end;">
        <div style="width: 320px; font-size: 0.9rem; line-height: 1.8;">
            <div style="display: flex; justify-content: space-between;">
                <span>Taxable Subtotal:</span>
                <strong><?= format_currency((float)$order['sub_total']) ?></strong>
            </div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; color: var(--color-success);">
                    <span>Discount:</span>
                    <span>- <?= format_currency((float)$order['discount_amount']) ?></span>
                </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between;">
                <span>Total Tax (GST):</span>
                <strong><?= format_currency((float)$order['total_tax']) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--color-heading); border-top: 1px solid var(--border-color, #e2e8f0); padding-top: 0.5rem; margin-top: 0.25rem;">
                <span>Order Total:</span>
                <span><?= format_currency((float)$order['grand_total']) ?></span>
            </div>
        </div>
    </div>
</div>

