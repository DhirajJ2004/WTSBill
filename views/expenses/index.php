<?php
$pageTitle = 'Expense Management - WTSBill ERP';
$pageHeader = 'Operational Expenses & Vendor Bills';
$currentRoute = 'expenses';

require_once __DIR__ . '/../db_helper.php';

use App\Models\Expense;
use App\Models\BankAccount;
use App\Services\AccountingEventService;
use App\Services\DocumentNumberService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;
$financialYear = get_current_financial_year();

$submitError = null;
$submitSuccess = null;

if (isset($_GET['created'])) {
    $submitSuccess = "Expense #" . htmlspecialchars($_GET['created']) . " recorded and posted successfully!";
}

// -------------------------------------------------------------
// Handle Web Form POST /expenses
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $amount = round(floatval($input['amount'] ?? 0), 2);
            if ($amount <= 0.001) {
                throw new \InvalidArgumentException('Expense amount must be greater than zero.');
            }

            $category = trim($input['category'] ?? '');
            if (empty($category)) {
                throw new \InvalidArgumentException('Expense Category is required.');
            }

            $vendor = trim($input['payee'] ?? ($input['vendor'] ?? 'General Vendor'));
            if (empty($vendor)) {
                throw new \InvalidArgumentException('Vendor / Payee name is required.');
            }

            $taxAmount = round(floatval($input['tax_amount'] ?? 0), 2);
            if ($taxAmount < 0 || $taxAmount >= $amount) {
                throw new \InvalidArgumentException('Tax amount must be non-negative and less than total expense amount.');
            }

            $paymentMode = strtoupper(str_replace(' ', '_', trim($input['payment_mode'] ?? 'BANK_TRANSFER')));
            if ($paymentMode === 'PETTY_CASH') $paymentMode = 'CASH';

            $validModes = ['BANK_TRANSFER', 'CARD', 'UPI', 'CHEQUE', 'CASH', 'NEFT', 'RTGS', 'IMPS'];
            if (!in_array($paymentMode, $validModes)) {
                throw new \InvalidArgumentException("Invalid payment mode '{$paymentMode}'. Supported modes: " . implode(', ', $validModes));
            }

            $expDate = $input['expense_date'] ?? date('Y-m-d');
            $gstin = trim($input['gstin'] ?? '');
            $isItcEligible = isset($input['is_itc_eligible']) ? boolval($input['is_itc_eligible']) : true;
            $referenceNo = trim($input['reference_no'] ?? ($input['reference'] ?? ''));
            $description = trim($input['description'] ?? '');
            $userName = $_SESSION['user']['name'] ?? 'Admin';

            $expense = DB::transaction(function () use ($companyId, $branchId, $financialYear, $category, $vendor, $expDate, $amount, $taxAmount, $paymentMode, $gstin, $isItcEligible, $referenceNo, $description, $input, $userName) {
                $expNumber = DocumentNumberService::generateNextNumber($companyId, $branchId, $financialYear, 'EXPENSE');

                $expense = Expense::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'expense_number' => $expNumber,
                    'category' => $category,
                    'payee' => $vendor,
                    'expense_date' => $expDate,
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'payment_mode' => $paymentMode,
                    'gstin' => $gstin,
                    'is_itc_eligible' => $isItcEligible,
                    'reference_no' => $referenceNo,
                    'description' => $description,
                ]);

                // Update Bank Account & Bank Transactions if paid via bank
                $bankAccountId = !empty($input['bank_account_id']) ? intval($input['bank_account_id']) : null;
                if (!$bankAccountId && in_array($paymentMode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'NEFT', 'RTGS', 'IMPS'])) {
                    $defaultBank = BankAccount::where('company_id', $companyId)->where('is_active', 1)->first();
                    if ($defaultBank) {
                        $bankAccountId = $defaultBank->id;
                    }
                }

                if ($bankAccountId) {
                    $bankAcc = BankAccount::where('company_id', $companyId)->find($bankAccountId);
                    if ($bankAcc) {
                        $bankAcc->current_balance = round($bankAcc->current_balance - $amount, 2);
                        $bankAcc->save();

                        \App\Models\BankTransaction::create([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'bank_account_id' => $bankAccountId,
                            'transaction_date' => $expDate,
                            'type' => 'DEBIT',
                            'debit_credit' => 'DEBIT',
                            'transaction_type' => 'EXPENSE',
                            'reference_number' => $expNumber,
                            'description' => "Expense: {$category} - {$vendor} (Ref: {$expNumber})",
                            'amount' => $amount,
                            'balance_after' => $bankAcc->current_balance,
                            'reconciliation_status' => 'UNRECONCILED',
                            'source' => 'EXPENSE',
                            'source_id' => $expense->id,
                        ]);
                    }
                }

                // Post Double-Entry Accounting Journal
                AccountingEventService::recordExpenseAccounting($expense, $userName);

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'EXPENSE_CREATE',
                    'Expense',
                    $expense->id,
                    "Recorded expense {$expNumber} ({$category}) of ₹" . number_format($amount, 2) . " to {$vendor} via {$paymentMode}"
                );

                return $expense;
            });

            if ($isJson) {
                response_json([
                    'status' => 'success',
                    'message' => "Expense #{$expense->expense_number} recorded successfully",
                    'data' => $expense,
                ], 201);
            } else {
                $targetUrl = url('/expenses?created=' . urlencode($expense->expense_number));
                if (!headers_sent()) {
                    header("Location: " . $targetUrl);
                    exit;
                }
                echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                exit;
            }
        } catch (\Throwable $e) {
            if ($isJson) {
                response_json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 422);
            } else {
                $submitError = $e->getMessage();
            }
        }
    }
}

