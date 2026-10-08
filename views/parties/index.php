<?php
$pageTitle = 'Parties & Directory - WTS LEDGER PRO';
$currentRoute = 'parties';

require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../../app/Helpers/helpers.php';

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\PartyService;
use App\Repositories\PartyRepository;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\CsrfMiddleware;

// 1. Authenticate & Resolve Tenant
$user = AuthMiddleware::handle();
$tenant = TenantMiddleware::handle();
$companyId = $tenant['company_id'];
$branchId = $tenant['branch_id'];
$userName = $user->name ?? 'Admin';

$submitError = $_SESSION['flash_error'] ?? null;
$submitSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// 2. Handle POST Actions (Create, Update, Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? 'save_party';

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid or expired security token. Please try again.';
    } else {
        try {
            $role = strtolower(trim($input['party_role'] ?? ($input['role'] ?? ($input['type'] ?? 'customer'))));

            if ($action === 'save_party' || $action === 'create') {
                if ($role === 'supplier') {
                    $res = PartyService::createSupplier($input, $companyId, $branchId, $userName);
                } else {
                    $res = PartyService::createCustomer($input, $companyId, $branchId, $userName);
                }

                if ($res['success']) {
                    $submitSuccess = $res['message'];
                } else {
                    $submitError = $res['message'];
                }
            } elseif ($action === 'update_party' || $action === 'update') {
                $partyId = intval($input['party_id'] ?? ($input['id'] ?? 0));
                if ($role === 'supplier') {
                    $res = PartyService::updateSupplier($partyId, $input, $companyId, $userName);
                } else {
                    $res = PartyService::updateCustomer($partyId, $input, $companyId, $userName);
                }

                if ($res['success']) {
                    $submitSuccess = $res['message'];
                } else {
                    $submitError = $res['message'];
                }
            } elseif ($action === 'delete_party' || $action === 'delete') {
                $partyId = intval($input['party_id'] ?? ($input['id'] ?? 0));
                if ($role === 'supplier') {
                    $res = PartyService::deleteSupplier($partyId, $companyId, $userName);
                } else {
                    $res = PartyService::deleteCustomer($partyId, $companyId, $userName);
                }

                if ($res['success']) {
                    $submitSuccess = $res['message'];
                } else {
                    $submitError = $res['message'];
                }
            }

            if ($isJson) {
                if (!empty($res['success'])) {
                    response_json(['status' => 'success', 'data' => $res], 200);
                } else {
                    response_json(['status' => 'error', 'message' => $submitError ?? 'Validation failed'], 422);
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

// 3. Query Customers & Suppliers
$activeTab = $_GET['tab'] ?? ($_GET['type'] === 'supplier' ? 'suppliers' : 'all');
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$taxTypeFilter = $_GET['tax_type'] ?? 'all';

$filters = [
    'search' => $search,
    'status' => $statusFilter,
    'tax_type' => $taxTypeFilter,
];

$customerData = PartyRepository::getCustomers($companyId, $filters, 1, 500);
$supplierData = PartyRepository::getSuppliers($companyId, $filters, 1, 500);

$customers = $customerData['data'];
$suppliers = $supplierData['data'];

// Compute Summary Metrics
$totalReceivables = 0.0;
foreach ($customers as $c) {
    $totalReceivables += (float)($c->current_balance ?? $c->opening_balance ?? 0);
}
$totalPayables = 0.0;
foreach ($suppliers as $s) {
    $totalPayables += (float)($s->current_balance ?? $s->opening_balance ?? 0);
}

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <?php if ($submitSuccess): ?>
            <div class="alert alert-success" style="padding: 14px 18px; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; color: #065f46; display: flex; align-items: center; justify-content: space-between; font-weight: 600;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check text-success" style="font-size: 18px;"></i>
                    <span><?= htmlspecialchars($submitSuccess) ?></span>
                </div>
                <button type="button" class="alert-close-btn" onclick="this.closest('.alert').remove()" style="background:none;border:none;cursor:pointer;color:#065f46;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($submitError): ?>
            <div class="alert alert-danger" style="padding: 14px 18px; margin-bottom: 20px; background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; display: flex; align-items: center; justify-content: space-between; font-weight: 600;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size: 18px;"></i>
                    <span><?= htmlspecialchars($submitError) ?></span>
                </div>
                <button type="button" class="alert-close-btn" onclick="this.closest('.alert').remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <!-- Page Header Component -->
        <?= component('page_header', [
            'title' => 'Client & Supplier Directory',
            'subtitle' => 'Manage customer CRM, vendor procurement profiles, credit terms, and GSTIN compliance.',
            'icon' => 'fa-solid fa-address-book',
            'badge' => (count($customers) + count($suppliers)) . ' Total Parties',
            'badgeVariant' => 'primary',
            'breadcrumbs' => [
                ['label' => 'Directory', 'url' => '/parties'],
                ['label' => 'Parties List']
            ],
            'actions' => '
                <button class="btn btn-outline btn-sm" onclick="exportTableToCSV(\'partyTable\', \'Parties_Directory.csv\')">
                    <i class="fa-solid fa-download"></i>
                    <span>Export CSV</span>
                </button>
                <button class="btn btn-primary btn-sm" onclick="openModal(\'addPartyModal\')">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>+ Add New Party</span>
                </button>
            '
        ]) ?>

        <!-- Top Summary Metric Cards -->
        <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="card p-4">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Customers</div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= count($customers) ?></div>
                <div style="font-size: 11px; color: #10b981; margin-top: 2px;"><i class="fa-solid fa-arrow-up"></i> Active Buyers</div>
            </div>
            <div class="card p-4">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Suppliers</div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= count($suppliers) ?></div>
                <div style="font-size: 11px; color: #0284c7; margin-top: 2px;"><i class="fa-solid fa-truck"></i> Verified Vendors</div>
            </div>
            <div class="card p-4">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Receivables</div>
                <div style="font-size: 24px; font-weight: 800; color: #10b981; margin-top: 4px;">₹ <?= number_format($totalReceivables, 2) ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Customer balance due</div>
            </div>
            <div class="card p-4">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Payables</div>
                <div style="font-size: 24px; font-weight: 800; color: #ef4444; margin-top: 4px;">₹ <?= number_format($totalPayables, 2) ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Vendor invoices payable</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="subnav-tabs-wrapper" style="margin-bottom: 20px;">
            <div class="subnav-tabs" id="partiesTabsNav">
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'all' ? 'active' : '' ?>"
                    data-tab="all"
                    onclick="filterPartyByType(''); switchTab('partiesTabsNav', 'tab-all-parties', event)">
                    All Parties (<?= count($customers) + count($suppliers) ?>)
                </a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'customers' ? 'active' : '' ?>"
                    data-tab="customers"
                    onclick="filterPartyByType('customer'); switchTab('partiesTabsNav', 'tab-all-parties', event)">
                    Customers (<?= count($customers) ?>)
                </a>
                <a href="javascript:void(0)" class="subnav-tab <?= $activeTab === 'suppliers' ? 'active' : '' ?>"
                    data-tab="suppliers"
                    onclick="filterPartyByType('supplier'); switchTab('partiesTabsNav', 'tab-all-parties', event)">
                    Suppliers (<?= count($suppliers) ?>)
                </a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="balances"
                    onclick="switchTab('partiesTabsNav', 'tab-party-balances', event)">
                    Outstanding Ledgers
                </a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="gst"
                    onclick="switchTab('partiesTabsNav', 'tab-gst-verify', event)">
                    GST Compliance
                </a>
            </div>
        </div>

        <div class="parties-tabs-container">
            <!-- TAB 1: ALL PARTIES TABLE -->
            <div id="tab-all-parties" class="subnav-pane active">
                <!-- Search & Filters Toolbar -->
                <div class="card mb-4 p-3">
                    <form method="GET" action="<?= url('/parties') ?>" id="partyFilterForm" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                        <div style="position: relative; flex: 1; min-width: 260px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 12px; color: #94a3b8;"></i>
                            <input type="text" name="search" id="partySearch" class="form-input" style="padding-left: 36px;"
                                placeholder="Search client name, GSTIN, phone, city..." value="<?= htmlspecialchars($search) ?>" onkeyup="filterPartiesTable()">
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="status" class="form-select" style="min-width: 130px;" onchange="this.form.submit()">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                                <option value="INACTIVE" <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <select name="tax_type" class="form-select" style="min-width: 140px;" onchange="this.form.submit()">
                                <option value="all" <?= $taxTypeFilter === 'all' ? 'selected' : '' ?>>All Tax Types</option>
                                <option value="Registered" <?= $taxTypeFilter === 'Registered' ? 'selected' : '' ?>>GST Registered</option>
                                <option value="Unregistered" <?= $taxTypeFilter === 'Unregistered' ? 'selected' : '' ?>>Unregistered</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-filter"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Parties Table Component -->
                <div class="card p-0">
                    <div class="table-responsive">
                        <table class="table table-hover" id="partyTable">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">PARTY TYPE</th>
                                    <th style="width: 22%;">COMPANY / CLIENT NAME</th>
                                    <th style="width: 14%;">GSTIN / PAN</th>
                                    <th style="width: 18%;">PHONE &amp; EMAIL</th>
                                    <th style="width: 12%;">LOCATION</th>
                                    <th style="width: 12%; text-align: right;">BALANCE</th>
                                    <th style="width: 12%; text-align: center;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $allParties = [];
                                foreach ($customers as $c) {
                                    $allParties[] = ['type' => 'customer', 'data' => $c];
                                }
                                foreach ($suppliers as $s) {
                                    $allParties[] = ['type' => 'supplier', 'data' => $s];
                                }

                                if (empty($allParties)):
                                ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-6 text-muted">
                                            <?= component('empty_state', [
                                                'title' => 'No Parties Found',
                                                'message' => 'No customers or suppliers match your search filter criteria.',
                                                'actionText' => 'Create First Party',
                                                'actionOnclick' => 'openModal(\'addPartyModal\')',
                                                'icon' => 'fa-solid fa-address-book'
                                            ]) ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allParties as $pItem): 
                                        $type = $pItem['type'];
                                        $party = $pItem['data'];
                                        $pId = (int)$party->id;
                                        $pName = $party->name ?? 'Unnamed';
                                        $pGstin = $party->gstin ?? '';
                                        $pPan = $party->pan ?? '';
                                        $pPhone = $party->phone ?? '-';
                                        $pEmail = $party->email ?? '-';
                                        $pCity = $party->city ?? ($party->state ?? 'Mumbai');
                                        $pBalance = (float)($party->current_balance ?? $party->opening_balance ?? 0.0);
                                        $pAddress = $party->address_line1 ?? '';
                                    ?>
                                        <tr class="party-row" data-type="<?= $type ?>" data-name="<?= htmlspecialchars(strtolower($pName)) ?>" data-gstin="<?= htmlspecialchars(strtolower($pGstin)) ?>" data-city="<?= htmlspecialchars(strtolower($pCity)) ?>">
                                            <td>
                                                <?php if ($type === 'customer'): ?>
                                                    <span class="badge badge-primary"><i class="fa-solid fa-user"></i> Customer</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info"><i class="fa-solid fa-truck"></i> Supplier</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($pName) ?></div>
                                                <div style="font-size: 11px; color: #64748b;">ID #<?= $pId ?> &bull; <?= htmlspecialchars($party->customer_type ?? 'Business') ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($pGstin)): ?>
                                                    <div style="font-family: monospace; font-weight: 600; color: #0f172a; font-size: 12px;"><?= htmlspecialchars($pGstin) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($pPan)): ?>
                                                    <div style="font-size: 11px; color: #64748b;">PAN: <?= htmlspecialchars($pPan) ?></div>
                                                <?php elseif (empty($pGstin)): ?>
                                                    <span class="text-muted" style="font-size: 12px;">Unregistered</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><i class="fa-solid fa-phone text-muted" style="font-size: 10px; width: 14px;"></i> <?= htmlspecialchars($pPhone) ?></div>
                                                <?php if (!empty($party->email)): ?>
                                                    <div style="font-size: 11px; color: #64748b;"><i class="fa-regular fa-envelope text-muted" style="font-size: 10px; width: 14px;"></i> <?= htmlspecialchars($pEmail) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($pCity) ?></div>
                                                <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($party->state ?? 'Maharashtra') ?></div>
                                            </td>
                                            <td style="text-align: right;">
                                                <div style="font-weight: 700; color: <?= $pBalance > 0 ? ($type === 'customer' ? '#10b981' : '#ef4444') : '#64748b' ?>;">
                                                    ₹ <?= number_format($pBalance, 2) ?>
                                                </div>
                                                <div style="font-size: 10px; color: #94a3b8;"><?= $type === 'customer' ? 'Receivable' : 'Payable' ?></div>
                                            </td>
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 6px;">
                                                    <button type="button" class="btn btn-outline btn-xs" title="View Ledger & Transactions" onclick="viewPartyDetails(<?= $pId ?>, '<?= $type ?>', <?= htmlspecialchars(json_encode($pName)) ?>)">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline btn-xs" title="Edit Party" onclick="openEditPartyModal(<?= $pId ?>, '<?= $type ?>', <?= htmlspecialchars(json_encode($pName)) ?>, <?= htmlspecialchars(json_encode($pGstin)) ?>, <?= htmlspecialchars(json_encode($pPan)) ?>, <?= htmlspecialchars(json_encode($pPhone)) ?>, <?= htmlspecialchars(json_encode($pEmail)) ?>, <?= htmlspecialchars(json_encode($pAddress)) ?>, <?= htmlspecialchars(json_encode($pCity)) ?>)">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline btn-xs text-danger" title="Delete Party" onclick="confirmDeleteParty(<?= $pId ?>, '<?= $type ?>', <?= htmlspecialchars(json_encode($pName)) ?>)">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: OUTSTANDING LEDGERS -->
            <div id="tab-party-balances" class="subnav-pane">
                <div class="card p-4">
                    <h3 class="card-title mb-2">Outstanding Customer & Supplier Balances</h3>
                    <p class="text-muted mb-4" style="font-size: 13px;">Real-time aged receivable ledger statement summary grouped by party.</p>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PARTY NAME</th>
                                    <th>TYPE</th>
                                    <th>PHONE</th>
                                    <th>OPENING BALANCE</th>
                                    <th style="text-align: right;">CURRENT BALANCE</th>
                                    <th style="text-align: center;">LEDGER ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allParties as $pItem): 
                                    $type = $pItem['type'];
                                    $party = $pItem['data'];
                                    $bal = (float)($party->current_balance ?? $party->opening_balance ?? 0.0);
                                    if ($bal <= 0) continue;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($party->name) ?></strong></td>
                                        <td><span class="badge badge-<?= $type === 'customer' ? 'primary' : 'info' ?>"><?= ucfirst($type) ?></span></td>
                                        <td><?= htmlspecialchars($party->phone ?? '-') ?></td>
                                        <td>₹ <?= number_format((float)($party->opening_balance ?? 0), 2) ?></td>
                                        <td style="text-align: right; font-weight: 700; color: <?= $type === 'customer' ? '#10b981' : '#ef4444' ?>;">
                                            ₹ <?= number_format($bal, 2) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="<?= url('/payments?party_id=' . $party->id) ?>" class="btn btn-outline btn-xs">
                                                <i class="fa-solid fa-receipt"></i> Record Payment
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: GST COMPLIANCE -->
            <div id="tab-gst-verify" class="subnav-pane">
                <div class="card p-4">
                    <h3 class="card-title mb-2">GST Compliance & Master Registry</h3>
                    <p class="text-muted mb-4" style="font-size: 13px;">Overview of 15-character GSTIN registrations for GSTR-1 and GSTR-3B filings.</p>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PARTY</th>
                                    <th>GSTIN</th>
                                    <th>STATE CODE</th>
                                    <th>PAN</th>
                                    <th>TAX FILING STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allParties as $pItem): 
                                    $party = $pItem['data'];
                                    if (empty($party->gstin)) continue;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($party->name) ?></strong></td>
                                        <td><code style="font-weight: 700; color: #2563eb;"><?= htmlspecialchars($party->gstin) ?></code></td>
                                        <td><?= htmlspecialchars(substr($party->gstin, 0, 2)) ?> (<?= htmlspecialchars($party->state ?? 'Maharashtra') ?>)</td>
                                        <td><?= htmlspecialchars($party->pan ?? substr($party->gstin, 2, 10)) ?></td>
                                        <td><span class="badge badge-success"><i class="fa-solid fa-check"></i> Validated Format</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- 1. CREATE PARTY MODAL COMPONENT -->
