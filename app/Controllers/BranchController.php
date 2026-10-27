<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Repositories\BranchRepository;
use WTSBill\Services\BranchService;

/**
 * Controller for Branch Management and Branch Context Switching.
 */
class BranchController
{
    private BranchService $branchService;
    private BranchRepository $branchRepo;

    public function __construct(?BranchService $branchService = null, ?BranchRepository $branchRepo = null)
    {
        $this->branchService = $branchService ?? new BranchService();
        $this->branchRepo = $branchRepo ?? new BranchRepository();
    }

    /**
     * Switch the currently active branch in the session.
     * Route: POST /branches/switch
     */
    public function switch(Request $request): Response
    {
        $userId = (int)(Session::get('user_id') ?? 0);
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userRole = (string)(Session::get('user_role') ?? 'STAFF');

        if ($userId <= 0) {
            if ($this->expectsJson($request)) {
                return Response::json(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
            }
            return Response::redirect('/login');
        }

        $branchId = (int)($request->input('branch_id') ?? $request->query('branch_id') ?? $request->query('id') ?? 0);
        if ($branchId <= 0) {
            $err = ['branch_id' => 'Please select a valid branch.'];
            if ($this->expectsJson($request)) {
                return Response::json(['success' => false, 'message' => 'Please select a valid branch.', 'errors' => $err], 422);
            }
            Session::flash('error', 'Please select a valid branch.');
            return Response::redirectBack();
        }

        try {
            $branch = $this->branchService->switchBranch($userId, $branchId, $companyId, $userRole);

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => true,
                    'message' => "Successfully switched active branch to '{$branch['name']}'.",
                    'branch' => [
                        'id' => (int)$branch['id'],
                        'name' => $branch['name'],
                        'code' => $branch['branch_code'] ?? $branch['code'] ?? '',
                        'city' => $branch['city'] ?? '',
                        'is_main_branch' => (bool)$branch['is_main_branch'],
                    ],
                ]);
            }

