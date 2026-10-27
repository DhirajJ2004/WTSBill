<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SettingsController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Tests for Enterprise System Settings & Tenant Organization Profile.
 */
class SettingsEditTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $settingsController = new SettingsController();

        Session::start();
        Session::clear();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert(
            'SETTINGS-01',
            'Administrator authenticates and establishes session for settings management',
            Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
        );

        // 2. GET /settings renders HTML view with dynamic Organization Profile and Branches
        $indexReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/settings',
        ]);
        $indexResp = $settingsController->index($indexReq);
        $html = (string)$indexResp->getBody();
        $assert(
            'SETTINGS-02',
            'GET /settings returns HTTP 200 with Organization Profile and Edit triggers',
            $indexResp->getStatusCode() === 200 &&
            str_contains($html, 'Tenant Organization Profile') &&
            str_contains($html, 'openEditCompanyModal') &&
            str_contains($html, 'openEditBranchModal')
        );

        // 3. GET /api/settings/company returns company JSON
        $apiReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/settings/company',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $apiResp = $settingsController->getCompany($apiReq);
        $apiJson = json_decode((string)$apiResp->getBody(), true);
        $assert(
            'SETTINGS-03',
            'GET /api/settings/company returns HTTP 200 with company data payload',
            $apiResp->getStatusCode() === 200 &&
            ($apiJson['success'] ?? false) === true &&
            isset($apiJson['company']['gstin'])
        );

        // 4. Update Organization Profile via POST /settings/company/update
        $origCompany = Database::selectOne("SELECT * FROM companies WHERE id = 1");
        $origName = $origCompany['name'];
        $origEmail = $origCompany['email'];

        try {
            $updateReq = new Request([], [
                'name' => 'Wis Technosavvy Pvt Ltd Updated',
                'legal_name' => 'Wis Technosavvy Private Limited',
                'gstin' => '27AADCW7577N1ZE',
                'pan' => 'AADCW7577N',
                'email' => 'contact@wtsindia.co.in',
                'phone' => '020 47252364',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'state_code' => '27',
                'pincode' => '411041',
                'msme_no' => 'UDYAM-MH-26-0734598',
                'website' => 'http://www.wtsindia.co.in',
                'upi_id' => '9881032297@kotak',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/settings/company/update',
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $updateResp = $settingsController->updateCompany($updateReq);
            $updateJson = json_decode((string)$updateResp->getBody(), true);
            $updatedDb = Database::selectOne("SELECT name, legal_name, email FROM companies WHERE id = 1");

            $assert(
                'SETTINGS-04',
                'POST /settings/company/update saves new organization details in database',
                $updateResp->getStatusCode() === 200 &&
                ($updateJson['success'] ?? false) === true &&
                ($updatedDb['legal_name'] ?? '') === 'Wis Technosavvy Private Limited' &&
                ($updatedDb['email'] ?? '') === 'contact@wtsindia.co.in'
            );
        } finally {
            // Restore original company data
            Database::execute(
                "UPDATE companies SET name = ?, legal_name = ?, email = ? WHERE id = 1",
                [$origName, $origCompany['legal_name'] ?? $origName, $origEmail]
            );
        }

        // 5. Validation failure on invalid GSTIN
        $invalidReq = new Request([], [
            'name' => 'Invalid Co',
            'gstin' => 'INVALID_GSTIN_123',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/settings/company/update',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $invalidResp = $settingsController->updateCompany($invalidReq);
        $assert(
            'SETTINGS-05',
            'Submitting invalid GSTIN format returns HTTP 422 validation failure',
            $invalidResp->getStatusCode() === 422
        );
    }
}
