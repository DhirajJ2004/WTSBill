<?php
/**
 * WTSBill ERP — Centralized Company Branding Settings View
 * 
 * @var array $branding
 * @var array $branches
 * @var int|null $selectedBranchId
 * @var bool $isAdmin
 * @var string $title
 * @var string $currentUri
 */

use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$branding = $branding ?? [];
$branches = $branches ?? [];
$selectedBranchId = $selectedBranchId ?? null;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #051F20);">Company Branding Management</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Centralized control for Company Logo, Authorized Signature, and Official Stamp across all pages, PDFs, and print layouts
        </p>
    </div>

    <!-- Branch Override Switcher -->
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <label for="branding-branch-select" style="font-size: 0.82rem; font-weight: 600; color: var(--text-primary, #051F20);">Branding Scope:</label>
        <select id="branding-branch-select" class="form-control" onchange="window.location.href='/settings/branding' + (this.value ? '?branch_id=' + this.value : '')" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; border: 1px solid #8EB69B; border-radius: 6px; background: #FFFFFF; color: #051F20;">
            <option value="" <?= empty($selectedBranchId) ? 'selected' : '' ?>>🏢 Company-Wide Default (All Branches)</option>
            <?php foreach ($branches as $br): ?>
                <option value="<?= (int)$br['id'] ?>" <?= ($selectedBranchId === (int)$br['id']) ? 'selected' : '' ?>>
                    📍 Branch: <?= e($br['name']) ?> (<?= e($br['branch_code'] ?? $br['code'] ?? 'BR') ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Settings Top Navigation Tabs -->
<div class="tabs-nav" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #8EB69B; padding-bottom: 0.5rem; flex-wrap: wrap;">
    <a href="/settings" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Organization Profile</span>
    </a>
    <a href="/settings/branding" class="btn btn-primary active" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; background: #235347; color: #FFFFFF; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
        <span>Company Branding</span>
    </a>
    <a href="/users" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
        <span>Users & Roles</span>
    </a>
    <a href="/settings/templates" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
        <span>Invoice Templates</span>
    </a>
    <a href="/settings/warehouses" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
        <span>Warehouses & Godowns</span>
    </a>
    <a href="/settings/audit-logs" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; border-radius: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        <span>Security Audit Trail</span>
    </a>
</div>

<!-- Upload Cards Grid (3 Columns) -->
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
            <span class="badge" style="background: <?= $branding['is_custom_logo'] ? '#235347' : '#8EB69B' ?>; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= $branding['is_custom_logo'] ? 'Custom Logo' : 'Default Logo' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Logo Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-logo" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <img id="img-preview-logo" src="<?= e($branding['logo_url']) ?>" alt="Company Logo" style="max-height: 100px; max-width: 90%; object-fit: contain;">
                    </div>
                </div>

                <div style="font-size: 0.76rem; color: #4B5563; line-height: 1.4; margin-bottom: 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.75rem; border-radius: 6px;">
                    <strong style="color: #051F20;">Recommended:</strong> Square or rectangular transparent PNG, WebP, or SVG.<br>
                    <strong>Min dimensions:</strong> 200 × 200px. <strong>Max size:</strong> 2 MB.<br>
                    <span style="color: #235347; font-weight: 500;">✓ Automatically updates in Sidebar, Header, Login, Invoices, &amp; Quotations.</span>
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
                    <?php if ($branding['is_custom_logo']): ?>
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
            <span class="badge" style="background: <?= $branding['signature_url'] ? '#235347' : '#94A3B8' ?>; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= $branding['signature_url'] ? 'Configured' : 'Not Set' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Signature Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-sig" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <?php if ($branding['signature_url']): ?>
                            <img id="img-preview-sig" src="<?= e($branding['signature_url']) ?>" alt="Signature" style="max-height: 80px; max-width: 90%; object-fit: contain;">
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
                    <span style="color: #235347; font-weight: 500;">✓ Renders dynamically in the Authorized Signatory block of bills &amp; quotes.</span>
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
                        <span id="label-file-sig"><?= $branding['signature_url'] ? 'Replace Signature File...' : 'Choose Signature Image...' ?></span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-sig" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if ($branding['signature_url']): ?>
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
            <span class="badge" style="background: <?= $branding['stamp_url'] ? '#235347' : '#94A3B8' ?>; color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                <?= $branding['stamp_url'] ? 'Configured' : 'Not Set' ?>
            </span>
        </div>

        <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <!-- Stamp Preview Box -->
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #163832; margin-bottom: 0.4rem;">Live Preview:</div>
                    <div id="preview-box-stamp" style="width: 100%; height: 130px; border: 2px dashed #8EB69B; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 16px 16px; overflow: hidden; padding: 10px;">
                        <?php if ($branding['stamp_url']): ?>
                            <img id="img-preview-stamp" src="<?= e($branding['stamp_url']) ?>" alt="Company Stamp" style="max-height: 90px; max-width: 90%; object-fit: contain;">
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
                        <span id="label-file-stamp"><?= $branding['stamp_url'] ? 'Replace Stamp File...' : 'Choose Stamp Image...' ?></span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" id="btn-save-stamp" class="btn btn-primary" style="flex: 1; background: #235347; color: #FFFFFF; border: none; padding: 0.55rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                        Upload &amp; Apply
                    </button>
                    <?php if ($branding['stamp_url']): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveAsset('stamp', '<?= e((string)($selectedBranchId ?? '')) ?>')" style="padding: 0.55rem 0.85rem; border: 1px solid #EF4444; color: #EF4444; background: transparent; border-radius: 6px; font-size: 0.85rem; cursor: pointer;" title="Remove stamp">
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DISPLAY SETTINGS & DOCUMENT VISIBILITY CONFIGURATION -->
<div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
    <div style="padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(142,182,155,0.3); background: #0B2B26; color: #FFFFFF; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8EB69B" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #DAF1DE;">Document Display Settings &amp; Visibility Controls</h3>
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
                        <input type="number" name="signature_width" min="50" max="300" value="<?= (int)$branding['signature_width'] ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Height (px):</label>
                        <input type="number" name="signature_height" min="20" max="150" value="<?= (int)$branding['signature_height'] ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Alignment in Signatory Block:</label>
                    <select name="signature_position" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                        <option value="right" <?= ($branding['signature_position'] === 'right') ? 'selected' : '' ?>>Right Aligned (Standard)</option>
                        <option value="center" <?= ($branding['signature_position'] === 'center') ? 'selected' : '' ?>>Center Aligned</option>
                        <option value="left" <?= ($branding['signature_position'] === 'left') ? 'selected' : '' ?>>Left Aligned</option>
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
                        <input type="number" name="stamp_width" min="40" max="200" value="<?= (int)$branding['stamp_width'] ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Height (px):</label>
                        <input type="number" name="stamp_height" min="40" max="200" value="<?= (int)$branding['stamp_height'] ?>" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Alignment in Signatory Block:</label>
                    <select name="stamp_position" class="form-control" style="width: 100%; padding: 0.45rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                        <option value="right" <?= ($branding['stamp_position'] === 'right') ? 'selected' : '' ?>>Right Aligned (Standard)</option>
                        <option value="center" <?= ($branding['stamp_position'] === 'center') ? 'selected' : '' ?>>Center Aligned</option>
                        <option value="left" <?= ($branding['stamp_position'] === 'left') ? 'selected' : '' ?>>Left Aligned</option>
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
                            <input type="checkbox" name="show_signature_invoice" value="1" <?= $branding['show_signature_invoice'] ? 'checked' : '' ?>>
                            <span>Show on Sales Invoices</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_signature_quotation" value="1" <?= $branding['show_signature_quotation'] ? 'checked' : '' ?>>
                            <span>Show on Quotations</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_signature_purchase" value="1" <?= $branding['show_signature_purchase'] ? 'checked' : '' ?>>
                            <span>Show on Purchase Orders</span>
                        </label>
                    </div>
                </div>

                <div style="font-size: 0.8rem;">
                    <strong style="color: #051F20;">Official Stamp:</strong>
                    <div style="margin: 0.35rem 0 0 0.2rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_invoice" value="1" <?= $branding['show_stamp_invoice'] ? 'checked' : '' ?>>
                            <span>Show on Sales Invoices</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_quotation" value="1" <?= $branding['show_stamp_quotation'] ? 'checked' : '' ?>>
                            <span>Show on Quotations</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_stamp_purchase" value="1" <?= $branding['show_stamp_purchase'] ? 'checked' : '' ?>>
                            <span>Show on Purchase Orders</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #E2E8F0; padding-top: 1rem;">
            <button type="submit" class="btn btn-primary" style="background: #235347; color: #FFFFFF; border: none; padding: 0.6rem 1.4rem; font-size: 0.88rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Save Display Settings</span>
            </button>
        </div>
    </form>
</div>

<!-- Modal for Remove Confirmation -->
<div id="modal-remove-asset" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: #FFFFFF; width: 100%; max-width: 420px; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 1rem 1.25rem; background: #FEF2F2; border-bottom: 1px solid #FEE2E2; display: flex; align-items: center; gap: 0.6rem;">
            <span style="color: #DC2626; font-size: 1.25rem;">⚠️</span>
            <h4 style="margin: 0; color: #991B1B; font-size: 1rem; font-weight: 700;">Confirm Asset Removal</h4>
        </div>
        <div style="padding: 1.25rem; font-size: 0.88rem; color: #374151;">
            <p style="margin: 0 0 0.5rem 0;" id="modal-remove-desc">Are you sure you want to remove this branding asset?</p>
            <p style="margin: 0; font-size: 0.78rem; color: #6B7280;">This will immediately revert the asset to default across all invoices, quotations, and documents.</p>
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

<script>
function previewSelectedFile(input, targetImgId, emptyContainerId) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        
        // Label update
        var labelId = 'label-' + input.id;
        var labelEl = document.getElementById(labelId);
        if (labelEl) {
            labelEl.textContent = 'Selected: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById(targetImgId);
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (emptyContainerId) {
                var emptyEl = document.getElementById(emptyContainerId);
                if (emptyEl) emptyEl.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }
}

function confirmRemoveAsset(type, branchId) {
    document.getElementById('remove-asset-type').value = type;
    document.getElementById('remove-asset-branch').value = branchId;
    document.getElementById('modal-remove-desc').textContent = 'Are you sure you want to remove the ' + type + '?';
    document.getElementById('modal-remove-asset').style.display = 'flex';
}

function closeRemoveModal() {
    document.getElementById('modal-remove-asset').style.display = 'none';
}
</script>

