<?php
/**
 * WTSBill ERP - Reusable Confirmation Dialog Component
 *
 * Parameters:
 * - $id (string): Modal unique identifier
 * - $title (string): Dialog title (e.g. 'Delete Invoice')
 * - $message (string): Confirmation message
 * - $confirmText (string): Label on confirm button
 * - $cancelText (string): Label on cancel button
 * - $confirmAction (string|null): Form action URL
 * - $confirmOnclick (string|null): JS confirm callback
 * - $variant (string): 'danger' | 'warning' | 'primary'
 * - $icon (string|null): Header warning icon
 */

$id = $id ?? 'confirmDialog_' . uniqid();
$title = $title ?? 'Are you sure?';
$message = $message ?? 'This action cannot be undone.';
$confirmText = $confirmText ?? 'Confirm';
$cancelText = $cancelText ?? 'Cancel';
$confirmAction = $confirmAction ?? null;
$confirmOnclick = $confirmOnclick ?? null;
$variant = $variant ?? 'danger';
$icon = $icon ?? ($variant === 'danger' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-question');
?>
<div class="modal-backdrop confirm-modal-backdrop" id="<?= htmlspecialchars($id) ?>" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($id) ?>_title">
    <div class="modal-dialog modal-sm">
        <?php if (!empty($confirmAction)): ?>
            <form action="<?= htmlspecialchars(url($confirmAction)) ?>" method="POST" class="confirm-modal-form" id="<?= htmlspecialchars($id) ?>_form">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
        <?php endif; ?>

        <div class="modal-content">
            <div class="confirm-modal-body">
                <div class="confirm-icon-circle bg-<?= htmlspecialchars($variant) ?>-subtle text-<?= htmlspecialchars($variant) ?>">
                    <i class="<?= htmlspecialchars($icon) ?>"></i>
                </div>
                
                <h4 class="confirm-dialog-title" id="<?= htmlspecialchars($id) ?>_title"><?= htmlspecialchars($title) ?></h4>
                <p class="confirm-dialog-message"><?= htmlspecialchars($message) ?></p>
            </div>

            <div class="modal-footer confirm-modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('<?= htmlspecialchars($id) ?>')">
                    <?= htmlspecialchars($cancelText) ?>
                </button>

                <?php if (!empty($confirmAction)): ?>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($variant) ?> btn-sm">
                        <?= htmlspecialchars($confirmText) ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-<?= htmlspecialchars($variant) ?> btn-sm" <?= $confirmOnclick ? 'onclick="' . htmlspecialchars($confirmOnclick) . '"' : '' ?>>
                        <?= htmlspecialchars($confirmText) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($confirmAction)): ?>
            </form>
        <?php endif; ?>
    </div>
</div>
