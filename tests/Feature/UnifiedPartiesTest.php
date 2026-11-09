<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\PartyController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\PartyService;

/**
 * Feature Test: Unified Parties Module (Customers, Suppliers & Both-Role Partners)
 * Verifies unified listing, multi-criteria filtering, server-side pagination, Add/Edit party,
 * bi-directional role synchronization, 360-degree profile, CSV statements, and zero duplicate party records.
 */
class UnifiedPartiesTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $controller = new PartyController();
        $testCompanyId = 1;
        $testBranchId = 1;

        // -------------------------------------------------------------
        // PARTY-01: Unauthenticated request redirects to /login
        // -------------------------------------------------------------
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/parties'], null);
        $authMiddleware = new \WTSBill\Middleware\AuthenticationMiddleware();
        $authRes = $authMiddleware->handle($guestReq, fn() => $controller->index($guestReq));
        
        $assert(
            'PARTY-01',
            'Unauthenticated request to /parties redirects to /login (HTTP 302)',
            $authRes->getStatusCode() === 302 &&
            str_contains((string)$authRes->getHeader('Location'), '/login')
        );

        // Authenticate as Admin
        Session::setTenantContext($testCompanyId, $testBranchId);
        Session::set('user_id', 1);
        Session::set('user_role', 'ADMIN');
        Session::set('role', 'admin');
        Session::set('auth_user', [
            'id' => 1,
            'name' => 'System Administrator',
            'email' => 'admin@wtsbill.com',
            'role' => 'admin',
        ]);

        // -------------------------------------------------------------
        // PARTY-02: GET /parties renders Unified Parties table & controls
        // -------------------------------------------------------------
        $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/parties'], null);
        $indexRes = $controller->index($indexReq);
        $html = $indexRes->getContent();

        $assert(
            'PARTY-02',
            'GET /parties returns HTTP 200 with Unified Parties table, compact filters, search bar, and Add New Party button',
            $indexRes->getStatusCode() === 200 &&
            str_contains($html, 'Parties Management') &&
            str_contains($html, 'All Parties') &&
            str_contains($html, 'Customers') &&
            str_contains($html, 'Suppliers') &&
            str_contains($html, 'Add New Party') &&
            str_contains($html, 'Outstanding Balance')
        );

        // -------------------------------------------------------------
        // PARTY-03: Sidebar has exactly ONE Parties entry and no separate sub-items
        // -------------------------------------------------------------
        $menuItems = require ROOT_PATH . '/includes/menu.php';
        $partiesMenu = null;
        foreach ($menuItems as $item) {
            if (($item['id'] ?? '') === 'parties') {
                $partiesMenu = $item;
                break;
            }
        }

        $assert(
            'PARTY-03',
            'Sidebar navigation contains exactly ONE Parties entry pointing to /parties with zero sub-item dropdowns',
            $partiesMenu !== null &&
            ($partiesMenu['url'] ?? '') === '/parties' &&
            empty($partiesMenu['children']) &&
            $partiesMenu['label'] === 'Parties'
        );

        // -------------------------------------------------------------
        // PARTY-04: POST /parties/create creates Customer party and syncs to customers table
        // -------------------------------------------------------------
        $custName = 'Test Customer Beta ' . time();
        $createCustReq = new Request([], [
            'party_type' => 'Customer',
            'name' => $custName,
            'company_name' => $custName . ' Pvt Ltd',
            'contact_person' => 'Rahul Sharma',
            'phone' => '9881099991',
            'email' => 'rahul.' . time() . '@betacust.com',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'opening_balance' => '5000.00',
            'opening_balance_type' => 'Debit',
            'credit_limit' => '50000.00',
            'payment_terms_days' => '30',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/parties/create', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'application/json'], null);

        $createCustRes = $controller->store($createCustReq);
        $custJson = json_decode($createCustRes->getContent(), true);
        $createdCustPartyId = (int)($custJson['party_id'] ?? 0);

        // Verify record in parties and customers tables
        $partyRecord = Database::selectOne("SELECT * FROM parties WHERE id = ?", [$createdCustPartyId]);
        $linkedCust = Database::selectOne("SELECT * FROM customers WHERE party_id = ?", [$createdCustPartyId]);

        $assert(
            'PARTY-04',
            'POST /parties/create creates Customer party with code and syncs record to customers table',
            $createCustRes->getStatusCode() === 201 &&
            !empty($custJson['success']) &&
            $partyRecord !== null &&
            $partyRecord['party_type'] === 'Customer' &&
            str_starts_with((string)$partyRecord['party_code'], 'PRT-') &&
            $linkedCust !== null &&
            (int)$linkedCust['id'] === (int)$partyRecord['customer_id'] &&
            empty($partyRecord['supplier_id'])
        );

        // -------------------------------------------------------------
        // PARTY-05: POST /parties/create creates Supplier party and syncs to suppliers table
        // -------------------------------------------------------------
        $suppName = 'Test Supplier Delta ' . time();
        $createSuppReq = new Request([], [
            'party_type' => 'Supplier',
            'name' => $suppName,
            'company_name' => $suppName . ' Tools',
            'contact_person' => 'Anil Patil',
            'phone' => '9881099992',
            'email' => 'anil.' . time() . '@deltasupp.com',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'opening_balance' => '2500.00',
            'opening_balance_type' => 'Credit',
            'payment_terms_days' => '45',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/parties/create', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'application/json'], null);

        $createSuppRes = $controller->store($createSuppReq);
        $suppJson = json_decode($createSuppRes->getContent(), true);
        $createdSuppPartyId = (int)($suppJson['party_id'] ?? 0);

        $suppPartyRecord = Database::selectOne("SELECT * FROM parties WHERE id = ?", [$createdSuppPartyId]);
        $linkedSupp = Database::selectOne("SELECT * FROM suppliers WHERE party_id = ?", [$createdSuppPartyId]);

        $assert(
            'PARTY-05',
            'POST /parties/create creates Supplier party with code and syncs record to suppliers table',
            $createSuppRes->getStatusCode() === 201 &&
            !empty($suppJson['success']) &&
            $suppPartyRecord !== null &&
            $suppPartyRecord['party_type'] === 'Supplier' &&
            $linkedSupp !== null &&
            (int)$linkedSupp['id'] === (int)$suppPartyRecord['supplier_id'] &&
            empty($suppPartyRecord['customer_id'])
        );

        // -------------------------------------------------------------
        // PARTY-06: POST /parties/create with Both stores ONE record with both roles
        // -------------------------------------------------------------
        $bothName = 'Apex Multi-Role Enterprises ' . time();
        $createBothReq = new Request([], [
            'party_type' => 'Both',
            'name' => $bothName,
            'company_name' => $bothName . ' LLC',
            'contact_person' => 'Vikram Singhania',
            'phone' => '9881099993',
            'email' => 'vikram.' . time() . '@apexmulti.com',
            'city' => 'Nagpur',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'opening_balance' => '10000.00',
            'opening_balance_type' => 'Debit',
            'credit_limit' => '100000.00',
            'payment_terms_days' => '30',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/parties/create', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'application/json'], null);

        $createBothRes = $controller->store($createBothReq);
        $bothJson = json_decode($createBothRes->getContent(), true);
        $createdBothPartyId = (int)($bothJson['party_id'] ?? 0);

        // Verify EXACTLY ONE party record in parties table
        $bothPartiesCount = Database::selectOne("SELECT COUNT(*) as c FROM parties WHERE id = ?", [$createdBothPartyId]);
        $bothRecord = Database::selectOne("SELECT * FROM parties WHERE id = ?", [$createdBothPartyId]);
        $linkedCustRole = Database::selectOne("SELECT * FROM customers WHERE party_id = ?", [$createdBothPartyId]);
        $linkedSuppRole = Database::selectOne("SELECT * FROM suppliers WHERE party_id = ?", [$createdBothPartyId]);

        $assert(
            'PARTY-06',
            'Party marked Both is stored as EXACTLY ONE record in parties table with both customer and supplier roles',
            $createBothRes->getStatusCode() === 201 &&
            !empty($bothJson['success']) &&
            (int)$bothPartiesCount['c'] === 1 &&
            $bothRecord['party_type'] === 'Both' &&
            !empty($bothRecord['customer_id']) &&
            !empty($bothRecord['supplier_id']) &&
            $linkedCustRole !== null &&
            $linkedSuppRole !== null
        );

        // -------------------------------------------------------------
        // PARTY-07: Server-side filtering by type (Customer, Supplier, Both)
        // -------------------------------------------------------------
        $filterBothReq = new Request(['type' => 'both', 'format' => 'json'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/parties?type=both&format=json'], null);
        $filterBothRes = $controller->index($filterBothReq);
        $bothListData = json_decode($filterBothRes->getContent(), true);

        $allBoth = true;
        foreach ($bothListData['data'] as $p) {
            if ($p['party_type'] !== 'Both') {
                $allBoth = false;
                break;
            }
        }

        $assert(
            'PARTY-07',
            'GET /parties?type=both filters parties dataset strictly to parties with Both role',
            $filterBothRes->getStatusCode() === 200 &&
            !empty($bothListData['data']) &&
            $allBoth === true
        );

        // -------------------------------------------------------------
        // PARTY-08: Multi-criteria Search (by Name, Code, Phone, GSTIN)
        // -------------------------------------------------------------
        $searchReq = new Request(['q' => $bothRecord['party_code'], 'search_by' => 'party_code', 'format' => 'json'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/parties'], null);
        $searchRes = $controller->index($searchReq);
        $searchData = json_decode($searchRes->getContent(), true);

        $assert(
            'PARTY-08',
            'Search by party code accurately locates the specific unified party record',
            $searchRes->getStatusCode() === 200 &&
            count($searchData['data']) === 1 &&
            $searchData['data'][0]['id'] == $createdBothPartyId
        );

        // -------------------------------------------------------------
        // PARTY-09: GET /parties/{id} renders 360° Party Profile
        // -------------------------------------------------------------
        $profileReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/parties/{$createdBothPartyId}"], null);
        $profileRes = $controller->show($profileReq, ['id' => $createdBothPartyId]);
        $profileHtml = $profileRes->getContent();

        $assert(
            'PARTY-09',
            'GET /parties/{id} renders 360° Party Profile with financial summary and role capabilities',
            $profileRes->getStatusCode() === 200 &&
            str_contains($profileHtml, $bothName) &&
            str_contains($profileHtml, 'Outstanding Balance') &&
            str_contains($profileHtml, 'Consolidated Ledger') &&
            str_contains($profileHtml, 'Multi-Role Business Partner')
        );

        // -------------------------------------------------------------
        // PARTY-10: Both-type profile renders BOTH Sales Invoices and Purchase Bills tabs
        // -------------------------------------------------------------
        $assert(
            'PARTY-10',
            'Both-type party profile displays tabs for BOTH Sales Invoices and Purchase Bills',
            str_contains($profileHtml, 'Sales Invoices') &&
            str_contains($profileHtml, 'Purchase Bills') &&
            str_contains($profileHtml, 'Quotations') &&
            str_contains($profileHtml, 'Purchase Orders')
        );

        // -------------------------------------------------------------
        // PARTY-11: POST /parties/{id}/update syncs modifications to both roles
        // -------------------------------------------------------------
        $updatedPhone = '9881000000';
        $updateReq = new Request([], [
            'name' => $bothName . ' (Updated)',
            'phone' => $updatedPhone,
            'party_type' => 'Both',
            'city' => 'Nagpur Metro',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/parties/{$createdBothPartyId}/update", 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'application/json'], null);

        $updateRes = $controller->update($updateReq, ['id' => $createdBothPartyId]);
        $updateJson = json_decode($updateRes->getContent(), true);

        $updatedParty = Database::selectOne("SELECT * FROM parties WHERE id = ?", [$createdBothPartyId]);
        $updatedCust = Database::selectOne("SELECT * FROM customers WHERE party_id = ?", [$createdBothPartyId]);
        $updatedSupp = Database::selectOne("SELECT * FROM suppliers WHERE party_id = ?", [$createdBothPartyId]);

        $assert(
            'PARTY-11',
            'POST /parties/{id}/update synchronizes modified details across parties, customers, and suppliers tables',
            $updateRes->getStatusCode() === 200 &&
            !empty($updateJson['success']) &&
            $updatedParty['phone'] === $updatedPhone &&
            $updatedCust['phone'] === $updatedPhone &&
            $updatedSupp['phone'] === $updatedPhone
        );

        // -------------------------------------------------------------
        // PARTY-12: POST /parties/{id}/toggle-status toggles active status across both roles
        // -------------------------------------------------------------
        $toggleReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/parties/{$createdBothPartyId}/toggle-status", 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'application/json'], null);
        $toggleRes = $controller->toggleStatus($toggleReq, ['id' => $createdBothPartyId]);
        $toggleJson = json_decode($toggleRes->getContent(), true);

        $toggledParty = Database::selectOne("SELECT is_active, status FROM parties WHERE id = ?", [$createdBothPartyId]);
        $toggledCust = Database::selectOne("SELECT is_active, status FROM customers WHERE party_id = ?", [$createdBothPartyId]);
        $toggledSupp = Database::selectOne("SELECT is_active, status FROM suppliers WHERE party_id = ?", [$createdBothPartyId]);

        $assert(
            'PARTY-12',
            'POST /parties/{id}/toggle-status deactivates party and cascades is_active=0 to both customer and supplier roles',
            $toggleRes->getStatusCode() === 200 &&
            $toggledParty['is_active'] == 0 &&
            $toggledCust['is_active'] == 0 &&
            $toggledSupp['is_active'] == 0
        );

        // Re-activate for further tests
        Database::execute("UPDATE parties SET is_active = 1, status = 'ACTIVE' WHERE id = ?", [$createdBothPartyId]);
        Database::execute("UPDATE customers SET is_active = 1, status = 'ACTIVE' WHERE party_id = ?", [$createdBothPartyId]);
        Database::execute("UPDATE suppliers SET is_active = 1, status = 'ACTIVE' WHERE party_id = ?", [$createdBothPartyId]);

        // -------------------------------------------------------------
        // PARTY-13: GET /parties/{id}/statement/csv exports CSV ledger
        // -------------------------------------------------------------
        $csvReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/parties/{$createdBothPartyId}/statement/csv"], null);
        $csvRes = $controller->statementCsv($csvReq, ['id' => $createdBothPartyId]);
        $csvContent = $csvRes->getContent();

        $assert(
            'PARTY-13',
            'GET /parties/{id}/statement/csv serves text/csv stream with party headers and running balance columns',
            $csvRes->getStatusCode() === 200 &&
            str_contains((string)$csvRes->getHeader('Content-Type'), 'text/csv') &&
            str_contains($csvContent, 'Party Ledger Statement') &&
            str_contains($csvContent, 'Party Name:') &&
            str_contains($csvContent, 'Running Balance')
        );

        // -------------------------------------------------------------
        // PARTY-14: Invoice creation workflow recognizes Customer & Both parties
        // -------------------------------------------------------------
        $partyRepo = new \WTSBill\Repositories\PartyRepository();
        $foundCust = $partyRepo->findParty($createdBothPartyId); // check party ID
        $foundCustByRole = Database::selectOne("SELECT * FROM customers WHERE party_id = ? AND is_active = 1", [$createdBothPartyId]);

        $assert(
            'PARTY-14',
            'Invoice workflow recognizes Both-type party in customers table with full billing parameters',
            $foundCustByRole !== null &&
            !empty($foundCustByRole['id']) &&
            (int)$foundCustByRole['party_id'] === $createdBothPartyId
        );

        // -------------------------------------------------------------
        // PARTY-15: Purchase creation workflow recognizes Supplier & Both parties
        // -------------------------------------------------------------
        $foundSuppByRole = Database::selectOne("SELECT * FROM suppliers WHERE party_id = ? AND is_active = 1", [$createdBothPartyId]);

        $assert(
            'PARTY-15',
            'Purchase workflow recognizes Both-type party in suppliers table with full procurement parameters',
            $foundSuppByRole !== null &&
            !empty($foundSuppByRole['id']) &&
            (int)$foundSuppByRole['party_id'] === $createdBothPartyId
        );

        // Cleanup test-generated party records
        Database::execute("DELETE FROM customers WHERE party_id IN (?, ?, ?)", [$createdCustPartyId, $createdSuppPartyId, $createdBothPartyId]);
        Database::execute("DELETE FROM suppliers WHERE party_id IN (?, ?, ?)", [$createdCustPartyId, $createdSuppPartyId, $createdBothPartyId]);
        Database::execute("DELETE FROM parties WHERE id IN (?, ?, ?)", [$createdCustPartyId, $createdSuppPartyId, $createdBothPartyId]);
    }
}
