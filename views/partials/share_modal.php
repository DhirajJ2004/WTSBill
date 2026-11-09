<?php
if (defined('SHARE_MODAL_INCLUDED')) {
    return;
}
define('SHARE_MODAL_INCLUDED', true);
?>
<!-- WTSBill ERP — Enterprise Document Sharing Modal (WhatsApp & Email Delivery) -->
<div id="wtsbill-share-modal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(5, 31, 32, 0.75); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(4px);" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">
    <div class="modal-dialog" style="max-width: 620px; width: 100%; margin: 1rem; background: var(--bg-card, #ffffff); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color, #e2e8f0); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); max-height: 92vh; overflow-y: auto;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e2e8f0); padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span id="share-modal-icon" style="color: var(--primary, #0B2B26); display: flex;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                </span>
                <h3 id="share-modal-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary, #0f172a);">Share Document</h3>
            </div>
            <button type="button" onclick="closeShareModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted, #64748b); line-height: 1;" aria-label="Close dialog">&times;</button>
        </div>

        <!-- Document Metadata & Quick Actions -->
        <div style="background: var(--bg-surface, #f8fafc); padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted, #64748b); display: block;">Recipient Customer</span>
                <div id="share-customer-name" style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary, #0f172a); margin-top: 0.1rem;">Customer Name</div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted, #64748b); display: block;">Document & Amount</span>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.1rem;">
                    <span id="share-doc-number" style="font-size: 0.95rem; font-weight: 700; color: var(--primary, #0B2B26);">DOC-0000</span>
                    <span id="share-doc-amount" class="badge" style="background: rgba(35,83,71,0.12); color: #235347; font-weight: 700; font-size: 0.8rem; padding: 0.2rem 0.5rem; border-radius: 4px;">₹0.00</span>
                </div>
            </div>
            <div style="width: 100%; display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-color, #e2e8f0);">
                <a id="share-modal-pdf-preview-btn" href="#" target="_blank" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                    <span>👁️ Preview PDF</span>
                </a>
                <a id="share-modal-pdf-download-btn" href="#" download class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                    <span>⬇️ Download PDF</span>
                </a>
            </div>
        </div>

        <!-- Share Tabs Navigation -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e2e8f0); padding-bottom: 0.5rem;">
            <button type="button" id="share-tab-btn-whatsapp" class="btn btn-primary" onclick="switchShareTab('whatsapp')" style="flex: 1; font-size: 0.82rem; padding: 0.4rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                <span>WhatsApp</span>
            </button>
            <button type="button" id="share-tab-btn-email" class="btn btn-secondary" onclick="switchShareTab('email')" style="flex: 1; font-size: 0.82rem; padding: 0.4rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                <span>Email Delivery</span>
            </button>
            <button type="button" id="share-tab-btn-history" class="btn btn-secondary" onclick="switchShareTab('history')" style="flex: 1; font-size: 0.82rem; padding: 0.4rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Share History</span>
            </button>
        </div>

        <input type="hidden" id="share-entity-type" value="INVOICE">
        <input type="hidden" id="share-entity-id" value="0">
        <input type="hidden" id="share-company-name" value="Wis Technosavvy Pvt Ltd">

        <!-- Status / Alert Message Container -->
        <div id="share-alert-box" style="display: none; padding: 0.65rem 0.85rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.82rem;"></div>

        <!-- 1. WHATSAPP TAB PANEL -->
        <div id="share-panel-whatsapp">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">Recipient WhatsApp Number</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="share-phone-input" placeholder="e.g. 9876543210 or +91 9876543210" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;">
                </div>
                <small style="color: var(--text-muted, #64748b); font-size: 0.72rem;">Indian standard (10-digit) or international mobile with country code.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">Custom Message Note (Optional)</label>
                <textarea id="share-wa-note-input" rows="2" placeholder="e.g. Please find attached your document for processing." class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;"></textarea>
            </div>

            <!-- Sharing Methods Cards -->
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.25rem;">
                <!-- Method A: Direct Web Share API File Attachment -->
                <div id="share-webshare-card" style="background: rgba(37, 211, 102, 0.08); border: 1px solid rgba(37, 211, 102, 0.35); border-radius: 8px; padding: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <span style="font-weight: 700; font-size: 0.85rem; color: #15803d; display: flex; align-items: center; gap: 0.35rem;">
                            <span>📱</span> Method A — Direct File Share (Web Share API)
                        </span>
                        <span class="badge" style="background: #15803d; color: #fff; font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px;">Recommended</span>
                    </div>
                    <p style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin: 0 0 0.65rem 0; line-height: 1.4;">
                        Directly attaches the actual generated PDF file to native WhatsApp sharing sheet on supported mobile and desktop browsers.
                    </p>
                    <button type="button" id="btn-webshare-direct" class="btn btn-success" onclick="executeDirectWebShare()" style="width: 100%; font-size: 0.85rem; padding: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; background: #25D366; border-color: #25D366; color: #fff; font-weight: 600;">
                        <span>📤 Share Actual PDF via WhatsApp</span>
                    </button>
                </div>

                <!-- Method B: WhatsApp Business Cloud API & Fallback -->
                <div style="background: var(--bg-surface, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 0.85rem;">
                    <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary, #0f172a); display: block; margin-bottom: 0.4rem;">
                        <span>🌐</span> Method B — WhatsApp Business Cloud API / Web Direct
                    </span>
                    <p style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin: 0 0 0.65rem 0; line-height: 1.4;">
                        Sends document notification via Meta WhatsApp Cloud API if configured, or downloads the PDF and opens WhatsApp chat with verified link.
                    </p>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btn-wa-cloud-api" class="btn btn-primary" onclick="executeWhatsAppCloudApi()" style="flex: 1; font-size: 0.82rem; padding: 0.45rem;">
                            <span>Send via WhatsApp API</span>
                        </button>
                        <button type="button" id="btn-wa-click-to-chat" class="btn btn-secondary" onclick="executeWhatsAppClickToChatFallback()" style="flex: 1; font-size: 0.82rem; padding: 0.45rem;">
                            <span>Download PDF & Open wa.me</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. EMAIL DELIVERY TAB PANEL -->
        <div id="share-panel-email" style="display: none;">
            <?php
            $commSettings = \WTSBill\Services\CommunicationService::getSettings(\WTSBill\Core\Session::getCompanyId() ?? 1);
            $isSmtpConfigured = !empty($commSettings['smtp_host']) || !empty($commSettings['email_configured']);
            ?>
            <div id="share-email-unconfigured-banner" style="<?= $isSmtpConfigured ? 'display: none;' : 'display: block;' ?> background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; padding: 0.75rem 0.9rem; margin-bottom: 1rem; font-size: 0.8rem; color: #92400e;">
                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <span style="font-size: 1.1rem; line-height: 1;">⚙️</span>
                    <div>
                        <strong>SMTP Not Configured:</strong> Direct server sending requires an active SMTP host. You can set up your email provider (Gmail, Outlook, etc.) in <a href="/settings?tab=communication" target="_blank" style="color: #b45309; text-decoration: underline; font-weight: 700;">Settings → Communication</a>, or use the <strong>"Download PDF & Open Email App"</strong> button below to send immediately using your email client.
                    </div>
                </div>
            </div>

            <form id="share-email-form" onsubmit="executeEmailDispatch(event)">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">Recipient Email *</label>
                        <input type="email" id="share-email-input" required placeholder="client@example.com" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">CC (Optional)</label>
                        <input type="email" id="share-email-cc" placeholder="accounts@example.com" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">Email Subject</label>
                    <input type="text" id="share-email-subject" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; display: block;">Message Body</label>
                    <textarea id="share-email-body" rows="3" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card, #fff); font-size: 0.85rem;"></textarea>
                </div>

                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 6px; padding: 0.65rem 0.85rem; margin-bottom: 1.25rem; font-size: 0.78rem; color: var(--text-primary, #0f172a); display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.1rem;">📎</span>
                    <div>
                        <strong>Automatic PDF Attachment:</strong> The generated official PDF (<span id="share-attachment-name-label">document.pdf</span>) will be attached automatically to this outgoing email.
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeShareModal()">Cancel</button>
                    <button type="button" id="btn-email-client" class="btn btn-secondary" onclick="executeEmailClientFallback()" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; font-weight: 600; border-color: #cbd5e1; color: var(--text-primary, #0f172a);" title="Download the PDF and open your email app with details prefilled">
                        <span>📥 Download PDF & Open Email App</span>
                    </button>
                    <button type="submit" id="btn-submit-email" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1.25rem; font-weight: 600;">
                        <span>Send Email via Server (SMTP)</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- 3. SHARE HISTORY TAB PANEL -->
        <div id="share-panel-history" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h4 style="margin: 0; font-size: 0.85rem; font-weight: 700; color: var(--text-primary, #0f172a);">Delivery Audit Trail</h4>
                <button type="button" class="btn btn-sm btn-secondary" onclick="refreshShareHistory()" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">Refresh</button>
            </div>
            <div id="share-history-container" style="max-height: 220px; overflow-y: auto; font-size: 0.8rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 6px;">
                <div style="text-align: center; color: var(--text-muted, #64748b); padding: 1.5rem;">Loading audit records...</div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeShareModal()">Close</button>
            </div>
        </div>

    </div>
</div>

<script>
window.getDocPaths = function(type, id, number) {
    type = (type || 'INVOICE').toUpperCase();
    if (type === 'PURCHASE_BILL' || type === 'BILL' || type === 'PURCHASE') {
        return {
            title: 'Share Purchase Bill',
            partyLabel: 'Recipient Vendor / Supplier',
            docPrefix: 'PB-',
            defaultDocName: 'Purchase Bill',
            filename: 'PurchaseBill_' + (number || id) + '.pdf',
            previewUrl: '/purchases/bills/' + id + '/pdf',
            downloadUrl: '/purchases/bills/' + id + '/pdf?download=1',
            shareEndpoint: '/purchases/bills/' + id + '/share',
            historyEndpoint: '/purchases/bills/' + id + '/communication-history'
        };
    } else if (type === 'QUOTATION') {
        return {
            title: 'Share Quotation',
            partyLabel: 'Recipient Customer',
            docPrefix: 'QT-',
            defaultDocName: 'Commercial Quotation',
            filename: 'Quotation_' + (number || id) + '.pdf',
            previewUrl: '/sales/quotations/' + id + '/pdf',
            downloadUrl: '/sales/quotations/' + id + '/pdf?download=1',
            shareEndpoint: '/sales/quotations/' + id + '/share',
            historyEndpoint: '/sales/quotations/' + id + '/communication-history'
        };
    } else {
        return {
            title: 'Share Tax Invoice',
            partyLabel: 'Recipient Customer',
            docPrefix: 'INV-',
            defaultDocName: 'Tax Invoice',
            filename: 'Invoice_' + (number || id) + '.pdf',
            previewUrl: '/sales/invoices/' + id + '/pdf',
            downloadUrl: '/sales/invoices/' + id + '/pdf?download=1',
            shareEndpoint: '/sales/invoices/' + id + '/share',
            historyEndpoint: '/sales/invoices/' + id + '/communication-history'
        };
    }
};

window.openShareModal = function(type, id, number, customerName, phone, email, grandTotal, companyName) {
    type = (type || 'INVOICE').toUpperCase();
    id = parseInt(id) || 0;
    companyName = companyName || 'Wis Technosavvy Pvt Ltd';

    var paths = window.getDocPaths(type, id, number);

    document.getElementById('share-entity-type').value = type;
    document.getElementById('share-entity-id').value = id;
    document.getElementById('share-company-name').value = companyName;

    document.getElementById('share-modal-title').textContent = paths.title;
    document.getElementById('share-doc-number').textContent = number || (paths.docPrefix + id);
    document.getElementById('share-customer-name').textContent = customerName || (type.includes('PURCHASE') || type === 'BILL' ? 'Vendor / Supplier' : 'Valued Customer');
    
    var formattedAmount = grandTotal ? '₹' + parseFloat(grandTotal).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '';
    document.getElementById('share-doc-amount').textContent = formattedAmount;

    document.getElementById('share-phone-input').value = phone || '';
    document.getElementById('share-email-input').value = email || '';
    document.getElementById('share-email-cc').value = '';
    document.getElementById('share-wa-note-input').value = '';

    var filename = paths.filename;
    document.getElementById('share-attachment-name-label').textContent = filename;

    var previewUrl = paths.previewUrl;
    var downloadUrl = paths.downloadUrl;
    
    var previewBtn = document.getElementById('share-modal-pdf-preview-btn');
    if (previewBtn) previewBtn.href = previewUrl;

    var downloadBtn = document.getElementById('share-modal-pdf-download-btn');
    if (downloadBtn) {
        downloadBtn.href = downloadUrl;
        downloadBtn.download = filename;
    }

    // Default Email Subject per requirement
    var defaultSubject = paths.defaultDocName + ' ' + (number || id) + ' from ' + companyName;
    document.getElementById('share-email-subject').value = defaultSubject;

    var defaultBody = 'Dear ' + (customerName || (type.includes('PURCHASE') || type === 'BILL' ? 'Supplier' : 'Customer')) + ',\n\n' +
                      'Please find attached your ' + paths.defaultDocName + ' ' +
                      (number || id) + (formattedAmount ? ' for ' + formattedAmount : '') + '.\n\n' +
                      'Thank you for your business.\n' + companyName;
    document.getElementById('share-email-body').value = defaultBody;

    // Reset Alert
    var alertBox = document.getElementById('share-alert-box');
    alertBox.style.display = 'none';

    // Show modal and switch to WhatsApp tab by default
    switchShareTab('whatsapp');
    document.getElementById('wtsbill-share-modal').style.display = 'flex';
    refreshShareHistory();
};

window.closeShareModal = function() {
    var modal = document.getElementById('wtsbill-share-modal');
    if (modal) modal.style.display = 'none';
};

window.switchShareTab = function(tab) {
    var waPanel = document.getElementById('share-panel-whatsapp');
    var emailPanel = document.getElementById('share-panel-email');
    var histPanel = document.getElementById('share-panel-history');

    var waBtn = document.getElementById('share-tab-btn-whatsapp');
    var emailBtn = document.getElementById('share-tab-btn-email');
    var histBtn = document.getElementById('share-tab-btn-history');

    [waPanel, emailPanel, histPanel].forEach(function(p) { if (p) p.style.display = 'none'; });
    [waBtn, emailBtn, histBtn].forEach(function(b) {
        if (b) {
            b.classList.remove('btn-primary');
            b.classList.add('btn-secondary');
        }
    });

    if (tab === 'email') {
        if (emailPanel) emailPanel.style.display = 'block';
        if (emailBtn) { emailBtn.classList.remove('btn-secondary'); emailBtn.classList.add('btn-primary'); }
    } else if (tab === 'history') {
        if (histPanel) histPanel.style.display = 'block';
        if (histBtn) { histBtn.classList.remove('btn-secondary'); histBtn.classList.add('btn-primary'); }
        refreshShareHistory();
    } else {
        if (waPanel) waPanel.style.display = 'block';
        if (waBtn) { waBtn.classList.remove('btn-secondary'); waBtn.classList.add('btn-primary'); }
    }
};

window.renderShareNotice = function(msg, isSuccess) {
    var alertBox = document.getElementById('share-alert-box');
    if (!alertBox) return;
    alertBox.style.display = 'block';
    if (isSuccess) {
        alertBox.style.background = 'rgba(16, 185, 129, 0.12)';
        alertBox.style.color = '#065f46';
        alertBox.style.border = '1px solid #10b981';
        alertBox.innerHTML = '✓ ' + msg;
    } else {
        alertBox.style.background = 'rgba(239, 68, 68, 0.12)';
        alertBox.style.color = '#991b1b';
        alertBox.style.border = '1px solid #ef4444';
        alertBox.innerHTML = '⚠ ' + msg;
    }
};

/**
 * Method A: Direct Web Share API with File Attachment
 */
window.executeDirectWebShare = async function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var docNumber = document.getElementById('share-doc-number').textContent.trim();
    var customerName = document.getElementById('share-customer-name').textContent.trim();
    var companyName = document.getElementById('share-company-name').value;
    var note = document.getElementById('share-wa-note-input').value.trim();
    var paths = window.getDocPaths(type, id, docNumber);
    var filename = paths.filename;
    var pdfUrl = paths.previewUrl;

    var btn = document.getElementById('btn-webshare-direct');
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Preparing PDF File...</span>';

    try {
        var response = await fetch(pdfUrl);
        if (!response.ok) throw new Error('Could not fetch PDF from server.');
        var blob = await response.blob();
        var file = new File([blob], filename, { type: 'application/pdf' });

        var shareText = paths.defaultDocName + ' ' + docNumber + ' for ' + customerName + ' from ' + companyName + '.';
        if (note) shareText += '\nNote: ' + note;

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                title: paths.defaultDocName + ' ' + docNumber,
                text: shareText,
                files: [file]
            });
            btn.disabled = false;
            btn.innerHTML = '<span>📤 Share Actual PDF via WhatsApp</span>';
            renderShareNotice('Native sharing dialogue launched successfully with attached PDF.', true);
        } else {
            // Fallback if browser does not support file sharing
            btn.disabled = false;
            btn.innerHTML = '<span>📤 Share Actual PDF via WhatsApp</span>';
            renderShareNotice('Your browser does not support direct file attachments via Web Share. Downloading PDF now & opening WhatsApp fallback...', false);
            executeWhatsAppClickToChatFallback();
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>📤 Share Actual PDF via WhatsApp</span>';
        if (err.name !== 'AbortError') {
            renderShareNotice('Native share notice: ' + err.message + '. Falling back to download + WhatsApp.', false);
            executeWhatsAppClickToChatFallback();
        }
    }
};

