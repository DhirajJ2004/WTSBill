<?php

namespace App\Notifications\Services;

use App\Models\NotificationPreference;
use App\Models\BusinessCommunicationSetting;

class NotificationPreferenceService
{
    /**
     * Get user or company notification preferences.
     */
    public static function getPreferences(int $companyId, ?int $userId = null): array
    {
        $categories = ['PAYMENTS', 'SALES', 'PURCHASES', 'INVENTORY', 'ACCOUNTING', 'GST', 'BANKING', 'TRANSFERS', 'SYSTEM'];
        $rows = NotificationPreference::where('company_id', $companyId)
            ->where(function ($q) use ($userId) {
                if ($userId) {
                    $q->where('user_id', $userId)->orWhereNull('user_id');
                } else {
                    $q->whereNull('user_id');
                }
            })
            ->get()
            ->keyBy('category');

        $result = [];
        foreach ($categories as $cat) {
            $pref = $rows->get($cat);
            $result[$cat] = [
                'category' => $cat,
                'in_app_enabled' => $pref ? (bool)$pref->in_app_enabled : true,
                'email_enabled' => $pref ? (bool)$pref->email_enabled : false,
                'whatsapp_enabled' => $pref ? (bool)$pref->whatsapp_enabled : false,
                'sms_enabled' => $pref ? (bool)$pref->sms_enabled : false,
            ];
        }

        return $result;
    }

    /**
     * Save category notification preference.
     */
    public static function savePreference(int $companyId, string $category, array $channels, ?int $userId = null): NotificationPreference
    {
        $category = strtoupper(trim($category));
        $pref = NotificationPreference::where('company_id', $companyId)
            ->where('category', $category)
            ->where('user_id', $userId)
            ->first();

        if (!$pref) {
            $pref = new NotificationPreference([
                'company_id' => $companyId,
                'user_id' => $userId,
                'category' => $category,
            ]);
        }

        if (isset($channels['in_app_enabled'])) $pref->in_app_enabled = (bool)$channels['in_app_enabled'];
        if (isset($channels['email_enabled'])) $pref->email_enabled = (bool)$channels['email_enabled'];
        if (isset($channels['whatsapp_enabled'])) $pref->whatsapp_enabled = (bool)$channels['whatsapp_enabled'];
        if (isset($channels['sms_enabled'])) $pref->sms_enabled = (bool)$channels['sms_enabled'];

        $pref->save();
        return $pref;
    }

    /**
     * Check if a specific channel is active and enabled for this category.
     */
    public static function isChannelEnabled(int $companyId, string $category, string $channel, ?int $userId = null): bool
    {
        $channel = strtoupper($channel);
        if ($channel === 'IN_APP') return true;

        $pref = NotificationPreference::where('company_id', $companyId)
            ->where('category', $category)
            ->where(function ($q) use ($userId) {
                if ($userId) $q->where('user_id', $userId)->orWhereNull('user_id');
                else $q->whereNull('user_id');
            })
            ->first();

        if (!$pref) return false;

        return match ($channel) {
            'EMAIL' => (bool)$pref->email_enabled,
            'WHATSAPP' => (bool)$pref->whatsapp_enabled,
            'SMS' => (bool)$pref->sms_enabled,
            default => false,
        };
    }
}
