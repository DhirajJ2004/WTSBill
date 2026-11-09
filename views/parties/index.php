<?php
/**
 * WTSBill ERP — Unified Parties Management View
 * Displays all Customers, Suppliers, and Both-role parties in a single combined table
 * with real-time multi-criteria filtering, server-side pagination, and Add/Edit party modal.
 */

use WTSBill\Core\Session;

$appName = \WTSBill\Core\Config::get('app.name', 'WTSBill ERP');
$csrfToken = Session::csrfToken();
$filters = $filters ?? [];
$currentType = $filters['type'] ?? 'all';
$searchQuery = $filters['q'] ?? '';
$searchBy = $filters['search_by'] ?? 'all';
$selectedCity = $filters['city'] ?? '';
$selectedStatus = $filters['status'] ?? 'all';
$selectedBalance = $filters['balance'] ?? 'all';

$counts = $counts ?? ['all' => 0, 'customers' => 0, 'suppliers' => 0, 'both' => 0];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1];
$cities = $cities ?? [];
?>

<div class="parties-container" style="padding: 1.5rem; max-width: 1440px; margin: 0 auto;">
    <!-- HEADER BAR -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 700; color: #0F172A; margin: 0 0 0.25rem 0; letter-spacing: -0.02em;">
                Parties Management
            </h1>
            <p style="font-size: 0.88rem; color: #64748B; margin: 0;">
                Unified management for Customers, Suppliers, and Multi-Role accounts.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <button type="button" class="btn btn-primary" onclick="window.openPartyModal()" style="display: inline-flex; align-items: center; gap: 0.5rem; background: #235347; border: none; padding: 0.6rem 1.15rem; font-size: 0.88rem; font-weight: 600; border-radius: 8px; color: #FFFFFF; cursor: pointer; box-shadow: 0 2px 4px rgba(35,83,71,0.25);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Add New Party</span>
            </button>
        </div>
    </div>

    <!-- TYPE PILL TABS -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; border-bottom: 2px solid #E2E8F0; padding-bottom: 0.75rem;">
        <a href="<?= e(buildPartyFilterUrl(['type' => 'all', 'page' => 1])) ?>" class="party-tab-pill <?= $currentType === 'all' ? 'active' : '' ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.86rem; font-weight: 600; text-decoration: none; <?= $currentType === 'all' ? 'background: #235347; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569;' ?>">
            <span>All Parties</span>
            <span style="font-size: 0.75rem; padding: 2px 7px; border-radius: 10px; <?= $currentType === 'all' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #E2E8F0; color: #64748B;' ?>"><?= (int)$counts['all'] ?></span>
        </a>
        <a href="<?= e(buildPartyFilterUrl(['type' => 'customer', 'page' => 1])) ?>" class="party-tab-pill <?= $currentType === 'customer' ? 'active' : '' ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.86rem; font-weight: 600; text-decoration: none; <?= $currentType === 'customer' ? 'background: #235347; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569;' ?>">
            <span>Customers</span>
            <span style="font-size: 0.75rem; padding: 2px 7px; border-radius: 10px; <?= $currentType === 'customer' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #E2E8F0; color: #64748B;' ?>"><?= (int)$counts['customers'] ?></span>
        </a>
        <a href="<?= e(buildPartyFilterUrl(['type' => 'supplier', 'page' => 1])) ?>" class="party-tab-pill <?= $currentType === 'supplier' ? 'active' : '' ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.86rem; font-weight: 600; text-decoration: none; <?= $currentType === 'supplier' ? 'background: #235347; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569;' ?>">
            <span>Suppliers</span>
            <span style="font-size: 0.75rem; padding: 2px 7px; border-radius: 10px; <?= $currentType === 'supplier' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #E2E8F0; color: #64748B;' ?>"><?= (int)$counts['suppliers'] ?></span>
        </a>
        <a href="<?= e(buildPartyFilterUrl(['type' => 'both', 'page' => 1])) ?>" class="party-tab-pill <?= $currentType === 'both' ? 'active' : '' ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.86rem; font-weight: 600; text-decoration: none; <?= $currentType === 'both' ? 'background: #235347; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569;' ?>">
            <span>Both (Customer &amp; Supplier)</span>
            <span style="font-size: 0.75rem; padding: 2px 7px; border-radius: 10px; <?= $currentType === 'both' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #E2E8F0; color: #64748B;' ?>"><?= (int)$counts['both'] ?></span>
        </a>
    </div>

    <!-- FILTER & SEARCH CONTROL BAR -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="GET" action="/parties" id="partyFilterForm" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="type" value="<?= e($currentType) ?>">

            <!-- Search Field Selector -->
            <div style="min-width: 140px;">
                <select name="search_by" id="filterSearchBy" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #F8FAFC; color: #1E293B;">
                    <option value="all" <?= $searchBy === 'all' ? 'selected' : '' ?>>All Fields</option>
                    <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>>Party Name</option>
                    <option value="party_code" <?= $searchBy === 'party_code' ? 'selected' : '' ?>>Party Code</option>
                    <option value="phone" <?= $searchBy === 'phone' ? 'selected' : '' ?>>Phone Number</option>
                    <option value="email" <?= $searchBy === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="gstin" <?= $searchBy === 'gstin' ? 'selected' : '' ?>>GSTIN / PAN</option>
                </select>
            </div>

            <!-- Search Input -->
            <div style="flex: 1; min-width: 220px; position: relative;">
                <input type="text" name="q" id="filterSearchQuery" value="<?= e($searchQuery) ?>" placeholder="Search by name, code, phone, email, GSTIN..." class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.85rem 0.5rem 2.2rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #FFFFFF; color: #1E293B;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2.5" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>

            <!-- City Filter -->
            <div style="min-width: 130px;">
                <select name="city" id="filterCity" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #F8FAFC; color: #1E293B;">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= e($c) ?>" <?= $selectedCity === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div style="min-width: 120px;">
                <select name="status" id="filterStatus" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #F8FAFC; color: #1E293B;">
                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <!-- Balance Filter -->
            <div style="min-width: 140px;">
                <select name="balance" id="filterBalance" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #F8FAFC; color: #1E293B;">
                    <option value="all" <?= $selectedBalance === 'all' ? 'selected' : '' ?>>All Balances</option>
                    <option value="has_outstanding" <?= $selectedBalance === 'has_outstanding' ? 'selected' : '' ?>>With Outstanding</option>
                    <option value="zero_balance" <?= $selectedBalance === 'zero_balance' ? 'selected' : '' ?>>Zero Balance (₹0)</option>
                    <option value="debit" <?= $selectedBalance === 'debit' ? 'selected' : '' ?>>Debit (Receivable)</option>
                    <option value="credit" <?= $selectedBalance === 'credit' ? 'selected' : '' ?>>Credit (Payable)</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn" style="background: #235347; color: #FFFFFF; font-size: 0.85rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; border: none; cursor: pointer;">
                    Filter
                </button>
                <a href="/parties" class="btn btn-secondary" style="background: #E2E8F0; color: #475569; font-size: 0.85rem; font-weight: 600; padding: 0.5rem 0.85rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;" title="Clear all filters">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    <span>Clear</span>
                </a>
            </div>
        </form>
    </div>

    <!-- COMBINED PARTY TABLE CARD -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.86rem;">
                <thead>
                    <tr style="background: #0B2B26; color: #DAF1DE; border-bottom: 2px solid #235347;">
                        <th style="padding: 0.75rem 1rem; font-weight: 600;">Party Name</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">Party Code</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">Party Type</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">Contact Person</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">Phone Number</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">Email</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">GSTIN</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600;">City / State</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: right;">Outstanding Balance</th>
                        <th style="padding: 0.75rem 0.85rem; font-weight: 600; text-align: center;">Status</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parties)): ?>
                        <tr>
                            <td colspan="11" style="padding: 3rem 1rem; text-align: center; color: #64748B;">
                                <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.35rem;">No parties found</div>
                                <div style="font-size: 0.82rem;">Try adjusting your search criteria or clear the active filters.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parties as $idx => $p): ?>
                            <?php
                                $rowBg = ($idx % 2 === 0) ? '#FFFFFF' : '#F8FAFC';
                                $pType = $p['party_type'];
                                $badgeStyle = match ($pType) {
                                    'Both' => 'background: #E0E7FF; color: #4338CA; border: 1px solid #C7D2FE;',
                                    'Supplier' => 'background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;',
                                    default => 'background: #DAF1DE; color: #163832; border: 1px solid #8EB69B;',
                                };

                                $balance = (float)($p['current_balance'] ?? 0.0);
                                $balanceClass = 'color: #334155;';
                                $balanceLabel = '₹ 0.00';
                                if ($balance > 0.01) {
                                    $balanceClass = 'color: #047857; font-weight: 600;';
                                    $balanceLabel = '₹ ' . number_format($balance, 2) . ' Dr';
                                } elseif ($balance < -0.01) {
                                    $balanceClass = 'color: #B91C1C; font-weight: 600;';
                                    $balanceLabel = '₹ ' . number_format(abs($balance), 2) . ' Cr';
                                }
                            ?>
                            <tr style="background: <?= $rowBg ?>; border-bottom: 1px solid #E2E8F0; transition: background 0.15s ease;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='<?= $rowBg ?>'">
                                <!-- Name -->
                                <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                    <a href="/parties/<?= (int)$p['id'] ?>" style="color: #0F172A; text-decoration: none;" onmouseover="this.style.color='#235347'; this.style.textDecoration='underline';" onmouseout="this.style.color='#0F172A'; this.style.textDecoration='none';">
                                        <?= e($p['name']) ?>
                                    </a>
                                    <?php if (!empty($p['company_name']) && $p['company_name'] !== $p['name']): ?>
                                        <div style="font-size: 0.75rem; color: #64748B; font-weight: 400;"><?= e($p['company_name']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Code -->
                                <td style="padding: 0.75rem 0.85rem; color: #475569; font-family: monospace; font-size: 0.82rem;">
                                    <?= e($p['party_code']) ?>
                                </td>

                                <!-- Type -->
                                <td style="padding: 0.75rem 0.85rem;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.74rem; font-weight: 600; <?= $badgeStyle ?>">
                                        <?= e($pType) ?>
                                    </span>
                                </td>

                                <!-- Contact Person -->
                                <td style="padding: 0.75rem 0.85rem; color: #334155;">
                                    <?= e($p['contact_person'] ?: '—') ?>
                                </td>

                                <!-- Phone -->
                                <td style="padding: 0.75rem 0.85rem; color: #334155; white-space: nowrap;">
                                    <?= e($p['phone'] ?: '—') ?>
                                </td>

                                <!-- Email -->
                                <td style="padding: 0.75rem 0.85rem; color: #334155;">
                                    <?= e($p['email'] ?: '—') ?>
                                </td>

                                <!-- GSTIN -->
                                <td style="padding: 0.75rem 0.85rem; color: #475569; font-family: monospace; font-size: 0.82rem;">
                                    <?= e($p['gstin'] ?: '—') ?>
                                </td>

                                <!-- City / State -->
                                <td style="padding: 0.75rem 0.85rem; color: #334155;">
                                    <?= e(implode(', ', array_filter([$p['city'], $p['state']]))) ?: '—' ?>
                                </td>

                                <!-- Outstanding Balance -->
                                <td style="padding: 0.75rem 1rem; text-align: right; <?= $balanceClass ?>">
                                    <?= $balanceLabel ?>
                                </td>

                                <!-- Status -->
                                <td style="padding: 0.75rem 0.85rem; text-align: center;">
                                    <?php if ($p['is_active']): ?>
                                        <span style="display: inline-block; padding: 2px 7px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; background: #DEF7EC; color: #03543F;">Active</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 7px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; background: #FDE8E8; color: #9B1C1C;">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="padding: 0.75rem 1rem; text-align: center; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                        <!-- View Profile -->
                                        <a href="/parties/<?= (int)$p['id'] ?>" class="btn-action" title="View 360° Profile" style="color: #235347; padding: 4px; display: inline-flex; border-radius: 4px;" onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='transparent'">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>

                                        <!-- Edit Party -->
                                        <button type="button" class="btn-action" onclick='window.editParty(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Party" style="background: none; border: none; cursor: pointer; color: #2563EB; padding: 4px; display: inline-flex; border-radius: 4px;" onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='transparent'">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>

                                        <!-- Toggle Status -->
                                        <button type="button" class="btn-action" onclick="window.togglePartyStatus(<?= (int)$p['id'] ?>)" title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>" style="background: none; border: none; cursor: pointer; color: <?= $p['is_active'] ? '#DC2626' : '#16A34A' ?>; padding: 4px; display: inline-flex; border-radius: 4px;" onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='transparent'">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION BAR -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid #E2E8F0; background: #F8FAFC; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.84rem; color: #64748B;">
                Showing <span style="font-weight: 600; color: #1E293B;"><?= empty($parties) ? 0 : (($pagination['page'] - 1) * $pagination['per_page'] + 1) ?></span> to <span style="font-weight: 600; color: #1E293B;"><?= min($pagination['total'], $pagination['page'] * $pagination['per_page']) ?></span> of <span style="font-weight: 600; color: #1E293B;"><?= (int)$pagination['total'] ?></span> parties
            </div>

            <?php if ($pagination['total_pages'] > 1): ?>
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="<?= e(buildPartyFilterUrl(['page' => $pagination['page'] - 1])) ?>" class="btn-page" style="padding: 0.35rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF; color: #334155; font-size: 0.82rem; text-decoration: none;">&larr; Prev</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['total_pages'], $pagination['page'] + 2); $i++): ?>
                        <a href="<?= e(buildPartyFilterUrl(['page' => $i])) ?>" class="btn-page <?= $i === $pagination['page'] ? 'active' : '' ?>" style="padding: 0.35rem 0.65rem; border: 1px solid <?= $i === $pagination['page'] ? '#235347' : '#CBD5E1' ?>; border-radius: 4px; background: <?= $i === $pagination['page'] ? '#235347' : '#FFFFFF' ?>; color: <?= $i === $pagination['page'] ? '#FFFFFF' : '#334155' ?>; font-size: 0.82rem; text-decoration: none; font-weight: <?= $i === $pagination['page'] ? '600' : '400' ?>;"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="<?= e(buildPartyFilterUrl(['page' => $pagination['page'] + 1])) ?>" class="btn-page" style="padding: 0.35rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF; color: #334155; font-size: 0.82rem; text-decoration: none;">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- UNIFIED ADD / EDIT PARTY MODAL -->