<div class="modal-backdrop" id="addPartyModal" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= url('/parties') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_party">
            
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <div class="modal-icon-box"><i class="fa-solid fa-user-plus"></i></div>
                        <h3 class="modal-title">Create New Client or Supplier</h3>
                    </div>
                    <button type="button" class="modal-close-btn" onclick="closeModal('addPartyModal')"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="modal-body">
                    <!-- Party Role Tabs -->
                    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                        <label class="btn btn-outline btn-sm" style="flex: 1; cursor: pointer; text-align: center;">
                            <input type="radio" name="party_role" value="customer" checked onchange="togglePartyRole(this.value)"> Customer (Buyer)
                        </label>
                        <label class="btn btn-outline btn-sm" style="flex: 1; cursor: pointer; text-align: center;">
                            <input type="radio" name="party_role" value="supplier" onchange="togglePartyRole(this.value)"> Supplier (Vendor)
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_name">Legal / Trade Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="add_name" class="form-input" placeholder="e.g. Ramesh Hardware Stores" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_gstin">GSTIN (15 Digits)</label>
                            <input type="text" name="gstin" id="add_gstin" class="form-input" placeholder="27AAAAA0000A1Z5" maxlength="15" onchange="autoFillPan(this.value, 'add_pan')">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_phone">Phone / Mobile</label>
                            <input type="text" name="phone" id="add_phone" class="form-input" placeholder="+91 9876543210">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_email">Email Address</label>
                            <input type="email" name="email" id="add_email" class="form-input" placeholder="accounts@client.com">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_pan">PAN</label>
                            <input type="text" name="pan" id="add_pan" class="form-input" placeholder="AAAAA0000A" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_opening_balance">Opening Balance (₹)</label>
                            <input type="number" step="0.01" name="opening_balance" id="add_opening_balance" class="form-input" placeholder="0.00" value="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_credit_limit">Credit Limit (₹)</label>
                            <input type="number" step="0.01" name="credit_limit" id="add_credit_limit" class="form-input" placeholder="50000.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_address">Street Address</label>
                        <input type="text" name="address" id="add_address" class="form-input" placeholder="Industrial Estate Road">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_city">City</label>
                            <input type="text" name="city" id="add_city" class="form-input" placeholder="Mumbai" value="Mumbai">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_state">State</label>
                            <input type="text" name="state" id="add_state" class="form-input" placeholder="Maharashtra" value="Maharashtra">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_pincode">Pincode</label>
                            <input type="text" name="pincode" id="add_pincode" class="form-input" placeholder="400001">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addPartyModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Save Party</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. EDIT PARTY MODAL -->
