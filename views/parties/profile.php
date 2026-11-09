<?php
/**
 * WTSBill ERP — Unified 360-Degree Party Profile
 * Displays complete partner overview, role capabilities (Customer, Supplier, Both),
 * sales invoices, purchase bills, quotations, purchase orders, payments, and ledger statement.
 */

use WTSBill\Core\Session;

$appName = \WTSBill\Core\Config::get('app.name', 'WTSBill ERP');
$party = $party ?? [];
$partyType = $partyType ?? ($party['party_type'] ?? 'Customer');
$summary = $summary ?? [];
$invoices = $invoices ?? [];
$bills = $bills ?? [];
$quotations = $quotations ?? [];
$purchaseOrders = $purchaseOrders ?? [];
$payments = $payments ?? [];
$ledger = $ledger ?? [];
$hasCustomerRole = $hasCustomerRole ?? false;
$hasSupplierRole = $hasSupplierRole ?? false;

$badgeStyle = match ($partyType) {
    'Both' => 'background: #E0E7FF; color: #4338CA; border: 1px solid #C7D2FE;',
    'Supplier' => 'background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;',
    default => 'background: #DAF1DE; color: #163832; border: 1px solid #8EB69B;',
};
?>

<div class="party-profile-container" style="padding: 1.5rem; max-width: 1440px; margin: 0 auto;">
    <!-- TOP NAVIGATION & BREADCRUMB -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.86rem; color: #64748B;">
            <a href="/parties" style="color: #235347; text-decoration: none; font-weight: 600;">&larr; Parties</a>
            <span>/</span>
            <span style="color: #1E293B; font-weight: 600;"><?= e($party['name']) ?></span>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/parties/<?= (int)$party['id'] ?>/statement/csv" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem; font-size: 0.84rem; font-weight: 600; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; color: #334155; text-decoration: none;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <span>Download Statement (CSV)</span>
            </a>
            <?php if ($hasCustomerRole): ?>
                <a href="/sales/invoices/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem; font-size: 0.84rem; font-weight: 600; background: #235347; border: none; border-radius: 6px; color: #FFFFFF; text-decoration: none;">
                    <span>+ New Invoice</span>
                </a>
            <?php endif; ?>
            <?php if ($hasSupplierRole): ?>
                <a href="/purchases/bills" class="btn" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem; font-size: 0.84rem; font-weight: 600; background: #0B2B26; border: none; border-radius: 6px; color: #DAF1DE; text-decoration: none;">
                    <span>+ Record Purchase</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- PROFILE HERO BANNER -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 1.15rem; align-items: center;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: #0B2B26; color: #DAF1DE; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; box-shadow: 0 4px 10px rgba(11,43,38,0.25);">
                    <?= strtoupper(substr($party['name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                        <h1 style="margin: 0; font-size: 1.45rem; font-weight: 700; color: #0F172A; letter-spacing: -0.01em;">
                            <?= e($party['name']) ?>
                        </h1>
                        <span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; <?= $badgeStyle ?>">
                            <?= e($partyType) ?>
                        </span>
                        <?php if ($party['is_active']): ?>
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; background: #DEF7EC; color: #03543F;">Active</span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; background: #FDE8E8; color: #9B1C1C;">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 1.25rem; font-size: 0.84rem; color: #64748B; margin-top: 0.35rem; flex-wrap: wrap;">
                        <span>Code: <strong style="color: #334155;"><?= e($party['party_code']) ?></strong></span>
                        <?php if (!empty($party['gstin'])): ?>
                            <span>GSTIN: <strong style="color: #334155;"><?= e($party['gstin']) ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($party['pan'])): ?>
                            <span>PAN: <strong style="color: #334155;"><?= e($party['pan']) ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($party['phone'])): ?>
                            <span>Phone: <strong style="color: #334155;"><?= e($party['phone']) ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($party['email'])): ?>
                            <span>Email: <strong style="color: #334155;"><?= e($party['email']) ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Role Badge Information -->
            <div style="text-align: right;">
                <div style="font-size: 0.78rem; color: #64748B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Role Status</div>
                <div style="font-size: 0.92rem; font-weight: 700; color: #1E293B; margin-top: 2px;">
                    <?php if ($partyType === 'Both'): ?>
                        Customer &amp; Supplier
                    <?php elseif ($partyType === 'Supplier'): ?>
                        Authorized Supplier
                    <?php else: ?>
                        Authorized Customer
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($partyType === 'Both'): ?>
            <div style="margin-top: 1.25rem; background: #EEF2FF; border: 1px solid #C7D2FE; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.82rem; color: #3730A3;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span><strong>Multi-Role Business Partner:</strong> This account is synchronized for both Sales (Invoices, Quotations) and Procurement (Bills, POs). Financial balances are consolidated automatically.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4 KPI SUMMARY CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.15rem; margin-bottom: 1.5rem;">
        <!-- Card 1: Outstanding Balance -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.15rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.78rem; color: #64748B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Outstanding Balance</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: <?= $summary['net_balance'] >= 0 ? '#047857' : '#B91C1C' ?>; margin-top: 0.25rem;">
                ₹ <?= number_format(abs($summary['net_balance']), 2) ?>
                <span style="font-size: 0.82rem; font-weight: 500; color: #64748B;"><?= $summary['net_balance'] >= 0 ? 'Dr (Receivable)' : 'Cr (Payable)' ?></span>
            </div>
            <?php if ($partyType === 'Both'): ?>
                <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem; display: flex; gap: 0.75rem;">
                    <span>Receivable: <strong style="color: #047857;">₹ <?= number_format($summary['receivable_balance'], 2) ?></strong></span>
                    <span>Payable: <strong style="color: #B91C1C;">₹ <?= number_format($summary['payable_balance'], 2) ?></strong></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card 2: Total Billed Amount -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.15rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.78rem; color: #64748B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Total Billed Turnover</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: #1E293B; margin-top: 0.25rem;">
                ₹ <?= number_format($summary['total_billed_sales'] + $summary['total_billed_purchases'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">
                <?php if ($partyType === 'Both'): ?>
                    Sales: ₹ <?= number_format($summary['total_billed_sales'], 2) ?> &bull; Purchases: ₹ <?= number_format($summary['total_billed_purchases'], 2) ?>
                <?php elseif ($partyType === 'Supplier'): ?>
                    Total Inward Bills from Vendor
                <?php else: ?>
                    Total Sales Invoices to Customer
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 3: Payments & Collections -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.15rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.78rem; color: #64748B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Total Payments &amp; Receipts</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: #235347; margin-top: 0.25rem;">
                ₹ <?= number_format($summary['total_receipts'] + $summary['total_payments_made'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">
                Receipts: ₹ <?= number_format($summary['total_receipts'], 2) ?> &bull; Paid: ₹ <?= number_format($summary['total_payments_made'], 2) ?>
            </div>
        </div>

        <!-- Card 4: Document Counts -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.15rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 0.78rem; color: #64748B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Documents &amp; Transactions</div>
            <div style="font-size: 1.45rem; font-weight: 700; color: #1E293B; margin-top: 0.25rem;">
                <?= (int)($summary['invoices_count'] + $summary['bills_count'] + $summary['quotations_count']) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">
                <?= (int)$summary['invoices_count'] ?> Invoices &bull; <?= (int)$summary['bills_count'] ?> Bills &bull; <?= (int)$summary['quotations_count'] ?> Quotes
            </div>
        </div>
    </div>

    <!-- TABBED SECTION CARD -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <!-- Tab Navigation Header -->
        <div style="display: flex; gap: 0.25rem; background: #0B2B26; padding: 0.5rem 1rem 0 1rem; border-bottom: 2px solid #235347; overflow-x: auto;">
            <button type="button" class="tab-btn active" onclick="switchProfileTab('overview', this)" style="background: #235347; color: #FFFFFF; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                Overview &amp; Contact
            </button>
            <?php if ($hasCustomerRole): ?>
                <button type="button" class="tab-btn" onclick="switchProfileTab('invoices', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                    Sales Invoices (<?= count($invoices) ?>)
                </button>
                <button type="button" class="tab-btn" onclick="switchProfileTab('quotations', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                    Quotations (<?= count($quotations) ?>)
                </button>
            <?php endif; ?>
            <?php if ($hasSupplierRole): ?>
                <button type="button" class="tab-btn" onclick="switchProfileTab('bills', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                    Purchase Bills (<?= count($bills) ?>)
                </button>
                <button type="button" class="tab-btn" onclick="switchProfileTab('pos', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                    Purchase Orders (<?= count($purchaseOrders) ?>)
                </button>
            <?php endif; ?>
            <button type="button" class="tab-btn" onclick="switchProfileTab('payments', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                Payments &amp; Receipts (<?= count($payments) ?>)
            </button>
            <button type="button" class="tab-btn" onclick="switchProfileTab('ledger', this)" style="background: transparent; color: #DAF1DE; border: none; padding: 0.6rem 1.1rem; border-radius: 8px 8px 0 0; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                Consolidated Ledger (<?= count($ledger) ?>)
            </button>
        </div>

        <!-- TAB CONTENT: OVERVIEW -->
        <div id="tab-overview" class="profile-tab-pane" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Left: Contact & Addresses -->
                <div>
                    <h3 style="font-size: 0.96rem; font-weight: 700; color: #0F172A; margin: 0 0 0.85rem 0; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.4rem;">
                        Contact &amp; Location Details
                    </h3>
                    <table style="width: 100%; font-size: 0.86rem; border-collapse: collapse;">
                        <tr><td style="padding: 0.4rem 0; color: #64748B; width: 140px;">Contact Person:</td><td style="font-weight: 600; color: #1E293B;"><?= e($party['contact_person'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Mobile / Phone:</td><td style="font-weight: 600; color: #1E293B;"><?= e($party['phone'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Alternate Phone:</td><td><?= e($party['alt_phone'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Email Address:</td><td><?= e($party['email'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">City / State:</td><td><?= e(implode(', ', array_filter([$party['city'], $party['state'], $party['pincode']]))) ?: '—' ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Billing Address:</td><td style="white-space: pre-line;"><?= e($party['billing_address'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Shipping Address:</td><td style="white-space: pre-line;"><?= e($party['shipping_address'] ?: ($party['billing_address'] ?: '—')) ?></td></tr>
                    </table>
                </div>

                <!-- Right: Financial & Statutory -->
                <div>
                    <h3 style="font-size: 0.96rem; font-weight: 700; color: #0F172A; margin: 0 0 0.85rem 0; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.4rem;">
                        Statutory, Tax &amp; Credit Terms
                    </h3>
                    <table style="width: 100%; font-size: 0.86rem; border-collapse: collapse;">
                        <tr><td style="padding: 0.4rem 0; color: #64748B; width: 140px;">GSTIN:</td><td style="font-family: monospace; font-weight: 600; color: #1E293B;"><?= e($party['gstin'] ?: 'Unregistered') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">PAN:</td><td style="font-family: monospace; font-weight: 600; color: #1E293B;"><?= e($party['pan'] ?: '—') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">State Code:</td><td><?= e($party['state_code'] ?: '27') ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Opening Balance:</td><td>₹ <?= number_format((float)$party['opening_balance'], 2) ?> (<?= e($party['opening_balance_type']) ?>)</td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Credit Limit:</td><td>₹ <?= number_format((float)$party['credit_limit'], 2) ?></td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Payment Terms:</td><td><?= (int)$party['payment_terms_days'] ?> Days</td></tr>
                        <tr><td style="padding: 0.4rem 0; color: #64748B;">Internal Notes:</td><td style="font-style: italic; color: #475569;"><?= e($party['notes'] ?: 'None') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB CONTENT: SALES INVOICES -->
        <?php if ($hasCustomerRole): ?>
            <div id="tab-invoices" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
                <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                            <th style="padding: 0.65rem 0.85rem;">Invoice No.</th>
                            <th style="padding: 0.65rem 0.85rem;">Date</th>
                            <th style="padding: 0.65rem 0.85rem;">Due Date</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Total Amount</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Balance Due</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Status</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="7" style="padding: 2rem; text-align: center; color: #64748B;">No sales invoices recorded for this customer yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr style="border-bottom: 1px solid #E2E8F0;">
                                    <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($inv['invoice_number']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($inv['invoice_date'] ?? $inv['created_at']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($inv['due_date'] ?? '—') ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 600;">₹ <?= number_format((float)$inv['grand_total'], 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; color: <?= (float)($inv['remaining_balance'] ?? 0) > 0 ? '#B91C1C' : '#047857' ?>;">₹ <?= number_format((float)($inv['remaining_balance'] ?? 0), 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;">
                                        <span style="display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 0.72rem; font-weight: 600; background: #DEF7EC; color: #03543F;"><?= e($inv['payment_status'] ?? 'PAID') ?></span>
                                    </td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;">
                                        <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" style="color: #235347; text-decoration: underline; font-weight: 600;">Print</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB CONTENT: QUOTATIONS -->
            <div id="tab-quotations" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
                <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                            <th style="padding: 0.65rem 0.85rem;">Quotation No.</th>
                            <th style="padding: 0.65rem 0.85rem;">Date</th>
                            <th style="padding: 0.65rem 0.85rem;">Valid Until</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Total Amount</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Status</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                            <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #64748B;">No quotations generated for this customer.</td></tr>
                        <?php else: ?>
                            <?php foreach ($quotations as $q): ?>
                                <tr style="border-bottom: 1px solid #E2E8F0;">
                                    <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($q['quotation_number']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($q['quotation_date'] ?? $q['created_at']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($q['valid_until'] ?? '—') ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 600;">₹ <?= number_format((float)$q['grand_total'], 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;"><?= e($q['status'] ?? 'SENT') ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;">
                                        <a href="/sales/quotations/<?= (int)$q['id'] ?>/print" target="_blank" style="color: #235347; text-decoration: underline; font-weight: 600;">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- TAB CONTENT: PURCHASE BILLS -->
        <?php if ($hasSupplierRole): ?>
            <div id="tab-bills" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
                <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                            <th style="padding: 0.65rem 0.85rem;">Bill No.</th>
                            <th style="padding: 0.65rem 0.85rem;">Date</th>
                            <th style="padding: 0.65rem 0.85rem;">Due Date</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Total Amount</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Balance Due</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #64748B;">No inward purchase bills recorded from this vendor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bills as $b): ?>
                                <tr style="border-bottom: 1px solid #E2E8F0;">
                                    <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($b['bill_number']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($b['bill_date'] ?? $b['created_at']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($b['due_date'] ?? '—') ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 600;">₹ <?= number_format((float)$b['total_amount'], 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; color: #B91C1C;">₹ <?= number_format((float)($b['remaining_balance'] ?? 0), 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;"><?= e($b['payment_status'] ?? 'UNPAID') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB CONTENT: PURCHASE ORDERS -->
            <div id="tab-pos" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
                <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                            <th style="padding: 0.65rem 0.85rem;">PO Number</th>
                            <th style="padding: 0.65rem 0.85rem;">Order Date</th>
                            <th style="padding: 0.65rem 0.85rem;">Branch</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: right;">Total Amount</th>
                            <th style="padding: 0.65rem 0.85rem; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchaseOrders)): ?>
                            <tr><td colspan="5" style="padding: 2rem; text-align: center; color: #64748B;">No purchase orders issued to this supplier yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($purchaseOrders as $po): ?>
                                <tr style="border-bottom: 1px solid #E2E8F0;">
                                    <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($po['order_number'] ?? ('PO-' . $po['id'])) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($po['order_date'] ?? $po['created_at']) ?></td>
                                    <td style="padding: 0.65rem 0.85rem;"><?= e($po['branch_name'] ?? 'Main') ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 600;">₹ <?= number_format((float)$po['total_amount'], 2) ?></td>
                                    <td style="padding: 0.65rem 0.85rem; text-align: center;"><?= e($po['status'] ?? 'PENDING') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- TAB CONTENT: PAYMENTS & RECEIPTS -->
        <div id="tab-payments" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
            <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                        <th style="padding: 0.65rem 0.85rem;">Reference No.</th>
                        <th style="padding: 0.65rem 0.85rem;">Date</th>
                        <th style="padding: 0.65rem 0.85rem;">Type</th>
                        <th style="padding: 0.65rem 0.85rem; text-align: right;">Amount</th>
                        <th style="padding: 0.65rem 0.85rem;">Payment Mode</th>
                        <th style="padding: 0.65rem 0.85rem;">Notes / Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #64748B;">No payment or receipt entries found for this party.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pmt): ?>
                            <tr style="border-bottom: 1px solid #E2E8F0;">
                                <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($pmt['doc_number'] ?? ('PMT-' . $pmt['id'])) ?></td>
                                <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($pmt['doc_date'] ?? '') ?></td>
                                <td style="padding: 0.65rem 0.85rem;">
                                    <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 0.74rem; font-weight: 600; <?= ($pmt['entry_type'] ?? '') === 'Receipt' ? 'background: #DEF7EC; color: #03543F;' : 'background: #FEF3C7; color: #92400E;' ?>">
                                        <?= e($pmt['entry_type'] ?? 'Payment') ?>
                                    </span>
                                </td>
                                <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 600;">₹ <?= number_format((float)$pmt['amount'], 2) ?></td>
                                <td style="padding: 0.65rem 0.85rem;"><?= e($pmt['payment_mode'] ?? 'Cash/Bank') ?></td>
                                <td style="padding: 0.65rem 0.85rem; color: #64748B;"><?= e($pmt['reference_no'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB CONTENT: CONSOLIDATED LEDGER -->
        <div id="tab-ledger" class="profile-tab-pane" style="display: none; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div style="font-size: 0.88rem; font-weight: 600; color: #1E293B;">
                    Consolidated Transaction Ledger &amp; Running Balance
                </div>
                <a href="/parties/<?= (int)$party['id'] ?>/statement/csv" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.35rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #FFFFFF; color: #334155; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Export Statement (CSV)</span>
                </a>
            </div>

            <table class="table" style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F1F5F9; color: #475569; border-bottom: 1px solid #CBD5E1;">
                        <th style="padding: 0.65rem 0.85rem;">Date</th>
                        <th style="padding: 0.65rem 0.85rem;">Type</th>
                        <th style="padding: 0.65rem 0.85rem;">Reference</th>
                        <th style="padding: 0.65rem 0.85rem;">Role</th>
                        <th style="padding: 0.65rem 0.85rem;">Description</th>
                        <th style="padding: 0.65rem 0.85rem; text-align: right;">Debit (₹)</th>
                        <th style="padding: 0.65rem 0.85rem; text-align: right;">Credit (₹)</th>
                        <th style="padding: 0.65rem 0.85rem; text-align: right;">Running Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="8" style="padding: 2rem; text-align: center; color: #64748B;">No transactions posted to this party's ledger yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $entry): ?>
                            <tr style="border-bottom: 1px solid #E2E8F0;">
                                <td style="padding: 0.65rem 0.85rem; color: #475569;"><?= e($entry['date']) ?></td>
                                <td style="padding: 0.65rem 0.85rem; font-weight: 600;"><?= e($entry['type']) ?></td>
                                <td style="padding: 0.65rem 0.85rem; font-family: monospace;"><?= e($entry['reference']) ?></td>
                                <td style="padding: 0.65rem 0.85rem;">
                                    <span style="font-size: 0.74rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; background: #E2E8F0; color: #334155;">
                                        <?= e($entry['role'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="padding: 0.65rem 0.85rem; color: #64748B;"><?= e($entry['description']) ?></td>
                                <td style="padding: 0.65rem 0.85rem; text-align: right; color: <?= (float)$entry['debit'] > 0 ? '#047857' : '#94A3B8' ?>; font-weight: <?= (float)$entry['debit'] > 0 ? '600' : '400' ?>;">
                                    <?= (float)$entry['debit'] > 0 ? ('₹ ' . number_format((float)$entry['debit'], 2)) : '—' ?>
                                </td>
                                <td style="padding: 0.65rem 0.85rem; text-align: right; color: <?= (float)$entry['credit'] > 0 ? '#B91C1C' : '#94A3B8' ?>; font-weight: <?= (float)$entry['credit'] > 0 ? '600' : '400' ?>;">
                                    <?= (float)$entry['credit'] > 0 ? ('₹ ' . number_format((float)$entry['credit'], 2)) : '—' ?>
                                </td>
                                <td style="padding: 0.65rem 0.85rem; text-align: right; font-weight: 700; color: <?= (float)($entry['running_balance'] ?? 0) >= 0 ? '#047857' : '#B91C1C' ?>;">
                                    ₹ <?= number_format(abs((float)($entry['running_balance'] ?? 0)), 2) ?> <?= (float)($entry['running_balance'] ?? 0) >= 0 ? 'Dr' : 'Cr' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.switchProfileTab = function(tabKey, btn) {
    document.querySelectorAll('.profile-tab-pane').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.style.background = 'transparent';
        el.style.color = '#DAF1DE';
    });

    var target = document.getElementById('tab-' + tabKey);
    if (target) {
        target.style.display = 'block';
    }
    if (btn) {
        btn.style.background = '#235347';
        btn.style.color = '#FFFFFF';
    }
};
</script>

