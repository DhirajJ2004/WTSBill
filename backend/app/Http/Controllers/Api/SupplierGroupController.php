<?php

namespace App\Http\Controllers\Api;

use App\Models\SupplierGroup;
use App\Http\Middleware\AuthMiddleware;

class SupplierGroupController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('suppliers', 'view');
        $groups = SupplierGroup::all();
        return response_json(['status' => 'success', 'data' => $groups]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('suppliers', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Group Name is required.'], 422);
        }

        $group = SupplierGroup::create([
            'company_id' => $companyId,
            'name' => $name,
            'description' => trim($input['description'] ?? ''),
            'status' => 'ACTIVE',
        ]);

        return response_json(['status' => 'success', 'message' => 'Supplier group created successfully.', 'data' => $group], 201);
    }
}
