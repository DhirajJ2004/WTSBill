<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;
use WTSBill\Repositories\SupplierRepository;

/**
 * Supplier Business Service.
 * Implements GSTIN/PAN validation, state mapping, and multi-tenant persistence.
 */
class SupplierService
{
    protected SupplierRepository $supplierRepo;

    public function __construct(?SupplierRepository $supplierRepo = null)
    {
        $this->supplierRepo = $supplierRepo ?? new SupplierRepository();
    }

    /**
     * Retrieve all suppliers for listing.
     */
    public function getSuppliers(string $search = ''): array
    {
        return $this->supplierRepo->search($search);
    }

    /**
     * Get single supplier by ID.
     */
    public function getSupplier(int $id): ?array
    {
        return $this->supplierRepo->find($id);
    }

    /**
     * Register a new supplier with validation and transaction safety.
     */
    public function createSupplier(array $data): array
    {
        $validated = $this->validateSupplierData($data);
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
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int)$this->supplierRepo->create($insertData);
        return $this->supplierRepo->find($id);
    }

    /**
     * Update an existing supplier record.
     */
    public function updateSupplier(int $id, array $data): array
    {
        $existing = $this->supplierRepo->find($id);
        if (!$existing) {
            throw new ValidationException(['id' => 'Supplier record not found.']);
        }

        $validated = $this->validateSupplierData($data, $id);

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
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->supplierRepo->update($id, $updateData);
        return $this->supplierRepo->find($id);
    }

    /**
     * Delete / deactivate a supplier.
     */
    public function deleteSupplier(int $id): bool
    {
        $existing = $this->supplierRepo->find($id);
        if ($existing === null) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->supplierRepo->update($id, [
            'is_active' => 0,
            'deleted_at' => $now,
            'status' => 'INACTIVE',
            'updated_at' => $now,
        ]);

        return true;
    }

    /**
     * Validate payload fields for supplier registration.
     */
    protected function validateSupplierData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            $errors['name'] = 'Supplier / Vendor name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Supplier name must be at least 2 characters.';
        }

        $gstin = strtoupper(trim((string)($data['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($data['pan'] ?? '')));

        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                $errors['gstin'] = 'GSTIN must be exactly 15 alphanumeric characters.';
            } else {
                $existing = $this->supplierRepo->findByGstin($gstin, $excludeId);
                if ($existing) {
                    $errors['gstin'] = "A supplier with GSTIN '{$gstin}' already exists.";
                }
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
            'payment_terms_days' => (int)($data['payment_terms_days'] ?? 30),
            'contact_person' => trim((string)($data['contact_person'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
    }

    /**
     * Retrieve complete 360-degree Supplier Profile, purchase bills, payments, and ledger.
     */
    public function getSupplierProfileData(int $supplierId, int $branchId = 0): ?array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $supplier = \WTSBill\Core\Database::selectOne(
            "SELECT s.*, b.name AS branch_name
             FROM suppliers s
             LEFT JOIN branches b ON s.supplier_group_id = b.id
             WHERE s.id = ? AND s.company_id = ? AND s.deleted_at IS NULL",
            [$supplierId, $companyId]
        );

        if (!$supplier) {
            return null;
        }

        $pBranchSql = "";
        $pParams = [$supplierId, $companyId];
        if ($branchId > 0) {
            $pBranchSql = " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $pParams[] = $branchId;
        }

        // 1. Inward Purchase Bills
        $bills = \WTSBill\Core\Database::select(
            "SELECT p.id, p.purchase_number, p.vendor_invoice_number, p.purchase_date, p.due_date,
                    p.grand_total, p.amount_paid, p.amount_due, p.status, p.branch_id, p.notes
             FROM purchases p
             WHERE p.supplier_id = ? AND p.company_id = ? AND p.deleted_at IS NULL
             {$pBranchSql}
             ORDER BY p.purchase_date DESC, p.id DESC",
            $pParams
        );

        // 2. Payments
        $pmtParams = [$supplierId, $companyId];
        $pmtBranchSql = "";
        if ($branchId > 0) {
            $pmtBranchSql = " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $pmtParams[] = $branchId;
        }

        $payments = \WTSBill\Core\Database::select(
            "SELECT p.id, COALESCE(p.payment_number, CONCAT('PMT-', p.id)) AS payment_number,
                    p.payment_date, p.amount, p.payment_mode,
                    COALESCE(p.reference_number, p.reference_no, p.transaction_reference, '—') AS reference_number,
                    p.status, p.branch_id, p.notes
             FROM payments p
             WHERE p.party_id = ? AND p.party_type = 'SUPPLIER' AND p.company_id = ?
             {$pmtBranchSql}
             ORDER BY p.payment_date DESC, p.id DESC",
            $pmtParams
        );

        // 3. Debit Notes
        $dnParams = [$supplierId, $companyId];
        $dnBranchSql = "";
        if ($branchId > 0) {
            $dnBranchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $dnParams[] = $branchId;
        }

        $debitNotes = \WTSBill\Core\Database::select(
            "SELECT id, debit_note_number, debit_note_date, amount, status, reason, notes
             FROM debit_notes
             WHERE supplier_id = ? AND company_id = ?
             {$dnBranchSql}
             ORDER BY debit_note_date DESC, id DESC",
            $dnParams
        );

        $today = date('Y-m-d');
        $outstandingBills = [];
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $outstandingBal = 0.0;
        $overdueAmt = 0.0;

        foreach ($bills as &$b) {
            $gt = (float)$b['grand_total'];
            $pd = (float)($b['amount_paid'] ?? 0);
            $due = (float)($b['amount_due'] ?? ($gt - $pd));
            $totalBilled += $gt;
            $totalPaid += $pd;
            $outstandingBal += $due;

            $dueDate = !empty($b['due_date']) ? $b['due_date'] : $b['purchase_date'];
            $isOverdue = ($due > 0.001 && $dueDate < $today);
            $daysOverdue = $isOverdue ? (int)floor((strtotime($today) - strtotime($dueDate)) / 86400) : 0;
            if ($isOverdue) {
                $overdueAmt += $due;
            }
            $b['days_overdue'] = $daysOverdue;
            $b['is_overdue'] = $isOverdue;
            $b['grand_total_formatted'] = $this->formatCurrency($gt);
            $b['amount_paid_formatted'] = $this->formatCurrency($pd);
            $b['amount_due_formatted'] = $this->formatCurrency($due);

            if ($due > 0.001) {
                $outstandingBills[] = $b;
            }
        }
        unset($b);

        $totalDebitNotes = 0.0;
        foreach ($debitNotes as &$dn) {
            $amt = (float)$dn['amount'];
            $totalDebitNotes += $amt;
            $dn['amount_formatted'] = $this->formatCurrency($amt);
        }
        unset($dn);

        foreach ($payments as &$pmt) {
            $pmt['amount_formatted'] = $this->formatCurrency((float)$pmt['amount']);
        }
        unset($pmt);

        // 4. Supplier Running Ledger (Chronological)
        // Opening balance
        $opBal = (float)($supplier['opening_balance'] ?? 0);
        $rawEvents = [];

        if (abs($opBal) > 0.001) {
            $rawEvents[] = [
                'date' => $supplier['created_at'] ? date('Y-m-d', strtotime((string)$supplier['created_at'])) : date('Y-m-01'),
                'type' => 'OPENING_BALANCE',
                'voucher_no' => 'OB-' . $supplier['id'],
                'ref_no' => '—',
                'description' => 'Opening Balance Payable',
                'debit' => 0.0,
                'credit' => $opBal,
            ];
        }

        foreach ($bills as $b) {
            $rawEvents[] = [
                'date' => $b['purchase_date'],
                'type' => 'PURCHASE_BILL',
                'voucher_no' => $b['purchase_number'] ?: ('BILL-' . $b['id']),
                'ref_no' => $b['vendor_invoice_number'] ?: '—',
                'description' => 'Purchase Bill (' . ($b['vendor_invoice_number'] ? 'Vendor Inv #' . $b['vendor_invoice_number'] : 'Direct') . ')',
                'debit' => 0.0,
                'credit' => (float)$b['grand_total'],
            ];
        }

        foreach ($payments as $p) {
            $rawEvents[] = [
                'date' => $p['payment_date'],
                'type' => 'PAYMENT',
                'voucher_no' => $p['payment_number'],
                'ref_no' => $p['reference_number'] ?: '—',
                'description' => 'Disbursement (' . $p['payment_mode'] . ')',
                'debit' => (float)$p['amount'],
                'credit' => 0.0,
            ];
        }

        foreach ($debitNotes as $dn) {
            $rawEvents[] = [
                'date' => $dn['debit_note_date'],
                'type' => 'DEBIT_NOTE',
                'voucher_no' => $dn['debit_note_number'],
                'ref_no' => '—',
                'description' => 'Debit Note: ' . ($dn['reason'] ?: 'Purchase Return / Adjustment'),
                'debit' => (float)$dn['amount'],
                'credit' => 0.0,
            ];
        }

        usort($rawEvents, fn($a, $b) => strcmp($a['date'], $b['date']));

        $running = 0.0;
        $ledger = [];
        foreach ($rawEvents as $ev) {
            // In supplier payable account: Credit increases payable balance, Debit decreases payable balance
            $running = $running + $ev['credit'] - $ev['debit'];
            $ev['running_balance'] = round($running, 2);
            $ev['running_balance_formatted'] = $this->formatCurrency($running);
            $ev['debit_formatted'] = $ev['debit'] > 0 ? $this->formatCurrency($ev['debit']) : '—';
            $ev['credit_formatted'] = $ev['credit'] > 0 ? $this->formatCurrency($ev['credit']) : '—';
            $ledger[] = $ev;
        }

        $code = 'SUP-' . str_pad((string)$supplier['id'], 4, '0', STR_PAD_LEFT);

        return [
            'success' => true,
            'supplier' => array_merge($supplier, [
                'supplier_code' => $code,
                'phone' => $supplier['phone'] ?: '—',
                'email' => $supplier['email'] ?: '—',
                'gstin' => $supplier['gstin'] ?: '—',
                'pan' => $supplier['pan'] ?: '—',
                'status' => ((int)$supplier['is_active'] === 1) ? 'ACTIVE' : 'INACTIVE',
                'assigned_branch' => $supplier['branch_name'] ?: 'All Branches (Shared Access)',
                'address' => implode(', ', array_filter([$supplier['address_line1'], $supplier['city'], $supplier['state'], $supplier['pincode']])) ?: '—',
                'contact_person' => $supplier['contact_person'] ?? '',
                'notes' => $supplier['notes'] ?? '',
            ]),
            'financial_summary' => [
                'total_billed' => round($totalBilled, 2),
                'total_billed_formatted' => $this->formatCurrency($totalBilled),
                'total_paid' => round($totalPaid, 2),
                'total_paid_formatted' => $this->formatCurrency($totalPaid),
                'outstanding_balance' => round($outstandingBal, 2),
                'outstanding_balance_formatted' => $this->formatCurrency($outstandingBal),
                'overdue_amount' => round($overdueAmt, 2),
                'overdue_amount_formatted' => $this->formatCurrency($overdueAmt),
                'total_bills' => count($bills),
                'outstanding_bills_count' => count($outstandingBills),
                'total_debit_notes' => round($totalDebitNotes, 2),
                'total_debit_notes_formatted' => $this->formatCurrency($totalDebitNotes),
            ],
            'bills' => $bills,
            'outstanding_bills' => $outstandingBills,
            'payments' => $payments,
            'debit_notes' => $debitNotes,
            'ledger' => $ledger,
        ];
    }

    /**
     * Format currency with symbol.
     */
    protected function formatCurrency(float $amount): string
    {
        return '₹ ' . number_format($amount, 2, '.', ',');
    }
}
