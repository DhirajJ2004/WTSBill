<?php
$title = $title ?? 'Notifications & System Alerts';
$notifications = $notifications ?? [];
$unreadCount = $unreadCount ?? 0;
$status = $status ?? 'all';
$category = $category ?? 'ALL';
?>

<div class="content-wrapper" style="padding: 24px; max-width: 1000px; margin: 0 auto;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/dashboard" style="color: #64748b; text-decoration: none;">Dashboard</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Notifications</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span>Notification Center</span>
                <?php if ($unreadCount > 0): ?>
                    <span id="unreadBadge" style="font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; background: #fee2e2; color: #b91c1c;">
                        <?= (int)$unreadCount ?> Unread
                    </span>
                <?php else: ?>
                    <span id="unreadBadge" style="font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; background: #dcfce7; color: #15803d;">
                        All Caught Up
                    </span>
                <?php endif; ?>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Automated operational notifications, stock replenishment warnings, and debtor payment reminders.</p>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="button" id="markAllBtn" onclick="markAllAsRead()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; cursor: pointer; transition: all 0.2s;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Mark All as Read
            </button>
        </div>
    </div>

    <!-- Category Filter Pills -->
    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
        <a href="/notifications?category=ALL&status=<?= urlencode($status) ?>" style="padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $category === 'ALL' ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
            All Categories
        </a>
        <a href="/notifications?category=INVENTORY&status=<?= urlencode($status) ?>" style="padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $category === 'INVENTORY' ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
            Inventory Alerts
        </a>
        <a href="/notifications?category=FINANCIAL&status=<?= urlencode($status) ?>" style="padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $category === 'FINANCIAL' ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
            Financial &amp; Due
        </a>
        <a href="/notifications?category=SYSTEM&status=<?= urlencode($status) ?>" style="padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $category === 'SYSTEM' ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
            System Events
        </a>
    </div>

    <!-- Notifications List -->
    <div style="display: flex; flex-direction: column; gap: 12px;" id="notificationsList">
        <?php if (empty($notifications)): ?>
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 48px; text-align: center; color: #94a3b8;">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 12px auto; opacity: 0.5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <div style="font-size: 16px; font-weight: 600; color: #475569;">No notifications right now</div>
                <div style="font-size: 13px; margin-top: 4px;">System alerts and updates will appear here automatically.</div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): 
                $isUnread = ((int)$n['is_read'] === 0);
            ?>
                <div id="notif-<?= (int)$n['id'] ?>" style="background: <?= $isUnread ? '#fff' : '#f8fafc' ?>; border: 1px solid <?= $isUnread ? '#cbd5e1' : '#e2e8f0' ?>; border-left: 4px solid <?= $n['priority'] === 'URGENT' ? '#dc2626' : ($n['category'] === 'INVENTORY' ? '#ea580c' : '#2563eb') ?>; border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; box-shadow: <?= $isUnread ? '0 1px 3px rgba(0,0,0,0.04)' : 'none' ?>;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; <?= $n['priority'] === 'URGENT' ? 'background: #fee2e2; color: #b91c1c;' : 'background: #e2e8f0; color: #475569;' ?>">
                                <?= htmlspecialchars($n['category']) ?>
                            </span>
                            <span style="font-size: 12px; color: #94a3b8;">
                                <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                            </span>
                            <?php if ($isUnread): ?>
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: #2563eb;"></span>
                            <?php endif; ?>
                        </div>
                        <h3 style="font-size: 15px; font-weight: 600; color: #1e293b; margin: 0 0 4px 0;">
                            <?= htmlspecialchars($n['title']) ?>
                        </h3>
                        <p style="font-size: 13px; color: #475569; margin: 0;">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0;">
                        <?php if (!empty($n['action_url'])): ?>
                            <a href="<?= htmlspecialchars($n['action_url']) ?>" style="padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #2563eb; color: #fff; text-decoration: none;">
                                View Details
                            </a>
                        <?php endif; ?>
                        <?php if ($isUnread): ?>
                            <button type="button" onclick="markOneAsRead(<?= (int)$n['id'] ?>)" style="padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; background: #fff; border: 1px solid #cbd5e1; color: #475569; cursor: pointer;">
                                Mark Read
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function markOneAsRead(id) {
    fetch('/api/notifications/' + id + '/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const card = document.getElementById('notif-' + id);
            if (card) {
                card.style.background = '#f8fafc';
                card.style.borderColor = '#e2e8f0';
                const btn = card.querySelector('button');
                if (btn) btn.remove();
            }
        }
    })
    .catch(() => {});
}

function markAllAsRead() {
    fetch('/api/notifications/mark-all-read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.location.reload();
        }
    })
    .catch(() => {});
}
</script>