/**
 * Method B: WhatsApp Business Cloud API Server-Side Dispatch
 */
window.executeWhatsAppCloudApi = function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var phone = document.getElementById('share-phone-input').value.trim();
    var note = document.getElementById('share-wa-note-input').value.trim();

    if (!phone) {
        renderShareNotice('Please provide a recipient mobile phone number.', false);
        return;
    }

    var btn = document.getElementById('btn-wa-cloud-api');
    btn.disabled = true;
    btn.textContent = 'Dispatching via API...';

    var paths = window.getDocPaths(type, id);
    var endpoint = paths.shareEndpoint;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '<?= \WTSBill\Core\Session::csrfToken() ?>';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            channel: 'WHATSAPP',
            phone: phone,
            note: note
        })
    }).then(async function(r) {
        var text = await r.text();
        var res;
        try {
            res = JSON.parse(text);
        } catch(e) {
            throw new Error('Server returned invalid response (HTTP ' + r.status + ')');
        }
        return res;
    }).then(function(res) {
        btn.disabled = false;
        btn.textContent = 'Send via WhatsApp API';

        if (res.success && res.results && res.results.whatsapp && res.results.whatsapp.success) {
            renderShareNotice('WhatsApp notification dispatched successfully! ' + (res.results.whatsapp.message || ''), true);
            if (res.results.whatsapp.url) {
                window.open(res.results.whatsapp.url, '_blank');
            }
        } else {
            var errMsg = (res.results && res.results.whatsapp && res.results.whatsapp.error) ? res.results.whatsapp.error : (res.message || 'WhatsApp API dispatch failed.');
            renderShareNotice(errMsg, false);
        }
        refreshShareHistory();
    }).catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Send via WhatsApp API';
        renderShareNotice('Error: ' + err.message, false);
    });
};