<!-- ============================================================ -->
<div id="partyModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(5, 31, 32, 0.7); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div style="background: #FFFFFF; border-radius: 12px; width: 100%; max-width: 780px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.4); border: 1px solid #E2E8F0; margin: auto;">
        <!-- Modal Header -->
        <div style="padding: 1.15rem 1.5rem; background: #0B2B26; color: #DAF1DE; border-top-left-radius: 12px; border-top-right-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8EB69B" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <h3 id="partyModalTitle" style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #FFFFFF;">Add New Party</h3>
            </div>
            <button type="button" onclick="window.closePartyModal()" style="background: none; border: none; color: #DAF1DE; cursor: pointer; font-size: 1.3rem; line-height: 1;" title="Close">&times;</button>
        </div>

        <!-- Notification Banner -->
        <div id="partyModalNotice" style="display: none; padding: 0.75rem 1.5rem; font-size: 0.85rem; border-bottom: 1px solid transparent;"></div>

        <!-- Modal Form Body -->
        <form id="partyForm" onsubmit="window.saveParty(event)" style="display: flex; flex-direction: column; overflow-y: auto; flex: 1;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" id="partyFormId" value="">

            <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1.15rem;">
                <!-- 1. Party Type Selector (Customer, Supplier, Both) -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.5rem;">
                        Party Role / Type <span style="color: #DC2626;">*</span>
                    </label>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.86rem; color: #1E293B;">
                            <input type="radio" name="party_type" value="Customer" checked id="ptypeCustomer" style="accent-color: #235347;">
                            <span><strong>Customer</strong> (Buyer)</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.86rem; color: #1E293B;">
                            <input type="radio" name="party_type" value="Supplier" id="ptypeSupplier" style="accent-color: #235347;">
                            <span><strong>Supplier</strong> (Vendor)</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.86rem; color: #1E293B;">
                            <input type="radio" name="party_type" value="Both" id="ptypeBoth" style="accent-color: #235347;">
                            <span><strong>Both</strong> (Multi-Role Partner)</span>
                        </label>
                    </div>
                    <div style="font-size: 0.74rem; color: #64748B; margin-top: 0.35rem;">
                        * A party marked <strong>Both</strong> is stored as a single unified record authorized for both Sales and Purchases.
                    </div>
                </div>

                <!-- 2. Primary Identifiers Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Party Name <span style="color: #DC2626;">*</span>
                        </label>
                        <input type="text" name="name" id="pName" required placeholder="e.g. Acme Corporation" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Trade / Company Name
                        </label>
                        <input type="text" name="company_name" id="pCompanyName" placeholder="Leave blank if same as party name" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>

                <!-- 3. Contact Details Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Contact Person
                        </label>
                        <input type="text" name="contact_person" id="pContactPerson" placeholder="e.g. Ramesh Patel" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Mobile / Phone Number
                        </label>
                        <input type="text" name="phone" id="pPhone" placeholder="e.g. 9881032297" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Email Address
                        </label>
                        <input type="email" name="email" id="pEmail" placeholder="billing@partner.com" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>

                <!-- 4. Tax & Statutory Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            GSTIN (15 Digits)
                        </label>
                        <input type="text" name="gstin" id="pGstin" maxlength="15" placeholder="e.g. 27AADCW7577N1ZE" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; text-transform: uppercase;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            PAN Number (10 Digits)
                        </label>
                        <input type="text" name="pan" id="pPan" maxlength="10" placeholder="e.g. AADCW7577N" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; text-transform: uppercase;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Party Code (Optional)
                        </label>
                        <input type="text" name="party_code" id="pPartyCode" placeholder="Auto-generated if empty" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>

                <!-- 5. Address Details -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Billing Address
                        </label>
                        <textarea name="billing_address" id="pBillingAddress" rows="2" placeholder="Street, Building, Area" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;"></textarea>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Shipping Address
                        </label>
                        <textarea name="shipping_address" id="pShippingAddress" rows="2" placeholder="Leave blank to copy billing address" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;"></textarea>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            City
                        </label>
                        <input type="text" name="city" id="pCity" placeholder="e.g. Pune" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            State
                        </label>
                        <input type="text" name="state" id="pState" value="Maharashtra" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Pincode
                        </label>
                        <input type="text" name="pincode" id="pPincode" placeholder="e.g. 411041" class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                </div>

                <!-- 6. Financial & Credit Terms -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.75rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem;">
                    <div>
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                            Opening Balance (₹)
                        </label>
                        <input type="number" step="0.01" name="opening_balance" id="pOpeningBalance" value="0.00" class="form-control" style="width: 100%; font-size: 0.82rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                            Balance Type
                        </label>
                        <select name="opening_balance_type" id="pOpeningBalanceType" class="form-control" style="width: 100%; font-size: 0.82rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #fff;">
                            <option value="Debit">Debit (Receivable)</option>
                            <option value="Credit">Credit (Payable)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                            Credit Limit (₹)
                        </label>
                        <input type="number" step="0.01" name="credit_limit" id="pCreditLimit" value="0.00" class="form-control" style="width: 100%; font-size: 0.82rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                            Payment Terms
                        </label>
                        <select name="payment_terms_days" id="pPaymentTerms" class="form-control" style="width: 100%; font-size: 0.82rem; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; background: #fff;">
                            <option value="0">Immediate / Due on Receipt</option>
                            <option value="15">Net 15 Days</option>
                            <option value="30" selected>Net 30 Days</option>
                            <option value="45">Net 45 Days</option>
                            <option value="60">Net 60 Days</option>
                        </select>
                    </div>
                </div>

                <!-- 7. Notes & Active Status -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: center;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                            Remarks / Notes
                        </label>
                        <input type="text" name="notes" id="pNotes" placeholder="Special pricing terms, bank instructions, etc." class="form-control" style="width: 100%; font-size: 0.85rem; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                    </div>
                    <div style="padding-top: 1.25rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.86rem; color: #1E293B; font-weight: 600;">
                            <input type="checkbox" name="is_active" id="pIsActive" value="1" checked style="width: 16px; height: 16px; accent-color: #235347;">
                            <span>Active Account</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 1rem 1.5rem; background: #F8FAFC; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 0.75rem; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <button type="button" onclick="window.closePartyModal()" class="btn btn-secondary" style="padding: 0.55rem 1.15rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; background: #E2E8F0; color: #475569; border: none; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="btnSaveParty" class="btn btn-primary" style="padding: 0.55rem 1.35rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; background: #235347; color: #FFFFFF; border: none; cursor: pointer;">
                    Save Party
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Helper to build URL with query params
function buildPartyFilterUrl(overrides) {
    var params = new URLSearchParams(window.location.search);
    for (var k in overrides) {
        if (overrides[k] === null || overrides[k] === '' || overrides[k] === 'all') {
            params.delete(k);
        } else {
            params.set(k, overrides[k]);
        }
    }
    return '/parties?' + params.toString();
}

