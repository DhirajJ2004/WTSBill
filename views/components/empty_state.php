<?php
/**
 * WTSBill ERP - Reusable Empty State Component
 *
 * Parameters:
 * - $icon (string|null): FontAwesome icon class
 * - $title (string): Main empty title
 * - $message (string|null): Helpful descriptive subtitle
 * - $actionText (string|null): Call-to-action button label
 * - $actionUrl (string|null): Action target URL
 * - $actionIcon (string|null): CTA icon
 * - $actionOnclick (string|null): CTA click handler
 * - $class (string|null): Extra CSS class
 */

$icon = $icon ?? 'fa-solid fa-folder-open';
$title = $title ?? 'No Data Available';
$message = $message ?? 'There are currently no records found for this section.';
$actionText = $actionText ?? null;
$actionUrl = $actionUrl ?? null;
$actionIcon = $actionIcon ?? 'fa-solid fa-plus';
$actionOnclick = $actionOnclick ?? null;
$customClass = $class ?? '';
?>
<div class="empty-state-wrapper <?= htmlspecialchars($customClass) ?>">
    <div class="empty-state-icon-box">
        <i class="<?= htmlspecialchars($icon) ?>"></i>
    </div>
    <h3 class="empty-state-title"><?= htmlspecialchars($title) ?></h3>
    <?php if (!empty($message)): ?>
        <p class="empty-state-message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($actionText)): ?>
        <div class="empty-state-action">
            <?php if (!empty($actionUrl)): ?>
                <a href="<?= htmlspecialchars(url($actionUrl)) ?>" class="btn btn-primary btn-sm">
                    <?php if (!empty($actionIcon)): ?>
                        <i class="<?= htmlspecialchars($actionIcon) ?>"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($actionText) ?></span>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary btn-sm" <?= $actionOnclick ? 'onclick="' . htmlspecialchars($actionOnclick) . '"' : '' ?>>
                    <?php if (!empty($actionIcon)): ?>
                        <i class="<?= htmlspecialchars($actionIcon) ?>"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($actionText) ?></span>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
