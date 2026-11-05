<?php
/**
 * WTSBill ERP — General Ledger View
 *
 * @var array $ledger
 * @var array $filters
 * @var array $branches
 */

$account = $ledger['account'] ?? null;
$accounts = $ledger['accounts'] ?? [];
$entries = $ledger['entries'] ?? [];
$pagination = $ledger['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 50, 'total_pages' => 1];
$openingBal = $ledger['opening_balance'] ?? 0.0;
$closingBal = $ledger['closing_balance'] ?? 0.0;
$totalDebits = $ledger['total_debits'] ?? 0.0;
$totalCredits = $ledger['total_credits'] ?? 0.0;

// Query string helper
if (!function_exists('ledger_url')) {
    function ledger_url(array $overrides = []): string {
        global $filters;
        $params = array_merge($filters ?? [], $overrides);
        return '/accounting/ledger?' . http_build_query($params);
    }
}
?>

<div class="page-header ledger-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h1 class="page-title">General Ledger</h1>
            <?php if ($account): ?>
                <span class="badge badge-primary" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                    <?= e($account['code']) ?> &mdash; <?= e($account['name']) ?>
                </span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle">Complete transactional audit trail, running account balances, and double-entry postings</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="/reports/balance-sheet" class="btn btn-secondary" style="text-decoration: none;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Balance Sheet
        </a>
        <a href="/accounting/ledger/export?<?= http_build_query(array_merge($filters, ['format' => 'csv'])) ?>" class="btn btn-secondary" style="text-decoration: none;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export CSV
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()" title="Print Ledger Statement">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print
        </button>
    </div>
</div>

<!-- Financial Summary KPI Cards -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-info, #0284c7);">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted); margin-bottom: 0.35rem;">Opening Balance</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-heading);">
            <?= e($ledger['opening_balance_formatted'] ?? '₹ 0.00') ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">As of <?= e(format_date($filters['from_date'])) ?></div>
    </div>

    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-primary, #0ea5e9);">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted); margin-bottom: 0.35rem;">Total Debits</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-primary, #0ea5e9);">
            <?= e($ledger['total_debits_formatted'] ?? '₹ 0.00') ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">During filtered period</div>
    </div>

    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-warning, #f59e0b);">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted); margin-bottom: 0.35rem;">Total Credits</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-warning, #f59e0b);">
            <?= e($ledger['total_credits_formatted'] ?? '₹ 0.00') ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">During filtered period</div>
    </div>

    <div class="card" style="padding: 1.15rem; border-left: 4px solid var(--color-success, #10b981);">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted); margin-bottom: 0.35rem;">Closing Balance</div>
        <div class="tabular-nums" style="font-size: 1.35rem; font-weight: 700; color: var(--color-success, #10b981);">
            <?= e($ledger['closing_balance_formatted'] ?? '₹ 0.00') ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem;">As of <?= e(format_date($filters['to_date'])) ?></div>
    </div>
</div>

