<?php

namespace App\Controllers;

use App\Services\CompanyService;
use App\Http\Request;

class CompanyController
{
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];
        $companies = CompanyService::getAllCompanies($filters);
        return ['success' => true, 'data' => $companies];
    }

    public function show(int $id)
    {
        $company = CompanyService::getCompanyById($id);
        if (!$company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }
        return ['success' => true, 'data' => $company];
    }

    public function store(Request $request)
    {
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return CompanyService::createCompany($request->all(), $currentUser);
    }

    public function update(Request $request, int $id)
    {
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        return CompanyService::updateCompany($id, $request->all(), $currentUser);
    }
}
