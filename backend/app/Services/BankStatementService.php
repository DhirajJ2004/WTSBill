<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankStatementImport;
use App\Models\BankStatementRow;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class BankStatementService
{
    /**
     * Preview and validate statement rows with dynamic column mapping and duplicate detection
     */
    public static function previewStatement(
        int $companyId,
        int $bankAccountId,
        array $rawRows,
        string $fileName = 'statement.csv',
        ?array $columnMapping = null
    ): array {
        $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

        $mapping = $columnMapping ?: static::autoDetectColumnMapping($rawRows[0] ?? []);

        $parsedRows = [];
        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;

        foreach ($rawRows as $idx => $row) {
            $parsed = static::parseRow($row, $mapping);
            if (!$parsed['is_valid']) {
                $invalidCount++;
                $parsedRows[] = array_merge($parsed, ['row_index' => $idx + 1, 'is_duplicate' => false]);
                continue;
            }

            // Duplicate detection: check if transaction with same bank account, date, amount, and reference/description exists
            $isDuplicate = static::checkDuplicate(
                $companyId,
                $bankAccountId,
                $parsed['transaction_date'],
                $parsed['amount'],
                $parsed['debit_credit'],
                $parsed['reference_number'],
                $parsed['description']
            );

            if ($isDuplicate) {
                $duplicateCount++;
            } else {
                $validCount++;
            }

            $parsedRows[] = array_merge($parsed, [
                'row_index' => $idx + 1,
                'is_duplicate' => $isDuplicate,
            ]);
        }

        return [
            'file_name' => $fileName,
            'bank_account_id' => $bankAccountId,
            'bank_name' => $bankAccount->bank_name,
            'total_rows' => count($rawRows),
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'duplicate_rows' => $duplicateCount,
            'column_mapping' => $mapping,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Confirm and import validated statement rows into Bank Transactions
     */
    public static function confirmImport(
        int $companyId,
        int $bankAccountId,
        array $rawRows,
        string $fileName = 'statement.csv',
        ?array $columnMapping = null,
        bool $skipDuplicates = true,
        ?string $userName = 'System'
    ): array {
        return DB::transaction(function () use (
            $companyId, $bankAccountId, $rawRows, $fileName, $columnMapping, $skipDuplicates, $userName
        ) {
            $preview = static::previewStatement($companyId, $bankAccountId, $rawRows, $fileName, $columnMapping);

            $import = BankStatementImport::create([
                'company_id' => $companyId,
                'branch_id' => null,
                'bank_account_id' => $bankAccountId,
                'file_name' => $fileName,
                'file_type' => str_ends_with(strtolower($fileName), '.xlsx') ? 'EXCEL' : 'CSV',
                'total_rows' => $preview['total_rows'],
                'valid_rows' => $preview['valid_rows'],
                'invalid_rows' => $preview['invalid_rows'],
                'duplicate_rows' => $preview['duplicate_rows'],
                'imported_rows' => 0,
                'status' => 'IMPORTED',
                'column_mapping_json' => $preview['column_mapping'],
                'imported_by' => $userName,
            ]);

            $importedCount = 0;

            foreach ($preview['rows'] as $row) {
                if (!$row['is_valid']) {
                    BankStatementRow::create([
                        'company_id' => $companyId,
                        'import_id' => $import->id,
                        'bank_account_id' => $bankAccountId,
                        'row_index' => $row['row_index'],
                        'transaction_date' => $row['transaction_date'] ?: date('Y-m-d'),
                        'description' => $row['description'] ?? 'Invalid row',
                        'reference_number' => $row['reference_number'] ?? null,
                        'amount' => $row['amount'] ?? 0.00,
                        'is_duplicate' => false,
                        'is_valid' => false,
                        'error_message' => $row['error_message'],
                    ]);
                    continue;
                }

                if ($row['is_duplicate'] && $skipDuplicates) {
                    BankStatementRow::create([
                        'company_id' => $companyId,
                        'import_id' => $import->id,
                        'bank_account_id' => $bankAccountId,
                        'row_index' => $row['row_index'],
                        'transaction_date' => $row['transaction_date'],
                        'value_date' => $row['value_date'],
                        'description' => $row['description'],
                        'reference_number' => $row['reference_number'],
                        'debit_amount' => $row['debit_amount'],
                        'credit_amount' => $row['credit_amount'],
                        'amount' => $row['amount'],
                        'balance' => $row['balance'],
                        'is_duplicate' => true,
                        'is_valid' => true,
                    ]);
                    continue;
                }

                // Insert into Bank Transactions
                $txn = BankTransactionService::recordTransaction(
                    companyId: $companyId,
                    bankAccountId: $bankAccountId,
                    debitCredit: $row['debit_credit'],
                    amount: $row['amount'],
                    transactionType: 'OTHER',
                    description: $row['description'],
                    referenceNo: $row['reference_number'],
                    transactionDate: $row['transaction_date'],
                    valueDate: $row['value_date'],
                    source: 'BANK_IMPORT',
                    sourceId: (string)$import->id,
                    isReconciled: false
                );

                BankStatementRow::create([
                    'company_id' => $companyId,
                    'import_id' => $import->id,
                    'bank_account_id' => $bankAccountId,
                    'row_index' => $row['row_index'],
                    'transaction_date' => $row['transaction_date'],
                    'value_date' => $row['value_date'],
                    'description' => $row['description'],
                    'reference_number' => $row['reference_number'],
                    'debit_amount' => $row['debit_amount'],
                    'credit_amount' => $row['credit_amount'],
                    'amount' => $row['amount'],
                    'balance' => $row['balance'],
                    'is_duplicate' => false,
                    'is_valid' => true,
                    'bank_transaction_id' => $txn->id,
                ]);

                $importedCount++;
            }

            $import->imported_rows = $importedCount;
            $import->save();

            AuditLogService::log(
                $companyId,
                'BANK_STATEMENT_IMPORTED',
                'BankStatementImport',
                $import->id,
                "Imported {$importedCount} transactions from statement {$fileName}",
                null,
                $import->toArray(),
                $userName
            );

            return [
                'import_id' => $import->id,
                'file_name' => $fileName,
                'total_rows' => $preview['total_rows'],
                'imported_rows' => $importedCount,
                'skipped_duplicates' => $preview['duplicate_rows'],
                'invalid_rows' => $preview['invalid_rows'],
                'status' => 'SUCCESS',
            ];
        });
    }

    /**
     * Auto-detect column mapping from header row or first data row
     */
    public static function autoDetectColumnMapping(array $sampleRow): array
    {
        $mapping = [
            'date_col' => 'date',
            'value_date_col' => 'value_date',
            'description_col' => 'description',
            'reference_col' => 'reference',
            'debit_col' => 'debit',
            'credit_col' => 'credit',
            'amount_col' => 'amount',
            'balance_col' => 'balance',
        ];

        $keys = array_keys($sampleRow);
        foreach ($keys as $k) {
            $lower = strtolower((string)$k);
            if (in_array($lower, ['date', 'txn_date', 'transaction_date', 'trans date'])) $mapping['date_col'] = $k;
            if (in_array($lower, ['value_date', 'val_date', 'val date'])) $mapping['value_date_col'] = $k;
            if (in_array($lower, ['description', 'particulars', 'narration', 'desc', 'details'])) $mapping['description_col'] = $k;
            if (in_array($lower, ['reference', 'ref_no', 'chq_no', 'cheque_no', 'utr', 'ref'])) $mapping['reference_col'] = $k;
            if (in_array($lower, ['debit', 'dr', 'withdrawal', 'debit_amount', 'paid_out'])) $mapping['debit_col'] = $k;
            if (in_array($lower, ['credit', 'cr', 'deposit', 'credit_amount', 'paid_in'])) $mapping['credit_col'] = $k;
            if (in_array($lower, ['amount', 'txn_amount', 'transaction_amount'])) $mapping['amount_col'] = $k;
            if (in_array($lower, ['balance', 'closing_balance', 'bal', 'running_balance'])) $mapping['balance_col'] = $k;
        }

        return $mapping;
    }

    /**
     * Parse row into standardized structure
     */
    protected static function parseRow(array $row, array $mapping): array
    {
        $rawDate = $row[$mapping['date_col'] ?? 'date'] ?? ($row['date'] ?? null);
        $date = null;
        if ($rawDate) {
            $ts = strtotime((string)$rawDate);
            if ($ts !== false) {
                $date = date('Y-m-d', $ts);
            }
        }

        if (!$date) {
            return [
                'is_valid' => false,
                'error_message' => 'Missing or invalid transaction date.',
                'transaction_date' => null,
                'value_date' => null,
                'description' => $row[$mapping['description_col'] ?? 'description'] ?? '',
                'reference_number' => $row[$mapping['reference_col'] ?? 'reference'] ?? null,
                'debit_amount' => 0.0,
                'credit_amount' => 0.0,
                'amount' => 0.0,
                'debit_credit' => 'CREDIT',
                'balance' => 0.0,
            ];
        }

        $rawValDate = $row[$mapping['value_date_col'] ?? 'value_date'] ?? null;
        $valDate = $date;
        if ($rawValDate) {
            $ts = strtotime((string)$rawValDate);
            if ($ts !== false) $valDate = date('Y-m-d', $ts);
        }

        $desc = trim((string)($row[$mapping['description_col'] ?? 'description'] ?? ($row['description'] ?? 'Bank transaction')));
        $ref = trim((string)($row[$mapping['reference_col'] ?? 'reference'] ?? ($row['reference'] ?? '')));

        $debit = abs(floatval(str_replace(',', '', (string)($row[$mapping['debit_col'] ?? 'debit'] ?? ($row['debit'] ?? 0)))));
        $credit = abs(floatval(str_replace(',', '', (string)($row[$mapping['credit_col'] ?? 'credit'] ?? ($row['credit'] ?? 0)))));
        $amount = abs(floatval(str_replace(',', '', (string)($row[$mapping['amount_col'] ?? 'amount'] ?? ($row['amount'] ?? 0)))));
        $balance = floatval(str_replace(',', '', (string)($row[$mapping['balance_col'] ?? 'balance'] ?? ($row['balance'] ?? 0))));

        $debitCredit = 'CREDIT';
        $finalAmount = 0.0;

        if ($debit > 0) {
            $debitCredit = 'DEBIT';
            $finalAmount = $debit;
        } elseif ($credit > 0) {
            $debitCredit = 'CREDIT';
            $finalAmount = $credit;
        } elseif ($amount > 0) {
            // Check if amount has sign or type field
            $typeField = strtoupper((string)($row['type'] ?? ($row['dr_cr'] ?? 'CR')));
            $debitCredit = in_array($typeField, ['DR', 'DEBIT', 'OUT']) ? 'DEBIT' : 'CREDIT';
            $finalAmount = $amount;
        }

        if ($finalAmount <= 0) {
            return [
                'is_valid' => false,
                'error_message' => 'Transaction amount must be greater than zero.',
                'transaction_date' => $date,
                'value_date' => $valDate,
                'description' => $desc,
                'reference_number' => $ref,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'amount' => 0.0,
                'debit_credit' => $debitCredit,
                'balance' => $balance,
            ];
        }

        return [
            'is_valid' => true,
            'error_message' => null,
            'transaction_date' => $date,
            'value_date' => $valDate,
            'description' => $desc,
            'reference_number' => $ref ?: null,
            'debit_amount' => ($debitCredit === 'DEBIT') ? $finalAmount : 0.0,
            'credit_amount' => ($debitCredit === 'CREDIT') ? $finalAmount : 0.0,
            'amount' => $finalAmount,
            'debit_credit' => $debitCredit,
            'balance' => $balance,
        ];
    }

    /**
     * Check if similar transaction already exists
     */
    protected static function checkDuplicate(
        int $companyId,
        int $bankAccountId,
        string $date,
        float $amount,
        string $debitCredit,
        ?string $referenceNo,
        string $description
    ): bool {
        $query = BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)
            ->where('transaction_date', $date)
            ->where('amount', $amount)
            ->where('debit_credit', $debitCredit);

        if (!empty($referenceNo)) {
            $query->where(function ($q) use ($referenceNo, $description) {
                $q->where('reference_number', $referenceNo)
                  ->orWhere('reference_no', $referenceNo)
                  ->orWhere('description', $description);
            });
        } else {
            $query->where('description', $description);
        }

        return $query->exists();
    }
}
