<?php
/**
 * WTSBill ERP - Reusable Dropdown Component
 *
 * Parameters:
 * - $id (string): Unique dropdown ID
 * - $triggerText (string): Text on trigger button
 * - $triggerIcon (string|null): Icon on trigger button
 * - $triggerClass (string): Class for trigger button
 * - $align (string): 'left' | 'right'
 * - $items (array|null): List of menu items
 * - $content (string|null): Custom dropdown menu HTML
 */

$id = $id ?? 'dropdown_' . uniqid();
$triggerText = $triggerText ?? 'Options';
$triggerIcon = $triggerIcon ?? null;
$triggerClass = $triggerClass ?? 'btn btn-outline btn-sm';
$align = $align ?? 'left';
$items = $items ?? [];
$content = $content ?? $slot ?? null;
?>
<div class="dropdown-wrapper" style="position: relative; display: inline-block;">
    <button type="button" 
            class="<?= htmlspecialchars($triggerClass) ?>" 
            id="<?= htmlspecialchars($id) ?>_btn" 
            onclick="toggleNavDropdown('<?= htmlspecialchars($id) ?>_menu')" 
            aria-expanded="false">
        <?php if (!empty($triggerIcon)): ?>
            <i class="<?= htmlspecialchars($triggerIcon) ?>"></i>
        <?php endif; ?>
        <span><?= htmlspecialchars($triggerText) ?></span>
        <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px; opacity: 0.7;"></i>
    </button>

    <div class="nav-dropdown-menu <?= $align === 'right' ? 'right-aligned' : '' ?>" 
         id="<?= htmlspecialchars($id) ?>_menu" 
         style="min-width: 180px;">
        <?php if (!empty($content)): ?>
            <?= $content ?>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php if (!empty($item['divider'])): ?>
                    <div class="dropdown-divider"></div>
                <?php elseif (!empty($item['header'])): ?>
                    <div class="dropdown-header"><?= htmlspecialchars($item['header']) ?></div>
                <?php else: 
                    $itemUrl = $item['url'] ?? '#';
                    $itemText = $item['label'] ?? $item['text'] ?? '';
                    $itemIcon = $item['icon'] ?? null;
                    $itemClass = $item['class'] ?? '';
                    $itemOnclick = $item['onclick'] ?? null;
                ?>
                    <a href="<?= htmlspecialchars(url($itemUrl)) ?>" 
                       class="dropdown-item <?= htmlspecialchars($itemClass) ?>" 
                       <?= $itemOnclick ? 'onclick="' . htmlspecialchars($itemOnclick) . '"' : '' ?>>
                        <?php if (!empty($itemIcon)): ?>
                            <i class="<?= htmlspecialchars($itemIcon) ?>"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($itemText) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
