<?php
/**
 * WTSBill ERP — Enterprise System & Organization Settings View
 * Comprehensive settings dashboard for Tenant Organization Profile, GSTIN Master, and Operating Branches.
 */
use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$currentBranchId = (int)($activeBranchId ?? Session::getBranchId() ?? 1);
$isAdmin = $isAdmin ?? false;

// Format Company Data safely
$comp = $company ?? [];
$compName = $comp['legal_name'] ?? $comp['name'] ?? 'Wis Technosavvy Pvt Ltd';
$compTrade = $comp['name'] ?? 'Wis Technosavvy Pvt Ltd';
$compGstin = $comp['gstin'] ?? '27AADCW7577N1ZE';
$compPan = $comp['pan'] ?? 'AADCW7577N';
$compState = $comp['state'] ?? 'Maharashtra';
$compStateCode = $comp['state_code'] ?? '27';
$compCity = $comp['city'] ?? 'Pune';
$compPhone = $comp['phone'] ?? '020 47252364';
$compEmail = $comp['email'] ?? 'info@wtsindia.co.in';
$compWebsite = $comp['website'] ?? 'http://www.wtsindia.co.in';
$compMsme = $comp['msme_no'] ?? 'UDYAM-MH-26-0734598';
$compUpi = $comp['upi_id'] ?? '';
$compUpiName = $comp['upi_display_name'] ?? ($comp['legal_name'] ?? $comp['name'] ?? '');
$compPaymentInstructions = $comp['payment_instructions'] ?? 'Scan this QR code using any supported UPI application to pay the outstanding invoice balance.';
$compAddress = trim(($comp['address_line1'] ?? '') . ' ' . ($comp['address_line2'] ?? ''));
$compPincode = $comp['pincode'] ?? '411041';
$compCurrency = $comp['currency'] ?? 'INR';
$compFyStart = $comp['financial_year_start'] ?? '04-01';
$compTerms = $comp['terms_and_conditions'] ?? '';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Enterprise System Settings</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Configure organization profile, Indian GSTIN master, branch settings, and defaults</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-secondary" onclick="openEditCompanyModal()" style="display: flex; align-items: center; gap: 0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>Edit Organization Profile</span>
            </button>
            <button type="button" class="btn btn-primary" onclick="openCreateBranchModal()" style="display: flex; align-items: center; gap: 0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>+ Register Branch</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="tabs-nav" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; flex-wrap: wrap;">
    <button type="button" id="tab-btn-general" class="btn <?= ($activeTab ?? 'general') === 'general' ? 'btn-primary active' : 'btn-secondary' ?>" onclick="switchSettingsTab('general')" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Organization & Operating Branches</span>
    </button>
    <?php if ($isAdmin): ?>
        <button type="button" id="tab-btn-backups" class="btn <?= ($activeTab ?? 'general') === 'backups' ? 'btn-primary active' : 'btn-secondary' ?>" onclick="switchSettingsTab('backups')" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Backup & Disaster Recovery</span>
        </button>
        <button type="button" id="tab-btn-audit" class="btn <?= ($activeTab ?? 'general') === 'audit' ? 'btn-primary active' : 'btn-secondary' ?>" onclick="switchSettingsTab('audit')" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Audit Trail & System Logs</span>
            <span class="badge badge-primary" style="font-size: 0.7rem; padding: 0.15rem 0.4rem;" id="audit-badge-count"><?= (int)($auditSummary['total'] ?? 0) ?></span>
        </button>
    <?php endif; ?>
</div>