/**
 * Fallback: Download PDF & Open wa.me link
 */
window.executeWhatsAppClickToChatFallback = function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var docNumber = document.getElementById('share-doc-number').textContent.trim();
    var phone = document.getElementById('share-phone-input').value.trim();
    var note = document.getElementById('share-wa-note-input').value.trim();
    var paths = window.getDocPaths(type, id, docNumber);
    var filename = paths.filename;

    // 1. Trigger PDF download
    var dl = document.createElement('a');
    dl.href = paths.downloadUrl;
    dl.download = filename;
    document.body.appendChild(dl);
    dl.click();
    document.body.removeChild(dl);

    // 2. Dispatch to server to log CLICK_TO_CHAT and get wa.me url
    var endpoint = paths.shareEndpoint;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '<?= \WTSBill\Core\Session::csrfToken() ?>';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            channel: 'WHATSAPP',
            phone: phone,
            note: note
        })
    }).then(async function(r) {
        var text = await r.text();
        var res;
        try { res = JSON.parse(text); } catch(e) { res = {}; }
        return res;
    }).then(function(res) {
        if (res.results && res.results.whatsapp && res.results.whatsapp.url) {
            window.open(res.results.whatsapp.url, '_blank');
        } else if (phone) {
            var cleanPhone = phone.replace(/[^0-9]/g, '');
            if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
            window.open('https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(paths.defaultDocName + ' ' + docNumber), '_blank');
        }
        renderShareNotice('📄 PDF downloaded! Drag it into WhatsApp to share the direct file.', true);
        refreshShareHistory();
    }).catch(function() {
        if (phone) {
            var cleanPhone = phone.replace(/[^0-9]/g, '');
            if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
            window.open('https://wa.me/' + cleanPhone, '_blank');
        }
    });
};

