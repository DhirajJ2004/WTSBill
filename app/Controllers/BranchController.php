<?php

namespace App\Controllers;

use App\Services\BranchService;
use App\Http\Request;

class BranchController
{
    public function index(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $filters = [
            'is_active' => $request->get('is_active'),
            'search'    => $request->get('search'),
        ];
        $branches = BranchService::getBranches($companyId, $filters);
        return ['success' => true, 'data' => $branches];
    }

    public function show(Request $request, int $id)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $branch = BranchService::getBranchById($companyId, $id);
        if (!$branch) {
            return ['success' => false, 'message' => 'Branch not found.'];
        }
        return ['success' => true, 'data' => $branch];
    }

    public function store(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return BranchService::createBranch($companyId, $request->all(), $currentUser);
    }

    public function update(Request $request, int $id)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return BranchService::updateBranch($companyId, $id, $request->all(), $currentUser);
    }
}
