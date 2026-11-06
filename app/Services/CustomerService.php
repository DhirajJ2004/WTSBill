<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;
use WTSBill\Repositories\CustomerRepository;

/**
 * Customer Business Service.
 * Implements GSTIN/PAN validation, state code mapping, credit limit checks, and multi-tenant persistence.
 */
class CustomerService
{
    protected CustomerRepository $customerRepo;

    public function __construct(?CustomerRepository $customerRepo = null)
    {
        $this->customerRepo = $customerRepo ?? new CustomerRepository();
    }

    /**
     * Retrieve all customers for listing.
     */
    public function getCustomers(string $search = ''): array
    {
        return $this->customerRepo->search($search);
    }

    /**
     * Get single customer by ID.
     */
    public function getCustomer(int $id): ?array
    {
        return $this->customerRepo->find($id);
    }

    /**
     * Register a new customer with validation and transaction safety.
     */
    public function createCustomer(array $data): array
    {
        $validated = $this->validateCustomerData($data);
        $companyId = Session::getCompanyId() ?? 1;

        $insertData = [
            'company_id' => $companyId,
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'gstin' => $validated['gstin'] ?: null,
            'pan' => $validated['pan'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address_line1' => $validated['address_line1'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'state_code' => $validated['state_code'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'opening_balance' => (float)($validated['opening_balance'] ?? 0),
            'current_balance' => (float)($validated['opening_balance'] ?? 0),
            'credit_limit' => (float)($validated['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'notes' => $validated['notes'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int)$this->customerRepo->create($insertData);
        return $this->customerRepo->find($id);
    }

    /**
     * Update an existing customer record.
     */
    public function updateCustomer(int $id, array $data): array
    {
        $existing = $this->customerRepo->find($id);
        if (!$existing) {
            throw new ValidationException(['id' => 'Customer record not found.']);
        }

        $validated = $this->validateCustomerData($data, $id);

        $updateData = [
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'gstin' => $validated['gstin'] ?: null,
            'pan' => $validated['pan'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address_line1' => $validated['address_line1'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'state_code' => $validated['state_code'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'credit_limit' => (float)($validated['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'notes' => $validated['notes'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->customerRepo->update($id, $updateData);
        return $this->customerRepo->find($id);
    }

    /**
     * Delete / deactivate a customer.
     */
    public function deleteCustomer(int $id): bool
    {
        $existing = $this->customerRepo->find($id);
        if ($existing === null) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->customerRepo->update($id, [
            'is_active' => 0,
            'deleted_at' => $now,
            'status' => 'INACTIVE',
            'updated_at' => $now,
        ]);

        return true;
    }

    /**
     * Validate payload fields for customer registration.
     */
    protected function validateCustomerData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            $errors['name'] = 'Customer name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Customer name must be at least 2 characters.';
        }

        $gstin = strtoupper(trim((string)($data['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($data['pan'] ?? '')));

        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                $errors['gstin'] = 'GSTIN must be exactly 15 alphanumeric characters.';
            } else {
                $existing = $this->customerRepo->findByGstin($gstin, $excludeId);
                if ($existing) {
                    $errors['gstin'] = "A customer with GSTIN '{$gstin}' already exists.";
                }
                // Derive PAN from GSTIN if PAN is empty
                if (empty($pan)) {
                    $pan = substr($gstin, 2, 10);
                }
            }
        }

        $state = trim((string)($data['state'] ?? ''));
        $stateCode = trim((string)($data['state_code'] ?? ''));
        if (!empty($gstin) && empty($stateCode)) {
            $stateCode = substr($gstin, 0, 2);
        }

        $email = trim((string)($data['email'] ?? ''));
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return array_merge($data, [
            'name' => $name,
            'company_name' => trim((string)($data['company_name'] ?? '')),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => $email,
            'phone' => trim((string)($data['phone'] ?? '')),
            'address_line1' => trim((string)($data['address_line1'] ?? '')),
            'city' => trim((string)($data['city'] ?? '')),
            'state' => $state,
            'state_code' => $stateCode,
            'pincode' => trim((string)($data['pincode'] ?? '')),
            'opening_balance' => (float)($data['opening_balance'] ?? 0),
            'credit_limit' => (float)($data['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($data['payment_terms_days'] ?? 30),
            'notes' => trim((string)($data['notes'] ?? '')),
            'contact_person' => trim((string)($data['contact_person'] ?? '')),
        ]);
    }

    /**
     * Retrieve complete Customer Profile and historical ledger/documents for 360-degree profile.
     *
     * @param int $customerId
     * @param int $branchId (0 for consolidated or specific branch)
     * @return array|null
     */
    public function getCustomerProfileData(int $customerId, int $branchId = 0): ?array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $customer = \WTSBill\Core\Database::selectOne(
            "SELECT c.*, b.name AS branch_name 
             FROM customers c 
             LEFT JOIN branches b ON c.customer_group_id = b.id 
             WHERE c.id = ? AND c.company_id = ? AND c.deleted_at IS NULL",
            [$customerId, $companyId]
        );

        if (!$customer) {
            return null;
        }

        // Branch clause for documents
        $invBranchSql = "";
        $invParams = [$customerId, $companyId];
        if ($branchId > 0) {
            $invBranchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $invParams[] = $branchId;
        }

        // 1. Invoices
        $invoices = \WTSBill\Core\Database::select(
            "SELECT id, invoice_number, invoice_date, due_date, grand_total, amount_paid, amount_due, status, branch_id
             FROM invoices 
             WHERE customer_id = ? AND company_id = ? AND deleted_at IS NULL AND status != 'CANCELLED'
             {$invBranchSql}
             ORDER BY invoice_date DESC, id DESC",
            $invParams
        );

        $today = date('Y-m-d');
        $outstandingInvoices = [];
        $totalInvoiced = 0.0;
        $totalPaid = 0.0;
        $outstandingBal = 0.0;
        $overdueAmt = 0.0;

        foreach ($invoices as &$inv) {
            $gt = (float)$inv['grand_total'];
            $pd = (float)($inv['amount_paid'] ?? 0);
            $due = (float)($inv['amount_due'] ?? 0);
            $totalInvoiced += $gt;
            $totalPaid += $pd;
            $outstandingBal += $due;

            $dueDate = !empty($inv['due_date']) ? $inv['due_date'] : $inv['invoice_date'];
            $isOverdue = ($due > 0.001 && $dueDate < $today);
            $daysOverdue = $isOverdue ? (int)floor((strtotime($today) - strtotime($dueDate)) / 86400) : 0;
            if ($isOverdue) {
                $overdueAmt += $due;
            }
            $inv['days_overdue'] = $daysOverdue;
            $inv['is_overdue'] = $isOverdue;
            $inv['grand_total_formatted'] = $this->formatCurrency($gt);
            $inv['amount_paid_formatted'] = $this->formatCurrency($pd);
            $inv['amount_due_formatted'] = $this->formatCurrency($due);

            if ($due > 0.001) {
                $outstandingInvoices[] = $inv;
            }
        }
        unset($inv);

        // 2. Payments
        $pmtBranchSql = "";
        $pmtParams = [$customerId, $customerId];
        if ($branchId > 0) {
            $pmtBranchSql = " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $pmtParams[] = $branchId;
        }

        $payments = \WTSBill\Core\Database::select(
            "SELECT p.id, COALESCE(p.receipt_number, p.payment_number, CONCAT('RCP-', p.id)) AS receipt_number,
                    p.payment_date, p.amount, p.payment_mode,
                    COALESCE(p.transaction_reference, p.reference_number, p.reference_no, p.utr, p.cheque_number, '—') AS reference_number,
                    i.invoice_number, i.id AS invoice_id, p.status, p.branch_id
             FROM payments p
             LEFT JOIN payment_allocations pa ON p.id = pa.payment_id AND pa.document_type = 'INVOICE'
             LEFT JOIN invoices i ON pa.document_id = i.id
             WHERE ((p.party_id = ? AND p.party_type = 'CUSTOMER') OR (i.customer_id = ?))
             {$pmtBranchSql}
             GROUP BY p.id
             ORDER BY p.payment_date DESC, p.id DESC",
            $pmtParams
        );

        foreach ($payments as &$pmt) {
            $pmt['amount_formatted'] = $this->formatCurrency((float)$pmt['amount']);
        }
        unset($pmt);

        // 3. Quotations
        $qBranchSql = "";
        $qParams = [$customerId, $companyId];
        if ($branchId > 0) {
            $qBranchSql = " AND (q.branch_id = ? OR q.branch_id IS NULL)";
            $qParams[] = $branchId;
        }

        $quotations = \WTSBill\Core\Database::select(
            "SELECT q.id, q.quotation_number, q.quotation_date, q.valid_until, q.grand_total, q.status,
                    q.converted_to_invoice, q.converted_invoice_id, inv.invoice_number AS converted_invoice_number,
                    inv.status AS linked_invoice_status
             FROM quotations q
             LEFT JOIN invoices inv ON q.converted_invoice_id = inv.id
             WHERE q.customer_id = ? AND q.company_id = ? AND q.deleted_at IS NULL
             {$qBranchSql}
             ORDER BY q.quotation_date DESC, q.id DESC",
            $qParams
        );

        $qTotalCount = count($quotations);
        $qAcceptedCount = 0;
        $qPendingCount = 0;
        foreach ($quotations as &$q) {
            $st = strtoupper((string)$q['status']);
            if ($st === 'ACCEPTED' || $st === 'CONVERTED' || (int)$q['converted_to_invoice'] === 1) {
                $qAcceptedCount++;
            } elseif ($st === 'SENT' || $st === 'PENDING' || $st === 'DRAFT') {
                $qPendingCount++;
            }
            $q['grand_total_formatted'] = $this->formatCurrency((float)$q['grand_total']);
        }
        unset($q);

        // 4. Credit Notes
        $cnParams = [$customerId, $customerId, $companyId];
        $creditNotes = \WTSBill\Core\Database::select(
            "SELECT cn.id, cn.credit_note_number, cn.credit_note_date, cn.amount, cn.reason,
                    i.invoice_number, i.id AS invoice_id, cn.status
             FROM credit_notes cn
             LEFT JOIN invoices i ON cn.invoice_id = i.id
             WHERE (cn.customer_id = ? OR i.customer_id = ?) AND cn.company_id = ?
             ORDER BY cn.credit_note_date DESC, cn.id DESC",
            $cnParams
        );

        $totalCreditNotes = 0.0;
        foreach ($creditNotes as &$cn) {
            $amt = (float)$cn['amount'];
            $totalCreditNotes += $amt;
            $cn['amount_formatted'] = $this->formatCurrency($amt);
        }
        unset($cn);

        // 5. Customer Ledger (Chronological running balance)
        $cOpen = (float)($customer['opening_balance'] ?? 0);
        $ledgerEvents = [];

        if (abs($cOpen) > 0.001) {
            $ledgerEvents[] = [
                'date' => $customer['created_at'] ? date('Y-m-d', strtotime((string)$customer['created_at'])) : date('Y-01-01'),
                'voucher' => 'OPENING',
                'type' => 'OPENING_BALANCE',
                'description' => 'Opening Balance',
                'debit' => $cOpen > 0 ? $cOpen : 0.0,
                'credit' => $cOpen < 0 ? abs($cOpen) : 0.0,
                'sort_ts' => strtotime((string)($customer['created_at'] ?? '2026-01-01')),
            ];
        }

        foreach ($invoices as $invRow) {
            $ledgerEvents[] = [
                'date' => $invRow['invoice_date'],
                'voucher' => $invRow['invoice_number'],
                'type' => 'INVOICE',
                'description' => 'Tax Invoice ' . $invRow['invoice_number'],
                'debit' => (float)$invRow['grand_total'],
                'credit' => 0.0,
                'sort_ts' => strtotime($invRow['invoice_date'] . ' 00:00:00'),
            ];
        }

        foreach ($payments as $pmtRow) {
            $ledgerEvents[] = [
                'date' => $pmtRow['payment_date'],
                'voucher' => $pmtRow['receipt_number'],
                'type' => 'PAYMENT',
                'description' => 'Payment Receipt (' . $pmtRow['payment_mode'] . ($pmtRow['invoice_number'] ? ' for ' . $pmtRow['invoice_number'] : '') . ')',
                'debit' => 0.0,
                'credit' => (float)$pmtRow['amount'],
                'sort_ts' => strtotime($pmtRow['payment_date'] . ' 12:00:00'),
            ];
        }

        foreach ($creditNotes as $cnRow) {
            $ledgerEvents[] = [
                'date' => $cnRow['credit_note_date'],
                'voucher' => $cnRow['credit_note_number'],
                'type' => 'CREDIT_NOTE',
                'description' => 'Credit Note' . ($cnRow['reason'] ? ' - ' . $cnRow['reason'] : ''),
                'debit' => 0.0,
                'credit' => (float)$cnRow['amount'],
                'sort_ts' => strtotime($cnRow['credit_note_date'] . ' 15:00:00'),
            ];
        }

        usort($ledgerEvents, fn($a, $b) => $a['sort_ts'] <=> $b['sort_ts']);

        $customerLedger = [];
        $runningBal = 0.0;
        foreach ($ledgerEvents as $ev) {
            $dr = (float)$ev['debit'];
            $cr = (float)$ev['credit'];
            $runningBal += ($dr - $cr);
            $customerLedger[] = [
                'date' => $ev['date'],
                'voucher_number' => $ev['voucher'],
                'type' => $ev['type'],
                'description' => $ev['description'],
                'debit' => $dr,
                'debit_formatted' => $dr > 0 ? $this->formatCurrency($dr) : '-',
                'credit' => $cr,
                'credit_formatted' => $cr > 0 ? $this->formatCurrency($cr) : '-',
                'running_balance' => round($runningBal, 2),
                'running_balance_formatted' => $this->formatCurrency($runningBal) . ($runningBal >= 0 ? ' Dr' : ' Cr'),
            ];
        }

        // 6. Activity History
        $custNameTerm = '%' . $customer['name'] . '%';
        $activityHistory = \WTSBill\Core\Database::select(
            "SELECT id, action, entity_type, entity_id, description, ip_address, created_at, user_name
             FROM audit_logs
             WHERE (entity_type = 'CUSTOMER' AND entity_id = ?)
                OR (entity_type = 'INVOICE' AND entity_id IN (SELECT id FROM invoices WHERE customer_id = ?))
                OR (description LIKE ?)
             ORDER BY id DESC
             LIMIT 30",
            [$customerId, $customerId, $custNameTerm]
        );

        $customerCode = 'CUST-' . str_pad((string)$customer['id'], 4, '0', STR_PAD_LEFT);
        $customerAddresses = $this->getCustomerAddresses($customerId);

        return [
            'success' => true,
            'customer' => array_merge($customer, [
                'customer_code' => $customerCode,
                'phone' => $customer['phone'] ?: '—',
                'email' => $customer['email'] ?: '—',
                'gstin' => $customer['gstin'] ?: '—',
                'customer_type' => $customer['customer_type'] ?: 'Business',
                'status' => ((int)$customer['is_active'] === 1) ? 'ACTIVE' : 'INACTIVE',
                'registration_date' => $customer['created_at'] ? date('d-M-Y', strtotime((string)$customer['created_at'])) : '—',
                'assigned_branch' => $customer['branch_name'] ?: 'All Branches (Shared Access)',
                'billing_address' => implode(', ', array_filter([$customer['address_line1'], $customer['city'], $customer['state'], $customer['pincode']])) ?: '—',
                'shipping_address' => implode(', ', array_filter([$customer['address_line1'], $customer['city'], $customer['state'], $customer['pincode']])) ?: '—',
                'notes' => $customer['notes'] ?? '',
                'contact_person' => $customer['contact_person'] ?? '',
            ]),
            'financial_summary' => [
                'total_invoiced' => round($totalInvoiced, 2),
                'total_invoiced_formatted' => $this->formatCurrency($totalInvoiced),
                'total_paid' => round($totalPaid, 2),
                'total_paid_formatted' => $this->formatCurrency($totalPaid),
                'outstanding_balance' => round($outstandingBal, 2),
                'outstanding_balance_formatted' => $this->formatCurrency($outstandingBal),
                'overdue_amount' => round($overdueAmt, 2),
                'overdue_amount_formatted' => $this->formatCurrency($overdueAmt),
                'total_quotations' => $qTotalCount,
                'accepted_quotations' => $qAcceptedCount,
                'pending_quotations' => $qPendingCount,
                'total_credit_notes' => round($totalCreditNotes, 2),
                'total_credit_notes_formatted' => $this->formatCurrency($totalCreditNotes),
            ],
            'invoices' => $invoices,
            'outstanding_invoices' => $outstandingInvoices,
            'payments' => $payments,
            'quotations' => $quotations,
            'credit_notes' => $creditNotes,
            'ledger' => $customerLedger,
            'activities' => $activityHistory,
            'addresses' => $customerAddresses,
        ];
    }

    /**
     * Retrieve all saved billing/shipping addresses for a customer.
     */
    public function getCustomerAddresses(int $customerId): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        return \WTSBill\Core\Database::select(
            "SELECT * FROM customer_addresses WHERE customer_id = ? AND company_id = ? ORDER BY is_default DESC, id ASC",
            [$customerId, $companyId]
        );
    }

    /**
     * Add a billing or shipping address for a customer.
     */
    public function addCustomerAddress(int $customerId, array $data): int
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $type = strtoupper((string)($data['type'] ?? 'BILLING'));
        if (!in_array($type, ['BILLING', 'SHIPPING', 'BOTH'], true)) {
            $type = 'BILLING';
        }

        $addressLine1 = trim((string)($data['address_line1'] ?? ''));
        if (empty($addressLine1)) {
            throw new ValidationException(['address_line1' => 'Address line 1 is required.']);
        }

        $city = trim((string)($data['city'] ?? ''));
        $state = trim((string)($data['state'] ?? ''));
        $stateCode = trim((string)($data['state_code'] ?? ''));
        $pincode = trim((string)($data['pincode'] ?? ''));
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault) {
            \WTSBill\Core\Database::update('customer_addresses', ['is_default' => 0], 'customer_id = ? AND company_id = ? AND type = ?', [$customerId, $companyId, $type]);
        }

        $id = \WTSBill\Core\Database::insert('customer_addresses', [
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'type' => $type,
            'address_line1' => $addressLine1,
            'address_line2' => trim((string)($data['address_line2'] ?? '')),
            'city' => $city,
            'state' => $state,
            'state_code' => $stateCode ?: '27',
            'pincode' => $pincode,
            'country' => trim((string)($data['country'] ?? 'India')),
            'landmark' => trim((string)($data['landmark'] ?? '')),
            'is_default' => $isDefault,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$id;
    }

    /**
     * Delete a saved customer address.
     */
    public function deleteCustomerAddress(int $customerId, int $addressId): bool
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        \WTSBill\Core\Database::execute(
            "DELETE FROM customer_addresses WHERE id = ? AND customer_id = ? AND company_id = ?",
            [$addressId, $customerId, $companyId]
        );
        return true;
    }

    /**
     * Format currency in Indian grouping format (₹ 1,23,456.78).
     */
    public function formatCurrency(float $amount): string
    {
        $abs = abs($amount);
        $formatted = '₹ ' . number_format($abs, 2, '.', ',');
        $parts = explode('.', number_format($abs, 2, '.', ''));
        $intPart = $parts[0];
        $decPart = $parts[1] ?? '00';

        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $remaining = substr($intPart, 0, -3);
            $grouped = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining) . ',' . $lastThree;
            $formatted = '₹ ' . $grouped . '.' . $decPart;
        }

        return $amount < 0 ? '(' . $formatted . ')' : $formatted;
    }
}
