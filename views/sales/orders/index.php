<?php
/**
 * WTSBill ERP — Sales Orders Directory View
 *
 * @var array $orders
 * @var array $counts
 * @var string $currentStatus
 * @var string $search
 * @var int $branchId
 * @var array $branches
 */
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sales Orders</h1>
        <p class="page-subtitle">Commercial order confirmations, customer commitments, and fulfillment dispatch</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php if (\WTSBill\Auth\Gate::can('quotations.create')): ?>
            <a href="/sales/orders/create" class="btn btn-primary" style="text-decoration: none;">+ Create Sales Order</a>
        <?php endif; ?>
    </div>
</div>

<!-- Status Filter Pills Bar -->
<div class="status-pills-container" style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.75rem; margin-bottom: 1rem;">
    <?php
    $pills = [
        'ALL' => ['label' => 'All Orders', 'count' => $counts['ALL'] ?? 0],
        'PENDING' => ['label' => 'Pending', 'count' => $counts['PENDING'] ?? 0],
        'CONFIRMED' => ['label' => 'Confirmed', 'count' => $counts['CONFIRMED'] ?? 0],
        'PROCESSING' => ['label' => 'Processing', 'count' => $counts['PROCESSING'] ?? 0],
        'DISPATCHED' => ['label' => 'Dispatched', 'count' => $counts['DISPATCHED'] ?? 0],
        'COMPLETED' => ['label' => 'Completed', 'count' => $counts['COMPLETED'] ?? 0],
        'CANCELLED' => ['label' => 'Cancelled', 'count' => $counts['CANCELLED'] ?? 0],
    ];
    ?>
    <?php foreach ($pills as $key => $p): ?>
        <?php $isActive = strtoupper($currentStatus) === $key; ?>
        <a href="/sales/orders?status=<?= $key ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>"
           class="status-pill <?= $isActive ? 'active' : '' ?>"
           style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.85rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-decoration: none; border: 1px solid <?= $isActive ? 'var(--color-primary, #0ea5e9)' : 'var(--border-color, #e2e8f0)' ?>; background: <?= $isActive ? 'var(--color-primary, #0ea5e9)' : 'var(--bg-card, #ffffff)' ?>; color: <?= $isActive ? '#ffffff' : 'var(--color-heading)' ?>;">
            <?= e($p['label']) ?>
            <span style="font-size: 0.72rem; padding: 0.1rem 0.4rem; border-radius: 9999px; background: <?= $isActive ? 'rgba(255,255,255,0.25)' : 'var(--bg-surface, #f1f5f9)' ?>; color: <?= $isActive ? '#ffffff' : 'var(--color-muted)' ?>;">
                <?= (int)$p['count'] ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 1.25rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="/sales/orders" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" class="form-control" placeholder="Search by Order #, Customer, or Ref..." value="<?= e($search) ?>" style="font-size: 0.85rem;">
            </div>
            <?php if (!empty($branches) && count($branches) > 1): ?>
                <div style="width: 180px;">
                    <select name="branch_id" class="form-control" style="font-size: 0.85rem;">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary" style="font-size: 0.85rem;">Filter</button>
            <?php if (!empty($search) || $branchId > 0 || $currentStatus !== 'ALL'): ?>
                <a href="/sales/orders" class="btn btn-secondary" style="font-size: 0.85rem; text-decoration: none;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="table-container">
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer Name</th>
                    <th>Ref / Quotation</th>
                    <th style="text-align: right;">Grand Total</th>
                    <th style="text-align: center;">Order Status</th>
                    <th style="text-align: center;">Fulfillment</th>
                    <th style="text-align: right; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--color-muted);">
                            No sales orders found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $so): ?>
                        <tr>
                            <td>
                                <a href="/sales/orders/<?= (int)$so['id'] ?>" style="color: var(--color-primary); font-weight: 700; text-decoration: none;">
                                    <?= e($so['order_number']) ?>
                                </a>
                            </td>
                            <td><?= e(format_date($so['order_date'])) ?></td>
                            <td>
                                <a href="/customers/<?= (int)$so['customer_id'] ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                    <?= e($so['customer_name'] ?: 'Direct Customer') ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($so['source_quotation_number'])): ?>
                                    <span class="badge badge-info" style="font-size: 0.72rem;">Quote: <?= e($so['source_quotation_number']) ?></span>
                                <?php elseif (!empty($so['reference_no'])): ?>
                                    <code><?= e($so['reference_no']) ?></code>
                                <?php else: ?>
                                    <span style="color: var(--color-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700;" class="tabular-nums">
                                <?= format_currency((float)$so['grand_total']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $stColor = match ($so['status']) {
                                    'COMPLETED' => 'success',
                                    'CONFIRMED', 'PROCESSING' => 'primary',
                                    'DISPATCHED' => 'info',
                                    'CANCELLED' => 'danger',
                                    default => 'warning',
                                };
                                ?>
                                <span class="badge badge-<?= $stColor ?>"><?= e($so['status']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $so['fulfillment_status'] === 'FULFILLED' ? 'success' : ($so['fulfillment_status'] === 'DISPATCHED' ? 'info' : 'secondary') ?>" style="font-size: 0.7rem;">
                                    <?= e($so['fulfillment_status'] ?: 'UNFULFILLED') ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                                    <a href="/sales/orders/<?= (int)$so['id'] ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none;">View</a>
                                    <a href="/sales/orders/<?= (int)$so['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none;">Print</a>
                                    <?php if ($so['status'] !== 'COMPLETED' && $so['status'] !== 'CANCELLED'): ?>
                                        <form method="POST" action="/sales/orders/<?= (int)$so['id'] ?>/convert-to-invoice" style="display: inline;" onsubmit="return confirm('Generate confirmed Tax Invoice for this order?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">+ Invoice</button>
                                        </form>
                                        <form method="POST" action="/sales/orders/<?= (int)$so['id'] ?>/convert-to-challan" style="display: inline;" onsubmit="return confirm('Generate Delivery Challan for this order?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">+ Challan</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

