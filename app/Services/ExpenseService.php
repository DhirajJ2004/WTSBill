<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\ExpenseRepository;
use App\Validators\ExpenseValidator;
use App\Services\AccountingEventService;
use App\Services\AccountService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class ExpenseService
{
    /**
     * Atomically record an Operating Expense with double-entry accounting.
     */
    public static function recordExpense(
        array $input,
        int $companyId,
        ?int $branchId = null,
        mixed $authUser = 'Admin'
    ): array {
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $errors = ExpenseValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
        if (!$company) {
            return ['success' => false, 'message' => 'Active company context is invalid.'];
        }

        if (!$branchId) {
            $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch ? $branch->id : 1;
        }

        $amount = round(floatval($input['amount']), 2);
        $taxAmount = round(floatval($input['tax_amount'] ?? 0), 2);
        $expenseDate = !empty($input['expense_date']) ? substr(trim($input['expense_date']), 0, 10) : date('Y-m-d');
        $paymentMode = strtoupper($input['payment_mode'] ?? 'CASH');

        return DB::transaction(function () use (
            $companyId, $branchId, $amount, $taxAmount, $expenseDate, $paymentMode,
            $input, $userName
        ) {
            $expNumber = ExpenseRepository::generateNextExpenseNumber($companyId, 'EXP');

            $expense = Expense::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'expense_number' => $expNumber,
                'category' => trim($input['category']),
                'payee' => $input['payee'] ?? 'Vendor',
                'expense_date' => $expenseDate,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'payment_mode' => $paymentMode,
                'gstin' => $input['gstin'] ?? null,
                'is_itc_eligible' => !empty($input['is_itc_eligible']),
                'reference_no' => $input['reference_no'] ?? null,
                'description' => $input['description'] ?? '',
            ]);

            // Post Double-Entry Accounting
            AccountService::ensureDefaultAccounts($companyId);
            AccountingEventService::recordExpenseAccounting($expense, $userName);

            AuditLogService::log(
                $companyId,
                $userName,
                'EXPENSE_CREATE',
                'Expense',
                $expense->id,
                "Recorded Expense #{$expNumber} of ₹{$amount} under category '{$expense->category}'"
            );

            return [
                'success' => true,
                'expense_id' => $expense->id,
                'expense_number' => $expNumber,
                'amount' => $amount,
                'expense' => $expense,
                'message' => "Expense #{$expNumber} recorded successfully."
            ];
        });
    }

    /**
     * Delete an Expense.
     */
    public static function deleteExpense(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $expense = Expense::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$expense) {
            return ['success' => false, 'message' => "Expense #{$id} not found or unauthorized."];
        }

        DB::transaction(function () use ($expense, $companyId, $id, $userName) {
            try {
                $origJournal = \App\Models\JournalEntry::where('company_id', $companyId)
                    ->where('reference_type', 'EXPENSE')
                    ->where('reference_id', (string)$expense->id)
                    ->where('status', 'POSTED')
                    ->first();
                if ($origJournal) {
                    $origJournal->update(['status' => 'CANCELLED']);
                }
            } catch (\Throwable $e) {}

            $expense->delete();

            AuditLogService::log(
                $companyId,
                $userName,
                'EXPENSE_DELETE',
                'Expense',
                $id,
                "Deleted Expense #{$expense->expense_number}"
            );
        });

        return ['success' => true, 'message' => "Expense #{$expense->expense_number} deleted successfully."];
    }
}
