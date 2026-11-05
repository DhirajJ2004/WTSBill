<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Service for Intelligent Invoice Number Sequencing, Concurrency-Safe Acquisition,
 * and Number Reuse After Permanent/Authorized Invoice Deletion.
 */
class InvoiceNumberService
{
    /**
     * Parse an invoice number into its prefix, sequence integer, padding length, and calendar year.
     * Examples:
     *  - "INV-001"           => prefix: "INV-", sequence: 1, padding: 3, year: null
     *  - "INV-2026-0042"     => prefix: "INV-2026-", sequence: 42, padding: 4, year: "2026"
     *  - "INV-PUN-2026-0005" => prefix: "INV-PUN-2026-", sequence: 5, padding: 4, year: "2026"
     *  - "INV-1"             => prefix: "INV-", sequence: 1, padding: 1, year: null
     *
     * @return array{raw: string, prefix: string, sequence: int, padding: int, year: ?string}
     */
    public static function parse(string $invoiceNumber): array
    {
        $raw = trim($invoiceNumber);
        $prefix = 'INV-' . date('Y') . '-';
        $sequence = 1;
        $padding = 4;
        $year = null;

        if (preg_match('/^(.*?)(\d+)$/', $raw, $matches)) {
            $prefix = $matches[1];
            $sequence = (int)$matches[2];
            $padding = strlen($matches[2]);
        } elseif (!empty($raw)) {
            $prefix = rtrim($raw, '-') . '-';
        }

        if (preg_match('/(20\d\d)/', $prefix, $ym)) {
            $year = $ym[1];
        }

        return [
            'raw' => $raw,
            'prefix' => $prefix,
            'sequence' => $sequence,
            'padding' => $padding,
            'year' => $year,
        ];
    }

    /**
     * Detect default prefix, sequence padding, and fiscal/calendar year for a given company and branch.
     *
     * @return array{prefix: string, padding: int, year: string}
     */
    public static function detectDefaultPrefix(int $companyId, ?int $branchId = null): array
    {
        $currentYear = date('Y');

        if ($branchId !== null && $branchId > 0) {
            // Check if branch has an established numbering pattern in active invoices
            $existing = Database::selectOne(
                "SELECT invoice_number FROM invoices WHERE company_id = ? AND branch_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
                [$companyId, $branchId]
            );

            if ($existing && !empty($existing['invoice_number'])) {
                $parsed = self::parse($existing['invoice_number']);
                return [
                    'prefix' => $parsed['prefix'],
                    'padding' => max(3, $parsed['padding']),
                    'year' => $parsed['year'] ?? $currentYear,
                ];
            }

            // Look up branch code
            $branch = Database::selectOne(
                "SELECT code, branch_code, is_main_branch FROM branches WHERE id = ? AND company_id = ? LIMIT 1",
                [$branchId, $companyId]
            );

            if ($branch && empty($branch['is_main_branch'])) {
                $code = trim((string)($branch['code'] ?? $branch['branch_code'] ?? ''));
                if (!empty($code)) {
                    $cleanCode = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($code));
                    return [
                        'prefix' => 'INV-' . $cleanCode . '-' . $currentYear . '-',
                        'padding' => 4,
                        'year' => $currentYear,
                    ];
                }
            }
        }