<!-- Ledger Multi-Filter Bar -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="/accounting/ledger" id="ledger-filter-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem; align-items: flex-end;">
            <!-- Account -->
            <div>
                <label for="filter-account" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">Account</label>
                <select name="account_id" id="filter-account" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;" onchange="this.form.submit()">
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>" <?= ((int)$filters['account_id'] === (int)$acc['id']) ? 'selected' : '' ?>>
                            <?= e($acc['account_code']) ?> &mdash; <?= e($acc['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Branch -->
            <div>
                <label for="filter-branch" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">Branch</label>
                <select name="branch_id" id="filter-branch" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;" onchange="this.form.submit()">
                    <option value="0" <?= ((int)$filters['branch_id'] === 0) ? 'selected' : '' ?>>All Branches (Consolidated)</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= (int)$br['id'] ?>" <?= ((int)$filters['branch_id'] === (int)$br['id']) ? 'selected' : '' ?>>
                            <?= e($br['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- From Date -->
            <div>
                <label for="filter-from-date" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">From Date</label>
                <input type="date" name="from_date" id="filter-from-date" class="form-control" value="<?= e($filters['from_date']) ?>" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;">
            </div>

            <!-- To Date -->
            <div>
                <label for="filter-to-date" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">To Date</label>
                <input type="date" name="to_date" id="filter-to-date" class="form-control" value="<?= e($filters['to_date']) ?>" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;">
            </div>

            <!-- Transaction Type -->
            <div>
                <label for="filter-tx-type" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">Type</label>
                <select name="transaction_type" id="filter-tx-type" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;">
                    <option value="ALL" <?= $filters['transaction_type'] === 'ALL' ? 'selected' : '' ?>>All Transaction Types</option>
                    <option value="INVOICE" <?= $filters['transaction_type'] === 'INVOICE' ? 'selected' : '' ?>>Sales Invoice</option>
                    <option value="PURCHASE_PAYMENT" <?= $filters['transaction_type'] === 'PURCHASE_PAYMENT' ? 'selected' : '' ?>>Purchase Payment</option>
                    <option value="STOCK_ADJUSTMENT" <?= $filters['transaction_type'] === 'STOCK_ADJUSTMENT' ? 'selected' : '' ?>>Stock Adjustment</option>
                    <option value="MANUAL" <?= $filters['transaction_type'] === 'MANUAL' ? 'selected' : '' ?>>Manual Journal</option>
                </select>
            </div>

            <!-- Search Party / Description -->
            <div>
                <label for="filter-search" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.3rem;">Party / Search</label>
                <input type="text" name="search" id="filter-search" class="form-control" placeholder="Party, voucher, note..." value="<?= e($filters['search']) ?>" style="font-size: 0.85rem; padding: 0.45rem 0.65rem;">
            </div>

            <!-- Buttons -->
            <div style="display: flex; gap: 0.4rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.45rem 0.75rem; font-size: 0.85rem;">Filter</button>
                <a href="/accounting/ledger?account_id=<?= (int)$filters['account_id'] ?>" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.85rem; text-decoration: none;" title="Reset filters">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Ledger Transactions Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">
            Ledger Statement: <?= e($account['code'] ?? '') ?> &mdash; <?= e($account['name'] ?? 'General Ledger') ?>
            <span style="font-size: 0.85rem; font-weight: normal; color: var(--color-muted); margin-left: 0.5rem;">
                (<?= count($entries) ?> of <?= (int)$pagination['total'] ?> transactions)
            </span>
        </h3>
        <div style="font-size: 0.85rem; color: var(--color-muted);">
            Branch: <strong><?= e($ledger['branch']['name'] ?? 'All Branches') ?></strong>
        </div>
    </div>
    <div class="table-container" style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="min-width: 95px;">Date</th>
                    <th style="min-width: 60px;">ID</th>
                    <th style="min-width: 130px;">Voucher #</th>
                    <th style="min-width: 100px;">Type</th>
                    <th style="min-width: 110px;">Ref #</th>
                    <th style="min-width: 150px;">Customer / Supplier</th>
                    <th style="min-width: 200px;">Description</th>
                    <th style="text-align: right; min-width: 110px;">Debit (INR)</th>
                    <th style="text-align: right; min-width: 110px;">Credit (INR)</th>
                    <th style="text-align: right; min-width: 130px;">Running Balance</th>
                    <th style="min-width: 100px;">Branch</th>
                    <th style="min-width: 90px;">Created By</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr style="background: rgba(14, 165, 233, 0.05); font-weight: 600;">
                    <td><?= e(format_date($filters['from_date'])) ?></td>
                    <td>-</td>
                    <td><span class="badge badge-info" style="font-size: 0.75rem;">OPENING</span></td>
                    <td>OPENING_BAL</td>
                    <td>-</td>
                    <td>—</td>
                    <td><em>Opening Balance Brought Forward</em></td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; font-weight: 700; color: var(--color-info, #0284c7);" class="tabular-nums">
                        <?= e($ledger['opening_balance_formatted']) ?>
                    </td>
                    <td><?= e($ledger['branch']['name'] ?? 'All Branches') ?></td>
                    <td>System</td>
                </tr>

                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 2.5rem; color: var(--color-muted);">
                            No transactions recorded for this account in the selected date range and branch.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $row): ?>
                        <tr>
                            <td><?= e(format_date($row['date'])) ?></td>
                            <td><code>#<?= (int)$row['journal_id'] ?></code></td>
                            <td>
                                <strong><?= e($row['voucher_number']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 0.75rem;">
                                    <?= e($row['transaction_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['reference_number'])): ?>
                                    <?php if (!empty($row['source_url'])): ?>
                                        <a href="<?= e($row['source_url']) ?>" target="_blank" style="color: var(--color-primary); text-decoration: underline;">
                                            <?= e($row['reference_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <code><?= e($row['reference_number']) ?></code>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($row['party']) ?></strong>
                            </td>
                            <td style="max-width: 250px; white-space: normal;">
                                <?= e($row['description']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: <?= $row['debit'] > 0 ? 'var(--color-primary)' : 'inherit' ?>;" class="tabular-nums">
                                <?= e($row['debit_formatted']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: <?= $row['credit'] > 0 ? 'var(--color-warning)' : 'inherit' ?>;" class="tabular-nums">
                                <?= e($row['credit_formatted']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-heading);" class="tabular-nums">
                                <?= e($row['running_balance_formatted']) ?>
                            </td>
                            <td>
                                <small><?= e($row['branch_name']) ?></small>
                            </td>
                            <td>
                                <small><?= e($row['created_by']) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: 700; background: var(--bg-surface, #f8fafc); border-top: 2px solid var(--border-color, #e2e8f0);">
                    <td colspan="7">Closing Statement Balance (<?= e(format_date($filters['to_date'])) ?>)</td>
                    <td style="text-align: right; color: var(--color-primary);" class="tabular-nums"><?= e($ledger['total_debits_formatted']) ?></td>
                    <td style="text-align: right; color: var(--color-warning);" class="tabular-nums"><?= e($ledger['total_credits_formatted']) ?></td>
                    <td style="text-align: right; color: var(--color-success); font-size: 1.05rem;" class="tabular-nums"><?= e($ledger['closing_balance_formatted']) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--color-muted);">
                Showing page <strong><?= (int)$pagination['page'] ?></strong> of <strong><?= (int)$pagination['total_pages'] ?></strong> (<?= (int)$pagination['total'] ?> transactions)
            </div>
            <div style="display: flex; gap: 0.35rem;">
                <?php if ($pagination['page'] > 1): ?>
                    <a href="<?= ledger_url(['page' => $pagination['page'] - 1]) ?>" class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; text-decoration: none;">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $pagination['page'] - 2); $p <= min($pagination['total_pages'], $pagination['page'] + 2); $p++): ?>
                    <a href="<?= ledger_url(['page' => $p]) ?>" class="btn <?= ($p === (int)$pagination['page']) ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; text-decoration: none;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                    <a href="<?= ledger_url(['page' => $pagination['page'] + 1]) ?>" class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; text-decoration: none;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
