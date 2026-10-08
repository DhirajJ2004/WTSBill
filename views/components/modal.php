<?php
/**
 * WTSBill ERP - Reusable Modal Component
 *
 * Parameters:
 * - $id (string): Unique modal HTML ID
 * - $title (string): Modal title
 * - $icon (string|null): Header icon
 * - $size (string): 'sm'|'md'|'lg'|'xl'
 * - $content (string|null): Modal body HTML
 * - $footer (string|null): Modal footer buttons HTML
 * - $formAction (string|null): Form action URL if modal is a form
 * - $formMethod (string): 'POST'|'GET'
 */

$id = $id ?? 'modal_' . uniqid();
$title = $title ?? 'Modal Window';
$icon = $icon ?? null;
$size = $size ?? 'md';
$content = $content ?? $slot ?? $body ?? '';
$footer = $footer ?? null;
$formAction = $formAction ?? null;
$formMethod = $formMethod ?? 'POST';

$dialogClass = 'modal-dialog';
if ($size && $size !== 'md') {
    $dialogClass .= ' modal-' . $size;
}
?>
<div class="modal-backdrop" id="<?= htmlspecialchars($id) ?>" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($id) ?>_title">
    <div class="<?= $dialogClass ?>">
        <?php if (!empty($formAction)): ?>
            <form action="<?= htmlspecialchars(url($formAction)) ?>" method="<?= htmlspecialchars($formMethod) ?>" class="modal-form">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
        <?php endif; ?>

        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title-group">
                    <?php if (!empty($icon)): ?>
                        <div class="modal-icon-box">
                            <i class="<?= htmlspecialchars($icon) ?>"></i>
                        </div>
                    <?php endif; ?>
                    <h3 class="modal-title" id="<?= htmlspecialchars($id) ?>_title"><?= htmlspecialchars($title) ?></h3>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('<?= htmlspecialchars($id) ?>')" aria-label="Close modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <?= $content ?>
            </div>

            <?php if (!empty($footer)): ?>
                <div class="modal-footer">
                    <?= $footer ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($formAction)): ?>
            </form>
        <?php endif; ?>
    </div>
</div>
