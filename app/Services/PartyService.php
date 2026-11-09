<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;
use WTSBill\Repositories\PartyRepository;

/**
 * Unified Party Service for WTSBill ERP.
 * Manages unified business logic for Customers, Suppliers, and Both-role parties,
 * ensuring complete transaction integrity, bi-directional role synchronization, and 360-degree profiles.
 */
class PartyService
{
    protected PartyRepository $partyRepo;
    protected CustomerService $customerService;
    protected SupplierService $supplierService;

    public function __construct(
        ?PartyRepository $partyRepo = null,
        ?CustomerService $customerService = null,
        ?SupplierService $supplierService = null
    ) {
        $this->partyRepo = $partyRepo ?? new PartyRepository();
        $this->customerService = $customerService ?? new CustomerService();
        $this->supplierService = $supplierService ?? new SupplierService();
    }

    /**
     * Get paginated parties with filters and balance calculations.
     */
    public function getPartiesList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $result = $this->partyRepo->searchParties($filters, $companyId, $page, $perPage);
        $result['cities'] = $this->partyRepo->getDistinctCities($companyId);
        return $result;
    }

    /**
     * Retrieve single party record.
     */
    public function getParty(int $id): ?array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        return $this->partyRepo->findParty($id, $companyId);
    }

    /**
     * Retrieve complete 360° Profile data for a party across all active roles.
     */
    public function getPartyProfileData(int $partyId, int $branchId = 0): ?array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $party = $this->partyRepo->findParty($partyId, $companyId);

        if (!$party) {
            return null;
        }

        $partyType = $party['party_type'];
        $hasCustomerRole = ($partyType === 'Customer' || $partyType === 'Both') && !empty($party['customer_id']);
        $hasSupplierRole = ($partyType === 'Supplier' || $partyType === 'Both') && !empty($party['supplier_id']);

        $customerData = null;
        $supplierData = null;

        if ($hasCustomerRole) {
            $customerData = $this->customerService->getCustomerProfileData((int)$party['customer_id'], $branchId);
        }

        if ($hasSupplierRole) {
            $supplierData = $this->supplierService->getSupplierProfileData((int)$party['supplier_id'], $branchId);
        }

        // Aggregate Financial Summary
        $receivable = (float)($customerData['financial_summary']['outstanding_balance'] ?? ($hasCustomerRole ? (float)$party['current_balance'] : 0.0));
        $payable = (float)($supplierData['financial_summary']['outstanding_bills_balance'] ?? ($hasSupplierRole ? (float)$party['current_balance'] : 0.0));
        
        $totalBilledSales = (float)($customerData['financial_summary']['total_billed_amount'] ?? 0.0);
        $totalBilledPurchases = (float)($supplierData['financial_summary']['total_billed_amount'] ?? 0.0);
        
        $totalReceipts = (float)($customerData['financial_summary']['total_payments_received'] ?? 0.0);
        $totalPaymentsMade = (float)($supplierData['financial_summary']['total_payments_made'] ?? 0.0);

        $invoices = $customerData['invoices'] ?? [];
        $quotations = $customerData['quotations'] ?? [];
        $bills = $supplierData['bills'] ?? [];
        $purchaseOrders = [];
        $debitNotes = $supplierData['debit_notes'] ?? [];
        $creditNotes = $customerData['credit_notes'] ?? [];
        $payments = [];

        // Purchase orders for supplier
        if ($hasSupplierRole && !empty($party['supplier_id'])) {
            $purchaseOrders = Database::select(
                "SELECT po.*, b.name as branch_name 
                 FROM purchase_orders po 
                 LEFT JOIN branches b ON b.id = po.branch_id 
                 WHERE po.supplier_id = ? AND po.company_id = ? 
                 ORDER BY po.po_date DESC, po.id DESC",
                [(int)$party['supplier_id'], $companyId]
            );
        }

        // Consolidated Payments & Receipts
        if ($hasCustomerRole && !empty($party['customer_id'])) {
            $custPayments = Database::select(
                "SELECT id, payment_number as doc_number, payment_date as doc_date, amount, payment_mode, reference_no, 'Receipt' as entry_type 
                 FROM payments 
                 WHERE party_type = 'customer' AND party_id = ? AND company_id = ? 
                 ORDER BY payment_date DESC",
                [(int)$party['customer_id'], $companyId]
            );
            $payments = array_merge($payments, $custPayments);
        }

        if ($hasSupplierRole && !empty($party['supplier_id'])) {
            $suppPayments = Database::select(
                "SELECT id, payment_number as doc_number, payment_date as doc_date, amount, payment_mode, reference_no, 'Payment' as entry_type 
                 FROM payments 
                 WHERE party_type = 'supplier' AND party_id = ? AND company_id = ? 
                 ORDER BY payment_date DESC",
                [(int)$party['supplier_id'], $companyId]
            );
            $payments = array_merge($payments, $suppPayments);
        }

        // Build Consolidated Chronological Ledger
        $ledgerEntries = [];

        // Add customer invoices (Debits to customer receivable)
        foreach ($invoices as $inv) {
            $ledgerEntries[] = [
                'date' => $inv['invoice_date'] ?? $inv['created_at'],
                'type' => 'Tax Invoice',
                'reference' => $inv['invoice_number'] ?? ('INV-' . $inv['id']),
                'debit' => (float)($inv['grand_total'] ?? 0.0),
                'credit' => 0.0,
                'role' => 'Customer',
                'description' => 'Sales Invoice ' . ($inv['invoice_number'] ?? ''),
                'status' => $inv['payment_status'] ?? 'UNPAID',
            ];
        }

        // Add customer receipts (Credits to customer receivable)
        foreach ($customerData['payments'] ?? [] as $pmt) {
            $ledgerEntries[] = [
                'date' => $pmt['payment_date'] ?? $pmt['created_at'],
                'type' => 'Customer Receipt',
                'reference' => $pmt['payment_number'] ?? ('REC-' . $pmt['id']),
                'debit' => 0.0,
                'credit' => (float)($pmt['amount'] ?? 0.0),
                'role' => 'Customer',
                'description' => 'Receipt via ' . ($pmt['payment_mode'] ?? 'Cash/Bank'),
                'status' => 'RECEIVED',
            ];
        }

        // Add supplier bills (Credits to supplier payable)
        foreach ($bills as $bill) {
            $ledgerEntries[] = [
                'date' => $bill['bill_date'] ?? $bill['created_at'],
                'type' => 'Purchase Bill',
                'reference' => $bill['bill_number'] ?? ('BILL-' . $bill['id']),
                'debit' => 0.0,
                'credit' => (float)($bill['total_amount'] ?? 0.0),
                'role' => 'Supplier',
                'description' => 'Vendor Inward Bill ' . ($bill['bill_number'] ?? ''),
                'status' => $bill['payment_status'] ?? 'UNPAID',
            ];
        }

        // Add supplier payments (Debits to supplier payable)
        foreach ($supplierData['payments'] ?? [] as $spmt) {
            $ledgerEntries[] = [
                'date' => $spmt['payment_date'] ?? $spmt['created_at'],
                'type' => 'Supplier Payment',
                'reference' => $spmt['payment_number'] ?? ('PAY-' . $spmt['id']),
                'debit' => (float)($spmt['amount'] ?? 0.0),
                'credit' => 0.0,
                'role' => 'Supplier',
                'description' => 'Payment made via ' . ($spmt['payment_mode'] ?? 'Bank'),
                'status' => 'PAID',
            ];
        }

        // Sort ledger entries chronologically
        usort($ledgerEntries, function ($a, $b) {
            return strcmp((string)$a['date'], (string)$b['date']);
        });

        // Compute running balance
        $running = (float)$party['opening_balance'];
        foreach ($ledgerEntries as &$entry) {
            $running += ($entry['debit'] - $entry['credit']);
            $entry['running_balance'] = $running;
        }
        unset($entry);

        return [
            'party' => $party,
            'party_type' => $partyType,
            'has_customer_role' => $hasCustomerRole,
            'has_supplier_role' => $hasSupplierRole,
            'financial_summary' => [
                'opening_balance' => (float)$party['opening_balance'],
                'opening_balance_type' => $party['opening_balance_type'],
                'receivable_balance' => $receivable,
                'payable_balance' => $payable,
                'net_balance' => $receivable - $payable,
                'total_billed_sales' => $totalBilledSales,
                'total_billed_purchases' => $totalBilledPurchases,
                'total_receipts' => $totalReceipts,
                'total_payments_made' => $totalPaymentsMade,
                'invoices_count' => count($invoices),
                'bills_count' => count($bills),
                'quotations_count' => count($quotations),
                'payments_count' => count($payments),
            ],
            'invoices' => $invoices,
            'bills' => $bills,
            'quotations' => $quotations,
            'purchase_orders' => $purchaseOrders,
            'credit_notes' => $creditNotes,
            'debit_notes' => $debitNotes,
            'payments' => $payments,
            'ledger' => array_reverse($ledgerEntries), // Latest on top for UI
            'customer_record' => $customerData['customer'] ?? null,
            'supplier_record' => $supplierData['supplier'] ?? null,
        ];
    }

    /**
     * Create a new party with Customer, Supplier, or Both roles.
     * Prevents duplicate party records when marked Both.
     */
    public function createParty(array $input, ?int $userId = null): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = $userId ?? (Session::get('user_id') ? (int)Session::get('user_id') : 1);

        $name = trim((string)($input['name'] ?? ''));
        if (empty($name)) {
            return ['success' => false, 'message' => 'Party Name is strictly required.'];
        }

        $partyType = ucfirst(strtolower(trim((string)($input['party_type'] ?? 'Customer'))));
        if (!in_array($partyType, ['Customer', 'Supplier', 'Both'], true)) {
            $partyType = 'Customer';
        }

        $gstin = strtoupper(trim((string)($input['gstin'] ?? '')));
        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                return ['success' => false, 'message' => 'GSTIN must be exactly 15 alphanumeric characters.'];
            }
            // Check for duplicate GSTIN within company
            $existing = $this->partyRepo->findByGstin($gstin, $companyId);
            if ($existing) {
                return ['success' => false, 'message' => "A party with GSTIN {$gstin} already exists ({$existing['name']})."];
            }
        }

        $pan = strtoupper(trim((string)($input['pan'] ?? '')));
        if (empty($pan) && strlen($gstin) === 15) {
            $pan = substr($gstin, 2, 10);
        }

        $phone = trim((string)($input['phone'] ?? ($input['mobile_number'] ?? '')));
        $altPhone = trim((string)($input['alt_phone'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address format.'];
        }

        $companyName = trim((string)($input['company_name'] ?? $name));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $billingAddress = trim((string)($input['billing_address'] ?? ($input['address_line1'] ?? '')));
        $shippingAddress = trim((string)($input['shipping_address'] ?? $billingAddress));
        $city = trim((string)($input['city'] ?? ''));
        $state = trim((string)($input['state'] ?? 'Maharashtra'));
        $stateCode = trim((string)($input['state_code'] ?? '27'));
        $pincode = trim((string)($input['pincode'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));

        $openingBalance = (float)($input['opening_balance'] ?? 0.0);
        $openingBalanceType = ucfirst(strtolower(trim((string)($input['opening_balance_type'] ?? 'Debit'))));
        $currentBalance = ($openingBalanceType === 'Credit') ? -$openingBalance : $openingBalance;
        $creditLimit = (float)($input['credit_limit'] ?? 0.0);
        $paymentTermsDays = max(0, (int)($input['payment_terms_days'] ?? ($input['payment_terms'] ?? 30)));
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';

        $partyCode = trim((string)($input['party_code'] ?? ''));
        if (empty($partyCode)) {
            $partyCode = $this->partyRepo->generatePartyCode($companyId, $partyType);
        }

        // 1. Insert into parties table
        Database::execute(
            "INSERT INTO parties (company_id, party_code, party_type, name, company_name, contact_person, phone, alt_phone, email, billing_address, shipping_address, gstin, pan, city, state, state_code, pincode, opening_balance, opening_balance_type, current_balance, credit_limit, payment_terms_days, notes, is_active, status, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $companyId, $partyCode, $partyType, $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                $billingAddress, $shippingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                $openingBalance, $openingBalanceType, $currentBalance, $creditLimit, $paymentTermsDays, $notes,
                $isActive, $status, $userId, $userId
            ]
        );
        $partyId = (int)Database::pdo()->lastInsertId();

        $customerId = null;
        $supplierId = null;

        // 2. Sync to Customers table if Customer or Both
        if ($partyType === 'Customer' || $partyType === 'Both') {
            Database::execute(
                "INSERT INTO customers (company_id, party_id, name, company_name, contact_person, phone, alt_phone, email, address_line1, gstin, pan, city, state, state_code, pincode, opening_balance, current_balance, credit_limit, payment_terms_days, notes, is_active, status, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $companyId, $partyId, $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                    $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                    $openingBalance, $currentBalance, $creditLimit, $paymentTermsDays, $notes,
                    $isActive, $status, $userId, $userId
                ]
            );
            $customerId = (int)Database::pdo()->lastInsertId();
        }

        // 3. Sync to Suppliers table if Supplier or Both
        if ($partyType === 'Supplier' || $partyType === 'Both') {
            Database::execute(
                "INSERT INTO suppliers (company_id, party_id, name, company_name, contact_person, phone, alt_phone, email, address_line1, gstin, pan, city, state, state_code, pincode, opening_balance, current_balance, payment_terms_days, notes, is_active, status, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $companyId, $partyId, $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                    $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                    $openingBalance, $currentBalance, $paymentTermsDays, $notes,
                    $isActive, $status, $userId, $userId
                ]
            );
            $supplierId = (int)Database::pdo()->lastInsertId();
        }

        // 4. Update parties with customer_id and supplier_id references
        Database::execute(
            "UPDATE parties SET customer_id = ?, supplier_id = ? WHERE id = ?",
            [$customerId, $supplierId, $partyId]
        );

        $createdParty = $this->partyRepo->findParty($partyId, $companyId);

        return [
            'success' => true,
            'party' => $createdParty,
            'party_id' => $partyId,
            'message' => 'Party record created successfully.',
        ];
    }

    /**
     * Update an existing party record and keep Customer and Supplier roles synchronized.
     */
    public function updateParty(int $id, array $input, ?int $userId = null): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = $userId ?? (Session::get('user_id') ? (int)Session::get('user_id') : 1);

        $party = $this->partyRepo->findParty($id, $companyId);
        if (!$party) {
            return ['success' => false, 'message' => 'Party not found.'];
        }

        $name = trim((string)($input['name'] ?? $party['name']));
        if (empty($name)) {
            return ['success' => false, 'message' => 'Party Name is required.'];
        }

        $partyType = ucfirst(strtolower(trim((string)($input['party_type'] ?? $party['party_type']))));
        if (!in_array($partyType, ['Customer', 'Supplier', 'Both'], true)) {
            $partyType = $party['party_type'];
        }

        $gstin = strtoupper(trim((string)($input['gstin'] ?? ($party['gstin'] ?? ''))));
        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                return ['success' => false, 'message' => 'GSTIN must be 15 characters.'];
            }
            $existing = $this->partyRepo->findByGstin($gstin, $companyId, $id);
            if ($existing) {
                return ['success' => false, 'message' => "Another party with GSTIN {$gstin} already exists."];
            }
        }

        $pan = strtoupper(trim((string)($input['pan'] ?? ($party['pan'] ?? ''))));
        if (empty($pan) && strlen($gstin) === 15) {
            $pan = substr($gstin, 2, 10);
        }

        $phone = trim((string)($input['phone'] ?? ($input['mobile_number'] ?? $party['phone'])));
        $altPhone = trim((string)($input['alt_phone'] ?? $party['alt_phone']));
        $email = strtolower(trim((string)($input['email'] ?? $party['email'])));
        $companyName = trim((string)($input['company_name'] ?? $party['company_name']));
        $contactPerson = trim((string)($input['contact_person'] ?? $party['contact_person']));
        $billingAddress = trim((string)($input['billing_address'] ?? ($input['address_line1'] ?? $party['billing_address'])));
        $shippingAddress = trim((string)($input['shipping_address'] ?? ($party['shipping_address'] ?: $billingAddress)));
        $city = trim((string)($input['city'] ?? $party['city']));
        $state = trim((string)($input['state'] ?? $party['state']));
        $stateCode = trim((string)($input['state_code'] ?? $party['state_code']));
        $pincode = trim((string)($input['pincode'] ?? $party['pincode']));
        $notes = trim((string)($input['notes'] ?? $party['notes']));

        $openingBalance = isset($input['opening_balance']) ? (float)$input['opening_balance'] : (float)$party['opening_balance'];
        $openingBalanceType = ucfirst(strtolower(trim((string)($input['opening_balance_type'] ?? $party['opening_balance_type']))));
        $creditLimit = isset($input['credit_limit']) ? (float)$input['credit_limit'] : (float)$party['credit_limit'];
        $paymentTermsDays = isset($input['payment_terms_days']) ? (int)$input['payment_terms_days'] : (int)$party['payment_terms_days'];
        
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : (int)$party['is_active'];
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';

        // 1. Update parties table
        Database::execute(
            "UPDATE parties SET 
                name = ?, company_name = ?, party_type = ?, contact_person = ?, phone = ?, alt_phone = ?, email = ?,
                billing_address = ?, shipping_address = ?, gstin = ?, pan = ?, city = ?, state = ?, state_code = ?, pincode = ?,
                opening_balance = ?, opening_balance_type = ?, credit_limit = ?, payment_terms_days = ?, notes = ?,
                is_active = ?, status = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?",
            [
                $name, $companyName, $partyType, $contactPerson, $phone, $altPhone, $email,
                $billingAddress, $shippingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                $openingBalance, $openingBalanceType, $creditLimit, $paymentTermsDays, $notes,
                $isActive, $status, $userId, $id, $companyId
            ]
        );

        $customerId = $party['customer_id'] ? (int)$party['customer_id'] : null;
        $supplierId = $party['supplier_id'] ? (int)$party['supplier_id'] : null;

        // 2. Synchronize Customer Role
        if ($partyType === 'Customer' || $partyType === 'Both') {
            if ($customerId) {
                Database::execute(
                    "UPDATE customers SET 
                        name = ?, company_name = ?, contact_person = ?, phone = ?, alt_phone = ?, email = ?,
                        address_line1 = ?, gstin = ?, pan = ?, city = ?, state = ?, state_code = ?, pincode = ?,
                        credit_limit = ?, payment_terms_days = ?, notes = ?, is_active = ?, status = ?, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ?",
                    [
                        $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                        $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                        $creditLimit, $paymentTermsDays, $notes, $isActive, $status, $userId, $customerId, $companyId
                    ]
                );
            } else {
                Database::execute(
                    "INSERT INTO customers (company_id, party_id, name, company_name, contact_person, phone, alt_phone, email, address_line1, gstin, pan, city, state, state_code, pincode, opening_balance, current_balance, credit_limit, payment_terms_days, notes, is_active, status, created_by, updated_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $companyId, $id, $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                        $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                        $openingBalance, (float)$party['current_balance'], $creditLimit, $paymentTermsDays, $notes,
                        $isActive, $status, $userId, $userId
                    ]
                );
                $customerId = (int)Database::pdo()->lastInsertId();
            }
        } elseif ($partyType === 'Supplier' && $customerId) {
            // Deactivate customer role if demoted to pure supplier
            Database::execute("UPDATE customers SET is_active = 0, status = 'INACTIVE' WHERE id = ? AND company_id = ?", [$customerId, $companyId]);
        }

        // 3. Synchronize Supplier Role
        if ($partyType === 'Supplier' || $partyType === 'Both') {
            if ($supplierId) {
                Database::execute(
                    "UPDATE suppliers SET 
                        name = ?, company_name = ?, contact_person = ?, phone = ?, alt_phone = ?, email = ?,
                        address_line1 = ?, gstin = ?, pan = ?, city = ?, state = ?, state_code = ?, pincode = ?,
                        payment_terms_days = ?, notes = ?, is_active = ?, status = ?, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ?",
                    [
                        $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                        $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                        $paymentTermsDays, $notes, $isActive, $status, $userId, $supplierId, $companyId
                    ]
                );
            } else {
                Database::execute(
                    "INSERT INTO suppliers (company_id, party_id, name, company_name, contact_person, phone, alt_phone, email, address_line1, gstin, pan, city, state, state_code, pincode, opening_balance, current_balance, payment_terms_days, notes, is_active, status, created_by, updated_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $companyId, $id, $name, $companyName, $contactPerson, $phone, $altPhone, $email,
                        $billingAddress, $gstin, $pan, $city, $state, $stateCode, $pincode,
                        $openingBalance, (float)$party['current_balance'], $paymentTermsDays, $notes,
                        $isActive, $status, $userId, $userId
                    ]
                );
                $supplierId = (int)Database::pdo()->lastInsertId();
            }
        } elseif ($partyType === 'Customer' && $supplierId) {
            // Deactivate supplier role if demoted to pure customer
            Database::execute("UPDATE suppliers SET is_active = 0, status = 'INACTIVE' WHERE id = ? AND company_id = ?", [$supplierId, $companyId]);
        }

        // 4. Update party IDs
        Database::execute("UPDATE parties SET customer_id = ?, supplier_id = ? WHERE id = ?", [$customerId, $supplierId, $id]);

        $updatedParty = $this->partyRepo->findParty($id, $companyId);

        return [
            'success' => true,
            'party' => $updatedParty,
            'message' => 'Party record updated successfully.',
        ];
    }

    /**
     * Toggle Party active / inactive status across both roles.
     */
    public function togglePartyStatus(int $id): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $party = $this->partyRepo->findParty($id, $companyId);
        if (!$party) {
            return ['success' => false, 'message' => 'Party not found.'];
        }

        $newActive = $party['is_active'] ? 0 : 1;
        $newStatus = $newActive ? 'ACTIVE' : 'INACTIVE';

        Database::execute(
            "UPDATE parties SET is_active = ?, status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?",
            [$newActive, $newStatus, $id, $companyId]
        );

        if (!empty($party['customer_id'])) {
            Database::execute("UPDATE customers SET is_active = ?, status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?", [$newActive, $newStatus, $party['customer_id'], $companyId]);
        }

        if (!empty($party['supplier_id'])) {
            Database::execute("UPDATE suppliers SET is_active = ?, status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?", [$newActive, $newStatus, $party['supplier_id'], $companyId]);
        }

        return [
            'success' => true,
            'is_active' => $newActive,
            'status' => $newStatus,
            'message' => "Party has been {$newStatus}.",
        ];
    }

    /**
     * Soft delete or deactivate a party.
     */
    public function deleteParty(int $id): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $party = $this->partyRepo->findParty($id, $companyId);
        if (!$party) {
            return ['success' => false, 'message' => 'Party not found.'];
        }

        // Check if party has invoices or purchases
        $hasInvoices = false;
        if (!empty($party['customer_id'])) {
            $invRow = Database::selectOne("SELECT COUNT(*) as c FROM invoices WHERE customer_id = ? AND company_id = ? LIMIT 1", [$party['customer_id'], $companyId]);
            $hasInvoices = ((int)($invRow['c'] ?? 0)) > 0;
        }

        $hasBills = false;
        if (!empty($party['supplier_id'])) {
            $billRow = Database::selectOne("SELECT COUNT(*) as c FROM purchases WHERE supplier_id = ? AND company_id = ? LIMIT 1", [$party['supplier_id'], $companyId]);
            $hasBills = ((int)($billRow['c'] ?? 0)) > 0;
        }

        if ($hasInvoices || $hasBills) {
            // Cannot hard delete when financial transactions exist; deactivate safely
            Database::execute("UPDATE parties SET is_active = 0, status = 'INACTIVE', deleted_at = NOW() WHERE id = ? AND company_id = ?", [$id, $companyId]);
            if (!empty($party['customer_id'])) {
                Database::execute("UPDATE customers SET is_active = 0, status = 'INACTIVE', deleted_at = NOW() WHERE id = ? AND company_id = ?", [$party['customer_id'], $companyId]);
            }
            if (!empty($party['supplier_id'])) {
                Database::execute("UPDATE suppliers SET is_active = 0, status = 'INACTIVE', deleted_at = NOW() WHERE id = ? AND company_id = ?", [$party['supplier_id'], $companyId]);
            }
            return [
                'success' => true,
                'soft_deleted' => true,
                'message' => 'Party has transaction records; party was deactivated and archived safely.',
            ];
        }

        // Clean delete if no transaction records exist
        Database::execute("UPDATE parties SET deleted_at = NOW(), is_active = 0, status = 'INACTIVE' WHERE id = ? AND company_id = ?", [$id, $companyId]);
        if (!empty($party['customer_id'])) {
            Database::execute("UPDATE customers SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND company_id = ?", [$party['customer_id'], $companyId]);
        }
        if (!empty($party['supplier_id'])) {
            Database::execute("UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND company_id = ?", [$party['supplier_id'], $companyId]);
        }

        return [
            'success' => true,
            'soft_deleted' => false,
            'message' => 'Party record deleted successfully.',
        ];
    }
}
