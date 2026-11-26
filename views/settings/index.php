<?php
/**
 * WTSBill ERP — Company Profile & Branding View
 * Unified management page for Tenant Organization Profile, GSTIN Master, Operating Branches,
 * and Document Branding & Assets (Logo, Signature, Stamp, Invoice Display Settings).
 *
 * @var array $company
 * @var array $branches
 * @var array $allUsers
 * @var array $branding
 * @var int $activeBranchId
 * @var bool $isAdmin
 * @var string $title
 * @var string $currentUri
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

// Branding Defaults
$b = $branding ?? [];
$selectedBranchId = $selectedBranchId ?? null;
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #051F20);">Company Profile &amp; Branding</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Unified configuration for organization details, GSTIN master, operating branches, and document branding assets
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-secondary" onclick="openEditCompanyModal()" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; font-size: 0.85rem; border: 1px solid #8EB69B; border-radius: 6px; background: #FFFFFF; color: #051F20;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>Edit Profile</span>
            </button>
            <button type="button" class="btn btn-primary" onclick="openCreateBranchModal()" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; font-size: 0.85rem; background: #235347; color: #FFFFFF; border: none; border-radius: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>+ Register Branch</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Settings Top Navigation Tabs (Audit, Communication, and Backup moved OUT into separate modules) -->
<div class="tabs-nav" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #8EB69B; padding-bottom: 0.5rem; flex-wrap: wrap;">
    <a href="/settings" class="btn btn-primary active" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; background: #235347; color: #FFFFFF;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Company Profile &amp; Branding</span>
    </a>
    <a href="/users" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
        <span>Users &amp; Roles</span>
    </a>
    <a href="/settings/communication" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        <span>Communication</span>
    </a>
    <a href="/settings/backups" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Backup &amp; Disaster Recovery</span>
    </a>
    <a href="/settings/audit-logs" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        <span>Audit Trail</span>
    </a>
</div>

<!-- ======================================================== -->
<!-- SECTION 1: ORGANIZATION PROFILE & OPERATING BRANCHES     -->
<!-- ======================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">

    <!-- 1. Tenant Organization Profile Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div>
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </span>
                    <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">Company Information &amp; GSTIN</h3>
                </div>
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditCompanyModal()" title="Edit Organization Details" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border: 1px solid #235347; color: #235347; background: transparent; border-radius: 4px; cursor: pointer;">
                        Edit Profile
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Legal Entity Name</label>
                    <div id="disp-company-legal-name" style="font-size: 1.15rem; font-weight: 700; color: #051F20; margin-top: 0.15rem;"><?= e($compName) ?></div>
                    <?php if (!empty($compTrade) && $compTrade !== $compName): ?>
                        <div style="font-size: 0.85rem; color: #4B5563;">Trade Name: <?= e($compTrade) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">GSTIN</label>
                        <div id="disp-company-gstin" style="margin-top: 0.15rem;">
                            <?php if (!empty($compGstin)): ?>
                                <code style="font-size: 0.9rem; font-weight: 600; color: #235347; background: #DAF1DE; padding: 2px 6px; border-radius: 4px;"><?= e($compGstin) ?></code>
                                <div style="font-size: 0.78rem; color: #4B5563; margin-top: 0.15rem;">State: <?= e($compState) ?> (<?= e($compStateCode) ?>)</div>
                            <?php else: ?>
                                <span style="color: #4B5563;">&mdash;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">PAN</label>
                        <div id="disp-company-pan" style="margin-top: 0.15rem;">
                            <?php if (!empty($compPan)): ?>
                                <code style="font-size: 0.9rem; font-weight: 600; color: #051F20; background: #F1F5F9; padding: 2px 6px; border-radius: 4px;"><?= e($compPan) ?></code>
                            <?php else: ?>
                                <span style="color: #4B5563;">&mdash;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Phone / Contact</label>
                        <div style="font-size: 0.88rem; color: #051F20; margin-top: 0.15rem;"><?= e($compPhone ?: '—') ?></div>
                    </div>
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Official Email</label>
                        <div style="font-size: 0.88rem; color: #051F20; margin-top: 0.15rem;"><?= e($compEmail ?: '—') ?></div>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Registered Office Address</label>
                    <div style="font-size: 0.88rem; color: #051F20; margin-top: 0.15rem; line-height: 1.4;">
                        <?= nl2br(e($compAddress ?: '—')) ?><br>
                        <?= e($compCity) ?>, <?= e($compState) ?> &mdash; <?= e($compPincode) ?>
                    </div>
                </div>

                <div style="margin-bottom: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">MSME / Udyam No</label>
                        <div style="font-size: 0.85rem; color: #051F20; margin-top: 0.15rem;"><?= e($compMsme ?: '—') ?></div>
                    </div>
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Website</label>
                        <div style="font-size: 0.85rem; margin-top: 0.15rem;">
                            <?php if ($compWebsite): ?>
                                <a href="<?= e($compWebsite) ?>" target="_blank" rel="noopener" style="color: #235347; text-decoration: underline;"><?= e($compWebsite) ?></a>
                            <?php else: ?>
                                <span style="color: #4B5563;">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1rem; padding: 0.75rem 0.85rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <label style="color: #4B5563; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Merchant UPI Payment QR</label>
                        <div style="font-size: 0.92rem; font-weight: 700; color: #235347; font-family: monospace; margin-top: 0.15rem;">
                            <?= e($compUpi ?: 'sashted@okhdfcbank') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #64748B;">Payee: <strong><?= e($compUpiName ?: 'Dnyanesh Sashte') ?></strong></div>
                    </div>
                    <div style="background: #ffffff; padding: 4px; border: 1px solid #CBD5E1; border-radius: 6px; display: inline-flex; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                        <img src="/uploads/branding/company_1_upi_qr.png" alt="UPI QR" style="width: 52px; height: 52px; object-fit: contain;">
                    </div>
                </div>

                <div style="padding-top: 0.75rem; border-top: 1px dashed #CBD5E1; font-size: 0.8rem; color: #4B5563; display: flex; justify-content: space-between;">
                    <span>Default Currency: <strong><?= e($compCurrency) ?> (₹)</strong></span>
                    <span>Financial Year: <strong>01 Apr – 31 Mar</strong></span>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div class="card-footer" style="padding: 0.75rem 1.25rem; border-top: 1px solid rgba(142,182,155,0.3); background: #F8FAFC; text-align: right; border-radius: 0 0 10px 10px;">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditCompanyModal()" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; border: 1px solid #235347; color: #235347; background: transparent; border-radius: 4px; cursor: pointer;">
                    Update Organization Details
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. Operating Branches Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div>
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </span>
                    <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">Operating Branches &amp; Locations</h3>
                </div>
                <span class="badge" style="background: #235347; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                    <?= count($branches) ?> Active
                </span>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <?php if (empty($branches)): ?>
                    <div style="text-align: center; padding: 2rem; color: #4B5563;">
                        No operating branches registered yet.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($branches as $branch): ?>
                            <?php 
                            $bId = (int)$branch['id'];
                            $isCurrent = ($bId === $currentBranchId);
                            $isMain = ((int)($branch['is_main_branch'] ?? 0) === 1);
                            ?>
                            <div style="padding: 0.85rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <strong style="color: #051F20; font-size: 0.95rem;"><?= e($branch['name']) ?></strong>
                                        <span style="font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600; background: <?= $isMain ? '#235347' : '#8EB69B' ?>; color: #FFFFFF;">
                                            <?= $isMain ? 'MAIN HQ' : 'REGIONAL' ?>
                                        </span>
                                        <?php if ($isCurrent): ?>
                                            <span style="font-size: 0.62rem; padding: 0.15rem 0.35rem; border-radius: 4px; font-weight: 600; background: #10B981; color: #FFFFFF;">ACTIVE</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isAdmin): ?>
                                        <button type="button" class="btn btn-sm" onclick="openEditBranchModal(<?= $bId ?>)" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; border: 1px solid #CBD5E1; background: #FFFFFF; color: #051F20; border-radius: 4px; cursor: pointer;">
                                            Edit
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #4B5563; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
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
            <div class="card-footer" style="padding: 0.75rem 1.25rem; border-top: 1px solid rgba(142,182,155,0.3); background: #F8FAFC; text-align: right; border-radius: 0 0 10px 10px;">
                <button type="button" class="btn btn-sm btn-primary" onclick="openCreateBranchModal()" style="display: inline-flex; align-items: center; gap: 0.35rem; background: #235347; color: #FFFFFF; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Add Branch</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- SECTION 2: COMPANY BRANDING ASSETS (LOGO, SIGNATURE, STAMP) -->
<!-- ======================================================== -->
<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 2px solid #DAF1DE; padding-top: 1.5rem;">
    <div>
        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #051F20;">Company Branding &amp; Visual Identity</h2>
        <p style="margin: 0.2rem 0 0 0; color: #4B5563; font-size: 0.82rem;">Centralized asset management for Company Logo, Authorized Signature, and Stamp across all layouts and PDFs</p>
    </div>
    <!-- Branch Scope Selector -->
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="branding-branch-select" style="font-size: 0.82rem; font-weight: 600; color: #051F20;">Branding Scope:</label>
        <select id="branding-branch-select" onchange="window.location.href='/settings' + (this.value ? '?branch_id=' + this.value : '')" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; border: 1px solid #8EB69B; border-radius: 6px; background: #FFFFFF; color: #051F20;">
            <option value="" <?= empty($selectedBranchId) ? 'selected' : '' ?>>🏢 Company-Wide Default</option>
            <?php foreach ($branches as $br): ?>
                <option value="<?= (int)$br['id'] ?>" <?= ($selectedBranchId === (int)$br['id']) ? 'selected' : '' ?>>
                    📍 Branch: <?= e($br['name']) ?> (<?= e($br['branch_code'] ?? $br['code'] ?? 'BR') ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">

    <!-- CARD 1: COMPANY LOGO -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                </span>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">1. Company Logo</h3>
            </div>
            <span class="badge" style="background: <?= ($b['is_custom_logo'] ?? false) ? '#235347' : '#8EB69B' ?>; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= ($b['is_custom_logo'] ?? false) ? 'Custom Logo' : 'Default Logo' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Logo Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-logo" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <img id="img-preview-logo" src="<?= e($b['logo_url'] ?? '/assets/images/logo.png') ?>" alt="Company Logo" style="max-height: 100px; max-width: 90%; object-fit: contain; background: transparent;">
                    </div>
                </div>

                <div style="font-size: 0.76rem; color: #4B5563; line-height: 1.4; margin-bottom: 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.75rem; border-radius: 6px;">
                    <strong style="color: #051F20;">Recommended:</strong> Square or rectangular transparent PNG, WebP, or SVG.<br>
                    <strong>Min dimensions:</strong> 200 × 200px. <strong>Max size:</strong> 2 MB.<br>
                    <span style="color: #235347; font-weight: 500;">✓ Updates in Sidebar, Header, Login, Invoices &amp; Quotations.</span>
                </div>
            </div>

            <!-- Upload Form -->
            <form id="form-upload-logo" method="POST" action="/settings/branding/upload" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="type" value="logo">
                <input type="hidden" name="branch_id" value="<?= e((string)($selectedBranchId ?? '')) ?>">

                <div style="margin-bottom: 0.75rem;">
                    <input type="file" id="file-logo" name="asset_file" accept=".png,.jpg,.jpeg,.webp,.svg" onchange="previewSelectedFile(this, 'img-preview-logo')" style="display: none;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('file-logo').click()" style="width: 100%; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20; padding: 0.5rem; font-size: 0.84rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span id="label-file-logo">Choose New Logo File...</span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-logo" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if ($b['is_custom_logo'] ?? false): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveAsset('logo', '<?= e((string)($selectedBranchId ?? '')) ?>')" style="padding: 0.55rem 0.85rem; border: 1px solid #EF4444; color: #EF4444; background: transparent; border-radius: 6px; font-size: 0.85rem; cursor: pointer;" title="Reset to default logo">
                            Reset
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD 2: AUTHORIZED SIGNATURE -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                </span>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">2. Authorized Signature</h3>
            </div>
            <span class="badge" style="background: (!empty($b['signature_url'])) ? '#235347' : '#94A3B8'; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= (!empty($b['signature_url'])) ? 'Configured' : 'Not Set' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Signature Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-sig" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <?php if (!empty($b['signature_url'])): ?>
                            <img id="img-preview-sig" src="<?= e($b['signature_url']) ?>" alt="Signature" style="max-height: 80px; max-width: 90%; object-fit: contain;">
                        <?php else: ?>
                            <div id="img-preview-sig-empty" style="text-align: center; color: #94A3B8; font-size: 0.8rem;">
                                <span>✍️ No signature uploaded yet</span>
                            </div>
                            <img id="img-preview-sig" src="" alt="Signature" style="display: none; max-height: 80px; max-width: 90%; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                </div>

                <div style="font-size: 0.76rem; color: #4B5563; line-height: 1.4; margin-bottom: 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.75rem; border-radius: 6px;">
                    <strong style="color: #051F20;">Recommended:</strong> Clean dark signature on transparent PNG.<br>
                    <strong>Max size:</strong> 2 MB.<br>
                    <span style="color: #235347; font-weight: 500;">✓ Renders dynamically in the Signatory block of bills &amp; quotes.</span>
                </div>
            </div>

            <!-- Upload Form -->
            <form id="form-upload-sig" method="POST" action="/settings/branding/upload" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="type" value="signature">
                <input type="hidden" name="branch_id" value="<?= e((string)($selectedBranchId ?? '')) ?>">

                <div style="margin-bottom: 0.75rem;">
                    <input type="file" id="file-sig" name="asset_file" accept=".png,.jpg,.jpeg,.webp" onchange="previewSelectedFile(this, 'img-preview-sig', 'img-preview-sig-empty')" style="display: none;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('file-sig').click()" style="width: 100%; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20; padding: 0.5rem; font-size: 0.84rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span id="label-file-sig"><?= !empty($b['signature_url']) ? 'Replace Signature File...' : 'Choose Signature Image...' ?></span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-sig" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if (!empty($b['signature_url'])): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveAsset('signature', '<?= e((string)($selectedBranchId ?? '')) ?>')" style="padding: 0.55rem 0.85rem; border: 1px solid #EF4444; color: #EF4444; background: transparent; border-radius: 6px; font-size: 0.85rem; cursor: pointer;" title="Remove signature">
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD 3: COMPANY STAMP / SEAL -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path></svg>
                </span>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">3. Company Official Stamp</h3>
            </div>
            <span class="badge" style="background: (!empty($b['stamp_url'])) ? '#235347' : '#94A3B8'; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= (!empty($b['stamp_url'])) ? 'Configured' : 'Not Set' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Stamp Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-stamp" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <?php if (!empty($b['stamp_url'])): ?>
                            <img id="img-preview-stamp" src="<?= e($b['stamp_url']) ?>" alt="Company Stamp" style="max-height: 90px; max-width: 90%; object-fit: contain;">
                        <?php else: ?>
                            <div id="img-preview-stamp-empty" style="text-align: center; color: #94A3B8; font-size: 0.8rem;">
                                <span>⭕ No stamp uploaded yet</span>
                            </div>
                            <img id="img-preview-stamp" src="" alt="Company Stamp" style="display: none; max-height: 90px; max-width: 90%; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                </div>

                <div style="font-size: 0.76rem; color: #4B5563; line-height: 1.4; margin-bottom: 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.75rem; border-radius: 6px;">
                    <strong style="color: #051F20;">Recommended:</strong> Circular or oval transparent PNG seal.<br>
                    <strong>Max size:</strong> 2 MB.<br>
                    <span style="color: #235347; font-weight: 500;">✓ Pairs neatly with the signature on invoices, quotes, &amp; POs.</span>
                </div>
            </div>

            <!-- Upload Form -->
            <form id="form-upload-stamp" method="POST" action="/settings/branding/upload" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="type" value="stamp">
                <input type="hidden" name="branch_id" value="<?= e((string)($selectedBranchId ?? '')) ?>">

                <div style="margin-bottom: 0.75rem;">
                    <input type="file" id="file-stamp" name="asset_file" accept=".png,.jpg,.jpeg,.webp" onchange="previewSelectedFile(this, 'img-preview-stamp', 'img-preview-stamp-empty')" style="display: none;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('file-stamp').click()" style="width: 100%; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20; padding: 0.5rem; font-size: 0.84rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span id="label-file-stamp"><?= !empty($b['stamp_url']) ? 'Replace Stamp File...' : 'Choose Stamp Image...' ?></span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-stamp" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if (!empty($b['stamp_url'])): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveAsset('stamp', '<?= e((string)($selectedBranchId ?? '')) ?>')" style="padding: 0.55rem 0.85rem; border: 1px solid #EF4444; color: #EF4444; background: transparent; border-radius: 6px; font-size: 0.85rem; cursor: pointer;" title="Remove stamp">
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD 4: UPI PAYMENT QR CODE -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
        <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #DAF1DE; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: #235347; color: #FFFFFF;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">4. UPI Payment QR Code</h3>
            </div>
            <span class="badge" style="background: <?= (!empty($b['qr_code_url'])) ? '#235347' : '#8EB69B' ?>; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= (!empty($b['qr_code_url'])) ? 'Custom QR Active' : 'Dynamic Auto-Generated' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- QR Code Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Scannable QR:</div>
                    <div style="height: 120px; background: #FFFFFF; border: 1.5px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0.5rem; overflow: hidden;">
                        <?php if (!empty($b['qr_code_url'])): ?>
                            <img id="img-preview-qr" src="<?= e($b['qr_code_url']) ?>" alt="UPI QR Code" style="max-height: 105px; max-width: 90%; object-fit: contain; border-radius: 4px;">
                        <?php else: ?>
                            <div id="img-preview-qr-empty" style="text-align: center; color: #64748B; font-size: 0.8rem;">
                                <span>📱 Auto-generates dynamic UPI QR</span>
                            </div>
                            <img id="img-preview-qr" src="" alt="UPI QR Code" style="display: none; max-height: 105px; max-width: 90%; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                </div>

                <div style="font-size: 0.76rem; color: #4B5563; line-height: 1.4; margin-bottom: 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.75rem; border-radius: 6px;">
                    <strong style="color: #051F20;">Active VPA:</strong> <code id="preview-active-vpa" style="background: #E2E8F0; padding: 1px 4px; border-radius: 3px;"><?= e($company['upi_id'] ?? '') ?></code><br>
                    <strong>Payee Name:</strong> <span id="preview-payee-name"><?= e($company['upi_display_name'] ?? '') ?></span><br>
                    <span style="color: #235347; font-weight: 500;">✓ Scannable by Google Pay, PhonePe, Paytm &amp; all BHIM UPI apps.</span>
                </div>
                <div id="qr-decode-status" style="display: none; font-size: 0.75rem; padding: 0.5rem 0.65rem; border-radius: 6px; margin-bottom: 0.75rem; line-height: 1.35;"></div>
            </div>

            <!-- Upload Form -->
            <form id="form-upload-qr" method="POST" action="/settings/branding/upload" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="type" value="qr">
                <input type="hidden" name="branch_id" value="<?= e((string)($selectedBranchId ?? '')) ?>">
                <input type="hidden" id="decoded-upi-id" name="decoded_upi_id" value="">
                <input type="hidden" id="decoded-upi-name" name="decoded_upi_name" value="">

                <div style="margin-bottom: 0.75rem;">
                    <input type="file" id="file-qr" name="asset_file" accept=".png,.jpg,.jpeg,.webp" onchange="handleQrUploadSelection(this)" style="display: none;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('file-qr').click()" style="width: 100%; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20; padding: 0.5rem; font-size: 0.84rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span id="label-file-qr"><?= !empty($b['qr_code_url']) ? 'Replace QR Image...' : 'Choose QR Image...' ?></span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-qr" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if (!empty($b['qr_code_url'])): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveAsset('qr', '<?= e((string)($selectedBranchId ?? '')) ?>')" style="padding: 0.55rem 0.85rem; border: 1px solid #EF4444; color: #EF4444; background: transparent; border-radius: 6px; font-size: 0.85rem; cursor: pointer;" title="Reset QR to dynamic generator">
                            Reset
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- SECTION 3: INVOICE BRANDING & DOCUMENT DISPLAY SETTINGS   -->
<!-- ======================================================== -->
<div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
    <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #0B2B26; color: #FFFFFF; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8EB69B" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #DAF1DE;">Invoice Branding &amp; Document Display Settings</h3>
        </div>
        <span style="font-size: 0.75rem; color: #8EB69B;">Fine-tune sizing and document-level inclusion rules</span>
    </div>

    <form method="POST" action="/settings/branding/settings" style="padding: 1.5rem;">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="branch_id" value="<?= e((string)($selectedBranchId ?? '')) ?>">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- Sizing: Signature -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.1rem; border-radius: 8px;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700; color: #163832; display: flex; align-items: center; gap: 0.4rem;">
                    <span>✍️</span> Signature Sizing &amp; Position
                </h4>
                <div style="display: flex; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Width (px):</label>
                        <input type="number" name="signature_width" min="50" max="300" value="<?= (int)($b['signature_width'] ?? 140) ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Height (px):</label>
                        <input type="number" name="signature_height" min="20" max="150" value="<?= (int)($b['signature_height'] ?? 60) ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Alignment in Signatory Block:</label>
                    <select name="signature_position" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                        <option value="right" <?= (($b['signature_position'] ?? 'right') === 'right') ? 'selected' : '' ?>>Right Aligned (Standard)</option>
                        <option value="center" <?= (($b['signature_position'] ?? '') === 'center') ? 'selected' : '' ?>>Center Aligned</option>
                        <option value="left" <?= (($b['signature_position'] ?? '') === 'left') ? 'selected' : '' ?>>Left Aligned</option>
                    </select>
                </div>
            </div>

            <!-- Sizing: Stamp -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.1rem; border-radius: 8px;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700; color: #163832; display: flex; align-items: center; gap: 0.4rem;">
                    <span>⭕</span> Stamp Sizing &amp; Position
                </h4>
                <div style="display: flex; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Width (px):</label>
                        <input type="number" name="stamp_width" min="40" max="200" value="<?= (int)($b['stamp_width'] ?? 90) ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Height (px):</label>
                        <input type="number" name="stamp_height" min="40" max="200" value="<?= (int)($b['stamp_height'] ?? 90) ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Alignment in Signatory Block:</label>
                    <select name="stamp_position" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                        <option value="right" <?= (($b['stamp_position'] ?? 'right') === 'right') ? 'selected' : '' ?>>Right Aligned (Standard)</option>
                        <option value="center" <?= (($b['stamp_position'] ?? '') === 'center') ? 'selected' : '' ?>>Center Aligned</option>
                        <option value="left" <?= (($b['stamp_position'] ?? '') === 'left') ? 'selected' : '' ?>>Left Aligned</option>
                    </select>
                </div>
            </div>

            <!-- Document Visibility Toggles -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.1rem; border-radius: 8px;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700; color: #163832; display: flex; align-items: center; gap: 0.4rem;">
                    <span>📄</span> Document Inclusions
                </h4>
                <div style="font-size: 0.8rem; margin-bottom: 0.6rem;">
                    <strong style="color: #051F20;">Authorized Signature:</strong>
                    <div style="margin: 0.35rem 0 0.5rem 0.2rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_signature_invoice" value="1" <?= (!empty($b['show_signature_invoice'])) ? 'checked' : '' ?>>
                            <span>Show on Sales Invoices</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_signature_quotation" value="1" <?= (!empty($b['show_signature_quotation'])) ? 'checked' : '' ?>>
                            <span>Show on Quotations</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_signature_purchase" value="1" <?= (!empty($b['show_signature_purchase'])) ? 'checked' : '' ?>>
                            <span>Show on Purchase Orders</span>
                        </label>
                    </div>
                </div>

                <div style="font-size: 0.8rem;">
                    <strong style="color: #051F20;">Official Stamp:</strong>
                    <div style="margin: 0.35rem 0 0 0.2rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_invoice" value="1" <?= (!empty($b['show_stamp_invoice'])) ? 'checked' : '' ?>>
                            <span>Show on Sales Invoices</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_quotation" value="1" <?= (!empty($b['show_stamp_quotation'])) ? 'checked' : '' ?>>
                            <span>Show on Quotations</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_purchase" value="1" <?= (!empty($b['show_stamp_purchase'])) ? 'checked' : '' ?>>
                            <span>Show on Purchase Orders</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #E2E8F0; padding-top: 1rem;">
            <button type="submit" class="btn btn-primary" style="background: #235347; color: #FFFFFF; border: none; padding: 0.6rem 1.4rem; font-size: 0.88rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Save Invoice Branding Settings</span>
            </button>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- MODALS: EDIT COMPANY, REGISTER BRANCH, REMOVE ASSET      -->
<!-- ======================================================== -->

<!-- Modal for Remove Confirmation -->
<div id="modal-remove-asset" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: #FFFFFF; width: 100%; max-width: 420px; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 1rem 1.25rem; background: #FEF2F2; border-bottom: 1px solid #FEE2E2; display: flex; align-items: center; gap: 0.6rem;">
            <span style="color: #DC2626; font-size: 1.25rem;">⚠️</span>
            <h4 style="margin: 0; color: #991B1B; font-size: 1rem; font-weight: 700;">Confirm Asset Removal</h4>
        </div>
        <div style="padding: 1.25rem; font-size: 0.88rem; color: #374151;">
            <p style="margin: 0 0 0.5rem 0;" id="modal-remove-desc">Are you sure you want to remove this branding asset?</p>
            <p style="margin: 0; font-size: 0.78rem; color: #6B7280;">This will revert the asset to default across all invoices, quotations, and documents.</p>
        </div>
        <form id="form-remove-asset" method="POST" action="/settings/branding/remove">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="remove-asset-type" name="type" value="">
            <input type="hidden" id="remove-asset-branch" name="branch_id" value="">
            <div style="padding: 0.75rem 1.25rem; background: #F9FAFB; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 0.65rem;">
                <button type="button" class="btn btn-secondary" onclick="closeRemoveModal()" style="padding: 0.5rem 1rem; border: 1px solid #D1D5DB; background: #FFFFFF; color: #374151; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-danger" style="padding: 0.5rem 1rem; border: none; background: #DC2626; color: #FFFFFF; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Yes, Remove Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 1: Edit Organization Profile -->
<div id="company-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Organization Profile</h3>
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

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Official Email</label>
                        <input type="email" id="company-email" name="email" class="form-control" value="<?= e($compEmail) ?>" placeholder="info@wtsindia.co.in">
                    </div>
                    <div>
                        <label class="form-label">Official Phone</label>
                        <input type="text" id="company-phone" name="phone" class="form-control" value="<?= e($compPhone) ?>" placeholder="020 47252364">
                    </div>
                </div>

                <div>
                    <label class="form-label">Premises / Address Line 1</label>
                    <input type="text" id="company-address1" name="address_line1" class="form-control" value="<?= e($comp['address_line1'] ?? '') ?>" placeholder="Flat/Shop, Building, Commercial Park">
                </div>

                <div>
                    <label class="form-label">Road / Area / Address Line 2</label>
                    <input type="text" id="company-address2" name="address_line2" class="form-control" value="<?= e($comp['address_line2'] ?? '') ?>" placeholder="Street, Landmark, Area">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" id="company-city" name="city" class="form-control" value="<?= e($compCity) ?>" placeholder="Pune">
                    </div>
                    <div>
                        <label class="form-label">State</label>
                        <input type="text" id="company-state" name="state" class="form-control" value="<?= e($compState) ?>" placeholder="Maharashtra">
                    </div>
                    <div>
                        <label class="form-label">State Code</label>
                        <input type="text" id="company-state-code" name="state_code" class="form-control" value="<?= e($compStateCode) ?>" maxlength="2" placeholder="27">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Pincode</label>
                        <input type="text" id="company-pincode" name="pincode" class="form-control" value="<?= e($compPincode) ?>" maxlength="10" placeholder="411041">
                    </div>
                    <div>
                        <label class="form-label">MSME / Udyam No</label>
                        <input type="text" id="company-msme" name="msme_no" class="form-control" value="<?= e($compMsme) ?>" placeholder="UDYAM-MH-26-0734598">
                    </div>
                    <div>
                        <label class="form-label">Website</label>
                        <input type="url" id="company-website" name="website" class="form-control" value="<?= e($compWebsite) ?>" placeholder="http://www.wtsindia.co.in">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">UPI ID for Invoices (VPA)</label>
                        <input type="text" id="company-upi" name="upi_id" class="form-control" value="<?= e($compUpi) ?>" placeholder="e.g. sashted@okhdfcbank">
                    </div>
                    <div>
                        <label class="form-label">UPI Payee Display Name</label>
                        <input type="text" id="company-upi-name" name="upi_display_name" class="form-control" value="<?= e($compUpiName) ?>" placeholder="e.g. Dnyanesh Sashte">
                    </div>
                </div>

                <div>
                    <label class="form-label">Invoice Payment Instructions</label>
                    <textarea id="company-payment-instructions" name="payment_instructions" class="form-control" rows="2" placeholder="e.g. Bank details, NEFT/RTGS accounts, UPI instructions"><?= e($compPaymentInstructions) ?></textarea>
                </div>

                <div>
                    <label class="form-label">Default Terms &amp; Conditions</label>
                    <textarea id="company-terms" name="terms_and_conditions" class="form-control" rows="3" placeholder="Standard commercial terms printed at the bottom of customer invoices"><?= e($compTerms) ?></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="WTSBill.Modal.close('company-modal')">Cancel</button>
                <button type="submit" id="company-submit-btn" class="btn btn-primary">Save Organization Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Create / Edit Operating Branch -->
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

                <div style="display: flex; gap: 1.5rem; align-items: center; margin-top: 0.5rem; padding: 0.75rem; background: var(--bg-surface, #F8FAFC); border-radius: 6px; border: 1px solid #E2E8F0;">
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
// Auto-derive PAN and State Code from GSTIN input
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

        if (!result.ok || !result.data.success) {
            errBox.textContent = result.data.message || 'Failed to update company settings.';
            errBox.style.display = 'block';
            try { errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch(e) {}
            if (window.Toast && window.Toast.error) {
                window.Toast.error(errBox.textContent);
            }
            return;
        }

        WTSBill.Modal.close('company-modal');
        window.location.reload();
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Save Organization Profile';
        errBox.textContent = 'Network or server error occurred.';
        errBox.style.display = 'block';
    });
}

function openCreateBranchModal() {
    document.getElementById('branch-modal-title').textContent = 'Register Operating Branch';
    document.getElementById('branch-form').reset();
    document.getElementById('branch-id').value = '';
    document.getElementById('branch-is-active').checked = true;
    document.getElementById('branch-is-main').checked = false;
    var errBox = document.getElementById('branch-form-error');
    if (errBox) errBox.style.display = 'none';
    WTSBill.Modal.open('branch-modal');
}

function openEditBranchModal(id) {
    document.getElementById('branch-modal-title').textContent = 'Edit Operating Branch';
    var errBox = document.getElementById('branch-form-error');
    if (errBox) errBox.style.display = 'none';

    WTSBill.Http.fetchWithCsrf('/branches/' + id, { headers: { 'Accept': 'application/json' } })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.branch) {
                var b = data.branch;
                document.getElementById('branch-id').value = b.id || '';
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
                document.getElementById('branch-is-main').checked = parseInt(b.is_main_branch) === 1;
                document.getElementById('branch-is-active').checked = parseInt(b.is_active) === 1;
                WTSBill.Modal.open('branch-modal');
            }
        })
        .catch(function() {
            WTSBill.Modal.open('branch-modal');
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

        if (!result.ok || !result.data.success) {
            errBox.textContent = result.data.message || 'Failed to save branch.';
            errBox.style.display = 'block';
            return;
        }

        WTSBill.Modal.close('branch-modal');
        window.location.reload();
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Save Branch';
        errBox.textContent = 'Network or server error occurred.';
        errBox.style.display = 'block';
    });
}

// Branding Preview & Remove Controls
function previewSelectedFile(input, imgId, emptyId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById(imgId);
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (emptyId) {
                var emptyEl = document.getElementById(emptyId);
                if (emptyEl) emptyEl.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function handleQrUploadSelection(input) {
    previewSelectedFile(input, 'img-preview-qr', 'img-preview-qr-empty');
    if (!input.files || !input.files[0]) return;

    var statusEl = document.getElementById('qr-decode-status');
    var labelEl = document.getElementById('label-file-qr');
    if (labelEl) labelEl.textContent = input.files[0].name;

    if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.style.background = '#F0FDF4';
        statusEl.style.border = '1px solid #8EB69B';
        statusEl.style.color = '#163832';
        statusEl.innerHTML = 'Scanning QR image for UPI details...';
    }

    if (window.WTSBill && window.WTSBill.QRScanner && typeof window.WTSBill.QRScanner.decodeFile === 'function') {
        window.WTSBill.QRScanner.decodeFile(
            input.files[0],
            function(extracted) {
                if (extracted && extracted.kind === 'upi' && extracted.details) {
                    var vpa = extracted.details.vpa || '';
                    var pname = extracted.details.name || '';
                    var upiInput = document.getElementById('company-upi');
                    var nameInput = document.getElementById('company-upi-name');
                    var decUpi = document.getElementById('decoded-upi-id');
                    var decName = document.getElementById('decoded-upi-name');
                    var prevVpa = document.getElementById('preview-active-vpa');
                    var prevName = document.getElementById('preview-payee-name');

                    if (vpa && upiInput) upiInput.value = vpa;
                    if (pname && nameInput) nameInput.value = pname;
                    if (decUpi) decUpi.value = vpa;
                    if (decName) decName.value = pname;
                    if (vpa && prevVpa) prevVpa.textContent = vpa;
                    if (pname && prevName) prevName.textContent = pname;

                    if (statusEl) {
                        statusEl.style.background = '#DAF1DE';
                        statusEl.style.border = '1px solid #235347';
                        statusEl.style.color = '#051F20';
                        statusEl.innerHTML = '<strong>✓ QR Data Extracted:</strong><br>' +
                            'Payee Name: <strong>' + (pname || 'Not specified') + '</strong><br>' +
                            'UPI ID: <strong>' + (vpa || 'Not specified') + '</strong>';
                    }
                    if (window.Toast && window.Toast.success) {
                        window.Toast.success('QR decoded: ' + (pname || vpa) + ' auto-filled!');
                    }
                } else {
                    if (statusEl) {
                        statusEl.style.background = '#FEF3C7';
                        statusEl.style.border = '1px solid #F59E0B';
                        statusEl.style.color = '#92400E';
                        statusEl.innerHTML = 'QR decoded: <code>' + (extracted ? extracted.value : '') + '</code>';
                    }
                }
            },
            function(errMsg) {
                if (statusEl) {
                    statusEl.style.background = '#FEF2F2';
                    statusEl.style.border = '1px solid #FCA5A5';
                    statusEl.style.color = '#991B1B';
                    statusEl.textContent = 'Could not auto-extract UPI details (' + errMsg + '). You can still upload the image.';
                }
            }
        );
    }
}

function confirmRemoveAsset(type, branchId) {
    var modal = document.getElementById('modal-remove-asset');
    var inputType = document.getElementById('remove-asset-type');
    var inputBranch = document.getElementById('remove-asset-branch');
    var desc = document.getElementById('modal-remove-desc');

    if (inputType) inputType.value = type;
    if (inputBranch) inputBranch.value = branchId || '';

    var names = { logo: 'Company Logo', signature: 'Authorized Signature', stamp: 'Company Stamp', qr: 'UPI Payment QR Code' };
    if (desc) desc.textContent = 'Are you sure you want to remove the ' + (names[type] || type) + '?';

    if (modal) modal.style.display = 'flex';
}

function closeRemoveModal() {
    var modal = document.getElementById('modal-remove-asset');
    if (modal) modal.style.display = 'none';
}
</script>