<div id="settings-sec-general" style="<?= ($activeTab ?? 'general') === 'general' ? '' : 'display: none;' ?>">
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- 1. Tenant Organization Profile Card -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: var(--primary-soft, rgba(37,99,235,0.1)); color: var(--primary, #2563eb);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </span>
                    <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 600;">Tenant Organization Profile</h3>
                </div>
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditCompanyModal()" title="Edit Organization Details" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Company Legal Name</label>
                    <div id="disp-company-legal-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-top: 0.15rem;"><?= e($compName) ?></div>
                    <?php if (!empty($compTrade) && $compTrade !== $compName): ?>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Trade Name: <?= e($compTrade) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">GSTIN</label>
                        <div id="disp-company-gstin" style="margin-top: 0.15rem;">
                            <?php if (!empty($compGstin)): ?>
                                <code style="font-size: 0.9rem; font-weight: 600;"><?= e($compGstin) ?></code>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">State: <?= e($compState) ?> (<?= e($compStateCode) ?>)</div>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">&mdash;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">PAN</label>
                        <div id="disp-company-pan" style="margin-top: 0.15rem;">
                            <?php if (!empty($compPan)): ?>
                                <code style="font-size: 0.9rem; font-weight: 600;"><?= e($compPan) ?></code>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">&mdash;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Financial Year</label>
                    <div id="disp-company-fy" style="font-size: 0.9rem; color: var(--text-primary); margin-top: 0.15rem;">01 April &mdash; 31 March (FY 2026-27)</div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Default Currency &amp; Timezone</label>
                    <div style="font-size: 0.9rem; color: var(--text-primary); margin-top: 0.15rem;"><?= e($compCurrency) ?> (₹) &bull; Asia/Kolkata (+05:30)</div>
                </div>

                <?php if (!empty($compAddress) || !empty($compCity)): ?>
                    <div style="margin-bottom: 1rem; border-top: 1px dashed var(--border-color); padding-top: 0.75rem;">
                        <label style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Registered Office</label>
                        <div style="font-size: 0.85rem; color: var(--text-primary); margin-top: 0.15rem;">
                            <?= e($compAddress) ?>
                            <?php if (!empty($compCity)): ?>
                                <br><?= e($compCity) ?>, <?= e($compState) ?> &ndash; <?= e($compPincode) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; border-top: 1px dashed var(--border-color); padding-top: 0.75rem; font-size: 0.82rem;">
                    <div>
                        <span style="color: var(--text-muted);">Phone:</span> <strong><?= e($compPhone ?: '—') ?></strong><br>
                        <span style="color: var(--text-muted);">Email:</span> <strong><?= e($compEmail ?: '—') ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted);">MSME:</span> <strong><?= e($compMsme ?: '—') ?></strong><br>
                        <span style="color: var(--text-muted);">UPI ID:</span> <strong style="color: <?= $compUpi ? 'var(--color-primary)' : 'var(--text-muted)' ?>;"><?= e($compUpi ?: 'Not Configured') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Active Operating Branches Card -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: var(--success-soft, rgba(16,185,129,0.1)); color: var(--success, #10b981);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </span>
                    <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 600;">Active Branches</h3>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="badge badge-primary"><?= count($branches) ?> Branches</span>
                    <?php if ($isAdmin): ?>
                        <a href="/branches" class="btn btn-sm btn-outline-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;" title="Manage all branches">
                            Manage All &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <?php if (empty($branches)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        No branches registered.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($branches as $branch): ?>
                            <?php 
                            $bId = (int)$branch['id'];
                            $isCurrent = ($bId === $currentBranchId);
                            $isActive = ((int)$branch['is_active'] === 1);
                            $isMain = ((int)$branch['is_main_branch'] === 1);
                            $badgeClass = $isMain ? 'badge-success' : 'badge-info';
                            $badgeText = $isMain ? 'MAIN' : 'REGIONAL';
                            ?>
                            <div class="branch-card-row" style="padding: 0.85rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 0.5rem; transition: border-color 0.15s ease;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= e($branch['name']) ?></strong>
                                        <span class="badge <?= $badgeClass ?>" style="font-size: 0.65rem;"><?= $badgeText ?></span>
                                        <?php if ($isCurrent): ?>
                                            <span class="badge badge-success" style="font-size: 0.62rem;">ACTIVE</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isAdmin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditBranchModal(<?= $bId ?>)" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" title="Edit branch details">
                                            Edit
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                                    <span>Code: <code><?= e($branch['branch_code'] ?? $branch['code'] ?? '—') ?></code></span>
                                    <span>&bull;</span>
                                    <span><?= e($branch['city'] ?? '—') ?><?= !empty($branch['address']) ? ', ' . e($branch['address']) : '' ?></span>
                                    <?php if (!empty($branch['gstin'])): ?>
                                        <span>&bull;</span>
                                        <span>GSTIN: <code><?= e($branch['gstin']) ?></code></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div class="card-footer" style="padding: 0.75rem 1.25rem; border-top: 1px solid var(--border-color); background: var(--bg-surface); text-align: right;">
                <button type="button" class="btn btn-sm btn-primary" onclick="openCreateBranchModal()" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Another Branch</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
</div><!-- /#settings-sec-general -->

<?php if ($isAdmin): ?>
<!-- ========================================== -->
<!-- 2. AUDIT TRAIL & SYSTEM LOGS SECTION       -->
<!-- ========================================== -->
<div id="settings-sec-audit" style="<?= ($activeTab ?? 'general') === 'audit' ? 'display: block;' : 'display: none;' ?> margin-bottom: 2rem;">
    <!-- 4 KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em;">Total Audit Events</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--primary, #2563eb); margin-top: 0.25rem;" id="audit-kpi-total"><?= (int)($auditSummary['total'] ?? 0) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Immutable activity log records</div>
        </div>
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em;">Today's Activity</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success, #10b981); margin-top: 0.25rem;" id="audit-kpi-today"><?= (int)($auditSummary['today'] ?? 0) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Actions logged since midnight</div>
        </div>
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em;">Modifications</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-warning, #f59e0b); margin-top: 0.25rem;" id="audit-kpi-updates"><?= (int)($auditSummary['updates'] + $auditSummary['creates']) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Records created & updated</div>
        </div>
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em;">Critical Deletions</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--danger, #ef4444); margin-top: 0.25rem;" id="audit-kpi-deletes"><?= (int)($auditSummary['deletes'] ?? 0) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Cancelled or soft-deleted items</div>
        </div>
    </div>

    <!-- Main Audit Log Table Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 600;">System Audit Trail</h3>
                <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);">Real-time immutable logging of all administrative and operator transactions</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="/settings/audit-logs/export" class="btn btn-sm btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Export CSV</span>
                </a>
                <button type="button" class="btn btn-sm btn-primary" onclick="loadAuditLogs(1)" style="display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div style="padding: 0.75rem 1.25rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" id="audit-filter-search" class="form-control" placeholder="Search description, user, IP..." style="font-size: 0.85rem; padding: 0.35rem 0.65rem;" oninput="debounceAuditSearch()">
            </div>
            <div style="width: 140px;">
                <select id="audit-filter-action" class="form-control" style="font-size: 0.85rem; padding: 0.35rem 0.5rem;" onchange="loadAuditLogs(1)">
                    <option value="">All Actions</option>
                    <option value="CREATE">CREATE</option>
                    <option value="UPDATE">UPDATE</option>
                    <option value="DELETE">DELETE</option>
                    <option value="LOGIN">LOGIN</option>
                    <option value="EXPORT">EXPORT</option>
                </select>
            </div>
            <div style="width: 150px;">
                <select id="audit-filter-entity" class="form-control" style="font-size: 0.85rem; padding: 0.35rem 0.5rem;" onchange="loadAuditLogs(1)">
                    <option value="">All Entities</option>
                    <option value="COMPANY">COMPANY</option>
                    <option value="BRANCH">BRANCH</option>
                    <option value="INVOICE">INVOICE</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                    <option value="USER">USER</option>
                    <option value="SETTINGS">SETTINGS</option>
                </select>
            </div>
        </div>

        <div class="table-container">
            <table class="table" style="font-size: 0.85rem; margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 60px;"># ID</th>
                        <th style="width: 160px;">Date & Time</th>
                        <th style="width: 180px;">User & IP</th>
                        <th style="width: 110px; text-align: center;">Action</th>
                        <th style="width: 130px;">Target Entity</th>
                        <th>Description</th>
                        <th style="width: 90px; text-align: center;">Details</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body">
                    <?php if (!empty($auditLogs)): ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <?php
                                $act = strtoupper((string)($log['action'] ?? ''));
                                $badgeClass = match($act) {
                                    'CREATE' => 'success',
                                    'UPDATE' => 'info',
                                    'DELETE' => 'danger',
                                    'LOGIN' => 'primary',
                                    default => 'secondary'
                                };
                            ?>
                            <tr id="audit-row-<?= (int)$log['id'] ?>">
                                <td><code>#<?= (int)$log['id'] ?></code></td>
                                <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.8rem;">
                                    <?= e(date('d M Y, H:i:s', strtotime($log['created_at']))) ?>
                                </td>
                                <td>
                                    <strong><?= e($log['user_name'] ?: 'Administrator') ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;"><?= e($log['ip_address'] ?? '127.0.0.1') ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $badgeClass ?>"><?= e($act) ?></span>
                                </td>
                                <td>
                                    <code><?= e($log['entity_type']) ?><?= !empty($log['entity_id']) ? ' #' . $log['entity_id'] : '' ?></code>
                                </td>
                                <td><?= e($log['description']) ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($log['before_data_json']) || !empty($log['after_data_json'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.75rem;" onclick="inspectAuditLog(<?= (int)$log['id'] ?>)">View</button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="no-audit-row">
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                No audit log records found. Any administrative changes will appear here automatically.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; font-size: 0.8rem; color: var(--text-muted);" id="audit-pagination-bar">
            <span id="audit-page-info">Showing page 1</span>
            <div style="display: flex; gap: 0.35rem;">
                <button type="button" class="btn btn-sm btn-secondary" id="audit-prev-btn" onclick="prevAuditPage()" disabled>&larr; Previous</button>
                <button type="button" class="btn btn-sm btn-secondary" id="audit-next-btn" onclick="nextAuditPage()">Next &rarr;</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. BACKUP & DISASTER RECOVERY SECTION     -->
<!-- ========================================== -->
<?php if ($isAdmin): ?>
<div id="settings-sec-backups" style="<?= ($activeTab ?? 'general') === 'backups' ? 'display: block;' : 'display: none;' ?> margin-bottom: 2rem;">
    <!-- 3 KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">Last Successful Backup</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary);" id="backup-kpi-last">
                <?= !empty($lastBackup) ? date('d M Y, h:i A', strtotime((string)$lastBackup['created_at'])) : 'Never Backed Up' ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;" id="backup-kpi-last-file">
                <?= !empty($lastBackup) ? e($lastBackup['file_name']) . ' (' . number_format($lastBackup['file_size'] / 1024, 1) . ' KB)' : 'Automatic 7-day schedule' ?>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">Next Scheduled Run</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: <?= !empty($nextBackup['is_overdue']) ? 'var(--danger, #DC2626)' : 'var(--primary, #2563EB)' ?>;">
                <?= e($nextBackup['human'] ?? 'Every 7 Days') ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                Managed via Scheduler / Windows Task Engine
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">Backup Archive Integrity</div>
                <div style="font-size: 1.15rem; font-weight: 700; color: var(--color-success, #059669); display: flex; align-items: center; gap: 0.4rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>SHA-256 Verified</span>
                </div>
            </div>
            <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-sm btn-primary" onclick="createBackupNow()" id="btn-create-backup" style="width: 100%; font-size: 0.8rem; padding: 0.45rem;">
                    + Create Backup Now
                </button>
            </div>
        </div>
    </div>

    <!-- Safe Audit Log Cleanup Action Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem; border-left: 4px solid var(--primary, #2563eb);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h4 style="margin: 0 0 0.25rem 0; font-size: 1rem; font-weight: 700;">Atomic Weekly Audit Log Retention & Cleanup</h4>
                <p style="margin: 0; font-size: 0.825rem; color: var(--text-muted);">
                    Creates a verified backup archive containing eligible logs first. Only after checksum verification succeeds, logs older than the retention period (default 7 days) are purged.
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.8rem; font-weight: 600; margin: 0;">Retention:</label>
                <select id="cleanup-retention-days" style="padding: 0.4rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.8rem;">
                    <option value="7" selected>7 Days (Recommended)</option>
                    <option value="14">14 Days</option>
                    <option value="30">30 Days</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="runAuditCleanup()" id="btn-cleanup-audit" style="font-size: 0.8rem; padding: 0.45rem 0.85rem;">
                    Run Safe Cleanup
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Records Table -->
    <div class="card">
        <div class="card-header" style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 600;">Verified Backup History</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= count($backups) ?> Archives Stored</span>
        </div>
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); text-align: left; background: var(--bg-surface);">
                        <th style="padding: 0.75rem 1rem;">Created At</th>
                        <th style="padding: 0.75rem 1rem;">Archive Name</th>
                        <th style="padding: 0.75rem 1rem;">Type</th>
                        <th style="padding: 0.75rem 1rem;">Size</th>
                        <th style="padding: 0.75rem 1rem;">SHA-256 Checksum</th>
                        <th style="padding: 0.75rem 1rem;">Status</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="backups-table-body">
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $b): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 1rem; white-space: nowrap;"><?= date('d M Y, h:i A', strtotime((string)$b['created_at'])) ?></td>
                                <td style="padding: 0.75rem 1rem; font-weight: 600;"><?= e($b['file_name']) ?></td>
                                <td style="padding: 0.75rem 1rem;">
                                    <span class="badge <?= $b['backup_type'] === 'SCHEDULED_WEEKLY' ? 'badge-primary' : ($b['backup_type'] === 'SAFETY_PRE_RESTORE' ? 'badge-warning' : 'badge-secondary') ?>" style="font-size: 0.7rem;">
                                        <?= e($b['backup_type']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem;"><?= number_format($b['file_size'] / 1024, 1) ?> KB</td>
                                <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.75rem; color: var(--text-muted);" title="<?= e($b['checksum']) ?>">
                                    <?= substr((string)$b['checksum'], 0, 14) ?>...
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <span class="badge badge-success" style="font-size: 0.7rem;">Verified</span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                                    <a href="/settings/backups/<?= (int)$b['id'] ?>/download" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; text-decoration: none;">Download</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="openRestoreModal(<?= (int)$b['id'] ?>, '<?= e($b['file_name']) ?>')" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; margin-left: 0.25rem;">Restore</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openDeleteModal(<?= (int)$b['id'] ?>, '<?= e($b['file_name']) ?>')" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; margin-left: 0.25rem; color: #dc2626;" title="Delete backup archive">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                No backup archives created yet. Click "+ Create Backup Now" to generate your first verified backup.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RESTORE DATABASE SAFETY CONFIRMATION MODAL -->
<div id="restore-backup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.75); z-index: 99999; align-items: center; justify-content: center;">
    <div style="max-width: 480px; width: 100%; margin: 1rem; background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--danger, #DC2626); display: flex; align-items: center; gap: 0.4rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <span>Confirm Database Restoration</span>
            </h3>
            <button type="button" onclick="closeRestoreModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-primary); line-height: 1.5; margin-bottom: 1rem;">
            <p style="margin-top: 0;">You are about to restore the database from archive: <strong id="restore-modal-filename"></strong>.</p>
            <div style="background: var(--bg-surface); padding: 0.75rem; border-radius: 6px; border-left: 3px solid #059669; margin-bottom: 1rem; font-size: 0.8rem;">
                ✓ A safety snapshot of your current database will be generated automatically before any changes are applied.
            </div>
            <p style="color: var(--text-muted); margin-bottom: 0.5rem;">To confirm, please type <strong>RESTORE</strong> below:</p>
            <input type="text" id="restore-confirm-text" placeholder="Type RESTORE" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.9rem;" oninput="checkRestoreInput()">
        </div>
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="btn-execute-restore" onclick="executeRestore()" disabled style="opacity: 0.5;">Restore Database</button>
        </div>
    </div>
</div>

<!-- DELETE BACKUP SAFETY CONFIRMATION MODAL -->
<div id="delete-backup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.75); z-index: 99999; align-items: center; justify-content: center;">
    <div style="max-width: 440px; width: 100%; margin: 1rem; background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--danger, #DC2626); display: flex; align-items: center; gap: 0.4rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                <span>Confirm Delete Backup</span>
            </h3>
            <button type="button" onclick="closeDeleteModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-primary); line-height: 1.5; margin-bottom: 1.25rem;">
            <p style="margin-top: 0;">Are you sure you want to permanently delete backup archive: <strong id="delete-modal-filename"></strong>?</p>
            <div style="background: var(--bg-surface); padding: 0.75rem; border-radius: 6px; border-left: 3px solid #dc2626; font-size: 0.8rem; color: var(--text-muted);">
                This will remove the archive from disk storage. Note: Safety policy prevents deleting the only remaining successful backup.
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="btn-execute-delete" onclick="executeDeleteBackup()">Delete Permanently</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- AUDIT DETAIL INSPECTION MODAL -->
<div id="audit-detail-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="modal-title" id="audit-modal-title">Audit Payload Inspection</h3>
            <button type="button" class="modal-close" onclick="closeAuditModal()">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);">Event Description</label>
                <div id="audit-modal-desc" style="font-size: 0.95rem; font-weight: 600; margin-top: 0.25rem;"></div>
            </div>
            <div id="audit-modal-before-sec" style="margin-bottom: 1rem; display: none;">
                <label style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--danger);">Before State</label>
                <pre id="audit-modal-before" style="background: var(--bg-surface); padding: 0.75rem; border-radius: 6px; font-size: 0.8rem; overflow-x: auto; margin-top: 0.25rem; border: 1px solid var(--border-color);"></pre>
            </div>
            <div id="audit-modal-after-sec" style="margin-bottom: 1rem; display: none;">
                <label style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-success);">After State / Payload</label>
                <pre id="audit-modal-after" style="background: var(--bg-surface); padding: 0.75rem; border-radius: 6px; font-size: 0.8rem; overflow-x: auto; margin-top: 0.25rem; border: 1px solid var(--border-color);"></pre>
            </div>
        </div>
        <div class="modal-footer" style="text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="closeAuditModal()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================== -->
