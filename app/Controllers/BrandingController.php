<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Repositories\BranchRepository;
use WTSBill\Services\CompanyBrandingService;

/**
 * Controller for Centralized Company Branding Management.
 * Manages Company Logo, Authorized Signature, and Company Stamp assets and display rules.
 */
class BrandingController
{
    private BranchRepository $branchRepo;

    public function __construct(?BranchRepository $branchRepo = null)
    {
        $this->branchRepo = $branchRepo ?? new BranchRepository();
    }

    /**
     * View Company Branding management interface.
     * Route: GET /settings/branding
     */
    public function index(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userRole = Gate::role() ?: (Session::get('user_role') ?? 'guest');
        $isAdmin = in_array(strtolower($userRole), ['admin', 'superadmin', 'owner'], true) || Gate::can('settings.view');

        $branchIdQuery = $req->query('branch_id');
        $selectedBranchId = ($branchIdQuery !== null && $branchIdQuery !== '' && $branchIdQuery !== 'all' && (int)$branchIdQuery > 0)
            ? (int)$branchIdQuery
            : null;

        $branding = CompanyBrandingService::getCompanyBranding($companyId, $selectedBranchId);
        $branches = $this->branchRepo->findAllByCompany($companyId);

        if ($req->expectsJson()) {
            return Response::json([
                'success' => true,
                'branding' => $branding,
                'branches' => $branches,
            ]);
        }

        $html = View::renderWithLayout('settings/branding', [
            'title' => 'Company Branding Management',
            'currentUri' => '/settings/branding',
            'branding' => $branding,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'isAdmin' => $isAdmin,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Upload and update branding asset (logo, signature, or stamp).
     * Route: POST /settings/branding/upload
     */
    public function upload(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::get('user_id') ?? 1);

        $type = (string)($req->input('type') ?? $req->query('type') ?? '');
        $branchIdInput = $req->input('branch_id') ?? $req->query('branch_id');
        $branchId = ($branchIdInput !== null && $branchIdInput !== '' && (int)$branchIdInput > 0)
            ? (int)$branchIdInput
            : null;

        // Retrieve file from $_FILES or Request
        $file = $_FILES['asset_file'] ?? ($_FILES['file'] ?? null);
        if (!$file) {
            $errResp = ['success' => false, 'message' => 'No file was provided in the upload request.'];
            if ($req->expectsJson()) {
                return Response::json($errResp, 422);
            }
            Session::flash('error', $errResp['message']);
            return Response::redirect('/settings/branding' . ($branchId ? "?branch_id={$branchId}" : ''));
        }

        $result = CompanyBrandingService::saveAsset($companyId, $branchId, $type, $file, $userId);

        $isAjax = $req->expectsJson() || strtolower((string)$req->header('X-Requested-With')) === 'xmlhttprequest';
        if ($isAjax) {
            return Response::json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        return Response::redirect('/settings/branding' . ($branchId ? "?branch_id={$branchId}" : ''));
    }

    /**
     * Remove branding asset and reset to default.
     * Route: POST /settings/branding/remove
     */
    public function remove(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::get('user_id') ?? 1);

        $type = (string)($req->input('type') ?? $req->query('type') ?? '');
        $branchIdInput = $req->input('branch_id') ?? $req->query('branch_id');
        $branchId = ($branchIdInput !== null && $branchIdInput !== '' && (int)$branchIdInput > 0)
            ? (int)$branchIdInput
            : null;

        $removed = CompanyBrandingService::removeAsset($companyId, $branchId, $type, $userId);

        $msg = $removed
            ? ucfirst($type) . ' has been reset to default successfully.'
            : 'Failed to remove branding asset.';

        $isAjax = $req->expectsJson() || strtolower((string)$req->header('X-Requested-With')) === 'xmlhttprequest';
        if ($isAjax) {
            return Response::json(['success' => $removed, 'message' => $msg], $removed ? 200 : 422);
        }

        Session::flash($removed ? 'success' : 'error', $msg);
        return Response::redirect('/settings/branding' . ($branchId ? "?branch_id={$branchId}" : ''));
    }

    /**
     * Save branding display options (size, alignment, document visibility switches).
     * Route: POST /settings/branding/settings
     */
    public function saveSettings(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::get('user_id') ?? 1);

        $branchIdInput = $req->input('branch_id') ?? $req->query('branch_id');
        $branchId = ($branchIdInput !== null && $branchIdInput !== '' && (int)$branchIdInput > 0)
            ? (int)$branchIdInput
            : null;

        $saved = CompanyBrandingService::updateDisplaySettings($companyId, $branchId, $req->all(), $userId);

        $msg = $saved ? 'Branding display settings updated successfully.' : 'Failed to update branding settings.';

        $isAjax = $req->expectsJson() || strtolower((string)$req->header('X-Requested-With')) === 'xmlhttprequest';
        if ($isAjax) {
            return Response::json(['success' => $saved, 'message' => $msg], $saved ? 200 : 422);
        }

        Session::flash($saved ? 'success' : 'error', $msg);
        return Response::redirect('/settings/branding' . ($branchId ? "?branch_id={$branchId}" : ''));
    }
}
