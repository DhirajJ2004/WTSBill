<?php

namespace App\Banking\Imports;

use App\Models\BankStatementImport;
use App\Models\BankStatementRow;
use Carbon\Carbon;
use InvalidArgumentException;

class BankStatementImportService
{
    /**
     * Import statement rows with mapping config and duplicate detection.
     */
    public static function importRows(int $companyId, int $bankAccountId, array $rows, array $columnMapping, string $fileName = 'statement.csv'): BankStatementImport
    {
        if (empty($rows)) {
            throw new InvalidArgumentException("No statement rows provided for import.");
        }

        $import = BankStatementImport::create([
            'company_id' => $companyId,
            'bank_account_id' => $bankAccountId,
            'file_name' => $fileName,
            'import_format' => 'CSV',
            'mapping_config_json' => $columnMapping,
            'total_rows' => count($rows),
            'imported_rows' => 0,
            'duplicate_rows' => 0,
            'status' => 'COMPLETED',
        ]);

        $imported = 0;
        $duplicates = 0;

        foreach ($rows as $r) {
            $dateVal = $r[$columnMapping['date'] ?? 'date'] ?? date('Y-m-d');
            $desc = $r[$columnMapping['description'] ?? 'description'] ?? 'Bank Entry';
            $ref = $r[$columnMapping['reference'] ?? 'reference'] ?? null;
            $debit = round(floatval($r[$columnMapping['debit'] ?? 'debit'] ?? 0), 2);
            $credit = round(floatval($r[$columnMapping['credit'] ?? 'credit'] ?? 0), 2);
            $balance = isset($r[$columnMapping['balance'] ?? 'balance']) ? floatval($r[$columnMapping['balance']]) : null;

            $parsedDate = Carbon::parse($dateVal)->toDateString();

            // Check if duplicate entry already exists in this bank account
            $isDuplicate = BankStatementRow::where('company_id', $companyId)
                ->where('bank_account_id', $bankAccountId)
                ->where('row_date', $parsedDate)
                ->where('debit', $debit)
                ->where('credit', $credit)
                ->where('description', $desc)
                ->exists();

            if ($isDuplicate) {
                $duplicates++;
            } else {
                $imported++;
            }

            BankStatementRow::create([
                'company_id' => $companyId,
                'import_id' => $import->id,
                'bank_account_id' => $bankAccountId,
                'row_date' => $parsedDate,
                'transaction_date' => $parsedDate,
                'description' => $desc,
                'reference_number' => $ref,
                'debit' => $debit,
                'credit' => $credit,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'amount' => ($credit > 0 ? $credit : -$debit),
                'balance' => $balance ?: 0.00,
                'match_status' => 'UNMATCHED',
                'duplicate_flag' => $isDuplicate,
                'is_duplicate' => $isDuplicate,
            ]);
        }

        $import->update([
            'imported_rows' => $imported,
            'duplicate_rows' => $duplicates,
        ]);

        return $import->load('rows');
    }
}