        // Standard default: INV-YYYY-
        return [
            'prefix' => 'INV-' . $currentYear . '-',
            'padding' => 4,
            'year' => $currentYear,
        ];
    }

    /**
     * Safety check: Verify whether an invoice is eligible for deletion.
     * Enforces statutory accounting rules:
     *  - Paid or partially paid invoices cannot be deleted without reversal.
     *  - Invoices with active payment allocations cannot be deleted.
     *  - Invoices in locked/closed accounting periods cannot be deleted.
     *
     * @param array|int $invoiceOrId
     * @return array{allowed: bool, reason: ?string}
     */
    public static function canDeleteInvoice(array|int $invoiceOrId, int $companyId = 1): array
    {
        $invoice = is_array($invoiceOrId)
            ? $invoiceOrId
            : Database::selectOne("SELECT * FROM invoices WHERE id = ? AND company_id = ?", [(int)$invoiceOrId, $companyId]);

        if (!$invoice) {
            return ['allowed' => false, 'reason' => 'Invoice not found or already deleted.'];
        }

        $invId = (int)($invoice['id'] ?? 0);

        // 1. Check paid or partially paid status
        $amountPaid = (float)($invoice['amount_paid'] ?? 0);
        $status = strtoupper(trim((string)($invoice['status'] ?? '')));

        if ($amountPaid > 0.001 || in_array($status, ['PAID', 'PARTIAL'], true)) {
            return [
                'allowed' => false,
                'reason' => "Cannot delete invoice '{$invoice['invoice_number']}': it has recorded payments (Paid: ₹" . number_format($amountPaid, 2) . "). Authorized payment reversal or credit note cancellation required.",
            ];
        }

        // 2. Check payment allocations
        try {
            $alloc = Database::selectOne(
                "SELECT COUNT(*) as c FROM payment_allocations WHERE invoice_id = ?",
                [$invId]
            );
            if ((int)($alloc['c'] ?? 0) > 0) {
                return [
                    'allowed' => false,
                    'reason' => "Cannot delete invoice '{$invoice['invoice_number']}': linked to active payment allocation records.",
                ];
            }
        } catch (\Throwable $e) {
            // Table might not exist or use document_id
        }

        // 3. Check accounting period lock
        try {
            $invDate = $invoice['invoice_date'] ?? date('Y-m-d');
            $period = Database::selectOne(
                "SELECT period_name FROM accounting_periods WHERE company_id = ? AND (is_closed = 1 OR is_locked = 1) AND ? BETWEEN start_date AND end_date LIMIT 1",
                [(int)($invoice['company_id'] ?? $companyId), $invDate]
            );
            if ($period) {
                return [
                    'allowed' => false,
                    'reason' => "Cannot delete invoice '{$invoice['invoice_number']}': falls within closed accounting period '{$period['period_name']}'.",
                ];
            }
        } catch (\Throwable $e) {
            // Table might not exist in all environments
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Release an invoice number into the reusable pool for subsequent newly created invoices.
     */
    public static function releaseInvoiceNumber(array $invoice, ?string $reason = null, ?int $userId = null): void
    {
        $invNum = trim((string)($invoice['invoice_number'] ?? ''));
        if (empty($invNum)) {
            return;
        }

        $companyId = (int)($invoice['company_id'] ?? Session::getCompanyId() ?? 1);
        $branchId = !empty($invoice['branch_id']) ? (int)$invoice['branch_id'] : null;
        $parsed = self::parse($invNum);

        // Check if already present in pool and unused
        $existing = Database::selectOne(
            "SELECT id FROM reusable_invoice_numbers WHERE company_id = ? AND invoice_number = ? AND is_reused = 0 LIMIT 1",
            [$companyId, $invNum]
        );

        if ($existing) {
            return;
        }

        $resolvedUserId = $userId ?? (int)(Session::getUserId() ?? 1);
        $deletionReason = !empty($reason) ? substr(trim($reason), 0, 255) : 'Invoice deleted';

        Database::insert('reusable_invoice_numbers', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => $invNum,
            'prefix' => $parsed['prefix'],
            'sequence_number' => $parsed['sequence'],
            'year' => $parsed['year'],
            'original_invoice_id' => !empty($invoice['id']) ? (int)$invoice['id'] : null,
            'deleted_by_user_id' => $resolvedUserId,
            'deletion_reason' => $deletionReason,
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_reused' => 0,
            'reused_at' => null,
            'reused_by_invoice_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Concurrency-safe invoice number acquisition.
     * Priority:
     *  1. Available released numbers from deleted invoices matching prefix (ordered by lowest sequence).
     *  2. Next monotonic sequence number.
     * Uses row-level locking (FOR UPDATE) within the current transaction to avoid race conditions.
     */
    public static function acquireInvoiceNumber(
        int $companyId,
        ?int $branchId = null,
        ?string $preferredPrefix = null,
        ?int $preferredPadding = null
    ): string {
        $detected = self::detectDefaultPrefix($companyId, $branchId);
        $prefix = $preferredPrefix !== null ? $preferredPrefix : $detected['prefix'];
        $padding = $preferredPadding !== null ? $preferredPadding : $detected['padding'];

        // Step 1: Check reusable numbers pool with FOR UPDATE lock
        $branchClause = ($branchId !== null && $branchId > 0)
            ? "(branch_id = ? OR branch_id IS NULL)"
            : "1=1";
        $params = ($branchId !== null && $branchId > 0)
            ? [$companyId, $prefix, $branchId]
            : [$companyId, $prefix];

        $reusableCandidates = Database::select(
            "SELECT * FROM reusable_invoice_numbers 
             WHERE company_id = ? AND prefix = ? AND {$branchClause} AND is_reused = 0 
             ORDER BY sequence_number ASC, id ASC 
             FOR UPDATE",
            $params
        );

        foreach ($reusableCandidates as $candidate) {
            $candidateNum = $candidate['invoice_number'];

            // Verify number is not currently occupied by any active invoice
            $activeInvoice = Database::selectOne(
                "SELECT id FROM invoices WHERE company_id = ? AND invoice_number = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE",
                [$companyId, $candidateNum]
            );

            if ($activeInvoice) {
                // Number is already taken; mark stale pool record as reused and skip
                Database::execute(
                    "UPDATE reusable_invoice_numbers SET is_reused = 1, updated_at = NOW() WHERE id = ?",
                    [(int)$candidate['id']]
                );
                continue;
            }

            // Claim this candidate
            Database::execute(
                "UPDATE reusable_invoice_numbers SET is_reused = 1, reused_at = NOW(), updated_at = NOW() WHERE id = ?",
                [(int)$candidate['id']]
            );

            return $candidateNum;
        }

        // Step 2: Generate next sequential number
        // Find highest sequence among active invoices matching prefix
        $activeInvoices = Database::select(
            "SELECT invoice_number FROM invoices 
             WHERE company_id = ? AND invoice_number LIKE ? 
             ORDER BY LENGTH(invoice_number) DESC, invoice_number DESC 
             LIMIT 100 
             FOR UPDATE",
            [$companyId, $prefix . '%']
        );

        $maxSeq = 0;
        foreach ($activeInvoices as $row) {
            $parsed = self::parse($row['invoice_number']);
            if ($parsed['prefix'] === $prefix && $parsed['sequence'] > $maxSeq) {
                $maxSeq = $parsed['sequence'];
            }
        }

        // Also check highest sequence in reusable pool (in case an invoice was deleted and created)
        $poolMax = Database::selectOne(
            "SELECT MAX(sequence_number) as max_seq FROM reusable_invoice_numbers WHERE company_id = ? AND prefix = ?",
            [$companyId, $prefix]
        );
        if ($poolMax && (int)($poolMax['max_seq'] ?? 0) > $maxSeq) {
            $maxSeq = (int)$poolMax['max_seq'];
        }

        // Fallback: If no invoice matching prefix exists yet and using default year prefix, use last invoice id to maintain continuity
        if ($maxSeq === 0 && str_starts_with($prefix, 'INV-' . date('Y'))) {
            $lastInv = Database::selectOne(
                "SELECT id FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$companyId]
            );
            $maxSeq = $lastInv ? (int)$lastInv['id'] : 0;
        }

        // Increment sequence and guarantee uniqueness against active invoices
        do {
            $maxSeq++;
            $candidateNum = $prefix . str_pad((string)$maxSeq, $padding, '0', STR_PAD_LEFT);
            $exists = Database::selectOne(
                "SELECT id FROM invoices WHERE company_id = ? AND invoice_number = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE",
                [$companyId, $candidateNum]
            );
        } while ($exists);

        return $candidateNum;
    }

    /**
     * Mark a claimed reusable invoice number as officially consumed by a newly inserted invoice record.
     */
    public static function confirmInvoiceNumberUsage(int $companyId, string $invoiceNumber, int $newInvoiceId): void
    {
        Database::execute(
            "UPDATE reusable_invoice_numbers 
             SET reused_by_invoice_id = ?, reused_at = NOW(), updated_at = NOW() 
             WHERE company_id = ? AND invoice_number = ? AND is_reused = 1 AND (reused_by_invoice_id IS NULL OR reused_by_invoice_id = 0)",
            [$newInvoiceId, $companyId, $invoiceNumber]
        );
    }

    /**
     * Cancel an invoice without deleting it or releasing its number.
     * Required for statutory GST compliance: Preserves voucher number and audit log for tax audit reconciliation.
     *
     * @return array{success: bool, message: string}
     */
    public static function cancelInvoice(int $companyId, int $invoiceId, string $reason, ?int $userId = null): array
    {
        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$invoiceId, $companyId]
        );

        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found or already deleted.'];
        }

        if (strtoupper(trim((string)$invoice['status'])) === 'CANCELLED') {
            return ['success' => false, 'message' => 'Invoice is already cancelled.'];
        }

        $resolvedUserId = $userId ?? (int)(Session::getUserId() ?? 1);
        $cleanReason = trim($reason) !== '' ? trim($reason) : 'Cancelled by authorized user';

        Database::transaction(function () use ($invoice, $invoiceId, $companyId, $cleanReason, $resolvedUserId) {
            $grandTotal = (float)$invoice['grand_total'];
            $amountPaid = (float)($invoice['amount_paid'] ?? 0);
            $customerId = (int)$invoice['customer_id'];
            $unpaidAmount = max(0, $grandTotal - $amountPaid);

            // Roll back unpaid customer balance
            if ($unpaidAmount > 0) {
                Database::execute(
                    "UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ? AND company_id = ?",
                    [$unpaidAmount, $customerId, $companyId]
                );
            }

            // Mark invoice as cancelled (preserve record and DO NOT set deleted_at)
            Database::execute(
                "UPDATE invoices 
                 SET status = 'CANCELLED', 
                     cancelled_at = NOW(), 
                     cancellation_reason = ?, 
                     updated_at = NOW() 
                 WHERE id = ? AND company_id = ?",
                [$cleanReason, $invoiceId, $companyId]
            );

            // Record audit log
            AuditService::log(
                'CANCEL',
                'INVOICE',
                $invoiceId,
                "Cancelled invoice '{$invoice['invoice_number']}'. Reason: {$cleanReason}. Number preserved for GST audit compliance."
            );
        });

        return [
            'success' => true,
            'message' => "Invoice '{$invoice['invoice_number']}' cancelled successfully. Number preserved for audit trail.",
        ];
    }

    /**
     * Delete an invoice (soft or permanent) and release its number into the reusable pool.
     *
     * @return array{success: bool, message: string}
     */
    public static function deleteInvoice(
        int $companyId,
        int $invoiceId,
        string $reason = 'Invoice deleted',
        ?int $userId = null,
        bool $permanent = false
    ): array {
        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND company_id = ?",
            [$invoiceId, $companyId]
        );

        if (!$invoice || ($invoice['deleted_at'] !== null && !$permanent)) {
            return ['success' => false, 'message' => 'Invoice not found or already deleted.'];
        }

        // Validate deletion rules
        $check = self::canDeleteInvoice($invoice, $companyId);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['reason']];
        }

        $resolvedUserId = $userId ?? (int)(Session::getUserId() ?? 1);
        $cleanReason = trim($reason) !== '' ? trim($reason) : 'Invoice deleted';

        Database::transaction(function () use ($invoice, $invoiceId, $companyId, $cleanReason, $resolvedUserId, $permanent) {
            $grandTotal = (float)$invoice['grand_total'];
            $customerId = (int)$invoice['customer_id'];

            // 1. Roll back customer ledger balance
            Database::execute(
                "UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ? AND company_id = ?",
                [$grandTotal, $customerId, $companyId]
            );

            // 2. Perform deletion
            if ($permanent) {
                // Purge child line items
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
                // Purge journal entries if any
                try {
                    Database::execute("DELETE FROM journal_entries WHERE reference_type = 'INVOICE' AND reference_id = ?", [$invoiceId]);
                } catch (\Throwable $e) {}
                // Purge invoice row
                Database::execute("DELETE FROM invoices WHERE id = ? AND company_id = ?", [$invoiceId, $companyId]);
            } else {
                // Soft-delete
                Database::execute(
                    "UPDATE invoices SET deleted_at = NOW(), status = 'CANCELLED', updated_at = NOW() WHERE id = ? AND company_id = ?",
                    [$invoiceId, $companyId]
                );
            }

            // 3. Release number into reusable pool
            self::releaseInvoiceNumber($invoice, $cleanReason, $resolvedUserId);

            // 4. Record audit log
            $deleteType = $permanent ? 'Permanent Deletion' : 'Deletion';
            AuditService::log(
                'DELETE',
                'INVOICE',
                $invoiceId,
                "{$deleteType} of invoice '{$invoice['invoice_number']}'. Reason: {$cleanReason}. Number released for reuse."
            );
        });

        return [
            'success' => true,
            'message' => "Invoice '{$invoice['invoice_number']}' deleted successfully. Invoice number is now available for reuse.",
        ];
    }
}
