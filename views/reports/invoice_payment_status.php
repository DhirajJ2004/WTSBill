<?php
$title = $title ?? 'Invoice Payment Status Report';
$report = $report ?? [];
$summary = $report['summary'] ?? [
    'pending' => ['count' => 0, 'total_invoice_amount' => 0.0, 'total_outstanding_amount' => 0.0],
    'paid' => ['count' => 0, 'total_invoice_amount' => 0.0, 'total_amount_received' => 0.0],
    'partially_paid' => ['count' => 0, 'total_invoice_amount' => 0.0, 'total_amount_received' => 0.0, 'total_remaining_balance' => 0.0],
    'total_outstanding' => 0.0,
];
$records = $report['records'] ?? [];
$filteredTotals = $report['filtered_totals'] ?? [
    'count' => 0,
    'total_invoice_amount' => 0.0,
    'total_paid_amount' => 0.0,
    'total_remaining_balance' => 0.0,
];
$pagination = $report['pagination'] ?? [
    'total' => 0,
    'page' => 1,
    'per_page' => 25,
    'total_pages' => 1,
    'from' => 0,
    'to' => 0,
];
$branches = $branches ?? [];
$filters = $filters ?? [];
$sort = $report['sort'] ?? ['field' => 'invoice_date', 'direction' => 'desc'];

// Helper to build filter query string for pagination, sorting, and export
$buildQuery = function(array $overrides = []) use ($filters, $sort): string {
    $merged = array_merge($filters, $overrides);
    // Remove empty parameters
    $clean = array_filter($merged, function($v) {
        return $v !== null && $v !== '' && $v !== 'all';
    });
    return http_build_query($clean);
};

$exportQuery = $buildQuery(['page' => null, 'per_page' => null]);
$exportUrl = '/reports/invoice-payment-status/export' . ($exportQuery ? '?' . $exportQuery : '');
?>

