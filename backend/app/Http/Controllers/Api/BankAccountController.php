<?php

namespace App\Http\Controllers\Api;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Banking\Services\BankAccountService;
use App\Banking\Services\BankTransactionService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BankAccountController
{
    /**
     * List bank accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $accounts = BankAccount::where('company_id', $companyId)->get();

        return response()->json([
            'status' => 'success',
            'bank_accounts' => $accounts,
        ]);
    }

    /**
     * Create bank account.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'manage')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $account = BankAccountService::createBankAccount($companyId, $request->all(), $request->input('branch_id'));
            return response()->json([
                'status' => 'success',
                'message' => 'Bank account created successfully',
                'bank_account' => $account,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * List bank transactions for an account.
     */
    public function transactions(int $id, Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

        $transactions = BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $account->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(30);

        return response()->json([
            'status' => 'success',
            'bank_account' => $account,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Inter-bank transfer.
     */
    public function transfer(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'manage')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $sourceId = intval($request->input('source_bank_id'));
        $destId = intval($request->input('destination_bank_id'));
        $amount = floatval($request->input('amount'));
        $ref = $request->input('reference');

        try {
            $result = BankTransactionService::transferFunds($companyId, $sourceId, $destId, $amount, $ref, null, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Funds transferred successfully',
                'result' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cash Deposit.
     */
    public function cashDeposit(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'manage')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $bankId = intval($request->input('bank_account_id'));
        $amount = floatval($request->input('amount'));
        $ref = $request->input('reference');

        try {
            $tx = BankTransactionService::cashDeposit($companyId, $bankId, $amount, $ref, null, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Cash deposited successfully',
                'transaction' => $tx,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cash Withdrawal.
     */
    public function cashWithdrawal(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'manage')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $bankId = intval($request->input('bank_account_id'));
        $amount = floatval($request->input('amount'));
        $ref = $request->input('reference');

        try {
            $tx = BankTransactionService::cashWithdrawal($companyId, $bankId, $amount, $ref, null, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Cash withdrawn successfully',
                'transaction' => $tx,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
