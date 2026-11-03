<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Services\AuditLogger;

/**
 * Payment & Collections Business Service.
 * Handles customer partial payments, multiple installment tracking,
 * allocation against sales invoices, automated payment status transitions,
 * double-entry GL journal posting, and audit trails.
 */
class PaymentService
{
    /**
     * Record a payment installment against a sales tax invoice.
     *
     * Validates:
     * - Invoice eligibility (must not be CANCELLED or deleted)
     * - Positive payment amount (> 0)
     * - Payment amount does not exceed current outstanding balance
     *
     * Operations in atomic transaction:
     * 1. Generates sequential receipt voucher number (RCP-YYYY-XXXX)
     * 2. Inserts payment record into `payments` table
     * 3. Inserts document allocation into `payment_allocations` table
     * 4. Updates invoice `amount_paid`, `amount_due`, `status`, and `payment_status`
     * 5. Decreases customer Accounts Receivable balance (`customers.current_balance`)
     * 6. Increases bank account balance if bank account specified
     * 7. Posts double-entry General Ledger journal entry (Dr Bank/Cash, Cr Accounts Receivable)
     * 8. Records comprehensive security audit log
     *
     * @param int $companyId
     * @param int $branchId
     * @param int $userId
     * @param string $userName
     * @param int $invoiceId
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function recordInvoicePayment(
        int $companyId,
        int $branchId,
        int $userId,
        string $userName,
        int $invoiceId,
        array $data
    ): array {
        if ($invoiceId <= 0) {
            throw new ValidationException(['invoice_id' => 'Valid invoice ID is required.'], 'Valid invoice ID is required.');
        }

        // 1. Fetch and validate invoice
        $invoice = Database::selectOne(
            "SELECT i.*, COALESCE(c.name, i.customer_name) AS customer_name, c.current_balance AS customer_current_balance 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL",
            [$invoiceId, $companyId]
        );

        if (!$invoice) {
            throw new ValidationException(['invoice_id' => 'Invoice not found or unauthorized.'], 'Invoice not found or unauthorized.');
        }

        $invStatus = strtoupper(trim((string)($invoice['status'] ?? '')));
        if ($invStatus === 'CANCELLED') {
            throw new ValidationException(['status' => 'Cannot record payment on a cancelled invoice.'], 'Cannot record payment on a cancelled invoice.');
        }

        $grandTotal = round((float)$invoice['grand_total'], 2);

        // 2. Derive existing paid amount from valid payment allocations
        $allocRow = Database::selectOne(
            "SELECT COALESCE(SUM(pa.allocated_amount), 0) AS total_allocated 
             FROM payment_allocations pa 
             JOIN payments py ON pa.payment_id = py.id 
             WHERE pa.document_type = 'INVOICE' 
               AND pa.document_id = ? 
               AND pa.company_id = ? 
               AND py.deleted_at IS NULL 
               AND py.status IN ('COMPLETED', 'CONFIRMED', 'VERIFIED')",
            [$invoiceId, $companyId]
        );

        $totalAllocatedBefore = round((float)($allocRow['total_allocated'] ?? (float)$invoice['amount_paid']), 2);
        $outstandingBefore = max(0.00, round($grandTotal - $totalAllocatedBefore, 2));

        if ($outstandingBefore <= 0.001) {
            throw new ValidationException(['amount' => 'This invoice is already fully paid (Outstanding balance is ₹0.00).'], 'This invoice is already fully paid.');
        }

        // 3. Validate payment amount
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0.00) {
            throw new ValidationException(['amount' => 'Payment amount must be greater than zero.'], 'Payment amount must be greater than zero.');
        }

        if ($amount > ($outstandingBefore + 0.01)) {
            $formattedOut = number_format($outstandingBefore, 2);
            $formattedAmt = number_format($amount, 2);
            throw new ValidationException(
                ['amount' => "Payment amount (₹{$formattedAmt}) cannot exceed the outstanding balance (₹{$formattedOut})."],
                "Payment amount cannot exceed the outstanding balance."
            );
        }

        // 4. Sanitize metadata fields & enforce duplicate reference check
        $paymentDate = trim((string)($data['payment_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $paymentDate = date('Y-m-d');
        }

        $paymentMode = trim((string)($data['payment_mode'] ?? 'Bank Transfer'));
        $validModes = ['Bank Transfer', 'NEFT/RTGS', 'UPI', 'Cash', 'Cheque', 'Card'];
        if (!in_array($paymentMode, $validModes, true)) {
            $paymentMode = 'Bank Transfer';
        }

        $referenceNo = trim((string)($data['reference_no'] ?? ($data['reference_number'] ?? '')));
        if (!empty($referenceNo)) {
            $dup = Database::selectOne(
                "SELECT id, payment_number FROM payments 
                 WHERE company_id = ? 
                   AND (reference_no = ? OR reference_number = ? OR transaction_reference = ?) 
                   AND deleted_at IS NULL 
                   AND status != 'CANCELLED'",
                [$companyId, $referenceNo, $referenceNo, $referenceNo]
            );
            if ($dup) {
                throw new ValidationException(
                    ['reference_no' => "Duplicate payment transaction: Reference '{$referenceNo}' has already been recorded under {$dup['payment_number']}."],
                    "Duplicate payment transaction: Reference '{$referenceNo}' has already been recorded."
                );
            }
        }
        $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
        $notes = trim((string)($data['notes'] ?? ''));

        // 5. Execute atomic transaction
        return Database::transaction(function () use (
            $companyId, $branchId, $userId, $userName, $invoice, $invoiceId,
            $grandTotal, $totalAllocatedBefore, $outstandingBefore, $amount,
            $paymentDate, $paymentMode, $referenceNo, $bankAccountId, $notes
        ) {
            $year = date('Y');
            $countRow = Database::selectOne(
                "SELECT COUNT(*) AS total FROM payments WHERE company_id = ? AND payment_type = 'RECEIPT'",
                [$companyId]
            );
            $nextSeq = (int)($countRow['total'] ?? 0) + 1;
            $receiptNumber = sprintf('RCP-%s-%04d', $year, $nextSeq);

            // A. Insert into payments
            $paymentId = (int)Database::insert('payments', [
                'company_id' => $companyId,
                'branch_id' => $invoice['branch_id'] ?: $branchId,
                'payment_number' => $receiptNumber,
                'receipt_number' => $receiptNumber,
                'party_type' => 'CUSTOMER',
                'party_id' => $invoice['customer_id'],
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'allocated_amount' => $amount,
                'unallocated_amount' => 0.00,
                'payment_mode' => $paymentMode,
                'reference_no' => $referenceNo ?: null,
                'reference_number' => $referenceNo ?: null,
                'transaction_reference' => $referenceNo ?: null,
                'payment_type' => 'RECEIPT',
                'currency' => 'INR',
                'status' => 'COMPLETED',
                'bank_account_id' => $bankAccountId,
                'notes' => $notes ?: "Collection for Tax Invoice {$invoice['invoice_number']}",
                'created_by' => $userName ?: 'System',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // B. Insert into payment_allocations
            Database::insert('payment_allocations', [
                'company_id' => $companyId,
                'branch_id' => $invoice['branch_id'] ?: $branchId,
                'payment_id' => $paymentId,
                'document_type' => 'INVOICE',
                'document_id' => $invoiceId,
                'allocated_amount' => $amount,
                'allocation_date' => $paymentDate,
                'notes' => $notes ?: "Installment allocation for Invoice {$invoice['invoice_number']}",
                'created_by' => $userName ?: 'System',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // C. Update invoice amount_paid, amount_due, status & payment_status
            $newTotalPaid = round($totalAllocatedBefore + $amount, 2);
            $newOutstanding = max(0.00, round($grandTotal - $newTotalPaid, 2));

            $newStatus = 'UNPAID';
            $newPaymentStatus = 'UNPAID';

            if ($newOutstanding <= 0.001) {
                $newStatus = 'PAID';
                $newPaymentStatus = 'PAID';
            } elseif ($newTotalPaid > 0.001) {
                $newStatus = 'PARTIAL';
                $newPaymentStatus = 'PARTIALLY_PAID';
            }

            Database::update(
                'invoices',
                [
                    'amount_paid' => $newTotalPaid,
                    'amount_due' => $newOutstanding,
                    'status' => $newStatus,
                    'payment_status' => $newPaymentStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$invoiceId, $companyId]
            );

            // D. Reduce Customer Accounts Receivable Current Balance
            if (!empty($invoice['customer_id'])) {
                $cust = Database::selectOne(
                    "SELECT current_balance FROM customers WHERE id = ?",
                    [$invoice['customer_id']]
                );
                if ($cust) {
                    $newCustBal = max(0.00, round((float)$cust['current_balance'] - $amount, 2));
                    Database::update(
                        'customers',
                        ['current_balance' => $newCustBal, 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ? AND company_id = ?',
                        [$invoice['customer_id'], $companyId]
                    );
                }
            }

            // E. Increase Bank Account Balance if specified
            if ($bankAccountId) {
                $ba = Database::selectOne(
                    "SELECT current_balance FROM bank_accounts WHERE id = ? AND company_id = ?",
                    [$bankAccountId, $companyId]
                );
                if ($ba) {
                    $newBankBal = round((float)$ba['current_balance'] + $amount, 2);
                    Database::update(
                        'bank_accounts',
                        ['current_balance' => $newBankBal, 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ? AND company_id = ?',
                        [$bankAccountId, $companyId]
                    );
                }
            }

            // F. Post Double-Entry GL Journal Entry (Dr Bank/Cash, Cr Accounts Receivable)
            $this->postInvoiceReceiptJournal(
                $companyId,
                (int)($invoice['branch_id'] ?: $branchId),
                $userId,
                $userName,
                $invoice,
                $receiptNumber,
                $paymentDate,
                $amount,
                $paymentMode,
                $bankAccountId
            );

            // G. Security Audit Log
            AuditLogger::log(
                action: 'payment',
                module: 'sales',
                entityType: 'INVOICE',
                entityId: $invoiceId,
                entityLabel: (string)$invoice['invoice_number'],
                oldValues: [
                    'amount_paid' => $totalAllocatedBefore,
                    'amount_due' => $outstandingBefore,
                    'status' => $invoice['status'] ?? 'UNPAID',
                ],
                newValues: [
                    'amount_paid' => $newTotalPaid,
                    'amount_due' => $newOutstanding,
                    'status' => $newStatus,
                    'payment_amount' => $amount,
                    'receipt_number' => $receiptNumber,
                    'payment_mode' => $paymentMode,
                ],
                status: 'success',
                notes: "Recorded payment installment {$receiptNumber} of ₹" . number_format($amount, 2) .
                       " for Invoice {$invoice['invoice_number']}. Remaining balance: ₹" . number_format($newOutstanding, 2)
            );

            return [
                'success' => true,
                'message' => "Payment of ₹" . number_format($amount, 2) . " recorded successfully ({$receiptNumber}).",
                'receipt_number' => $receiptNumber,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'grand_total' => $grandTotal,
                'amount_paid' => $newTotalPaid,
                'amount_due' => $newOutstanding,
                'status' => $newStatus,
                'payment_status' => $newPaymentStatus,
            ];
        });
    }

    /**
     * Record an unverified UPI payment instruction awaiting administrative verification.
     * Does NOT reduce invoice outstanding balance or mark invoice as Paid until verified.
     *
     * @param int $companyId
     * @param int $branchId
     * @param int $userId
     * @param string $userName
     * @param int $invoiceId
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function recordPendingUpiPayment(
        int $companyId,
        int $branchId,
        int $userId,
        string $userName,
        int $invoiceId,
        array $data
    ): array {
        if ($invoiceId <= 0) {
            throw new ValidationException(['invoice_id' => 'Valid invoice ID is required.'], 'Valid invoice ID is required.');
        }

        $invoice = Database::selectOne(
            "SELECT i.*, COALESCE(c.name, i.customer_name) AS customer_name 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL",
            [$invoiceId, $companyId]
        );

        if (!$invoice) {
            throw new ValidationException(['invoice_id' => 'Invoice not found.'], 'Invoice not found.');
        }

        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0.00) {
            throw new ValidationException(['amount' => 'Payment amount must be greater than zero.'], 'Payment amount must be greater than zero.');
        }

        $referenceNo = trim((string)($data['reference_no'] ?? ($data['reference_number'] ?? ($data['transaction_reference'] ?? ''))));
        if (!empty($referenceNo)) {
            $existingRef = Database::selectOne(
                "SELECT id, payment_number FROM payments 
                 WHERE company_id = ? 
                   AND (reference_no = ? OR reference_number = ? OR transaction_reference = ?) 
                   AND deleted_at IS NULL 
                   AND status != 'CANCELLED'",
                [$companyId, $referenceNo, $referenceNo, $referenceNo]
            );
            if ($existingRef) {
                throw new ValidationException(
                    ['reference_no' => "Duplicate payment transaction: Reference '{$referenceNo}' has already been recorded under {$existingRef['payment_number']}."],
                    "Duplicate payment transaction: Reference '{$referenceNo}' has already been recorded."
                );
            }
        }

        $year = date('Y');
        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM payments WHERE company_id = ? AND payment_type = 'RECEIPT'",
            [$companyId]
        );
        $nextSeq = (int)($countRow['total'] ?? 0) + 1;
        $receiptNumber = sprintf('RCP-%s-%04d', $year, $nextSeq);

        $paymentId = (int)Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $invoice['branch_id'] ?: $branchId,
            'payment_number' => $receiptNumber,
            'receipt_number' => $receiptNumber,
            'party_type' => 'CUSTOMER',
            'party_id' => $invoice['customer_id'],
            'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
            'amount' => $amount,
            'allocated_amount' => 0.00,
            'unallocated_amount' => $amount,
            'payment_mode' => 'UPI',
            'reference_no' => $referenceNo ?: null,
            'reference_number' => $referenceNo ?: null,
            'transaction_reference' => $referenceNo ?: null,
            'payment_type' => 'RECEIPT',
            'currency' => 'INR',
            'status' => 'PENDING_VERIFICATION',
            'bank_account_id' => !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null,
            'notes' => $data['notes'] ?? "Unverified UPI payment instruction for Tax Invoice {$invoice['invoice_number']}",
            'created_by' => $userName ?: 'System',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $invoice['branch_id'] ?: $branchId,
            'payment_id' => $paymentId,
            'document_type' => 'INVOICE',
            'document_id' => $invoiceId,
            'allocated_amount' => 0.00,
            'allocation_date' => $data['payment_date'] ?? date('Y-m-d'),
            'notes' => "Pending UPI verification for Invoice {$invoice['invoice_number']}",
            'created_by' => $userName ?: 'System',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log(
            action: 'create',
            module: 'payments',
            entityType: 'PAYMENT',
            entityId: $paymentId,
            entityLabel: $receiptNumber,
            oldValues: null,
            newValues: ['status' => 'PENDING_VERIFICATION', 'amount' => $amount, 'invoice_id' => $invoiceId],
            status: 'success',
            notes: "Recorded unverified UPI payment receipt {$receiptNumber} for Invoice {$invoice['invoice_number']}"
        );

        return [
            'payment_id' => $paymentId,
            'receipt_number' => $receiptNumber,
            'status' => 'PENDING_VERIFICATION',
            'verified' => false,
            'amount' => $amount,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice['invoice_number'],
            'message' => 'UPI payment instruction recorded pending verification. Invoice balance remains unchanged until confirmed.'
        ];
    }

    /**
     * Verify and confirm a pending payment transaction.
     * Transitions status to COMPLETED, updates allocations, reduces invoice outstanding balance,
     * updates customer ledger, and posts GL entries.
     *
     * @param int $companyId
     * @param int $paymentId
     * @param int $userId
     * @param string $userName
     * @param string|null $externalTransactionRef
     * @return array
     * @throws ValidationException
     */
    public function verifyPayment(
        int $companyId,
        int $paymentId,
        int $userId,
        string $userName,
        ?string $externalTransactionRef = null
    ): array {
        $payment = Database::selectOne(
            "SELECT * FROM payments WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$paymentId, $companyId]
        );

        if (!$payment) {
            throw new ValidationException(['payment_id' => 'Payment record not found.'], 'Payment record not found.');
        }

        if ($payment['status'] === 'COMPLETED' || $payment['status'] === 'CONFIRMED') {
            throw new ValidationException(['payment_id' => 'Payment transaction has already been verified and confirmed.'], 'Payment already verified.');
        }

        if ($payment['status'] === 'CANCELLED') {
            throw new ValidationException(['payment_id' => 'Cannot verify a cancelled payment voucher.'], 'Payment voucher is cancelled.');
        }

        $allocation = Database::selectOne(
            "SELECT * FROM payment_allocations WHERE payment_id = ? AND company_id = ? ORDER BY id ASC LIMIT 1",
            [$paymentId, $companyId]
        );

        if (!$allocation || $allocation['document_type'] !== 'INVOICE') {
            throw new ValidationException(['payment_id' => 'Payment allocation invoice not found.'], 'Payment allocation invoice not found.');
        }

        $invoiceId = (int)$allocation['document_id'];
        $amount = (float)$payment['amount'];

        return Database::transaction(function () use (
            $companyId, $userId, $userName, $paymentId, $payment, $allocation,
            $invoiceId, $amount, $externalTransactionRef
        ) {
            $invoice = Database::selectOne(
                "SELECT * FROM invoices WHERE id = ? AND company_id = ? FOR UPDATE",
                [$invoiceId, $companyId]
            );

            $grandTotal = (float)$invoice['grand_total'];
            $existingPaidAlloc = Database::selectOne(
                "SELECT COALESCE(SUM(pa.allocated_amount), 0) AS total_paid
                 FROM payment_allocations pa
                 JOIN payments py ON pa.payment_id = py.id
                 WHERE pa.document_type = 'INVOICE'
                   AND pa.document_id = ?
                   AND pa.company_id = ?
                   AND pa.payment_id != ?
                   AND py.deleted_at IS NULL
                   AND py.status IN ('COMPLETED', 'CONFIRMED', 'VERIFIED')",
                [$invoiceId, $companyId, $paymentId]
            );
            $confirmedPrior = (float)($existingPaidAlloc['total_paid'] ?? 0);
            $newTotalPaid = round($confirmedPrior + $amount, 2);
            $newOutstanding = max(0.00, round($grandTotal - $newTotalPaid, 2));

            $newStatus = 'UNPAID';
            $newPaymentStatus = 'UNPAID';
            if ($newOutstanding <= 0.001) {
                $newStatus = 'PAID';
                $newPaymentStatus = 'PAID';
            } elseif ($newTotalPaid > 0.001) {
                $newStatus = 'PARTIAL';
                $newPaymentStatus = 'PARTIALLY_PAID';
            }

            // 1. Update Payment status to COMPLETED
            $updates = [
                'status' => 'COMPLETED',
                'allocated_amount' => $amount,
                'unallocated_amount' => 0.00,
                'updated_by' => $userName,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($externalTransactionRef) {
                $updates['transaction_reference'] = $externalTransactionRef;
                $updates['reference_no'] = $externalTransactionRef;
            }
            Database::update('payments', $updates, 'id = ? AND company_id = ?', [$paymentId, $companyId]);

            // 2. Update allocation
            Database::update(
                'payment_allocations',
                ['allocated_amount' => $amount, 'notes' => "Confirmed payment for Invoice {$invoice['invoice_number']}"],
                'id = ? AND company_id = ?',
                [$allocation['id'], $companyId]
            );

            // 3. Update Invoice
            Database::update(
                'invoices',
                [
                    'amount_paid' => $newTotalPaid,
                    'amount_due' => $newOutstanding,
                    'status' => $newStatus,
                    'payment_status' => $newPaymentStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ? AND company_id = ?',
                [$invoiceId, $companyId]
            );

            // 4. Update Customer Balance
            if (!empty($invoice['customer_id'])) {
                $cust = Database::selectOne("SELECT current_balance FROM customers WHERE id = ?", [$invoice['customer_id']]);
                if ($cust) {
                    $newCustBal = max(0.00, round((float)$cust['current_balance'] - $amount, 2));
                    Database::update('customers', ['current_balance' => $newCustBal, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$invoice['customer_id']]);
                }
            }

            // 5. Update Bank Account Balance if specified
            if (!empty($payment['bank_account_id'])) {
                $ba = Database::selectOne("SELECT current_balance FROM bank_accounts WHERE id = ? AND company_id = ?", [$payment['bank_account_id'], $companyId]);
                if ($ba) {
                    $newBankBal = round((float)$ba['current_balance'] + $amount, 2);
                    Database::update('bank_accounts', ['current_balance' => $newBankBal, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$payment['bank_account_id']]);
                }
            }

            // 6. Post GL journal
            $this->postInvoiceReceiptJournal(
                $companyId,
                (int)($payment['branch_id'] ?? 1),
                $userId,
                $userName,
                $invoice,
                (string)$payment['payment_number'],
                (string)$payment['payment_date'],
                $amount,
                (string)$payment['payment_mode'],
                $payment['bank_account_id'] ? (int)$payment['bank_account_id'] : null
            );

            // 7. Audit log
            AuditLogger::log(
                action: 'verify',
                module: 'payments',
                entityType: 'PAYMENT',
                entityId: $paymentId,
                entityLabel: (string)$payment['payment_number'],
                oldValues: ['status' => 'PENDING_VERIFICATION'],
                newValues: ['status' => 'COMPLETED', 'amount_due' => $newOutstanding, 'invoice_status' => $newStatus],
                status: 'success',
                notes: "Verified and confirmed UPI payment {$payment['payment_number']} for Invoice {$invoice['invoice_number']}"
            );

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'amount_paid' => $newTotalPaid,
                'amount_due' => $newOutstanding,
                'status' => $newStatus,
                'payment_status' => $newPaymentStatus,
                'verified' => true,
                'message' => 'Payment verified and confirmed successfully.'
            ];
        });
    }

    /**
     * Retrieve complete payment history for a given sales invoice.
     *
     * @param int $companyId
     * @param int $invoiceId
     * @return array
     */
    public function getInvoicePaymentHistory(int $companyId, int $invoiceId): array
    {
        return Database::select(
            "SELECT pa.id AS allocation_id,
                    pa.allocated_amount,
                    pa.allocation_date,
                    py.id AS payment_id,
                    py.payment_number,
                    py.receipt_number,
                    py.payment_mode,
                    COALESCE(py.reference_no, py.reference_number, '') AS reference_no,
                    py.created_by,
                    py.status,
                    COALESCE(py.notes, pa.notes, '') AS notes,
                    ba.bank_name,
                    ba.account_name,
                    ba.account_number
             FROM payment_allocations pa
             JOIN payments py ON pa.payment_id = py.id
             LEFT JOIN bank_accounts ba ON py.bank_account_id = ba.id
             WHERE pa.document_type = 'INVOICE'
               AND pa.document_id = ?
               AND pa.company_id = ?
               AND py.deleted_at IS NULL
               AND py.status != 'CANCELLED'
             ORDER BY pa.id ASC",
            [$invoiceId, $companyId]
        );
    }

    /**
     * Retrieve invoice list with computed partial payments and balances for Payments workspace.
     *
     * @param int $companyId
     * @param array $filters
     * @return array
     */
    public function getInvoicePaymentsList(int $companyId, array $filters = []): array
    {
        $where = ["i.company_id = ?", "i.deleted_at IS NULL"];
        $params = [$companyId];

        if (!empty($filters['customer_id'])) {
            $where[] = "i.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = "i.branch_id = ?";
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = "i.invoice_date >= ?";
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = "i.invoice_date <= ?";
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR i.customer_name LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $sql = "SELECT i.id,
                       i.invoice_number,
                       i.invoice_date,
                       i.due_date,
                       i.grand_total,
                       i.amount_paid,
                       i.amount_due,
                       i.status,
                       i.payment_status,
                       i.customer_id,
                       COALESCE(c.name, i.customer_name, 'Direct Client') AS customer_name,
                       c.phone AS customer_phone,
                       (SELECT MAX(pa.allocation_date) 
                        FROM payment_allocations pa 
                        JOIN payments py ON pa.payment_id = py.id 
                        WHERE pa.document_type = 'INVOICE' 
                          AND pa.document_id = i.id 
                          AND py.deleted_at IS NULL 
                          AND py.status != 'CANCELLED') AS last_payment_date,
                       (SELECT COUNT(*) 
                        FROM payment_allocations pa 
                        JOIN payments py ON pa.payment_id = py.id 
                        WHERE pa.document_type = 'INVOICE' 
                          AND pa.document_id = i.id 
                          AND py.deleted_at IS NULL 
                          AND py.status != 'CANCELLED') AS installments_count
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.id DESC LIMIT 200";

        $rows = Database::select($sql, $params);

        // Apply payment_status filter in memory or SQL
        $statusFilter = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $today = date('Y-m-d');

        $filtered = [];
        foreach ($rows as &$r) {
            $grand = (float)$r['grand_total'];
            $paid = (float)$r['amount_paid'];
            $due = (float)$r['amount_due'];

            $isOverdue = (!empty($r['due_date']) && $r['due_date'] < $today && $due > 0.001);
            $computedStatus = ($due <= 0.001) ? 'PAID' : (($paid > 0.001) ? 'PARTIALLY_PAID' : 'UNPAID');

            $r['computed_status'] = $computedStatus;
            $r['is_overdue'] = $isOverdue;

            if ($statusFilter === 'ALL') {
                $filtered[] = $r;
            } elseif ($statusFilter === 'OVERDUE' && $isOverdue) {
                $filtered[] = $r;
            } elseif ($statusFilter === 'PAID' && $computedStatus === 'PAID') {
                $filtered[] = $r;
            } elseif ($statusFilter === 'PARTIALLY_PAID' && $computedStatus === 'PARTIALLY_PAID') {
                $filtered[] = $r;
            } elseif ($statusFilter === 'UNPAID' && $computedStatus === 'UNPAID') {
                $filtered[] = $r;
            }
        }
        unset($r);

        return $filtered;
    }

    /**
     * Post balanced General Ledger Journal Entry for Customer Inward Receipt.
     * Dr: Bank / Cash in Hand (100200 / 100100)
     * Cr: Accounts Receivable (Debtors) (100300)
     */
    protected function postInvoiceReceiptJournal(
        int $companyId,
        int $branchId,
        int $userId,
        string $userName,
        array $invoice,
        string $receiptNumber,
        string $date,
        float $amount,
        string $mode,
        ?int $bankAccountId
    ): void {
        try {
            // Resolve Accounts Receivable Account (Code 100300 or DEBTORS)
            $arAcc = Database::selectOne(
                "SELECT id FROM chart_of_accounts 
                 WHERE company_id = ? AND (account_code = '100300' OR account_name LIKE '%Receivable%' OR account_name LIKE '%Debtor%') 
                 LIMIT 1",
                [$companyId]
            );

            // Resolve Bank/Cash Account
            $isCash = (stripos($mode, 'Cash') !== false);
            $assetAccCode = $isCash ? '100100' : '100200';
            $assetAcc = Database::selectOne(
                "SELECT id FROM chart_of_accounts 
                 WHERE company_id = ? AND account_code = ? 
                 LIMIT 1",
                [$companyId, $assetAccCode]
            );

            if (!$assetAcc) {
                $assetAcc = Database::selectOne(
                    "SELECT id FROM chart_of_accounts 
                     WHERE company_id = ? AND account_type = 'ASSET' AND (account_name LIKE '%Bank%' OR account_name LIKE '%Cash%') 
                     LIMIT 1",
                    [$companyId]
                );
            }

            if (!$arAcc || !$assetAcc) {
                return; // Gracefully continue if accounts are not configured
            }

            $arAccId = (int)$arAcc['id'];
            $assetAccId = (int)$assetAcc['id'];

            $journalNumber = 'JE-RCP-' . date('Ymd') . '-' . rand(1000, 9999);
            $desc = "Customer collection for Invoice {$invoice['invoice_number']} ({$receiptNumber})";

            $journalId = Database::insert('journal_entries', [
                'company_id' => $companyId,
                'entry_number' => $journalNumber,
                'entry_date' => $date,
                'narration' => $desc,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'status' => 'POSTED',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Debit Line: Bank/Cash
            Database::insert('journal_entry_lines', [
                'company_id' => $companyId,
                'journal_entry_id' => $journalId,
                'account_id' => $assetAccId,
                'debit_amount' => $amount,
                'credit_amount' => 0.00,
                'description' => "Inward collection via {$mode} ({$receiptNumber})",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit Line: Accounts Receivable
            Database::insert('journal_entry_lines', [
                'company_id' => $companyId,
                'journal_entry_id' => $journalId,
                'account_id' => $arAccId,
                'debit_amount' => 0.00,
                'credit_amount' => $amount,
                'description' => "Reduction of A/R for {$invoice['customer_name']} on Invoice {$invoice['invoice_number']}",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('postInvoiceReceiptJournal warning: ' . $e->getMessage());
        }
    }
}
