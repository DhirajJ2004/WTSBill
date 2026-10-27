<?php

use WTSBill\Core\Session;
use WTSBill\Repositories\BranchRepository;

$userName = Session::get('user_name', 'Administrator');
$userEmail = Session::get('user_email', 'admin@wtsbill.in');
$userRole = Session::get('user_role', 'ADMIN');
$userId = (int)(Session::get('user_id') ?? 1);
$companyId = Session::getCompanyId() ?? 1;
$activeBranchId = Session::getBranchId() ?? 1;

$isAdmin = in_array(strtoupper((string)$userRole), ['ADMIN', 'SUPERADMIN', 'OWNER'], true);

$branchRepo = new BranchRepository();
$authorizedBranches = $branchRepo->findSwitcherBranches($userId, $companyId, $userRole);

// Resolve active branch
$activeBranch = null;
foreach ($authorizedBranches as $b) {
    if ((int)$b['id'] === $activeBranchId) {
        $activeBranch = $b;
        break;
    }
}
if (!$activeBranch && !empty($authorizedBranches)) {
    $activeBranch = $authorizedBranches[0];
    $activeBranchId = (int)$activeBranch['id'];
}
$activeBranchName = $activeBranch['name'] ?? Session::getBranchName() ?? 'Main Branch';
$activeBranchCode = $activeBranch['branch_code'] ?? $activeBranch['code'] ?? 'MAIN';

