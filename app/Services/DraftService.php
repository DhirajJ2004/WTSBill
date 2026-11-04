<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * Service for Automatic In-Flight Form Draft Persistence & Recovery.
 * Preserves unfinished invoices, quotations, bills, customer/supplier profiles,
 * and expenses without polluting accounting ledgers or production reports.
 */
class DraftService
{
    /**
     * Save or update an in-flight form draft.
     */
    public static function saveDraft(
        int $companyId,
        int $branchId,
        int $userId,
        string $formType,
        ?string $formKey,
        array $formData
    ): array {
        $formType = strtoupper(trim($formType));
        $formKey = $formKey ? trim($formKey) : $formType;

        // Clean out sensitive or non-data internal fields
        unset($formData['_csrf_token'], $formData['csrf_token'], $formData['password'], $formData['password_confirmation']);

        // Check if there is meaningful data to save
        $hasData = false;
        foreach ($formData as $k => $v) {
            if ($v !== '' && $v !== null && $v !== [] && $k !== 'status') {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            return [
                'success' => false,
                'message' => 'No meaningful form data to save.',
            ];
        }

        $json = json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $existing = Database::selectOne(
            "SELECT id FROM user_drafts 
             WHERE company_id = ? AND user_id = ? AND form_type = ? AND form_key = ? 
             LIMIT 1",
            [$companyId, $userId, $formType, $formKey]
        );

        if ($existing) {
            $draftId = (int)$existing['id'];
            Database::update('user_drafts', [
                'branch_id' => $branchId,
                'form_data_json' => $json,
                'status' => 'DRAFT',
                'expires_at' => $expiresAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$draftId]);
        } else {
            $draftId = (int)Database::insert('user_drafts', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'form_type' => $formType,
                'form_key' => $formKey,
                'form_data_json' => $json,
                'status' => 'DRAFT',
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'success' => true,
            'draft_id' => $draftId,
            'form_type' => $formType,
            'form_key' => $formKey,
            'saved_at' => date('Y-m-d H:i:s'),
            'message' => 'Unfinished work saved automatically as a draft.',
        ];
    }

    /**
     * Retrieve active draft for user and form type.
     */
    public static function getDraft(
        int $companyId,
        int $userId,
        string $formType,
        ?string $formKey = null
    ): ?array {
        $formType = strtoupper(trim($formType));
        $params = [$companyId, $userId, $formType];
        $sql = "SELECT * FROM user_drafts 
                WHERE company_id = ? AND user_id = ? AND form_type = ? AND status = 'DRAFT'";

        if ($formKey !== null && $formKey !== '') {
            $sql .= " AND form_key = ?";
            $params[] = trim($formKey);
        }

        $sql .= " AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1";

        $draft = Database::selectOne($sql, $params);
        if (!$draft) {
            return null;
        }

        $decoded = json_decode((string)$draft['form_data_json'], true);
        return [
            'id' => (int)$draft['id'],
            'company_id' => (int)$draft['company_id'],
            'branch_id' => (int)$draft['branch_id'],
            'form_type' => $draft['form_type'],
            'form_key' => $draft['form_key'],
            'data' => is_array($decoded) ? $decoded : [],
            'saved_at' => $draft['updated_at'] ?? $draft['created_at'],
        ];
    }

    /**
     * Discard an existing draft.
     */
    public static function discardDraft(int $companyId, int $userId, string $formType, ?string $formKey = null): bool
    {
        $formType = strtoupper(trim($formType));
        $params = [$companyId, $userId, $formType];
        $sql = "DELETE FROM user_drafts WHERE company_id = ? AND user_id = ? AND form_type = ?";

        if ($formKey !== null && $formKey !== '') {
            $sql .= " AND form_key = ?";
            $params[] = trim($formKey);
        }

        Database::pdo()->prepare($sql)->execute($params);
        return true;
    }

    /**
     * Discard draft by primary key.
     */
    public static function discardDraftById(int $draftId, int $companyId, int $userId): bool
    {
        Database::delete('user_drafts', 'id = ? AND company_id = ? AND user_id = ?', [$draftId, $companyId, $userId]);
        return true;
    }
}
