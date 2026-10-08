<?php

namespace App\Services;

use App\Models\BranchSetting;
use App\Models\Branch;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class SettingService
{
    /**
     * Get settings for a company and branch.
     */
    public static function getSettings(int $companyId, int $branchId): array
    {
        $setting = BranchSetting::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->first();

        if (!$setting) {
            $setting = BranchSetting::create([
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'invoice_prefix'   => 'INV-',
                'quotation_prefix' => 'QTN-',
                'purchase_prefix'  => 'PUR-',
                'receipt_prefix'   => 'RCP-',
            ]);
        }

        $res = $setting->toArray();
        if (!empty($res['settings_json']) && is_string($res['settings_json'])) {
            $res['extra_settings'] = json_decode($res['settings_json'], true) ?: [];
        } else {
            $res['extra_settings'] = [];
        }

        return $res;
    }

    /**
     * Update settings for a branch / company.
     */
    public static function updateSettings(int $companyId, int $branchId, array $data, string $userName = 'Admin'): array
    {
        $setting = BranchSetting::firstOrCreate(
            ['company_id' => $companyId, 'branch_id' => $branchId]
        );

        $oldValues = $setting->toArray();

        if (isset($data['invoice_prefix'])) $setting->invoice_prefix = trim($data['invoice_prefix']);
        if (isset($data['quotation_prefix'])) $setting->quotation_prefix = trim($data['quotation_prefix']);
        if (isset($data['purchase_prefix'])) $setting->purchase_prefix = trim($data['purchase_prefix']);
        if (isset($data['receipt_prefix'])) $setting->receipt_prefix = trim($data['receipt_prefix']);
        if (isset($data['default_warehouse_id'])) $setting->default_warehouse_id = $data['default_warehouse_id'] ? (int)$data['default_warehouse_id'] : null;
        if (isset($data['logo_url'])) $setting->logo_url = trim($data['logo_url']);
        if (isset($data['terms_conditions'])) $setting->terms_conditions = trim($data['terms_conditions']);
        if (isset($data['bank_account_id'])) $setting->bank_account_id = $data['bank_account_id'] ? (int)$data['bank_account_id'] : null;

        if (isset($data['extra_settings']) && is_array($data['extra_settings'])) {
            $setting->settings_json = json_encode($data['extra_settings']);
        }

        $setting->save();

        AuditLogService::record([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'user_name'   => $userName,
            'action'      => 'UPDATE_SETTINGS',
            'entity'      => 'BranchSetting',
            'entity_id'   => $setting->id,
            'description' => "Updated settings for Branch #{$branchId}",
            'old_values'  => $oldValues,
            'new_values'  => $setting->toArray(),
        ]);

        return [
            'success'  => true,
            'settings' => $setting,
            'message'  => 'Settings saved successfully.'
        ];
    }
}
