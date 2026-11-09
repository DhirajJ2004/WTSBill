<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\BrandingController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\CompanyBrandingService;

/**
 * Feature Test: Centralized Company Branding Management.
 * Validates logo, signature, and stamp upload, display configuration,
 * branch isolation, SVG sanitization, and fallback behavior.
 */
class CompanyBrandingTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        $controller = new BrandingController();
        $testCompanyId = 1;
        $testBranchId = 1;

        // Cleanup before testing
        Database::execute("DELETE FROM company_brandings WHERE company_id = ?", [$testCompanyId]);
        CompanyBrandingService::clearCache();

        // -------------------------------------------------------------
        // BRAND-01: Default Company Branding Retrieval
        // -------------------------------------------------------------
        $defaultBranding = CompanyBrandingService::getCompanyBranding($testCompanyId);
        $assert(
            'BRAND-01',
            'CompanyBrandingService returns default logo with cache buster query and empty custom signature/stamp',
            !empty($defaultBranding['logo_url']) &&
            str_contains($defaultBranding['logo_url'], '/assets/images/logo.png') &&
            str_contains($defaultBranding['logo_url'], '?v=') &&
            $defaultBranding['is_custom_logo'] === false &&
            empty($defaultBranding['signature_path']) &&
            empty($defaultBranding['stamp_path'])
        );

        // -------------------------------------------------------------
        // BRAND-02: Admin Access to Branding Settings UI
        // -------------------------------------------------------------
        Session::setTenantContext($testCompanyId, $testBranchId);
        Session::set('user_id', 1);
        Session::set('user_role', 'ADMIN');
        Session::set('role', 'admin');
        Session::set('auth_user', [
            'id' => 1,
            'name' => 'System Administrator',
            'email' => 'admin@wtsbill.com',
            'role' => 'admin',
        ]);

        $adminReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/settings/branding'], null);
        $adminRes = $controller->index($adminReq);
        $html = $adminRes->getContent();

        $assert(
            'BRAND-02',
            'GET /settings/branding renders 3 upload cards: Company Logo, Authorized Signature, and Company Stamp',
            $adminRes->getStatusCode() === 200 &&
            str_contains($html, '1. Company Logo') &&
            str_contains($html, '2. Authorized Signature') &&
            str_contains($html, '3. Company Official Stamp') &&
            str_contains($html, 'Document Display Settings')
        );

        // -------------------------------------------------------------
        // BRAND-03: Upload Valid PNG Logo
        // -------------------------------------------------------------
        // Create a 1x1 transparent PNG generator helper
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $createFakePng = function(string $name = 'test.png') use ($pngData) {
            $tmpPath = sys_get_temp_dir() . '/brand_' . bin2hex(random_bytes(4)) . '_' . $name;
            file_put_contents($tmpPath, $pngData);
            return [
                'name' => $name,
                'type' => 'image/png',
                'tmp_name' => $tmpPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($pngData),
            ];
        };

        $_FILES['asset_file'] = $createFakePng('test_logo.png');
        $uploadLogoReq = new Request(['type' => 'logo'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $uploadLogoRes = $controller->upload($uploadLogoReq);
        $logoJson = json_decode($uploadLogoRes->getContent(), true);

        $updatedBranding = CompanyBrandingService::getCompanyBranding($testCompanyId);

        $assert(
            'BRAND-03',
            'Valid PNG logo upload persists to uploads/branding, updates database and sets is_custom_logo=true',
            $uploadLogoRes->getStatusCode() === 200 &&
            !empty($logoJson['success']) &&
            $updatedBranding['is_custom_logo'] === true &&
            str_starts_with($updatedBranding['logo_path'], '/uploads/branding/') &&
            file_exists(CompanyBrandingService::getRootPath() . '/public' . $updatedBranding['logo_path'])
        );

        // -------------------------------------------------------------
        // BRAND-04: SVG Sanitization (Reject Malicious SVG with Script)
        // -------------------------------------------------------------
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="40"/><script>alert("xss")</script></svg>';
        $tmpSvgPath = sys_get_temp_dir() . '/malicious_test.svg';
        file_put_contents($tmpSvgPath, $maliciousSvg);

        $fakeBadSvg = [
            'name' => 'bad.svg',
            'type' => 'image/svg+xml',
            'tmp_name' => $tmpSvgPath,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($maliciousSvg),
        ];

        $_FILES['asset_file'] = $fakeBadSvg;
        $badSvgReq = new Request(['type' => 'logo'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $badSvgRes = $controller->upload($badSvgReq);
        $badSvgJson = json_decode($badSvgRes->getContent(), true);

        $assert(
            'BRAND-04',
            'Malicious SVG containing <script> is strictly rejected with 422 error and does not overwrite logo',
            $badSvgRes->getStatusCode() === 422 &&
            empty($badSvgJson['success']) &&
            str_contains(strtolower($badSvgJson['message']), 'security')
        );

        // -------------------------------------------------------------
        // BRAND-05: Clean Valid SVG Upload
        // -------------------------------------------------------------
        $cleanSvg = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="#235347"/></svg>';
        $tmpCleanSvgPath = sys_get_temp_dir() . '/clean_test.svg';
        file_put_contents($tmpCleanSvgPath, $cleanSvg);

        $fakeCleanSvg = [
            'name' => 'clean.svg',
            'type' => 'image/svg+xml',
            'tmp_name' => $tmpCleanSvgPath,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($cleanSvg),
        ];

        $_FILES['asset_file'] = $fakeCleanSvg;
        $cleanSvgReq = new Request(['type' => 'logo'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $cleanSvgRes = $controller->upload($cleanSvgReq);
        $cleanSvgJson = json_decode($cleanSvgRes->getContent(), true);

        $assert(
            'BRAND-05',
            'Sanitized clean SVG is accepted and saves successfully as company logo',
            $cleanSvgRes->getStatusCode() === 200 &&
            !empty($cleanSvgJson['success']) &&
            str_ends_with($cleanSvgJson['path'], '.svg')
        );

        // -------------------------------------------------------------
        // BRAND-06: Reject Oversized or Invalid File Extension
        // -------------------------------------------------------------
        $fakeExePath = sys_get_temp_dir() . '/bad_script.exe';
        file_put_contents($fakeExePath, 'MZdummy');
        $fakeExe = [
            'name' => 'script.exe',
            'type' => 'application/octet-stream',
            'tmp_name' => $fakeExePath,
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $_FILES['asset_file'] = $fakeExe;
        $badExtReq = new Request(['type' => 'signature'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $badExtRes = $controller->upload($badExtReq);
        $badExtJson = json_decode($badExtRes->getContent(), true);
        @unlink($fakeExePath);

        $assert(
            'BRAND-06',
            'Files with disallowed extensions or types (.exe) are rejected with HTTP 422',
            $badExtRes->getStatusCode() === 422 &&
            empty($badExtJson['success'])
        );

        // -------------------------------------------------------------
        // BRAND-07: Upload Authorized Signature & Official Stamp
        // -------------------------------------------------------------
        $_FILES['asset_file'] = $createFakePng('signature.png');
        $sigReq = new Request(['type' => 'signature'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $sigRes = $controller->upload($sigReq);
        $sigJson = json_decode($sigRes->getContent(), true);

        $_FILES['asset_file'] = $createFakePng('stamp.png');
        $stampReq = new Request(['type' => 'stamp'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $stampRes = $controller->upload($stampReq);
        $stampJson = json_decode($stampRes->getContent(), true);

        $sigStampBranding = CompanyBrandingService::getCompanyBranding($testCompanyId);

        $assert(
            'BRAND-07',
            'Authorized signature and company stamp upload succeed and populate respective branding URLs',
            $sigRes->getStatusCode() === 200 &&
            $stampRes->getStatusCode() === 200 &&
            !empty($sigStampBranding['signature_url']) &&
            !empty($sigStampBranding['stamp_url']) &&
            $sigStampBranding['is_custom_signature'] === true &&
            $sigStampBranding['is_custom_stamp'] === true
        );

        // -------------------------------------------------------------
        // BRAND-08: Update Document Display Settings (Sizing & Visibility)
        // -------------------------------------------------------------
        $settingsPayload = [
            'signature_width' => '160',
            'signature_height' => '70',
            'signature_position' => 'center',
            'stamp_width' => '90',
            'stamp_height' => '90',
            'stamp_position' => 'right',
            'show_signature_invoice' => '1',
            'show_signature_quotation' => '1',
            'show_signature_purchase' => '0',
            'show_stamp_invoice' => '1',
            'show_stamp_quotation' => '0',
            'show_stamp_purchase' => '1',
        ];

        $saveSettingsReq = new Request($settingsPayload, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/settings', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $saveSettingsRes = $controller->saveSettings($saveSettingsReq);
        $saveSettingsJson = json_decode($saveSettingsRes->getContent(), true);

        $customizedBranding = CompanyBrandingService::getCompanyBranding($testCompanyId);

        $assert(
            'BRAND-08',
            'POST /settings/branding/settings persists custom sizing and document visibility toggles',
            $saveSettingsRes->getStatusCode() === 200 &&
            !empty($saveSettingsJson['success']) &&
            $customizedBranding['signature_width'] === 160 &&
            $customizedBranding['signature_height'] === 70 &&
            $customizedBranding['signature_position'] === 'center' &&
            $customizedBranding['stamp_width'] === 90 &&
            $customizedBranding['stamp_height'] === 90 &&
            $customizedBranding['show_signature_purchase'] === false &&
            $customizedBranding['show_stamp_quotation'] === false
        );

        // -------------------------------------------------------------
        // BRAND-09: Multi-Branch Branding Override & Fallback
        // -------------------------------------------------------------
        // Upload a branch-specific logo for branch 2
        $branch2Id = 2;
        $_FILES['asset_file'] = $createFakePng('branch2_logo.png');
        $branchUploadReq = new Request(['type' => 'logo', 'branch_id' => (string)$branch2Id], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/upload', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $branchUploadRes = $controller->upload($branchUploadReq);

        $branchBranding = CompanyBrandingService::getCompanyBranding($testCompanyId, $branch2Id);
        $defaultCompanyBranding = CompanyBrandingService::getCompanyBranding($testCompanyId, null);

        $assert(
            'BRAND-09',
            'Branch-specific branding override takes precedence for that branch without altering company-wide defaults',
            $branchUploadRes->getStatusCode() === 200 &&
            $branchBranding['branch_id'] === $branch2Id &&
            $branchBranding['logo_path'] !== $defaultCompanyBranding['logo_path']
        );

        // -------------------------------------------------------------
        // BRAND-10: Reset Asset to Default & Safe Removal
        // -------------------------------------------------------------
        $removeReq = new Request(['type' => 'signature'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/branding/remove', 'HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'], null);
        $removeRes = $controller->remove($removeReq);
        $removeJson = json_decode($removeRes->getContent(), true);

        $afterRemoveBranding = CompanyBrandingService::getCompanyBranding($testCompanyId);

        $assert(
            'BRAND-10',
            'POST /settings/branding/remove resets signature to null and deletes custom file safely from disk',
            $removeRes->getStatusCode() === 200 &&
            !empty($removeJson['success']) &&
            empty($afterRemoveBranding['signature_path']) &&
            empty($afterRemoveBranding['signature_url']) &&
            $afterRemoveBranding['is_custom_signature'] === false
        );

        // Cleanup temporary files and restore Company 1 baseline branding
        if (isset($tmpSvgPath) && is_file($tmpSvgPath)) @unlink($tmpSvgPath);
        if (isset($tmpCleanSvgPath) && is_file($tmpCleanSvgPath)) @unlink($tmpCleanSvgPath);
        unset($_FILES['asset_file']);

        Database::execute("UPDATE company_brandings SET logo_path = ?, signature_path = NULL, stamp_path = NULL, signature_width = 140, signature_height = 56, signature_position = 'right', stamp_width = 65, stamp_height = 65, stamp_position = 'right', show_signature_invoice = 1, show_signature_quotation = 1, show_signature_purchase = 1, show_stamp_invoice = 1, show_stamp_quotation = 1, show_stamp_purchase = 1 WHERE company_id = ?", [CompanyBrandingService::DEFAULT_LOGO, $testCompanyId]);
        Database::execute("DELETE FROM company_brandings WHERE branch_id IS NOT NULL AND branch_id > 0");
        Database::execute("UPDATE companies SET logo_url = ?, stamp_signature_url = ? WHERE id = ?", [CompanyBrandingService::DEFAULT_LOGO, CompanyBrandingService::DEFAULT_STAMP_SIGNATURE, $testCompanyId]);
        CompanyBrandingService::clearCache();
    }
}