/**
 * Fallback: Download PDF & Open Email App (mailto:)
 */
window.executeEmailClientFallback = function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var docNumber = document.getElementById('share-doc-number').textContent.trim();
    var email = document.getElementById('share-email-input').value.trim();
    var cc = document.getElementById('share-email-cc').value.trim();
    var paths = window.getDocPaths(type, id, docNumber);
    var subject = document.getElementById('share-email-subject').value.trim() || (paths.defaultDocName + ' ' + docNumber);
    var body = document.getElementById('share-email-body').value.trim();
    var filename = paths.filename;

    if (!email) {
        renderShareNotice('Please enter a recipient email address.', false);
        document.getElementById('share-email-input').focus();
        return;
    }

    // 1. Download PDF automatically
    var dl = document.createElement('a');
    dl.href = paths.downloadUrl;
    dl.download = filename;
    document.body.appendChild(dl);
    dl.click();
    document.body.removeChild(dl);

    // 2. Open default mail client (mailto:)
    var mailtoUri = 'mailto:' + encodeURIComponent(email) +
        '?subject=' + encodeURIComponent(subject) +
        '&body=' + encodeURIComponent(body + '\n\n[Attached File: ' + filename + ']');
    if (cc) {
        mailtoUri += '&cc=' + encodeURIComponent(cc);
    }
    window.location.href = mailtoUri;

    // 3. Log to server audit history
    var endpoint = paths.shareEndpoint;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '<?= \WTSBill\Core\Session::csrfToken() ?>';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            channel: 'EMAIL_CLIENT',
            email: email,
            cc: cc,
            subject: subject,
            body: body
        })
    }).then(function() {
        refreshShareHistory();
    }).catch(function() {});

    renderShareNotice('✓ PDF downloaded! Opening your default email client... simply attach ' + filename + ' to your email and click Send.', true);
};

