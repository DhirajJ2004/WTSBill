<?php

namespace App\Imports\Validators;

use App\Models\ChartOfAccount;

class OpeningBalanceImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'account_code', 'Account Code', $issues);

        $code = trim((string)($row['account_code'] ?? ''));
        $debit = $this->validateNumeric($row, 'debit_amount', 'Debit Amount', $issues, false, 0.0);
        $credit = $this->validateNumeric($row, 'credit_amount', 'Credit Amount', $issues, false, 0.0);

        if ($debit < 0 || $credit < 0) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'debit_amount',
                'message' => 'Debit and Credit amounts must be non-negative.',
                'suggested_fix' => 'Enter positive numeric values.',
            ];
        }

        if ($debit > 0 && $credit > 0) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'debit_amount',
                'message' => 'A single row cannot have both debit and credit amounts.',
                'suggested_fix' => 'Specify either debit or credit amount per account.',
            ];
        }

        $account = ChartOfAccount::where('company_id', $companyId)->where('account_code', $code)->first();
        if (!$account) {
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'account_code',
                'message' => "Account code '{$code}' does not exist in Chart of Accounts and will be created.",
                'suggested_fix' => 'Verify account code exists or let system create it.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $status = $hasErrors ? 'ERROR' : 'VALID';

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => false,
            'parsed' => [
                'account_id' => $account?->id,
                'account_code' => $code,
                'account_name' => $row['account_name'] ?? $account?->name,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'opening_date' => $row['opening_date'] ?? date('Y-04-01'),
                'reference_notes' => $row['reference_notes'] ?? 'Opening balance import',
            ],
        ];
    }
}
