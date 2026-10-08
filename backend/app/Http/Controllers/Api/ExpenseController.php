<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class ExpenseController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('expenses', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $expenses = Expense::where('company_id', $companyId)
            ->orderBy('expense_date', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $expenses,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('expenses', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $userName = $user?->name ?: 'Admin';
        $input = get_json_input();

        // 1. Validation
        $amount = round(floatval($input['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            return response_json(['status' => 'error', 'message' => 'Expense amount must be greater than zero.'], 422);
        }

        $category = trim($input['category'] ?? '');
        if (empty($category)) {
            return response_json(['status' => 'error', 'message' => 'Expense Category is required.'], 422);
        }

        $taxAmount = round(floatval($input['tax_amount'] ?? 0), 2);
        if ($taxAmount < 0 || $taxAmount >= $amount) {
            return response_json(['status' => 'error', 'message' => 'Tax amount must be non-negative and less than total expense amount.'], 422);
        }

        $gstin = trim($input['gstin'] ?? '');
        if (!empty($gstin)) {
            $gstinPattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
            if (!preg_match($gstinPattern, $gstin)) {
                return response_json(['status' => 'error', 'message' => 'Invalid GSTIN format for vendor.'], 422);
            }
        }

        $paymentMode = strtoupper(str_replace(' ', '_', trim($input['payment_mode'] ?? 'BANK_TRANSFER')));
        if ($paymentMode === 'PETTY_CASH') $paymentMode = 'CASH';

        $branchId = !empty($input['branch_id']) ? intval($input['branch_id']) : (AuthMiddleware::getBranchId() ?: null);
        $fy = $input['financial_year'] ?? '2026-27';
        $expenseDate = $input['expense_date'] ?? date('Y-m-d');
        $vendor = trim($input['payee'] ?? ($input['vendor'] ?? 'General Vendor'));
        $isItcEligible = isset($input['is_itc_eligible']) ? boolval($input['is_itc_eligible']) : true;
        $referenceNo = trim($input['reference_no'] ?? ($input['reference'] ?? ''));
        $description = trim($input['description'] ?? '');

        try {
            $expense = \Illuminate\Database\Capsule\Manager::transaction(function () use ($companyId, $branchId, $fy, $category, $vendor, $expenseDate, $amount, $taxAmount, $paymentMode, $gstin, $isItcEligible, $referenceNo, $description, $input, $userName) {
                // Generate sequential scoped document number
                $expNumber = \App\Services\DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'EXPENSE');

                $expense = Expense::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'expense_number' => $expNumber,
                    'category' => $category,
                    'payee' => $vendor,
                    'expense_date' => $expenseDate,
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'payment_mode' => $paymentMode,
                    'gstin' => $gstin,
                    'is_itc_eligible' => $isItcEligible,
                    'reference_no' => $referenceNo,
                    'description' => $description,
                ]);

                // Update Bank Account & Bank Transactions if paid via bank
                $bankAccountId = !empty($input['bank_account_id']) ? intval($input['bank_account_id']) : null;
                if (!$bankAccountId && in_array($paymentMode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])) {
                    $defaultBank = \App\Models\BankAccount::where('company_id', $companyId)->where('is_active', 1)->first();
                    if ($defaultBank) {
                        $bankAccountId = $defaultBank->id;
                    }
                }

                if ($bankAccountId) {
                    $bankAcc = \App\Models\BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);
                    if (isset($bankAcc->is_active) && !$bankAcc->is_active) {
                        throw new \InvalidArgumentException("Selected bank account is INACTIVE and cannot be used for expense disbursement.");
                    }
                    $bankAcc->current_balance = round($bankAcc->current_balance - $amount, 2);
                    $bankAcc->save();

                    \App\Models\BankTransaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'bank_account_id' => $bankAccountId,
                        'transaction_date' => $expenseDate,
                        'type' => 'DEBIT',
                        'debit_credit' => 'DEBIT',
                        'transaction_type' => 'EXPENSE',
                        'reference_number' => $expNumber,
                        'description' => "Expense: {$category} - {$vendor} (Ref: {$expNumber})",
                        'amount' => $amount,
                        'balance_after' => $bankAcc->current_balance,
                        'reconciliation_status' => 'UNRECONCILED',
                        'source' => 'EXPENSE',
                        'source_id' => $expense->id,
                    ]);
                }

                // Post Double-Entry Accounting Journal
                \App\Services\AccountingEventService::recordExpenseAccounting($expense, $userName);

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'EXPENSE_CREATE',
                    'Expense',
                    $expense->id,
                    "Recorded expense {$expNumber} ({$category}) of ₹" . number_format($amount, 2) . " to {$vendor} via {$paymentMode}"
                );

                return $expense;
            });

            return response_json([
                'status' => 'success',
                'message' => "Expense #{$expense->expense_number} recorded successfully",
                'data' => $expense,
                'expense' => $expense,
            ], 201);
        } catch (\Throwable $e) {
            return response_json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'Error recording expense.',
            ], 400);
        }
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('expenses', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Expense::withoutGlobalScopes()->find(intval($id));
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Expense not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        return response_json([
            'status' => 'success',
            'data' => $raw,
        ]);
    }
}
