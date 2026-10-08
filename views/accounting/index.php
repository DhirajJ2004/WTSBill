<?php
$pageTitle = 'Double-Entry Accounting & Banking - WTS LEDGER PRO';
$currentRoute = 'accounting';

require_once __DIR__ . '/../db_helper.php';

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\LedgerService;
use App\Services\TrialBalanceService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: null;
$financialYear = get_current_financial_year();

// Ensure standard Chart of Accounts exist for the tenant
AccountService::ensureDefaultAccounts($companyId);

$activeTab = $_GET['tab'] ?? ($_GET['view'] ?? 'chart');
if ($activeTab === 'ledger') $activeTab = 'general-ledger';
if ($activeTab === 'banking') $activeTab = 'banking-cash';
if ($activeTab === 'journal') $activeTab = 'journal-vouchers';
if ($activeTab === 'trial') $activeTab = 'trial-balance';

$submitError = null;
$submitSuccess = null;

if (isset($_GET['created'])) {
    $submitSuccess = "Record #" . htmlspecialchars($_GET['created']) . " created and posted successfully!";
}

// -------------------------------------------------------------
// Handle POST actions for Accounting
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? ($input['form_action'] ?? 'save_journal');

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $userName = $_SESSION['user']['name'] ?? 'Admin';

            if ($action === 'save_journal') {
                $entryDate = $input['entry_date'] ?? date('Y-m-d');
                $fy = $input['financial_year'] ?? $financialYear;
                $narration = trim($input['narration'] ?? ($input['description'] ?? ''));
                if (empty($narration)) {
                    $narration = 'Manual Journal Voucher';
                }

                $lines = [];
                // Check if structured multi-line input or simple debit/credit pair
                if (!empty($input['lines']) && is_array($input['lines'])) {
                    $lines = $input['lines'];
                } elseif (!empty($input['debit_account_id']) && !empty($input['credit_account_id'])) {
                    $amount = round(floatval($input['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        throw new \InvalidArgumentException('Journal amount must be greater than zero.');
                    }
                    if (intval($input['debit_account_id']) === intval($input['credit_account_id'])) {
                        throw new \InvalidArgumentException('Debit and Credit account heads cannot be the same.');
                    }

                    $lines = [
                        [
                            'account_id' => intval($input['debit_account_id']),
                            'debit' => $amount,
                            'credit' => 0.00,
                            'description' => $narration,
                        ],
                        [
                            'account_id' => intval($input['credit_account_id']),
                            'debit' => 0.00,
                            'credit' => $amount,
                            'description' => $narration,
                        ]
                    ];
                } else {
                    throw new \InvalidArgumentException('Please provide valid debit and credit account selections.');
                }

                $journal = JournalService::createJournalEntry([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'financial_year' => $fy,
                    'entry_date' => $entryDate,
                    'entry_type' => strtoupper($input['entry_type'] ?? 'MANUAL'),
                    'description' => $narration,
                    'narration' => $narration,
                    'lines' => $lines,
                    'status' => 'POSTED',
                ], $userName);

                if ($isJson) {
                    response_json([
                        'status' => 'success',
                        'message' => "Journal #{$journal->journal_number} created and posted successfully.",
                        'data' => $journal,
                    ], 201);
                } else {
                    $targetUrl = url('/accounting?tab=journal-vouchers&created=' . urlencode($journal->journal_number));
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            } elseif ($action === 'add_account') {
                $code = trim($input['account_code'] ?? '');
                $name = trim($input['account_name'] ?? '');
                $type = strtoupper(trim($input['account_type'] ?? 'ASSET'));
                $subtype = !empty($input['account_subtype']) ? strtoupper(trim($input['account_subtype'])) : null;
                $nature = strtoupper($input['nature'] ?? (in_array($type, ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT'));
                $openingBal = floatval($input['opening_balance'] ?? 0);

                if (empty($code) || empty($name)) {
                    throw new \InvalidArgumentException('Account Code and Account Name are required.');
                }

                $exists = ChartOfAccount::where('company_id', $companyId)->where('account_code', $code)->exists();
                if ($exists) {
                    throw new \InvalidArgumentException("Account code '{$code}' already exists.");
                }

                $account = ChartOfAccount::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'account_code' => $code,
                    'account_name' => $name,
                    'account_type' => $type,
                    'account_subtype' => $subtype,
                    'nature' => $nature,
                    'opening_balance' => $openingBal,
                    'opening_balance_type' => $nature,
                    'is_system_account' => false,
                    'is_active' => true,
                    'description' => trim($input['description'] ?? ''),
                ]);

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'ACCOUNT_CREATE',
                    'ChartOfAccount',
                    $account->id,
                    "Created Account '{$account->account_name}' ({$account->account_code})"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'data' => $account], 201);
                } else {
                    $targetUrl = url('/accounting?tab=chart&created=' . urlencode($account->account_code));
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            } elseif ($action === 'add_bank') {
                $bankName = trim($input['bank_name'] ?? '');
                $accountName = trim($input['account_name'] ?? '');
                $accountNumber = trim($input['account_number'] ?? '');
                $ifsc = trim($input['ifsc_code'] ?? ($input['ifsc'] ?? ''));
                $openingBal = floatval($input['opening_balance'] ?? 0);

                if (empty($bankName) || empty($accountNumber)) {
                    throw new \InvalidArgumentException('Bank Name and Account Number are required.');
                }

                $bankAcc = BankAccount::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'bank_name' => $bankName,
                    'account_name' => $accountName ?: $bankName,
                    'account_number' => $accountNumber,
                    'account_type' => $input['account_type'] ?? 'CURRENT',
                    'ifsc_code' => $ifsc,
                    'ifsc' => $ifsc,
                    'opening_balance' => $openingBal,
                    'current_balance' => $openingBal,
                    'is_active' => true,
                    'is_primary' => !empty($input['is_primary']),
                    'created_by' => $userName,
                ]);

                if ($isJson) {
                    response_json(['status' => 'success', 'data' => $bankAcc], 201);
                } else {
                    $targetUrl = url('/accounting?tab=banking-cash&created=' . urlencode($bankAcc->bank_name));
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            }
        } catch (\Throwable $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
            } else {
                $submitError = $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// Load Data for Tabs
// -------------------------------------------------------------
// -------------------------------------------------------------
// Load Data for Tabs (Optimized Single Bulk Query & Tab-Aware Loading)
// -------------------------------------------------------------
$accounts = ChartOfAccount::where('company_id', $companyId)->orderBy('account_code', 'asc')->get();

// Calculate Closing Balance for each Chart of Account in ONE single bulk query
$accountTotals = JournalLine::where('journal_lines.company_id', $companyId)
    ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
    ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
    ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as deb_sum, SUM(journal_lines.credit) as cred_sum')
    ->groupBy('journal_lines.account_id')
    ->get()
    ->keyBy('account_id');

$accountBalances = [];
foreach ($accounts as $acc) {
    $sums = $accountTotals->get($acc->id);
    $debSum = floatval($sums ? $sums->deb_sum : 0);
    $credSum = floatval($sums ? $sums->cred_sum : 0);

    $initial = floatval($acc->opening_balance);
    $initialType = strtoupper($acc->opening_balance_type ?: 'DEBIT');

    if ($initialType === 'DEBIT') {
        $debSum += $initial;
    } else {
        $credSum += $initial;
    }

    $rawBalance = $debSum - $credSum;
    $nature = $acc->getNormalNature();

    if ($nature === 'DEBIT') {
        $accountBalances[$acc->id] = [
            'amount' => abs($rawBalance),
            'type' => ($rawBalance >= 0) ? 'Dr' : 'Cr',
            'net' => $rawBalance
        ];
    } else {
        $creditNet = $credSum - $debSum;
        $accountBalances[$acc->id] = [
            'amount' => abs($creditNet),
            'type' => ($creditNet >= 0) ? 'Cr' : 'Dr',
            'net' => $creditNet
        ];
    }
}

// Tab 2: Journal Entries (Lazy / Targeted load)
$journals = ($activeTab === 'journal-vouchers' || $activeTab === 'chart') ? JournalEntry::where('company_id', $companyId)
    ->with(['lines.account'])
    ->orderBy('entry_date', 'desc')
    ->orderBy('id', 'desc')
    ->limit(100)
    ->get() : collect([]);

// Tab 3: General Ledger Filters & Data
$selectedAccountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : ($accounts->first()?->id ?? 0);
$selectedFy = $_GET['fy'] ?? $financialYear;
$fromDate = $_GET['from_date'] ?? null;
$toDate = $_GET['to_date'] ?? null;

// Determine date range from FY if dates not provided
if (!$fromDate || !$toDate) {
    if ($selectedFy && preg_match('/^(\d{4})-(\d{2,4})$/', $selectedFy, $m)) {
        $startYear = intval($m[1]);
        $fromDate = $fromDate ?: "{$startYear}-04-01";
        $endYear = ($startYear + 1);
        $toDate = $toDate ?: "{$endYear}-03-31";
    } else {
        $fromDate = $fromDate ?: date('Y-04-01');
        $toDate = $toDate ?: date('Y-m-d');
    }
}

$ledgerData = null;
if ($activeTab === 'general-ledger' && $selectedAccountId > 0) {
    try {
        $ledgerData = LedgerService::getAccountLedger($companyId, $selectedAccountId, $fromDate, $toDate, $branchId, $selectedFy);
    } catch (\Throwable $e) {
        $ledgerData = null;
    }
}

// Tab 4: Banking & Cash Data
$bankAccounts = BankAccount::where('company_id', $companyId)->get();
$bankTransactions = ($activeTab === 'banking-cash') ? BankTransaction::where('company_id', $companyId)
    ->with('bankAccount')
    ->orderBy('transaction_date', 'desc')
    ->orderBy('id', 'desc')
    ->limit(50)
    ->get() : collect([]);

// Tab 5: Trial Balance Data (Targeted execution)
$trialBalance = ($activeTab === 'trial-balance') ? TrialBalanceService::getTrialBalance($companyId, $toDate, $branchId, $selectedFy) : [
    'as_of_date' => $toDate,
    'financial_year' => $selectedFy,
    'is_balanced' => true,
    'total_debit' => 0,
    'total_credit' => 0,
    'difference' => 0,
    'rows' => []
];

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <?php if ($submitSuccess): ?>
            <div class="alert alert-success" style="padding: 14px 18px; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; color: #065f46; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-circle-check text-success" style="font-size: 18px;"></i>
                <span><?= $submitSuccess ?></span>
            </div>
        <?php endif; ?>

        <?php if ($submitError): ?>
            <div class="alert alert-danger" style="padding: 14px 18px; margin-bottom: 20px; background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size: 18px;"></i>
                <span><?= htmlspecialchars($submitError) ?></span>
            </div>
        <?php endif; ?>

        <!-- Accounting Header -->
        <div class="workspace-header-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div class="workspace-header-left">
                <h1 class="workspace-title" style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">Banking &amp; Double-Entry Ledger</h1>
                <p class="workspace-subtitle" style="font-size: 13px; color: #64748b; margin: 0;">General ledger, trial balance, bank accounts, and double-entry journal vouchers.</p>
            </div>
            <div class="workspace-header-right" style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="exportTableToCSV('accountingTable', 'Accounting_Ledger.csv')">
                    <i class="fa-solid fa-download"></i> Export
                </button>
                <button class="btn btn-primary" onclick="openModal('addJournalModal')">
                    <i class="fa-solid fa-plus"></i> + New Journal Entry
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="subnav-tabs-wrapper" style="margin-bottom: 20px;">
            <div class="subnav-tabs" id="accountingTabsNav" style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0;">
                <a href="?tab=chart" class="subnav-tab <?= $activeTab === 'chart' ? 'active font-bold' : '' ?>" style="padding: 10px 18px; text-decoration: none; border-bottom: 2px solid <?= $activeTab === 'chart' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'chart' ? '#2563eb' : '#64748b' ?>;">Chart of Accounts</a>
                <a href="?tab=journal-vouchers" class="subnav-tab <?= $activeTab === 'journal-vouchers' ? 'active font-bold' : '' ?>" style="padding: 10px 18px; text-decoration: none; border-bottom: 2px solid <?= $activeTab === 'journal-vouchers' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'journal-vouchers' ? '#2563eb' : '#64748b' ?>;">Journal Vouchers</a>
                <a href="?tab=general-ledger" class="subnav-tab <?= $activeTab === 'general-ledger' ? 'active font-bold' : '' ?>" style="padding: 10px 18px; text-decoration: none; border-bottom: 2px solid <?= $activeTab === 'general-ledger' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'general-ledger' ? '#2563eb' : '#64748b' ?>;">General Ledger</a>
                <a href="?tab=banking-cash" class="subnav-tab <?= $activeTab === 'banking-cash' ? 'active font-bold' : '' ?>" style="padding: 10px 18px; text-decoration: none; border-bottom: 2px solid <?= $activeTab === 'banking-cash' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'banking-cash' ? '#2563eb' : '#64748b' ?>;">Banking &amp; Cash</a>
                <a href="?tab=trial-balance" class="subnav-tab <?= $activeTab === 'trial-balance' ? 'active font-bold' : '' ?>" style="padding: 10px 18px; text-decoration: none; border-bottom: 2px solid <?= $activeTab === 'trial-balance' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'trial-balance' ? '#2563eb' : '#64748b' ?>;">Trial Balance</a>
            </div>
        </div>

        <div class="accounting-tabs-container">
            <!-- TAB 1: CHART OF ACCOUNTS -->
            <?php if ($activeTab === 'chart'): ?>
                <div id="tab-chart-accounts" class="subnav-pane">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-title"><i class="fa-solid fa-sitemap"></i> Chart of Accounts Master Hierarchy</div>
                            <button class="btn btn-primary btn-sm" onclick="openModal('addAccountModal')"><i class="fa-solid fa-plus"></i> Add Account Head</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="accountingTable">
                                <thead>
                                    <tr>
                                        <th>Account Code</th>
                                        <th>Account Head Name</th>
                                        <th>Classification Type</th>
                                        <th>Subtype</th>
                                        <th>Normal Nature</th>
                                        <th>Closing Balance (₹)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($accounts->isEmpty()): ?>
                                        <tr><td colspan="7" style="text-align: center; padding: 25px;">No accounts found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($accounts as $acc): ?>
                                            <?php
                                                $bal = $accountBalances[$acc->id] ?? ['amount' => 0, 'type' => 'Dr'];
                                                $typeUpper = strtoupper($acc->account_type);
                                                $badgeClass = 'badge-info';
                                                if (in_array($typeUpper, ['ASSET', 'BANK', 'CASH'])) $badgeClass = 'badge-primary';
                                                elseif (in_array($typeUpper, ['LIABILITY'])) $badgeClass = 'badge-warning';
                                                elseif (in_array($typeUpper, ['INCOME'])) $badgeClass = 'badge-success';
                                                elseif (in_array($typeUpper, ['EXPENSE'])) $badgeClass = 'badge-danger';
                                            ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($acc->account_code) ?></strong></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($acc->account_name) ?></strong>
                                                    <?php if ($acc->is_system_account): ?>
                                                        <span class="badge badge-secondary" style="font-size: 10px; margin-left: 5px;">SYSTEM</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($acc->account_type) ?></span></td>
                                                <td><small class="text-muted"><?= htmlspecialchars($acc->account_subtype ?: '-') ?></small></td>
                                                <td><span class="badge badge-outline"><?= htmlspecialchars($acc->nature) ?></span></td>
                                                <td>
                                                    <strong style="color: <?= $bal['type'] === 'Dr' ? '#2563eb' : '#059669' ?>;">
                                                        ₹<?= number_format($bal['amount'], 2) ?> (<?= $bal['type'] ?>)
                                                    </strong>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $acc->is_active ? 'badge-success' : 'badge-danger' ?>">
                                                        <?= $acc->is_active ? 'ACTIVE' : 'INACTIVE' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: JOURNAL VOUCHERS -->
            <?php if ($activeTab === 'journal-vouchers'): ?>
                <div id="tab-journal-vouchers" class="subnav-pane">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-title"><i class="fa-solid fa-book-journal-whills"></i> Double-Entry Journal Vouchers (JV)</div>
                            <button class="btn btn-primary btn-sm" onclick="openModal('addJournalModal')"><i class="fa-solid fa-plus"></i> New JV</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>JV #</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Debit Account(s)</th>
                                        <th>Credit Account(s)</th>
                                        <th>Narration / Remarks</th>
                                        <th>Total Debit (₹)</th>
                                        <th>Total Credit (₹)</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($journals->isEmpty()): ?>
                                        <tr><td colspan="10" style="text-align: center; padding: 25px; color: #64748b;">No journal vouchers recorded yet. Click <strong>+ New Journal Entry</strong> to create one.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($journals as $jv): ?>
                                            <?php
                                                $debitNames = [];
                                                $creditNames = [];
                                                foreach ($jv->lines as $line) {
                                                    $accName = $line->account?->account_name ?: "Acc #{$line->account_id}";
                                                    if ($line->debit > 0) $debitNames[] = $accName . " (₹" . number_format($line->debit, 2) . ")";
                                                    if ($line->credit > 0) $creditNames[] = $accName . " (₹" . number_format($line->credit, 2) . ")";
                                                }
                                                $jvJson = htmlspecialchars(json_encode($jv), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <tr>
                                                <td>
                                                    <button type="button" class="btn btn-link" style="padding: 0; font-weight: 700; text-decoration: underline; color: #2563eb;" onclick='viewJournalDetails(<?= $jvJson ?>)'>
                                                        <?= htmlspecialchars($jv->journal_number) ?>
                                                    </button>
                                                </td>
                                                <td><?= htmlspecialchars($jv->entry_date) ?></td>
                                                <td><span class="badge badge-info"><?= htmlspecialchars($jv->entry_type) ?></span></td>
                                                <td><small><?= htmlspecialchars(implode(', ', $debitNames) ?: '-') ?></small></td>
                                                <td><small><?= htmlspecialchars(implode(', ', $creditNames) ?: '-') ?></small></td>
                                                <td><small><?= htmlspecialchars($jv->narration ?: $jv->description) ?></small></td>
                                                <td><strong style="color: #2563eb;">₹<?= number_format($jv->total_debit, 2) ?></strong></td>
                                                <td><strong style="color: #059669;">₹<?= number_format($jv->total_credit, 2) ?></strong></td>
                                                <td><span class="badge badge-success"><?= strtoupper(htmlspecialchars($jv->status)) ?></span></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-secondary" onclick='viewJournalDetails(<?= $jvJson ?>)'>
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 3: GENERAL LEDGER -->
            <?php if ($activeTab === 'general-ledger'): ?>
                <div id="tab-general-ledger" class="subnav-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-scale-balanced"></i> General Ledger Account Explorer</div>
                        </div>

                        <!-- Real Database Filter Controls -->
                        <form method="GET" action="<?= url('/accounting') ?>" style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
                            <input type="hidden" name="tab" value="general-ledger">

                            <div style="flex: 1; min-width: 260px;">
                                <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px;">Account Head *</label>
                                <select name="account_id" class="form-control" onchange="this.form.submit()">
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= $acc->id ?>" <?= $acc->id == $selectedAccountId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($acc->account_code . ' - ' . $acc->account_name . ' (' . $acc->account_type . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="width: 170px;">
                                <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px;">Financial Year</label>
                                <select name="fy" class="form-control" onchange="this.form.submit()">
                                    <option value="2026-27" <?= $selectedFy === '2026-27' ? 'selected' : '' ?>>FY 2026-27 (Current)</option>
                                    <option value="2025-26" <?= $selectedFy === '2025-26' ? 'selected' : '' ?>>FY 2025-26</option>
                                    <option value="2024-25" <?= $selectedFy === '2024-25' ? 'selected' : '' ?>>FY 2024-25</option>
                                </select>
                            </div>

                            <div style="width: 150px;">
                                <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px;">From Date</label>
                                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
                            </div>

                            <div style="width: 150px;">
                                <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px;">To Date</label>
                                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
                            </div>

                            <div>
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply Filter</button>
                            </div>
                        </form>

                        <!-- Ledger Presentation Table -->
                        <?php if ($ledgerData): ?>
                            <?php
                                $accObj = $ledgerData['account'] ?? null;
                                $accDisplayName = is_object($accObj) ? $accObj->account_name : ($ledgerData['account_name'] ?? 'Account');
                                $accDisplayCode = is_object($accObj) ? $accObj->account_code : ($ledgerData['account_code'] ?? '');
                                $ledgerEntries = $ledgerData['transactions'] ?? ($ledgerData['entries'] ?? []);
                            ?>
                            <div style="padding: 16px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;"><?= htmlspecialchars($accDisplayName) ?> <span class="badge badge-secondary"><?= htmlspecialchars($accDisplayCode) ?></span></h3>
                                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">Nature: <strong><?= htmlspecialchars($ledgerData['nature']) ?></strong> | Period: <?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?></p>
                                </div>
                                <div style="display: flex; gap: 20px; text-align: right;">
                                    <div>
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Opening Balance</div>
                                        <div style="font-size: 14px; font-weight: 800; color: #0f172a;">₹<?= number_format($ledgerData['opening_balance'], 2) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Period Debits</div>
                                        <div style="font-size: 14px; font-weight: 800; color: #2563eb;">₹<?= number_format($ledgerData['total_debit'], 2) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Period Credits</div>
                                        <div style="font-size: 14px; font-weight: 800; color: #059669;">₹<?= number_format($ledgerData['total_credit'], 2) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Closing Balance</div>
                                        <div style="font-size: 15px; font-weight: 800; color: #d97706;">₹<?= number_format($ledgerData['closing_balance'], 2) ?> (<?= htmlspecialchars($ledgerData['nature']) ?>)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Particulars / Narration</th>
                                            <th>Voucher Ref #</th>
                                            <th>Type</th>
                                            <th style="text-align: right;">Debit (₹)</th>
                                            <th style="text-align: right;">Credit (₹)</th>
                                            <th style="text-align: right;">Running Balance (₹)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="background: #f8fafc; font-style: italic;">
                                            <td><?= htmlspecialchars($fromDate) ?></td>
                                            <td colspan="5"><strong>Opening Balance B/F</strong></td>
                                            <td style="text-align: right;"><strong>₹<?= number_format($ledgerData['opening_balance'], 2) ?></strong></td>
                                        </tr>
                                        <?php if (empty($ledgerEntries)): ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center; padding: 20px; color: #64748b;">No transactions recorded for this account in the selected period.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($ledgerEntries as $ent): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ent['date']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($ent['description']) ?>
                                                        <?php if (!empty($ent['party_type'])): ?>
                                                            <span class="badge badge-info" style="font-size: 10px; margin-left: 4px;"><?= htmlspecialchars($ent['party_type']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong><?= htmlspecialchars($ent['journal_number']) ?></strong></td>
                                                    <td><span class="badge badge-secondary"><?= htmlspecialchars($ent['entry_type']) ?></span></td>
                                                    <td style="text-align: right; color: #2563eb; font-weight: 600;">
                                                        <?= $ent['debit'] > 0 ? '₹' . number_format($ent['debit'], 2) : '-' ?>
                                                    </td>
                                                    <td style="text-align: right; color: #059669; font-weight: 600;">
                                                        <?= $ent['credit'] > 0 ? '₹' . number_format($ent['credit'], 2) : '-' ?>
                                                    </td>
                                                    <td style="text-align: right; font-weight: 800;">
                                                        ₹<?= number_format($ent['running_balance'], 2) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <tr style="background: #f1f5f9; font-weight: 800; border-top: 2px solid #0f172a;">
                                            <td colspan="4">PERIOD TOTALS / CLOSING BALANCE</td>
                                            <td style="text-align: right; color: #2563eb;">₹<?= number_format($ledgerData['total_debit'], 2) ?></td>
                                            <td style="text-align: right; color: #059669;">₹<?= number_format($ledgerData['total_credit'], 2) ?></td>
                                            <td style="text-align: right; color: #d97706;">₹<?= number_format($ledgerData['closing_balance'], 2) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: BANKING & CASH -->
            <?php if ($activeTab === 'banking-cash'): ?>
                <div id="tab-banking-cash" class="subnav-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Registered Bank Accounts & Cash Desks</h3>
                        <button class="btn btn-primary btn-sm" onclick="openModal('addBankModal')"><i class="fa-solid fa-plus"></i> Add Bank Account</button>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <?php if ($bankAccounts->isEmpty()): ?>
                            <div class="card" style="padding: 20px; text-align: center; color: #64748b;">
                                No bank accounts registered. Click <strong>Add Bank Account</strong> to configure one.
                            </div>
                        <?php else: ?>
                            <?php foreach ($bankAccounts as $ba): ?>
                                <div class="card" style="padding: 20px; border-left: 4px solid #2563eb;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <span class="badge badge-primary" style="font-size: 11px;"><?= strtoupper(htmlspecialchars($ba->account_type)) ?></span>
                                            <?php if ($ba->is_primary): ?>
                                                <span class="badge badge-success" style="font-size: 11px; margin-left: 4px;">PRIMARY</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge <?= $ba->is_active ? 'badge-success' : 'badge-danger' ?>"><?= $ba->is_active ? 'ACTIVE' : 'INACTIVE' ?></span>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 8px;"><?= htmlspecialchars($ba->bank_name) ?></div>
                                    <div style="font-size: 13px; color: #64748b; margin-bottom: 12px;"><?= htmlspecialchars($ba->account_name) ?> | <?= htmlspecialchars($ba->masked_account_number) ?> | IFSC: <?= htmlspecialchars($ba->ifsc_code ?: $ba->ifsc) ?></div>
                                    <div style="font-size: 22px; font-weight: 800; color: #059669;">₹<?= number_format($ba->current_balance, 2) ?></div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Opening Bal: ₹<?= number_format($ba->opening_balance, 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Bank Transactions Feed -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-money-bill-transfer"></i> Recent Bank &amp; Cash Transactions</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Bank Account</th>
                                        <th>Type</th>
                                        <th>Source / Ref</th>
                                        <th>Description</th>
                                        <th style="text-align: right;">Amount (₹)</th>
                                        <th style="text-align: right;">Balance After (₹)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($bankTransactions->isEmpty()): ?>
                                        <tr><td colspan="8" style="text-align: center; padding: 25px; color: #64748b;">No bank transactions recorded yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($bankTransactions as $tx): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tx->transaction_date?->format('Y-m-d') ?: date('Y-m-d')) ?></td>
                                                <td><strong><?= htmlspecialchars($tx->bankAccount?->bank_name ?: 'Bank') ?></strong></td>
                                                <td>
                                                    <span class="badge <?= $tx->type === 'CREDIT' ? 'badge-success' : 'badge-danger' ?>">
                                                        <?= htmlspecialchars($tx->type) ?>
                                                    </span>
                                                </td>
                                                <td><strong><?= htmlspecialchars($tx->reference_number ?: ($tx->reference_no ?: $tx->source)) ?></strong></td>
                                                <td><?= htmlspecialchars($tx->description) ?></td>
                                                <td style="text-align: right; font-weight: 800; color: <?= $tx->type === 'CREDIT' ? '#059669' : '#dc2626' ?>;">
                                                    <?= $tx->type === 'CREDIT' ? '+' : '-' ?>₹<?= number_format($tx->amount, 2) ?>
                                                </td>
                                                <td style="text-align: right; font-weight: 700;">₹<?= number_format($tx->balance_after, 2) ?></td>
                                                <td><span class="badge badge-secondary"><?= htmlspecialchars($tx->reconciliation_status ?: 'UNRECONCILED') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: TRIAL BALANCE -->
            <?php if ($activeTab === 'trial-balance'): ?>
                <div id="tab-trial-balance" class="subnav-pane">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div class="card-title"><i class="fa-solid fa-scale-unbalanced"></i> Trial Balance as of <?= htmlspecialchars($toDate ?: date('Y-m-d')) ?></div>
                                <small class="text-muted">Financial Year: <?= htmlspecialchars($selectedFy) ?></small>
                            </div>
                            <div>
                                <?php if ($trialBalance['is_balanced']): ?>
                                    <span class="badge badge-success" style="font-size: 13px; padding: 6px 12px;"><i class="fa-solid fa-circle-check"></i> DOUBLE-ENTRY BALANCED</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size: 13px; padding: 6px 12px;"><i class="fa-solid fa-triangle-exclamation"></i> UNBALANCED (Delta: ₹<?= number_format($trialBalance['difference'], 2) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Account Code</th>
                                        <th>Account Head Particulars</th>
                                        <th>Classification</th>
                                        <th style="text-align: right;">Debit (₹)</th>
                                        <th style="text-align: right;">Credit (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($trialBalance['accounts'])): ?>
                                        <tr><td colspan="5" style="text-align: center; padding: 25px;">No active ledger balances found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($trialBalance['accounts'] as $row): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($row['account_code']) ?></strong></td>
                                                <td><strong><?= htmlspecialchars($row['account_name']) ?></strong></td>
                                                <td><span class="badge badge-secondary"><?= htmlspecialchars($row['account_type']) ?></span></td>
                                                <td style="text-align: right; color: #2563eb; font-weight: 600;">
                                                    <?= $row['debit'] > 0 ? '₹' . number_format($row['debit'], 2) : '-' ?>
                                                </td>
                                                <td style="text-align: right; color: #059669; font-weight: 600;">
                                                    <?= $row['credit'] > 0 ? '₹' . number_format($row['credit'], 2) : '-' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <tr style="background: #0f172a; color: #ffffff; font-weight: 800; font-size: 15px;">
                                        <td colspan="3" style="color: #ffffff;">TOTAL TRIAL BALANCE SUM</td>
                                        <td style="text-align: right; color: #60a5fa;">₹<?= number_format($trialBalance['total_debit'], 2) ?></td>
                                        <td style="text-align: right; color: #34d399;">₹<?= number_format($trialBalance['total_credit'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal: View Journal Voucher Details -->
<div class="modal-overlay" id="viewJournalModal">
    <div class="modal-content" style="max-width: 750px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-invoice text-primary"></i> Journal Voucher Details: <span id="vJvNumber"></span></div>
            <button class="modal-close" onclick="closeModal('viewJournalModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px; background: #f8fafc; padding: 12px; border-radius: 6px;">
                <div>
                    <div style="font-size: 11px; color: #64748b;">ENTRY DATE</div>
                    <div id="vJvDate" style="font-weight: 700; color: #0f172a;"></div>
                </div>
                <div>
                    <div style="font-size: 11px; color: #64748b;">TYPE / FY</div>
                    <div id="vJvType" style="font-weight: 700; color: #0f172a;"></div>
                </div>
                <div>
                    <div style="font-size: 11px; color: #64748b;">STATUS</div>
                    <div id="vJvStatus" style="font-weight: 700; color: #059669;"></div>
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <div style="font-size: 11px; color: #64748b;">NARRATION / DESCRIPTION</div>
                <div id="vJvNarration" style="font-size: 13px; color: #334155; font-style: italic; background: #fff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; margin-top: 4px;"></div>
            </div>

            <h4 style="font-size: 13px; font-weight: 800; margin-bottom: 8px;">Voucher Itemized Ledger Lines</h4>
            <div class="table-responsive">
                <table class="table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Account Head</th>
                            <th>Description</th>
                            <th style="text-align: right;">Debit (₹)</th>
                            <th style="text-align: right;">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody id="vJvLinesBody">
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 800; background: #f8fafc; border-top: 2px solid #0f172a;">
                            <td colspan="2">TOTALS (Double-Entry Invariant)</td>
                            <td style="text-align: right; color: #2563eb;" id="vJvTotalDebit">₹0.00</td>
                            <td style="text-align: right; color: #059669;" id="vJvTotalCredit">₹0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewJournalModal')">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Add Manual Journal Voucher -->
<div class="modal-overlay" id="addJournalModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-pen-to-square text-primary"></i> Record Double-Entry Journal Voucher</div>
            <button class="modal-close" onclick="closeModal('addJournalModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/accounting') ?>" onsubmit="return validateJournalForm(this)">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_journal">
            <input type="hidden" name="financial_year" value="<?= htmlspecialchars($financialYear) ?>">

            <div class="modal-body">
                <div id="journalErrorMsg" style="display: none; padding: 10px 14px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b; font-size: 13px; margin-bottom: 14px;"></div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Voucher Date *</label>
                        <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Voucher Type *</label>
                        <select name="entry_type" class="form-control">
                            <option value="MANUAL">Manual Adjustment</option>
                            <option value="BANK_TRANSFER">Bank Transfer / Contra</option>
                            <option value="EXPENSE">Expense Disbursement</option>
                            <option value="OPENING_BALANCE">Opening Balance Setup</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Debit Account Head (Dr) *</label>
                    <select name="debit_account_id" id="jvDebitAcc" class="form-control" required>
                        <option value="">-- Select Debit Account --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc->id ?>"><?= htmlspecialchars($acc->account_code . ' - ' . $acc->account_name . ' (' . $acc->account_type . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Credit Account Head (Cr) *</label>
                    <select name="credit_account_id" id="jvCreditAcc" class="form-control" required>
                        <option value="">-- Select Credit Account --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc->id ?>"><?= htmlspecialchars($acc->account_code . ' - ' . $acc->account_name . ' (' . $acc->account_type . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Voucher Amount (₹) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="jvAmount" class="form-control" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Narration / Remarks *</label>
                    <input type="text" name="narration" class="form-control" placeholder="e.g. Monthly rent advance or inter-bank transfer" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addJournalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Post Double-Entry Journal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Account Head -->
<div class="modal-overlay" id="addAccountModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-folder-plus text-primary"></i> Add New Account Head</div>
            <button class="modal-close" onclick="closeModal('addAccountModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/accounting') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_account">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Code *</label>
                        <input type="text" name="account_code" class="form-control" placeholder="e.g. 4050" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Classification *</label>
                        <select name="account_type" class="form-control" required>
                            <option value="ASSET">Asset</option>
                            <option value="LIABILITY">Liability</option>
                            <option value="EQUITY">Equity</option>
                            <option value="INCOME">Income / Revenue</option>
                            <option value="EXPENSE">Expense</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Account Head Name *</label>
                    <input type="text" name="account_name" class="form-control" placeholder="e.g. Server Infrastructure Hosting" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subtype (Optional)</label>
                        <input type="text" name="account_subtype" class="form-control" placeholder="e.g. OPERATING_EXPENSES">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Balance (₹)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Account purpose / notes">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAccountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Account Head</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Bank Account -->
<div class="modal-overlay" id="addBankModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-building-columns text-primary"></i> Register Bank Account</div>
            <button class="modal-close" onclick="closeModal('addBankModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/accounting') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_bank">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Bank Name *</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. HDFC Bank" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Holder / Display Name</label>
                    <input type="text" name="account_name" class="form-control" placeholder="e.g. Main Current Account">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Number *</label>
                        <input type="text" name="account_number" class="form-control" placeholder="e.g. 50200089201920" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-control" placeholder="e.g. HDFC0000123">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Type</label>
                        <select name="account_type" class="form-control">
                            <option value="CURRENT">Current Account</option>
                            <option value="SAVINGS">Savings Account</option>
                            <option value="OD">Overdraft (OD)</option>
                            <option value="CASH_CREDIT">Cash Credit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Balance (₹)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_primary" id="isPrimaryBank" value="1">
                    <label for="isPrimaryBank" class="form-label" style="margin: 0;">Set as Primary Company Bank Account</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBankModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Register Bank</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewJournalDetails(jv) {
    if (!jv) return;

    document.getElementById('vJvNumber').innerText = jv.journal_number || ('JV #' + jv.id);
    document.getElementById('vJvDate').innerText = jv.entry_date || '-';
    document.getElementById('vJvType').innerText = (jv.entry_type || 'MANUAL') + ' (' + (jv.financial_year || '2026-27') + ')';
    document.getElementById('vJvStatus').innerText = (jv.status || 'POSTED').toUpperCase();
    document.getElementById('vJvNarration').innerText = jv.narration || jv.description || 'No narration provided.';

    let tbody = document.getElementById('vJvLinesBody');
    tbody.innerHTML = '';

    let totalDr = 0;
    let totalCr = 0;

    if (jv.lines && jv.lines.length > 0) {
        jv.lines.forEach(line => {
            let tr = document.createElement('tr');
            let accName = line.account ? (line.account.account_code + ' - ' + line.account.account_name) : ('Account #' + line.account_id);
            let dr = parseFloat(line.debit || 0);
            let cr = parseFloat(line.credit || 0);
            totalDr += dr;
            totalCr += cr;

            tr.innerHTML = `
                <td><strong>${accName}</strong></td>
                <td>${line.description || '-'}</td>
                <td style="text-align: right; color: #2563eb; font-weight: 600;">${dr > 0 ? '₹' + dr.toFixed(2) : '-'}</td>
                <td style="text-align: right; color: #059669; font-weight: 600;">${cr > 0 ? '₹' + cr.toFixed(2) : '-'}</td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No line items found.</td></tr>';
    }

    document.getElementById('vJvTotalDebit').innerText = '₹' + totalDr.toFixed(2);
    document.getElementById('vJvTotalCredit').innerText = '₹' + totalCr.toFixed(2);

    openModal('viewJournalModal');
}

function validateJournalForm(form) {
    let drAcc = document.getElementById('jvDebitAcc').value;
    let crAcc = document.getElementById('jvCreditAcc').value;
    let amount = parseFloat(document.getElementById('jvAmount').value || 0);
    let errBox = document.getElementById('journalErrorMsg');
    errBox.style.display = 'none';

    if (!drAcc || !crAcc) {
        errBox.innerText = 'Both Debit and Credit accounts must be selected.';
        errBox.style.display = 'block';
        return false;
    }
    if (drAcc === crAcc) {
        errBox.innerText = 'Debit Account and Credit Account cannot be the same.';
        errBox.style.display = 'block';
        return false;
    }
    if (amount <= 0 || isNaN(amount)) {
        errBox.innerText = 'Voucher amount must be greater than zero.';
        errBox.style.display = 'block';
        return false;
    }
    return true;
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