// Open Add Party Modal
window.openPartyModal = function() {
    document.getElementById('partyFormId').value = '';
    document.getElementById('partyForm').reset();
    document.getElementById('ptypeCustomer').checked = true;
    document.getElementById('pIsActive').checked = true;
    document.getElementById('pState').value = 'Maharashtra';
    document.getElementById('partyModalTitle').innerText = 'Add New Party';
    document.getElementById('partyModalNotice').style.display = 'none';
    document.getElementById('partyModal').style.display = 'flex';
};

// Edit Existing Party
window.editParty = function(party) {
    if (!party) return;
    document.getElementById('partyFormId').value = party.id || '';
    document.getElementById('partyModalTitle').innerText = 'Edit Party: ' + (party.name || '');
    document.getElementById('partyModalNotice').style.display = 'none';

    var pType = party.party_type || 'Customer';
    if (pType === 'Both') {
        document.getElementById('ptypeBoth').checked = true;
    } else if (pType === 'Supplier') {
        document.getElementById('ptypeSupplier').checked = true;
    } else {
        document.getElementById('ptypeCustomer').checked = true;
    }

    document.getElementById('pName').value = party.name || '';
    document.getElementById('pCompanyName').value = party.company_name || '';
    document.getElementById('pContactPerson').value = party.contact_person || '';
    document.getElementById('pPhone').value = party.phone || '';
    document.getElementById('pEmail').value = party.email || '';
    document.getElementById('pGstin').value = party.gstin || '';
    document.getElementById('pPan').value = party.pan || '';
    document.getElementById('pPartyCode').value = party.party_code || '';
    document.getElementById('pBillingAddress').value = party.billing_address || '';
    document.getElementById('pShippingAddress').value = party.shipping_address || '';
    document.getElementById('pCity').value = party.city || '';
    document.getElementById('pState').value = party.state || 'Maharashtra';
    document.getElementById('pPincode').value = party.pincode || '';
    document.getElementById('pOpeningBalance').value = party.opening_balance || '0.00';
    document.getElementById('pOpeningBalanceType').value = party.opening_balance_type || 'Debit';
    document.getElementById('pCreditLimit').value = party.credit_limit || '0.00';
    document.getElementById('pPaymentTerms').value = party.payment_terms_days || '30';
    document.getElementById('pNotes').value = party.notes || '';
    document.getElementById('pIsActive').checked = !!parseInt(party.is_active);

    document.getElementById('partyModal').style.display = 'flex';
};