// Initials calculation
$nameParts = explode(' ', trim($userName));
$initials = strtoupper(substr($nameParts[0] ?? 'A', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
if (strlen($initials) === 1) {
    $initials = substr(strtoupper($userName), 0, 2);
}
?>
<header class="erp-header">
    <div class="header-left">
        <button type="button" id="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Toggle navigation" onclick="window.toggleSidebar(event)" title="Toggle Sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Active Tenant Context (accessible to automation/DOM without visual noise) -->
        <span class="tenant-meta" data-company-id="<?= e((string)$companyId) ?>" data-branch-id="<?= e((string)$activeBranchId) ?>" style="display: none;" aria-hidden="true">Company #<?= e((string)$companyId) ?> &bull; Branch #<?= e((string)$activeBranchId) ?></span>

        <!-- Branch Selector Dropdown or Single Branch Badge -->
        <?php if ($isAdmin || count($authorizedBranches) > 1): ?>
            <div class="branch-selector-dropdown" id="branch-selector-wrapper" style="position: relative; display: inline-block;">
                <button type="button" id="branch-switch-btn" class="branch-btn" 
                        aria-haspopup="true" aria-expanded="false" title="Switch Operating Branch"
                        onclick="if(window.WTSBill && WTSBill.Dropdown){ WTSBill.Dropdown.toggle('branch-switch-menu'); } else { var m = document.getElementById('branch-switch-menu'); if(m){ m.classList.toggle('show'); } } event.stopPropagation();"
                        style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.35rem 0.75rem; color: var(--text-main); cursor: pointer; text-align: left; height: 38px;">
                    <span class="branch-icon" style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: var(--primary-soft); color: var(--primary); flex-shrink: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </span>
                    <div class="branch-info" style="display: flex; flex-direction: column; line-height: 1.2;">
                        <span class="branch-label" style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Operating Branch</span>
                        <span class="branch-name" id="header-branch-name" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($activeBranchName) ?></span>
                    </div>
                    <svg class="branch-caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-muted); margin-left: 0.25rem;">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <div id="branch-switch-menu" class="dropdown-menu branch-dropdown-menu" style="position: absolute; top: calc(100% + 6px); left: 0; min-width: 290px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(15,23,42,0.1), 0 8px 10px -6px rgba(15,23,42,0.06); z-index: 9999;">
                    <div class="branch-menu-header" style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); background: var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">Switch Branch</div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">Select operating branch context</div>
                        </div>
                        <span class="badge badge-secondary" id="header-branch-count" style="font-size: 0.68rem;"><?= count($authorizedBranches) ?></span>
                    </div>
                    <div class="branch-menu-list" id="header-branch-list" style="padding: 0.35rem; max-height: 280px; overflow-y: auto;">
                        <?php foreach ($authorizedBranches as $branch): ?>
                            <?php 
                            $bId = (int)$branch['id'];
                            $isCurrent = ($bId === (int)$activeBranchId);
                            $bActive = ((int)$branch['is_active'] === 1);
                            ?>
                            <div class="branch-menu-item-wrap" id="branch-item-wrap-<?= $bId ?>" style="margin-bottom: 2px;">
                                <?php if ($bActive): ?>
                                    <form action="/branches/switch" method="POST" onsubmit="if(window.WTSBill && WTSBill.Branch){ WTSBill.Branch.switch(<?= $bId ?>, '<?= e(addslashes($branch['name'])) ?>'); return false; }" style="margin: 0; padding: 0; width: 100%;">
                                        <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
                                        <input type="hidden" name="branch_id" value="<?= $bId ?>">
                                        <button type="submit" 
                                                class="branch-item <?= $isCurrent ? 'active' : '' ?>" 
                                                data-branch-id="<?= $bId ?>"
                                                style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-radius: 6px; border: none; background: <?= $isCurrent ? 'var(--primary-soft)' : 'transparent' ?>; color: var(--text-main); cursor: pointer; text-align: left; transition: background 0.15s ease;"
                                                onmouseover="if(!this.classList.contains('active')) this.style.background='var(--border-light)';"
                                                onmouseout="if(!this.classList.contains('active')) this.style.background='transparent';">
                                            <div class="branch-item-left" style="display: flex; align-items: center; gap: 0.65rem;">
                                                <span class="branch-dot <?= $isCurrent ? 'active' : '' ?>" style="width: 8px; height: 8px; border-radius: 50%; background: <?= $isCurrent ? 'var(--primary)' : 'var(--text-muted)' ?>;"></span>
                                                <div>
                                                    <div class="branch-item-name" style="font-size: 0.85rem; font-weight: 500; color: <?= $isCurrent ? 'var(--primary)' : 'var(--text-main)' ?>;"><?= e($branch['name']) ?></div>
                                                    <div class="branch-item-code" style="font-size: 0.72rem; color: var(--text-muted);">
                                                        <span><?= e($branch['branch_code'] ?? $branch['code'] ?? '') ?></span>
                                                        <?php if (!empty($branch['city'])): ?>
                                                            &bull; <span><?= e($branch['city']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge badge-success" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; background: var(--success); color: #ffffff; border-radius: 4px; font-weight: 600;">ACTIVE</span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="branch-item inactive" title="Inactive branch cannot be selected" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-radius: 6px; opacity: 0.6; cursor: not-allowed; background: transparent;">
                                        <div class="branch-item-left" style="display: flex; align-items: center; gap: 0.65rem;">
                                            <span class="branch-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted);"></span>
                                            <div>
                                                <div class="branch-item-name" style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);"><?= e($branch['name']) ?></div>
                                                <div class="branch-item-code" style="font-size: 0.72rem; color: var(--text-muted);">
                                                    <span><?= e($branch['branch_code'] ?? $branch['code'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="badge badge-danger" style="font-size: 0.62rem; padding: 0.15rem 0.35rem;">INACTIVE</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="branch-menu-footer" style="padding: 0.5rem 0.75rem; border-top: 1px solid var(--border); background: var(--bg-card); display: flex; flex-direction: column;">
                            <button type="button" id="header-add-branch-btn" class="btn-add-branch" 
                                    onclick="openAddBranchModal(); event.stopPropagation();"
                                    style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.55rem 0.75rem; border-radius: 6px; border: 1px dashed var(--primary); background: var(--primary-soft); color: var(--primary); font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease;"
                                    onmouseover="this.style.background='var(--border-light)';"
                                    onmouseout="this.style.background='var(--primary-soft)';">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>+ Add Branch</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="branch-static-badge" title="Active Branch Context" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.4rem 0.75rem; color: var(--text-main); font-size: 0.85rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span style="font-weight: 600; color: var(--text-main);"><?= e($activeBranchName) ?></span>
                <span class="badge badge-secondary" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; margin-left: 0.25rem;">Active</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="header-right" style="display: flex; align-items: center; gap: 0.75rem;">
        <!-- Light / Dark Theme Switcher -->
        <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" title="Toggle Light / Dark Mode" onclick="window.toggleTheme(event)">
            <svg id="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg id="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <span id="theme-toggle-label" style="font-size: 0.75rem;">Theme</span>
        </button>

        <div class="user-dropdown">
            <button type="button" id="user-menu-btn" class="user-btn" aria-haspopup="true" aria-expanded="false">
                <div class="user-avatar"><?= e($initials) ?></div>
                <div class="user-info">
                    <span class="user-name"><?= e($userName) ?></span>
                    <span class="user-role"><?= e($userRole) ?></span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div id="user-menu-dropdown" class="dropdown-menu">
                <div style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);"><?= e($userName) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($userEmail) ?></div>
                </div>
                <?php if (in_array(strtoupper($userRole), ['ADMIN', 'SUPERADMIN', 'OWNER'])): ?>
                    <a href="/branches" class="dropdown-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Branches & Locations
                    </a>
                <?php endif; ?>
                <a href="/settings" class="dropdown-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                <form action="/logout" method="POST" style="margin: 0;">
                    <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
                    <button type="submit" class="dropdown-item" style="color: var(--color-danger);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<?php if ($isAdmin): ?>
<!-- Global Add Branch Modal for Authorized Administrators -->
<div id="add-branch-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="add-branch-modal-title" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="max-width: 640px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.1); overflow: hidden; animation: modalFadeIn 0.2s ease-out;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding: 1rem 1.25rem; background: var(--bg-card);">
            <div>
                <h3 id="add-branch-modal-title" class="modal-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>Add Branch</span>
                </h3>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.78rem; color: var(--text-muted);">Register a new operating branch location in WTSBill ERP</p>
            </div>
            <button type="button" class="modal-close" onclick="closeAddBranchModal()" aria-label="Close modal" style="background: transparent; border: none; font-size: 1.5rem; line-height: 1; color: var(--text-muted); cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px;">&times;</button>
        </div>
        
        <form id="add-branch-form" onsubmit="handleAddBranchSubmit(event)" novalidate style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
            
            <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto;">
                <!-- Inline Error Alert Box -->
                <div id="add-branch-error-alert" class="alert alert-danger" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                    <div style="font-weight: 600; margin-bottom: 0.25rem;" id="add-branch-error-title">Validation Error</div>
                    <div id="add-branch-error-msg"></div>
                </div>

                <!-- Inline Success Alert Box -->
                <div id="add-branch-success-alert" class="alert alert-success" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; background: rgba(34, 197, 94, 0.12); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3);">
                    <div id="add-branch-success-msg">Branch registered successfully.</div>
                </div>

                <!-- Form Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label" for="add-branch-name" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Branch Name <span style="color: var(--color-danger, #ef4444);">*</span>
                        </label>
                        <input type="text" id="add-branch-name" name="name" class="form-control" placeholder="e.g. Nagpur Regional Branch" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                        <span class="field-error" id="err-name" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                    </div>

                    <div>
                        <label class="form-label" for="add-branch-code" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Branch Code <span style="color: var(--color-danger, #ef4444);">*</span>
                        </label>
                        <input type="text" id="add-branch-code" name="branch_code" class="form-control" placeholder="e.g. NAG-03, SUR-04" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem; text-transform: uppercase;">
                        <span class="field-error" id="err-branch_code" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label" for="add-branch-contact-person" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Contact Person <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" id="add-branch-contact-person" name="contact_person" class="form-control" placeholder="e.g. Ramesh Patil" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label class="form-label" for="add-branch-phone" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Phone Number <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" id="add-branch-phone" name="phone" class="form-control" placeholder="e.g. +91 9876543210" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                        <span class="field-error" id="err-phone" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label" for="add-branch-email" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Email Address <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="email" id="add-branch-email" name="email" class="form-control" placeholder="e.g. nagpur@wtsbill.in" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                        <span class="field-error" id="err-email" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                    </div>

                    <div>
                        <label class="form-label" for="add-branch-status" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            Status <span style="color: var(--color-danger, #ef4444);">*</span>
                        </label>
                        <select id="add-branch-status" name="is_active" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label" for="add-branch-address" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                        Address <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                    </label>
                    <textarea id="add-branch-address" name="address" rows="2" class="form-control" placeholder="Building, premises, street or industrial locality" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem; resize: vertical;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 2fr 1.5fr; gap: 0.75rem;">
                    <div>
                        <label class="form-label" for="add-branch-city" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            City <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" id="add-branch-city" name="city" class="form-control" placeholder="e.g. Nagpur" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label class="form-label" for="add-branch-state" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            State <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" id="add-branch-state" name="state" class="form-control" value="Maharashtra" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label class="form-label" for="add-branch-pincode" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">
                            PIN Code <span style="color: var(--text-muted); font-weight: normal; font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" id="add-branch-pincode" name="pincode" class="form-control" placeholder="440001" maxlength="10" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem;">
                        <span class="field-error" id="err-pincode" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 1rem 1.25rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="closeAddBranchModal()" style="padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.875rem; font-weight: 500; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="add-branch-submit-btn" class="btn btn-primary" style="padding: 0.5rem 1.25rem; border-radius: 6px; border: none; background: var(--primary); color: #ffffff; font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <span id="add-branch-spinner" style="display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #ffffff; animation: spin 0.8s linear infinite;"></span>
                    <span id="add-branch-submit-label">Save Branch</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.openAddBranchModal = function() {
    var modal = document.getElementById('add-branch-modal');
    if (!modal) return;
    
    // Close branch dropdown if open
    var menu = document.getElementById('branch-switch-menu');
    if (menu) menu.classList.remove('show');
    
    // Reset form and alerts
    var form = document.getElementById('add-branch-form');
    if (form) form.reset();
    
    var errAlert = document.getElementById('add-branch-error-alert');
    if (errAlert) errAlert.style.display = 'none';
    var succAlert = document.getElementById('add-branch-success-alert');
    if (succAlert) succAlert.style.display = 'none';
    
    // Clear all field-level errors
    document.querySelectorAll('#add-branch-modal .field-error').forEach(function(el) {
        el.style.display = 'none';
        el.textContent = '';
    });
    document.querySelectorAll('#add-branch-modal .form-control').forEach(function(el) {
        el.style.borderColor = '';
    });
    
    var stateInput = document.getElementById('add-branch-state');
    if (stateInput && !stateInput.value) stateInput.value = 'Maharashtra';
    
    var statusSelect = document.getElementById('add-branch-status');
    if (statusSelect) statusSelect.value = '1';
    
    modal.style.display = 'flex';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    setTimeout(function() {
        var firstInput = document.getElementById('add-branch-name');
        if (firstInput) firstInput.focus();
    }, 50);
};

window.closeAddBranchModal = function() {
    var modal = document.getElementById('add-branch-modal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.style.display = 'none';
    document.body.style.overflow = '';
};

window.handleAddBranchSubmit = function(e) {
    if (e && e.preventDefault) e.preventDefault();
    
    var form = document.getElementById('add-branch-form');
    var btn = document.getElementById('add-branch-submit-btn');
    var spinner = document.getElementById('add-branch-spinner');
    var submitLabel = document.getElementById('add-branch-submit-label');
    var errAlert = document.getElementById('add-branch-error-alert');
    var errMsg = document.getElementById('add-branch-error-msg');
    var succAlert = document.getElementById('add-branch-success-alert');
    
    // Reset errors
    if (errAlert) errAlert.style.display = 'none';
    if (succAlert) succAlert.style.display = 'none';
    document.querySelectorAll('#add-branch-modal .field-error').forEach(function(el) {
        el.style.display = 'none';
        el.textContent = '';
    });
    document.querySelectorAll('#add-branch-modal .form-control').forEach(function(el) {
        el.style.borderColor = '';
    });

    var nameInput = document.getElementById('add-branch-name');
    var codeInput = document.getElementById('add-branch-code');
    var emailInput = document.getElementById('add-branch-email');
    var phoneInput = document.getElementById('add-branch-phone');
    var pincodeInput = document.getElementById('add-branch-pincode');
    var contactInput = document.getElementById('add-branch-contact-person');
    var statusInput = document.getElementById('add-branch-status');
    var addressInput = document.getElementById('add-branch-address');
    var cityInput = document.getElementById('add-branch-city');
    var stateInput = document.getElementById('add-branch-state');
    
    var hasError = false;
    var clientErrors = [];

    // Client-side validation
    var nameVal = (nameInput ? nameInput.value.trim() : '');
    if (!nameVal) {
        hasError = true;
        clientErrors.push('Branch Name is required.');
        if (nameInput) {
            nameInput.style.borderColor = '#ef4444';
            var errEl = document.getElementById('err-name');
            if (errEl) { errEl.textContent = 'Branch Name is required.'; errEl.style.display = 'block'; }
        }
    }

    var codeVal = (codeInput ? codeInput.value.trim().toUpperCase() : '');
    if (!codeVal) {
        hasError = true;
        clientErrors.push('Branch Code is required.');
        if (codeInput) {
            codeInput.style.borderColor = '#ef4444';
            var errEl = document.getElementById('err-branch_code');
            if (errEl) { errEl.textContent = 'Branch Code is required.'; errEl.style.display = 'block'; }
        }
    }

    var emailVal = (emailInput ? emailInput.value.trim() : '');
    if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        hasError = true;
        clientErrors.push('Please enter a valid email address.');
        if (emailInput) {
            emailInput.style.borderColor = '#ef4444';
            var errEl = document.getElementById('err-email');
            if (errEl) { errEl.textContent = 'Please enter a valid email address.'; errEl.style.display = 'block'; }
        }
    }

    var phoneVal = (phoneInput ? phoneInput.value.trim() : '');
    if (phoneVal && !/^[0-9\+\-\s\(\)]{7,20}$/.test(phoneVal)) {
        hasError = true;
        clientErrors.push('Please enter a valid phone number (7-20 digits).');
        if (phoneInput) {
            phoneInput.style.borderColor = '#ef4444';
            var errEl = document.getElementById('err-phone');
            if (errEl) { errEl.textContent = 'Please enter a valid phone number.'; errEl.style.display = 'block'; }
        }
    }

    var pinVal = (pincodeInput ? pincodeInput.value.trim() : '');
    if (pinVal && !/^[0-9]{4,10}$/.test(pinVal)) {
        hasError = true;
        clientErrors.push('PIN Code must be between 4 and 10 digits.');
        if (pincodeInput) {
            pincodeInput.style.borderColor = '#ef4444';
            var errEl = document.getElementById('err-pincode');
            if (errEl) { errEl.textContent = 'PIN Code must be 4 to 10 digits.'; errEl.style.display = 'block'; }
        }
    }

    if (hasError) {
        if (errAlert && errMsg) {
            errMsg.innerHTML = clientErrors.map(function(msg) { return '&bull; ' + msg; }).join('<br>');
            errAlert.style.display = 'block';
        }
        return false;
    }

    // Loading State
    if (btn) btn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    if (submitLabel) submitLabel.textContent = 'Saving Branch...';

    var payload = {
        name: nameVal,
        branch_code: codeVal,
        contact_person: (contactInput ? contactInput.value.trim() : ''),
        phone: phoneVal,
        email: emailVal,
        is_active: parseInt(statusInput ? statusInput.value : '1', 10),
        address: (addressInput ? addressInput.value.trim() : ''),
        city: (cityInput ? cityInput.value.trim() : ''),
        state: (stateInput ? stateInput.value.trim() : 'Maharashtra'),
        pincode: pinVal
    };

    var fetchFn = (window.WTSBill && WTSBill.Http && WTSBill.Http.fetchWithCsrf) ? WTSBill.Http.fetchWithCsrf : (window.fetchWithCsrf || fetch);

    fetchFn('/branches', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(function(res) {
        return res.json().then(function(data) {
            return { ok: res.ok, status: res.status, data: data };
        });
    })
    .then(function(result) {
        // Reset button state
        if (btn) btn.disabled = false;
        if (spinner) spinner.style.display = 'none';
        if (submitLabel) submitLabel.textContent = 'Save Branch';

        if (result.ok && result.data.success) {
            if (succAlert) {
                succAlert.textContent = result.data.message || 'Branch registered successfully.';
                succAlert.style.display = 'block';
            }
            if (window.Toast && window.Toast.show) {
                window.Toast.show(result.data.message || 'Branch created successfully.', 'success');
            } else if (window.WTSBill && WTSBill.Toast && WTSBill.Toast.show) {
                WTSBill.Toast.show(result.data.message || 'Branch created successfully.', 'success');
            }

            // Refresh branch list without page reload
            window.refreshBranchDropdown(result.data.branch);

            // Close modal after brief timeout
            setTimeout(function() {
                window.closeAddBranchModal();
            }, 350);

            // If on /branches administrative view, reload or update table
            if (window.location.pathname === '/branches' || window.location.pathname.indexOf('/branches') === 0) {
                setTimeout(function() { window.location.reload(); }, 600);
            }
        } else {
            var msg = (result.data && result.data.message) ? result.data.message : 'Failed to create branch.';
            var fieldErrors = (result.data && result.data.errors) ? result.data.errors : {};

            if (errAlert && errMsg) {
                var errHtml = '<strong>' + msg + '</strong>';
                var items = [];
                for (var key in fieldErrors) {
                    if (fieldErrors.hasOwnProperty(key)) {
                        items.push('&bull; ' + fieldErrors[key]);
                        var fInput = document.getElementById('add-branch-' + key.replace('_', '-')) || document.getElementById('add-branch-' + key);
                        if (fInput) fInput.style.borderColor = '#ef4444';
                        var fErr = document.getElementById('err-' + key);
                        if (fErr) { fErr.textContent = fieldErrors[key]; fErr.style.display = 'block'; }
                    }
                }
                if (items.length > 0) {
                    errHtml += '<div style="margin-top: 0.25rem;">' + items.join('<br>') + '</div>';
                }
                errMsg.innerHTML = errHtml;
                errAlert.style.display = 'block';
            }
        }
    })
    .catch(function(err) {
        if (btn) btn.disabled = false;
        if (spinner) spinner.style.display = 'none';
        if (submitLabel) submitLabel.textContent = 'Save Branch';
        if (errAlert && errMsg) {
            errMsg.textContent = 'Network or server error: ' + err.message;
            errAlert.style.display = 'block';
        }
    });

    return false;
};

window.refreshBranchDropdown = function(newBranch) {
    var fetchFn = (window.WTSBill && WTSBill.Http && WTSBill.Http.fetchWithCsrf) ? WTSBill.Http.fetchWithCsrf : (window.fetchWithCsrf || fetch);
    
    fetchFn('/api/branches', {
        headers: { 'Accept': 'application/json' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (!data || !data.success || !Array.isArray(data.branches)) return;

        var listEl = document.getElementById('header-branch-list');
        var countEl = document.getElementById('header-branch-count');
        if (countEl) countEl.textContent = data.branches.length;

        if (listEl) {
            var activeId = parseInt(data.active_branch_id || '0', 10);
            var html = '';

            data.branches.forEach(function(b) {
                var bId = parseInt(b.id, 10);
                var isCurrent = (bId === activeId);
                var isActive = (parseInt(b.is_active, 10) === 1);
                var safeName = (b.name || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                var code = b.branch_code || b.code || '';
                var city = b.city || '';

                html += '<div class="branch-menu-item-wrap" id="branch-item-wrap-' + bId + '" style="margin-bottom: 2px;">';
                if (isActive) {
                    html += '<form action="/branches/switch" method="POST" onsubmit="if(window.WTSBill && WTSBill.Branch){ WTSBill.Branch.switch(' + bId + ', \'' + safeName + '\'); return false; }" style="margin: 0; padding: 0; width: 100%;">';
                    html += '<input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">';
                    html += '<input type="hidden" name="branch_id" value="' + bId + '">';
                    html += '<button type="submit" class="branch-item ' + (isCurrent ? 'active' : '') + '" data-branch-id="' + bId + '" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-radius: 6px; border: none; background: ' + (isCurrent ? 'var(--primary-soft)' : 'transparent') + '; color: var(--text-main); cursor: pointer; text-align: left; transition: background 0.15s ease;" onmouseover="if(!this.classList.contains(\'active\')) this.style.background=\'var(--border-light)\';" onmouseout="if(!this.classList.contains(\'active\')) this.style.background=\'transparent\';">';
                    html += '<div class="branch-item-left" style="display: flex; align-items: center; gap: 0.65rem;">';
                    html += '<span class="branch-dot ' + (isCurrent ? 'active' : '') + '" style="width: 8px; height: 8px; border-radius: 50%; background: ' + (isCurrent ? 'var(--primary)' : 'var(--text-muted)') + ';"></span>';
                    html += '<div>';
                    html += '<div class="branch-item-name" style="font-size: 0.85rem; font-weight: 500; color: ' + (isCurrent ? 'var(--primary)' : 'var(--text-main)') + ';">' + escapeHtml(b.name) + '</div>';
                    html += '<div class="branch-item-code" style="font-size: 0.72rem; color: var(--text-muted);">';
                    html += '<span>' + escapeHtml(code) + '</span>';
                    if (city) {
                        html += ' &bull; <span>' + escapeHtml(city) + '</span>';
                    }
                    html += '</div></div></div>';
                    if (isCurrent) {
                        html += '<span class="badge badge-success" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; background: var(--success); color: #ffffff; border-radius: 4px; font-weight: 600;">ACTIVE</span>';
                    }
                    html += '</button></form>';
                } else {
                    html += '<div class="branch-item inactive" title="Inactive branch cannot be selected" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-radius: 6px; opacity: 0.6; cursor: not-allowed; background: transparent;">';
                    html += '<div class="branch-item-left" style="display: flex; align-items: center; gap: 0.65rem;">';
                    html += '<span class="branch-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted);"></span>';
                    html += '<div>';
                    html += '<div class="branch-item-name" style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">' + escapeHtml(b.name) + '</div>';
                    html += '<div class="branch-item-code" style="font-size: 0.72rem; color: var(--text-muted);">';
                    html += '<span>' + escapeHtml(code) + '</span>';
                    html += '</div></div></div>';
                    html += '<span class="badge badge-danger" style="font-size: 0.62rem; padding: 0.15rem 0.35rem;">INACTIVE</span>';
                    html += '</div>';
                }
                html += '</div>';
            });

            listEl.innerHTML = html;
        }
    })
    .catch(function(err) {
        console.error('Error refreshing branches list:', err);
    });
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.closeAddBranchModal();
    }
});

document.addEventListener('click', function(e) {
    var modal = document.getElementById('add-branch-modal');
    if (modal && e.target === modal) {
        window.closeAddBranchModal();
    }
    var menu = document.getElementById('branch-switch-menu');
    var btn = document.getElementById('branch-switch-btn');
    if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
        menu.classList.remove('show');
    }
});
// Enterprise Self-Contained Toggle Controller (Synchronous & resilient against asset cache)
window.toggleSidebar = function(e) {
    if (e) {
        if (e._sidebarHandled) return;
        e._sidebarHandled = true;
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    var now = Date.now();
    if (window._lastSidebarToggle && (now - window._lastSidebarToggle) < 200) {
        return;
    }
    window._lastSidebarToggle = now;

    var isMobile = window.innerWidth <= 768;
    var sidebar = document.getElementById('erp-sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    if (isMobile) {
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    } else {
        document.body.classList.toggle('sidebar-collapsed');
        try {
            var isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('wtsbill_sidebar_collapsed', isCollapsed ? '1' : '0');
        } catch(err) {}
    }
};

window.closeSidebar = function() {
    var sidebar = document.getElementById('erp-sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
};

window.toggleTheme = function(e) {
    if (e) {
        if (e._themeHandled) return;
        e._themeHandled = true;
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    var now = Date.now();
    if (window._lastThemeToggle && (now - window._lastThemeToggle) < 200) {
        return;
    }
    window._lastThemeToggle = now;

    var html = document.documentElement;
    var current = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', next);
    try {
        localStorage.setItem('wtsbill_theme', next);
    } catch(err) {}

    window.updateThemeUI(next);

    if (window.Toast && window.Toast.show) {
        window.Toast.show('Switched to ' + (next === 'dark' ? 'Dark' : 'Light') + ' theme', 'info', 1800);
    }
};

window.updateThemeUI = function(theme) {
    if (!theme) {
        try {
            theme = localStorage.getItem('wtsbill_theme') || document.documentElement.getAttribute('data-theme') || 'light';
        } catch(e) {
            theme = document.documentElement.getAttribute('data-theme') || 'light';
        }
    }
    document.documentElement.setAttribute('data-theme', theme);

    var sun = document.getElementById('theme-icon-sun');
    var moon = document.getElementById('theme-icon-moon');
    var label = document.getElementById('theme-toggle-label');
    var btn = document.getElementById('theme-toggle-btn');

    if (theme === 'dark') {
        if (sun) sun.style.display = 'inline-block';
        if (moon) moon.style.display = 'none';
        if (label) label.textContent = 'Dark';
        if (btn) btn.setAttribute('title', 'Switch to Light Mode');
    } else {
        if (sun) sun.style.display = 'none';
        if (moon) moon.style.display = 'inline-block';
        if (label) label.textContent = 'Light';
        if (btn) btn.setAttribute('title', 'Switch to Dark Mode');
    }
};

// Immediate execution to set correct icon and label on initial paint
window.updateThemeUI();
</script>