<div class="modal-backdrop" id="editPartyModal" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= url('/parties') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_party">
            <input type="hidden" name="party_id" id="edit_party_id">
            <input type="hidden" name="party_role" id="edit_party_role">
            
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <div class="modal-icon-box"><i class="fa-solid fa-pen-to-square"></i></div>
                        <h3 class="modal-title" id="editPartyModalTitle">Edit Party Details</h3>
                    </div>
                    <button type="button" class="modal-close-btn" onclick="closeModal('editPartyModal')"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="edit_name">Legal / Trade Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_gstin">GSTIN</label>
                            <input type="text" name="gstin" id="edit_gstin" class="form-input" maxlength="15" onchange="autoFillPan(this.value, 'edit_pan')">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="edit_phone">Phone / Mobile</label>
                            <input type="text" name="phone" id="edit_phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_email">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-input">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="edit_pan">PAN</label>
                            <input type="text" name="pan" id="edit_pan" class="form-input" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_city">City</label>
                            <input type="text" name="city" id="edit_city" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_address">Street Address</label>
                        <input type="text" name="address" id="edit_address" class="form-input">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editPartyModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Update Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 3. VIEW PARTY DETAILS & TRANSACTION HISTORY MODAL -->
<div class="modal-backdrop" id="viewPartyModal" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title-group">
                    <div class="modal-icon-box"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <h3 class="modal-title" id="viewPartyTitle">Party Statement &amp; History</h3>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('viewPartyModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body" id="viewPartyBody">
                <div class="loading-state-wrapper"><div class="loading-spinner-ring"></div><p>Loading transactions...</p></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('viewPartyModal')">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Statement</button>
            </div>
        </div>
    </div>
</div>

<!-- 4. DELETE CONFIRMATION DIALOG -->
<?= component('confirm_dialog', [
    'id' => 'deletePartyConfirm',
    'title' => 'Delete Party Profile',
    'message' => 'Are you sure you want to delete this party? This action can be reversed via audit records.',
    'confirmText' => 'Delete Party',
    'confirmAction' => '/parties',
    'variant' => 'danger'
]) ?>

<script>
function autoFillPan(gstin, targetId) {
    if (gstin && gstin.length >= 12) {
        const pan = gstin.substring(2, 12).toUpperCase();
        const el = document.getElementById(targetId);
        if (el) el.value = pan;
    }
}

function filterPartyByType(type) {
    const rows = document.querySelectorAll('.party-row');
    rows.forEach(r => {
        if (!type || r.getAttribute('data-type') === type) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function filterPartiesTable() {
    const query = (document.getElementById('partySearch')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.party-row');
    rows.forEach(r => {
        const name = r.getAttribute('data-name') || '';
        const gstin = r.getAttribute('data-gstin') || '';
        const city = r.getAttribute('data-city') || '';
        if (!query || name.includes(query) || gstin.includes(query) || city.includes(query)) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function openEditPartyModal(id, role, name, gstin, pan, phone, email, address, city) {
    document.getElementById('edit_party_id').value = id;
    document.getElementById('edit_party_role').value = role;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_gstin').value = gstin || '';
    document.getElementById('edit_pan').value = pan || '';
    document.getElementById('edit_phone').value = phone || '';
    document.getElementById('edit_email').value = email || '';
    document.getElementById('edit_address').value = address || '';
    document.getElementById('edit_city').value = city || '';
    document.getElementById('editPartyModalTitle').innerText = 'Edit ' + (role === 'supplier' ? 'Supplier' : 'Customer') + ' - ' + name;
    openModal('editPartyModal');
}

function confirmDeleteParty(id, role, name) {
    const form = document.getElementById('deletePartyConfirm_form');
    if (form) {
        form.innerHTML = `
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_party">
            <input type="hidden" name="party_id" value="${id}">
            <input type="hidden" name="party_role" value="${role}">
        `;
    }
    document.getElementById('deletePartyConfirm_title').innerText = 'Delete ' + name;
    openModal('deletePartyConfirm');
}

function viewPartyDetails(id, type, name) {
    document.getElementById('viewPartyTitle').innerText = name + ' - Statement & History';
    document.getElementById('viewPartyBody').innerHTML = '<div class="loading-state-wrapper"><div class="loading-spinner-ring"></div><p>Fetching ledger transactions...</p></div>';
    openModal('viewPartyModal');

    fetch((window.baseUrl || '') + '/api/v1/' + (type === 'supplier' ? 'suppliers/' : 'customers/') + id, {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success' && data.data) {
            const p = data.data;
            const history = data.summary || {};
            let html = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-size: 16px; font-weight: 800; color: #0f172a;">${escapeHtml(p.name || '')}</div>
                        <div style="font-size: 13px; color: #64748b;">GSTIN: <strong>${escapeHtml(p.gstin || 'Unregistered')}</strong> &bull; PAN: ${escapeHtml(p.pan || '-')}</div>
                        <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><i class="fa-solid fa-phone"></i> ${escapeHtml(p.phone || '-')} &bull; <i class="fa-regular fa-envelope"></i> ${escapeHtml(p.email || '-')}</div>
                        <div style="font-size: 13px; color: #64748b; margin-top: 2px;"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(p.address_line1 || '')}, ${escapeHtml(p.city || '')}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600;">Current Balance Due</div>
                        <div style="font-size: 24px; font-weight: 800; color: ${p.current_balance > 0 ? '#10b981' : '#64748b'};">₹ ${(p.current_balance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Credit Limit: ₹ ${(p.credit_limit || 0).toLocaleString('en-IN')}</div>
                    </div>
                </div>

                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px;">Recent Transaction History</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>DOC TYPE</th>
                                <th>DOC / VOUCHER #</th>
                                <th style="text-align: right;">DEBIT (₹)</th>
                                <th style="text-align: right;">CREDIT (₹)</th>
                                <th style="text-align: center;">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            if (p.invoices && p.invoices.length > 0) {
                p.invoices.forEach(inv => {
                    html += `
                        <tr>
                            <td>${escapeHtml(inv.invoice_date || '-')}</td>
                            <td><span class="badge badge-primary">INVOICE</span></td>
                            <td><strong>${escapeHtml(inv.invoice_number || '-')}</strong></td>
                            <td style="text-align: right; font-weight: 600;">₹ ${(inv.total_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                            <td style="text-align: right;">-</td>
                            <td style="text-align: center;"><span class="badge badge-${inv.status === 'PAID' ? 'success' : 'warning'}">${escapeHtml(inv.status)}</span></td>
                        </tr>
                    `;
                });
            } else {
                html += `<tr><td colspan="6" class="text-center py-4 text-muted">No transaction records found for this party.</td></tr>`;
            }

            html += `</tbody></table></div>`;
            document.getElementById('viewPartyBody').innerHTML = html;
        } else {
            document.getElementById('viewPartyBody').innerHTML = '<div class="alert alert-danger">Failed to load party details.</div>';
        }
    })
    .catch(err => {
        document.getElementById('viewPartyBody').innerHTML = '<div class="alert alert-danger">Error: ' + escapeHtml(err.message) + '</div>';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>