// Close Modal
window.closePartyModal = function() {
    document.getElementById('partyModal').style.display = 'none';
};

// Save Party (Create or Update via AJAX)
window.saveParty = async function(e) {
    e.preventDefault();
    var form = document.getElementById('partyForm');
    var id = document.getElementById('partyFormId').value;
    var btn = document.getElementById('btnSaveParty');
    var notice = document.getElementById('partyModalNotice');

    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function(val, key) {
        payload[key] = val;
    });

    var url = id ? ('/parties/' + id + '/update') : '/parties/create';

    btn.disabled = true;
    btn.innerText = 'Saving...';
    notice.style.display = 'none';

    try {
        var resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        var res = await resp.json();

        if (res.success) {
            notice.style.background = '#DEF7EC';
            notice.style.color = '#03543F';
            notice.style.borderColor = '#84E1BC';
            notice.innerText = res.message || 'Party saved successfully!';
            notice.style.display = 'block';

            setTimeout(function() {
                window.location.reload();
            }, 600);
        } else {
            btn.disabled = false;
            btn.innerText = 'Save Party';
            notice.style.background = '#FDE8E8';
            notice.style.color = '#9B1C1C';
            notice.style.borderColor = '#F8B4B4';
            notice.innerText = res.message || 'An error occurred while saving the party.';
            notice.style.display = 'block';
        }
    } catch(err) {
        btn.disabled = false;
        btn.innerText = 'Save Party';
        notice.style.background = '#FDE8E8';
        notice.style.color = '#9B1C1C';
        notice.style.borderColor = '#F8B4B4';
        notice.innerText = 'Network error: ' + err.message;
        notice.style.display = 'block';
    }
};

// Toggle Active / Inactive Status
window.togglePartyStatus = async function(id) {
    if (!id) return;
    try {
        var resp = await fetch('/parties/' + id + '/toggle-status', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        var res = await resp.json();
        if (res.success) {
            window.location.reload();
        }
    } catch(e) {}
};

// Auto open modal if ?action=create
document.addEventListener('DOMContentLoaded', function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'create') {
        window.openPartyModal();
    }
});
</script>

<?php
/**
 * PHP Helper function to preserve filters when navigating tabs or pages.
 */
function buildPartyFilterUrl(array $overrides): string {
    $current = $_GET ?? [];
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '' || $v === 'all') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return '/parties' . (!empty($current) ? ('?' . http_build_query($current)) : '');
}
?>
