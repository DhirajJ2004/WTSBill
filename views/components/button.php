<?php
/**
 * WTSBill ERP - Reusable Button Component
 *
 * Parameters:
 * - $text (string|null): Button label text
 * - $icon (string|null): FontAwesome icon class
 * - $iconPosition (string): 'left' | 'right'
 * - $variant (string): 'primary'|'secondary'|'outline'|'success'|'danger'|'warning'|'info'|'ghost'
 * - $size (string): 'xs'|'sm'|'md'|'lg'
 * - $type (string): 'button'|'submit'|'reset'|'link'
 * - $href (string|null): Target URL if type is link
 * - $id (string|null): HTML ID
 * - $class (string|null): Extra CSS classes
 * - $onclick (string|null): JS click handler
 * - $disabled (bool): Disabled state
 * - $attributes (array|string|null): Custom data/aria attributes
 */

$text = $text ?? '';
$icon = $icon ?? null;
$iconPosition = $iconPosition ?? 'left';
$variant = $variant ?? 'primary';
$size = $size ?? 'md';
$type = $type ?? ($href ? 'link' : 'button');
$href = $href ?? null;
$id = $id ?? null;
$customClass = $class ?? '';
$onclick = $onclick ?? null;
$disabled = !empty($disabled);

$btnClasses = ['btn'];
if ($variant === 'outline') {
    $btnClasses[] = 'btn-outline';
} elseif ($variant === 'ghost') {
    $btnClasses[] = 'btn-ghost';
} else {
    $btnClasses[] = 'btn-' . $variant;
}

if ($size && $size !== 'md') {
    $btnClasses[] = 'btn-' . $size;
}

if (!empty($customClass)) {
    $btnClasses[] = $customClass;
}

$classAttr = implode(' ', $btnClasses);
$idAttr = $id ? 'id="' . htmlspecialchars($id) . '"' : '';
$onclickAttr = $onclick ? 'onclick="' . htmlspecialchars($onclick) . '"' : '';
$disabledAttr = $disabled ? 'disabled' : '';

$attrStr = '';
if (is_array($attributes ?? null)) {
    foreach ($attributes as $k => $v) {
        $attrStr .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
    }
} elseif (is_string($attributes ?? null)) {
    $attrStr .= ' ' . $attributes;
}
?>
<?php if ($type === 'link' || !empty($href)): ?>
    <a href="<?= htmlspecialchars(url($href)) ?>" class="<?= $classAttr ?>" <?= $idAttr ?> <?= $onclickAttr ?> <?= $attrStr ?>>
        <?php if ($icon && $iconPosition === 'left'): ?>
            <i class="<?= htmlspecialchars($icon) ?>"></i>
        <?php endif; ?>
        <?php if (!empty($text)): ?>
            <span><?= htmlspecialchars($text) ?></span>
        <?php endif; ?>
        <?php if ($icon && $iconPosition === 'right'): ?>
            <i class="<?= htmlspecialchars($icon) ?>"></i>
        <?php endif; ?>
    </a>
<?php else: ?>
    <button type="<?= htmlspecialchars($type) ?>" class="<?= $classAttr ?>" <?= $idAttr ?> <?= $onclickAttr ?> <?= $disabledAttr ?> <?= $attrStr ?>>
        <?php if ($icon && $iconPosition === 'left'): ?>
            <i class="<?= htmlspecialchars($icon) ?>"></i>
        <?php endif; ?>
        <?php if (!empty($text)): ?>
            <span><?= htmlspecialchars($text) ?></span>
        <?php endif; ?>
        <?php if ($icon && $iconPosition === 'right'): ?>
            <i class="<?= htmlspecialchars($icon) ?>"></i>
        <?php endif; ?>
    </button>
<?php endif; ?>
