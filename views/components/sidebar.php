<?php
/**
 * WTSBill ERP - Reusable Sidebar Component
 *
 * Parameters:
 * - $currentRoute (string): active navigation key (e.g. 'dashboard', 'invoices', etc.)
 * - $currentCompany (array|null): active company metadata
 */

$currentRoute = $currentRoute ?? $route ?? 'dashboard';
if (!isset($currentCompany) && function_exists('get_current_company')) {
    $currentCompany = get_current_company();
}
$companyName = $currentCompany['name'] ?? 'Wis Technosavvy';
$companyId = function_exists('get_current_company_id') ? get_current_company_id() : 1;
?>
<aside class="sidebar" id="appSidebar">
    <!-- Sidebar Brand & Logo -->
    <div class="sidebar-header">
        <a href="<?= url('/dashboard') ?>" class="sidebar-brand-group">
            <div class="sidebar-logo-circle">
                <img src="<?= url('/assets/img/logo.png') ?>" alt="WTS Logo" class="sidebar-logo-img">
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-title">WTSBill</span>
                <span class="brand-subtitle">Enterprise ERP</span>
            </div>
        </a>
        <button class="sidebar-collapse-btn" onclick="toggleSidebar()" title="Toggle Sidebar (Ctrl+B)">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <!-- Grouped Navigation Menu -->
    <div class="sidebar-menu">
        <!-- 1. OVERVIEW -->
        <div class="menu-category">Overview</div>
        <a href="<?= url('/dashboard') ?>" class="menu-item <?= $currentRoute === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <span class="menu-icon-box">
                <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span class="menu-label">Dashboard</span>
        </a>

        <!-- 2. SALES -->
        <div class="menu-category">Sales</div>
        <a href="<?= url('/invoices') ?>" class="menu-item <?= $currentRoute === 'invoices' ? 'active' : '' ?>" title="Sales Invoices">
            <span class="menu-icon-box">
                <i class="fa-regular fa-file-lines"></i>
            </span>
            <span class="menu-label">Invoices</span>
        </a>
        <a href="<?= url('/quotations') ?>" class="menu-item <?= $currentRoute === 'quotations' ? 'active' : '' ?>" title="Quotations & Estimates">
            <span class="menu-icon-box">
                <i class="fa-solid fa-file-signature"></i>
            </span>
            <span class="menu-label">Quotations</span>
        </a>
        <a href="<?= url('/recurring-invoices') ?>" class="menu-item <?= $currentRoute === 'recurring_invoices' || $currentRoute === 'recurring-invoices' ? 'active' : '' ?>" title="Recurring Invoices">
            <span class="menu-icon-box">
                <i class="fa-solid fa-repeat"></i>
            </span>
            <span class="menu-label">Recurring</span>
        </a>
        <a href="<?= url('/credit-notes') ?>" class="menu-item <?= $currentRoute === 'credit_notes' || $currentRoute === 'credit-notes' ? 'active' : '' ?>" title="Credit Notes">
            <span class="menu-icon-box">
                <i class="fa-solid fa-receipt"></i>
            </span>
            <span class="menu-label">Credit Notes</span>
        </a>

        <!-- 3. PURCHASES -->
        <div class="menu-category">Purchases</div>
        <a href="<?= url('/purchases') ?>" class="menu-item <?= $currentRoute === 'purchases' ? 'active' : '' ?>" title="Purchase Bills & Orders">
            <span class="menu-icon-box">
                <i class="fa-solid fa-bag-shopping"></i>
            </span>
            <span class="menu-label">Purchase Bills</span>
        </a>
        <a href="<?= url('/debit-notes') ?>" class="menu-item <?= $currentRoute === 'debit_notes' || $currentRoute === 'debit-notes' ? 'active' : '' ?>" title="Debit Notes">
            <span class="menu-icon-box">
                <i class="fa-solid fa-arrow-rotate-left"></i>
            </span>
            <span class="menu-label">Debit Notes</span>
        </a>

        <!-- 4. INVENTORY -->
        <div class="menu-category">Inventory</div>
        <a href="<?= url('/inventory') ?>" class="menu-item <?= $currentRoute === 'inventory' ? 'active' : '' ?>" title="Stock & Warehouses">
            <span class="menu-icon-box">
                <i class="fa-solid fa-boxes-stacked"></i>
            </span>
            <span class="menu-label">Products & Stock</span>
        </a>
        <a href="<?= url('/parties') ?>" class="menu-item <?= $currentRoute === 'parties' ? 'active' : '' ?>" title="Customers & Suppliers">
            <span class="menu-icon-box">
                <i class="fa-solid fa-address-book"></i>
            </span>
            <span class="menu-label">Parties & Contacts</span>
        </a>

        <!-- 5. ACCOUNTING -->
        <div class="menu-category">Accounting</div>
        <a href="<?= url('/accounting') ?>" class="menu-item <?= $currentRoute === 'accounting' ? 'active' : '' ?>" title="Banking & Chart of Accounts">
            <span class="menu-icon-box">
                <i class="fa-solid fa-building-columns"></i>
            </span>
            <span class="menu-label">Banking & Ledger</span>
        </a>
        <a href="<?= url('/payments') ?>" class="menu-item <?= $currentRoute === 'payments' ? 'active' : '' ?>" title="Payments & Receipts">
            <span class="menu-icon-box">
                <i class="fa-regular fa-credit-card"></i>
            </span>
            <span class="menu-label">Payments</span>
        </a>
        <a href="<?= url('/expenses') ?>" class="menu-item <?= $currentRoute === 'expenses' ? 'active' : '' ?>" title="Expenses & Petty Cash">
            <span class="menu-icon-box">
                <i class="fa-solid fa-money-bill-transfer"></i>
            </span>
            <span class="menu-label">Expenses</span>
        </a>

        <!-- 6. REPORTS -->
        <div class="menu-category">Reports</div>
        <a href="<?= url('/reports') ?>" class="menu-item <?= $currentRoute === 'reports' ? 'active' : '' ?>" title="GST, Financial & Tax Reports">
            <span class="menu-icon-box">
                <i class="fa-solid fa-chart-column"></i>
            </span>
            <span class="menu-label">Financial & GST</span>
            <span class="sidebar-badge-pill">GSTR</span>
        </a>

        <!-- 7. ADMINISTRATION -->
        <div class="menu-category">Administration</div>
        <a href="<?= url('/users') ?>" class="menu-item <?= $currentRoute === 'users' ? 'active' : '' ?>" title="User Access & Permissions">
            <span class="menu-icon-box">
                <i class="fa-solid fa-user-shield"></i>
            </span>
            <span class="menu-label">Users & Roles</span>
        </a>
        <a href="<?= url('/audit-logs') ?>" class="menu-item <?= $currentRoute === 'audit_logs' || $currentRoute === 'audit-logs' ? 'active' : '' ?>" title="System Audit Logs">
            <span class="menu-icon-box">
                <i class="fa-solid fa-shield-halved"></i>
            </span>
            <span class="menu-label">Audit Trail</span>
        </a>

        <!-- 8. SETTINGS -->
        <div class="menu-category">Settings</div>
        <a href="<?= url('/select-company') ?>" class="menu-item <?= $currentRoute === 'select_company' || $currentRoute === 'select-company' ? 'active' : '' ?>" title="Switch / Manage Companies">
            <span class="menu-icon-box">
                <i class="fa-solid fa-building-circle-arrow-right"></i>
            </span>
            <span class="menu-label">Workspaces</span>
        </a>
        <a href="<?= url('/settings') ?>" class="menu-item <?= $currentRoute === 'settings' ? 'active' : '' ?>" title="Company & System Settings">
            <span class="menu-icon-box">
                <i class="fa-solid fa-sliders"></i>
            </span>
            <span class="menu-label">Settings</span>
        </a>
    </div>

    <!-- Sidebar Footer / Active Workspace Pill -->
    <div class="sidebar-footer">
        <a href="<?= url('/select-company') ?>" class="sidebar-workspace-pill" title="Active Workspace">
            <div class="workspace-dot"></div>
            <div class="workspace-meta">
                <div class="workspace-name"><?= htmlspecialchars(substr($companyName, 0, 20)) ?></div>
                <div class="workspace-sub">Online &bull; ID #<?= $companyId ?></div>
            </div>
            <i class="fa-solid fa-up-right-and-down-left-from-center workspace-arrow"></i>
        </a>
    </div>
</aside>
