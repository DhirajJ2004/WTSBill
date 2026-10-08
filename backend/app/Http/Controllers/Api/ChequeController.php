<?php

namespace App\Http\Controllers\Api;

use App\Models\Cheque;
use App\Cheques\Services\ChequeService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Exception;
use Throwable;

class ChequeController
{
    /**
     * List cheques register.
     */
    public function index($request = null)
    {
        $companyId = AuthMiddleware::getTenantId();

        $query = Cheque::where('company_id', $companyId)
            ->with(['bankAccount', 'events'])
            ->orderBy('cheque_date', 'desc')
            ->orderBy('id', 'desc');

        $type = $_GET['cheque_type'] ?? ($_GET['type'] ?? null);
        if ($type && strtoupper($type) !== 'ALL') {
            $query->where('cheque_type', strtoupper($type));
        }
        $status = $_GET['status'] ?? null;
        if ($status && strtoupper($status) !== 'ALL') {
            $query->where('status', strtoupper($status));
        }

        $cheques = $query->get();

        return response_json([
            'status' => 'success',
            'data' => $cheques,
            'cheques' => $cheques,
        ]);
    }

    /**
     * Alias for cheque register report.
     */
    public function getRegister($request = null)
    {
        return $this->index($request);
    }

    /**
     * Show single cheque.
     */
    public function show($id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $cheque = Cheque::where('company_id', $companyId)
            ->with(['bankAccount', 'events'])
            ->findOrFail(intval($id));

        return response_json([
            'status' => 'success',
            'data' => $cheque,
            'cheque' => $cheque,
        ]);
    }

    /**
     * Store (receive or issue) cheque.
     */
    public function store($request = null)
    {
        $input = get_json_input();
        $type = strtoupper($input['cheque_type'] ?? 'RECEIVED');

        if ($type === 'ISSUED') {
            return $this->issue($request);
        }
        return $this->receive($request);
    }

    /**
     * Receive cheque from customer.
     */
    public function receive($request = null)
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();
        $input = get_json_input();

        try {
            $cheque = ChequeService::receiveCheque($companyId, $input, $user?->name ?: 'Admin');
            return response_json([
                'status' => 'success',
                'message' => 'Cheque recorded in register',
                'data' => $cheque,
                'cheque' => $cheque,
            ], 201);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Deposit cheque in bank.
     */
    public function deposit($id, $request = null)
    {
        $user = AuthMiddleware::getUser();
        $input = get_json_input();
        $bankAccountId = intval($input['bank_account_id'] ?? 0);

        try {
            $cheque = ChequeService::depositCheque(intval($id), $bankAccountId, $user?->name ?: 'Admin');
            return response_json([
                'status' => 'success',
                'message' => 'Cheque deposited to bank account',
                'data' => $cheque,
                'cheque' => $cheque,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Clear cheque.
     */
    public function clear($id, $request = null)
    {
        $user = AuthMiddleware::getUser();
        $input = get_json_input();
        $clearanceDate = $input['clearance_date'] ?? date('Y-m-d');

        try {
            $cheque = ChequeService::clearCheque(intval($id), $clearanceDate, $user?->name ?: 'Admin');
            return response_json([
                'status' => 'success',
                'message' => 'Cheque cleared and funds realized',
                'data' => $cheque,
                'cheque' => $cheque,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Bounce cheque.
     */
    public function bounce($id, $request = null)
    {
        $user = AuthMiddleware::getUser();
        $input = get_json_input();
        $reason = $input['bounce_reason'] ?? ($input['reason'] ?? 'Insufficient Funds');
        $charges = floatval($input['bounce_charges'] ?? 0);

        try {
            $cheque = ChequeService::bounceCheque(intval($id), $reason, $charges, $user?->name ?: 'Admin');
            return response_json([
                'status' => 'success',
                'message' => 'Cheque marked bounced and reversal journal recorded',
                'data' => $cheque,
                'cheque' => $cheque,
            ]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel cheque.
     */
    public function cancel($id, $request = null)
    {
        $companyId = AuthMiddleware::getTenantId();
        $cheque = Cheque::where('company_id', $companyId)->findOrFail(intval($id));
        $cheque->status = 'CANCELLED';
        $cheque->save();

        return response_json([
            'status' => 'success',
            'message' => 'Cheque cancelled successfully',
            'data' => $cheque,
            'cheque' => $cheque,
        ]);
    }

    /**
     * Issue supplier cheque.
     */
    public function issue($request = null)
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();
        $input = get_json_input();

        try {
            $cheque = ChequeService::issueCheque($companyId, $input, $user?->name ?: 'Admin');
            return response_json([
                'status' => 'success',
                'message' => 'Supplier cheque issued',
                'data' => $cheque,
                'cheque' => $cheque,
            ], 201);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