$expenses = get_expenses();
$bankAccounts = BankAccount::where('company_id', $companyId)->where('is_active', 1)->get();

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

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-wallet"></i> Operational Expense Tracker</div>
                <button class="btn btn-primary" onclick="openModal('addExpenseModal')"><i class="fa-solid fa-plus"></i> + Record Expense</button>
            </div>

            <!-- Filters -->
            <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                <input type="text" id="expSearch" class="form-control" placeholder="Search expense #, vendor, category..." style="max-width: 320px;" onkeyup="filterExpenses()">
                <select id="catFilter" class="form-control" style="max-width: 200px;" onchange="filterExpenses()">
                    <option value="">All Categories</option>
                    <option value="cloud">Cloud Infrastructure</option>
                    <option value="rent">Office Rent</option>
                    <option value="software">Software Subscriptions</option>
                    <option value="salary">Staff Salaries</option>
                    <option value="marketing">Marketing</option>
                    <option value="professional">Professional Fees</option>
                    <option value="repairs">Repairs & Maintenance</option>
                    <option value="supplies">Office Supplies</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table" id="expTable">
                    <thead>
                        <tr>
                            <th>Expense #</th>
                            <th>Category</th>
                            <th>Vendor / Payee</th>
                            <th>Expense Date</th>
                            <th>Payment Mode</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">No expense records found. Click <strong>+ Record Expense</strong> to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                                <tr data-cat="<?= strtolower($e['category'] ?? '') ?>">
                                    <td><strong><?= htmlspecialchars($e['expense_number'] ?? 'EXP-' . $e['id']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($e['category'] ?? 'General') ?></span></td>
                                    <td><strong><?= htmlspecialchars($e['vendor'] ?? 'Vendor') ?></strong></td>
                                    <td><?= htmlspecialchars($e['expense_date'] ?? date('Y-m-d')) ?></td>
                                    <td><?= htmlspecialchars($e['payment_mode'] ?? 'Bank') ?></td>
                                    <td><strong style="color: var(--danger);">₹<?= number_format($e['amount'] ?? 0, 2) ?></strong></td>
                                    <td><span class="badge badge-success"><?= strtoupper(htmlspecialchars($e['status'] ?? 'PAID')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Add Expense -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-receipt text-primary"></i> Record New Operational Expense</div>
            <button class="modal-close" onclick="closeModal('addExpenseModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/expenses') ?>" id="expenseForm">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div id="expenseErrorMsg" style="display: none; padding: 10px 14px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b; font-size: 13px; margin-bottom: 14px;"></div>

                <div class="form-group">
                    <label class="form-label">Expense Category *</label>
                    <select class="form-control" name="category" id="expCategory" required>
                        <option value="Cloud Infrastructure">Cloud Infrastructure (AWS / DigitalOcean / Azure)</option>
                        <option value="Office Rent">Office Rent & Utilities</option>
                        <option value="Software Subscriptions">Software & SaaS Licenses</option>
                        <option value="Staff Salaries">Payroll & Contractor Compensation</option>
                        <option value="Marketing">Marketing & Client Acquisition</option>
                        <option value="Professional Fees">Legal, Audit & Professional Fees</option>
                        <option value="Repairs & Maintenance">Repairs & Maintenance</option>
                        <option value="Office Supplies">Office Supplies & Stationery</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Vendor / Payee Name *</label>
                        <input type="text" class="form-control" name="vendor" id="expVendor" placeholder="e.g. AWS Web Services" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expense Date *</label>
                        <input type="date" class="form-control" name="expense_date" id="expDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Total Amount Paid (₹) *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="expAmount" placeholder="18500.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">GST / Tax Amount (₹)</label>
                        <input type="number" step="0.01" min="0.00" class="form-control" name="tax_amount" id="expTaxAmount" placeholder="0.00" value="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Vendor GSTIN (Optional)</label>
                        <input type="text" class="form-control" name="gstin" id="expGstin" placeholder="27BBBBB1234C1Z5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Mode *</label>
                        <select class="form-control" name="payment_mode" id="expPaymentMode" required>
                            <option value="BANK_TRANSFER">Company Bank Account</option>
                            <option value="CARD">Corporate Credit / Debit Card</option>
                            <option value="UPI">UPI / Digital Payment</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="CASH">Petty Cash</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Paid From Bank Account</label>
                        <select class="form-control" name="bank_account_id" id="expBankAccount">
                            <option value="">-- Default Company Bank --</option>
                            <?php foreach ($bankAccounts as $ba): ?>
                                <option value="<?= $ba->id ?>"><?= htmlspecialchars($ba->bank_name . ' - ' . $ba->account_name . ' (' . $ba->masked_account_number . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference / Bill / UTR No.</label>
                        <input type="text" class="form-control" name="reference_no" id="expReference" placeholder="e.g. INV-98124">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ITC Eligibility</label>
                        <select class="form-control" name="is_itc_eligible" id="expItcEligible">
                            <option value="1">Eligible for GST Input Tax Credit (ITC)</option>
                            <option value="0">Ineligible / Blocked ITC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description / Remarks</label>
                        <input type="text" class="form-control" name="description" id="expDescription" placeholder="Monthly recurring server charge">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveExpense"><i class="fa-solid fa-check"></i> Save Expense</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterExpenses() {
    let q = (document.getElementById('expSearch').value || '').toLowerCase();
    let cat = (document.getElementById('catFilter').value || '').toLowerCase();
    let rows = document.querySelectorAll('#expTable tbody tr');
    
    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rCat = (r.getAttribute('data-cat') || '').toLowerCase();
        
        let matchText = !q || text.includes(q);
        let matchCat = !cat || rCat.includes(cat);
        
        if (matchText && matchCat) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
