<?php
require_once __DIR__ . '/../db_helper.php';
$navCompany = get_current_company();
$navCompanyId = get_current_company_id();
$navFY = get_current_financial_year();
$navUser = get_current_user_info();
$navCompanies = get_all_companies();
$navFYList = get_financial_years();
$navBranch = get_current_branch();
$navBranchId = get_current_branch_id();
$navBranches = get_current_branches();
$navRecentLogs = get_audit_logs();
$recentNotices = array_slice($navRecentLogs, 0, 5);
?>
<header class="navbar" id="appNavbar">
    <!-- Navbar Left: Toggle & Breadcrumb Context -->
    <div class="navbar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Navigation">
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
                <a href="<?= url('/accounting?tab=journal-vouchers') ?>" class="dropdown-item"><i class="fa-solid fa-book-journal-whills text-violet"></i> Journal Voucher</a>
            </div>
        </div>

        <!-- Notification Bell & Feed -->
        <div class="nav-pill-dropdown">
            <button class="nav-icon-btn" type="button" onclick="toggleNavDropdown('notificationsMenu')" title="Notifications & Activity Feed">
                <i class="fa-regular fa-bell"></i>
                <?php if (!empty($recentNotices)): ?>
                    <span class="nav-notification-dot"></span>
                <?php endif; ?>
            </button>
            <div class="nav-dropdown-menu right-aligned" id="notificationsMenu" style="min-width: 320px; max-width: 380px;">
                <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>System Activity</span>
                    <a href="<?= url('/audit-logs') ?>" style="font-size: 11px; color: var(--primary); text-decoration: none; font-weight: 600;">View All &rarr;</a>
                </div>
                <?php if (empty($recentNotices)): ?>
                    <div class="dropdown-item" style="color: var(--text-muted); font-size: 13px; padding: 14px; text-align: center;">No new activity.</div>
                <?php else: ?>
                    <?php foreach ($recentNotices as $n): ?>
                        <a href="<?= url('/audit-logs') ?>" class="dropdown-item" style="display: block; padding: 8px 12px; border-bottom: 1px solid var(--border-subtle);">
                            <div style="font-weight: 600; font-size: 12.5px; color: var(--text);"><?= htmlspecialchars($n['module'] ?? 'Activity') ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); white-space: normal; line-height: 1.35;"><?= htmlspecialchars(substr($n['details'] ?? '', 0, 75)) ?>...</div>
                            <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px;"><?= htmlspecialchars($n['timestamp'] ?? '') ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Keyboard Shortcuts Help -->
        <button class="nav-icon-btn" type="button" onclick="openModal('helpShortcutsModal')" title="Keyboard Shortcuts">
            <i class="fa-regular fa-circle-question"></i>
        </button>

        <!-- Theme Toggle (Light / Dark) -->
        <button class="nav-icon-btn theme-toggle-btn" type="button" onclick="toggleTheme()" title="Toggle Theme (Alt+T)">
            <i class="fa-solid fa-moon"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="nav-pill-dropdown">
            <button class="nav-user-profile" type="button" onclick="toggleNavDropdown('profileMenu')">
                <div class="nav-avatar"><?= htmlspecialchars($navUser['avatar'] ?? 'AD') ?></div>
                <div class="nav-user-info">
                    <div class="nav-user-name"><?= htmlspecialchars($navUser['name'] ?? 'Anil Desai') ?></div>
                    <div class="nav-user-role"><?= htmlspecialchars($navUser['role'] ?? 'ADMIN') ?></div>
                </div>
                <i class="fa-solid fa-chevron-down nav-user-arrow"></i>
            </button>
            <div class="nav-dropdown-menu right-aligned" id="profileMenu" style="min-width: 220px;">
                <div class="dropdown-header">Signed in as <strong><?= htmlspecialchars($navUser['email'] ?? 'admin@wtsbill.in') ?></strong></div>
                <a href="<?= url('/select-company') ?>" class="dropdown-item"><i class="fa-solid fa-building-user"></i> Switch Company</a>
                <a href="<?= url('/users') ?>" class="dropdown-item"><i class="fa-solid fa-users"></i> Users &amp; Permissions</a>
                <a href="<?= url('/settings') ?>" class="dropdown-item"><i class="fa-solid fa-gear"></i> System Settings</a>
                <a href="<?= url('/audit-logs') ?>" class="dropdown-item"><i class="fa-solid fa-list-check"></i> Audit Trail</a>
                <div class="dropdown-divider"></div>
                <a href="<?= url('/logout') ?>" class="dropdown-item" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
            </div>
        </div>
    </div>
</header>

<!-- Global Help & Keyboard Shortcuts Modal -->
<div class="modal-overlay" id="helpShortcutsModal">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-circle-question text-primary"></i> WTSBill &bull; Keyboard Shortcuts</div>
            <button class="modal-close" onclick="closeModal('helpShortcutsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                <div class="shortcut-item">
                    <span>Global Search</span>
                    <kbd>Ctrl + K</kbd>
                </div>
                <div class="shortcut-item">
                    <span>New Invoice</span>
                    <kbd>Alt + I</kbd>
                </div>
                <div class="shortcut-item">
                    <span>New Customer</span>
                    <kbd>Alt + C</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Toggle Theme</span>
                    <kbd>Alt + T</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Close Modal / Drawer</span>
                    <kbd>Esc</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Toggle Sidebar</span>
                    <kbd>Ctrl + B</kbd>
                </div>
            </div>

            <div class="dropdown-subheader" style="margin-bottom: 8px;">Quick Links</div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <a href="<?= url('/dashboard') ?>" class="dropdown-item"><i class="fa-solid fa-chart-pie text-primary"></i> Dashboard</a>
                <a href="<?= url('/accounting') ?>" class="dropdown-item"><i class="fa-solid fa-scale-balanced text-primary"></i> Banking &amp; General Ledger</a>
                <a href="<?= url('/reports') ?>" class="dropdown-item"><i class="fa-solid fa-file-invoice-dollar text-primary"></i> GST &amp; Compliance</a>
                <a href="<?= url('/settings') ?>" class="dropdown-item"><i class="fa-solid fa-sliders text-primary"></i> Company Settings</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('helpShortcutsModal')">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let searchInput = document.getElementById('globalQuickSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                let q = encodeURIComponent(searchInput.value.trim());
                if (q) {
                    window.location.href = '<?= url('/invoices') ?>?search=' + q;
                }
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            let s = document.getElementById('globalQuickSearch');
            if (s) { s.focus(); s.select(); }
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
            e.preventDefault();
            window.toggleSidebar();
        }
        if (e.altKey && e.key.toLowerCase() === 'i') {
            window.location.href = '<?= url('/create-invoice') ?>';
        }
        if (e.altKey && e.key.toLowerCase() === 'c') {
            window.location.href = '<?= url('/parties') ?>';
        }
        if (e.altKey && e.key.toLowerCase() === 't') {
            window.toggleTheme();
        }
    });
});
</script>
