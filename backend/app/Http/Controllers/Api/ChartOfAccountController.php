<?php

namespace App\Http\Controllers\Api;

use App\Models\ChartOfAccount;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class ChartOfAccountController
{
    /**
     * List accounts with search and filtering.
     */
    public function index()
    {
        $user = AuthMiddleware::authorize('accounting', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $query = ChartOfAccount::with(['parent', 'children']);

        // 1. Search Query
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('account_name', 'like', "%{$search}%")
                  ->orWhere('account_code', 'like', "%{$search}%");
            });
        }

        // 2. Type Filter
        if (!empty($_GET['account_type']) && $_GET['account_type'] !== 'all') {
            $query->where('account_type', strtoupper($_GET['account_type']));
        }

        // 3. Status Filter
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $statusVal = $_GET['status'] === 'Active' || $_GET['status'] === 'Active' ? 1 : 0;
            $query->where('is_active', $statusVal);
        }

        // 4. Sorting
        $sortBy = $_GET['sort_by'] ?? 'account_code';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['account_name', 'account_code', 'account_type'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('account_code', 'asc');
        }

        $accounts = $query->get();

        return response_json([
            'status' => 'success',
            'data' => $accounts,
        ]);
    }

    /**
     * Create account.
     */
    public function store()
    {
        $user = AuthMiddleware::authorize('accounting', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? $input['account_name'] ?? '');
        $type = strtoupper(trim($input['type'] ?? $input['account_type'] ?? 'ASSET'));
        $code = trim($input['code'] ?? $input['account_code'] ?? '');

        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Account Name is required.'], 422);
        }

        // Validate type
        $validTypes = ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'];
        if (!in_array($type, $validTypes, true)) {
            return response_json(['status' => 'error', 'message' => 'Invalid Account Type. Must be ASSET, LIABILITY, EQUITY, INCOME, or EXPENSE.'], 422);
        }

        // Validate code uniqueness for the company
        if (!empty($code)) {
            $existingCode = ChartOfAccount::where('account_code', $code)->first();
            if ($existingCode) {
                return response_json(['status' => 'error', 'message' => "Account code '{$code}' already exists."], 422);
            }
        }

        // Validate parent account
        $parentId = !empty($input['parent_account_id']) ? intval($input['parent_account_id']) : null;
        if ($parentId !== null) {
            $parent = ChartOfAccount::find($parentId);
            if (!$parent) {
                return response_json(['status' => 'error', 'message' => 'Parent account not found.'], 422);
            }
            // Enforce company ID matches
            if ($parent->company_id !== $companyId) {
                return response_json(['status' => 'error', 'message' => 'Unauthorized parent account.'], 403);
            }
        }

        $account = ChartOfAccount::create([
            'company_id' => $companyId,
            'account_name' => $name,
            'account_code' => $code ?: null,
            'account_type' => $type,
            'parent_account_id' => $parentId,
            'description' => trim($input['description'] ?? ''),
            'is_system_account' => false,
            'is_active' => true,
        ]);

        AuditLogService::log($companyId, $user->name, 'ACCOUNT_CREATE', 'ChartOfAccount', $account->id, "Created Account '{$account->account_name}' ({$account->account_code})");

        return response_json([
            'status' => 'success',
            'message' => 'Account created successfully.',
            'data' => $account,
        ], 201);
    }

    /**
     * Update account.
     */
    public function update($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response_json(['status' => 'error', 'message' => 'Account not found.'], 404);
        }

        // System accounts cannot have code or name changed casually, but description can be updated
        $name = trim($input['name'] ?? $input['account_name'] ?? '');
        $code = trim($input['code'] ?? $input['account_code'] ?? '');

        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Account Name is required.'], 422);
        }

        // Validate code uniqueness
        if (!empty($code)) {
            $existingCode = ChartOfAccount::where('account_code', $code)
                ->where('id', '!=', $account->id)
                ->first();
            if ($existingCode) {
                return response_json(['status' => 'error', 'message' => "Account code '{$code}' already exists."], 422);
            }
        }

        // Validate parent
        $parentId = !empty($input['parent_account_id']) ? intval($input['parent_account_id']) : null;
        if ($parentId !== null) {
            if ($parentId == $account->id) {
                return response_json(['status' => 'error', 'message' => 'An account cannot be its own parent.'], 422);
            }
            $parent = ChartOfAccount::find($parentId);
            if (!$parent || $parent->company_id !== $companyId) {
                return response_json(['status' => 'error', 'message' => 'Invalid parent account.'], 422);
            }
        }

        $account->update([
            'account_name' => $name,
            'account_code' => $code ?: null,
            'parent_account_id' => $parentId,
            'description' => trim($input['description'] ?? ''),
            'is_active' => isset($input['is_active']) ? boolval($input['is_active']) : $account->is_active,
        ]);

        AuditLogService::log($companyId, $user->name, 'ACCOUNT_UPDATE', 'ChartOfAccount', $account->id, "Updated Account '{$account->account_name}'");

        return response_json([
            'status' => 'success',
            'message' => 'Account updated successfully.',
            'data' => $account,
        ]);
    }

    /**
     * Delete/Deactivate account.
     */
    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('accounting', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response_json(['status' => 'error', 'message' => 'Account not found.'], 404);
        }

        if ($account->is_system_account) {
            return response_json(['status' => 'error', 'message' => 'System accounts cannot be deleted.'], 422);
        }

        // Check if there are journal entry lines referencing this account
        $hasJournalLines = DB::table('journal_entry_lines')
            ->where('account_id', $account->id)
            ->exists();

        if ($hasJournalLines) {
            // Deactivate
            $account->update(['is_active' => false]);
            AuditLogService::log($companyId, $user->name, 'ACCOUNT_DEACTIVATE', 'ChartOfAccount', $account->id, "Deactivated Account '{$account->account_name}' due to ledger postings");
            return response_json(['status' => 'success', 'message' => 'Account has ledger history and was deactivated instead of deleted.']);
        }

        $account->delete();
        AuditLogService::log($companyId, $user->name, 'ACCOUNT_DELETE', 'ChartOfAccount', $account->id, "Deleted Account '{$account->account_name}'");

        return response_json(['status' => 'success', 'message' => 'Account deleted successfully.']);
    }
}
