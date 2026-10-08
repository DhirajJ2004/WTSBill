<?php

namespace App\Http\Controllers\Api;

use App\Models\TaxRate;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class TaxRateController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('settings', 'view');
        $rates = TaxRate::orderBy('rate', 'asc')->get();
        return response_json(['status' => 'success', 'data' => $rates]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('settings', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        $rate = floatval($input['rate'] ?? -1);

        if (empty($name) || $rate < 0) {
            return response_json(['status' => 'error', 'message' => 'Tax Rate Name and valid positive Rate are required.'], 422);
        }

        $tax = TaxRate::create([
            'company_id' => $companyId,
            'name' => $name,
            'rate' => $rate,
            'tax_type' => $input['tax_type'] ?? 'GST',
            'effective_from' => !empty($input['effective_from']) ? $input['effective_from'] : null,
            'effective_to' => !empty($input['effective_to']) ? $input['effective_to'] : null,
            'status' => 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'TAX_CREATE', 'TaxRate', $tax->id, "Created Tax Rate '{$tax->name}' ({$tax->rate}%)");

        return response_json(['status' => 'success', 'message' => 'Tax rate created successfully.', 'data' => $tax], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('settings', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $tax = TaxRate::find($id);
        if (!$tax) {
            return response_json(['status' => 'error', 'message' => 'Tax rate not found.'], 404);
        }

        $name = trim($input['name'] ?? '');
        $rate = floatval($input['rate'] ?? -1);

        if (empty($name) || $rate < 0) {
            return response_json(['status' => 'error', 'message' => 'Tax Rate Name and valid positive Rate are required.'], 422);
        }

        $tax->update([
            'name' => $name,
            'rate' => $rate,
            'tax_type' => $input['tax_type'] ?? 'GST',
            'effective_from' => !empty($input['effective_from']) ? $input['effective_from'] : null,
            'effective_to' => !empty($input['effective_to']) ? $input['effective_to'] : null,
            'status' => $input['status'] ?? 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'TAX_UPDATE', 'TaxRate', $tax->id, "Updated Tax Rate '{$tax->name}' ({$tax->rate}%)");

        return response_json(['status' => 'success', 'message' => 'Tax rate updated successfully.', 'data' => $tax]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('settings', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $tax = TaxRate::find($id);
        if (!$tax) {
            return response_json(['status' => 'error', 'message' => 'Tax rate not found.'], 404);
        }

        $tax->update(['status' => 'INACTIVE']);
        AuditLogService::log($companyId, $user->name, 'TAX_DELETE', 'TaxRate', $tax->id, "Deactivated Tax Rate '{$tax->name}'");

        return response_json(['status' => 'success', 'message' => 'Tax rate deactivated successfully.']);
    }
}
