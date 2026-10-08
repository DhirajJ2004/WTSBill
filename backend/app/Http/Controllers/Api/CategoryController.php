<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class CategoryController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('inventory', 'view');
        $categories = Category::with('parent')->get();
        return response_json(['status' => 'success', 'data' => $categories]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('inventory', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Category Name is required.'], 422);
        }

        $parentCategoryId = !empty($input['parent_category_id']) ? intval($input['parent_category_id']) : null;

        $category = Category::create([
            'company_id' => $companyId,
            'name' => $name,
            'code' => trim($input['code'] ?? ''),
            'parent_category_id' => $parentCategoryId,
            'description' => trim($input['description'] ?? ''),
            'status' => 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'CATEGORY_CREATE', 'Category', $category->id, "Created Category '{$category->name}'");

        return response_json(['status' => 'success', 'message' => 'Category created successfully.', 'data' => $category], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $category = Category::find($id);
        if (!$category) {
            return response_json(['status' => 'error', 'message' => 'Category not found.'], 404);
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Category Name is required.'], 422);
        }

        $parentCategoryId = !empty($input['parent_category_id']) ? intval($input['parent_category_id']) : null;

        if ($parentCategoryId !== null && $this->wouldCreateCircularHierarchy($category->id, $parentCategoryId)) {
            return response_json(['status' => 'error', 'message' => 'Invalid parent category: would create a circular relationship.'], 422);
        }

        $category->update([
            'name' => $name,
            'code' => trim($input['code'] ?? ''),
            'parent_category_id' => $parentCategoryId,
            'description' => trim($input['description'] ?? ''),
            'status' => $input['status'] ?? 'ACTIVE',
        ]);

        AuditLogService::log($companyId, $user->name, 'CATEGORY_UPDATE', 'Category', $category->id, "Updated Category '{$category->name}'");

        return response_json(['status' => 'success', 'message' => 'Category updated successfully.', 'data' => $category]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('inventory', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $category = Category::find($id);
        if (!$category) {
            return response_json(['status' => 'error', 'message' => 'Category not found.'], 404);
        }

        // Just deactivate
        $category->update(['status' => 'INACTIVE']);

        AuditLogService::log($companyId, $user->name, 'CATEGORY_DELETE', 'Category', $category->id, "Deactivated Category '{$category->name}'");

        return response_json(['status' => 'success', 'message' => 'Category deactivated successfully.']);
    }

    /**
     * Check if assigning parent category creates a circular loop.
     */
    private function wouldCreateCircularHierarchy($categoryId, $parentCategoryId)
    {
        if (empty($parentCategoryId)) {
            return false;
        }
        if ($categoryId == $parentCategoryId) {
            return true;
        }

        $currParentId = $parentCategoryId;
        while (!empty($currParentId)) {
            $parent = Category::find($currParentId);
            if (!$parent) {
                break;
            }
            if ($parent->parent_category_id == $categoryId) {
                return true;
            }
            $currParentId = $parent->parent_category_id;
        }
        return false;
    }
}
