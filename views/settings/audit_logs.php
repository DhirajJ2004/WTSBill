<?php
/**
 * WTSBill ERP — Security Audit Trail & System Activity View
 * Clean, executive-grade audit log displaying essential activity information:
 * User, Action, Affected Record, Description, and Timestamp.
 *
 * @var array $auditLogs
 * @var array $auditSummary
 * @var bool $isAdmin
 * @var string $title
 * @var string $currentUri
 */

use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$auditSummary = $auditSummary ?? ['total' => 0, 'today' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #1E293B);">Security Audit Trail</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Real-time activity tracking: monitor users, modifications, transactions, and system events
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="/settings/audit-logs/export" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.85rem; border: 1px solid #7AAACE; border-radius: 6px; text-decoration: none; color: #1E293B; background: #FFFFFF;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Export CSV</span>
        </a>
    </div>
</div>

<!-- Settings Module Navigation Tabs -->
<div class="tabs-nav" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #7AAACE; padding-bottom: 0.5rem; flex-wrap: wrap;">
    <a href="/settings" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Company Profile &amp; Branding</span>
    </a>
    <a href="/settings/audit-logs" class="btn btn-primary active" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; background: #355872; color: #FFFFFF; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        <span>Audit Trail</span>
        <span class="badge" style="background: #EBF5FC; color: #1E293B; font-size: 0.7rem; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 700;"><?= (int)($auditSummary['total'] ?? 0) ?></span>
    </a>
    <a href="/settings/communication" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        <span>Communication</span>
    </a>
    <a href="/settings/backups" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Backup &amp; Disaster Recovery</span>
    </a>
    <a href="/users" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #7AAACE; background: #FFFFFF; color: #1E293B;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Users &amp; Roles</span>
    </a>
</div>

<!-- 4 Clean Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1.15rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.74rem; text-transform: uppercase; color: #2A485E; font-weight: 600; letter-spacing: 0.04em;">Total Logged Events</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #1E293B; margin-top: 0.2rem;" id="audit-kpi-total"><?= (int)($auditSummary['total'] ?? 0) ?></div>
        <div style="font-size: 0.74rem; color: #4B5563; margin-top: 0.2rem;">Complete activity history</div>
    </div>
    <div class="card" style="padding: 1.15rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.74rem; text-transform: uppercase; color: #2A485E; font-weight: 600; letter-spacing: 0.04em;">Today's Activity</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #355872; margin-top: 0.2rem;" id="audit-kpi-today"><?= (int)($auditSummary['today'] ?? 0) ?></div>
        <div style="font-size: 0.74rem; color: #4B5563; margin-top: 0.2rem;">Events recorded today</div>
    </div>
    <div class="card" style="padding: 1.15rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.74rem; text-transform: uppercase; color: #2A485E; font-weight: 600; letter-spacing: 0.04em;">Modifications</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #2A485E; margin-top: 0.2rem;" id="audit-kpi-updates"><?= (int)(($auditSummary['updates'] ?? 0) + ($auditSummary['creates'] ?? 0)) ?></div>
        <div style="font-size: 0.74rem; color: #4B5563; margin-top: 0.2rem;">Created and updated records</div>
    </div>
    <div class="card" style="padding: 1.15rem; background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px;">
        <div style="font-size: 0.74rem; text-transform: uppercase; color: #2A485E; font-weight: 600; letter-spacing: 0.04em;">Deletions</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #DC2626; margin-top: 0.2rem;" id="audit-kpi-deletes"><?= (int)($auditSummary['deletes'] ?? 0) ?></div>
        <div style="font-size: 0.74rem; color: #4B5563; margin-top: 0.2rem;">Cancelled or deleted entries</div>
    </div>
</div>

<!-- Main Audit Log Table Card -->
<div class="card" style="background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
    <!-- Filter Bar -->
    <div style="padding: 0.85rem 1.25rem; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
        <div style="flex: 1; min-width: 220px;">
            <input type="text" id="audit-filter-search" class="form-control" placeholder="Search user, action, description, or record..." style="font-size: 0.85rem; padding: 0.45rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; width: 100%;" oninput="debounceAuditSearch()">
        </div>
        <div style="width: 140px;">
            <select id="audit-filter-action" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; width: 100%;" onchange="loadAuditLogs(1)">
                <option value="">All Actions</option>
                <option value="CREATE">CREATE</option>
                <option value="UPDATE">UPDATE</option>
                <option value="DELETE">DELETE</option>
                <option value="LOGIN">LOGIN</option>
                <option value="EXPORT">EXPORT</option>
            </select>
        </div>
        <div style="width: 150px;">
            <select id="audit-filter-entity" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; width: 100%;" onchange="loadAuditLogs(1)">
                <option value="">All Modules</option>
                <option value="INVOICE">Invoices</option>
                <option value="CUSTOMER">Customers</option>
                <option value="SUPPLIER">Suppliers</option>
                <option value="PRODUCT">Products</option>
                <option value="PAYMENT">Payments</option>
                <option value="DEBIT_NOTE">Debit Notes</option>
                <option value="CREDIT_NOTE">Credit Notes</option>
                <option value="USER">Users</option>
                <option value="COMPANY">Company Profile</option>
            </select>
        </div>
    </div>

    <!-- Essential Audit Log Table (Clean, readable columns: Timestamp, User, Action, Affected Record, Description) -->
    <div class="table-container" style="overflow-x: auto;">
        <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: #EBF5FC; border-bottom: 1.5px solid #7AAACE; color: #1E293B;">
                    <th style="padding: 0.75rem 1rem; width: 170px; text-align: left;">Date &amp; Time</th>
                    <th style="padding: 0.75rem 1rem; width: 180px; text-align: left;">User</th>
                    <th style="padding: 0.75rem 1rem; width: 110px; text-align: center;">Action</th>
                    <th style="padding: 0.75rem 1rem; width: 190px; text-align: left;">Affected Record</th>
                    <th style="padding: 0.75rem 1rem; text-align: left;">Summary / Description</th>
                </tr>
            </thead>
            <tbody id="audit-table-body">
                <?php if (!empty($auditLogs)): ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <?php
                            $act = strtoupper((string)($log['action'] ?? ''));
                            $badgeBg = match($act) {
                                'CREATE' => '#ECFDF5; color: #059669; border: 1px solid #A7F3D0;',
                                'UPDATE', 'SETTINGS_CHANGE' => '#EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;',
                                'DELETE' => '#FEF2F2; color: #DC2626; border: 1px solid #FECACA;',
                                'LOGIN' => '#F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF;',
                                default => '#F1F5F9; color: #475569; border: 1px solid #CBD5E1;'
                            };
                            $entityDisplay = e($log['entity_type'] ?? 'RECORD');
                            if (!empty($log['entity_id'])) {
                                $entityDisplay .= ' #' . (int)$log['entity_id'];
                            }
                            if (!empty($log['entity_label'])) {
                                $entityDisplay .= ' (' . e($log['entity_label']) . ')';
                            }
                        ?>
                        <tr id="audit-row-<?= (int)$log['id'] ?>" style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.7rem 1rem; white-space: nowrap; color: #4B5563; font-size: 0.82rem;">
                                <?= e(date('d M Y, h:i A', strtotime($log['created_at']))) ?>
                            </td>
                            <td style="padding: 0.7rem 1rem;">
                                <strong style="color: #1E293B;"><?= e($log['user_name'] ?: 'Administrator') ?></strong>
                                <?php if (!empty($log['user_role'])): ?>
                                    <div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase;"><?= e($log['user_role']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.7rem 1rem; text-align: center;">
                                <span class="badge" style="padding: 0.2rem 0.55rem; font-size: 0.74rem; font-weight: 700; border-radius: 4px; background: <?= $badgeBg ?>">
                                    <?= e($act) ?>
                                </span>
                            </td>
                            <td style="padding: 0.7rem 1rem;">
                                <code style="font-size: 0.8rem; background: #F1F5F9; padding: 0.15rem 0.35rem; border-radius: 4px; color: #2A485E;">
                                    <?= $entityDisplay ?>
                                </code>
                            </td>
                            <td style="padding: 0.7rem 1rem; color: #1E293B; line-height: 1.4;">
                                <?= e($log['description']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-audit-row">
                        <td colspan="5" style="text-align: center; color: #64748B; padding: 2.5rem;">
                            No audit trail records found. Activity will be recorded here automatically.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Bar -->
    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; font-size: 0.8rem; color: #64748B; background: #F8FAFC; border-top: 1px solid #E2E8F0;" id="audit-pagination-bar">
        <span id="audit-page-info">Showing page 1</span>
        <div style="display: flex; gap: 0.4rem;">
            <button type="button" class="btn btn-sm btn-secondary" id="audit-prev-btn" onclick="prevAuditPage()" disabled style="padding: 0.3rem 0.7rem; border-radius: 4px; border: 1px solid #CBD5E1; background: #FFFFFF; cursor: pointer;">&larr; Previous</button>
            <button type="button" class="btn btn-sm btn-secondary" id="audit-next-btn" onclick="nextAuditPage()" style="padding: 0.3rem 0.7rem; border-radius: 4px; border: 1px solid #CBD5E1; background: #FFFFFF; cursor: pointer;">Next &rarr;</button>
        </div>
    </div>
</div>

<script>
var currentAuditPage = 1;
var totalAuditPages = 1;
var auditSearchDebounceTimer = null;

function debounceAuditSearch() {
    clearTimeout(auditSearchDebounceTimer);
    auditSearchDebounceTimer = setTimeout(function() {
        loadAuditLogs(1);
    }, 350);
}

function loadAuditLogs(page) {
    page = page || 1;
    var search = document.getElementById('audit-filter-search').value.trim();
    var action = document.getElementById('audit-filter-action').value;
    var entity = document.getElementById('audit-filter-entity').value;

    var url = '/api/settings/audit-logs?page=' + page + '&limit=20';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (action) url += '&action=' + encodeURIComponent(action);
    if (entity) url += '&entity=' + encodeURIComponent(entity);

    fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (!data.success) return;
        currentAuditPage = data.page || page;
        totalAuditPages = data.total_pages || 1;

        renderAuditRows(data.rows || []);
        document.getElementById('audit-page-info').textContent = 'Showing page ' + currentAuditPage + ' of ' + totalAuditPages + ' (' + (data.total || 0) + ' events)';
        document.getElementById('audit-prev-btn').disabled = (currentAuditPage <= 1);
        document.getElementById('audit-next-btn').disabled = (currentAuditPage >= totalAuditPages);
    })
    .catch(function(err) {
        console.error('Failed to load audit logs:', err);
    });
}

