<?php
$title = $title ?? 'Trial Balance Statement';
$trialBalance = $trialBalance ?? [];
$groups = $trialBalance['groups'] ?? [];
$summary = $trialBalance['summary'] ?? [];
$branches = $branches ?? [];
$asOfDate = $asOfDate ?? date('Y-m-d');
$selectedBranch = $selectedBranch ?? 0;
$isBalanced = (bool)($summary['is_balanced'] ?? true);
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/reports/balance-sheet" style="color: #64748b; text-decoration: none;">Accounting Reports</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Trial Balance</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span>Trial Balance Statement</span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; <?= $isBalanced ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
                    <?= $isBalanced ? 'BALANCED' : 'OUT OF BALANCE' ?>
                </span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Comprehensive closing debit and credit ledger account verification statement.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/reports/trial-balance/export?as_of_date=<?= urlencode($asOfDate) ?>&branch_id=<?= (int)$selectedBranch ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </a>
            <button type="button" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; background: #334155; color: #fff; border: none; cursor: pointer;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Statement
            </button>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Total Debits (Dr)</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($summary['total_debits'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Sum of all debit account balances</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">Total Credits (Cr)</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)($summary['total_credits'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Sum of all credit account balances</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: <?= $isBalanced ? '#16a34a' : '#dc2626' ?>; text-transform: uppercase;">Net Discrepancy / Difference</div>
            <div style="font-size: 24px; font-weight: 700; color: <?= $isBalanced ? '#16a34a' : '#dc2626' ?>; margin-top: 6px;">₹<?= number_format((float)($summary['difference'] ?? 0), 2) ?></div>
            <div style="font-size: 12px; color: <?= $isBalanced ? '#16a34a' : '#dc2626' ?>; margin-top: 4px;"><?= $isBalanced ? 'Double-entry equation in balance' : 'Adjustment required' ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Ledger Accounts</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;"><?= (int)($summary['total_accounts'] ?? 0) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Active Chart of Accounts</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/reports/trial-balance" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">As-of Date</label>
                <input type="date" name="as_of_date" value="<?= htmlspecialchars($asOfDate) ?>" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Branch</label>
                <select name="branch_id" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; min-width: 180px;">
                    <option value="0">Consolidated (All Branches)</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$selectedBranch === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" style="padding: 8px 18px; border-radius: 8px; background: #2563eb; color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Update Trial Balance
                </button>
            </div>
        </form>
    </div>

    <!-- Trial Balance Statement Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px; width: 140px;">Account Code</th>
                    <th style="padding: 14px 20px;">Account Title &amp; Description</th>
                    <th style="padding: 14px 20px; text-align: right; width: 200px;">Debit Balance (₹)</th>
                    <th style="padding: 14px 20px; text-align: right; width: 200px;">Credit Balance (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $gKey => $g): ?>
                    <tr style="background: #f1f5f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                        <td colspan="4" style="padding: 10px 20px; font-weight: 700; color: #1e293b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?= htmlspecialchars($g['title']) ?>
                        </td>
                    </tr>
                    <?php foreach ($g['accounts'] as $acc): 
                        $dr = (float)$acc['debit_balance'];
                        $cr = (float)$acc['credit_balance'];
                    ?>
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 12px 20px; font-family: monospace; font-weight: 600; color: #64748b;">
                                <?= htmlspecialchars($acc['account_code']) ?>
                            </td>
                            <td style="padding: 12px 20px;">
                                <a href="/accounting/ledger?account_id=<?= (int)$acc['id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                    <?= htmlspecialchars($acc['account_name']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: <?= $dr > 0 ? '#1e293b' : '#cbd5e1' ?>;">
                                <?= $dr > 0 ? '₹' . number_format($dr, 2) : '—' ?>
                            </td>
                            <td style="padding: 12px 20px; text-align: right; font-weight: 600; color: <?= $cr > 0 ? '#1e293b' : '#cbd5e1' ?>;">
                                <?= $cr > 0 ? '₹' . number_format($cr, 2) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Group Subtotal -->
                    <tr style="background: #fafafa; border-bottom: 1px solid #e2e8f0; font-weight: 600;">
                        <td colspan="2" style="padding: 10px 20px; text-align: right; color: #475569; font-size: 12px;">
                            SUBTOTAL <?= htmlspecialchars($g['title']) ?>:
                        </td>
                        <td style="padding: 10px 20px; text-align: right; color: #2563eb;">
                            ₹<?= number_format((float)$g['subtotal_debit'], 2) ?>
                        </td>
                        <td style="padding: 10px 20px; text-align: right; color: #059669;">
                            ₹<?= number_format((float)$g['subtotal_credit'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 3px solid #1e293b; font-weight: 700; font-size: 15px;">
                    <td colspan="2" style="padding: 16px 20px; color: #1e293b;">
                        TOTAL TRIAL BALANCE VERIFICATION
                    </td>
                    <td style="padding: 16px 20px; text-align: right; color: #2563eb;">
                        ₹<?= number_format((float)($summary['total_debits'] ?? 0), 2) ?>
                    </td>
                    <td style="padding: 16px 20px; text-align: right; color: #059669;">
                        ₹<?= number_format((float)($summary['total_credits'] ?? 0), 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

