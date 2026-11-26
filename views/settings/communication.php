<?php
/**
 * WTSBill ERP — Communication & Multi-Channel Sharing Settings View
 * Dedicated configuration view for SMTP Email, WhatsApp click-to-chat, and document notifications.
 *
 * @var array $commSettings
 * @var bool $isAdmin
 * @var string $title
 * @var string $currentUri
 */

use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$comm = $commSettings ?? [];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #1E293B);">Communication &amp; Document Sharing</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Configure SMTP email delivery, WhatsApp dispatch, invoice sharing, and customer communication channels
        </p>
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
    <a href="/settings/communication" class="btn btn-primary active" style="font-size: 0.85rem; padding: 0.45rem 0.9rem; display: flex; align-items: center; gap: 0.4rem; text-decoration: none; background: #355872; color: #FFFFFF; border-radius: 6px;">
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

<div class="card" style="background: #FFFFFF; border: 1px solid #7AAACE; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
    <form method="POST" action="/settings/communication">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

        <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; color: #1E293B;">1. Outbound Email (SMTP) Configuration</h3>
        <p style="font-size: 0.82rem; color: #4B5563; margin-bottom: 1.25rem;">Used to send PDF Tax Invoices, Quotations, and Account Statements to customers.</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e($comm['smtp_host'] ?? '') ?>" class="form-control" placeholder="e.g. smtp.gmail.com" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= (int)($comm['smtp_port'] ?? 587) ?>" class="form-control" placeholder="587" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">Encryption</label>
                <select name="smtp_encryption" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    <option value="tls" <?= (($comm['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS (Recommended - 587)</option>
                    <option value="ssl" <?= (($comm['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL (Port 465)</option>
                    <option value="none" <?= (($comm['smtp_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>None (Internal)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">SMTP Username / Email</label>
                <input type="text" name="smtp_username" value="<?= e($comm['smtp_username'] ?? '') ?>" class="form-control" placeholder="billing@yourdomain.com" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">SMTP Password / App Password</label>
                <input type="password" name="smtp_password" value="<?= e($comm['smtp_password'] ?? '') ?>" class="form-control" placeholder="••••••••••••" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #2A485E; margin-bottom: 0.25rem;">Sender Name</label>
                <input type="text" name="smtp_from_name" value="<?= e($comm['smtp_from_name'] ?? 'Wis Technosavvy Pvt Ltd') ?>" class="form-control" placeholder="Wis Technosavvy Billing" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 1.5rem 0;">

        <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; color: #1E293B;">2. WhatsApp Communication Mode</h3>
        <p style="font-size: 0.82rem; color: #4B5563; margin-bottom: 1.25rem;">Choose between instant browser-based WhatsApp Web / App links or automated API dispatch.</p>

        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="radio" name="whatsapp_provider" value="click_to_chat" <?= (($comm['whatsapp_provider'] ?? 'click_to_chat') === 'click_to_chat') ? 'checked' : '' ?>>
                <div>
                    <strong style="color: #1E293B; font-size: 0.88rem;">Click-to-Chat (Zero Setup / 100% Free)</strong>
                    <div style="font-size: 0.78rem; color: #64748B;">Instantly opens customer's chat on WhatsApp Web or mobile app with pre-filled invoice message and public PDF download link.</div>
                </div>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="radio" name="whatsapp_provider" value="whatsapp_cloud_api" <?= (($comm['whatsapp_provider'] ?? '') === 'whatsapp_cloud_api') ? 'checked' : '' ?>>
                <div>
                    <strong style="color: #1E293B; font-size: 0.88rem;">Meta WhatsApp Cloud API (Automated)</strong>
                    <div style="font-size: 0.78rem; color: #64748B;">Dispatches pre-approved templates directly to customer numbers via Meta Cloud API token.</div>
                </div>
            </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary" style="background: #355872; color: #FFFFFF; border: none; padding: 0.55rem 1.25rem; font-size: 0.88rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                Save Communication Settings
            </button>
        </div>
    </form>
</div>

