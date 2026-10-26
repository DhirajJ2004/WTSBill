<?php

use WTSBill\Core\Session;
use WTSBill\Repositories\BranchRepository;

$userName = Session::get('user_name', 'Administrator');
$userEmail = Session::get('user_email', 'admin@wtsbill.in');
$userRole = Session::get('user_role', 'ADMIN');
$userId = (int)(Session::get('user_id') ?? 1);
$companyId = Session::getCompanyId() ?? 1;
$activeBranchId = Session::getBranchId() ?? 1;

$branchRepo = new BranchRepository();
$authorizedBranches = $branchRepo->findUserAuthorizedBranches($userId, $companyId, $userRole);

// Resolve active branch
$activeBranch = null;
foreach ($authorizedBranches as $b) {
    if ((int)$b['id'] === $activeBranchId) {
        $activeBranch = $b;
        break;
    }
}
if (!$activeBranch && !empty($authorizedBranches)) {
    $activeBranch = $authorizedBranches[0];
    $activeBranchId = (int)$activeBranch['id'];
}
$activeBranchName = $activeBranch['name'] ?? Session::getBranchName() ?? 'Main Branch';
$activeBranchCode = $activeBranch['branch_code'] ?? $activeBranch['code'] ?? 'MAIN';

// Initials calculation
$nameParts = explode(' ', trim($userName));
$initials = strtoupper(substr($nameParts[0] ?? 'A', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
if (strlen($initials) === 1) {
    $initials = substr(strtoupper($userName), 0, 2);
}
?>
<header class="erp-header">
    <div class="header-left">
        <button type="button" id="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Toggle navigation" onclick="window.toggleSidebar(event)" title="Toggle Sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Active Tenant Context (accessible to automation/DOM without visual noise) -->
        <span class="tenant-meta" data-company-id="<?= e((string)$companyId) ?>" data-branch-id="<?= e((string)$activeBranchId) ?>" style="display: none;" aria-hidden="true">Company #<?= e((string)$companyId) ?> &bull; Branch #<?= e((string)$activeBranchId) ?></span>

        <!-- Branch Selector Dropdown or Single Branch Badge -->
        <?php if (count($authorizedBranches) > 1): ?>
            <div class="branch-selector-dropdown" style="position: relative; display: inline-block;">
                <button type="button" id="branch-switch-btn" class="branch-btn" 
                        aria-haspopup="true" aria-expanded="false" title="Switch Operating Branch"
                        onclick="if(window.WTSBill && WTSBill.Dropdown){ WTSBill.Dropdown.toggle('branch-switch-menu'); } else { var m = document.getElementById('branch-switch-menu'); if(m){ m.classList.toggle('show'); } } event.stopPropagation();"
                        style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.35rem 0.75rem; color: var(--text-main); cursor: pointer; text-align: left; height: 38px;">
                    <span class="branch-icon" style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: var(--primary-soft); color: var(--primary); flex-shrink: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </span>
                    <div class="branch-info" style="display: flex; flex-direction: column; line-height: 1.2;">
                        <span class="branch-label" style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Operating Branch</span>
                        <span class="branch-name" id="header-branch-name" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($activeBranchName) ?></span>
                    </div>
                    <svg class="branch-caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-muted); margin-left: 0.25rem;">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <div id="branch-switch-menu" class="dropdown-menu branch-dropdown-menu" style="position: absolute; top: calc(100% + 6px); left: 0; min-width: 280px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(15,23,42,0.1), 0 8px 10px -6px rgba(15,23,42,0.06); z-index: 9999;">
                    <div class="branch-menu-header" style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); background: var(--border-light);">
                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">Switch Branch</div>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">Select operating branch context</div>
                    </div>
                    <div class="branch-menu-list" style="padding: 0.35rem; max-height: 280px; overflow-y: auto;">
                        <?php foreach ($authorizedBranches as $branch): ?>
                            <?php $isCurrent = ((int)$branch['id'] === (int)$activeBranchId); ?>
                            <form action="/branches/switch" method="POST" style="margin: 0; padding: 0; width: 100%;">
                                <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
                                <input type="hidden" name="branch_id" value="<?= (int)$branch['id'] ?>">
                                <button type="submit" 
                                        class="branch-item <?= $isCurrent ? 'active' : '' ?>" 
                                        data-branch-id="<?= (int)$branch['id'] ?>"
                                        style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-radius: 6px; border: none; background: <?= $isCurrent ? 'var(--primary-soft)' : 'transparent' ?>; color: var(--text-main); cursor: pointer; text-align: left; transition: background 0.15s ease; margin-bottom: 2px;"
                                        onmouseover="if(!this.classList.contains('active')) this.style.background='var(--border-light)';"
                                        onmouseout="if(!this.classList.contains('active')) this.style.background='transparent';">
                                    <div class="branch-item-left" style="display: flex; align-items: center; gap: 0.65rem;">
                                        <span class="branch-dot <?= $isCurrent ? 'active' : '' ?>" style="width: 8px; height: 8px; border-radius: 50%; background: <?= $isCurrent ? 'var(--primary)' : 'var(--text-muted)' ?>;"></span>
                                        <div>
                                            <div class="branch-item-name" style="font-size: 0.85rem; font-weight: 500; color: <?= $isCurrent ? 'var(--primary)' : 'var(--text-main)' ?>;"><?= e($branch['name']) ?></div>
                                            <div class="branch-item-code" style="font-size: 0.72rem; color: var(--text-muted);">
                                                <span><?= e($branch['branch_code'] ?? $branch['code'] ?? '') ?></span>
                                                <?php if (!empty($branch['city'])): ?>
                                                    &bull; <span><?= e($branch['city']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($isCurrent): ?>
                                        <span class="badge badge-success" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; background: var(--success); color: #ffffff; border-radius: 4px; font-weight: 600;">ACTIVE</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="branch-static-badge" title="Active Branch Context" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.4rem 0.75rem; color: var(--text-main); font-size: 0.85rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span style="font-weight: 600; color: var(--text-main);"><?= e($activeBranchName) ?></span>
                <span class="badge badge-secondary" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; margin-left: 0.25rem;">Active</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="header-right" style="display: flex; align-items: center; gap: 0.75rem;">
        <!-- Light / Dark Theme Switcher -->
        <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" title="Toggle Light / Dark Mode" onclick="window.toggleTheme(event)">
            <svg id="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg id="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <span id="theme-toggle-label" style="font-size: 0.75rem;">Theme</span>
        </button>

        <div class="user-dropdown">
            <button type="button" id="user-menu-btn" class="user-btn" aria-haspopup="true" aria-expanded="false">
                <div class="user-avatar"><?= e($initials) ?></div>
                <div class="user-info">
                    <span class="user-name"><?= e($userName) ?></span>
                    <span class="user-role"><?= e($userRole) ?></span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div id="user-menu-dropdown" class="dropdown-menu">
                <div style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);"><?= e($userName) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($userEmail) ?></div>
                </div>
                <?php if (in_array(strtoupper($userRole), ['ADMIN', 'SUPERADMIN', 'OWNER'])): ?>
                    <a href="/branches" class="dropdown-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Branches & Locations
                    </a>
                <?php endif; ?>
                <a href="/settings" class="dropdown-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                <form action="/logout" method="POST" style="margin: 0;">
                    <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
                    <button type="submit" class="dropdown-item" style="color: var(--color-danger);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
// Enterprise Self-Contained Toggle Controller (Synchronous & resilient against asset cache)
window.toggleSidebar = function(e) {
    if (e) {
        if (e._sidebarHandled) return;
        e._sidebarHandled = true;
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    var now = Date.now();
    if (window._lastSidebarToggle && (now - window._lastSidebarToggle) < 200) {
        return;
    }
    window._lastSidebarToggle = now;

    var isMobile = window.innerWidth <= 768;
    var sidebar = document.getElementById('erp-sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    if (isMobile) {
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    } else {
        document.body.classList.toggle('sidebar-collapsed');
        try {
            var isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('wtsbill_sidebar_collapsed', isCollapsed ? '1' : '0');
        } catch(err) {}
    }
};

window.closeSidebar = function() {
    var sidebar = document.getElementById('erp-sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
};

window.toggleTheme = function(e) {
    if (e) {
        if (e._themeHandled) return;
        e._themeHandled = true;
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    var now = Date.now();
    if (window._lastThemeToggle && (now - window._lastThemeToggle) < 200) {
        return;
    }
    window._lastThemeToggle = now;

    var html = document.documentElement;
    var current = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', next);
    try {
        localStorage.setItem('wtsbill_theme', next);
    } catch(err) {}

    window.updateThemeUI(next);

    if (window.Toast && window.Toast.show) {
        window.Toast.show('Switched to ' + (next === 'dark' ? 'Dark' : 'Light') + ' theme', 'info', 1800);
    }
};

window.updateThemeUI = function(theme) {
    if (!theme) {
        try {
            theme = localStorage.getItem('wtsbill_theme') || document.documentElement.getAttribute('data-theme') || 'light';
        } catch(e) {
            theme = document.documentElement.getAttribute('data-theme') || 'light';
        }
    }
    document.documentElement.setAttribute('data-theme', theme);

    var sun = document.getElementById('theme-icon-sun');
    var moon = document.getElementById('theme-icon-moon');
    var label = document.getElementById('theme-toggle-label');
    var btn = document.getElementById('theme-toggle-btn');

    if (theme === 'dark') {
        if (sun) sun.style.display = 'inline-block';
        if (moon) moon.style.display = 'none';
        if (label) label.textContent = 'Dark';
        if (btn) btn.setAttribute('title', 'Switch to Light Mode');
    } else {
        if (sun) sun.style.display = 'none';
        if (moon) moon.style.display = 'inline-block';
        if (label) label.textContent = 'Light';
        if (btn) btn.setAttribute('title', 'Switch to Dark Mode');
    }
};

// Immediate execution to set correct icon and label on initial paint
window.updateThemeUI();
</script>