function renderAuditRows(rows) {
    var tbody = document.getElementById('audit-table-body');
    tbody.innerHTML = '';

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #64748B; padding: 2.5rem;">No matching audit activity found.</td></tr>';
        return;
    }

    rows.forEach(function(r) {
        var act = (r.action || '').toUpperCase();
        var badgeBg = '#F1F5F9; color: #475569; border: 1px solid #CBD5E1;';
        if (act === 'CREATE') badgeBg = '#ECFDF5; color: #059669; border: 1px solid #A7F3D0;';
        else if (act === 'UPDATE' || act === 'SETTINGS_CHANGE') badgeBg = '#EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;';
        else if (act === 'DELETE') badgeBg = '#FEF2F2; color: #DC2626; border: 1px solid #FECACA;';
        else if (act === 'LOGIN') badgeBg = '#F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF;';

        var dt = r.created_at ? new Date(r.created_at).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
        var entityStr = r.entity_type || 'RECORD';
        if (r.entity_id) entityStr += ' #' + r.entity_id;
        if (r.entity_label) entityStr += ' (' + r.entity_label + ')';

        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #E2E8F0';
        tr.innerHTML = `
            <td style="padding: 0.7rem 1rem; white-space: nowrap; color: #4B5563; font-size: 0.82rem;">${dt}</td>
            <td style="padding: 0.7rem 1rem;">
                <strong style="color: #1E293B;">${escapeHtml(r.user_name || 'Administrator')}</strong>
                ${r.user_role ? `<div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase;">${escapeHtml(r.user_role)}</div>` : ''}
            </td>
            <td style="padding: 0.7rem 1rem; text-align: center;">
                <span class="badge" style="padding: 0.2rem 0.55rem; font-size: 0.74rem; font-weight: 700; border-radius: 4px; background: ${badgeBg}">
                    ${escapeHtml(act)}
                </span>
            </td>
            <td style="padding: 0.7rem 1rem;">
                <code style="font-size: 0.8rem; background: #F1F5F9; padding: 0.15rem 0.35rem; border-radius: 4px; color: #2A485E;">
                    ${escapeHtml(entityStr)}
                </code>
            </td>
            <td style="padding: 0.7rem 1rem; color: #1E293B; line-height: 1.4;">
                ${escapeHtml(r.description || '—')}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function prevAuditPage() {
    if (currentAuditPage > 1) loadAuditLogs(currentAuditPage - 1);
}

function nextAuditPage() {
    if (currentAuditPage < totalAuditPages) loadAuditLogs(currentAuditPage + 1);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