<!-- 1. EDIT TENANT ORGANIZATION PROFILE MODAL  -->
<!-- ========================================== -->
<div id="company-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Tenant Organization Profile</h3>
            <button type="button" class="modal-close" onclick="WTSBill.Modal.close('company-modal')">&times;</button>
        </div>
        <form id="company-form" onsubmit="handleCompanyFormSubmit(event)">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem; max-height: 75vh; overflow-y: auto;">
                <div id="company-form-error" class="alert alert-danger" style="display: none;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Trade / Display Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="company-name" name="name" class="form-control" value="<?= e($compTrade) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Legal Entity Name</label>
                        <input type="text" id="company-legal-name" name="legal_name" class="form-control" value="<?= e($compName) ?>" placeholder="e.g. Wis Technosavvy Pvt Ltd">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">GSTIN (15 Alphanumeric)</label>
                        <input type="text" id="company-gstin" name="gstin" class="form-control" value="<?= e($compGstin) ?>" maxlength="15" placeholder="27AADCW7577N1ZE" style="text-transform: uppercase;" oninput="syncGstinPan(this.value)">
                    </div>
                    <div>
                        <label class="form-label">PAN (10 Digits)</label>
                        <input type="text" id="company-pan" name="pan" class="form-control" value="<?= e($compPan) ?>" maxlength="10" placeholder="AADCW7577N" style="text-transform: uppercase;">
                    </div>
                </div>

                <div>
                    <label class="form-label">Premises / Address Line 1</label>
                    <input type="text" id="company-address1" name="address_line1" class="form-control" value="<?= e($comp['address_line1'] ?? '') ?>" placeholder="Office No-B-7, 2nd floor, Shreya Business Hub">
                </div>
                <div>
                    <label class="form-label">Address Line 2 (Optional)</label>
                    <input type="text" id="company-address2" name="address_line2" class="form-control" value="<?= e($comp['address_line2'] ?? '') ?>" placeholder="Opp CNG Pump, Narhe">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" id="company-city" name="city" class="form-control" value="<?= e($compCity) ?>" placeholder="Pune">
                    </div>
                    <div>
                        <label class="form-label">State</label>
                        <input type="text" id="company-state" name="state" class="form-control" value="<?= e($compState) ?>">
                    </div>
                    <div>
                        <label class="form-label">State Code</label>
                        <input type="text" id="company-state-code" name="state_code" class="form-control" value="<?= e($compStateCode) ?>" maxlength="2">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Pincode</label>
                        <input type="text" id="company-pincode" name="pincode" class="form-control" value="<?= e($compPincode) ?>" placeholder="411041" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" id="company-phone" name="phone" class="form-control" value="<?= e($compPhone) ?>" placeholder="020 47252364">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" id="company-email" name="email" class="form-control" value="<?= e($compEmail) ?>" placeholder="info@wtsindia.co.in">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">MSME No. (UDYAM)</label>
                        <input type="text" id="company-msme" name="msme_no" class="form-control" value="<?= e($compMsme) ?>" placeholder="UDYAM-MH-26-0734598">
                    </div>
                    <div>
                        <label class="form-label">Website</label>
                        <input type="text" id="company-website" name="website" class="form-control" value="<?= e($compWebsite) ?>" placeholder="http://www.wtsindia.co.in">
                    </div>
                    <div>
                        <label class="form-label">Merchant UPI ID (e.g. merchant@bank)</label>
                        <input type="text" id="company-upi" name="upi_id" class="form-control" value="<?= e($compUpi) ?>" placeholder="merchant@upi">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Merchant Payee Display Name</label>
                        <input type="text" id="company-upi-name" name="upi_display_name" class="form-control" value="<?= e($compUpiName) ?>" placeholder="Legal or Brand Name">
                    </div>
                    <div>
                        <label class="form-label">Default UPI Payment Instructions</label>
                        <input type="text" id="company-payment-instructions" name="payment_instructions" class="form-control" value="<?= e($compPaymentInstructions) ?>" placeholder="Scan this QR code using any supported UPI application to pay the outstanding invoice balance.">
                    </div>
                </div>

                <div>
                    <label class="form-label">Default Invoice Terms &amp; Conditions</label>
                    <textarea id="company-terms" name="terms_and_conditions" class="form-control" rows="3" placeholder="Terms printed on invoices"><?= e($compTerms) ?></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="WTSBill.Modal.close('company-modal')">Cancel</button>
                <button type="submit" id="company-submit-btn" class="btn btn-primary">Save Organization Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. CREATE / EDIT BRANCH MODAL              -->
