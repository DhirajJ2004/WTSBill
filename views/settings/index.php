<?php
$pageTitle = 'Company & System Settings - WTSBill ERP';
$pageHeader = 'Company Profile & ERP Preferences';
$currentRoute = 'settings';

require_once __DIR__ . '/../db_helper.php';

use App\Models\Company;
use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$company = Company::find($companyId);
if (!$company) {
    $company = Company::first();
    $companyId = $company?->id ?? 1;
}

$submitError = null;
$submitSuccess = null;

if (isset($_GET['updated'])) {
    $submitSuccess = "Company settings updated successfully!";
}

// -------------------------------------------------------------
// Handle POST for Settings
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? 'save_settings';

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $userName = $_SESSION['user']['name'] ?? 'Admin';

            if ($action === 'save_settings') {
                $name = trim($input['name'] ?? ($input['company_name'] ?? ''));
                if (empty($name)) {
                    throw new \InvalidArgumentException('Company Registered Name is required.');
                }

                $gstin = trim($input['gstin'] ?? '');
                $state = trim($input['state'] ?? ($input['state_code'] ?? ''));
                $phone = trim($input['phone'] ?? '');
                $email = trim($input['email'] ?? '');
                $address = trim($input['address'] ?? '');

                $company->name = $name;
                $company->legal_name = $name;
                if (!empty($gstin)) $company->gstin = $gstin;
                if (!empty($state)) $company->state = $state;
                if (!empty($phone)) $company->phone = $phone;
                if (!empty($email)) $company->email = $email;
                if (!empty($address)) $company->address = $address;
                $company->save();

                // Update default bank account if provided
                if (!empty($input['bank_name']) && !empty($input['account_number'])) {
                    $bank = BankAccount::where('company_id', $companyId)->where('is_primary', 1)->first();
                    if (!$bank) {
                        $bank = BankAccount::where('company_id', $companyId)->first();
                    }
                    if ($bank) {
                        $bank->bank_name = trim($input['bank_name']);
                        $bank->account_number = trim($input['account_number']);
                        if (!empty($input['ifsc'])) $bank->ifsc_code = trim($input['ifsc']);
                        $bank->save();
                    }
                }

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'COMPANY_UPDATE',
                    'Company',
                    $company->id,
                    "Updated profile and preferences for company '{$company->name}'"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => 'Settings updated successfully', 'data' => $company]);
                } else {
                    $targetUrl = url('/settings?updated=1');
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            } elseif ($action === 'download_backup') {
                // Generate a database JSON snapshot backup
                $backupData = [
                    'company' => $company,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'invoices_count' => DB::table('invoices')->where('company_id', $companyId)->count(),
                    'customers_count' => DB::table('customers')->where('company_id', $companyId)->count(),
                    'products_count' => DB::table('products')->where('company_id', $companyId)->count(),
                    'journals_count' => DB::table('journal_entries')->where('company_id', $companyId)->count(),
                ];

                $filename = 'wtsbill_backup_' . date('Ymd_His') . '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($backupData, JSON_PRETTY_PRINT);
                exit;
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

$primaryBank = BankAccount::where('company_id', $companyId)->where('is_primary', 1)->first() ?: BankAccount::where('company_id', $companyId)->first();

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

        <div class="card" style="max-width: 900px;">
            <!-- Settings Tabs Header -->
            <div style="display: flex; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 24px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary btn-sm set-tab-btn active" onclick="switchSettingTab('profile')">
                    <i class="fa-solid fa-building"></i> Company Profile
                </button>
                <button type="button" class="btn btn-outline btn-sm set-tab-btn" onclick="switchSettingTab('numbering')">
                    <i class="fa-solid fa-hashtag"></i> Document Numbering
                </button>
                <button type="button" class="btn btn-outline btn-sm set-tab-btn" onclick="switchSettingTab('smtp')">
                    <i class="fa-solid fa-envelope"></i> Email / SMTP
                </button>
                <button type="button" class="btn btn-outline btn-sm set-tab-btn" onclick="switchSettingTab('currency')">
                    <i class="fa-solid fa-coins"></i> Multi-Currency
                </button>
                <button type="button" class="btn btn-outline btn-sm set-tab-btn" onclick="switchSettingTab('backup')">
                    <i class="fa-solid fa-database"></i> Backup &amp; Maintenance
                </button>
            </div>

            <form method="POST" action="<?= url('/settings') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <!-- Tab 1: Company Profile -->
                <div id="profileTab" class="setting-tab">
                    <div class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-building text-primary"></i> Company Business Profile &amp; GSTIN Details</div>

                    <div class="form-group">
                        <label class="form-label">Company Registered Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($company->name ?? 'Wis Technosavvy Pvt Ltd') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">GSTIN / Tax ID</label>
                            <input type="text" name="gstin" class="form-control" value="<?= htmlspecialchars($company->gstin ?? '27AAAAA0000A1Z5') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State / Place of Supply</label>
                            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($company->state ?? 'Maharashtra') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Support Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($company->phone ?? '+91 98200 99999') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Support Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company->email ?? 'billing@wtsbill.com') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Registered Office Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($company->address ?? 'Plot 45, MIDC Industrial Estate, Andheri East, Mumbai - 400093') ?></textarea>
                    </div>

                    <div style="background: var(--bg-main, #f8fafc); padding: 16px; border-radius: 8px; margin-top: 16px; border: 1px solid #e2e8f0;">
                        <h4 style="font-weight: 700; margin-bottom: 12px; font-size: 14px;"><i class="fa-solid fa-building-columns text-primary"></i> Primary Bank Details for Invoices</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($primaryBank?->bank_name ?? 'HDFC Bank') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($primaryBank?->account_number ?? '50200012345678') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" name="ifsc" class="form-control" value="<?= htmlspecialchars($primaryBank?->ifsc_code ?: ($primaryBank?->ifsc ?: 'HDFC0000123')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($primaryBank?->account_name ?: ($company->name ?? 'Wis Technosavvy')) ?>" readonly style="background: #f1f5f9;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Document Numbering -->
                <div id="numberingTab" class="setting-tab" style="display: none;">
                    <div class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-list-ol text-primary"></i> Invoice &amp; Document Numbering Formats</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sales Invoice Prefix</label>
                            <input type="text" class="form-control" value="INV" readonly style="background: #f1f5f9;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Financial Year Scoping</label>
                            <input type="text" class="form-control" value="FY26-27 / Automated" readonly style="background: #f1f5f9;">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Quotation Prefix</label>
                            <input type="text" class="form-control" value="QT" readonly style="background: #f1f5f9;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Receipt Prefix</label>
                            <input type="text" class="form-control" value="REC" readonly style="background: #f1f5f9;">
                        </div>
                    </div>
                </div>

                <!-- Tab 3: SMTP -->
                <div id="smtpTab" class="setting-tab" style="display: none;">
                    <div class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-paper-plane text-primary"></i> Email &amp; Notification Settings</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SMTP Host Server</label>
                            <input type="text" class="form-control" value="smtp.mailtrap.io">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Port</label>
                            <input type="text" class="form-control" value="587">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sender Email Address</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($company->email ?? 'noreply@wtsbill.com') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Encryption Type</label>
                            <select class="form-control">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Multi-Currency -->
                <div id="currencyTab" class="setting-tab" style="display: none;">
                    <div class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-coins text-primary"></i> Multi-Currency Invoicing &amp; Base Currency</div>
                    <div class="form-group">
                        <label class="form-label">Default Base Currency</label>
                        <select class="form-control">
                            <option value="INR" selected>INR (₹) - Indian Rupee (Default Base Currency)</option>
                            <option value="USD">USD ($) - US Dollar</option>
                            <option value="EUR">EUR (€) - Euro</option>
                            <option value="GBP">GBP (£) - British Pound</option>
                        </select>
                    </div>
                </div>

                <!-- Tab 5: Backup & Maintenance -->
                <div id="backupTab" class="setting-tab" style="display: none;">
                    <div class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-server text-primary"></i> Database Backup &amp; Maintenance</div>
                    <div style="background: #f8fafc; padding: 18px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0;">
                        <h4 style="font-weight: 700; margin-bottom: 6px;"><i class="fa-solid fa-download text-primary"></i> Instant Company Data Snapshot</h4>
                        <p style="color: #64748b; font-size: 13px; margin-bottom: 14px;">Generate and download a complete JSON backup archive of company records, invoices, parties, and ledger entries.</p>
                        <button type="submit" name="action" value="download_backup" class="btn btn-secondary">
                            <i class="fa-solid fa-download"></i> Download Company Snapshot Backup
                        </button>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save All Settings</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function switchSettingTab(tabId) {
    document.querySelectorAll('.setting-tab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.set-tab-btn').forEach(el => {
        el.classList.remove('btn-primary', 'active');
        el.classList.add('btn-outline');
    });

    const target = document.getElementById(tabId + 'Tab');
    if (target) target.style.display = 'block';
    const btn = window.event ? (window.event.currentTarget || window.event.target.closest('.set-tab-btn')) : null;
    if (btn) {
        btn.classList.remove('btn-outline');
        btn.classList.add('btn-primary', 'active');
    }
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>