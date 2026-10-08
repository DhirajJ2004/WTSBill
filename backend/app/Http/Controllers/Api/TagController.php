<?php

namespace App\Http\Controllers\Api;

use App\Models\Tag;
use App\Http\Middleware\AuthMiddleware;

class TagController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('settings', 'view');
        $tags = Tag::all();
        return response_json(['status' => 'success', 'data' => $tags]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('settings', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Tag Name is required.'], 422);
        }

        $tag = Tag::create([
            'company_id' => $companyId,
            'name' => $name,
            'color' => trim($input['color'] ?? '#000000'),
        ]);

        return response_json(['status' => 'success', 'message' => 'Tag created successfully.', 'data' => $tag], 201);
    }
}
