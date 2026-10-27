<?php

use WTSBill\Core\Config;

$appName = Config::get('app.name', 'WTSBill ERP');
$currentUri = $currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/');

// Helper to check active state
$isActive = function(string $path) use ($currentUri): string {
    if ($path === '/' && ($currentUri === '/' || $currentUri === '')) {
        return 'active';
    }
    if ($path !== '/' && str_starts_with($currentUri, $path)) {
        return 'active';
    }
    return '';
};

// Helper to check if any child path in a section group is currently active
$groupHasActive = function(array $paths) use ($currentUri): bool {
    foreach ($paths as $path) {
        if ($path !== '/' && str_starts_with($currentUri, $path)) {
            return true;
        }
    }
    return false;
};
?>
<aside id="erp-sidebar" class="erp-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">W</div>
        <div class="brand-title"><?= e($appName) ?></div>
        <span class="brand-tag" style="display: none;" aria-hidden="true">v2.0</span>
    </div>

    <nav class="sidebar-nav">
        <!-- Standalone Dashboard Link -->
        <a href="/" class="nav-item <?= $isActive('/') ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </span>
            <span>Dashboard</span>
        </a>

        <!-- 1. Parties & CRM Group -->
        <?php $partiesActive = $groupHasActive(['/customers', '/suppliers']); ?>
        <div class="nav-group collapsed <?= $partiesActive ? 'has-active' : '' ?>" data-nav-group="parties">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('parties', event)" aria-expanded="false" title="Parties & CRM">
                <span>Parties & CRM</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-parties">
                <a href="/customers" class="nav-item <?= $isActive('/customers') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </span>
                    <span>Customers</span>
                </a>
                <a href="/suppliers" class="nav-item <?= $isActive('/suppliers') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    </span>
                    <span>Suppliers</span>
                </a>
            </div>
        </div>

        <!-- 2. Inventory Group -->
        <?php $invActive = $groupHasActive(['/products', '/categories', '/inventory/stock', '/inventory']); ?>
        <div class="nav-group collapsed <?= $invActive ? 'has-active' : '' ?>" data-nav-group="inventory">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('inventory', event)" aria-expanded="false" title="Inventory">
                <span>Inventory</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-inventory">
                <a href="/products" class="nav-item <?= $isActive('/products') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    </span>
                    <span>Items & Catalog</span>
                </a>
                <a href="/categories" class="nav-item <?= $isActive('/categories') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    </span>
                    <span>Categories</span>
                </a>
                <a href="/inventory/stock" class="nav-item <?= $isActive('/inventory/stock') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    </span>
                    <span>Stock Adjustments</span>
                </a>
            </div>
        </div>

        <!-- 3. Sales & Billing Group -->
        <?php $salesActive = $groupHasActive(['/sales']); ?>
        <div class="nav-group collapsed <?= $salesActive ? 'has-active' : '' ?>" data-nav-group="sales">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('sales', event)" aria-expanded="false" title="Sales & Billing">
                <span>Sales & Billing</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-sales">
                <a href="/sales/invoices" class="nav-item <?= $isActive('/sales/invoices') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </span>
                    <span>Tax Invoices</span>
                </a>
                <a href="/sales/quotations" class="nav-item <?= $isActive('/sales/quotations') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </span>
                    <span>Quotations & Orders</span>
                </a>
                <a href="/sales/challans" class="nav-item <?= $isActive('/sales/challans') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>
                    </span>
                    <span>Delivery Challans</span>
                </a>
            </div>
        </div>

        <!-- 4. Purchases & Expenses Group -->
        <?php $purchasesActive = $groupHasActive(['/purchases', '/expenses']); ?>
        <div class="nav-group collapsed <?= $purchasesActive ? 'has-active' : '' ?>" data-nav-group="purchases">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('purchases', event)" aria-expanded="false" title="Purchases & Expenses">
                <span>Purchases & Expenses</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-purchases">
                <a href="/purchases/bills" class="nav-item <?= $isActive('/purchases/bills') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    </span>
                    <span>Purchase Bills</span>
                </a>
                <a href="/expenses" class="nav-item <?= $isActive('/expenses') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </span>
                    <span>Expenses</span>
                </a>
            </div>
        </div>

        <!-- 5. Double-Entry Accounting Group -->
        <?php $acctActive = $groupHasActive(['/accounts']); ?>
        <div class="nav-group collapsed <?= $acctActive ? 'has-active' : '' ?>" data-nav-group="accounting">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('accounting', event)" aria-expanded="false" title="Double-Entry Accounting">
                <span>Double-Entry Accounting</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-accounting">
                <a href="/accounts" class="nav-item <?= $isActive('/accounts') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"></line><line x1="6" y1="18" x2="6" y2="11"></line><line x1="10" y1="18" x2="10" y2="11"></line><line x1="14" y1="18" x2="14" y2="11"></line><line x1="18" y1="18" x2="18" y2="11"></line><polygon points="12 2 20 7 4 7"></polygon></svg>
                    </span>
                    <span>Chart of Accounts</span>
                </a>
                <a href="/accounts/journals" class="nav-item <?= $isActive('/accounts/journals') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    </span>
                    <span>Journal Entries</span>
                </a>
                <a href="/accounts/ledger" class="nav-item <?= $isActive('/accounts/ledger') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                    </span>
                    <span>General Ledger</span>
                </a>
            </div>
        </div>

        <!-- 6. Compliance & Reports Group -->
        <?php $reportsActive = $groupHasActive(['/reports']); ?>
        <div class="nav-group collapsed <?= $reportsActive ? 'has-active' : '' ?>" data-nav-group="reports">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('reports', event)" aria-expanded="false" title="Compliance & Reports">
                <span>Compliance & Reports</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-reports">
                <a href="/reports/profit-loss" class="nav-item <?= $isActive('/reports/profit-loss') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    </span>
                    <span>Profit & Loss</span>
                </a>
                <a href="/reports/balance-sheet" class="nav-item <?= $isActive('/reports/balance-sheet') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                    </span>
                    <span>Balance Sheet</span>
                </a>
                <a href="/reports/gst" class="nav-item <?= $isActive('/reports/gst') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                    </span>
                    <span>GSTR-1 Compliance</span>
                </a>
            </div>
        </div>

        <!-- 7. Administration Group -->
        <?php $adminActive = $groupHasActive(['/users', '/settings']); ?>
        <div class="nav-group collapsed <?= $adminActive ? 'has-active' : '' ?>" data-nav-group="admin">
            <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('admin', event)" aria-expanded="false" title="Administration">
                <span>Administration</span>
                <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="nav-group-items" id="nav-group-admin">
                <a href="/users" class="nav-item <?= $isActive('/users') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                    </span>
                    <span>Users & Roles</span>
                </a>
                <a href="/settings" class="nav-item <?= $isActive('/settings') ?>">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </span>
                    <span>System Settings</span>
                </a>
            </div>
        </div>
    </nav>
