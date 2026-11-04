/**
 * WTSBill ERP — Session Inactivity Monitor & In-Flight Form Auto-Saver
 * - Enforces 15-minute inactivity session expiration.
 * - Multi-tab synchronization via localStorage events.
 * - At 14 minutes: Auto-saves active form drafts and presents a 60-second warning modal.
 * - At 15 minutes: Destroys session and redirects to /login?session_expired=1.
 * - Form draft recovery banners across sales invoices, quotations, purchase bills, etc.
 */
(function() {
    'use strict';

    // Only run if user is authenticated (indicated by CSRF token presence)
    var csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfTokenMeta) return;

    var TIMEOUT_SECONDS = 900; // 15 minutes
    var WARNING_SECONDS = 840; // 14 minutes
    var CHECK_INTERVAL = 1000; // 1 second

    var lastActivity = Date.now();
    try {
        var stored = localStorage.getItem('wtsbill_last_active');
        if (stored) {
            var parsed = parseInt(stored, 10);
            if (!isNaN(parsed) && parsed > 0 && (Date.now() - parsed) < (TIMEOUT_SECONDS * 1000)) {
                lastActivity = parsed;
            }
        }
    } catch(e) {}

    var hasAutoSaved = false;
    var warningModal = null;
    var countdownEl = null;

    // Track qualifying user activity
    var throttleTimer = null;
    function recordActivity() {
        if (throttleTimer) return;
        throttleTimer = setTimeout(function() {
            throttleTimer = null;
            lastActivity = Date.now();
            try {
                localStorage.setItem('wtsbill_last_active', lastActivity.toString());
            } catch(e) {}
            if (warningModal && warningModal.style.display !== 'none') {
                hideWarningModal();
            }
        }, 500);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(evt) {
        window.addEventListener(evt, recordActivity, { passive: true });
    });

    // Multi-tab synchronization
    window.addEventListener('storage', function(e) {
        if (e.key === 'wtsbill_last_active' && e.newValue) {
            var remoteTime = parseInt(e.newValue, 10);
            if (!isNaN(remoteTime) && remoteTime > lastActivity) {
                lastActivity = remoteTime;
                if (warningModal && warningModal.style.display !== 'none') {
                    hideWarningModal();
                }
            }
        }
    });

    // Create Warning Modal DOM
    function createWarningModal() {
        var modal = document.createElement('div');
        modal.id = 'session-timeout-modal';
        modal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.75); z-index:999999; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(3px);';
        modal.innerHTML = 
            '<div style="background:var(--bg-card, #ffffff); color:var(--text-primary, #0f172a); border-radius:12px; max-width:440px; width:100%; padding:1.75rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3); border:1px solid var(--border-color, #e2e8f0); text-align:center;">' +
                '<div style="width:54px; height:54px; border-radius:50%; background:#FEF3C7; color:#D97706; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;">' +
                    '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>' +
                '</div>' +
                '<h3 style="margin:0 0 0.5rem 0; font-size:1.25rem; font-weight:700;">Session Inactivity Warning</h3>' +
                '<p style="margin:0 0 1rem 0; font-size:0.9rem; color:var(--text-muted, #64748b); line-height:1.5;">' +
                    'You have been inactive for 14 minutes. For security, your session will automatically terminate in ' +
                    '<strong id="session-countdown-seconds" style="color:#DC2626; font-size:1.05rem;">60</strong> seconds.' +
                '</p>' +
                '<div id="draft-save-status-badge" style="background:#ECFDF5; color:#059669; border-radius:6px; padding:0.4rem 0.75rem; font-size:0.8rem; font-weight:600; margin-bottom:1.25rem; display:none;">' +
                    '✓ In-flight form draft automatically saved to recovery storage.' +
                '</div>' +
                '<div style="display:flex; gap:0.75rem; justify-content:center;">' +
                    '<button type="button" id="btn-continue-session" class="btn btn-primary" style="flex:1; padding:0.65rem 1rem; font-weight:600; cursor:pointer;">Continue Working</button>' +
                    '<a href="/logout" class="btn btn-secondary" style="padding:0.65rem 1rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; cursor:pointer;">Logout</a>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        modal.querySelector('#btn-continue-session').addEventListener('click', function() {
            renewSession();
        });

        return modal;
    }

    function showWarningModal(remainingSecs) {
        if (!warningModal) {
            warningModal = createWarningModal();
            countdownEl = warningModal.querySelector('#session-countdown-seconds');
        }
        if (countdownEl) {
            countdownEl.textContent = Math.max(0, remainingSecs);
        }
        warningModal.style.display = 'flex';
    }

    function hideWarningModal() {
        if (warningModal) {
            warningModal.style.display = 'none';
        }
        hasAutoSaved = false;
    }

    function renewSession() {
        lastActivity = Date.now();
        try {
            localStorage.setItem('wtsbill_last_active', lastActivity.toString());
        } catch(e) {}
        hideWarningModal();

        fetch('/api/session/touch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || ''
            }
        }).catch(function() {});

        if (window.Toast && typeof window.Toast.show === 'function') {
            window.Toast.show('Session resumed. You can continue working safely.', 'success');
        }
    }

    // Auto-save eligible forms
    function autoSaveActiveForm() {
        var form = document.querySelector('#invoice-form') || 
                   document.querySelector('#quotation-form') || 
                   document.querySelector('#purchase-bill-form') ||
                   document.querySelector('#create-customer-form') ||
                   document.querySelector('#create-supplier-form') ||
                   document.querySelector('#product-form') ||
                   document.querySelector('form[data-auto-draft]');

        if (!form) return;

        var formType = form.getAttribute('data-form-type') || 
                       (form.id.includes('invoice') ? 'INVOICE' : 
                       (form.id.includes('quotation') ? 'QUOTATION' : 
                       (form.id.includes('purchase') ? 'PURCHASE_BILL' : 
                       (form.id.includes('customer') ? 'CUSTOMER' : 
                       (form.id.includes('supplier') ? 'SUPPLIER' : 'GENERAL')))));

        var formDataObj = {};
        var elements = form.elements;
        var hasEnteredData = false;

        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || el.type === 'hidden' && (el.name.includes('csrf') || el.name === 'id')) continue;
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.checked) {
                    formDataObj[el.name] = el.value;
                    hasEnteredData = true;
                }
            } else if (el.value && el.value.trim() !== '') {
                formDataObj[el.name] = el.value;
                hasEnteredData = true;
            }
        }

        if (!hasEnteredData) return;

        // Auto-save in background without resetting activity timer
        fetch('/api/drafts/auto-save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || '',
                'X-Session-KeepAlive': '0',
                'X-Background': '1'
            },
            body: JSON.stringify({
                form_type: formType,
                form_key: window.location.pathname,
                form_data: formDataObj
            })
        }).then(function(res) {
            return res.json();
        }).then(function(data) {
            if (data.success && warningModal) {
                var badge = warningModal.querySelector('#draft-save-status-badge');
                if (badge) badge.style.display = 'block';
            }
        }).catch(function() {});
    }

    // Main Inactivity Watcher Loop
    setInterval(function() {
        var idleSeconds = Math.floor((Date.now() - lastActivity) / 1000);

        if (idleSeconds >= TIMEOUT_SECONDS) {
            // Inactivity reached exactly 15 minutes! Terminate session & redirect.
            window.location.href = '/login?session_expired=1';
            return;
        }

        if (idleSeconds >= WARNING_SECONDS) {
            var remaining = TIMEOUT_SECONDS - idleSeconds;
            if (!hasAutoSaved) {
                hasAutoSaved = true;
                autoSaveActiveForm();
            }
            showWarningModal(remaining);
        } else {
            if (warningModal && warningModal.style.display !== 'none') {
                hideWarningModal();
            }
        }
    }, CHECK_INTERVAL);

    // ==========================================
    // FORM DRAFT RECOVERY ENGINE
    // ==========================================
    function checkAndOfferDraftRecovery() {
        var form = document.querySelector('#invoice-form') || 
                   document.querySelector('#quotation-form') || 
                   document.querySelector('#purchase-bill-form') ||
                   document.querySelector('#create-customer-form') ||
                   document.querySelector('#create-supplier-form') ||
                   document.querySelector('#product-form') ||
                   document.querySelector('form[data-auto-draft]');

        if (!form) return;

        var formType = form.getAttribute('data-form-type') || 
                       (form.id.includes('invoice') ? 'INVOICE' : 
                       (form.id.includes('quotation') ? 'QUOTATION' : 
                       (form.id.includes('purchase') ? 'PURCHASE_BILL' : 
                       (form.id.includes('customer') ? 'CUSTOMER' : 
                       (form.id.includes('supplier') ? 'SUPPLIER' : 'GENERAL')))));

        fetch('/api/drafts/check?form_type=' + encodeURIComponent(formType) + '&form_key=' + encodeURIComponent(window.location.pathname), {
            headers: {
                'X-Session-KeepAlive': '0',
                'X-Background': '1'
            }
        }).then(function(res) {
            return res.json();
        }).then(function(res) {
            if (res.success && res.has_draft && res.draft && res.draft.data) {
                renderDraftRecoveryBanner(form, res.draft);
            }
        }).catch(function() {});
    }

    function renderDraftRecoveryBanner(form, draft) {
        var existingBanner = document.querySelector('#wtsbill-draft-banner');
        if (existingBanner) existingBanner.remove();

        var banner = document.createElement('div');
        banner.id = 'wtsbill-draft-banner';
        banner.style.cssText = 'background:var(--primary-soft, #EFF6FF); border:1px solid var(--primary, #3B82F6); border-radius:8px; padding:0.85rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;';
        
        banner.innerHTML = 
            '<div style="display:flex; align-items:center; gap:0.6rem;">' +
                '<span style="color:var(--primary, #2563EB); display:flex; align-items:center;">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>' +
                '</span>' +
                '<div>' +
                    '<strong style="color:var(--text-primary, #0F172A); font-size:0.9rem;">Unsaved Draft Available</strong>' +
                    '<p style="margin:0; font-size:0.8rem; color:var(--text-muted, #64748B);">Saved automatically on ' + (draft.saved_at || 'recent session') + '. Would you like to restore your work?</p>' +
                '</div>' +
            '</div>' +
            '<div style="display:flex; gap:0.5rem;">' +
                '<button type="button" id="btn-restore-draft-act" class="btn btn-sm btn-primary" style="font-size:0.8rem; padding:0.35rem 0.75rem;">Restore Draft</button>' +
                '<button type="button" id="btn-discard-draft-act" class="btn btn-sm btn-outline-secondary" style="font-size:0.8rem; padding:0.35rem 0.75rem;">Discard</button>' +
            '</div>';

        form.parentNode.insertBefore(banner, form);

        banner.querySelector('#btn-restore-draft-act').addEventListener('click', function() {
            var fields = draft.data || {};
            for (var key in fields) {
                var el = form.elements[key];
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = (fields[key] === el.value || fields[key] === true || fields[key] === '1');
                    } else if (el.type === 'radio') {
                        var radios = form.querySelectorAll('input[name="' + key + '"]');
                        radios.forEach(function(r) {
                            r.checked = (r.value === fields[key]);
                        });
                    } else {
                        el.value = fields[key];
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
            banner.remove();
            if (window.Toast && typeof window.Toast.show === 'function') {
                window.Toast.show('Draft restored successfully.', 'success');
            }
        });

        banner.querySelector('#btn-discard-draft-act').addEventListener('click', function() {
            fetch('/api/drafts/discard', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || ''
                },
                body: JSON.stringify({ draft_id: draft.id })
            }).then(function() {
                banner.remove();
                if (window.Toast && typeof window.Toast.show === 'function') {
                    window.Toast.show('Draft discarded.', 'info');
                }
            }).catch(function() {});
        });
    }

    // Run recovery check on DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndOfferDraftRecovery);
    } else {
        checkAndOfferDraftRecovery();
    }
})();

