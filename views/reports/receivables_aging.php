<?php
$title = $title ?? 'Accounts Receivable Aging Report';
$report = $report ?? [];
$summary = $report['summary'] ?? [];
$customers = $report['customers'] ?? [];
$branches = $branches ?? [];
$allCustomers = $allCustomers ?? [];
$asOfDate = $asOfDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;
$selectedCustomer = $selectedCustomer ?? 0;
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/balance-sheet" style="color: #64748b; text-decoration: none;">Reports</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Receivables Aging Statement</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Accounts Receivable Aging</h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Chronological classification of outstanding invoices across 0-30, 31-60, 61-90, and 90+ day aging buckets.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/reports/receivables-aging/export?as_of_date=<?= urlencode($asOfDate) ?>&branch_id=<?= (int)$selectedBranch ?>&customer_id=<?= (int)$selectedCustomer ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Report
            </button>
        </div>
    </div>

    <!-- KPI Summary Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Receivables</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($summary['total_due'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?= (int)($summary['total_customers'] ?? 0) ?> Active Debtors</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #16a34a; text-transform: uppercase;">0–30 Days (Current)</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 6px;">₹<?= number_format((float)($summary['bucket_0_30'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;">Standard terms</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #0284c7; text-transform: uppercase;">31–60 Days</div>
            <div style="font-size: 22px; font-weight: 700; color: #0284c7; margin-top: 6px;">₹<?= number_format((float)($summary['bucket_31_60'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Reminder stage</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #d97706; text-transform: uppercase;">61–90 Days</div>
            <div style="font-size: 22px; font-weight: 700; color: #d97706; margin-top: 6px;">₹<?= number_format((float)($summary['bucket_61_90'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #d97706; margin-top: 4px;">Follow-up required</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase;">90+ Days (Critical)</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format((float)($summary['bucket_90_plus'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #dc2626; margin-top: 4px;">High collection risk</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/reports/receivables-aging" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">As-of Date</label>
                <input type="date" name="as_of_date" value="<?= htmlspecialchars($asOfDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Branch</label>
                <select name="branch_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 160px;">
                    <option value="0">All Operating Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$selectedBranch === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Customer</label>
                <select name="customer_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 200px;">
                    <option value="0">All Debtors</option>
                    <?php foreach ($allCustomers as $ac): ?>
                        <option value="<?= (int)$ac['id'] ?>" <?= (int)$selectedCustomer === (int)$ac['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ac['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" style="padding: 8px 18px; border-radius: 8px; background: #2563eb; color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Update Aging Statement
                </button>
            </div>
        </form>
    </div>

    <!-- Aging Master Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px;">Customer Name</th>
                    <th style="padding: 14px 16px;">Contact</th>
                    <th style="padding: 14px 16px; text-align: right;">Credit Limit</th>
                    <th style="padding: 14px 16px; text-align: right; color: #16a34a;">0–30 Days</th>
                    <th style="padding: 14px 16px; text-align: right; color: #0284c7;">31–60 Days</th>
                    <th style="padding: 14px 16px; text-align: right; color: #d97706;">61–90 Days</th>
                    <th style="padding: 14px 16px; text-align: right; color: #dc2626;">90+ Days</th>
                    <th style="padding: 14px 20px; text-align: right; color: #1e293b;">Total Due (₹)</th>
                    <th style="padding: 14px 20px; text-align: center;">Details</th>
                </tr>
            </thead>
            <tbody style="divide-y: 1px solid #f1f5f9;">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" style="padding: 48px; text-align: center; color: #94a3b8; font-size: 14px;">
                            No outstanding accounts receivable found as of <?= htmlspecialchars($asOfDate) ?>. All invoices are fully settled!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): 
                        $cId = (int)$c['customer_id'];
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 20px; font-weight: 600;">
                                <a href="/customers/<?= $cId ?>" style="color: #2563eb; text-decoration: none;">
                                    <?= htmlspecialchars($c['customer_name']) ?>
                                </a>
                            </td>
                            <td style="padding: 14px 16px; color: #64748b;">
                                <?= htmlspecialchars($c['customer_phone']) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; color: #64748b;">
                                ₹<?= number_format((float)$c['credit_limit'], 2) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; font-weight: 500; color: <?= (float)$c['bucket_0_30'] > 0 ? '#16a34a' : '#cbd5e1' ?>;">
                                ₹<?= number_format((float)$c['bucket_0_30'], 2) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; font-weight: 500; color: <?= (float)$c['bucket_31_60'] > 0 ? '#0284c7' : '#cbd5e1' ?>;">
                                ₹<?= number_format((float)$c['bucket_31_60'], 2) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; font-weight: 600; color: <?= (float)$c['bucket_61_90'] > 0 ? '#d97706' : '#cbd5e1' ?>;">
                                ₹<?= number_format((float)$c['bucket_61_90'], 2) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; font-weight: 700; color: <?= (float)$c['bucket_90_plus'] > 0 ? '#dc2626' : '#cbd5e1' ?>;">
                                ₹<?= number_format((float)$c['bucket_90_plus'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: right; font-weight: 700; font-size: 14px; color: #1e293b;">
                                ₹<?= number_format((float)$c['total_due'], 2) ?>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <button type="button" onclick="toggleCustomerInvoices(<?= $cId ?>)" style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; cursor: pointer;">
                                    Invoices (<?= count($c['invoices']) ?>)
                                </button>
                            </td>
                        </tr>

                        <!-- Expandable Invoices Row -->
                        <tr id="customerInvoices_<?= $cId ?>" style="display: none; background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <td colspan="9" style="padding: 16px 24px;">
                                <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px;">
                                    Outstanding Invoices for <?= htmlspecialchars($c['customer_name']) ?>
                                </div>
                                <table style="width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <thead>
                                        <tr style="background: #f1f5f9; color: #475569; font-weight: 600;">
                                            <th style="padding: 8px 12px;">Invoice No</th>
                                            <th style="padding: 8px 12px;">Invoice Date</th>
                                            <th style="padding: 8px 12px;">Due Date</th>
                                            <th style="padding: 8px 12px; text-align: right;">Days Overdue</th>
                                            <th style="padding: 8px 12px; text-align: center;">Aging Bucket</th>
                                            <th style="padding: 8px 12px; text-align: right;">Grand Total</th>
                                            <th style="padding: 8px 12px; text-align: right;">Amount Paid</th>
                                            <th style="padding: 8px 12px; text-align: right; color: #dc2626;">Amount Due</th>
                                            <th style="padding: 8px 12px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($c['invoices'] as $inv): ?>
                                            <tr style="border-top: 1px solid #f1f5f9;">
                                                <td style="padding: 8px 12px; font-weight: 600; color: #2563eb;">
                                                    <a href="/sales/invoices/<?= (int)$inv['invoice_id'] ?>/print" target="_blank" style="color: #2563eb; text-decoration: none;">
                                                        <?= htmlspecialchars($inv['invoice_number']) ?>
                                                    </a>
                                                </td>
                                                <td style="padding: 8px 12px; color: #64748b;"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
                                                <td style="padding: 8px 12px; color: #64748b;"><?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—' ?></td>
                                                <td style="padding: 8px 12px; text-align: right; font-weight: 600; color: <?= (int)$inv['days_overdue'] > 60 ? '#dc2626' : '#475569' ?>;">
                                                    <?= (int)$inv['days_overdue'] ?> days
                                                </td>
                                                <td style="padding: 8px 12px; text-align: center;">
                                                    <span style="padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: #eff6ff; color: #1d4ed8;">
                                                        <?= htmlspecialchars($inv['aging_bucket']) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 8px 12px; text-align: right;">₹<?= number_format((float)$inv['grand_total'], 2) ?></td>
                                                <td style="padding: 8px 12px; text-align: right; color: #16a34a;">₹<?= number_format((float)$inv['amount_paid'], 2) ?></td>
                                                <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #dc2626;">₹<?= number_format((float)$inv['amount_due'], 2) ?></td>
                                                <td style="padding: 8px 12px; text-align: center;">
                                                    <a href="/sales/invoices/<?= (int)$inv['invoice_id'] ?>/print" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($customers)): ?>
                <tfoot>
                    <tr style="background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 700;">
                        <td colspan="3" style="padding: 14px 20px; font-size: 14px; color: #1e293b;">
                            TOTAL RECEIVABLES SUMMARY
                        </td>
                        <td style="padding: 14px 16px; text-align: right; color: #16a34a; font-size: 14px;">
                            ₹<?= number_format((float)($summary['bucket_0_30'] ?? 0), 2) ?>
                        </td>
                        <td style="padding: 14px 16px; text-align: right; color: #0284c7; font-size: 14px;">
                            ₹<?= number_format((float)($summary['bucket_31_60'] ?? 0), 2) ?>
                        </td>
                        <td style="padding: 14px 16px; text-align: right; color: #d97706; font-size: 14px;">
                            ₹<?= number_format((float)($summary['bucket_61_90'] ?? 0), 2) ?>
                        </td>
                        <td style="padding: 14px 16px; text-align: right; color: #dc2626; font-size: 14px;">
                            ₹<?= number_format((float)($summary['bucket_90_plus'] ?? 0), 2) ?>
                        </td>
                        <td style="padding: 14px 20px; text-align: right; color: #1e293b; font-size: 16px;">
                            ₹<?= number_format((float)($summary['total_due'] ?? 0), 2) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function toggleCustomerInvoices(cId) {
    var row = document.getElementById('customerInvoices_' + cId);
    if (!row) return;
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}
</script>

