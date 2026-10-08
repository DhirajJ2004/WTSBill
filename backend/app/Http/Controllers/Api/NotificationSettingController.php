<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationTemplate;
use App\Models\NotificationRule;
use App\Models\BusinessCommunicationSetting;
use App\Notifications\Services\NotificationPreferenceService;
use App\Notifications\Templates\TemplateService;
use App\Notifications\Services\ReminderService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class NotificationSettingController
{
    /**
     * Get communication & channel preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        $prefs = NotificationPreferenceService::getPreferences($companyId, $user?->id);
        $setting = BusinessCommunicationSetting::where('company_id', $companyId)->first();

        return response()->json([
            'status' => 'success',
            'preferences' => $prefs,
            'settings' => $setting,
        ]);
    }

    /**
     * Save communication preferences.
     */
    public function savePreferences(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'notifications', 'manage')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $categories = $request->input('preferences', []);
        foreach ($categories as $cat => $channels) {
            NotificationPreferenceService::savePreference($companyId, $cat, $channels, $user?->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification preferences saved successfully',
        ]);
    }

    /**
     * List templates.
     */
    public function listTemplates(): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        TemplateService::ensureDefaultTemplates($companyId);

        $templates = NotificationTemplate::where('company_id', $companyId)->get();

        return response()->json([
            'status' => 'success',
            'templates' => $templates,
        ]);
    }

    /**
     * Preview template.
     */
    public function previewTemplate(int $id, Request $request): JsonResponse
    {
        try {
            $preview = TemplateService::preview($id, $request->input('sample_data', []));
            return response()->json([
                'status' => 'success',
                'preview' => $preview,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
