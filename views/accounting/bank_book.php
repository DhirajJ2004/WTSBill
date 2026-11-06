<?php
$title = $title ?? 'Bank Book Register';
$bankBook = $bankBook ?? [];
$entries = $bankBook['entries'] ?? [];
$bankAccounts = $bankBook['bank_accounts'] ?? [];
$selectedBank = $bankBook['selected_bank'] ?? null;
$branches = $branches ?? [];
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$selectedBankId = $selectedBankId ?? ($selectedBank['id'] ?? 0);
$selectedBranch = $selectedBranch ?? 0;
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/balance-sheet" style="color: #64748b; text-decoration: none;">Accounting Ledgers</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Bank Book</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Bank Book Register</h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Audit register of commercial bank accounts, electronic fund transfers (NEFT/RTGS/IMPS), UPI deposits, and cheque transactions.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/accounting/cash-book" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                View Cash Book
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                Print Bank Book
            </button>
        </div>
    </div>

    <!-- Bank Account Pills -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <span style="font-size: 13px; font-weight: 600; color: #475569; margin-right: 8px;">Bank Account:</span>
        <?php foreach ($bankAccounts as $ba): 
            $active = ((int)$selectedBankId === (int)$ba['id']);
        ?>
            <a href="/accounting/bank-book?bank_account_id=<?= (int)$ba['id'] ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&branch_id=<?= (int)$selectedBranch ?>" style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?= $active ? 'background: #2563eb; color: #fff;' : 'background: #f1f5f9; color: #475569;' ?>">
                <?= htmlspecialchars($ba['bank_name']) ?> (<?= htmlspecialchars($ba['account_number']) ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Opening Bank Balance</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($bankBook['opening_balance'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">As of <?= date('d M Y', strtotime($fromDate)) ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #16a34a; text-transform: uppercase;">Total Bank Deposits</div>
            <div style="font-size: 24px; font-weight: 700; color: #16a34a; margin-top: 6px;">₹<?= number_format((float)($bankBook['total_deposits'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;">Direct credits &amp; transfers</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase;">Total Withdrawals</div>
            <div style="font-size: 24px; font-weight: 700; color: #dc2626; margin-top: 6px;">₹<?= number_format((float)($bankBook['total_withdrawals'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #dc2626; margin-top: 4px;">Supplier payments &amp; debits</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Closing Ledger Balance</div>
            <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-top: 6px;">₹<?= number_format((float)($bankBook['closing_balance'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #2563eb; margin-top: 4px;">Net Book Balance</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/accounting/bank-book" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <input type="hidden" name="bank_account_id" value="<?= (int)$selectedBankId ?>">

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
                    Filter Bank Book
                </button>
            </div>
        </form>
    </div>

    <!-- Chronological Bank Register Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px;">Date</th>
                    <th style="padding: 14px 16px;">Voucher No</th>
                    <th style="padding: 14px 16px;">Party / Account</th>
                    <th style="padding: 14px 16px;">Mode &amp; Ref / UTR</th>
                    <th style="padding: 14px 16px; text-align: right; color: #16a34a;">Deposit (Dr) ₹</th>
                    <th style="padding: 14px 16px; text-align: right; color: #dc2626;">Withdrawal (Cr) ₹</th>
                    <th style="padding: 14px 20px; text-align: right; color: #1e293b;">Running Balance ₹</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Row -->
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600;">
                    <td style="padding: 12px 20px; color: #64748b;"><?= date('d M Y', strtotime($fromDate)) ?></td>
                    <td style="padding: 12px 16px; color: #64748b;">—</td>
                    <td style="padding: 12px 16px; color: #1e293b;">OPENING BANK BALANCE</td>
                    <td style="padding: 12px 16px; color: #64748b;">Brought forward</td>
                    <td style="padding: 12px 16px; text-align: right;">—</td>
                    <td style="padding: 12px 16px; text-align: right;">—</td>
                    <td style="padding: 12px 20px; text-align: right; font-weight: 700; color: #1e293b;">
                        ₹<?= number_format((float)($bankBook['opening_balance'] ?? 0), 2) ?>
                    </td>
                </tr>

                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="7" style="padding: 36px; text-align: center; color: #94a3b8;">
                            No bank transactions found for this account during the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): 
                        $dep = (float)$e['deposit_amount'];
                        $wdr = (float)$e['withdrawal_amount'];
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
                            <td style="padding: 12px 16px; color: #64748b; font-size: 12px;">
                                <span style="font-weight: 500; color: #334155;"><?= htmlspecialchars($e['payment_mode']) ?></span>
                                <?php if (!empty($e['reference_no'])): ?>
                                    <div style="font-family: monospace; color: #94a3b8;"><?= htmlspecialchars($e['reference_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= $dep > 0 ? '#16a34a' : '#cbd5e1' ?>;">
                                <?= $dep > 0 ? '₹' . number_format($dep, 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= $wdr > 0 ? '#dc2626' : '#cbd5e1' ?>;">
                                <?= $wdr > 0 ? '₹' . number_format($wdr, 2) : '—' ?>
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
                        CLOSING BOOK BALANCE AS OF <?= date('d M Y', strtotime($toDate)) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #16a34a; font-size: 14px;">
                        ₹<?= number_format((float)($bankBook['total_deposits'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; color: #dc2626; font-size: 14px;">
                        ₹<?= number_format((float)($bankBook['total_withdrawals'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 14px 20px; text-align: right; color: #2563eb; font-size: 16px;">
                        ₹<?= number_format((float)($bankBook['closing_balance'] ?? 0), 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