<!-- ========================================== -->
<div id="branch-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 640px;">
        <div class="modal-header">
            <h3 id="branch-modal-title" class="modal-title">Register Operating Branch</h3>
            <button type="button" class="modal-close" onclick="WTSBill.Modal.close('branch-modal')">&times;</button>
        </div>
        <form id="branch-form" onsubmit="handleBranchFormSubmit(event)">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="branch-id" name="id" value="">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem; max-height: 75vh; overflow-y: auto;">
                <div id="branch-form-error" class="alert alert-danger" style="display: none;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Branch Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="branch-name" name="name" class="form-control" placeholder="e.g. Pune Regional Branch" required>
                    </div>
                    <div>
                        <label class="form-label">Branch Code <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="branch-code" name="branch_code" class="form-control" placeholder="e.g. PUN-02, NAS-03" required style="text-transform: uppercase;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Contact Person</label>
                        <input type="text" id="branch-contact" name="contact_person" class="form-control" placeholder="e.g. Branch Manager">
                    </div>
                    <div>
                        <label class="form-label">Legal Name (if separate)</label>
                        <input type="text" id="branch-legal-name" name="legal_name" class="form-control" placeholder="Leave blank to use Branch Name">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">GSTIN (15 Digits)</label>
                        <input type="text" id="branch-gstin" name="gstin" class="form-control" placeholder="27AAAAA0000A1Z5" maxlength="15" style="text-transform: uppercase;">
                    </div>
                    <div>
                        <label class="form-label">PAN (10 Digits)</label>
                        <input type="text" id="branch-pan" name="pan" class="form-control" placeholder="AAAAA0000A" maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>

                <div>
                    <label class="form-label">Premises / Street Address</label>
                    <textarea id="branch-address" name="address" class="form-control" rows="2" placeholder="Building, plot, street, industrial area"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" id="branch-city" name="city" class="form-control" placeholder="e.g. Pune">
                    </div>
                    <div>
                        <label class="form-label">State</label>
                        <input type="text" id="branch-state" name="state" class="form-control" value="Maharashtra">
                    </div>
                    <div>
                        <label class="form-label">State Code</label>
                        <input type="text" id="branch-state-code" name="state_code" class="form-control" value="27" maxlength="2">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Pincode</label>
                        <input type="text" id="branch-pincode" name="pincode" class="form-control" placeholder="411057" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Contact Phone</label>
                        <input type="text" id="branch-phone" name="phone" class="form-control" placeholder="+91 9876543210">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" id="branch-email" name="email" class="form-control" placeholder="pune@wtsbill.in">
                    </div>
                </div>

                <div style="display: flex; gap: 1.5rem; align-items: center; margin-top: 0.5rem; padding: 0.75rem; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="branch-is-main" name="is_main_branch" value="1">
                        <span>Is Main Organization HQ Branch</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="branch-is-active" name="is_active" value="1" checked>
                        <span>Active for Operations</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="WTSBill.Modal.close('branch-modal')">Cancel</button>
                <button type="submit" id="branch-submit-btn" class="btn btn-primary">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<script>
