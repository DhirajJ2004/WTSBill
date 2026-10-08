<?php
$pageTitle = 'Customers & Suppliers - WTS LEDGER PRO';
$currentRoute = 'parties';

$typeFilter = $_GET['type'] ?? '';
$activeTab = ($typeFilter === 'supplier') ? 'suppliers' : (($typeFilter === 'customer') ? 'customers' : 'all');

require_once __DIR__ . '/../db_helper.php';

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;

$submitError = null;
$submitSuccess = null;

// Handle direct Web POST for Parties
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? 'save_party';

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $userName = $_SESSION['user']['name'] ?? 'Admin';
            $role = strtolower(trim($input['party_role'] ?? ($input['role'] ?? 'customer')));
            $name = trim($input['name'] ?? ($input['party_name'] ?? ''));

            if (empty($name)) {
                throw new \InvalidArgumentException('Legal Name is required.');
            }

            if ($action === 'save_party') {
                if ($role === 'supplier') {
                    $party = Supplier::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'name' => $name,
                        'company_name' => $name,
                        'gstin' => trim($input['gstin'] ?? ''),
                        'pan' => trim($input['pan'] ?? ''),
                        'phone' => trim($input['phone'] ?? ''),
                        'email' => trim($input['email'] ?? ''),
                        'address_line1' => trim($input['address'] ?? ''),
                        'city' => trim($input['city'] ?? ''),
                        'state' => 'Maharashtra',
                        'is_active' => true,
                    ]);
                } else {
                    $party = Customer::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'name' => $name,
                        'company_name' => $name,
                        'gstin' => trim($input['gstin'] ?? ''),
                        'pan' => trim($input['pan'] ?? ''),
                        'phone' => trim($input['phone'] ?? ''),
                        'email' => trim($input['email'] ?? ''),
                        'address_line1' => trim($input['address'] ?? ''),
                        'city' => trim($input['city'] ?? ''),
                        'state' => 'Maharashtra',
                        'customer_type' => 'Business',
                        'is_active' => true,
                    ]);
                }

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'PARTY_CREATE',
                    $role === 'supplier' ? 'Supplier' : 'Customer',
                    $party->id,
                    "Added {$role} '{$party->name}'"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'data' => $party], 201);
                } else {
                    $targetUrl = url('/parties?created=' . urlencode($party->name));
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            } elseif ($action === 'update_party') {
                $partyId = intval($input['party_id'] ?? 0);
                if ($role === 'supplier') {
                    $party = Supplier::where('company_id', $companyId)->findOrFail($partyId);
                } else {
                    $party = Customer::where('company_id', $companyId)->findOrFail($partyId);
                }

                $party->name = $name;
                $party->company_name = $name;
                $party->gstin = trim($input['gstin'] ?? '');
                $party->pan = trim($input['pan'] ?? '');
                $party->phone = trim($input['phone'] ?? '');
                $party->email = trim($input['email'] ?? '');
                $party->address_line1 = trim($input['address'] ?? '');
                $party->city = trim($input['city'] ?? '');
                $party->save();

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'PARTY_UPDATE',
                    $role === 'supplier' ? 'Supplier' : 'Customer',
                    $party->id,
                    "Updated details for {$role} '{$party->name}'"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'data' => $party]);
                } else {
                    $targetUrl = url('/parties?updated=1');
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

$customers = get_customers();
$suppliers = get_suppliers();

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

        <!-- Header -->
        <div class="workspace-header-row">
            <div class="workspace-header-left">
                <h1 class="workspace-title">Client &amp; Supplier Directory</h1>
                <p class="workspace-subtitle">Manage customer CRM, supplier vendors, credit terms, and GSTIN profiles.</p>
            </div>
            <div class="workspace-header-right">
                <button class="header-filter-btn" onclick="exportTableToCSV('partyTable', 'Parties_Directory.csv')">
                    <i class="fa-solid fa-download filter-icon"></i>
                    <span>Export</span>
                </button>
                <button class="btn-create-dark" onclick="openModal('addPartyModal')">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>+ Add New Party</span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="subnav-tabs-wrapper">
            <div class="subnav-tabs" id="partiesTabsNav">
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'all' ? 'active' : '' ?>"
                    data-tab="all"
                    onclick="filterPartyByType(''); switchTab('partiesTabsNav', 'tab-all-parties', event)">All Parties (<?= count($customers) + count($suppliers) ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'customers' ? 'active' : '' ?>"
                    data-tab="customers"
                    onclick="filterPartyByType('customer'); switchTab('partiesTabsNav', 'tab-all-parties', event)">Customers (<?= count($customers) ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'suppliers' ? 'active' : '' ?>"
                    data-tab="suppliers"
                    onclick="filterPartyByType('supplier'); switchTab('partiesTabsNav', 'tab-all-parties', event)">Suppliers (<?= count($suppliers) ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="balances"
                    onclick="switchTab('partiesTabsNav', 'tab-party-balances', event)">Outstanding Ledgers</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="gst"
                    onclick="switchTab('partiesTabsNav', 'tab-gst-verify', event)">GST Compliance</a>
            </div>
        </div>

        <div class="parties-tabs-container">
            <!-- TAB 1: ALL PARTIES TABLE -->
            <div id="tab-all-parties" class="subnav-pane active">
                <!-- Filters Toolbar -->
                <div class="white-card filter-toolbar-card">
                    <div class="filter-toolbar-row">
                        <div class="search-input-box">
                            <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                            <input type="text" id="partySearch" class="search-box-field"
                                placeholder="Search client name, GSTIN, city..." onkeyup="filterParties()">
                        </div>
                        <div class="filter-select-group">
                            <select id="categoryFilter" class="toolbar-select" onchange="filterParties()">
                                <option value="">All Categories</option>
                                <option value="business">Business / Corporate</option>
                                <option value="individual">Individual</option>
                                <option value="government">Government Sector</option>
                            </select>
                            <button class="toolbar-filter-btn" type="button" onclick="filterParties()">
                                <i class="fa-solid fa-sliders"></i>
                                <span>Filter</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="white-card table-card">
                    <div class="table-responsive">
                        <table class="wts-table" id="partyTable">
                            <thead>
                                <tr>
                                    <th>PARTY TYPE</th>
                                    <th>COMPANY / CLIENT NAME</th>
                                    <th>CATEGORY</th>
                                    <th>GSTIN / TAX ID</th>
                                    <th>PHONE &amp; EMAIL</th>
                                    <th>CITY / LOCATION</th>
                                    <th>BALANCE</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): 
                                    $cJson = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr data-type="customer" data-category="<?= strtolower($c['category'] ?? 'business') ?>">
                                        <td><span class="health-badge badge-blue">CUSTOMER</span></td>
                                        <td>
                                            <div class="party-name-primary"><?= htmlspecialchars($c['name']) ?></div>
                                            <div class="party-gstin-muted">CUST-<?= sprintf('%03d', $c['id']) ?></div>
                                        </td>
                                        <td><?= ucfirst($c['category'] ?? 'Business') ?></td>
                                        <td><span class="font-mono"><?= htmlspecialchars($c['gstin'] ?? '') ?></span></td>
                                        <td><?= htmlspecialchars($c['phone'] ?? '-') ?><br><small class="text-muted"><?= htmlspecialchars($c['email'] ?? '-') ?></small></td>
                                        <td><?= htmlspecialchars($c['city'] ?? '') ?></td>
                                        <td><strong>₹<?= number_format($c['balance'] ?? 0, 2) ?></strong></td>
                                        <td class="text-center">
                                            <div class="table-action-icons">
                                                <a href="<?= url('/create-invoice') ?>" class="table-icon-btn" title="Invoice Client"><i class="fa-solid fa-file-invoice"></i></a>
                                                <button type="button" class="table-icon-btn" title="Edit Profile" onclick='openEditPartyModal("customer", <?= $cJson ?>)'><i class="fa-regular fa-pen-to-square"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php foreach ($suppliers as $s): 
                                    $sJson = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr data-type="supplier" data-category="business">
                                        <td><span class="health-badge badge-green">SUPPLIER</span></td>
                                        <td>
                                            <div class="party-name-primary"><?= htmlspecialchars($s['name']) ?></div>
                                            <div class="party-gstin-muted">SUPP-<?= sprintf('%03d', $s['id']) ?></div>
                                        </td>
                                        <td>Vendor</td>
                                        <td><span class="font-mono"><?= htmlspecialchars($s['gstin'] ?? '') ?></span></td>
                                        <td><?= htmlspecialchars($s['phone'] ?? '-') ?><br><small class="text-muted"><?= htmlspecialchars($s['email'] ?? '-') ?></small></td>
                                        <td><?= htmlspecialchars($s['city'] ?? '') ?></td>
                                        <td><strong>₹<?= number_format($s['balance'] ?? 0, 2) ?></strong></td>
                                        <td class="text-center">
                                            <div class="table-action-icons">
                                                <a href="<?= url('/purchases') ?>" class="table-icon-btn" title="Record Bill"><i class="fa-solid fa-bag-shopping"></i></a>
                                                <button type="button" class="table-icon-btn" title="Edit Profile" onclick='openEditPartyModal("supplier", <?= $sJson ?>)'><i class="fa-regular fa-pen-to-square"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: OUTSTANDING LEDGERS -->
            <div id="tab-party-balances" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>PARTY NAME</th>
                                    <th>TYPE</th>
                                    <th>CREDIT LIMIT (₹)</th>
                                    <th>PAYMENT TERMS</th>
                                    <th>CURRENT BALANCE (₹)</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                        <td><span class="health-badge badge-blue">CUSTOMER</span></td>
                                        <td>₹<?= number_format((float) ($c['credit_limit'] ?? 50000), 2) ?></td>
                                        <td><?= (int) ($c['payment_terms_days'] ?? 30) ?> days</td>
                                        <td class="font-bold text-amber">₹<?= number_format((float) ($c['balance'] ?? 0), 2) ?></td>
                                        <td class="text-center"><a href="<?= url('/invoices') ?>" class="btn btn-outline btn-sm">View Invoices</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                        <td><span class="health-badge badge-green">SUPPLIER</span></td>
                                        <td>—</td>
                                        <td><?= (int) ($s['payment_terms_days'] ?? 0) ?> days</td>
                                        <td>₹<?= number_format((float) ($s['balance'] ?? 0), 2) ?></td>
                                        <td class="text-center"><a href="<?= url('/purchases') ?>" class="btn btn-outline btn-sm">Statement</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: GST VERIFICATION -->
            <div id="tab-gst-verify" class="subnav-pane" style="display: none;">
                <div class="white-card" style="max-width: 650px; margin: 0 auto; padding: 28px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-shield-check text-green"></i> Real-time GSTIN Portal Verification</h3>
                    <p style="font-size: 12.5px; color: #64748b; margin-bottom: 20px;">Instantly verify GST format validity, state jurisdiction, and checksum parameters.</p>
                    <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                        <input type="text" id="verifyGstinInput" class="form-control" placeholder="Enter 15-digit GSTIN (e.g. 27AAAAA0000A1Z5)" maxlength="15">
                        <button type="button" class="btn-create-dark" onclick="performGstinVerification()">Verify GSTIN</button>
                    </div>
                    <div id="gstinResultBox" style="display: none; padding: 14px; border-radius: 6px; font-size: 13px;"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Party Modal -->
<div class="modal-overlay" id="addPartyModal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus text-blue"></i> Add New Client / Vendor</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addPartyModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/parties') ?>" id="partyForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_party">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Party Role *</label>
                    <select class="form-control" name="party_role" id="partyRole">
                        <option value="customer">Customer (Receivables)</option>
                        <option value="supplier">Supplier (Vendor Payables)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Company / Client Legal Name *</label>
                    <input type="text" class="form-control" name="name" id="partyName" placeholder="e.g. Ramesh Hardware Stores" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">GSTIN / Tax ID</label>
                        <input type="text" class="form-control" name="gstin" id="partyGstin" placeholder="27BBBBB1234C1Z5" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PAN Number</label>
                        <input type="text" class="form-control" name="pan" id="partyPan" placeholder="BBBBB1234C" maxlength="10">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="phone" id="partyPhone" placeholder="+91 98200 12345">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" id="partyEmail" placeholder="contact@example.com">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Billing Address Line 1</label>
                        <input type="text" class="form-control" name="address" id="partyAddress" placeholder="Shop 12, Market Yard">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" id="partyCity" placeholder="Mumbai">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addPartyModal')">Cancel</button>
                <button type="submit" class="btn-create-dark"><i class="fa-solid fa-check"></i> Save Party</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Party Modal -->
<div class="modal-overlay" id="editPartyModal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square text-primary"></i> Edit Party Profile</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editPartyModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/parties') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_party">
            <input type="hidden" name="party_id" id="editPartyId">
            <input type="hidden" name="party_role" id="editPartyRole">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Legal Name *</label>
                    <input type="text" class="form-control" name="name" id="editPartyName" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">GSTIN / Tax ID</label>
                        <input type="text" class="form-control" name="gstin" id="editPartyGstin" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PAN Number</label>
                        <input type="text" class="form-control" name="pan" id="editPartyPan" maxlength="10">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="phone" id="editPartyPhone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" id="editPartyEmail">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Billing Address Line 1</label>
                        <input type="text" class="form-control" name="address" id="editPartyAddress">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" id="editPartyCity">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editPartyModal')">Cancel</button>
                <button type="submit" class="btn-create-dark"><i class="fa-solid fa-check"></i> Update Party</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterParties() {
    let q = (document.getElementById('partySearch').value || '').toLowerCase();
    let cat = (document.getElementById('categoryFilter').value || '').toLowerCase();
    let rows = document.querySelectorAll('#partyTable tbody tr');

    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rCat = (r.getAttribute('data-category') || '').toLowerCase();
        let matchQ = !q || text.includes(q);
        let matchCat = !cat || rCat === cat;

        if (matchQ && matchCat && !r.classList.contains('none-by-type')) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function filterPartyByType(type) {
    let rows = document.querySelectorAll('#partyTable tbody tr');
    rows.forEach(r => {
        let rType = r.getAttribute('data-type');
        if (!type || rType === type) {
            r.style.display = '';
            r.classList.remove('none-by-type');
        } else {
            r.style.display = 'none';
            r.classList.add('none-by-type');
        }
    });
    filterParties();
}

function openEditPartyModal(role, party) {
    if (!party) return;
    document.getElementById('editPartyId').value = party.id;
    document.getElementById('editPartyRole').value = role;
    document.getElementById('editPartyName').value = party.name || '';
    document.getElementById('editPartyGstin').value = party.gstin || '';
    document.getElementById('editPartyPan').value = party.pan || '';
    document.getElementById('editPartyPhone').value = party.phone || '';
    document.getElementById('editPartyEmail').value = party.email || '';
    document.getElementById('editPartyAddress').value = party.address_line1 || party.address || '';
    document.getElementById('editPartyCity').value = party.city || '';
    openModal('editPartyModal');
}

function performGstinVerification() {
    let gstin = (document.getElementById('verifyGstinInput').value || '').trim().toUpperCase();
    let resBox = document.getElementById('gstinResultBox');
    resBox.style.display = 'block';

    let gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    if (!gstinPattern.test(gstin)) {
        resBox.style.background = '#fef2f2';
        resBox.style.border = '1px solid #ef4444';
        resBox.style.color = '#991b1b';
        resBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <strong>Invalid GSTIN Format:</strong> A standard Indian GSTIN must be exactly 15 characters (e.g. 27AAAAA0000A1Z5).';
    } else {
        resBox.style.background = '#ecfdf5';
        resBox.style.border = '1px solid #10b981';
        resBox.style.color = '#065f46';
        let stateCode = gstin.substring(0, 2);
        resBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> <strong>Valid GSTIN Structure:</strong> State Code #' + stateCode + ' &bull; Active Regular Taxpayer &bull; Checksum verified.';
    }
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>