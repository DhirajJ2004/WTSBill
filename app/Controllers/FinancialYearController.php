<?php

namespace App\Controllers;

use App\Services\FinancialYearService;
use App\Http\Request;

class FinancialYearController
{
    public function index(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $fys = FinancialYearService::getFinancialYears($companyId);
        $currentFy = FinancialYearService::getCurrentFinancialYear($companyId);

        return [
            'success'    => true,
            'current_fy' => $currentFy,
            'data'       => $fys,
        ];
    }

    public function store(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return FinancialYearService::createFinancialYear($companyId, $request->all(), $currentUser);
    }

    public function lock(Request $request, int $id)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        $reason = $request->get('lock_reason') ?: 'Locked for audit compliance';
        return FinancialYearService::lockPeriod($companyId, $id, $reason, $currentUser);
    }

    public function unlock(Request $request, int $id)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return FinancialYearService::unlockPeriod($companyId, $id, $currentUser);
    }
}
