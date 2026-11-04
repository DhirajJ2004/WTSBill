<!-- WTSBill ERP — Document Sharing & Multi-Channel Communication Modal -->
<div id="wtsbill-share-modal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.75); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-dialog" style="max-width: 580px; width: 100%; margin: 1rem; background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span id="share-modal-icon" style="color: var(--primary); display: flex;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                </span>
                <h3 id="share-modal-title" style="margin: 0; font-size: 1.15rem; font-weight: 700;">Share Document</h3>
            </div>
            <button type="button" onclick="closeShareModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>

        <form id="share-doc-form" onsubmit="executeShareDocument(event)">
            <input type="hidden" id="share-entity-type" value="INVOICE">
            <input type="hidden" id="share-entity-id" value="0">

            <div style="background: var(--bg-surface); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);">Recipient Client</span>
                    <div id="share-customer-name" style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary); margin-top: 0.1rem;">Customer Name</div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);">Document Number</span>
                    <div id="share-doc-number" style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-top: 0.1rem;">DOC-0000</div>
                </div>
            </div>

            <!-- Delivery Channel Selector -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Select Delivery Channel</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-size: 0.8rem; background: var(--bg-card);">
                        <input type="radio" name="share_channel" value="BOTH" checked onchange="updateShareFieldsVisibility()"> Both
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-size: 0.8rem; background: var(--bg-card);">
                        <input type="radio" name="share_channel" value="WHATSAPP" onchange="updateShareFieldsVisibility()"> WhatsApp
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-size: 0.8rem; background: var(--bg-card);">
                        <input type="radio" name="share_channel" value="EMAIL" onchange="updateShareFieldsVisibility()"> Email
                    </label>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div class="form-group" id="share-field-phone-sec">
                    <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Mobile Phone (WhatsApp)</label>
                    <input type="text" id="share-phone-input" placeholder="e.g. 9876543210" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.85rem;">
                </div>
                <div class="form-group" id="share-field-email-sec">
                    <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Email Address</label>
                    <input type="email" id="share-email-input" placeholder="client@example.com" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.85rem;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Optional Custom Note</label>
                <input type="text" id="share-note-input" placeholder="e.g. Please find attached for your approval." class="form-control" style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.85rem;">
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-bottom: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeShareModal()">Cancel</button>
                <button type="submit" id="btn-submit-share" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.25rem;">
                    <span>Send & Share</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </form>

        <!-- Communication History Section -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h4 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">Document Communication History</h4>
                <button type="button" class="btn btn-sm btn-secondary" onclick="refreshShareHistory()" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">Refresh</button>
            </div>
            <div id="share-history-container" style="max-height: 180px; overflow-y: auto; font-size: 0.8rem; border: 1px solid var(--border-color); border-radius: 6px;">
                <div style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Loading history...</div>
            </div>
        </div>
    </div>
</div>

<script>
window.openShareModal = function(type, id, number, customerName, phone, email) {
    document.getElementById('share-entity-type').value = type;
    document.getElementById('share-entity-id').value = id;
    document.getElementById('share-modal-title').textContent = 'Share ' + (type === 'INVOICE' ? 'Tax Invoice' : 'Quotation');
    document.getElementById('share-doc-number').textContent = number;
    document.getElementById('share-customer-name').textContent = customerName || 'Valued Customer';
    document.getElementById('share-phone-input').value = phone || '';
    document.getElementById('share-email-input').value = email || '';
    document.getElementById('share-note-input').value = '';

    updateShareFieldsVisibility();
    document.getElementById('wtsbill-share-modal').style.display = 'flex';
    refreshShareHistory();
};

window.closeShareModal = function() {
    document.getElementById('wtsbill-share-modal').style.display = 'none';
};

window.updateShareFieldsVisibility = function() {
    var channel = document.querySelector('input[name="share_channel"]:checked').value;
    var phoneSec = document.getElementById('share-field-phone-sec');
    var emailSec = document.getElementById('share-field-email-sec');

    if (channel === 'WHATSAPP') {
        phoneSec.style.display = 'block';
        emailSec.style.display = 'none';
    } else if (channel === 'EMAIL') {
        phoneSec.style.display = 'none';
        emailSec.style.display = 'block';
    } else {
        phoneSec.style.display = 'block';
        emailSec.style.display = 'block';
    }
};

window.refreshShareHistory = function() {
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var container = document.getElementById('share-history-container');

    var url = (type === 'INVOICE') 
        ? '/sales/invoices/' + id + '/communication-history'
        : '/sales/quotations/' + id + '/communication-history';

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success || !res.data || res.data.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No prior communications recorded for this document.</div>';
                return;
            }

            var html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.78rem;">';
            html += '<thead><tr style="background: var(--bg-surface); border-bottom: 1px solid var(--border-color); text-align: left;">' +
                    '<th style="padding: 0.4rem 0.6rem;">Date</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Channel</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Recipient</th>' +
                    '<th style="padding: 0.4rem 0.6rem;">Status</th>' +
                    '</tr></thead><tbody>';

            res.data.forEach(function(item) {
                var badgeClass = (item.status === 'SENT' || item.status === 'DELIVERED') ? 'badge-success' : 
                                 (item.status === 'CLICK_TO_CHAT_GENERATED' ? 'badge-warning' : 'badge-danger');
                html += '<tr style="border-bottom: 1px solid var(--border-color);">' +
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
            container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Unable to load history.</div>';
        });
};

window.executeShareDocument = function(e) {
    e.preventDefault();
    var type = document.getElementById('share-entity-type').value;
    var id = document.getElementById('share-entity-id').value;
    var channel = document.querySelector('input[name="share_channel"]:checked').value;
    var phone = document.getElementById('share-phone-input').value.trim();
    var email = document.getElementById('share-email-input').value.trim();
    var note = document.getElementById('share-note-input').value.trim();

    var btn = document.getElementById('btn-submit-share');
    btn.disabled = true;
    btn.textContent = 'Dispatching...';

    var endpoint = (type === 'INVOICE')
        ? '/sales/invoices/' + id + '/share'
        : '/sales/quotations/' + id + '/share';

    var csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            channel: channel,
            phone: phone,
            email: email,
            note: note
        })
    }).then(function(r) { return r.json(); }).then(function(res) {
        btn.disabled = false;
        btn.innerHTML = '<span>Send & Share</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';

        if (res.success) {
            Toast.show(res.message || 'Document shared successfully.', 'success');
            // If click to chat fallback was generated for WhatsApp, open in new tab
            if (res.results && res.results.whatsapp && res.results.whatsapp.mode === 'CLICK_TO_CHAT' && res.results.whatsapp.url) {
                window.open(res.results.whatsapp.url, '_blank');
                Toast.show('WhatsApp opened in new tab. Click Send in WhatsApp to deliver.', 'info');
            }
            refreshShareHistory();
        } else {
            Toast.show(res.message || 'Document delivery failed.', 'error');
            refreshShareHistory();
        }
    }).catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Send & Share</span>';
        Toast.show('Error: ' + err.message, 'error');
    });
};
</script>