/**
 * Email Dispatch with PDF Attachment
 */
window.executeEmailDispatch = function(e) {
    e.preventDefault();
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var email = document.getElementById('share-email-input').value.trim();
    var cc = document.getElementById('share-email-cc').value.trim();
    var subject = document.getElementById('share-email-subject').value.trim();
    var body = document.getElementById('share-email-body').value.trim();

    if (!email) {
        renderShareNotice('Please provide a valid recipient email address.', false);
        return;
    }

    var btn = document.getElementById('btn-submit-email');
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Sending via Server (SMTP)...</span>';

    var paths = window.getDocPaths(type, id);
    var endpoint = paths.shareEndpoint;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '<?= \WTSBill\Core\Session::csrfToken() ?>';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            channel: 'EMAIL',
            email: email,
            cc: cc,
            subject: subject,
            body: body
        })
    }).then(async function(r) {
        var text = await r.text();
        var res;
        try {
            res = JSON.parse(text);
        } catch(e) {
            throw new Error('Server returned invalid response (HTTP ' + r.status + ')');
        }
        return res;
    }).then(function(res) {
        btn.disabled = false;
        btn.innerHTML = '<span>Send Email via Server (SMTP)</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';

        if (res.success && res.results && res.results.email && res.results.email.success) {
            renderShareNotice('✓ Email with attached original invoice PDF was sent successfully to ' + email + '.', true);
            refreshShareHistory();
        } else {
            var errMsg = (res.results && res.results.email && res.results.email.error) ? res.results.email.error : (res.message || 'Email delivery failed.');
            renderShareNotice(errMsg, false);
            refreshShareHistory();
        }
    }).catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Send Email via Server (SMTP)</span>';
        renderShareNotice('Transmission Error: ' + err.message, false);
    });
};

