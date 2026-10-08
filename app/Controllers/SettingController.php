<?php

namespace App\Controllers;

use App\Services\SettingService;
use App\Http\Request;

class SettingController
{
    public function show(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $branchId = (int)($request->get('branch_id') ?: ($_SESSION['branch_id'] ?? 1));

        $settings = SettingService::getSettings($companyId, $branchId);
        return ['success' => true, 'data' => $settings];
    }

    public function update(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $branchId = (int)($request->get('branch_id') ?: ($_SESSION['branch_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';

        return SettingService::updateSettings($companyId, $branchId, $request->all(), $currentUser);
    }
}
