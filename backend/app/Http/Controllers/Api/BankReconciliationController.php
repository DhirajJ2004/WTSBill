<?php

namespace App\Http\Controllers\Api;

use App\Models\BankReconciliation;
use App\Banking\Imports\BankStatementImportService;
use App\Banking\Reconciliation\BankReconciliationService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BankReconciliationController
{
    /**
     * Start reconciliation statement.
     */
    public function start(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'reconcile')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $bankId = intval($request->input('bank_account_id'));
        $start = $request->input('statement_start_date', date('Y-m-01'));
        $end = $request->input('statement_end_date', date('Y-m-t'));
        $openingBal = floatval($request->input('opening_balance', 0));
        $closingBal = floatval($request->input('closing_balance', 0));

        try {
            $rec = BankReconciliationService::startReconciliation($companyId, $bankId, $start, $end, $openingBal, $closingBal);
            return response()->json([
                'status' => 'success',
                'message' => 'Bank reconciliation statement started',
                'reconciliation' => $rec,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Import statement rows.
     */
    public function import(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'banking', 'reconcile')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $bankId = intval($request->input('bank_account_id'));
        $rows = $request->input('rows', []);
        $mapping = $request->input('mapping', []);
        $fileName = $request->input('file_name', 'statement.csv');

        try {
            $import = BankStatementImportService::importRows($companyId, $bankId, $rows, $mapping, $fileName);
            return response()->json([
                'status' => 'success',
                'message' => "Imported {$import->imported_rows} rows ({$import->duplicate_rows} duplicates detected)",
                'import' => $import,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get suggested matches for reconciliation.
     */
    public function suggestedMatches(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $bankId = intval($request->input('bank_account_id'));

        $matches = BankReconciliationService::findSuggestedMatches($companyId, $bankId);

        return response()->json([
            'status' => 'success',
            'suggested_matches' => $matches,
        ]);
    }

    /**
     * Confirm match.
     */
    public function match(Request $request): JsonResponse
    {
        $user = AuthMiddleware::getUser();
        if ($user && !PermissionManager::can($user, 'banking', 'reconcile')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $recId = intval($request->input('reconciliation_id'));
        $rowId = intval($request->input('statement_row_id'));
        $txId = intval($request->input('bank_transaction_id'));
        $confidence = $request->input('confidence', 'EXACT');

        try {
            $match = BankReconciliationService::matchTransaction($recId, $rowId, $txId, $confidence, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Transaction matched successfully',
                'match' => $match,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete reconciliation.
     */
    public function complete(int $id): JsonResponse
    {
        $user = AuthMiddleware::getUser();
        if ($user && !PermissionManager::can($user, 'banking', 'reconcile')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $rec = BankReconciliationService::completeReconciliation($id, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Bank reconciliation completed successfully',
                'reconciliation' => $rec,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
