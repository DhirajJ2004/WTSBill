<?php
$title = $title ?? 'Cash Book Ledger';
$cashBook = $cashBook ?? [];
$entries = $cashBook['entries'] ?? [];
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/balance-sheet" style="color: #64748b; text-decoration: none;">Accounting Ledgers</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Cash Book</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Cash Book Register</h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Complete chronological journal of all cash receipts, counter collections, and petty cash disbursements.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/accounting/bank-book" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                View Bank Book
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                Print Cash Book
            </button>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Opening Cash Balance</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($cashBook['opening_balance'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">As of <?= date('d M Y', strtotime($fromDate)) ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #16a34a; text-transform: uppercase;">Total Cash Receipts</div>
            <div style="font-size: 24px; font-weight: 700; color: #16a34a; margin-top: 6px;">₹<?= number_format((float)($cashBook['total_receipts'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;">Inflows during period</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase;">Total Cash Disbursements</div>
            <div style="font-size: 24px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format((float)($cashBook['total_payments'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #dc2626; margin-top: 4px;">Outflows during period</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Closing Cash Balance</div>
            <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-top: 6px;">₹<?= number_format((float)($cashBook['closing_balance'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #2563eb; margin-top: 4px;">Net Cash in Hand</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/accounting/cash-book" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">From Date</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">To Date</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Branch</label>
                <select name="branch_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 180px;">
                    <option value="0">All Operating Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$selectedBranch === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" style="padding: 8px 18px; border-radius: 8px; background: #2563eb; color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Filter Cash Book
                </button>
            </div>
        </form>
    </div>

    <!-- Chronological Cash Ledger Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px;">Date</th>
                    <th style="padding: 14px 16px;">Voucher No</th>
                    <th style="padding: 14px 16px;">Party / Account</th>
                    <th style="padding: 14px 16px;">Narration / Ref</th>
                    <th style="padding: 14px 16px; text-align: right; color: #16a34a;">Receipt (Dr) ₹</th>
                    <th style="padding: 14px 16px; text-align: right; color: #dc2626;">Payment (Cr) ₹</th>
                    <th style="padding: 14px 20px; text-align: right; color: #1e293b;">Running Balance ₹</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Row -->
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600;">
                    <td style="padding: 12px 20px; color: #64748b;"><?= date('d M Y', strtotime($fromDate)) ?></td>
                    <td style="padding: 12px 16px; color: #64748b;">—</td>
                    <td style="padding: 12px 16px; color: #1e293b;">OPENING CASH IN HAND BALANCE</td>
                    <td style="padding: 12px 16px; color: #64748b;">Brought forward</td>
                    <td style="padding: 12px 16px; text-align: right;">—</td>
                    <td style="padding: 12px 16px; text-align: right;">—</td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                        ₹<?= number_format((float)($cashBook['opening_balance'] ?? 0), 2) ?>
                    </td>
                </tr>

                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="7" style="padding: 36px; text-align: center; color: #94a3b8;">
                            No cash transactions recorded during this period.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): 
                        $rec = (float)$e['receipt_amount'];
                        $pmt = (float)$e['payment_amount'];
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">
                                <?= date('d M Y', strtotime($e['payment_date'])) ?>
                            </td>
                            <td style="padding: 12px 16px; font-weight: 600; color: #2563eb;">
                                <?= htmlspecialchars($e['payment_number'] ?? 'PMT-' . $e['id']) ?>
                            </td>
                            <td style="padding: 12px 16px; font-weight: 500; color: #1e293b;">
                                <?= htmlspecialchars($e['party_name']) ?>
                            </td>
                            <td style="padding: 12px 16px; color: #64748b;">
                                <?= htmlspecialchars($e['notes'] ?: $e['reference_no'] ?: '—') ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= $rec > 0 ? '#16a34a' : '#cbd5e1' ?>;">
                                <?= $rec > 0 ? '₹' . number_format($rec, 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= $pmt > 0 ? '#dc2626' : '#cbd5e1' ?>;">
                                <?= $pmt > 0 ? '₹' . number_format($pmt, 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                                ₹<?= number_format((float)$e['running_balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 700;">
                    <td colspan="4" style="padding: 14px 20px; font-size: 14px; color: #1e293b;">
                        CLOSING CASH IN HAND AS OF <?= date('d M Y', strtotime($toDate)) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #16a34a; font-size: 14px;">
                        ₹<?= number_format((float)($cashBook['total_receipts'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #dc2626; font-size: 14px;">
                        ₹<?= number_format((float)($cashBook['total_payments'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right; color: #2563eb; font-size: 16px;">
                        ₹<?= number_format((float)($cashBook['closing_balance'] ?? 0), 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

