<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * LedgerService — Accountant-grade general transaction ledger engine.
 * Computes opening balances, chronological running balances, multi-branch isolation,
 * party reconciliation, pagination, and multi-format exports.
 */
class LedgerService
{
    /**
     * Compute full transaction ledger statement for an account.
     */
    public function getLedger(int $companyId, array $filters = []): array
    {
        $accountId = (int)($filters['account_id'] ?? 0);
        $branchId = (int)($filters['branch_id'] ?? 0);
        $fromDate = !empty($filters['from_date']) ? $this->sanitizeDate((string)$filters['from_date']) : date('Y-01-01');
        $toDate = !empty($filters['to_date']) ? $this->sanitizeDate((string)$filters['to_date']) : date('Y-m-d');
        $txType = strtoupper(trim((string)($filters['transaction_type'] ?? 'ALL')));
        $voucherNo = trim((string)($filters['voucher_no'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 50);

        // 1. Fetch all accounts for dropdown selector
        $accounts = Database::select(
            "SELECT id, account_code, account_name, account_type, nature, opening_balance 
             FROM chart_of_accounts 
             WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY account_code ASC",
            [$companyId]
        );

        if (empty($accounts)) {
            return [
                'success' => false,
                'message' => 'No active chart of accounts found.',
                'account' => null,
                'accounts' => [],
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'total_debits' => 0.0,
                'total_credits' => 0.0,
                'entries' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0],
            ];
        }

        // If no account selected, default to the first account (or HDFC Bank/Debtors if available)
        $selectedAccount = null;
        if ($accountId > 0) {
            foreach ($accounts as $acc) {
                if ((int)$acc['id'] === $accountId) {
                    $selectedAccount = $acc;
                    break;
                }
            }
        }
        if (!$selectedAccount) {
            $selectedAccount = $accounts[0];
            $accountId = (int)$selectedAccount['id'];
        }

        $accountType = strtoupper((string)$selectedAccount['account_type']);
        $isDebitNature = in_array($accountType, ['ASSET', 'EXPENSE'], true);

        // 2. Compute Opening Balance up to $fromDate
        $initialOpening = (float)$selectedAccount['opening_balance'];

        $priorParams = [$accountId, $companyId, $fromDate];
        $priorBranchSql = "";
        if ($branchId > 0) {
            $priorBranchSql = " AND (je.branch_id = ? OR je.branch_id IS NULL)";
            $priorParams[] = $branchId;
        }

        $priorSums = Database::selectOne(
            "SELECT 
                COALESCE(SUM(jel.debit_amount), 0) AS total_dr,
                COALESCE(SUM(jel.credit_amount), 0) AS total_cr
             FROM journal_entry_lines jel
             INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
             WHERE jel.account_id = ? 
               AND je.company_id = ? 
               AND je.status = 'POSTED' 
               AND je.entry_date < ?
               {$priorBranchSql}",
            $priorParams
        ) ?: ['total_dr' => 0.0, 'total_cr' => 0.0];

        $priorDr = (float)$priorSums['total_dr'];
        $priorCr = (float)$priorSums['total_cr'];

        if ($isDebitNature) {
            $openingBalance = round($initialOpening + $priorDr - $priorCr, 2);
        } else {
            $openingBalance = round($initialOpening + $priorCr - $priorDr, 2);
        }

        // 3. Query all posted journal lines for this account in date range
        $where = [
            "jel.account_id = ?",
            "je.company_id = ?",
            "je.status = 'POSTED'",
            "je.entry_date >= ?",
            "je.entry_date <= ?",
        ];
        $params = [$accountId, $companyId, $fromDate, $toDate];

        if ($branchId > 0) {
            $where[] = "(je.branch_id = ? OR je.branch_id IS NULL)";
            $params[] = $branchId;
        }

        if ($txType !== 'ALL' && !empty($txType)) {
            $where[] = "(je.entry_type = ? OR je.reference_type = ?)";
            $params[] = $txType;
            $params[] = $txType;
        }

        if (!empty($voucherNo)) {
            $where[] = "(je.entry_number LIKE ? OR je.journal_number LIKE ?)";
            $term = '%' . $voucherNo . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(je.entry_number LIKE ? OR je.narration LIKE ? OR jel.description LIKE ? OR je.reference_id LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(" AND ", $where);

        $sql = "SELECT 
                    je.id AS journal_id,
                    COALESCE(je.entry_number, je.journal_number, CONCAT('JE-', je.id)) AS voucher_number,
                    je.entry_date,
                    COALESCE(je.entry_type, 'JOURNAL') AS entry_type,
                    je.reference_type,
                    je.reference_id,
                    je.narration,
                    je.branch_id,
                    b.name AS branch_name,
                    je.created_by,
                    u.name AS creator_name,
                    jel.id AS line_id,
                    jel.debit_amount,
                    jel.credit_amount,
                    jel.description AS line_description
                FROM journal_entry_lines jel
                INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
                LEFT JOIN branches b ON je.branch_id = b.id
                LEFT JOIN users u ON je.created_by = u.id
                WHERE {$whereClause}
                ORDER BY je.entry_date ASC, je.id ASC, jel.id ASC";

        $allRows = Database::select($sql, $params);

        // 4. Compute running balance for all rows chronologically
        $running = $openingBalance;
        $totalDebits = 0.0;
        $totalCredits = 0.0;
        $processedRows = [];

        foreach ($allRows as $row) {
            $dr = (float)$row['debit_amount'];
            $cr = (float)$row['credit_amount'];

            $totalDebits += $dr;
            $totalCredits += $cr;

            if ($isDebitNature) {
                $running = round($running + ($dr - $cr), 2);
            } else {
                $running = round($running + ($cr - $dr), 2);
            }

            // Party resolution (Customer / Supplier)
            $party = $this->resolveParty($row);

            // Source URL resolution
            $sourceUrl = $this->resolveSourceUrl($row);

            $processedRows[] = [
                'journal_id' => (int)$row['journal_id'],
                'line_id' => (int)$row['line_id'],
                'date' => $row['entry_date'],
                'voucher_number' => $row['voucher_number'],
                'transaction_type' => $row['reference_type'] ?: $row['entry_type'],
                'reference_number' => (string)($row['reference_id'] ?? ''),
                'party' => $party,
                'description' => $row['line_description'] ?: ($row['narration'] ?: 'Double-entry journal transaction'),
                'debit' => $dr,
                'debit_formatted' => $dr > 0 ? $this->formatCurrency($dr) : '-',
                'credit' => $cr,
                'credit_formatted' => $cr > 0 ? $this->formatCurrency($cr) : '-',
                'running_balance' => $running,
                'running_balance_formatted' => $this->formatCurrency($running) . ($isDebitNature ? ($running >= 0 ? ' Dr' : ' Cr') : ($running >= 0 ? ' Cr' : ' Dr')),
                'branch_id' => (int)($row['branch_id'] ?? 0),
                'branch_name' => $row['branch_name'] ?: 'All Branches',
                'created_by' => $row['creator_name'] ?: ($row['created_by'] ? 'User #' . $row['created_by'] : 'System'),
                'source_url' => $sourceUrl,
            ];
        }

        $closingBalance = $running;
        $totalRecords = count($processedRows);

        // 5. Pagination
        $paginatedRows = $processedRows;
        $totalPages = 1;
        if ($perPage > 0 && $totalRecords > 0) {
            $totalPages = (int)ceil($totalRecords / $perPage);
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $paginatedRows = array_slice($processedRows, $offset, $perPage);
        }

        // Branch info
        $branchName = 'All Branches';
        if ($branchId > 0) {
            $bRow = Database::selectOne("SELECT name FROM branches WHERE id = ? AND company_id = ?", [$branchId, $companyId]);
            if ($bRow) {
                $branchName = (string)$bRow['name'];
            }
        }

        return [
            'success' => true,
            'company_id' => $companyId,
            'branch' => [
                'id' => $branchId,
                'name' => $branchName,
            ],
            'filters' => [
                'account_id' => $accountId,
                'branch_id' => $branchId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'transaction_type' => $txType,
                'voucher_no' => $voucherNo,
                'search' => $search,
            ],
            'account' => [
                'id' => $accountId,
                'code' => $selectedAccount['account_code'],
                'name' => $selectedAccount['account_name'],
                'type' => $accountType,
                'nature' => $selectedAccount['nature'],
                'is_debit' => $isDebitNature,
            ],
            'accounts' => $accounts,
            'opening_balance' => $openingBalance,
            'opening_balance_formatted' => $this->formatCurrency($openingBalance) . ($isDebitNature ? ($openingBalance >= 0 ? ' Dr' : ' Cr') : ($openingBalance >= 0 ? ' Cr' : ' Dr')),
            'closing_balance' => $closingBalance,
            'closing_balance_formatted' => $this->formatCurrency($closingBalance) . ($isDebitNature ? ($closingBalance >= 0 ? ' Dr' : ' Cr') : ($closingBalance >= 0 ? ' Cr' : ' Dr')),
            'total_debits' => round($totalDebits, 2),
            'total_debits_formatted' => $this->formatCurrency($totalDebits),
            'total_credits' => round($totalCredits, 2),
            'total_credits_formatted' => $this->formatCurrency($totalCredits),
            'entries' => $paginatedRows,
            'all_entries' => $processedRows,
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Resolve Customer or Supplier party name from transaction metadata.
     */
    private function resolveParty(array $row): string
    {
        $narration = (string)($row['narration'] ?? '');
        $refType = (string)($row['reference_type'] ?? '');
        $refId = (string)($row['reference_id'] ?? '');

        // 1. Try parsing from narration
        if (preg_match('/Customer(?:\s+Payment)?:\s*([^,\(\n]+)/i', $narration, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Vendor(?:\s+Payout)?:\s*([^,\(\n]+)/i', $narration, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Purchase Bill\s+([A-Z0-9\-]+)/i', $narration, $m)) {
            $pbNumber = trim($m[1]);
            $supplier = Database::selectOne(
                "SELECT COALESCE(s.name, p.supplier_name) as name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.purchase_number = ? OR p.vendor_invoice_number = ? LIMIT 1",
                [$pbNumber, $pbNumber]
            );
            if ($supplier && !empty($supplier['name'])) {
                return (string)$supplier['name'];
            }
        }

        // 2. Try reference lookup
        if ($refType === 'INVOICE' && !empty($refId)) {
            $inv = Database::selectOne(
                "SELECT COALESCE(c.name, i.customer_name) as cust FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ? OR i.invoice_number = ? LIMIT 1",
                [$refId, $refId]
            );
            if ($inv && !empty($inv['cust'])) {
                return (string)$inv['cust'];
            }
        }

        if (($refType === 'PURCHASE' || $refType === 'PURCHASE_PAYMENT') && !empty($refId)) {
            $pur = Database::selectOne(
                "SELECT COALESCE(s.name, p.supplier_name) as supp FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ? OR p.purchase_number = ? LIMIT 1",
                [$refId, $refId]
            );
            if ($pur && !empty($pur['supp'])) {
                return (string)$pur['supp'];
            }
        }

        return '—';
    }

    /**
     * Resolve direct link to original document if available.
     */
    private function resolveSourceUrl(array $row): ?string
    {
        $refType = (string)($row['reference_type'] ?? '');
        $refId = (string)($row['reference_id'] ?? '');
        $narration = (string)($row['narration'] ?? '');

        if ($refType === 'INVOICE' && is_numeric($refId)) {
            return "/sales/invoices/{$refId}/print";
        }

        if (preg_match('/INV-[\d\-]+/i', $narration, $m)) {
            $inv = Database::selectOne("SELECT id FROM invoices WHERE invoice_number = ? LIMIT 1", [$m[0]]);
            if ($inv) {
                return "/sales/invoices/{$inv['id']}/print";
            }
        }

        return null;
    }

    /**
     * Format currency in Indian numbering format (₹ 1,23,456.78).
     */
    public function formatCurrency(float $amount): string
    {
        $abs = abs($amount);
        $formatted = '₹ ' . number_format($abs, 2, '.', ',');
        // Convert to Indian grouping if >= 1000
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

    /**
     * Sanitize YYYY-MM-DD date.
     */
    private function sanitizeDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    /**
     * Export Ledger as CSV string.
     */
    public function exportCsv(array $data): string
    {
        $lines = [];
        $lines[] = '"WTSBill ERP — General Ledger Statement"';
        $lines[] = '"Account: ' . $data['account']['code'] . ' - ' . str_replace('"', '""', $data['account']['name']) . '","Branch: ' . str_replace('"', '""', $data['branch']['name']) . '"';
        $lines[] = '"Period: ' . $data['filters']['from_date'] . ' to ' . $data['filters']['to_date'] . '","Opening Balance: ' . $data['opening_balance_formatted'] . '"';
        $lines[] = '"Total Debits: ' . $data['total_debits_formatted'] . '","Total Credits: ' . $data['total_credits_formatted'] . '","Closing Balance: ' . $data['closing_balance_formatted'] . '"';
        $lines[] = '""';

        $lines[] = '"Date","Voucher #","Type","Reference #","Customer / Supplier","Description","Debit (₹)","Credit (₹)","Running Balance","Branch","Created By"';

        // Opening Balance row
        $lines[] = sprintf(
            '"%s","%s","%s","%s","%s","%s",%s,%s,"%s","%s","%s"',
            $data['filters']['from_date'],
            'OPENING',
            'OPENING_BALANCE',
            '-',
            '-',
            'Balance Brought Forward',
            '""',
            '""',
            $data['opening_balance_formatted'],
            $data['branch']['name'],
            'System'
        );

        $entries = $data['all_entries'] ?? $data['entries'];
        foreach ($entries as $row) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s",%s,%s,"%s","%s","%s"',
                $row['date'],
                $row['voucher_number'],
                $row['transaction_type'],
                $row['reference_number'],
                str_replace('"', '""', $row['party']),
                str_replace('"', '""', $row['description']),
                $row['debit'] > 0 ? sprintf('%.2f', $row['debit']) : '""',
                $row['credit'] > 0 ? sprintf('%.2f', $row['credit']) : '""',
                $row['running_balance_formatted'],
                str_replace('"', '""', $row['branch_name']),
                str_replace('"', '""', $row['created_by'])
            );
        }

        return implode("\r\n", $lines);
    }
}
