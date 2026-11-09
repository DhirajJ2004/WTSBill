<?php
require_once dirname(__DIR__, 2) . '/includes/tabs.php';

$title = 'Items & Inventory Hub';
$activeTab = $activeTab ?? (string)($_GET['tab'] ?? 'items');

// Define the 5 tabs as requested
$tabs = [
    [
        'id' => 'items',
        'label' => 'Items & Catalog',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
    ],
    [
        'id' => 'categories',
        'label' => 'Categories',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
    ],
    [
        'id' => 'inout',
        'label' => 'Stock In/Out',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>',
    ],
    [
        'id' => 'adjustments',
        'label' => 'Stock Adjustments',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>',
    ],
    [
        'id' => 'transfers',
        'label' => 'Stock Transfers',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
    ],
];
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <span style="color: #235347; font-weight: 500;">Items & Stock</span>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">Items Hub</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">Items & Stock Management</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Unified catalog for product items, categories, stock tracking, manual adjustments, and godown transfers.</p>
        </div>
        <div style="display: flex; gap: 0.65rem;">
            <?php if ($activeTab === 'items'): ?>
                <button type="button" class="btn btn-primary" onclick="if(window.openProductModal){window.openProductModal();}else{location.href='/products#create';}" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; border: none; font-size: 0.85rem; cursor: pointer;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>+ Add New Item</span>
                </button>
            <?php elseif ($activeTab === 'categories'): ?>
                <button type="button" class="btn btn-primary" onclick="if(window.openCategoryModal){window.openCategoryModal();}else{location.href='/categories#create';}" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; border: none; font-size: 0.85rem; cursor: pointer;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>+ New Category</span>
                </button>
            <?php elseif ($activeTab === 'inout'): ?>
                <a href="/inventory/stock?action=adjust" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; text-decoration: none; font-size: 0.85rem;">
                    <span>+ Record Stock Movement</span>
                </a>
            <?php elseif ($activeTab === 'transfers'): ?>
                <a href="/inventory/transfers/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; text-decoration: none; font-size: 0.85rem;">
                    <span>+ New Transfer</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reusable Tabs Component -->
    <?= render_tabs($tabs, $activeTab, '/items') ?>

    <!-- TAB 1: Items & Catalog -->
    <?php if ($activeTab === 'items'): ?>
        <div class="tab-pane active" id="pane-items">
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <h2 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0;">Product Catalog (<?= count($itemsList ?? []) ?> Items)</h2>
                    <form method="GET" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="tab" value="items">
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search SKU, name or barcode..." style="padding: 0.45rem 0.75rem; border: 1px solid #8EB69B; border-radius: 6px; font-size: 0.82rem; background: #FFFFFF; color: #051F20;">
                        <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; background: #235347; color: #FFFFFF; border: none; border-radius: 6px; font-size: 0.82rem; cursor: pointer;">Search</button>
                    </form>
                </div>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                        <thead>
                            <tr style="background: #F4FAF6; border-bottom: 1px solid #8EB69B;">
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">ITEM NAME</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">SKU / HSN</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">CATEGORY</th>
                                <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; color: #051F20;">SALE PRICE</th>
                                <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; color: #051F20;">PURCHASE PRICE</th>
                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; color: #051F20;">STOCK</th>
                                <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; color: #051F20;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itemsList)): ?>
                                <?php foreach ($itemsList as $it): ?>
                                    <tr style="border-bottom: 1px solid #E8F5EB;">
                                        <td style="padding: 0.75rem 1rem; font-weight: 600; color: #051F20;">
                                            <?= htmlspecialchars($it['name']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #163832; font-size: 0.8rem;">
                                            <?= htmlspecialchars($it['sku'] ?? '-') ?> <?= !empty($it['hsn_code']) ? ' | HSN: ' . htmlspecialchars($it['hsn_code']) : '' ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #163832;">
                                            <span class="badge" style="background: #DAF1DE; color: #235347; border: 1px solid #8EB69B; font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                                <?= htmlspecialchars($it['category_name'] ?? 'General') ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #051F20;">
                                            ₹ <?= number_format((float)($it['sale_price'] ?? $it['unit_price'] ?? 0), 2) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; color: #163832;">
                                            ₹ <?= number_format((float)($it['purchase_price'] ?? 0), 2) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <?php $stock = (float)($it['current_stock'] ?? 0); ?>
                                            <span class="badge" style="<?= $stock <= 5 ? 'background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5;' : 'background: #DAF1DE; color: #235347; border: 1px solid #8EB69B;' ?> font-size: 0.74rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px;">
                                                <?= $stock ?> <?= htmlspecialchars(strtoupper($it['unit'] ?? 'PCS')) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <a href="/products/<?= (int)$it['id'] ?>" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.75rem; border: 1px solid #8EB69B; background: #DAF1DE; color: #235347; border-radius: 4px; text-decoration: none; font-weight: 600;">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="padding: 2.5rem; text-align: center; color: #163832;">
                                        No items found. <a href="/products" style="color: #235347; font-weight: 600;">Manage Products &rarr;</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 2: Categories -->
    <?php elseif ($activeTab === 'categories'): ?>
        <div class="tab-pane active" id="pane-categories">
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0;">Item Categories Master</h2>
                    <a href="/categories" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; background: #235347; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 0.8rem;">Open Full Category Manager &rarr;</a>
                </div>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                        <thead>
                            <tr style="background: #F4FAF6; border-bottom: 1px solid #8EB69B;">
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">CATEGORY NAME</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">SLUG / CODE</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #051F20;">DESCRIPTION</th>
                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; color: #051F20;">LINKED PRODUCTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categoriesList)): ?>
                                <?php foreach ($categoriesList as $cat): ?>
                                    <tr style="border-bottom: 1px solid #E8F5EB;">
                                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #051F20;">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #235347; font-size: 0.8rem; font-weight: 500;">
                                            <?= htmlspecialchars($cat['slug'] ?? '-') ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #163832;">
                                            <?= htmlspecialchars($cat['description'] ?? 'Standard Category') ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span class="badge" style="background: #DAF1DE; color: #235347; border: 1px solid #8EB69B; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 10px;">
                                                <?= (int)($cat['product_count'] ?? 0) ?> Items
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="padding: 2.5rem; text-align: center; color: #163832;">
                                        No categories defined yet. <a href="/categories" style="color: #235347; font-weight: 600;">Manage Categories &rarr;</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 3: Stock In/Out -->
    <?php elseif ($activeTab === 'inout'): ?>
        <div class="tab-pane active" id="pane-inout">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.25rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0 0 1rem 0;">Direct Stock In / Out (Inward & Deductions)</h3>
                    <p style="font-size: 0.82rem; color: #163832; margin-bottom: 1rem;">Record warehouse receipt, production surplus, damage write-offs, or sample allocations.</p>
                    <a href="/inventory/stock?action=adjust" class="btn btn-primary" style="display: inline-block; background: #235347; color: #FFFFFF; padding: 0.55rem 1.25rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">Launch Stock Movement Tool &rarr;</a>
                </div>
                <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.25rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0 0 0.5rem 0;">Batch Stock Adjustment</h3>
                    <p style="font-size: 0.82rem; color: #163832; margin-bottom: 1rem;">Perform comprehensive stock counts across multiple godowns with approval workflows.</p>
                    <a href="/inventory/stock" class="btn btn-secondary" style="display: inline-block; background: #DAF1DE; border: 1.5px solid #8EB69B; color: #163832; padding: 0.55rem 1.25rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">View Full Inventory &rarr;</a>
                </div>
            </div>
        </div>

    <!-- TAB 4: Stock Adjustments -->
    <?php elseif ($activeTab === 'adjustments'): ?>
        <div class="tab-pane active" id="pane-adjustments">
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0;">Stock Adjustments Register</h2>
                    <a href="/inventory/stock?tab=history" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; background: #235347; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 0.8rem;">Open Full Adjustment History &rarr;</a>
                </div>
                <div style="padding: 1.5rem; text-align: center; color: #163832;">
                    <p style="font-size: 0.9rem; margin-bottom: 1rem;">View all physical stock audit adjustments, discrepancies, damaged item removals, and corrections.</p>
                    <a href="/inventory/stock?tab=history" class="btn btn-primary" style="display: inline-block; padding: 0.55rem 1.25rem; background: #235347; color: #FFFFFF; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">View Adjustments Log &rarr;</a>
                </div>
            </div>
        </div>

    <!-- TAB 5: Stock Transfers -->
    <?php elseif ($activeTab === 'transfers'): ?>
        <div class="tab-pane active" id="pane-transfers">
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 1rem; font-weight: 700; color: #051F20; margin: 0;">Inter-Warehouse Stock Transfers</h2>
                    <a href="/inventory/transfers" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; background: #235347; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 0.8rem;">Open Full Transfers Hub &rarr;</a>
                </div>
                <div style="padding: 1.5rem; text-align: center; color: #163832;">
                    <p style="font-size: 0.9rem; margin-bottom: 1rem;">Manage dispatched materials, transit receipts, and godown-to-godown stock allocations.</p>
                    <a href="/inventory/transfers" class="btn btn-primary" style="display: inline-block; padding: 0.55rem 1.25rem; background: #235347; color: #FFFFFF; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">View All Stock Transfers &rarr;</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

