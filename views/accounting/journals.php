<?php
/**
 * WTSBill ERP — Journal Entries View & Double-Entry Posting Workflow
 *
 * @var array $journalEntries
 * @var array $accounts
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Journal Entries</h1>
        <p class="page-subtitle">Manual and automated double-entry postings with strict balance validation (&sum; Debit = &sum; Credit)</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="journal-search" class="form-control" placeholder="Search entry #..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openJournalModal()">+ New Journal Entry</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Journal Entry Register (<span id="journals-count"><?= count($journalEntries ?? []) ?></span> Entries)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="journals-table">
            <thead>
                <tr>
                    <th>Entry #</th>
                    <th>Date</th>
                    <th>Narration / Purpose</th>
                    <th style="text-align: right;">Total Debit</th>
                    <th style="text-align: right;">Total Credit</th>
                    <th style="text-align: center;">Balance Status</th>
                </tr>
            </thead>
            <tbody id="journals-table-body">
                <?php if (!empty($journalEntries)): ?>
                    <?php foreach ($journalEntries as $je): ?>
                        <tr>
                            <td><strong><?= e($je['entry_number']) ?></strong></td>
                            <td><?= e(format_date($je['entry_date'])) ?></td>
                            <td><?= e($je['narration'] ?? 'General ledger adjustment') ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-primary);"><?= format_currency((float)$je['total_debit']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-success);"><?= format_currency((float)$je['total_credit']) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-success">&check; BALANCED</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-journals-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No journal entries posted.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Double-Entry Journal Modal -->
<div id="journal-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="journal-modal-title">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="journal-modal-title" class="modal-title">Post Double-Entry Journal</h3>
            <button type="button" class="modal-close" onclick="window.closeJournalModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="journal-form" onsubmit="window.saveJournal(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="journal-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="je-date">Entry Date <span style="color: var(--danger);">*</span></label>
                            <input type="date" id="je-date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="je-amount">Entry Amount (₹) <span style="color: var(--danger);">*</span></label>
                            <input type="number" id="je-amount" name="amount" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="je-debit-acc">Debit Account (Dr) <span style="color: var(--danger);">*</span></label>
                        <select id="je-debit-acc" name="debit_account_id" class="form-control" required>
                            <option value="">-- Select Debit Ledger --</option>
                            <?php foreach ($accounts ?? [] as $acc): ?>
                                <option value="<?= e((string)$acc['id']) ?>">
                                    [<?= e($acc['account_code']) ?>] <?= e($acc['account_name']) ?> (<?= e($acc['account_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="je-credit-acc">Credit Account (Cr) <span style="color: var(--danger);">*</span></label>
                        <select id="je-credit-acc" name="credit_account_id" class="form-control" required>
                            <option value="">-- Select Credit Ledger --</option>
                            <?php foreach ($accounts ?? [] as $acc): ?>
                                <option value="<?= e((string)$acc['id']) ?>">
                                    [<?= e($acc['account_code']) ?>] <?= e($acc['account_name']) ?> (<?= e($acc['account_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="je-narration">Narration / Voucher Remarks <span style="color: var(--danger);">*</span></label>
                        <textarea id="je-narration" name="narration" class="form-control" rows="2" placeholder="e.g. Being transfer of funds from Current A/c to Petty Cash" required></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeJournalModal()">Cancel</button>
                <button type="submit" id="journal-submit-btn" class="btn btn-primary">Post Balanced Journal</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('journal-search', 'journals-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('journal-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeJournalModal();
                }
            }
        });
    });

    window.openJournalModal = function() {
        const form = document.getElementById('journal-form');
        form.reset();
        document.getElementById('journal-modal-error').style.display = 'none';
        document.getElementById('journal-modal-error').textContent = '';
        document.getElementById('journal-submit-btn').disabled = false;
        document.getElementById('journal-submit-btn').textContent = 'Post Balanced Journal';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('journal-modal');
        } else {
            document.getElementById('journal-modal').classList.add('active');
        }
    };

    window.closeJournalModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('journal-modal');
        } else {
            document.getElementById('journal-modal').classList.remove('active');
        }
    };

    window.saveJournal = async function(e) {
        e.preventDefault();
        const form = document.getElementById('journal-form');
        const errBox = document.getElementById('journal-modal-error');
        const submitBtn = document.getElementById('journal-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/accounts/journals', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: formData
            });

            const result = await resp.json();

            if (resp.ok && result.success) {
                window.closeJournalModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Journal entry posted successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to post journal entry.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Balanced Journal';
            }
        } catch (err) {
            console.error('Save journal error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Post Balanced Journal';
        }
    };
</script>