<div class="content-wrapper" style="padding: 24px; max-width: 1400px; margin: 0 auto;">
    <!-- TOP BREADCRUMB & HEADER ACTION BAR -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 6px;">
                <a href="/reports/profit-loss" style="color: #64748b; text-decoration: none;">Reports</a>
                <span>/</span>
                <span style="color: #0B2B26; font-weight: 600;">Payment Status</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #0B2B26; margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>Invoice Payment Status Report</span>
                <span style="font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #DAF1DE; color: #235347;">Live Audit</span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Comprehensive breakdown of unpaid, fully paid, and partially paid sales invoices with real-time outstanding balances.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <!-- PROMINENT DOWNLOAD EXCEL BUTTON -->
            <a href="<?= e($exportUrl) ?>" class="btn-download-excel" id="btn-download-excel" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; background: #235347; color: #FFFFFF; border: 1px solid #163832; text-decoration: none; box-shadow: 0 2px 4px rgba(35, 83, 71, 0.25); transition: all 0.2s ease;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="8" y1="13" x2="16" y2="13"></line>
                    <line x1="8" y1="17" x2="16" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Download Excel (.xlsx)</span>
            </a>

            <!-- PRINT BUTTON -->
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; background: #FFFFFF; color: #334155; border: 1px solid #CBD5E1; cursor: pointer; transition: all 0.2s ease;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print</span>
            </button>
        </div>
    </div>

    <!-- SUMMARY CARDS (SECTION 2: PENDING, PAID, PARTIALLY PAID, TOTAL OUTSTANDING) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 24px;">
        <!-- Card A: Pending Invoices -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 4px solid #EF4444; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #DC2626; text-transform: uppercase; letter-spacing: 0.05em;">Pending Invoices (Unpaid)</div>
                    <div style="font-size: 26px; font-weight: 700; color: #0B2B26; margin-top: 6px;"><?= (int)$summary['pending']['count'] ?> <span style="font-size: 13px; font-weight: 500; color: #64748b;">bills</span></div>
                </div>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #FEE2E2; display: flex; align-items: center; justify-content: center; color: #DC2626;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
            </div>
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; justify-content: space-between; font-size: 12px;">
                <span style="color: #64748B;">Total Original: <strong style="color: #1E293B;">₹<?= number_format($summary['pending']['total_invoice_amount'], 2) ?></strong></span>
                <span style="color: #DC2626; font-weight: 600;">Due: ₹<?= number_format($summary['pending']['total_outstanding_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Card B: Paid Invoices -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 4px solid #10B981; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.05em;">Paid Invoices (Fully Settled)</div>
                    <div style="font-size: 26px; font-weight: 700; color: #0B2B26; margin-top: 6px;"><?= (int)$summary['paid']['count'] ?> <span style="font-size: 13px; font-weight: 500; color: #64748b;">settled</span></div>
                </div>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #DCFCE7; display: flex; align-items: center; justify-content: center; color: #059669;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
            </div>
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; justify-content: space-between; font-size: 12px;">
                <span style="color: #64748B;">Total Original: <strong style="color: #1E293B;">₹<?= number_format($summary['paid']['total_invoice_amount'], 2) ?></strong></span>
                <span style="color: #059669; font-weight: 600;">Received: ₹<?= number_format($summary['paid']['total_amount_received'], 2) ?></span>
            </div>
        </div>

        <!-- Card C: Partially Paid Invoices -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 4px solid #F59E0B; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #D97706; text-transform: uppercase; letter-spacing: 0.05em;">Partially Paid Invoices</div>
                    <div style="font-size: 26px; font-weight: 700; color: #0B2B26; margin-top: 6px;"><?= (int)$summary['partially_paid']['count'] ?> <span style="font-size: 13px; font-weight: 500; color: #64748b;">partially paid</span></div>
                </div>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #FEF3C7; display: flex; align-items: center; justify-content: center; color: #D97706;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
            </div>
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; flex-direction: column; gap: 4px; font-size: 12px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748B;">Total Original: ₹<?= number_format($summary['partially_paid']['total_invoice_amount'], 2) ?></span>
                    <span style="color: #059669; font-weight: 600;">Recv: ₹<?= number_format($summary['partially_paid']['total_amount_received'], 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                    <span style="color: #64748B;">Remaining Balance:</span>
                    <span style="color: #D97706;">₹<?= number_format($summary['partially_paid']['total_remaining_balance'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Card D: Total Outstanding -->
        <div style="background: linear-gradient(135deg, #0B2B26 0%, #163832 50%, #235347 100%); border-radius: 12px; padding: 20px; color: #FFFFFF; box-shadow: 0 4px 10px rgba(11, 43, 38, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #8EB69B; text-transform: uppercase; letter-spacing: 0.05em;">Total Net Outstanding</div>
                    <div style="font-size: 26px; font-weight: 700; color: #DAF1DE; margin-top: 6px;">₹<?= number_format($summary['total_outstanding'], 2) ?></div>
                </div>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(218, 241, 222, 0.15); display: flex; align-items: center; justify-content: center; color: #DAF1DE;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                </div>
            </div>
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(142, 182, 155, 0.25); font-size: 12px; color: #8EB69B;">
                Combined Unpaid (₹<?= number_format($summary['pending']['total_outstanding_amount'], 2) ?>) + Partial (₹<?= number_format($summary['partially_paid']['total_remaining_balance'], 2) ?>)
            </div>
        </div>
    </div>

    <!-- FILTER SECTION (SECTION 4: MULTI-FIELD REFINED FILTERS) -->
    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="GET" action="/reports/invoice-payment-status" id="filter-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 16px;">
                <!-- 1. Invoice Date From -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Invoice Date From</label>
                    <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 2. Invoice Date To -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Invoice Date To</label>
                    <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 3. Due Date From -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Due Date From</label>
                    <input type="date" name="due_date_from" value="<?= e($filters['due_date_from'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 4. Due Date To -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Due Date To</label>
                    <input type="date" name="due_date_to" value="<?= e($filters['due_date_to'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 5. Payment Status Dropdown -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Payment Status</label>
                    <select name="payment_status" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B; background: #FFFFFF;">
                        <option value="all" <?= empty($filters['payment_status']) || $filters['payment_status'] === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="unpaid" <?= ($filters['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid Only</option>
                        <option value="partially_paid" <?= ($filters['payment_status'] ?? '') === 'partially_paid' ? 'selected' : '' ?>>Partially Paid Only</option>
                        <option value="paid" <?= ($filters['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid Only</option>
                    </select>
                </div>

                <!-- 6. Overdue / Not Overdue Dropdown -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Overdue Status</label>
                    <select name="overdue" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B; background: #FFFFFF;">
                        <option value="all" <?= empty($filters['overdue']) || $filters['overdue'] === 'all' ? 'selected' : '' ?>>All Invoices</option>
                        <option value="overdue" <?= ($filters['overdue'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue Invoices Only</option>
                        <option value="not_overdue" <?= ($filters['overdue'] ?? '') === 'not_overdue' ? 'selected' : '' ?>>Current (Not Overdue)</option>
                    </select>
                </div>

                <!-- 7. Branch Dropdown -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Operating Branch</label>
                    <select name="branch_id" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B; background: #FFFFFF;">
                        <option value="">All Operating Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (!empty($filters['branch_id']) && (int)$filters['branch_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 8. Invoice Number -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Invoice Number</label>
                    <input type="text" name="invoice_number" value="<?= e($filters['invoice_number'] ?? '') ?>" placeholder="e.g. INV-00125" style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 9. Customer Name -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Customer Name</label>
                    <input type="text" name="customer_name" value="<?= e($filters['customer_name'] ?? '') ?>" placeholder="Customer name..." style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>

                <!-- 10. Customer Mobile -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Mobile Number</label>
                    <input type="text" name="mobile" value="<?= e($filters['mobile'] ?? '') ?>" placeholder="Mobile or WhatsApp..." style="width: 100%; padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; color: #1E293B;">
                </div>
            </div>

            <!-- Action Buttons Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #F1F5F9; padding-top: 14px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" style="padding: 9px 20px; background: #235347; color: #FFFFFF; font-size: 13px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <span>Filter Report</span>
                    </button>
                    <a href="/reports/invoice-payment-status" style="padding: 8px 16px; background: #F1F5F9; color: #475569; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 6px; border: 1px solid #CBD5E1;">Clear Filters</a>
                </div>

                <div style="font-size: 13px; color: #64748B;">
                    Showing <strong style="color: #0B2B26;"><?= number_format($pagination['total']) ?></strong> matching invoices
                </div>
            </div>
        </form>
    </div>

    <!-- DETAILED REPORT TABLE (SECTION 5) -->
    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #0B2B26; color: #FFFFFF; font-weight: 600; font-size: 12px; letter-spacing: 0.03em; text-transform: uppercase;">
                        <!-- 1. Invoice Number -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832;">
                            <a href="?<?= $buildQuery(['sort' => 'invoice_number', 'dir' => ($sort['field'] === 'invoice_number' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                <span>Invoice No.</span>
                                <?php if ($sort['field'] === 'invoice_number'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 2. Invoice Date -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832;">
                            <a href="?<?= $buildQuery(['sort' => 'invoice_date', 'dir' => ($sort['field'] === 'invoice_date' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                <span>Invoice Date</span>
                                <?php if ($sort['field'] === 'invoice_date'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 3. Customer Name -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832;">Customer Name</th>
                        <!-- 4. Mobile Number -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832;">Mobile Number</th>
                        <!-- 5. Invoice Amount -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832; text-align: right;">
                            <a href="?<?= $buildQuery(['sort' => 'invoice_amount', 'dir' => ($sort['field'] === 'invoice_amount' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <span>Invoice Amt (₹)</span>
                                <?php if ($sort['field'] === 'invoice_amount'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 6. Paid Amount -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832; text-align: right;">
                            <a href="?<?= $buildQuery(['sort' => 'paid_amount', 'dir' => ($sort['field'] === 'paid_amount' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <span>Paid Amt (₹)</span>
                                <?php if ($sort['field'] === 'paid_amount'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 7. Remaining Balance -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832; text-align: right;">
                            <a href="?<?= $buildQuery(['sort' => 'remaining_balance', 'dir' => ($sort['field'] === 'remaining_balance' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <span>Balance (₹)</span>
                                <?php if ($sort['field'] === 'remaining_balance'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 8. Due Date -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832;">
                            <a href="?<?= $buildQuery(['sort' => 'due_date', 'dir' => ($sort['field'] === 'due_date' && $sort['direction'] === 'asc') ? 'desc' : 'asc']) ?>" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                <span>Due Date</span>
                                <?php if ($sort['field'] === 'due_date'): ?>
                                    <span><?= $sort['direction'] === 'asc' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <!-- 9. Payment Status -->
                        <th style="padding: 12px 14px; border-bottom: 2px solid #163832; text-align: center;">Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" style="padding: 48px 24px; text-align: center; color: #64748B;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <div style="font-size: 15px; font-weight: 600; color: #334155;">No invoices match the selected filter criteria.</div>
                                    <div style="font-size: 13px;">Try adjusting your date range, status, or search keywords.</div>
                                    <a href="/reports/invoice-payment-status" style="margin-top: 6px; padding: 6px 14px; background: #235347; color: #fff; font-size: 12px; font-weight: 600; border-radius: 6px; text-decoration: none;">Reset Filters</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $idx => $r): ?>
                            <?php
                                $isOverdue = $r['is_overdue'];
                                $rowBg = ($idx % 2 === 0) ? '#FFFFFF' : '#F8FAFC';
                                $borderLeft = $isOverdue ? 'border-left: 4px solid #DC2626;' : 'border-left: 4px solid transparent;';
                            ?>
                            <tr style="background: <?= $rowBg ?>; border-bottom: 1px solid #E2E8F0; <?= $borderLeft ?> transition: background 0.15s ease;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='<?= $rowBg ?>'">
                                <!-- 1. Invoice Number Link -->
                                <td style="padding: 12px 14px; font-weight: 600;">
                                    <a href="/sales/invoices/<?= (int)$r['id'] ?>" title="View Invoice <?= e($r['invoice_number']) ?>" style="color: #235347; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                        <span><?= e($r['invoice_number']) ?></span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                </td>

                                <!-- 2. Invoice Date -->
                                <td style="padding: 12px 14px; color: #475569; white-space: nowrap;">
                                    <?= date('d M Y', strtotime($r['invoice_date'])) ?>
                                </td>

                                <!-- 3. Customer Name -->
                                <td style="padding: 12px 14px; font-weight: 500; color: #1E293B;">
                                    <?= e($r['customer_name']) ?>
                                </td>

                                <!-- 4. Mobile Number -->
                                <td style="padding: 12px 14px; color: #475569; white-space: nowrap;">
                                    <?php if (!empty($r['mobile_number'])): ?>
                                        <a href="tel:<?= e($r['mobile_number']) ?>" style="color: #475569; text-decoration: none;">
                                            <?= e($r['mobile_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94A3B8;">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 5. Invoice Amount -->
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #1E293B;">
                                    ₹<?= number_format($r['invoice_amount'], 2) ?>
                                </td>

                                <!-- 6. Paid Amount -->
                                <td style="padding: 12px 14px; text-align: right; font-weight: 500; color: #059669;">
                                    ₹<?= number_format($r['paid_amount'], 2) ?>
                                </td>

                                <!-- 7. Remaining Balance -->
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: <?= $r['remaining_balance'] > 0.001 ? '#DC2626' : '#059669' ?>;">
                                    ₹<?= number_format($r['remaining_balance'], 2) ?>
                                </td>

                                <!-- 8. Due Date & Overdue Badge -->
                                <td style="padding: 12px 14px; white-space: nowrap;">
                                    <?php if (!empty($r['due_date'])): ?>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="color: #475569;"><?= date('d M Y', strtotime($r['due_date'])) ?></span>
                                            <?php if ($isOverdue): ?>
                                                <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: #FEE2E2; color: #DC2626; text-transform: uppercase;">Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94A3B8;">Immediate</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 9. Payment Status Badge -->
                                <td style="padding: 12px 14px; text-align: center; white-space: nowrap;">
                                    <?php if ($r['payment_status'] === 'PAID'): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #DCFCE7; color: #059669;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <span>Paid</span>
                                        </span>
                                    <?php elseif ($r['payment_status'] === 'PARTIALLY_PAID'): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #FEF3C7; color: #D97706;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            <span>Partial</span>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #FEE2E2; color: #DC2626;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                            <span>Unpaid</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <!-- FILTERED TOTALS FOOTER ROW -->
                <tfoot>
                    <tr style="background: #DAF1DE; color: #0B2B26; font-weight: 700; border-top: 2px solid #8EB69B;">
                        <td colspan="4" style="padding: 12px 14px;">
                            FILTERED TOTALS (<?= number_format($filteredTotals['count']) ?> Invoices)
                        </td>
                        <td style="padding: 12px 14px; text-align: right;">
                            ₹<?= number_format($filteredTotals['total_invoice_amount'], 2) ?>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; color: #059669;">
                            ₹<?= number_format($filteredTotals['total_paid_amount'], 2) ?>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; color: #B91C1C;">
                            ₹<?= number_format($filteredTotals['total_remaining_balance'], 2) ?>
                        </td>
                        <td colspan="2" style="padding: 12px 14px; font-size: 11px; color: #235347; text-align: right;">
                            All matching filtered rows
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- PAGINATION BAR -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: #FFFFFF; border-top: 1px solid #E2E8F0; flex-wrap: wrap; gap: 12px;">
                <div style="font-size: 13px; color: #64748B;">
                    Showing <strong style="color: #0B2B26;"><?= $pagination['from'] ?></strong> to <strong style="color: #0B2B26;"><?= $pagination['to'] ?></strong> of <strong style="color: #0B2B26;"><?= number_format($pagination['total']) ?></strong> records
                </div>

                <div style="display: flex; gap: 6px; align-items: center;">
                    <!-- Previous Button -->
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="?<?= $buildQuery(['page' => $pagination['page'] - 1]) ?>" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; font-size: 12px; font-weight: 600; text-decoration: none;">&larr; Prev</a>
                    <?php else: ?>
                        <span style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E2E8F0; background: #F8FAFC; color: #94A3B8; font-size: 12px;">&larr; Prev</span>
                    <?php endif; ?>

                    <!-- Page Numbers (Compact Window) -->
                    <?php
                        $startPage = max(1, $pagination['page'] - 2);
                        $endPage = min($pagination['total_pages'], $startPage + 4);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                    ?>
                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <?php if ($p === $pagination['page']): ?>
                            <span style="padding: 6px 12px; border-radius: 6px; background: #235347; color: #FFFFFF; font-size: 12px; font-weight: 700;"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?<?= $buildQuery(['page' => $p]) ?>" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; font-size: 12px; font-weight: 500; text-decoration: none;"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Next Button -->
                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="?<?= $buildQuery(['page' => $pagination['page'] + 1]) ?>" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; font-size: 12px; font-weight: 600; text-decoration: none;">Next &rarr;</a>
                    <?php else: ?>
                        <span style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E2E8F0; background: #F8FAFC; color: #94A3B8; font-size: 12px;">Next &rarr;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