function syncGstinPan(gstin) {
    gstin = (gstin || '').toUpperCase().trim();
    if (gstin.length >= 12) {
        var pan = gstin.substring(2, 12);
        var panInput = document.getElementById('company-pan');
        if (panInput && (!panInput.value || panInput.value.length < 10)) {
            panInput.value = pan;
        }
    }
    if (gstin.length >= 2) {
        var stateCode = gstin.substring(0, 2);
        var stateCodeInput = document.getElementById('company-state-code');
        if (stateCodeInput && !stateCodeInput.value) {
            stateCodeInput.value = stateCode;
        }
    }
}

function openEditCompanyModal() {
    var errBox = document.getElementById('company-form-error');
    if (errBox) errBox.style.display = 'none';

    // Fetch latest fresh company data
    WTSBill.Http.fetchWithCsrf('/api/settings/company')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.company) {
                var c = data.company;
                document.getElementById('company-name').value = c.name || '';
                document.getElementById('company-legal-name').value = c.legal_name || c.name || '';
                document.getElementById('company-gstin').value = c.gstin || '';
                document.getElementById('company-pan').value = c.pan || '';
                document.getElementById('company-address1').value = c.address_line1 || '';
                document.getElementById('company-address2').value = c.address_line2 || '';
                document.getElementById('company-city').value = c.city || '';
                document.getElementById('company-state').value = c.state || 'Maharashtra';
                document.getElementById('company-state-code').value = c.state_code || '27';
                document.getElementById('company-pincode').value = c.pincode || '';
                document.getElementById('company-phone').value = c.phone || '';
                document.getElementById('company-email').value = c.email || '';
                document.getElementById('company-msme').value = c.msme_no || '';
                document.getElementById('company-website').value = c.website || '';
                document.getElementById('company-upi').value = c.upi_id || '';
                document.getElementById('company-upi-name').value = c.upi_display_name || c.legal_name || c.name || '';
                document.getElementById('company-payment-instructions').value = c.payment_instructions || '';
                document.getElementById('company-terms').value = c.terms_and_conditions || '';
            }
            WTSBill.Modal.open('company-modal');
        })
        .catch(function() {
            WTSBill.Modal.open('company-modal');
        });
}

