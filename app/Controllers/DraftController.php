<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\Config;
use WTSBill\Services\DraftService;

/**
 * Controller for In-Flight Form Auto-Saving, Draft Restoration, and Session Heartbeat.
 */
class DraftController
{
    /**
     * Auto-save active form data.
     * Route: POST /api/drafts/auto-save
     */
    public function autoSave(Request $req): Response
    {
        $userId = (int)(Session::getUserId() ?? 0);
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);

        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Unauthenticated session.'], 401);
        }

        $input = $req->isJson() ? $req->json() : $req->all();
        $formType = (string)($input['form_type'] ?? 'GENERAL');
        $formKey = (string)($input['form_key'] ?? $formType);
        $formData = (array)($input['form_data'] ?? []);

        if (empty($formData) && isset($input['fields'])) {
            $formData = (array)$input['fields'];
        }

        $res = DraftService::saveDraft($companyId, $branchId, $userId, $formType, $formKey, $formData);
        return Response::json($res);
    }

    /**
     * Check if a recoverable draft exists for a form.
     * Route: GET /api/drafts/check
     */
    public function check(Request $req): Response
    {
        $userId = (int)(Session::getUserId() ?? 0);
        $companyId = (int)(Session::getCompanyId() ?? 1);

        if ($userId <= 0) {
            return Response::json(['success' => false, 'draft' => null], 401);
        }

        $formType = (string)($req->query('form_type') ?? '');
        $formKey = (string)($req->query('form_key') ?? null);

        if (empty($formType)) {
            return Response::json(['success' => false, 'message' => 'Missing form_type parameter.'], 422);
        }

        $draft = DraftService::getDraft($companyId, $userId, $formType, $formKey);
        return Response::json([
            'success' => true,
            'has_draft' => ($draft !== null),
            'draft' => $draft,
        ]);
    }

    /**
     * Discard an active draft.
     * Route: POST /api/drafts/discard
     */
    public function discard(Request $req): Response
    {
        $userId = (int)(Session::getUserId() ?? 0);
        $companyId = (int)(Session::getCompanyId() ?? 1);

        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $input = $req->isJson() ? $req->json() : $req->all();
        $draftId = (int)($input['draft_id'] ?? 0);
        $formType = (string)($input['form_type'] ?? '');
        $formKey = (string)($input['form_key'] ?? null);

        if ($draftId > 0) {
            DraftService::discardDraftById($draftId, $companyId, $userId);
        } elseif (!empty($formType)) {
            DraftService::discardDraft($companyId, $userId, $formType, $formKey);
        }

        return Response::json(['success' => true, 'message' => 'Draft discarded successfully.']);
    }

    /**
     * Check session idle status and remaining time.
     * Route: GET /api/session/status
     */
    public function sessionStatus(Request $req): Response
    {
        $userId = (int)(Session::getUserId() ?? 0);
        if ($userId <= 0) {
            return Response::json(['authenticated' => false, 'remaining_seconds' => 0], 401);
        }

        $lastActivity = (int)Session::get('last_activity_at', time());
        $lifetime = (int)Config::get('session.lifetime', 900);
        $elapsed = time() - $lastActivity;
        $remaining = max(0, $lifetime - $elapsed);

        return Response::json([
            'authenticated' => true,
            'idle_seconds' => $elapsed,
            'remaining_seconds' => $remaining,
            'timeout_seconds' => $lifetime,
        ]);
    }

    /**
     * Heartbeat to actively renew session upon user action.
     * Route: POST /api/session/touch
     */
    public function touch(Request $req): Response
    {
        $userId = (int)(Session::getUserId() ?? 0);
        if ($userId <= 0) {
            return Response::json(['authenticated' => false], 401);
        }

        Session::set('last_activity_at', time());
        return Response::json(['authenticated' => true, 'remaining_seconds' => (int)Config::get('session.lifetime', 900)]);
    }
}

