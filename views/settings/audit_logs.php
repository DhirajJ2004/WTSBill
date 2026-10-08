<?php
$pageTitle = 'Activity & Audit Logs - WTSBill ERP';
$pageHeader = 'System Security & Activity Trail';
$currentRoute = 'audit-logs';

require_once __DIR__ . '/../db_helper.php';
$auditLogs = get_audit_logs();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-list-check"></i> Audit Logs & Activity History</div>
                <button class="btn btn-outline btn-sm" onclick="location.reload()"><i class="fa-solid fa-arrows-rotate"></i> Refresh Logs</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>User Name</th>
                            <th>Action Event</th>
                            <th>Module</th>
                            <th>Description / Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><strong>#LOG-<?= sprintf('%04d', $log['id']) ?></strong></td>
                                <td><?= htmlspecialchars($log['timestamp']) ?></td>
                                <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td><?= htmlspecialchars($log['module']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
