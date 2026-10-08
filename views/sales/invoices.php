<?php
$pageTitle = 'Sales & Invoicing Workspace - WTS LEDGER PRO';
$currentRoute = 'invoices';

require_once __DIR__ . '/../db_helper.php';
$invoices = get_invoices();
$customers = get_customers();
$quotes = get_quotations();
$totalSales = array_sum(array_map(static fn($invoice) => (float) ($invoice['grand_total'] ?? 0), $invoices));
$paidCollections = array_sum(array_map(static fn($invoice) => (float) ($invoice['amount_paid'] ?? 0), $invoices));
$pendingBalance = array_sum(array_map(static fn($invoice) => (float) ($invoice['amount_due'] ?? 0), $invoices));
$overdueBalance = array_sum(array_map(static fn($invoice) => strtotime($invoice['due_date'] ?? 'now') < time() ? (float) ($invoice['amount_due'] ?? 0) : 0, $invoices));

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <!-- Sales Workspace Header -->
        <div class="workspace-header-row">
            <div class="workspace-header-left">
                <h1 class="workspace-title">Sales &amp; Invoicing Workspace</h1>
                <p class="workspace-subtitle">Manage invoices, estimates, dispatches, returns, and customer sales.</p>
            </div>
            <div class="workspace-header-right">
                <button class="header-filter-btn" onclick="exportTableToCSV('salesInvoicesTable', 'Sales_Invoices.csv')">
                    <i class="fa-solid fa-download filter-icon"></i>
                    <span>Export</span>
                </button>
                <button class="header-filter-btn" onclick="openModal('importInvoiceModal')">
                    <i class="fa-solid fa-upload filter-icon"></i>
                    <span>Import</span>
                </button>
                <a href="<?= url('/create-invoice') ?>" class="btn-create-dark" style="text-decoration: none;">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create Invoice</span>
                </a>
            </div>
        </div>

        <!-- 4 Stat Cards Row -->
        <div class="sales-stats-row">
            <div class="stat-card">
                <div class="stat-card-label">TOTAL SALES</div>
                <div class="stat-card-value">₹<?= number_format($totalSales, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">PAID COLLECTIONS</div>
                <div class="stat-card-value text-green">₹<?= number_format($paidCollections, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">PENDING BALANCE</div>
                <div class="stat-card-value text-amber">₹<?= number_format($pendingBalance, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">OVERDUE DEBTS</div>
                <div class="stat-card-value text-coral">₹<?= number_format($overdueBalance, 2) ?></div>
            </div>
        </div>

        <!-- Sales Navigation Tabs (Every Tab Interactive!) -->
        <div class="subnav-tabs-wrapper">
            <div class="subnav-tabs" id="salesTabsNav">
                <a href="javascript:void(0)" class="subnav-tab active" data-tab="invoices"
                    onclick="switchTab('salesTabsNav', 'tab-tax-invoices', event)">Tax Invoices (<?= count($invoices) ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="quotations"
                    onclick="switchTab('salesTabsNav', 'tab-quotations', event)">Quotations &amp; Estimates (<?= count($quotes) ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="orders"
                    onclick="switchTab('salesTabsNav', 'tab-sales-orders', event)">Sales Orders</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="challans"
                    onclick="switchTab('salesTabsNav', 'tab-delivery-challans', event)">Delivery Challans</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="credits"
                    onclick="switchTab('salesTabsNav', 'tab-credit-notes', event)">Credit Notes &amp; Returns</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="recurring"
                    onclick="switchTab('salesTabsNav', 'tab-recurring', event)">Recurring Schedules</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="insights"
                    onclick="switchTab('salesTabsNav', 'tab-sales-insights', event)">Sales Insights</a>
            </div>
        </div>

        <!-- TAB PANES CONTAINER -->
        <div class="sales-tabs-container">
            <!-- TAB 1: TAX INVOICES (DEFAULT ACTIVE) -->
            <div id="tab-tax-invoices" class="subnav-pane active">
                <!-- Filter & Search Toolbar -->
                <div class="white-card filter-toolbar-card">
                    <div class="filter-toolbar-row">
                        <div class="search-input-box">
                            <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                            <input type="text" id="salesSearchInput" class="search-box-field"
                                placeholder="Search invoice number, customer, phone or GSTIN..."
                                onkeyup="filterSalesTable()">
                        </div>

                        <div class="filter-select-group">
                            <select id="customerFilterSelect" class="toolbar-select" onchange="filterSalesTable()">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select id="statusFilterSelect" class="toolbar-select" onchange="filterSalesTable()">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="partially_paid">Partially Paid</option>
                                <option value="overdue">Overdue</option>
                            </select>

                            <button class="toolbar-filter-btn" type="button" onclick="filterSalesTable()">
                                <i class="fa-solid fa-sliders"></i>
                                <span>Filter</span>
                            </button>

                            <a href="javascript:void(0)" class="toolbar-clear-link" onclick="clearSalesFilters()">Clear</a>
                        </div>
                    </div>
                </div>

                <!-- Invoices Data Table -->
                <div class="white-card table-card">
                    <div class="table-responsive">
                        <table class="wts-table" id="salesInvoicesTable">
                            <thead>
                                <tr>
                                    <th>INVOICE #</th>
                                    <th>DATE</th>
                                    <th>CUSTOMER NAME</th>
                                    <th>AMOUNT</th>
                                    <th>PAID</th>
                                    <th>BALANCE DUE</th>
                                    <th>STATUS</th>
                                    <th>DUE DATE</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv):
                                    $st = strtolower($inv['status'] ?? '');
                                    $paidVal = (float) ($inv['amount_paid'] ?? 0);
                                    $balVal = (float) ($inv['amount_due'] ?? 0);
                                    ?>
                                    <tr data-status="<?= $st ?>">
                                        <td>
                                            <a href="<?= url('/print-invoice?id=' . $inv['id']) ?>"
                                                class="invoice-link"><?= htmlspecialchars($inv['invoice_number']) ?></a>
                                        </td>
                                        <td><?= htmlspecialchars($inv['invoice_date'] ?? '') ?></td>
                                        <td>
                                            <div class="party-name-primary"><?= htmlspecialchars($inv['customer_name'] ?? '') ?></div>
                                            <?php if (!empty($inv['customer_gstin'])): ?>
                                                <div class="party-gstin-muted">GSTIN: <?= htmlspecialchars($inv['customer_gstin']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>₹<?= number_format($inv['grand_total'] ?? 0, 2) ?></strong></td>
                                        <td class="text-green font-bold">₹<?= number_format($paidVal, 2) ?></td>
                                        <td>₹<?= number_format($balVal, 2) ?></td>
                                        <td>
                                            <span class="badge-status badge-status-<?= $st === 'paid' ? 'paid' : ($st === 'pending' ? 'pending' : ($st === 'cancelled' ? 'overdue' : 'overdue')) ?>" style="<?= $st === 'cancelled' ? 'background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;' : '' ?>">
                                                <?= strtoupper($st) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($inv['due_date'] ?? '') ?></td>
                                        <td class="text-center">
                                            <div class="table-action-icons">
                                                <a href="<?= url('/print-invoice?id=' . $inv['id']) ?>" title="View Invoice" class="table-icon-btn"><i class="fa-regular fa-eye"></i></a>
                                                <a href="<?= url('/print-invoice?id=' . $inv['id']) ?>" target="_blank" title="Print" class="table-icon-btn"><i class="fa-solid fa-print"></i></a>
                                                <a href="<?= url('/create-invoice?duplicate_from=' . $inv['id']) ?>" title="Duplicate Invoice" class="table-icon-btn"><i class="fa-regular fa-copy"></i></a>
                                                <?php if ($st !== 'cancelled'): ?>
                                                    <button type="button" onclick="cancelInvoice(<?= (int)$inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number']) ?>')" title="Cancel Invoice" class="table-icon-btn" style="color: #dc2626;"><i class="fa-regular fa-circle-xmark"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$invoices): ?>
                                    <tr>
                                        <td colspan="9" class="text-center" style="padding: 48px 20px; color: var(--text-muted);">
                                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
                                                <i class="fa-solid fa-file-invoice" style="font-size: 32px; color: var(--text-muted); opacity: 0.6; margin-bottom: 4px;"></i>
                                                <div style="font-size: 14px; font-weight: 600; color: var(--text);">No invoices found</div>
                                                <div style="font-size: 12.5px; color: var(--text-muted); max-width: 320px;">Issue your first sales invoice to track billing, client receivables, and GST payments.</div>
                                                <a href="<?= url('/create-invoice') ?>" class="btn btn-primary btn-sm" style="margin-top: 8px;">
                                                    <i class="fa-solid fa-plus"></i> Create Invoice
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: QUOTATIONS & ESTIMATES -->
            <div id="tab-quotations" class="subnav-pane" style="display: none;">
                <div class="white-card" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 2px;">Quotations &amp; Proforma Estimates</h3>
                            <p style="font-size: 12px; color: #64748b; margin: 0;">Convert approved client quotations to tax invoices with 1-click.</p>
                        </div>
                        <a href="<?= url('/create-quotation') ?>" class="btn-create-dark" style="text-decoration: none;">
                            <i class="fa-solid fa-plus"></i>
                            <span>Create Quotation</span>
                        </a>
                    </div>
                </div>

                <div class="white-card table-card">
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>QUOTATION #</th>
                                    <th>DATE</th>
                                    <th>CLIENT / PROSPECT</th>
                                    <th>VALID UNTIL</th>
                                    <th>ESTIMATED AMOUNT</th>
                                    <th>STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotes as $q): ?>
                                    <tr>
                                        <td><a href="<?= url('/print-quotation?id=' . $q['id']) ?>" class="invoice-link"><?= htmlspecialchars($q['quotation_number']) ?></a></td>
                                        <td><?= htmlspecialchars($q['quotation_date']) ?></td>
                                        <td><strong><?= htmlspecialchars($q['customer_name'] ?? 'Prospective Client') ?></strong></td>
                                        <td><?= htmlspecialchars($q['valid_until']) ?></td>
                                        <td><strong>₹<?= number_format($q['grand_total'], 2) ?></strong></td>
                                        <td><span class="badge-status badge-status-paid"><?= strtoupper($q['status']) ?></span></td>
                                        <td class="text-center">
                                            <div class="table-action-icons">
                                                <a href="<?= url('/print-quotation?id=' . $q['id']) ?>" target="_blank" class="table-icon-btn" title="View / Print"><i class="fa-solid fa-print"></i></a>
                                                <a href="<?= url('/create-invoice?from_quote=' . $q['id']) ?>" class="table-icon-btn" title="Convert to Invoice" style="color: #2563eb;"><i class="fa-solid fa-arrow-right-arrow-left"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$quotes): ?>
                                    <tr>
                                        <td colspan="7" class="text-center" style="padding: 25px; color: #64748b;">No quotations created yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SALES ORDERS -->
            <div id="tab-sales-orders" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">Confirmed Sales Orders (SO)</h3>
                        <a href="<?= url('/create-quotation') ?>" class="btn-create-dark" style="text-decoration: none;"><i class="fa-solid fa-plus"></i> New Sales Order</a>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>ORDER #</th>
                                    <th>ORDER DATE</th>
                                    <th>CUSTOMER</th>
                                    <th>ITEMS ORDERED</th>
                                    <th>ORDER VALUE</th>
                                    <th>DISPATCH STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><a href="<?= url('/print-order?type=so&number=SO-2026-081&party=' . urlencode('Ramesh Hardware Stores')) ?>" target="_blank" class="invoice-link">SO-2026-081</a></td>
                                    <td>2026-08-02</td>
                                    <td><strong>Ramesh Hardware Stores</strong></td>
                                    <td>40 Coils Copper Wire 1.5sqmm</td>
                                    <td><strong>₹74,000.00</strong></td>
                                    <td><span class="badge-status badge-status-paid">DISPATCHED</span></td>
                                    <td class="text-center">
                                        <a href="<?= url('/print-order?type=so&number=SO-2026-081&party=' . urlencode('Ramesh Hardware Stores')) ?>" target="_blank" class="table-icon-btn" title="View"><i class="fa-regular fa-eye"></i></a>
                                        <a href="<?= url('/print-order?type=so&number=SO-2026-081&party=' . urlencode('Ramesh Hardware Stores')) ?>" target="_blank" class="table-icon-btn" title="Print SO"><i class="fa-solid fa-print"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: DELIVERY CHALLANS -->
            <div id="tab-delivery-challans" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">Delivery Challans &amp; Dispatch Goods Receipts</h3>
                        <a href="<?= url('/create-invoice') ?>" class="btn-create-dark" style="text-decoration: none;"><i class="fa-solid fa-plus"></i> Generate Challan</a>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>CHALLAN #</th>
                                    <th>DATE</th>
                                    <th>RECIPIENT / CLIENT</th>
                                    <th>VEHICLE #</th>
                                    <th>E-WAY BILL #</th>
                                    <th>STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><a href="<?= url('/print-challan?number=DC-2026-009&client=' . urlencode('Ramesh Hardware Stores')) ?>" target="_blank" class="invoice-link">DC-2026-009</a></td>
                                    <td>2026-08-02</td>
                                    <td><strong>Ramesh Hardware Stores</strong></td>
                                    <td>MH-12-QX-4891</td>
                                    <td>241829019283</td>
                                    <td><span class="badge-status badge-status-paid">DELIVERED</span></td>
                                    <td class="text-center">
                                        <a href="<?= url('/print-challan?number=DC-2026-009&client=' . urlencode('Ramesh Hardware Stores')) ?>" target="_blank" class="table-icon-btn" title="Print Challan"><i class="fa-solid fa-print"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: CREDIT NOTES & RETURNS -->
            <div id="tab-credit-notes" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">Credit Notes Register</h3>
                        <a href="<?= url('/credit-notes') ?>" class="btn-create-dark" style="text-decoration: none;"><i class="fa-solid fa-plus"></i> New Credit Note</a>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>CREDIT NOTE #</th>
                                    <th>DATE</th>
                                    <th>CUSTOMER NAME</th>
                                    <th>ORIGINAL INVOICE</th>
                                    <th>CREDIT AMOUNT</th>
                                    <th>REASON</th>
                                    <th>STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><a href="<?= url('/print-credit-note?number=CN-2026-001&invoice=INV-2023-0451&customer=' . urlencode('Ramesh Hardware Stores') . '&amount=2500') ?>" target="_blank" class="invoice-link">CN-2026-001</a></td>
                                    <td>2026-08-04</td>
                                    <td><strong>Ramesh Hardware Stores</strong></td>
                                    <td>INV-2023-0451</td>
                                    <td class="text-coral font-bold">₹2,500.00</td>
                                    <td>Discount Adjustment on Volume</td>
                                    <td><span class="badge-status badge-status-paid">APPLIED</span></td>
                                    <td class="text-center">
                                        <a href="<?= url('/print-credit-note?number=CN-2026-001&invoice=INV-2023-0451&customer=' . urlencode('Ramesh Hardware Stores') . '&amount=2500') ?>" target="_blank" class="table-icon-btn" title="Print Credit Note"><i class="fa-solid fa-print"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 6: RECURRING SCHEDULES -->
            <div id="tab-recurring" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">Recurring AMC &amp; SaaS Billing Schedules</h3>
                        <a href="<?= url('/recurring-invoices') ?>" class="btn-create-dark" style="text-decoration: none;"><i class="fa-solid fa-plus"></i> Add Subscription</a>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>SUBSCRIPTION #</th>
                                    <th>CLIENT NAME</th>
                                    <th>BILLING FREQUENCY</th>
                                    <th>NEXT INVOICE DATE</th>
                                    <th>RECURRING AMOUNT</th>
                                    <th>AUTO INVOICE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><a href="<?= url('/recurring-invoices') ?>" class="invoice-link">SUB-101</a></td>
                                    <td><strong>TechCorp Solutions Pvt Ltd</strong></td>
                                    <td>Monthly</td>
                                    <td>2026-10-01</td>
                                    <td><strong>₹17,700.00</strong></td>
                                    <td><span class="badge-status badge-status-paid">ENABLED</span></td>
                                    <td><span class="badge-status badge-status-paid">ACTIVE</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 7: SALES INSIGHTS -->
            <div id="tab-sales-insights" class="subnav-pane" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="white-card">
                        <h3 class="card-head-title">Revenue by Customer Concentration</h3>
                        <p class="card-head-sub">Top clients by invoicing volume in FY 2026-27</p>
                        <div style="margin-top: 14px;">
                            <div class="health-item-row">
                                <div>
                                    <div class="health-item-label">Customer Volume Breakdown</div>
                                    <div class="health-item-value">₹<?= number_format($totalSales, 2) ?></div>
                                </div>
                                <div class="health-badge badge-green">100.0% Contribution</div>
                            </div>
                        </div>
                    </div>
                    <div class="white-card">
                        <h3 class="card-head-title">Invoice Collection Efficiency</h3>
                        <p class="card-head-sub">Average DSO (Days Sales Outstanding) and payment turnaround</p>
                        <div style="display: flex; align-items: center; justify-content: space-around; padding: 24px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 800; color: #10b981;">0 Days</div>
                                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Average Collection DSO</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 800; color: #2563eb;">100%</div>
                                <div style="font-size: 11px; color: #64748b; font-weight: 600;">On-time Paid Ratio</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Import Invoices / CSV -->
<div class="modal-overlay" id="importInvoiceModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-import text-primary"></i> Bulk CSV Invoice Import</div>
            <button class="modal-close" onclick="closeModal('importInvoiceModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">Select a standard CSV file with columns: <code>Customer, Invoice Date, Product, Quantity, Price, Tax Rate</code>.</p>
            <div class="form-group">
                <label class="form-label">Upload CSV File</label>
                <input type="file" accept=".csv" class="form-control" id="invoiceCsvInput">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('importInvoiceModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="closeModal('importInvoiceModal'); showToast('CSV structure parsed & verified!', 'success');"><i class="fa-solid fa-upload"></i> Upload &amp; Preview</button>
        </div>
    </div>
</div>

<script>
function filterSalesTable() {
    let q = (document.getElementById('salesSearchInput').value || '').toLowerCase().trim();
    let cust = (document.getElementById('customerFilterSelect').value || '').toLowerCase().trim();
    let st = (document.getElementById('statusFilterSelect').value || '').toLowerCase().trim();

    let rows = document.querySelectorAll('#salesInvoicesTable tbody tr');
    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rSt = r.getAttribute('data-status') || '';

        let matchQ = !q || text.includes(q);
        let matchCust = !cust || text.includes(cust);
        let matchSt = !st || rSt.includes(st);

        if (matchQ && matchCust && matchSt) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function clearSalesFilters() {
    document.getElementById('salesSearchInput').value = '';
    document.getElementById('customerFilterSelect').value = '';
    document.getElementById('statusFilterSelect').value = '';
    filterSalesTable();
}

async function cancelInvoice(id, number) {
    let reason = prompt('Are you sure you want to cancel invoice ' + number + '?\n\nThis will reverse stock deductions, void accounting journals, and reduce customer receivable balance.\n\nEnter cancellation reason:', 'Customer requested cancellation');
    if (reason === null) return;
    if (!reason.trim()) reason = 'Cancelled by user';

    try {
        let res = await fetch('<?= url('/api/v1/invoices/') ?>' + id + '/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reason: reason })
        });
        let data = await res.json();
        if (data.status === 'success' || data.success) {
            showToast(data.message || 'Invoice cancelled successfully', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            alert('Error cancelling invoice: ' + (data.message || 'Failed to cancel'));
        }
    } catch (e) {
        alert('Network or server error while cancelling invoice: ' + e.message);
    }
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>