/**
 * Fetch and render document communication audit records
 */
window.refreshShareHistory = function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var container = document.getElementById('share-history-container');
    if (!container || !id || id === '0') return;

    var paths = window.getDocPaths(type, id);
    var url = paths.historyEndpoint;

    fetch(url, {
        headers: { 'Accept': 'application/json' }
    })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success || !res.data || res.data.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted, #64748b); padding: 1.5rem;">No prior communications recorded for this document.</div>';
                return;
            }

            var html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.78rem;">';
            html += '<thead><tr style="background: var(--bg-surface, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); text-align: left;">' +
                    '<th style="padding: 0.4rem 0.6rem;">Date & Time</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Channel</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Recipient</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Status</th>' +
                    '</tr></thead><tbody>';

            res.data.forEach(function(item) {
                var badgeClass = (item.status === 'SENT' || item.status === 'DELIVERED') ? 'badge-success' : 
                                 (item.status === 'CLICK_TO_CHAT_GENERATED' ? 'badge-warning' : 'badge-danger');
                html += '<tr style="border-bottom: 1px solid var(--border-color, #e2e8f0);">' +
                        '<td style="padding: 0.4rem 0.6rem; white-space: nowrap;">' + (item.created_at || '').substring(0, 16) + '</td>' +
                        '<td style="padding: 0.4rem 0.6rem; font-weight: 600;">' + item.channel + '</td>' +
                        '<td style="padding: 0.4rem 0.6rem;">' + (item.recipient || '') + '</td>' +
                        '<td style="padding: 0.4rem 0.6rem;"><span class="badge ' + badgeClass + '" style="font-size: 0.7rem;">' + item.status + '</span></td>' +
                        '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        })
        .catch(function() {
            container.innerHTML = '<div style="text-align: center; color: var(--text-muted, #64748b); padding: 1.5rem;">Unable to load history.</div>';
        });
};
</script>
