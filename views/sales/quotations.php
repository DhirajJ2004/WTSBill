<?php
$pageTitle = 'Quotations & Estimates - WTSBill ERP';
$pageHeader = 'Quotations & Proposal Estimates';
$currentRoute = 'quotations';

require_once __DIR__ . '/../db_helper.php';
$quotations = get_quotations();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-file-signature"></i> All Quotations & Estimates</div>
                <a href="<?= url('/create-quotation') ?>" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> + Create New Quotation
                </a>
            </div>

            <!-- Filters -->
            <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                <input type="text" id="quoteSearch" class="form-control" placeholder="Search quote # or customer..." style="max-width: 320px;" onkeyup="filterQuotes()">
                <select id="statusFilter" class="form-control" style="max-width: 180px;" onchange="filterQuotes()">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                    <option value="expired">Expired</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table" id="quoteTable">
                    <thead>
                        <tr>
                            <th>Quotation #</th>
                            <th>Customer Name</th>
                            <th>Quote Date</th>
                            <th>Valid Until</th>
                            <th>Grand Total</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotations as $q): ?>
                            <tr data-status="<?= strtolower($q['status'] ?? 'sent') ?>">
                                <td><strong><?= htmlspecialchars($q['quotation_number']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($q['customer_name'] ?? 'Walk-in Client') ?></strong></td>
                                <td><?= htmlspecialchars($q['quotation_date'] ?? date('Y-m-d')) ?></td>
                                <td><?= htmlspecialchars($q['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))) ?></td>
                                <td><strong>₹<?= number_format($q['grand_total'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <?php 
                                    $st = strtolower($q['status'] ?? 'sent');
                                    $stBadge = 'info';
                                    if ($st === 'accepted') $stBadge = 'success';
                                    elseif ($st === 'rejected') $stBadge = 'danger';
                                    elseif ($st === 'draft') $stBadge = 'secondary';
                                    elseif ($st === 'expired') $stBadge = 'warning';
                                    ?>
                                    <span class="badge badge-<?= $stBadge ?>"><?= strtoupper(htmlspecialchars($st)) ?></span>
                                </td>
                                <td class="text-right">
                                    <a href="<?= url('/print-quotation?id=' . $q['id']) ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i> Print</a>
                                    <?php if ($st === 'accepted' || $st === 'sent'): ?>
                                        <button class="btn btn-success btn-sm" onclick="convertQuoteToInvoice('<?= htmlspecialchars($q['quotation_number']) ?>')"><i class="fa-solid fa-bolt"></i> Convert to Invoice</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function filterQuotes() {
    let q = document.getElementById('quoteSearch').value.toLowerCase();
    let st = document.getElementById('statusFilter').value.toLowerCase();
    let rows = document.querySelectorAll('#quoteTable tbody tr');
    
    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rSt = r.getAttribute('data-status');
        
        let matchText = !q || text.includes(q);
        let matchSt = !st || rSt === st;
        
        if (matchText && matchSt) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function convertQuoteToInvoice(quoteNum) {
    if (confirm('Convert Quotation ' + quoteNum + ' directly into a Sales Invoice?')) {
        window.location.href = (window.baseUrl || '') + '/create-invoice?from_quote=' + encodeURIComponent(quoteNum);
    }
}
<?php if (!empty($_GET['created'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    if (window.showToast) {
        window.showToast('Quotation <?= htmlspecialchars($_GET['created']) ?> saved and dispatched successfully!', 'success', 'Quotation Created');
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
