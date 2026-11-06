<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;

/**
 * Customer Advance & Receivables Aging Business Service.
 * Manages advance receipts (ADV-YYYY-XXXX), drawdown against invoices,
 * multi-document payment allocations, and accounts receivable aging analytics.
 */
class CustomerAdvanceService
{
    /**
     * Generate sequential advance payment voucher number (ADV-YYYY-XXXX).
     */
    public function generateAdvanceNumber(int $companyId): string
    {
        $year = date('Y');
        $prefix = "ADV-{$year}-";

        $latest = Database::selectOne(
            "SELECT payment_number FROM payments 
             WHERE company_id = ? AND payment_number LIKE ? 
             ORDER BY id DESC LIMIT 1",
            [$companyId, $prefix . '%']
        );

        $seq = 1;
        if ($latest && preg_match('/ADV-\d{4}-(\d+)/', (string)$latest['payment_number'], $matches)) {
            $seq = (int)$matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $seq);
    }

    /**
     * Retrieve customer advances with filtering.
     */
    public function getAdvances(int $companyId, array $filters = []): array
    {
        $where = ["ca.company_id = ?"];
        $params = [$companyId];

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $where[] = "ca.status = ?";
            $params[] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $where[] = "ca.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = "(ca.branch_id = ? OR ca.branch_id IS NULL)";
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR p.payment_number LIKE ? OR ca.notes LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                ca.*,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                c.gstin AS customer_gstin,
                p.payment_number,
                p.payment_mode,
                p.reference_no,
                b.name AS branch_name
            FROM customer_advances ca
            JOIN customers c ON ca.customer_id = c.id
            LEFT JOIN payments p ON ca.payment_id = p.id
            LEFT JOIN branches b ON ca.branch_id = b.id
            WHERE {$whereClause}
            ORDER BY ca.advance_date DESC, ca.id DESC
        ";

        return Database::select($sql, $params);
    }

    /**
     * Get single advance details with allocation history.
     */
    public function getAdvance(int $companyId, int $advanceId): ?array
    {
        $sql = "
            SELECT 
                ca.*,
                c.name AS customer_name,
                c.company_name AS customer_company_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                c.gstin AS customer_gstin,
                c.current_balance AS customer_current_balance,
                p.payment_number,
                p.payment_mode,
                p.reference_no,
                b.name AS branch_name
            FROM customer_advances ca
            JOIN customers c ON ca.customer_id = c.id
            LEFT JOIN payments p ON ca.payment_id = p.id
            LEFT JOIN branches b ON ca.branch_id = b.id
            WHERE ca.id = ? AND ca.company_id = ?
        ";

        $advance = Database::selectOne($sql, [$advanceId, $companyId]);
        if (!$advance) {
            return null;
        }

        // Fetch allocations
        $allocSql = "
            SELECT 
                pa.*,
                i.invoice_number,
                i.invoice_date,
                i.grand_total,
                i.amount_due,
                i.status AS invoice_status
            FROM payment_allocations pa
            LEFT JOIN invoices i ON pa.document_id = i.id
            WHERE pa.payment_id = ? AND pa.company_id = ? AND pa.document_type = 'INVOICE'
            ORDER BY pa.id DESC
        ";
        $advance['allocations'] = Database::select($allocSql, [$advance['payment_id'], $companyId]);

        return $advance;
    }

