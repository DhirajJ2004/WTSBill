<?php
/**
 * WTSBill ERP - Reusable Card Component
 *
 * Parameters:
 * - $title (string|null): Card header title
 * - $subtitle (string|null): Header subtitle
 * - $icon (string|null): Header icon class
 * - $badge (string|null): Header status badge text
 * - $badgeVariant (string): 'primary'|'success'|'danger'|'warning'|'info'|'secondary'
 * - $actions (string|null): Action buttons in header
 * - $content (string|null): Card body content
 * - $footer (string|null): Card footer content
 * - $class (string|null): Custom CSS class for card wrapper
 * - $id (string|null): HTML ID
 * - $noPadding (bool): Remove body padding if true (useful for full-width tables)
 */

$title = $title ?? null;
$subtitle = $subtitle ?? null;
$icon = $icon ?? null;
$badge = $badge ?? null;
$badgeVariant = $badgeVariant ?? 'primary';
$actions = $actions ?? null;
$content = $content ?? $slot ?? $body ?? '';
$footer = $footer ?? null;
$customClass = $class ?? '';
$id = $id ?? null;
$noPadding = !empty($noPadding);

$showHeader = !empty($title) || !empty($actions) || !empty($badge);
?>
<div class="card <?= $customClass ?>" <?= $id ? 'id="' . htmlspecialchars($id) . '"' : '' ?>>
    <?php if ($showHeader): ?>
        <div class="card-header">
            <div class="card-header-left">
                <?php if (!empty($icon)): ?>
                    <div class="card-icon-box">
                        <i class="<?= htmlspecialchars($icon) ?>"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="card-title-row">
                        <?php if (!empty($title)): ?>
                            <h3 class="card-title"><?= htmlspecialchars($title) ?></h3>
                        <?php endif; ?>
                        <?php if (!empty($badge)): ?>
                            <span class="badge badge-<?= htmlspecialchars($badgeVariant) ?>"><?= htmlspecialchars($badge) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($subtitle)): ?>
                        <div class="card-subtitle"><?= htmlspecialchars($subtitle) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($actions)): ?>
                <div class="card-header-actions">
                    <?= $actions ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card-body <?= $noPadding ? 'p-0' : '' ?>">
        <?= $content ?>
    </div>

    <?php if (!empty($footer)): ?>
        <div class="card-footer">
            <?= $footer ?>
        </div>
    <?php endif; ?>
</div>