function handleCompanyFormSubmit(e) {
    e.preventDefault();
    var form = document.getElementById('company-form');
    var btn = document.getElementById('company-submit-btn');
    var errBox = document.getElementById('company-form-error');

    btn.disabled = true;
    btn.textContent = 'Saving...';
    errBox.style.display = 'none';

    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function(val, key) { payload[key] = val; });

    WTSBill.Http.fetchWithCsrf('/settings/company/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) {
        return res.json().then(function(data) { return { ok: res.ok, data: data }; });
    })
    .then(function(result) {
        btn.disabled = false;
        btn.textContent = 'Save Organization Profile';

        if (result.ok && result.data.success) {
            WTSBill.Modal.close('company-modal');
            WTSBill.Toast.show(result.data.message || 'Organization Profile updated successfully.', 'success');
            setTimeout(function() { window.location.reload(); }, 400);
        } else {
            errBox.textContent = result.data.message || 'Failed to update organization profile.';
            errBox.style.display = 'block';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Save Organization Profile';
        errBox.textContent = 'Network error: ' + err.message;
        errBox.style.display = 'block';
    });
}

function openCreateBranchModal() {
    document.getElementById('branch-modal-title').textContent = 'Register Operating Branch';
    document.getElementById('branch-form').reset();
    document.getElementById('branch-id').value = '';
    document.getElementById('branch-is-active').checked = true;
    document.getElementById('branch-is-main').checked = false;
    document.getElementById('branch-form-error').style.display = 'none';
    WTSBill.Modal.open('branch-modal');
}

function openEditBranchModal(branchId) {
    document.getElementById('branch-modal-title').textContent = 'Edit Branch Details';
    document.getElementById('branch-form-error').style.display = 'none';

    WTSBill.Http.fetchWithCsrf('/branches/' + branchId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.branch) {
                var b = data.branch;
                document.getElementById('branch-id').value = b.id;
                document.getElementById('branch-name').value = b.name || '';
                document.getElementById('branch-code').value = b.branch_code || b.code || '';
                document.getElementById('branch-contact').value = b.contact_person || '';
                document.getElementById('branch-legal-name').value = b.legal_name || '';
                document.getElementById('branch-gstin').value = b.gstin || '';
                document.getElementById('branch-pan').value = b.pan || '';
                document.getElementById('branch-address').value = b.address || '';
                document.getElementById('branch-city').value = b.city || '';
                document.getElementById('branch-state').value = b.state || 'Maharashtra';
                document.getElementById('branch-state-code').value = b.state_code || '27';
                document.getElementById('branch-pincode').value = b.pincode || '';
                document.getElementById('branch-phone').value = b.phone || '';
                document.getElementById('branch-email').value = b.email || '';
                document.getElementById('branch-is-main').checked = (parseInt(b.is_main_branch) === 1);
                document.getElementById('branch-is-active').checked = (parseInt(b.is_active) === 1);

                WTSBill.Modal.open('branch-modal');
            } else {
                WTSBill.Toast.show(data.message || 'Failed to load branch details', 'danger');
            }
        })
        .catch(function(err) {
            WTSBill.Toast.show('Error loading branch: ' + err.message, 'danger');
        });
}

function handleBranchFormSubmit(e) {
    e.preventDefault();
    var branchId = document.getElementById('branch-id').value;
    var isEdit = (branchId && parseInt(branchId) > 0);
    var url = isEdit ? '/branches/' + branchId + '/update' : '/branches';
    var btn = document.getElementById('branch-submit-btn');
    var errBox = document.getElementById('branch-form-error');

    btn.disabled = true;
    btn.textContent = 'Saving...';
    errBox.style.display = 'none';

    var formData = new FormData(document.getElementById('branch-form'));
    var payload = {};
    formData.forEach(function(val, key) { payload[key] = val; });
    payload['is_main_branch'] = document.getElementById('branch-is-main').checked ? 1 : 0;
    payload['is_active'] = document.getElementById('branch-is-active').checked ? 1 : 0;

    WTSBill.Http.fetchWithCsrf(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) {
        return res.json().then(function(data) { return { ok: res.ok, data: data }; });
    })
    .then(function(result) {
        btn.disabled = false;
        btn.textContent = 'Save Branch';

        if (result.ok && result.data.success) {
            WTSBill.Modal.close('branch-modal');
            WTSBill.Toast.show(result.data.message || 'Branch saved successfully.', 'success');
            setTimeout(function() { window.location.reload(); }, 400);
        } else {
            errBox.textContent = result.data.message || 'Failed to save branch.';
            errBox.style.display = 'block';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Save Branch';
        errBox.textContent = 'Network error: ' + err.message;
        errBox.style.display = 'block';
    });
}

