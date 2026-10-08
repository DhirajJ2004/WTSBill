<?php

namespace App\Imports\Processors;

use App\Models\ChartOfAccount;
use App\Services\JournalService;
use App\Services\AccountService;
use RuntimeException;

class OpeningBalanceImportProcessor
{
    public static function process(int $companyId, array $parsedRows, string $duplicateAction = 'SKIP'): array
    {
        $imported = 0;
        $totalDebit = 0.00;
        $totalCredit = 0.00;
        $journalLines = [];

        foreach ($parsedRows as $item) {
            $data = $item['parsed'];
            $code = $data['account_code'];

            $account = ChartOfAccount::where('company_id', $companyId)->where('account_code', $code)->first();
            if (!$account) {
                $account = ChartOfAccount::create([
                    'company_id' => $companyId,
                    'account_code' => $code,
                    'account_name' => $data['reference_notes'] ?: "Account {$code}",
                    'account_type' => 'ASSET',
                    'nature' => 'DEBIT',
                    'is_active' => true,
                ]);
            }

            $debit = round((float)$data['debit_amount'], 2);
            $credit = round((float)$data['credit_amount'], 2);
            $totalDebit += $debit;
            $totalCredit += $credit;

            // Update account's opening balance
            $netOpening = round($debit - $credit, 2);
            $account->update([
                'opening_balance' => $netOpening,
            ]);

            if ($debit > 0 || $credit > 0) {
                $journalLines[] = [
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $data['reference_notes'] ?: "Opening Balance for {$account->account_name}",
                ];
            }

            $imported++;
        }

        // Check if balanced, else balance via Opening Balance Adjustment Offset Account
        $diff = round($totalDebit - $totalCredit, 2);
        if (abs($diff) > 0.001) {
            $adjAccount = ChartOfAccount::where('company_id', $companyId)->where('account_code', '3000')->first()
                ?? ChartOfAccount::where('company_id', $companyId)->where('account_type', 'EQUITY')->first();

            if (!$adjAccount) {
                $adjAccount = ChartOfAccount::create([
                    'company_id' => $companyId,
                    'account_code' => '3000',
                    'account_name' => 'Owner Capital / Opening Balance Offset',
                    'account_type' => 'EQUITY',
                    'nature' => 'CREDIT',
                    'is_active' => true,
                ]);
            }

            if ($diff > 0) {
                // More debits than credits -> Credit offset
                $journalLines[] = [
                    'account_id' => $adjAccount->id,
                    'debit' => 0.00,
                    'credit' => abs($diff),
                    'description' => 'Opening Balance Balancing Adjustment Offset',
                ];
            } else {
                // More credits than debits -> Debit offset
                $journalLines[] = [
                    'account_id' => $adjAccount->id,
                    'debit' => abs($diff),
                    'credit' => 0.00,
                    'description' => 'Opening Balance Balancing Adjustment Offset',
                ];
            }
        }

        if (count($journalLines) >= 2) {
            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => date('Y-04-01'),
                'entry_type' => 'MANUAL',
                'status' => 'POSTED',
                'description' => 'Opening Balances Import Journal',
                'lines' => $journalLines,
            ], 'Import Processor');
        }

        return ['imported' => $imported, 'updated' => 0, 'skipped' => 0];
    }
}
