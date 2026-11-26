<?php
/**
 * WTSBill ERP — Enterprise Backup Management & Disaster Recovery View
 * Dedicated view for creating snapshots, downloading archives, and reviewing backup health.
 *
 * @var array $backups
 * @var array|null $lastBackup
 * @var array $nextBackup
 * @var array $cleanupLogs
 * @var bool $isAdmin
 * @var string $title
 * @var string $currentUri
 */

use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$backups = $backups ?? [];
$nextBackup = $nextBackup ?? [];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #1E293B);">Backup &amp; Disaster Recovery</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Automated 7-day database archiving, SHA-256 verified ZIP backups, and disaster restore tools
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <form method="POST" action="/settings/backups/create" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerText='Creating Snapshot...';">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.95rem; font-size: 0.85rem; border: none; border-radius: 6px; color: #FFFFFF; background: #355872; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <span>+ Create Backup Now</span>
            </button>
        </form>
    </div>
</div>

<!-- Settings Module Navigation Tabs -->
<div class="tabs-nav" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #7AAACE; padding-bottom: 0.5rem; flex-wrap: wrap;">
    <a href="/settings" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Company Profile &amp; Branding</span>
    </a>
    <a href="/settings/audit-logs" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        <span>Audit Trail</span>
    </a>
    <a href="/settings/communication" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        <span>Communication</span>
    </a>
    <a href="/settings/backups" class="btn btn-primary active" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; background: #355872; color: #FFFFFF; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Backup &amp; Disaster Recovery</span>
    </a>
    <a href="/users" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Users &amp; Roles</span>
    </a>
</div>

<!-- 3 KPI Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1.25rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: #2A485E; margin-bottom: 0.35rem;">Last Successful Backup</div>
        <div style="font-size: 1.2rem; font-weight: 700; color: #1E293B;" id="backup-kpi-last">
            <?= !empty($lastBackup) ? date('d M Y, h:i A', strtotime((string)$lastBackup['created_at'])) : 'Never Backed Up' ?>
        </div>
        <div style="font-size: 0.75rem; color: #4B5563; margin-top: 0.25rem;" id="backup-kpi-last-file">
            <?= !empty($lastBackup) ? e($lastBackup['file_name']) . ' (' . number_format($lastBackup['file_size'] / 1024, 1) . ' KB)' : 'Automatic 7-day schedule' ?>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: #2A485E; margin-bottom: 0.35rem;">Next Scheduled Run</div>
        <div style="font-size: 1.2rem; font-weight: 700; color: #355872;">
            <?= e($nextBackup['human'] ?? 'Every 7 Days') ?>
        </div>
        <div style="font-size: 0.75rem; color: #4B5563; margin-top: 0.25rem;">
            Managed automatically via System Task Scheduler
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: #2A485E; margin-bottom: 0.35rem;">Backup Archive Integrity</div>
        <div style="font-size: 1.2rem; font-weight: 700; color: #059669; display: flex; align-items: center; gap: 0.4rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span>SHA-256 Verified</span>
        </div>
        <div style="font-size: 0.75rem; color: #4B5563; margin-top: 0.25rem;">All archives checksum protected</div>
    </div>
</div>

<!-- Backup Archives Table -->
<div class="card" style="background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
    <div class="card-header" style="padding: 0.85rem 1.25rem; border-bottom: 1px solid #E2E8F0; background: #F8FAFC;">
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #1E293B;">Verified Backup Archives</h3>
    </div>
    <div class="table-container" style="overflow-x: auto;">
        <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: #EBF5FC; border-bottom: 1.5px solid #7AAACE; color: #1E293B;">
                    <th style="padding: 0.75rem 1rem; text-align: left;">File Name</th>
                    <th style="padding: 0.75rem 1rem; width: 140px; text-align: left;">Type</th>
                    <th style="padding: 0.75rem 1rem; width: 100px; text-align: right;">Size</th>
                    <th style="padding: 0.75rem 1rem; width: 170px; text-align: left;">Created At</th>
                    <th style="padding: 0.75rem 1rem; width: 120px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($backups)): ?>
                    <?php foreach ($backups as $b): ?>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem 1rem;">
                                <strong><?= e($b['file_name']) ?></strong>
                                <div style="font-size: 0.72rem; color: #64748B; font-family: monospace;">SHA-256: <?= substr((string)$b['checksum'], 0, 16) ?>...</div>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span class="badge" style="background: #E2E8F0; color: #334155; font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px;"><?= e($b['backup_type']) ?></span>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace;">
                                <?= number_format($b['file_size'] / 1024, 1) ?> KB
                            </td>
                            <td style="padding: 0.75rem 1rem; color: #4B5563; font-size: 0.82rem;">
                                <?= date('d M Y, h:i A', strtotime((string)$b['created_at'])) ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <a href="/settings/backups/<?= (int)$b['id'] ?>/download" class="btn btn-sm btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border: 1px solid #7AAACE; border-radius: 4px; text-decoration: none; color: #1E293B; background: #EBF5FC;">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748B; padding: 2.5rem;">
                            No backup archives found yet. Create a backup to protect your system data.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

