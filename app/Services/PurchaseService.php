<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;

/**
 * Purchase Service
 * Handles Accounts Payable, bills querying, KPIs, payments recording,
 * double-entry GL journal posting, detail inspection, and exporting.
 */
class PurchaseService
{
    /**
     * Fetch paginated and filtered purchase bills along with summary KPIs.
     */
    public function getBills(int $companyId, int $branchId, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $supplierId = (int)($filters['supplier_id'] ?? 0);
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $fromDate = trim((string)($filters['from'] ?? ''));
        $toDate = trim((string)($filters['to'] ?? ''));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'id')));
        $dir = strtolower(trim((string)($filters['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = in_array((int)($filters['per_page'] ?? 25), [10, 25, 50, 100], true) ? (int)$filters['per_page'] : 25;
        $offset = ($page - 1) * $perPage;

        // Base where conditions
        $where = ["p.company_id = ?", "(p.branch_id = ? OR p.branch_id IS NULL)"];
        $params = [$companyId, $branchId];

        if ($search !== '') {
            $where[] = "(p.purchase_number LIKE ? OR p.vendor_invoice_number LIKE ? OR COALESCE(s.name, p.supplier_name, '') LIKE ?)";
            $wild = '%' . $search . '%';
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
        }

        if ($supplierId > 0) {
            $where[] = "p.supplier_id = ?";
            $params[] = $supplierId;
        }

        if ($fromDate !== '') {
            $where[] = "p.purchase_date >= ?";
            $params[] = $fromDate;
        }

        if ($toDate !== '') {
            $where[] = "p.purchase_date <= ?";
            $params[] = $toDate;
        }

        // Status filter:
        // OVERDUE is computed dynamically: due_date < CURDATE() AND amount_due > 0.001
        if ($status === 'OVERDUE') {
            $where[] = "(p.due_date < CURDATE() AND COALESCE(p.amount_due, (p.grand_total - p.amount_paid)) > 0.001)";
        } elseif ($status === 'UNPAID') {
            $where[] = "(p.status = 'UNPAID' AND (p.due_date >= CURDATE() OR p.due_date IS NULL) AND COALESCE(p.amount_due, (p.grand_total - p.amount_paid)) > 0.001)";
        } elseif ($status === 'PARTIALLY PAID' || $status === 'PARTIALLY_PAID') {
            $where[] = "p.status = 'PARTIALLY_PAID'";
        } elseif ($status === 'PAID') {
            $where[] = "p.status = 'PAID'";
        }

        $whereClause = implode(' AND ', $where);

        // Allowed sort columns
        $sortMap = [
            'id' => 'p.id',
            'bill_number' => 'p.purchase_number',
            'supplier' => 'supplier_name',
            'vendor_invoice' => 'p.vendor_invoice_number',
            'bill_date' => 'p.purchase_date',
            'due_date' => 'p.due_date',
            'total' => 'p.grand_total',
            'paid' => 'p.amount_paid',
            'balance' => 'balance_due',
            'status' => 'computed_status'
        ];
        $orderBy = $sortMap[$sort] ?? 'p.id';

        // Count query
        $countRow = Database::selectOne(
            "SELECT COUNT(*) as total_count,
                    COALESCE(SUM(p.grand_total), 0) as total_amount,
                    COALESCE(SUM(p.amount_paid), 0) as total_paid,
                    COALESCE(SUM(COALESCE(p.amount_due, p.grand_total - p.amount_paid)), 0) as total_balance
             FROM purchases p
             LEFT JOIN suppliers s ON p.supplier_id = s.id
             WHERE {$whereClause}",
            $params
        );
        $totalRows = (int)($countRow['total_count'] ?? 0);

        // Main select query
        $sql = "SELECT p.id, p.purchase_number, p.vendor_invoice_number,
                       p.supplier_id,
                       COALESCE(s.name, p.supplier_name, 'Direct Vendor') as supplier_name,
                       p.purchase_date, p.due_date,
                       p.sub_total, p.cgst_amount, p.sgst_amount, p.igst_amount, p.total_tax,
                       p.grand_total,
                       COALESCE(p.amount_paid, 0) as amount_paid,
                       COALESCE(p.amount_due, p.grand_total - COALESCE(p.amount_paid, 0)) as balance_due,
                       p.status as raw_status,
                       CASE 
                           WHEN (p.due_date < CURDATE() AND COALESCE(p.amount_due, p.grand_total - COALESCE(p.amount_paid, 0)) > 0.001) THEN 'OVERDUE'
                           WHEN COALESCE(p.amount_paid, 0) >= p.grand_total AND p.grand_total > 0 THEN 'PAID'
                           WHEN COALESCE(p.amount_paid, 0) > 0.001 THEN 'PARTIALLY PAID'
                           ELSE 'UNPAID'
                       END as computed_status,
                       DATEDIFF(p.due_date, CURDATE()) as days_until_due
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE {$whereClause}
                ORDER BY {$orderBy} {$dir}
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::select($sql, $params);

        // Format rows
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $days = (int)($r['days_until_due'] ?? 0);
            $bal = (float)$r['balance_due'];

            if ($bal <= 0.001) {
                $r['due_in_text'] = 'Paid';
                $r['due_in_class'] = 'text-muted';
            } elseif ($days < 0) {
                $absDays = abs($days);
                $r['due_in_text'] = "{$absDays} " . ($absDays === 1 ? 'day' : 'days') . " overdue";
                $r['due_in_class'] = 'text-danger font-semibold';
            } elseif ($days === 0) {
                $r['due_in_text'] = 'Due today';
                $r['due_in_class'] = 'text-warning font-semibold';
            } else {
                $r['due_in_text'] = "in {$days} " . ($days === 1 ? 'day' : 'days');
                $r['due_in_class'] = 'text-muted';
            }

            $r['can_delete'] = ((float)$r['amount_paid'] <= 0.001);
        }
        unset($r);

        // Fetch Global KPIs for this Company + Branch
        $kpis = $this->calculateKpis($companyId, $branchId);

        return [
            'rows' => $rows,
            'kpis' => $kpis,
            'totals' => [
                'total_amount' => (float)($countRow['total_amount'] ?? 0),
                'total_paid' => (float)($countRow['total_paid'] ?? 0),
                'total_balance' => (float)($countRow['total_balance'] ?? 0),
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalRows,
                'total_pages' => (int)ceil($totalRows / max(1, $perPage)),
                'from' => $totalRows > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalRows)
            ]
        ];
    }

    /**
     * Calculate 4 KPI Cards for the purchase bills screen:
     * 1. Total Payable (all unpaid + partial balance)
     * 2. Overdue (amount + count)
     * 3. Due in next 7 days (amount + count)
     * 4. Paid this month (amount)
     */
    public function calculateKpis(int $companyId, int $branchId): array
    {
        $today = date('Y-m-d');
        $in7Days = date('Y-m-d', strtotime('+7 days'));
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        // 1. Total Payable: SUM(balance) for all bills with balance > 0
        $payableRow = Database::selectOne(
            "SELECT COALESCE(SUM(COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0))), 0) as total_payable,
                    COUNT(*) as count
             FROM purchases
             WHERE company_id = ? AND (branch_id = ? OR branch_id IS NULL)
               AND COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0)) > 0.001",
            [$companyId, $branchId]
        );

        // 2. Overdue: due_date < today and balance > 0
        $overdueRow = Database::selectOne(
            "SELECT COALESCE(SUM(COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0))), 0) as overdue_amount,
                    COUNT(*) as count
             FROM purchases
             WHERE company_id = ? AND (branch_id = ? OR branch_id IS NULL)
               AND due_date < ?
               AND COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0)) > 0.001",
            [$companyId, $branchId, $today]
        );

        // 3. Due in next 7 days: due_date BETWEEN today AND in7Days and balance > 0
        $due7Row = Database::selectOne(
            "SELECT COALESCE(SUM(COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0))), 0) as due7_amount,
                    COUNT(*) as count
             FROM purchases
             WHERE company_id = ? AND (branch_id = ? OR branch_id IS NULL)
               AND due_date >= ? AND due_date <= ?
               AND COALESCE(amount_due, grand_total - COALESCE(amount_paid, 0)) > 0.001",
            [$companyId, $branchId, $today, $in7Days]
        );

        // 4. Paid this month: payments made between monthStart and monthEnd for supplier bills
        $paidRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) as paid_month
             FROM payments
             WHERE company_id = ? AND (branch_id = ? OR branch_id IS NULL)
               AND party_type = 'SUPPLIER'
               AND payment_date >= ? AND payment_date <= ?
               AND status = 'COMPLETED'",
            [$companyId, $branchId, $monthStart, $monthEnd]
        );

        return [
            'total_payable' => (float)($payableRow['total_payable'] ?? 0),
            'payable_count' => (int)($payableRow['count'] ?? 0),
            'overdue_amount' => (float)($overdueRow['overdue_amount'] ?? 0),
            'overdue_count' => (int)($overdueRow['count'] ?? 0),
            'due_7_days_amount' => (float)($due7Row['due7_amount'] ?? 0),
            'due_7_days_count' => (int)($due7Row['count'] ?? 0),
            'paid_this_month' => (float)($paidRow['paid_month'] ?? 0)
        ];
    }

    /**
     * Get full details of a single purchase bill for the slide-over drawer.
     */
    public function getBillDetails(int $companyId, int $billId): ?array
    {
        $bill = Database::selectOne(
            "SELECT p.*,
                    COALESCE(s.name, p.supplier_name, 'Direct Vendor') as supplier_name,
                    s.company_name as supplier_company,
                    COALESCE(s.gstin, p.supplier_gstin) as gstin,
                    s.phone as supplier_phone,
                    s.email as supplier_email,
                    COALESCE(p.amount_due, p.grand_total - COALESCE(p.amount_paid, 0)) as balance_due,
                    CASE 
                        WHEN (p.due_date < CURDATE() AND COALESCE(p.amount_due, p.grand_total - COALESCE(p.amount_paid, 0)) > 0.001) THEN 'OVERDUE'
                        WHEN COALESCE(p.amount_paid, 0) >= p.grand_total AND p.grand_total > 0 THEN 'PAID'
                        WHEN COALESCE(p.amount_paid, 0) > 0.001 THEN 'PARTIALLY PAID'
                        ELSE 'UNPAID'
                    END as computed_status
             FROM purchases p
             LEFT JOIN suppliers s ON p.supplier_id = s.id
             WHERE p.id = ? AND p.company_id = ?",
            [$billId, $companyId]
        );

        if (!$bill) {
            return null;
        }

        // Line items
        $items = Database::select(
            "SELECT id, product_id, item_name, hsn_sac, quantity, unit, unit_price,
                    taxable_value, gst_rate, tax_amount, total_amount
             FROM purchase_items
             WHERE purchase_id = ? AND company_id = ?
             ORDER BY id ASC",
            [$billId, $companyId]
        );

        // Payment history via payment_allocations
        $payments = Database::select(
            "SELECT pa.id as allocation_id, pa.allocated_amount, pa.allocation_date,
                    py.payment_number, py.payment_mode, py.reference_no, py.notes,
                    ba.bank_name, ba.account_name, ba.account_number
             FROM payment_allocations pa
             JOIN payments py ON pa.payment_id = py.id
             LEFT JOIN bank_accounts ba ON py.bank_account_id = ba.id
             WHERE pa.document_type = 'PURCHASE' AND pa.document_id = ? AND pa.company_id = ?
             ORDER BY pa.id DESC",
            [$billId, $companyId]
        );

        $bill['items'] = $items;
        $bill['payments'] = $payments;
        $bill['can_delete'] = ((float)$bill['amount_paid'] <= 0.001);

        return $bill;
    }

    /**
     * Record a payment against a purchase bill.
     * Updates bill amount_paid & amount_due, sets status (UNPAID -> PARTIALLY_PAID -> PAID),
     * records payment & allocation, and posts double-entry GL journal (Dr Accounts Payable, Cr Bank/Cash).
     */
    public function recordPayment(int $companyId, int $branchId, int $userId, string $userName, int $billId, array $data): array
    {
        $bill = Database::selectOne(
            "SELECT * FROM purchases WHERE id = ? AND company_id = ?",
            [$billId, $companyId]
        );

        if (!$bill) {
            throw new ValidationException(['bill_id' => 'Purchase bill not found.'], 'Purchase bill not found.');
        }

        $balanceDue = (float)($bill['amount_due'] ?? ($bill['grand_total'] - $bill['amount_paid']));
        if ($balanceDue <= 0.001) {
            throw new ValidationException(['amount' => 'This purchase bill is already fully paid.'], 'This purchase bill is already fully paid.');
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new ValidationException(['amount' => 'Payment amount must be greater than zero.'], 'Payment amount must be greater than zero.');
        }

        if ($amount > ($balanceDue + 0.01)) {
            throw new ValidationException(['amount' => "Payment amount (₹{$amount}) cannot exceed the balance due (₹{$balanceDue})."], "Payment amount cannot exceed the balance due.");
        }

        $paymentDate = trim((string)($data['payment_date'] ?? date('Y-m-d')));
        $mode = strtoupper(trim((string)($data['payment_mode'] ?? 'BANK')));
        $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
        $referenceNo = trim((string)($data['reference_no'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));

        return Database::transaction(function () use (
            $companyId, $branchId, $userId, $userName, $bill, $billId,
            $amount, $balanceDue, $paymentDate, $mode, $bankAccountId, $referenceNo, $notes
        ) {
            $paymentNumber = 'PMT-VND-' . date('Ymd') . '-' . rand(1000, 9999);

            // 1. Insert Payment Record
            $paymentId = Database::insert('payments', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'party_type' => 'SUPPLIER',
                'party_id' => $bill['supplier_id'],
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_mode' => $mode,
                'reference_no' => $referenceNo,
                'bank_account_id' => $bankAccountId,
                'notes' => $notes ?: "Disbursement for Purchase Bill {$bill['purchase_number']}",
                'status' => 'COMPLETED',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 2. Insert Payment Allocation
            Database::insert('payment_allocations', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_id' => $paymentId,
                'document_type' => 'PURCHASE',
                'document_id' => $billId,
                'allocated_amount' => $amount,
                'allocation_date' => $paymentDate,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 3. Update Bill status & balances
            $newPaid = round((float)$bill['amount_paid'] + $amount, 2);
            $newDue = max(0, round((float)$bill['grand_total'] - $newPaid, 2));

            $newStatus = 'UNPAID';
            if ($newDue <= 0.001) {
                $newStatus = 'PAID';
            } elseif ($newPaid > 0) {
                $newStatus = 'PARTIALLY_PAID';
            }

            Database::update(
                'purchases',
                [
                    'amount_paid' => $newPaid,
                    'amount_due' => $newDue,
                    'status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ? AND company_id = ?',
                [$billId, $companyId]
            );

            // 4. Update Supplier Payable Balance
            if (!empty($bill['supplier_id'])) {
                $sup = Database::selectOne("SELECT current_balance FROM suppliers WHERE id = ?", [$bill['supplier_id']]);
                if ($sup) {
                    $newSupBal = max(0, round((float)$sup['current_balance'] - $amount, 2));
                    Database::update('suppliers', ['current_balance' => $newSupBal], 'id = ?', [$bill['supplier_id']]);
                }
            }

            // 5. Update Bank Account balance if applicable
            if ($bankAccountId) {
                $ba = Database::selectOne("SELECT current_balance FROM bank_accounts WHERE id = ?", [$bankAccountId]);
                if ($ba) {
                    $newBankBal = round((float)$ba['current_balance'] - $amount, 2);
                    Database::update('bank_accounts', ['current_balance' => $newBankBal], 'id = ?', [$bankAccountId]);
                }
            }

            // 6. Post Double-Entry Journal Entry
            // Dr: Accounts Payable (Creditors) [200100]
            // Cr: Bank or Cash in Hand [100200 / 100100]
            $this->postPaymentJournal($companyId, $branchId, $userId, $bill, $paymentNumber, $paymentDate, $amount, $mode, $bankAccountId);

            return [
                'success' => true,
                'message' => "Payment of ₹" . number_format($amount, 2) . " recorded successfully.",
                'payment_number' => $paymentNumber,
                'bill_id' => $billId,
                'amount_paid' => $newPaid,
                'balance_due' => $newDue,
                'status' => $newStatus,
            ];
        });
    }

    /**
     * Post balanced General Ledger Journal Entry for Vendor Payment.
     */
    protected function postPaymentJournal(
        int $companyId,
        int $branchId,
        int $userId,
        array $bill,
        string $paymentNumber,
        string $date,
        float $amount,
        string $mode,
        ?int $bankAccountId
    ): void {
        // Resolve Accounts Payable Account (Code 200100 or LIABILITIES)
        $apAcc = Database::selectOne(
            "SELECT id FROM chart_of_accounts WHERE company_id = ? AND (account_code = '200100' OR account_name LIKE '%Creditor%' OR account_name LIKE '%Payable%') LIMIT 1",
            [$companyId]
        );
        $apAccountId = $apAcc ? (int)$apAcc['id'] : 1;

        // Resolve Credit Account (Bank or Cash)
        $creditCode = ($mode === 'CASH') ? '100100' : '100200';
        $bankAcc = Database::selectOne(
            "SELECT id FROM chart_of_accounts WHERE company_id = ? AND account_code = ? LIMIT 1",
            [$companyId, $creditCode]
        );
        if (!$bankAcc) {
            $bankAcc = Database::selectOne(
                "SELECT id FROM chart_of_accounts WHERE company_id = ? AND (account_name LIKE '%Bank%' OR account_name LIKE '%Cash%') LIMIT 1",
                [$companyId]
            );
        }
        $creditAccountId = $bankAcc ? (int)$bankAcc['id'] : 2;

        $journalNumber = 'JRN-' . date('Ymd') . '-' . rand(1000, 9999);
        $narration = "Payment against Purchase Bill {$bill['purchase_number']} (Ref: {$paymentNumber})";

        $journalId = Database::insert('journal_entries', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'entry_number' => $journalNumber,
            'journal_number' => $journalNumber,
            'entry_date' => $date,
            'reference_type' => 'PURCHASE_PAYMENT',
            'reference_id' => $bill['id'],
            'narration' => $narration,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'status' => 'POSTED',
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Dr: Accounts Payable
        Database::insert('journal_entry_lines', [
            'company_id' => $companyId,
            'journal_entry_id' => $journalId,
            'account_id' => $apAccountId,
            'debit_amount' => $amount,
            'credit_amount' => 0.00,
            'description' => "Debit Accounts Payable for {$bill['purchase_number']}",
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Cr: Bank or Cash
        Database::insert('journal_entry_lines', [
            'company_id' => $companyId,
            'journal_entry_id' => $journalId,
            'account_id' => $creditAccountId,
            'debit_amount' => 0.00,
            'credit_amount' => $amount,
            'description' => "Credit {$mode} for payment {$paymentNumber}",
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a purchase bill. Blocked if payment has already been recorded.
     */
    public function deleteBill(int $companyId, int $billId): bool
    {
        $bill = Database::selectOne("SELECT amount_paid FROM purchases WHERE id = ? AND company_id = ?", [$billId, $companyId]);
        if (!$bill) {
            throw new ValidationException(['id' => 'Purchase bill not found.'], 'Purchase bill not found.');
        }

        if ((float)($bill['amount_paid'] ?? 0) > 0.001) {
            throw new ValidationException(['id' => 'Cannot delete purchase bill with existing recorded payments.'], 'Cannot delete purchase bill with existing recorded payments.');
        }

        return Database::transaction(function () use ($companyId, $billId) {
            Database::execute("DELETE FROM purchase_items WHERE purchase_id = ? AND company_id = ?", [$billId, $companyId]);
            Database::execute("DELETE FROM purchases WHERE id = ? AND company_id = ?", [$billId, $companyId]);
            return true;
        });
    }

    /**
     * Duplicate a purchase bill into a new draft/unpaid bill.
     */
    public function duplicateBill(int $companyId, int $branchId, int $billId): int
    {
        $source = Database::selectOne("SELECT * FROM purchases WHERE id = ? AND company_id = ?", [$billId, $companyId]);
        if (!$source) {
            throw new ValidationException(['id' => 'Original purchase bill not found.'], 'Original purchase bill not found.');
        }

        $newPurchaseNumber = 'PB-' . date('Y') . '-' . rand(1000, 9999);
        $newVendorInvoice = ($source['vendor_invoice_number'] ?: 'INV') . '-COPY';

        return Database::transaction(function () use ($companyId, $branchId, $source, $billId, $newPurchaseNumber, $newVendorInvoice) {
            $newBillId = Database::insert('purchases', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $source['supplier_id'],
                'supplier_name' => $source['supplier_name'],
                'supplier_gstin' => $source['supplier_gstin'],
                'purchase_number' => $newPurchaseNumber,
                'vendor_invoice_number' => $newVendorInvoice,
                'purchase_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'sub_total' => $source['sub_total'],
                'cgst_amount' => $source['cgst_amount'],
                'sgst_amount' => $source['sgst_amount'],
                'igst_amount' => $source['igst_amount'],
                'total_tax' => $source['total_tax'],
                'round_off' => $source['round_off'],
                'grand_total' => $source['grand_total'],
                'amount_paid' => 0.0,
                'amount_due' => $source['grand_total'],
                'status' => 'UNPAID',
                'notes' => 'Duplicated from ' . $source['purchase_number'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Copy items if any
            $items = Database::select("SELECT * FROM purchase_items WHERE purchase_id = ? AND company_id = ?", [$billId, $companyId]);
            foreach ($items as $item) {
                Database::insert('purchase_items', [
                    'company_id' => $companyId,
                    'purchase_id' => $newBillId,
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'hsn_sac' => $item['hsn_sac'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'taxable_value' => $item['taxable_value'],
                    'gst_rate' => $item['gst_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'total_amount' => $item['total_amount'],
                ]);
            }

            return (int)$newBillId;
        });
    }

    /**
     * Export bills to CSV or HTML table for Excel/PDF conversion.
     */
    public function exportBills(int $companyId, int $branchId, array $filters, string $format = 'csv'): array
    {
        $filters['page'] = 1;
        $filters['per_page'] = 1000;
        $result = $this->getBills($companyId, $branchId, $filters);
        $rows = $result['rows'];

        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            fputcsv($output, ['Bill #', 'Supplier / Vendor', 'Vendor Invoice #', 'Bill Date', 'Due Date', 'Total Amount', 'Paid Amount', 'Balance Due', 'Payment Status']);
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['purchase_number'],
                    $r['supplier_name'],
                    $r['vendor_invoice_number'],
                    $r['purchase_date'],
                    $r['due_date'],
                    number_format((float)$r['grand_total'], 2, '.', ''),
                    number_format((float)$r['amount_paid'], 2, '.', ''),
                    number_format((float)$r['balance_due'], 2, '.', ''),
                    $r['computed_status'],
                ]);
            }
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            return [
                'content' => $content,
                'content_type' => 'text/csv; charset=UTF-8',
                'filename' => 'purchase_bills_' . date('Ymd_His') . '.csv'
            ];
        }

        // HTML Export for Excel / PDF
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Purchase Bills</title><style>
            body { font-family: sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
            th { background: #EAF1FF; color: #1D4ED8; font-weight: bold; }
            .num { text-align: right; }
        </style></head><body>';
        $html .= '<h2>WTSBill ERP - Purchase Bills & Accounts Payable</h2>';
        $html .= '<p>Generated on ' . date('d-m-Y H:i') . '</p>';
        $html .= '<table><thead><tr><th>Bill #</th><th>Supplier</th><th>Vendor Invoice #</th><th>Bill Date</th><th>Due Date</th><th>Total Amount</th><th>Paid Amount</th><th>Balance Due</th><th>Status</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>' .
                '<td>' . htmlspecialchars((string)($r['purchase_number'] ?? '')) . '</td>' .
                '<td>' . htmlspecialchars((string)($r['supplier_name'] ?? '')) . '</td>' .
                '<td>' . htmlspecialchars((string)($r['vendor_invoice_number'] ?? '')) . '</td>' .
                '<td>' . htmlspecialchars((string)($r['purchase_date'] ?? '')) . '</td>' .
                '<td>' . htmlspecialchars((string)($r['due_date'] ?? '')) . '</td>' .
                '<td class="num">₹' . number_format((float)$r['grand_total'], 2) . '</td>' .
                '<td class="num">₹' . number_format((float)$r['amount_paid'], 2) . '</td>' .
                '<td class="num">₹' . number_format((float)$r['balance_due'], 2) . '</td>' .
                '<td>' . htmlspecialchars((string)($r['computed_status'] ?? '')) . '</td>' .
                '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        return [
            'content' => $html,
            'content_type' => ($format === 'excel') ? 'application/vnd.ms-excel; charset=UTF-8' : 'text/html; charset=UTF-8',
            'filename' => 'purchase_bills_' . date('Ymd_His') . ($format === 'excel' ? '.xls' : '.html')
        ];
    }
}