            Session::flash('success', "Active branch switched to '{$branch['name']}'.");
            return Response::redirectBack();
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $firstError = !empty($errors) ? current($errors) : $e->getMessage();
            $status = 422;
            if (is_string($firstError) && str_contains(strtolower($firstError), 'permission')) {
                $status = 403;
            }

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => is_string($firstError) ? $firstError : $e->getMessage(),
                    'errors' => $errors,
                ], $status);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirectBack();
        } catch (\Throwable $e) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to switch branch: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to switch branch: ' . $e->getMessage());
            return Response::redirectBack();
        }
    }

    /**
     * Branch Management Admin List View.
     * Route: GET /branches
     */
    public function index(Request $request): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branches = $this->branchRepo->findAllByCompany($companyId);
        $allUsers = Database::select(
            "SELECT id, name, email, role FROM `users` WHERE (current_company_id = ? OR current_company_id IS NULL) AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC",
            [$companyId]
        );

        $activeBranchId = Session::getBranchId();

        $content = View::render('branches/index', [
            'branches' => $branches,
            'allUsers' => $allUsers,
            'activeBranchId' => $activeBranchId,
        ]);

        return Response::html(View::render('layouts/app', [
            'title' => 'Branch & Location Management',
            'content' => $content,
        ]));
    }

    /**
     * Create a new branch.
     * Route: POST /branches
     */
    public function store(Request $request): Response
    {
        if ($authResp = $this->checkAdmin($request)) {
            return $authResp;
        }

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $data = $request->all();

        try {
            $branch = $this->branchService->createBranch($data, $companyId);

            // Handle optional user assignments
            if (!empty($data['assigned_users']) && is_array($data['assigned_users'])) {
                $this->branchService->assignUsers((int)$branch['id'], $companyId, $data['assigned_users']);
            }

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => true,
                    'message' => "Branch '{$branch['name']}' created successfully.",
                    'branch' => $branch,
                ], 201);
            }

            Session::flash('success', "Branch '{$branch['name']}' created successfully.");
            return Response::redirect('/branches');
        } catch (ValidationException $e) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/branches');
        } catch (\Throwable $e) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to create branch: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to create branch: ' . $e->getMessage());
            return Response::redirect('/branches');
        }
    }

    /**
     * Get branch list for dropdown/switcher and client state updates.
     * Route: GET /api/branches, GET /branches/list
     */
    public function list(Request $request): Response
    {
        $userId = (int)(Session::get('user_id') ?? 0);
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userRole = (string)(Session::get('user_role') ?? 'STAFF');

        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
        }

        $isAdmin = in_array(strtoupper($userRole), ['ADMIN', 'SUPERADMIN', 'OWNER'], true);
        $branches = $this->branchRepo->findSwitcherBranches($userId, $companyId, $userRole);
        $activeBranchId = (int)(Session::getBranchId() ?? 1);

        return Response::json([
            'success' => true,
            'active_branch_id' => $activeBranchId,
            'is_admin' => $isAdmin,
            'branches' => array_map(function ($b) {
                return [
                    'id' => (int)$b['id'],
                    'name' => $b['name'],
                    'code' => $b['code'] ?? $b['branch_code'] ?? '',
                    'branch_code' => $b['branch_code'] ?? $b['code'] ?? '',
                    'contact_person' => $b['contact_person'] ?? null,
                    'phone' => $b['phone'] ?? null,
                    'email' => $b['email'] ?? null,
                    'city' => $b['city'] ?? null,
                    'state' => $b['state'] ?? null,
                    'is_main_branch' => (bool)$b['is_main_branch'],
                    'is_active' => (int)$b['is_active'],
                ];
            }, $branches),
        ]);
    }

    /**
     * Get branch details for modal edit.
     * Route: GET /branches/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branch = $this->branchRepo->find($id);

        if (!$branch || (int)$branch['company_id'] !== $companyId) {
            return Response::json(['success' => false, 'message' => 'Branch not found.'], 404);
        }

        $assignedUserIds = $this->branchRepo->getBranchUserIds($id, $companyId);
        $branch['assigned_user_ids'] = $assignedUserIds;

        return Response::json([
            'success' => true,
            'branch' => $branch,
        ]);
    }

    /**
     * Update an existing branch.
     * Route: POST /branches/{id}/update
     */
    public function update(Request $request, int $id): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $data = $request->all();

        try {
            $branch = $this->branchService->updateBranch($id, $data, $companyId);

            if (isset($data['assigned_users']) && is_array($data['assigned_users'])) {
                $this->branchService->assignUsers($id, $companyId, $data['assigned_users']);
            }

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => true,
                    'message' => "Branch '{$branch['name']}' updated successfully.",
                    'branch' => $branch,
                ]);
            }

            Session::flash('success', "Branch '{$branch['name']}' updated successfully.");
            return Response::redirect('/branches');
        } catch (ValidationException $e) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/branches');
        } catch (\Throwable $e) {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update branch: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update branch: ' . $e->getMessage());
            return Response::redirect('/branches');
        }
    }

    /**
     * Toggle branch active status.
     * Route: POST /branches/{id}/toggle-status
     */
    public function toggleStatus(Request $request, int $id): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);

        try {
            $branch = $this->branchService->toggleStatus($id, $companyId);
            $action = (int)$branch['is_active'] === 1 ? 'activated' : 'deactivated';

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => true,
                    'message' => "Branch '{$branch['name']}' has been {$action}.",
                    'is_active' => (int)$branch['is_active'],
                ]);
            }

            Session::flash('success', "Branch '{$branch['name']}' has been {$action}.");
            return Response::redirect('/branches');
        } catch (ValidationException $e) {
            if ($this->expectsJson($request)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/branches');
        } catch (\Throwable $e) {
            if ($this->expectsJson($request)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/branches');
        }
    }

    /**
     * Assign users to branch.
     * Route: POST /branches/{id}/assign-users
     */
    public function assignUsers(Request $request, int $id): Response
    {
        $this->ensureAdmin();

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userIds = $request->input('user_ids', []);
        if (!is_array($userIds)) {
            $userIds = [];
        }

        try {
            $this->branchService->assignUsers($id, $companyId, $userIds);

            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => true,
                    'message' => 'User branch assignments updated successfully.',
                ]);
            }

            Session::flash('success', 'User branch assignments updated successfully.');
            return Response::redirect('/branches');
        } catch (\Throwable $e) {
            if ($this->expectsJson($request)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/branches');
        }
    }

    private function expectsJson(Request $request): bool
    {
        $accept = (string)($request->header('accept') ?? '');
        $contentType = (string)($request->header('content-type') ?? '');
        $xRequestedWith = (string)($request->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }

    private function checkAdmin(Request $request): ?Response
    {
        $role = strtoupper((string)(Session::get('user_role') ?? 'STAFF'));
        if ($role !== 'ADMIN' && $role !== 'SUPERADMIN' && $role !== 'OWNER') {
            if ($this->expectsJson($request)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Forbidden: Administrator privileges required to perform this action.',
                ], 403);
            }
            Session::flash('error', 'Forbidden: Administrator privileges required.');
            return Response::redirect('/dashboard');
        }
        return null;
    }

    private function ensureAdmin(): void
    {
        $role = strtoupper((string)(Session::get('user_role') ?? 'STAFF'));
        if ($role !== 'ADMIN' && $role !== 'SUPERADMIN' && $role !== 'OWNER') {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Forbidden: Administrator privileges required.']));
        }
    }
}