    /**
     * Record a new customer advance payment.
     */
    public function recordAdvance(int $companyId, array $data, string $userName): int
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new ValidationException(['customer_id' => 'Please select a valid customer.'], 'Please select a valid customer.');
        }

        $customer = Database::selectOne("SELECT id, name FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL", [$customerId, $companyId]);
        if (!$customer) {
            throw new ValidationException(['customer_id' => 'Selected customer was not found.'], 'Selected customer was not found.');
        }

        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0.00) {
            throw new ValidationException(['amount' => 'Advance amount must be greater than zero.'], 'Advance amount must be greater than zero.');
        }

        $advanceDate = !empty($data['advance_date']) ? (string)$data['advance_date'] : date('Y-m-d');
        $paymentMode = trim((string)($data['payment_mode'] ?? 'NEFT/RTGS'));
        $referenceNo = trim((string)($data['reference_no'] ?? $data['reference_number'] ?? ''));
        $branchId = (int)($data['branch_id'] ?? Session::getBranchId() ?? 1);
        $notes = trim((string)($data['notes'] ?? ''));

        return Database::transaction(function() use ($companyId, $branchId, $customerId, $amount, $advanceDate, $paymentMode, $referenceNo, $notes, $userName) {
            $now = date('Y-m-d H:i:s');
            $advNumber = $this->generateAdvanceNumber($companyId);

            // 1. Insert Payment Record
            $paymentId = (int)Database::insert('payments', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $advNumber,
                'receipt_number' => $advNumber,
                'party_type' => 'CUSTOMER',
                'party_id' => $customerId,
                'payment_date' => $advanceDate,
                'amount' => $amount,
                'payment_mode' => $paymentMode,
                'reference_no' => $referenceNo,
                'financial_year' => date('Y') . '-' . substr((string)((int)date('Y') + 1), -2),
                'payment_type' => 'RECEIPT',
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'currency' => 'INR',
                'status' => 'CONFIRMED',
                'notes' => $notes ?: 'Customer Advance Receipt',
                'created_by' => $userName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 2. Insert Customer Advance Record
            $advanceId = (int)Database::insert('customer_advances', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'payment_id' => $paymentId,
                'advance_date' => $advanceDate,
                'total_amount' => $amount,
                'allocated_amount' => 0.00,
                'remaining_amount' => $amount,
                'status' => 'ACTIVE',
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 3. Update Customer Current Balance (advances reduce receivable balance)
            Database::execute(
                "UPDATE customers SET current_balance = current_balance - ?, updated_at = ? WHERE id = ? AND company_id = ?",
                [$amount, $now, $customerId, $companyId]
            );

            return $advanceId;
        });
    }

    /**
     * Allocate Advance funds against specific invoice(s).
     */
    public function allocateAdvance(int $companyId, int $advanceId, array $allocations, string $userName): array
    {
        $advance = $this->getAdvance($companyId, $advanceId);
        if (!$advance) {
            throw new ValidationException(['id' => 'Advance record not found.'], 'Advance record not found.');
        }

        if ($advance['status'] !== 'ACTIVE' || (float)$advance['remaining_amount'] <= 0.001) {
            throw new ValidationException(['status' => 'This advance is already fully exhausted or inactive.'], 'This advance is already fully exhausted or inactive.');
        }

        if (empty($allocations)) {
            throw new ValidationException(['allocations' => 'No invoice allocations provided.'], 'No invoice allocations provided.');
        }

        // Calculate total allocation requested
        $totalRequested = 0.0;
        $normalizedAllocations = [];

        foreach ($allocations as $key => $val) {
            $invId = is_array($val) ? (int)($val['invoice_id'] ?? 0) : (int)$key;
            $amt = is_array($val) ? (float)($val['amount'] ?? 0) : (float)$val;
            $amt = round($amt, 2);

            if ($invId > 0 && $amt > 0.00) {
                $normalizedAllocations[] = ['invoice_id' => $invId, 'amount' => $amt];
                $totalRequested += $amt;
            }
        }

        $totalRequested = round($totalRequested, 2);
        $remAmount = (float)$advance['remaining_amount'];

        if ($totalRequested > $remAmount + 0.001) {
            $err = sprintf('Requested allocation (₹%s) exceeds available advance balance (₹%s).', number_format($totalRequested, 2), number_format($remAmount, 2));
            throw new ValidationException(['amount' => $err], $err);
        }

        return Database::transaction(function() use ($companyId, $advance, $normalizedAllocations, $totalRequested, $userName) {
            $now = date('Y-m-d H:i:s');
            $customerId = (int)$advance['customer_id'];
            $paymentId = (int)$advance['payment_id'];
            $branchId = (int)($advance['branch_id'] ?? 1);

            $appliedInvoices = [];

            foreach ($normalizedAllocations as $alloc) {
                $invId = $alloc['invoice_id'];
                $allocAmt = $alloc['amount'];

                $invoice = Database::selectOne(
                    "SELECT id, invoice_number, grand_total, amount_paid, amount_due, status 
                     FROM invoices 
                     WHERE id = ? AND company_id = ? AND customer_id = ? AND deleted_at IS NULL",
                    [$invId, $companyId, $customerId]
                );

                if (!$invoice) {
                    throw new ValidationException(['invoice_id' => "Invoice #{$invId} not found or does not belong to this customer."], "Invoice #{$invId} not found or does not belong to this customer.");
                }

                $due = (float)$invoice['amount_due'];
                if ($allocAmt > $due + 0.001) {
                    $err = sprintf("Allocation of ₹%s exceeds invoice %s due balance of ₹%s.", number_format($allocAmt, 2), $invoice['invoice_number'], number_format($due, 2));
                    throw new ValidationException(['allocation' => $err], $err);
                }

                // 1. Record allocation in payment_allocations
                Database::insert('payment_allocations', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'payment_id' => $paymentId,
                    'document_type' => 'INVOICE',
                    'document_id' => $invId,
                    'allocated_amount' => $allocAmt,
                    'allocation_date' => date('Y-m-d'),
                    'notes' => "Advance Drawdown from {$advance['payment_number']}",
                    'created_by' => $userName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 2. Update Invoice balance
                $newPaid = round((float)$invoice['amount_paid'] + $allocAmt, 2);
                $newDue = round((float)$invoice['grand_total'] - $newPaid, 2);
                $newDue = max(0.00, $newDue);
                $newStatus = ($newDue <= 0.001) ? 'PAID' : 'PARTIALLY_PAID';

                Database::update(
                    'invoices',
                    [
                        'amount_paid' => $newPaid,
                        'amount_due' => $newDue,
                        'status' => $newStatus,
                        'updated_at' => $now,
                    ],
                    'id = ? AND company_id = ?',
                    [$invId, $companyId]
                );

                $appliedInvoices[] = [
                    'invoice_id' => $invId,
                    'invoice_number' => $invoice['invoice_number'],
                    'allocated' => $allocAmt,
                    'remaining_due' => $newDue,
                    'new_status' => $newStatus,
                ];
            }

            // 3. Update customer_advances balance
            $newAllocatedTotal = round((float)$advance['allocated_amount'] + $totalRequested, 2);
            $newRemaining = round((float)$advance['total_amount'] - $newAllocatedTotal, 2);
            $newRemaining = max(0.00, $newRemaining);
            $newAdvStatus = ($newRemaining <= 0.001) ? 'EXHAUSTED' : 'ACTIVE';

            Database::update(
                'customer_advances',
                [
                    'allocated_amount' => $newAllocatedTotal,
                    'remaining_amount' => $newRemaining,
                    'status' => $newAdvStatus,
                    'updated_at' => $now,
                ],
                'id = ? AND company_id = ?',
                [$advance['id'], $companyId]
            );

            // 4. Update payments table allocation metrics
            Database::execute(
                "UPDATE payments 
                 SET allocated_amount = allocated_amount + ?, 
                     unallocated_amount = GREATEST(0, unallocated_amount - ?), 
                     updated_at = ? 
                 WHERE id = ? AND company_id = ?",
                [$totalRequested, $totalRequested, $now, $paymentId, $companyId]
            );

            return [
                'success' => true,
                'advance_id' => (int)$advance['id'],
                'total_allocated' => $totalRequested,
                'remaining_advance' => $newRemaining,
                'status' => $newAdvStatus,
                'invoices_applied' => $appliedInvoices,
            ];
        });
    }

    /**
     * Retrieve active customer advances available for drawdown.
     */
    public function getCustomerActiveAdvances(int $companyId, int $customerId): array
    {
        return Database::select(
            "SELECT ca.*, p.payment_number, p.payment_mode 
             FROM customer_advances ca
             JOIN payments p ON ca.payment_id = p.id
             WHERE ca.customer_id = ? AND ca.company_id = ? AND ca.status = 'ACTIVE' AND ca.remaining_amount > 0.001
             ORDER BY ca.advance_date ASC, ca.id ASC",
            [$customerId, $companyId]
        );
    }

    /**
     * Accounts Receivable Aging Report calculation.
     * Buckets: 0-30 Days, 31-60 Days, 61-90 Days, 90+ Days.
     */
    public function getReceivablesAging(int $companyId, array $filters = []): array
    {
        $asOfDate = !empty($filters['as_of_date']) ? (string)$filters['as_of_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);
        $customerId = (int)($filters['customer_id'] ?? 0);

        $where = [
            "i.company_id = ?",
            "i.deleted_at IS NULL",
            "i.amount_due > 0.001",
            "i.status NOT IN ('CANCELLED', 'DRAFT')",
        ];
        $params = [$companyId];

        if ($branchId > 0) {
            $where[] = "(i.branch_id = ? OR i.branch_id IS NULL)";
            $params[] = $branchId;
        }

        if ($customerId > 0) {
            $where[] = "i.customer_id = ?";
            $params[] = $customerId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                i.id AS invoice_id,
                i.invoice_number,
                i.invoice_date,
                i.due_date,
                i.grand_total,
                i.amount_paid,
                i.amount_due,
                i.status,
                i.branch_id,
                c.id AS customer_id,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                c.credit_limit,
                c.payment_terms_days,
                b.name AS branch_name
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            LEFT JOIN branches b ON i.branch_id = b.id
            WHERE {$whereClause}
            ORDER BY c.name ASC, i.invoice_date ASC
        ";

        $invoices = Database::select($sql, $params);

        // Group by Customer
        $customerGroups = [];
        $grandTotals = [
            'bucket_0_30' => 0.0,
            'bucket_31_60' => 0.0,
            'bucket_61_90' => 0.0,
            'bucket_90_plus' => 0.0,
            'total_due' => 0.0,
            'total_invoices' => count($invoices),
        ];

        $asOfTs = strtotime($asOfDate);

        foreach ($invoices as $inv) {
            $cId = (int)$inv['customer_id'];
            if (!isset($customerGroups[$cId])) {
                $customerGroups[$cId] = [
                    'customer_id' => $cId,
                    'customer_name' => $inv['customer_name'],
                    'customer_phone' => $inv['customer_phone'] ?: '—',
                    'customer_email' => $inv['customer_email'] ?: '—',
                    'credit_limit' => (float)$inv['credit_limit'],
                    'payment_terms_days' => (int)$inv['payment_terms_days'],
                    'bucket_0_30' => 0.0,
                    'bucket_31_60' => 0.0,
                    'bucket_61_90' => 0.0,
                    'bucket_90_plus' => 0.0,
                    'total_due' => 0.0,
                    'invoices' => [],
                ];
            }

            // Calculate overdue days based on due_date (fallback to invoice_date)
            $refDate = $inv['due_date'] ?: $inv['invoice_date'];
            $refTs = strtotime($refDate);
            $daysOverdue = (int)floor(($asOfTs - $refTs) / 86400);

            $due = (float)$inv['amount_due'];

            if ($daysOverdue <= 30) {
                $customerGroups[$cId]['bucket_0_30'] += $due;
                $grandTotals['bucket_0_30'] += $due;
                $bucketLabel = '0-30 Days';
            } elseif ($daysOverdue <= 60) {
                $customerGroups[$cId]['bucket_31_60'] += $due;
                $grandTotals['bucket_31_60'] += $due;
                $bucketLabel = '31-60 Days';
            } elseif ($daysOverdue <= 90) {
                $customerGroups[$cId]['bucket_61_90'] += $due;
                $grandTotals['bucket_61_90'] += $due;
                $bucketLabel = '61-90 Days';
            } else {
                $customerGroups[$cId]['bucket_90_plus'] += $due;
                $grandTotals['bucket_90_plus'] += $due;
                $bucketLabel = '90+ Days';
            }

            $customerGroups[$cId]['total_due'] += $due;
            $grandTotals['total_due'] += $due;

            $inv['days_overdue'] = $daysOverdue;
            $inv['aging_bucket'] = $bucketLabel;
            $customerGroups[$cId]['invoices'][] = $inv;
        }

        // Format numbers
        $customersList = array_values($customerGroups);

        return [
            'as_of_date' => $asOfDate,
            'customers' => $customersList,
            'summary' => [
                'total_customers' => count($customersList),
                'total_invoices' => $grandTotals['total_invoices'],
                'bucket_0_30' => round($grandTotals['bucket_0_30'], 2),
                'bucket_31_60' => round($grandTotals['bucket_31_60'], 2),
                'bucket_61_90' => round($grandTotals['bucket_61_90'], 2),
                'bucket_90_plus' => round($grandTotals['bucket_90_plus'], 2),
                'total_due' => round($grandTotals['total_due'], 2),
            ],
        ];
    }

    /**
     * Export Aging Report as CSV stream.
     */
    public function exportReceivablesAgingCsv(int $companyId, array $filters = []): string
    {
        $report = $this->getReceivablesAging($companyId, $filters);
        $asOf = $report['as_of_date'];

        $out = fopen('php://memory', 'w+');
        fputcsv($out, ['ACCOUNTS RECEIVABLE AGING STATEMENT', "As of: {$asOf}"]);
        fputcsv($out, ['Customer Name', 'Phone', 'Credit Limit (INR)', '0-30 Days (INR)', '31-60 Days (INR)', '61-90 Days (INR)', '90+ Days (INR)', 'Total Due (INR)']);

        foreach ($report['customers'] as $c) {
            fputcsv($out, [
                $c['customer_name'],
                $c['customer_phone'],
                number_format($c['credit_limit'], 2, '.', ''),
                number_format($c['bucket_0_30'], 2, '.', ''),
                number_format($c['bucket_31_60'], 2, '.', ''),
                number_format($c['bucket_61_90'], 2, '.', ''),
                number_format($c['bucket_90_plus'], 2, '.', ''),
                number_format($c['total_due'], 2, '.', ''),
            ]);
        }

        $s = $report['summary'];
        fputcsv($out, [
            'TOTALS',
            '',
            '',
            number_format($s['bucket_0_30'], 2, '.', ''),
            number_format($s['bucket_31_60'], 2, '.', ''),
            number_format($s['bucket_61_90'], 2, '.', ''),
            number_format($s['bucket_90_plus'], 2, '.', ''),
            number_format($s['total_due'], 2, '.', ''),
        ]);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv ?: '';
    }
}
