<?php
/**
 * WTSBill ERP - Reusable Toast Notification Component
 *
 * Parameters:
 * - $message (string|null): Message text
 * - $type (string): 'success'|'danger'|'warning'|'info'
 * - $title (string|null): Title
 * - $autoShow (bool): Show immediately on page load
 */

$message = $message ?? null;
$type = $type ?? 'success';
$title = $title ?? ucfirst($type);
$autoShow = $autoShow ?? (!empty($message));

$iconMap = [
    'success' => 'fa-solid fa-circle-check',
    'danger' => 'fa-solid fa-circle-xmark',
    'warning' => 'fa-solid fa-triangle-exclamation',
    'info' => 'fa-solid fa-circle-info',
];
$icon = $iconMap[$type] ?? $iconMap['info'];
?>
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true">
    <?php if ($autoShow && !empty($message)): ?>
        <div class="toast toast-<?= htmlspecialchars($type) ?> toast-show" role="alert">
            <div class="toast-icon">
                <i class="<?= htmlspecialchars($icon) ?>"></i>
            </div>
            <div class="toast-content">
                <?php if (!empty($title)): ?>
                    <div class="toast-title"><?= htmlspecialchars($title) ?></div>
                <?php endif; ?>
                <div class="toast-message"><?= htmlspecialchars($message) ?></div>
            </div>
            <button type="button" class="toast-close-btn" onclick="this.closest('.toast').remove()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
if (typeof window.showToast !== 'function') {
    window.showToast = function(message, type = 'success', title = '') {
        const container = document.getElementById('toastContainer') || (function() {
            const el = document.createElement('div');
            el.id = 'toastContainer';
            el.className = 'toast-container';
            document.body.appendChild(el);
            return el;
        })();

        const icons = {
            success: 'fa-solid fa-circle-check',
            danger: 'fa-solid fa-circle-xmark',
            warning: 'fa-solid fa-triangle-exclamation',
            info: 'fa-solid fa-circle-info'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type} toast-show`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="toast-icon"><i class="${icons[type] || icons.info}"></i></div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${escapeHtml(message)}</div>
            </div>
            <button type="button" class="toast-close-btn" onclick="this.closest('.toast').remove()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('toast-show');
            setTimeout(() => toast.remove(), 250);
        }, 4000);
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
</script>