// ==========================================
// AUDIT LOGS & TAB SWITCHING WORKFLOW
// ==========================================
var currentAuditPage = 1;
var totalAuditPages = 1;
var auditSearchDebounceTimer = null;
var cachedAuditRows = {};

function switchSettingsTab(tab) {
    var generalSec = document.getElementById('settings-sec-general');
    var auditSec = document.getElementById('settings-sec-audit');
    var backupsSec = document.getElementById('settings-sec-backups');

    var genBtn = document.getElementById('tab-btn-general');
    var audBtn = document.getElementById('tab-btn-audit');
    var backupsBtn = document.getElementById('tab-btn-backups');

    // Hide all
    if (generalSec) generalSec.style.display = 'none';
    if (auditSec) auditSec.style.display = 'none';
    if (backupsSec) backupsSec.style.display = 'none';

    [genBtn, audBtn, backupsBtn].forEach(function(btn) {
        if (btn) {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-secondary');
        }
    });

    if (tab === 'audit') {
        if (auditSec) auditSec.style.display = 'block';
        if (audBtn) { audBtn.classList.remove('btn-secondary'); audBtn.classList.add('active', 'btn-primary'); }
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', '/settings/audit-logs');
        loadAuditLogs(1);
    } else if (tab === 'backups') {
        if (backupsSec) backupsSec.style.display = 'block';
        if (backupsBtn) { backupsBtn.classList.remove('btn-secondary'); backupsBtn.classList.add('active', 'btn-primary'); }
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', '/settings?tab=backups');
    } else {
        if (generalSec) generalSec.style.display = 'block';
        if (genBtn) { genBtn.classList.remove('btn-secondary'); genBtn.classList.add('active', 'btn-primary'); }
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', '/settings');
    }
}

// -------------------------------------------------------------
// Backup & Disaster Recovery Actions
// -------------------------------------------------------------
function createBackupNow() {
    var btn = document.getElementById('btn-create-backup');
    if (btn) { btn.disabled = true; btn.textContent = 'Generating Verified Backup...'; }

    fetch('/settings/backups/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (btn) { btn.disabled = false; btn.textContent = '+ Create Backup Now'; }
        if (res.success) {
            Toast.show(res.message || 'Backup archive created and verified.', 'success');
            setTimeout(function() { window.location.href = '/settings?tab=backups'; }, 1000);
        } else {
            Toast.show(res.message || 'Backup creation failed.', 'error');
        }
    }).catch(function(err) {
        if (btn) { btn.disabled = false; btn.textContent = '+ Create Backup Now'; }
        Toast.show('Error: ' + err.message, 'error');
    });
}

function runAuditCleanup() {
    var btn = document.getElementById('btn-cleanup-audit');
    var retentionDays = parseInt(document.getElementById('cleanup-retention-days').value, 10) || 7;
    if (btn) { btn.disabled = true; btn.textContent = 'Running Backup & Purge...'; }

    fetch('/settings/audit-logs/cleanup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ retention_days: retentionDays })
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (btn) { btn.disabled = false; btn.textContent = 'Run Safe Cleanup'; }
        if (res.success) {
            Toast.show(res.message || 'Audit logs safely archived and cleaned.', 'success');
            setTimeout(function() { window.location.href = '/settings?tab=backups'; }, 1200);
        } else {
            Toast.show(res.message || 'Audit cleanup failed.', 'error');
        }
    }).catch(function(err) {
        if (btn) { btn.disabled = false; btn.textContent = 'Run Safe Cleanup'; }
        Toast.show('Error: ' + err.message, 'error');
    });
}

var activeRestoreBackupId = null;
function openRestoreModal(backupId, filename) {
    activeRestoreBackupId = backupId;
    document.getElementById('restore-modal-filename').textContent = filename;
    document.getElementById('restore-confirm-text').value = '';
    document.getElementById('btn-execute-restore').disabled = true;
    document.getElementById('btn-execute-restore').style.opacity = '0.5';
    document.getElementById('restore-backup-modal').style.display = 'flex';
}

function closeRestoreModal() {
    document.getElementById('restore-backup-modal').style.display = 'none';
    activeRestoreBackupId = null;
}

function checkRestoreInput() {
    var val = (document.getElementById('restore-confirm-text').value || '').trim();
    var btn = document.getElementById('btn-execute-restore');
    if (val === 'RESTORE') {
        btn.disabled = false;
        btn.style.opacity = '1';
    } else {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }
}

function executeRestore() {
    if (!activeRestoreBackupId) return;
    var btn = document.getElementById('btn-execute-restore');
    btn.disabled = true;
    btn.textContent = 'Restoring Database...';

    fetch('/settings/backups/' + activeRestoreBackupId + '/restore', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ confirm: true, backup_id: activeRestoreBackupId })
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.success) {
            Toast.show(res.message || 'Database restored successfully.', 'success');
            setTimeout(function() { window.location.href = '/settings?tab=backups'; }, 1500);
        } else {
            btn.disabled = false;
            btn.textContent = 'Restore Database';
            Toast.show(res.message || 'Database restoration aborted.', 'error');
        }
    }).catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Restore Database';
        Toast.show('Error: ' + err.message, 'error');
    });
}

var activeDeleteBackupId = null;
function openDeleteModal(backupId, filename) {
    activeDeleteBackupId = backupId;
    var elem = document.getElementById('delete-modal-filename');
    if (elem) elem.textContent = filename;
    var modal = document.getElementById('delete-backup-modal');
    if (modal) modal.style.display = 'flex';
}

