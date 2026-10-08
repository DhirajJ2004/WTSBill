<?php

namespace App\Http\Controllers\Api;

use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class StockTransferController
{
    /**
     * List transfers.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $status = $request->input('status');

        $query = StockTransfer::where('company_id', $companyId)
            ->with(['fromWarehouse', 'toWarehouse', 'fromBranch', 'toBranch', 'items.product'])
            ->orderBy('id', 'desc');

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        $transfers = $query->paginate(25);

        return response()->json([
            'status' => 'success',
            'transfers' => $transfers,
        ]);
    }

    /**
     * Show single transfer.
     */
    public function show(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $transfer = StockTransfer::where('company_id', $companyId)
            ->with(['fromWarehouse', 'toWarehouse', 'fromBranch', 'toBranch', 'items.product'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'transfer' => $transfer,
        ]);
    }

    /**
     * Create transfer draft.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        if ($user && !PermissionManager::can($user, 'warehouse', 'transfer')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $transfer = StockTransferService::createTransfer($companyId, $request->all(), $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer created successfully',
                'transfer' => $transfer,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve transfer.
     */
    public function approve(int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        try {
            $transfer = StockTransferService::approveTransfer($id, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer approved successfully',
                'transfer' => $transfer,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Dispatch transfer.
     */
    public function dispatch(int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        try {
            $transfer = StockTransferService::dispatchTransfer($id, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer dispatched successfully (In Transit)',
                'transfer' => $transfer,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Receive transfer.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        $receivedItems = $request->input('received_quantities', []);

        try {
            $transfer = StockTransferService::receiveTransfer($id, $receivedItems, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer received and stock credited to destination warehouse',
                'transfer' => $transfer,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel transfer.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        $reason = $request->input('reason', 'Cancelled by user');

        try {
            $transfer = StockTransferService::cancelTransfer($id, $reason, $user?->name ?: 'System');
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer cancelled successfully',
                'transfer' => $transfer,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Print Transfer Note.
     */
    public function printNote(int $id): JsonResponse
    {
        try {
            $note = StockTransferService::generateTransferNote($id);
            return response()->json([
                'status' => 'success',
                'document' => $note,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
