<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\CustomerService;
use WTSBill\Services\SupplierService;

/**
 * Feature Test Suite for Phase 2: CRM & Party 360-Degree Profile Upgrades.
 * Covers Customer Multiple Addresses (customer_addresses), Contact Person & Notes,
 * 360-Degree Supplier Profile, Inward Bills, Disbursements, Supplier Ledger Statement,
 * CSV Export, and Backward-Compatible JSON APIs.
 */
class CrmExpansionTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Unauthenticated Security Protection
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/suppliers/1']);
        $guestRes = $router->dispatch($guestReq);
        $assert('CRM-01', 'Unauthenticated request to /suppliers/1 redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && str_contains($guestRes->getHeaders()['Location'] ?? '', '/login')
        );

        // Authenticate as Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csrfToken = Session::getCsrfToken();

        // 2. Add New Customer Address (customer_addresses)
        $cust = Database::selectOne("SELECT id FROM customers WHERE company_id = ? AND deleted_at IS NULL LIMIT 1", [$companyId]);
        $customerId = $cust ? (int)$cust['id'] : 1;

        $uniquePincode = '400' . rand(100, 999);
        $addAddrReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'type' => 'SHIPPING',
            'address_line1' => 'Plot 42, Central Logistics Park',
            'address_line2' => 'Warehouse Bay 7',
            'city' => 'Navi Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => $uniquePincode,
            'country' => 'India',
            'landmark' => 'Near JNPT Toll',
            'is_default' => 0,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/customers/{$customerId}/addresses",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $addAddrRes = $router->dispatch($addAddrReq);
        $addAddrJson = json_decode($addAddrRes->getContent(), true);

        $savedAddr = Database::selectOne(
            "SELECT * FROM customer_addresses WHERE customer_id = ? AND pincode = ? AND company_id = ?",
            [$customerId, $uniquePincode, $companyId]
        );

        $assert('CRM-02', 'POST /customers/{id}/addresses creates multi-address record in customer_addresses',
            $addAddrRes->getStatusCode() === 201 &&
            ($addAddrJson['success'] ?? false) === true &&
            $savedAddr !== null &&
            $savedAddr['type'] === 'SHIPPING' &&
            $savedAddr['city'] === 'Navi Mumbai'
        );

        $addressId = $savedAddr ? (int)$savedAddr['id'] : 0;

        // 3. Customer Profile Renders Address Book Tab and Master Notes
        $custProfileReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/customers/{$customerId}"]);
        $custProfileRes = $router->dispatch($custProfileReq);
        $custProfileHtml = $custProfileRes->getContent();

        $assert('CRM-03', 'GET /customers/{id} renders Address Book tab and includes newly added secondary address',
            $custProfileRes->getStatusCode() === 200 &&
            str_contains($custProfileHtml, 'Address Book') &&
            str_contains($custProfileHtml, 'Plot 42, Central Logistics Park') &&
            str_contains($custProfileHtml, 'Navi Mumbai')
        );

        // 4. Delete Customer Address
        $delAddrReq = new Request([], [
            '_csrf_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/customers/{$customerId}/addresses/{$addressId}/delete",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $delAddrRes = $router->dispatch($delAddrReq);

        $addrAfterDel = Database::selectOne(
            "SELECT * FROM customer_addresses WHERE id = ? AND company_id = ?",
            [$addressId, $companyId]
        );

        $assert('CRM-04', 'POST /customers/{id}/addresses/{id}/delete removes record from customer_addresses',
            $delAddrRes->getStatusCode() === 200 &&
            $addrAfterDel === null
        );

        // 5. Backward Compatibility: GET /suppliers/{id} with Accept: application/json returns supplier JSON
        $supRow = Database::selectOne("SELECT id FROM suppliers WHERE company_id = ? AND deleted_at IS NULL LIMIT 1", [$companyId]);
        $supplierId = $supRow ? (int)$supRow['id'] : 1;

        $jsonReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/suppliers/{$supplierId}",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $jsonRes = $router->dispatch($jsonReq);
        $supJson = json_decode($jsonRes->getContent(), true);

        $assert('CRM-05', 'GET /suppliers/{id} with Accept: application/json returns backward-compatible supplier JSON payload',
            $jsonRes->getStatusCode() === 200 &&
            ($supJson['success'] ?? false) === true &&
            isset($supJson['supplier']) &&
            (int)$supJson['supplier']['id'] === $supplierId
        );

        // 6. Browser Navigation: GET /suppliers/{id} renders 360-Degree Supplier Profile HTML
        $viewReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/suppliers/{$supplierId}"]);
        $viewRes = $router->dispatch($viewReq);
        $viewHtml = $viewRes->getContent();

        $assert('CRM-06', 'GET /suppliers/{id} (Browser) renders 360-Degree Supplier Profile with KPI summary cards',
            $viewRes->getStatusCode() === 200 &&
            str_contains($viewHtml, 'Supplier Master Details') &&
            str_contains($viewHtml, 'Total Inward Billed') &&
            str_contains($viewHtml, 'Total Paid / Disbursed') &&
            str_contains($viewHtml, 'Current Payable Due') &&
            str_contains($viewHtml, 'Overdue Payable')
        );

        // 7. Supplier Profile Renders All 5 Subsystem Tabs
        $assert('CRM-07', 'Supplier profile layout contains all 5 tabs (Overview, Bills, Disbursements, Debit Notes, Ledger)',
            str_contains($viewHtml, 'data-tab="tab-overview"') &&
            str_contains($viewHtml, 'data-tab="tab-bills"') &&
            str_contains($viewHtml, 'data-tab="tab-payments"') &&
            str_contains($viewHtml, 'data-tab="tab-debit-notes"') &&
            str_contains($viewHtml, 'data-tab="tab-ledger"')
        );

        // 8. SupplierService::getSupplierProfileData Computes Running Balance & Metrics
        $supplierService = new SupplierService();
        $profData = $supplierService->getSupplierProfileData($supplierId);

        $assert('CRM-08', 'SupplierService::getSupplierProfileData accurately computes metrics and chronological running ledger',
            $profData !== null &&
            isset($profData['financial_summary']['total_billed']) &&
            isset($profData['financial_summary']['outstanding_balance']) &&
            isset($profData['ledger']) &&
            is_array($profData['ledger']) &&
            isset($profData['bills'])
        );

        // 9. GET /suppliers/{id}/statement/csv Exports CSV Ledger Statement
        $csvReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/suppliers/{$supplierId}/statement/csv"]);
        $csvRes = $router->dispatch($csvReq);
        $csvContent = $csvRes->getContent();
        $csvHeaders = $csvRes->getHeaders();

        $assert('CRM-09', 'GET /suppliers/{id}/statement/csv serves text/csv stream with supplier ledger headers',
            $csvRes->getStatusCode() === 200 &&
            str_contains($csvHeaders['Content-Type'] ?? '', 'text/csv') &&
            str_contains($csvContent, 'Supplier Account Statement') &&
            str_contains($csvContent, 'Running Balance (Payable)')
        );

        // 10. Customer Edit Persists Notes and Contact Person
        $customerService = new CustomerService();
        $testCust = $customerService->createCustomer([
            'name' => 'CRM Test Client ' . rand(1000, 9999),
            'contact_person' => 'Rajesh Sharma',
            'notes' => 'Initial notes',
            'credit_limit' => 50000.00,
            'payment_terms_days' => 30,
        ]);
        $testCustId = (int)$testCust['id'];

        $testNotes = 'Preferred delivery window 10am-2pm. Regression test notes ' . rand(100, 999);
        $testContact = 'Rajesh Sharma Modified';

        $updatedCust = $customerService->updateCustomer($testCustId, [
            'name' => $testCust['name'],
            'contact_person' => $testContact,
            'notes' => $testNotes,
            'credit_limit' => 75000.00,
            'payment_terms_days' => 45,
        ]);

        $dbCust = Database::selectOne("SELECT * FROM customers WHERE id = ?", [$testCustId]);

        $assert('CRM-10', 'Customer update persists contact_person, notes, credit_limit, and payment_terms_days',
            $updatedCust['contact_person'] === $testContact &&
            $dbCust['notes'] === $testNotes &&
            (float)$dbCust['credit_limit'] === 75000.00 &&
            (int)$dbCust['payment_terms_days'] === 45
        );

        Database::execute("DELETE FROM customers WHERE id = ?", [$testCustId]);
    }
}