function closeDeleteModal() {
    var modal = document.getElementById('delete-backup-modal');
    if (modal) modal.style.display = 'none';
    activeDeleteBackupId = null;
}

function executeDeleteBackup() {
    if (!activeDeleteBackupId) return;
    var btn = document.getElementById('btn-execute-delete');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Deleting...';
    }

    fetch('/settings/backups/' + activeDeleteBackupId + '/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ backup_id: activeDeleteBackupId })
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.success) {
            Toast.show(res.message || 'Backup archive deleted.', 'success');
            setTimeout(function() { window.location.href = '/settings?tab=backups'; }, 1000);
        } else {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Delete Permanently';
            }
            Toast.show(res.message || 'Failed to delete backup archive.', 'error');
        }
    }).catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Delete Permanently';
        }
        Toast.show('Error: ' + err.message, 'error');
    });
}

function debounceAuditSearch() {
    clearTimeout(auditSearchDebounceTimer);
    auditSearchDebounceTimer = setTimeout(function() {
        loadAuditLogs(1);
    }, 300);
}

function loadAuditLogs(page) {
    if (!page) page = 1;
    currentAuditPage = page;

    var search = document.getElementById('audit-filter-search') ? document.getElementById('audit-filter-search').value : '';
    var action = document.getElementById('audit-filter-action') ? document.getElementById('audit-filter-action').value : '';
    var entity = document.getElementById('audit-filter-entity') ? document.getElementById('audit-filter-entity').value : '';

    var url = '/api/settings/audit-logs?page=' + page + '&per_page=20' +
              '&search=' + encodeURIComponent(search) +
              '&action=' + encodeURIComponent(action) +
              '&entity_type=' + encodeURIComponent(entity);

    fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(res) {
        if (res.success && res.data) {
            renderAuditRows(res.data);
            if (res.meta) {
                totalAuditPages = res.meta.last_page || 1;
                document.getElementById('audit-page-info').textContent = 'Page ' + res.meta.page + ' of ' + (res.meta.last_page || 1) + ' (' + res.meta.total + ' total events)';
                document.getElementById('audit-prev-btn').disabled = (res.meta.page <= 1);
                document.getElementById('audit-next-btn').disabled = (res.meta.page >= res.meta.last_page);
                var countBadge = document.getElementById('audit-badge-count');
                if (countBadge) countBadge.textContent = res.meta.total;
                var totalKpi = document.getElementById('audit-kpi-total');
                if (totalKpi) totalKpi.textContent = res.meta.total;
            }
        }
    })
    .catch(function(err) {
        console.error('Failed to load audit logs:', err);
    });
}

function renderAuditRows(rows) {
    var tbody = document.getElementById('audit-table-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    cachedAuditRows = {};

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">No matching audit events found.</td></tr>';
        return;
    }

    rows.forEach(function(r) {
        cachedAuditRows[r.id] = r;
        var act = (r.action || '').toUpperCase();
        var badgeClass = 'secondary';
        if (act === 'CREATE') badgeClass = 'success';
        else if (act === 'UPDATE') badgeClass = 'info';
        else if (act === 'DELETE') badgeClass = 'danger';
        else if (act === 'LOGIN') badgeClass = 'primary';

        var tr = document.createElement('tr');
        tr.id = 'audit-row-' + r.id;
        tr.innerHTML = `
            <td><code>#${r.id}</code></td>
            <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.8rem;">
                ${r.created_at || '—'}
            </td>
            <td>
                <strong>${r.user_name || 'Administrator'}</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">${r.ip_address || '127.0.0.1'}</div>
            </td>
            <td style="text-align: center;">
                <span class="badge badge-${badgeClass}">${act}</span>
            </td>
            <td>
                <code>${r.entity_type || 'SYSTEM'}${r.entity_id ? ' #' + r.entity_id : ''}</code>
            </td>
            <td>${r.description || '—'}</td>
            <td style="text-align: center;">
                ${(r.before_data_json || r.after_data_json) ? `<button type="button" class="btn btn-sm btn-outline-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.75rem;" onclick="inspectAuditLog(${r.id})">View</button>` : `<span style="color: var(--text-muted);">&mdash;</span>`}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function prevAuditPage() {
    if (currentAuditPage > 1) {
        loadAuditLogs(currentAuditPage - 1);
    }
}

function nextAuditPage() {
    if (currentAuditPage < totalAuditPages) {
        loadAuditLogs(currentAuditPage + 1);
    }
}

function inspectAuditLog(id) {
    var log = cachedAuditRows[id];
    if (!log) return;

    document.getElementById('audit-modal-desc').textContent = log.description || 'Action performed by ' + (log.user_name || 'Administrator');

    var beforeSec = document.getElementById('audit-modal-before-sec');
    var afterSec = document.getElementById('audit-modal-after-sec');

    if (log.before_data_json) {
        try {
            var bObj = JSON.parse(log.before_data_json);
            document.getElementById('audit-modal-before').textContent = JSON.stringify(bObj, null, 2);
        } catch(e) {
            document.getElementById('audit-modal-before').textContent = log.before_data_json;
        }
        beforeSec.style.display = 'block';
    } else {
        beforeSec.style.display = 'none';
    }

    if (log.after_data_json) {
        try {
            var aObj = JSON.parse(log.after_data_json);
            document.getElementById('audit-modal-after').textContent = JSON.stringify(aObj, null, 2);
        } catch(e) {
            document.getElementById('audit-modal-after').textContent = log.after_data_json;
        }
        afterSec.style.display = 'block';
    } else {
        afterSec.style.display = 'none';
    }

    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.open('audit-detail-modal');
    } else {
        document.getElementById('audit-detail-modal').classList.add('active');
    }
}

function closeAuditModal() {
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.close('audit-detail-modal');
    } else {
        document.getElementById('audit-detail-modal').classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var qTab = urlParams.get('tab');
    if (qTab === 'audit' || window.location.pathname.indexOf('audit-logs') !== -1) {
        switchSettingsTab('audit');
    } else if (qTab === 'backups') {
        switchSettingsTab('backups');
    }
});
</script>
