<?php
/**
 * WTSBill ERP - Reusable Topbar / Navbar Component
 *
 * Parameters:
 * - $navCompany (array|null): active company
 * - $navBranch (array|null): active branch
 * - $navFY (string|null): active financial year
 * - $navUser (array|null): authenticated user
 */

if (!isset($navCompany) && function_exists('get_current_company')) {
    $navCompany = get_current_company();
}
$navCompanyId = $navCompany['id'] ?? (function_exists('get_current_company_id') ? get_current_company_id() : 1);
$navFY = $navFY ?? (function_exists('get_current_financial_year') ? get_current_financial_year() : '2026-2027');
$navUser = $navUser ?? (function_exists('get_current_user_info') ? get_current_user_info() : ($_SESSION['user'] ?? []));
$navCompanies = function_exists('get_all_companies') ? get_all_companies() : [];
$navFYList = function_exists('get_financial_years') ? get_financial_years() : [];
$navBranch = $navBranch ?? (function_exists('get_current_branch') ? get_current_branch() : null);
$navBranchId = $navBranch['id'] ?? (function_exists('get_current_branch_id') ? get_current_branch_id() : 1);
$navBranches = function_exists('get_current_branches') ? get_current_branches() : [];
$navRecentLogs = function_exists('get_audit_logs') ? get_audit_logs() : [];
$recentNotices = array_slice($navRecentLogs, 0, 5);
?>
<header class="navbar" id="appNavbar">
    <!-- Navbar Left: Toggle & Breadcrumb Context -->
    <div class="navbar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Navigation" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="navbar-breadcrumb">
            <span class="breadcrumb-company" title="<?= htmlspecialchars($navCompany['name'] ?? 'Wis Technosavvy') ?>"><?= htmlspecialchars($navCompany['name'] ?? 'Wis Technosavvy') ?></span>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current" title="<?= htmlspecialchars($navBranch['name'] ?? 'Main Branch') ?>"><?= htmlspecialchars($navBranch['name'] ?? 'Main Branch') ?></span>
            <span class="breadcrumb-badge"><?= htmlspecialchars($navFY) ?></span>
        </div>
    </div>

    <!-- Navbar Center: Global Quick Search -->
    <div class="navbar-center">
        <div class="global-search-wrapper">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="global-search-input" id="globalQuickSearch" placeholder="Search invoices, products, customers... (Ctrl+K)" autocomplete="off" aria-label="Global Search">
            <kbd class="search-kbd-badge">Ctrl K</kbd>
        </div>
    </div>
    
    <!-- Navbar Right: Actions, Context Selectors, Notifications & User -->
    <div class="navbar-right">
        <!-- Quick Context Selectors (Company, Branch, FY) -->
        <div class="nav-pill-dropdown">
            <button class="nav-pill-btn" type="button" id="workspaceDropdownBtn" onclick="toggleNavDropdown('workspaceContext')">
                <i class="fa-solid fa-layer-group nav-pill-icon"></i>
                <span class="nav-pill-text"><?= htmlspecialchars($navBranch['name'] ?? 'Branch') ?></span>
                <i class="fa-solid fa-chevron-down nav-pill-arrow"></i>
            </button>
            <div class="nav-dropdown-menu" id="workspaceContext" style="min-width: 280px;">
                <div class="dropdown-header">Active Workspace Context</div>
                
                <div class="dropdown-subheader">Companies (<?= count($navCompanies) ?>)</div>
                <?php foreach ($navCompanies as $c): 
                    $isCur = ($c['id'] == $navCompanyId);
                ?>
                    <a href="<?= url('/switch-company?company_id=' . $c['id']) ?>" class="dropdown-item <?= $isCur ? 'active' : '' ?>">
                        <i class="fa-solid <?= $isCur ? 'fa-check text-primary' : 'fa-building' ?>"></i>
                        <span style="font-weight: 500;"><?= htmlspecialchars($c['name']) ?></span>
                    </a>
                <?php endforeach; ?>

                <div class="dropdown-divider"></div>
                <div class="dropdown-subheader">Branches (<?= count($navBranches) ?>)</div>
                <?php foreach ($navBranches as $b): 
                    $isBranchActive = ($b['id'] == $navBranchId);
                ?>
                    <a href="<?= url('/switch-company?company_id=' . $navCompanyId . '&branch_id=' . $b['id']) ?>" class="dropdown-item <?= $isBranchActive ? 'active' : '' ?>">
                        <i class="fa-solid <?= $isBranchActive ? 'fa-check text-primary' : 'fa-code-branch' ?>"></i>
                        <span><?= htmlspecialchars($b['name']) ?></span>
                    </a>
                <?php endforeach; ?>

                <div class="dropdown-divider"></div>
                <div class="dropdown-subheader">Financial Year</div>
                <?php foreach ($navFYList as $f): 
                    $isFyActive = ($f['code'] === $navFY);
                ?>
                    <a href="<?= url('/switch-company?company_id=' . $navCompanyId . '&fy=' . urlencode($f['code'])) ?>" class="dropdown-item <?= $isFyActive ? 'active' : '' ?>">
                        <i class="fa-solid <?= $isFyActive ? 'fa-check text-primary' : 'fa-calendar' ?>"></i>
                        <span><?= htmlspecialchars($f['title']) ?> (<?= htmlspecialchars($f['status']) ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- + Create Action Button with Menu -->
        <div class="create-action-dropdown">
            <button class="btn btn-primary btn-sm" type="button" onclick="toggleNavDropdown('createMenu')" style="gap: 6px;">
                <i class="fa-solid fa-plus"></i>
                <span>Create</span>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; opacity: 0.8;"></i>
            </button>
            <div class="nav-dropdown-menu right-aligned" id="createMenu" style="min-width: 220px;">
                <div class="dropdown-header">Quick Creation</div>
                <a href="<?= url('/create-invoice') ?>" class="dropdown-item"><i class="fa-solid fa-file-invoice text-emerald"></i> Sales Invoice</a>
                <a href="<?= url('/create-quotation') ?>" class="dropdown-item"><i class="fa-solid fa-file-signature text-blue"></i> Quotation</a>
                <a href="<?= url('/parties') ?>" class="dropdown-item"><i class="fa-solid fa-user-plus text-cyan"></i> Party / Customer</a>
                <a href="<?= url('/inventory') ?>" class="dropdown-item"><i class="fa-solid fa-box text-purple"></i> Product / Item</a>
                <a href="<?= url('/payments') ?>" class="dropdown-item"><i class="fa-regular fa-credit-card text-teal"></i> Payment Receipt</a>
                <a href="<?= url('/expenses') ?>" class="dropdown-item"><i class="fa-solid fa-receipt text-coral"></i> Expense Entry</a>
                <a href="<?= url('/purchases') ?>" class="dropdown-item"><i class="fa-solid fa-bag-shopping text-amber"></i> Purchase Bill</a>
            </div>
        </div>

        <!-- Light / Dark Theme Switcher -->
        <button class="navbar-icon-btn" onclick="toggleTheme()" title="Toggle Theme (Dark / Light Mode)" aria-label="Toggle Theme">
            <i class="fa-solid fa-sun" id="themeSunIcon" style="display: none;"></i>
            <i class="fa-solid fa-moon" id="themeMoonIcon"></i>
        </button>

        <!-- Notifications Bell & Dropdown -->
        <div class="nav-item-dropdown">
            <button class="navbar-icon-btn has-badge" type="button" onclick="toggleNavDropdown('notificationsMenu')" title="System Notifications" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <?php if (count($recentNotices) > 0): ?>
                    <span class="notification-indicator"></span>
                <?php endif; ?>
            </button>
            <div class="nav-dropdown-menu right-aligned" id="notificationsMenu" style="min-width: 320px;">
                <div class="dropdown-header-row">
                    <span class="dropdown-header-title">System Activity</span>
                    <a href="<?= url('/audit-logs') ?>" class="dropdown-header-link">View All</a>
                </div>
                <div class="notifications-scroll-area">
                    <?php if (empty($recentNotices)): ?>
                        <div class="empty-dropdown-msg">
                            <i class="fa-regular fa-bell-slash"></i>
                            <p>No recent activity logs.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotices as $notice): ?>
                            <div class="notification-item">
                                <div class="notif-icon-circle <?= strpos($notice['action'] ?? '', 'DELETE') !== false ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                                    <i class="fa-solid <?= strpos($notice['action'] ?? '', 'DELETE') !== false ? 'fa-trash' : (strpos($notice['action'] ?? '', 'CREATE') !== false ? 'fa-plus' : 'fa-pen-to-square') ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-text"><strong><?= htmlspecialchars($notice['user_name'] ?? 'System') ?></strong> <?= htmlspecialchars($notice['details'] ?? $notice['action'] ?? 'performed an action') ?></div>
                                    <div class="notif-time"><?= date('H:i, d M', strtotime($notice['created_at'] ?? 'now')) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Profile Pill & Dropdown -->
        <div class="user-profile-dropdown">
            <button class="user-profile-btn" type="button" onclick="toggleNavDropdown('userProfileMenu')" aria-label="User Profile">
                <div class="user-avatar-circle">
                    <?= htmlspecialchars($navUser['avatar'] ?? strtoupper(substr($navUser['name'] ?? 'A', 0, 1))) ?>
                </div>
                <div class="user-info-text">
                    <span class="user-display-name"><?= htmlspecialchars($navUser['name'] ?? 'Admin User') ?></span>
                    <span class="user-display-role"><?= htmlspecialchars($navUser['role'] ?? 'ADMIN') ?></span>
                </div>
                <i class="fa-solid fa-chevron-down user-chevron"></i>
            </button>
            <div class="nav-dropdown-menu right-aligned" id="userProfileMenu" style="min-width: 220px;">
                <div class="user-menu-header">
                    <div class="user-menu-name"><?= htmlspecialchars($navUser['name'] ?? 'Admin User') ?></div>
                    <div class="user-menu-email"><?= htmlspecialchars($navUser['email'] ?? 'admin@wtsbill.in') ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= url('/settings') ?>" class="dropdown-item"><i class="fa-solid fa-user-gear text-muted"></i> Account Settings</a>
                <a href="<?= url('/users') ?>" class="dropdown-item"><i class="fa-solid fa-users-gear text-muted"></i> User Management</a>
                <a href="<?= url('/select-company') ?>" class="dropdown-item"><i class="fa-solid fa-building-circle-arrow-right text-muted"></i> Switch Company</a>
                <a href="<?= url('/audit-logs') ?>" class="dropdown-item"><i class="fa-solid fa-clock-rotate-left text-muted"></i> Audit Logs</a>
                <div class="dropdown-divider"></div>
                <a href="<?= url('/logout') ?>" class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket text-danger"></i> Sign Out</a>
            </div>
        </div>
    </div>
</header>
