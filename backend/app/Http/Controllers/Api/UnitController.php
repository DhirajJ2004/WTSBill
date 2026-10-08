<?php

namespace App\Http\Controllers\Api;

use App\Models\Unit;
use App\Models\UnitConversion;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class UnitController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $units = Unit::orderBy('name', 'asc')->get();
        return response_json(['status' => 'success', 'data' => $units]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        $shortName = trim($input['short_name'] ?? '');

        if (empty($name) || empty($shortName)) {
            return response_json(['status' => 'error', 'message' => 'Unit Name and Short Name/Code are required.'], 422);
        }

        // Duplicate code check
        $existing = Unit::where('short_name', $shortName)->first();
        if ($existing) {
            return response_json(['status' => 'error', 'message' => "Unit code '{$shortName}' already exists."], 422);
        }

        $unit = Unit::create([
            'company_id' => $companyId,
            'name' => $name,
            'short_name' => $shortName,
            'type' => $input['type'] ?? 'QUANTITY',
            'decimal_precision' => intval($input['decimal_precision'] ?? 0),
            'status' => 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'UNIT_CREATE', 'Unit', $unit->id, "Created Unit '{$unit->name}'");

        return response_json(['status' => 'success', 'message' => 'Unit created successfully.', 'data' => $unit], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $unit = Unit::find($id);
        if (!$unit) {
            return response_json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        $name = trim($input['name'] ?? '');
        $shortName = trim($input['short_name'] ?? '');

        if (empty($name) || empty($shortName)) {
            return response_json(['status' => 'error', 'message' => 'Unit Name and Short Name are required.'], 422);
        }

        // Duplicate check
        $existing = Unit::where('short_name', $shortName)->where('id', '!=', $unit->id)->first();
        if ($existing) {
            return response_json(['status' => 'error', 'message' => "Unit code '{$shortName}' already exists."], 422);
        }

        $unit->update([
            'name' => $name,
            'short_name' => $shortName,
            'type' => $input['type'] ?? 'QUANTITY',
            'decimal_precision' => intval($input['decimal_precision'] ?? 0),
            'status' => $input['status'] ?? 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'UNIT_UPDATE', 'Unit', $unit->id, "Updated Unit '{$unit->name}'");

        return response_json(['status' => 'success', 'message' => 'Unit updated successfully.', 'data' => $unit]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $unit = Unit::find($id);
        if (!$unit) {
            return response_json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        $unit->update(['status' => 'INACTIVE']);
        AuditLogService::log($companyId, $user->name, 'UNIT_DELETE', 'Unit', $unit->id, "Deactivated Unit '{$unit->name}'");

        return response_json(['status' => 'success', 'message' => 'Unit deactivated successfully.']);
    }

    public function indexConversions()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $conversions = UnitConversion::with(['fromUnit', 'toUnit'])->get();
        return response_json(['status' => 'success', 'data' => $conversions]);
    }

    public function storeConversion()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $fromUnitId = intval($input['from_unit_id'] ?? 0);
        $toUnitId = intval($input['to_unit_id'] ?? 0);
        $factor = floatval($input['conversion_factor'] ?? 1.0);

        if ($fromUnitId <= 0 || $toUnitId <= 0 || $factor <= 0) {
            return response_json(['status' => 'error', 'message' => 'Valid from unit, to unit, and factor are required.'], 422);
        }

        $conversion = UnitConversion::create([
            'company_id' => $companyId,
            'from_unit_id' => $fromUnitId,
            'to_unit_id' => $toUnitId,
            'conversion_factor' => $factor,
        ]);

        return response_json(['status' => 'success', 'message' => 'Unit conversion created.', 'data' => $conversion], 201);
    }
}