</aside>
<div id="sidebar-overlay" class="sidebar-overlay" onclick="window.closeSidebar()"></div>

<script>
// Enterprise Sidebar Dropdown & Accordion Controller
window.toggleNavGroup = function(groupId, e) {
    if (e) {
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    var group = document.querySelector('[data-nav-group="' + groupId + '"]');
    if (!group) return;

    var isCollapsed = group.classList.contains('collapsed');

    if (isCollapsed) {
        group.classList.remove('collapsed');
    } else {
        group.classList.add('collapsed');
    }

    var btn = group.querySelector('.nav-group-toggle');
    if (btn) {
        btn.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
    }

    try {
        var openGroups = JSON.parse(sessionStorage.getItem('wtsbill_open_nav_groups') || '[]');
        if (!Array.isArray(openGroups)) openGroups = [];
        if (isCollapsed) {
            if (openGroups.indexOf(groupId) === -1) openGroups.push(groupId);
        } else {
            openGroups = openGroups.filter(function(id) { return id !== groupId; });
        }
        sessionStorage.setItem('wtsbill_open_nav_groups', JSON.stringify(openGroups));
    } catch(err) {}
};

window.initNavGroups = function() {
    try {
        var openGroups = JSON.parse(sessionStorage.getItem('wtsbill_open_nav_groups') || '[]');
        if (Array.isArray(openGroups) && openGroups.length > 0) {
            openGroups.forEach(function(groupId) {
                var group = document.querySelector('[data-nav-group="' + groupId + '"]');
                if (group) {
                    group.classList.remove('collapsed');
                    var btn = group.querySelector('.nav-group-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'true');
                }
            });
        }
    } catch(e) {}
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initNavGroups);
} else {
    window.initNavGroups();
}
</script>
