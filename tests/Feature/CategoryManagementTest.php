<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test for Category Management (Manage, Edit, Delete, Toggle Status, Safeguards).
 * Verifies category actions column, show/manage endpoint, updates, status toggles,
 * linked product deletion safety guards, and clean deletion.
 */
class CategoryManagementTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Authenticate as Company 1 Admin
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)Session::getCompanyId();
        $csrfToken = Session::getCsrfToken();

        $assert('CATMGT-01', 'Admin user authenticates and establishes session context for categories management',
            $user !== null && $companyId === 1 && !empty($csrfToken)
        );

        // 2. GET /categories view renders Actions column with Manage, Edit, Delete buttons
        $catListReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/categories']);
        $catListRes = $router->dispatch($catListReq);
        $html = $catListRes->getContent();

        $hasActionsHeader = str_contains($html, 'Actions') || str_contains($html, 'actions');
        $hasManageBtn = str_contains($html, 'openManageCategoryModal(');
        $hasEditBtn = str_contains($html, 'openEditCategoryModal(');
        $hasDeleteBtn = str_contains($html, 'deleteCategory(');
        $hasManageModal = str_contains($html, 'id="manage-category-modal"');

        $assert('CATMGT-02', 'GET /categories renders Actions column with Manage, Edit, and Delete action buttons',
            $catListRes->getStatusCode() === 200 && $hasActionsHeader && $hasManageBtn && $hasEditBtn && $hasDeleteBtn && $hasManageModal
        );

        // 3. Create a temporary test category
        $randCode = 'TMC-' . rand(100, 999);
        $createReq = new Request([], [
            'name' => 'Manage Test Category ' . $randCode,
            'code' => $randCode,
            'description' => 'Temporary category for manage, edit and delete testing',
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $createRes = $router->dispatch($createReq);
        $createData = json_decode($createRes->getContent(), true);
        $testCatId = (int)($createData['category']['id'] ?? 0);

        $assert('CATMGT-03', 'POST /categories creates new test category successfully',
            in_array($createRes->getStatusCode(), [200, 201], true) && ($createData['success'] ?? false) === true && $testCatId > 0
        );

        // 4. GET /categories/{id} returns category metadata and empty linked products array
        $showReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/categories/' . $testCatId,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $showRes = $router->dispatch($showReq);
        $showData = json_decode($showRes->getContent(), true);

        $assert('CATMGT-04', 'GET /categories/{id} returns category details and linked products structure',
            $showRes->getStatusCode() === 200 &&
            ($showData['success'] ?? false) === true &&
            ($showData['category']['id'] ?? 0) === $testCatId &&
            is_array($showData['products'] ?? null) &&
            count($showData['products']) === 0
        );

        // 5. POST /categories/{id}/update modifies category metadata
        $updateReq = new Request([], [
            'name' => 'Updated Manage Category ' . $randCode,
            'code' => $randCode . '-U',
            'description' => 'Updated classification description notes',
            'status' => 'ACTIVE',
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories/' . $testCatId . '/update',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $updateRes = $router->dispatch($updateReq);
        $updateData = json_decode($updateRes->getContent(), true);

        $assert('CATMGT-05', 'POST /categories/{id}/update successfully edits category name and code',
            $updateRes->getStatusCode() === 200 &&
            ($updateData['success'] ?? false) === true &&
            ($updateData['category']['name'] ?? '') === ('Updated Manage Category ' . $randCode)
        );

        // 6. POST /categories/{id}/toggle-status flips ACTIVE to INACTIVE
        $toggleReq1 = new Request([], [
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories/' . $testCatId . '/toggle-status',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $toggleRes1 = $router->dispatch($toggleReq1);
        $toggleData1 = json_decode($toggleRes1->getContent(), true);

        $assert('CATMGT-06', 'POST /categories/{id}/toggle-status changes status from ACTIVE to INACTIVE',
            $toggleRes1->getStatusCode() === 200 &&
            ($toggleData1['success'] ?? false) === true &&
            ($toggleData1['category']['status'] ?? '') === 'INACTIVE'
        );

        // 7. POST /categories/{id}/toggle-status flips back to ACTIVE
        $toggleReq2 = new Request([], [
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories/' . $testCatId . '/toggle-status',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $toggleRes2 = $router->dispatch($toggleReq2);
        $toggleData2 = json_decode($toggleRes2->getContent(), true);

        $assert('CATMGT-07', 'POST /categories/{id}/toggle-status changes status from INACTIVE back to ACTIVE',
            $toggleRes2->getStatusCode() === 200 &&
            ($toggleData2['success'] ?? false) === true &&
            ($toggleData2['category']['status'] ?? '') === 'ACTIVE'
        );

        // 8. Safety check: Cannot delete category with linked products (Hardware category has 2 products)
        $hwCat = Database::selectOne("SELECT id, name FROM categories WHERE name = 'Hardware' AND company_id = ?", [$companyId]);
        $hwId = (int)($hwCat['id'] ?? 1);

        $delHwReq = new Request([], [
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories/' . $hwId . '/delete',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $delHwRes = $router->dispatch($delHwReq);
        $delHwData = json_decode($delHwRes->getContent(), true);

        $assert('CATMGT-08', 'Deleting a category with linked products is strictly rejected with HTTP 422 safeguard',
            $delHwRes->getStatusCode() === 422 &&
            ($delHwData['success'] ?? false) === false &&
            ($delHwData['linked_products_count'] ?? 0) > 0 &&
            str_contains($delHwData['message'] ?? '', 'linked product')
        );

        // 9. Successfully delete test category that has 0 linked products
        $delTestReq = new Request([], [
            '_token' => $csrfToken,
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/categories/' . $testCatId . '/delete',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $delTestRes = $router->dispatch($delTestReq);
        $delTestData = json_decode($delTestRes->getContent(), true);

        $catInDb = Database::selectOne("SELECT id FROM categories WHERE id = ?", [$testCatId]);

        $assert('CATMGT-09', 'POST /categories/{id}/delete permanently deletes category with 0 linked products',
            $delTestRes->getStatusCode() === 200 &&
            ($delTestData['success'] ?? false) === true &&
            $catInDb === null
        );

        // 10. Querying non-existent category ID returns HTTP 404
        $notFoundReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/categories/999999',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $notFoundRes = $router->dispatch($notFoundReq);

        $assert('CATMGT-10', 'GET /categories/{non_existent_id} cleanly returns HTTP 404 not found',
            $notFoundRes->getStatusCode() === 404
        );
    }
}
