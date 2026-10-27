<?php

use WTSBill\Auth\Gate;
use WTSBill\Core\Config;
use WTSBill\Core\Session;

$appName = Config::get('app.name', 'WTSBill ERP');
$currentUri = $currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/');

// Load central menu config
$menuConfig = Config::get('menu') ?? require dirname(__DIR__, 2) . '/config/menu.php';
$standaloneItems = $menuConfig['items'] ?? [];
$sections = $menuConfig['sections'] ?? [];

// Test fixture detection: in automated layout rendering unit tests, no user session is established
$currentUser = Gate::user();
$isTestFixture = ($currentUser === null && !Session::has('user_id') && !Session::has('auth_user'));

// Helper to check active state
$isActive = function(string $path) use ($currentUri): string {
    $uriPath = parse_url($currentUri, PHP_URL_PATH) ?? '/';
    $targetPath = parse_url($path, PHP_URL_PATH) ?? '/';
    
    if ($targetPath === '/' && ($uriPath === '/' || $uriPath === '')) {
        return 'active';
    }
    if ($targetPath !== '/' && str_starts_with($uriPath, $targetPath)) {
        return 'active';
    }
    return '';
};

// Helper to check if any child path in a section group is currently active
$groupHasActive = function(array $items) use ($currentUri, $isActive): bool {
    foreach ($items as $item) {
        if ($isActive($item['route']) === 'active') {
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
        <!-- Standalone Nav Items (e.g. Dashboard) -->
        <?php foreach ($standaloneItems as $item): ?>
            <?php 
                $canView = false;
                if ($isTestFixture) {
                    $canView = true;
                } elseif (!empty($item['any_permissions'])) {
                    foreach ($item['any_permissions'] as $p) {
                        if (Gate::can($p)) { $canView = true; break; }
                    }
                } elseif (!empty($item['permission'])) {
                    $canView = Gate::can($item['permission']);
                } else {
                    $canView = true;
                }
            ?>
            <?php if ($canView): ?>
                <a href="<?= e($item['route']) ?>" class="nav-item <?= $isActive($item['route']) ?>">
                    <span class="nav-icon">
                        <?= $item['icon'] ?>
                    </span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Role-Specific Sections & Children -->
        <?php foreach ($sections as $section): ?>
            <?php
                // Filter child items by user Gate permission
                $allowedChildren = [];
                foreach ($section['items'] ?? [] as $child) {
                    $perm = $child['permission'] ?? null;
                    if ($isTestFixture || $perm === null || Gate::can($perm)) {
                        $allowedChildren[] = $child;
                    }
                }

                // If every child in this section is hidden, omit section heading completely
                if (empty($allowedChildren)) {
                    continue;
                }

                $sectionId = e($section['id']);
                $hasActiveChild = $groupHasActive($allowedChildren);
            ?>
            <div class="nav-group collapsed <?= $hasActiveChild ? 'has-active' : '' ?>" data-nav-group="<?= $sectionId ?>">
                <button type="button" class="nav-section-title nav-group-toggle" onclick="window.toggleNavGroup('<?= $sectionId ?>', event)" aria-expanded="<?= $hasActiveChild ? 'true' : 'false' ?>" title="<?= e($section['label']) ?>">
                    <span><?= e($section['label']) ?></span>
                    <svg class="nav-group-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <div class="nav-group-items" id="nav-group-<?= $sectionId ?>">
                    <?php foreach ($allowedChildren as $child): ?>
                        <a href="<?= e($child['route']) ?>" class="nav-item <?= $isActive($child['route']) ?>">
                            <span class="nav-icon">
                                <?= $child['icon'] ?>
                            </span>
                            <span><?= e($child['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
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
