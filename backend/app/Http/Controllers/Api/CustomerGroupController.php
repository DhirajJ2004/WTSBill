<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerGroup;
use App\Http\Middleware\AuthMiddleware;

class CustomerGroupController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('customers', 'view');
        $groups = CustomerGroup::all();
        return response_json(['status' => 'success', 'data' => $groups]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('customers', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Group Name is required.'], 422);
        }

        $group = CustomerGroup::create([
            'company_id' => $companyId,
            'name' => $name,
            'description' => trim($input['description'] ?? ''),
            'discount_percentage' => floatval($input['discount_percentage'] ?? 0),
            'status' => 'ACTIVE',
        ]);

        return response_json(['status' => 'success', 'message' => 'Customer group created successfully.', 'data' => $group], 201);
    }
}
