<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Centralized Company Branding Service for WTSBill ERP.
 * 
 * Single source of truth for Company Logo, Authorized Signature, and Company Stamp assets,
 * dimensions, document visibility, and multi-tenant / multi-branch isolation.
 */
class CompanyBrandingService
{
    public const DEFAULT_LOGO = '/assets/images/logo.png';
    public const DEFAULT_STAMP_SIGNATURE = '/assets/images/wts-signature-stamp.jpg';

    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    public const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/pjpeg',
        'image/webp',
        'image/svg+xml',
        'image/svg',
    ];
    public const MAX_FILE_SIZE = 2097152; // 2 MB

    /**
     * In-memory cache per request execution.
     */
    private static array $cache = [];

    /**
     * Clear runtime cache (useful during tests or after updates).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Retrieve complete branding configuration for a company and optional branch.
     *
     * @param int $companyId Tenant company ID
     * @param int|null $branchId Optional operating branch ID
     * @return array Standardized branding configuration
     */
    public static function getCompanyBranding(int $companyId = 1, ?int $branchId = null): array
    {
        $cacheKey = "{$companyId}_" . ($branchId ?? 'main');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // 1. If branchId specified, check for branch override
        $row = null;
        if ($branchId !== null && $branchId > 0) {
            $row = Database::selectOne(
                "SELECT * FROM company_brandings WHERE company_id = ? AND branch_id = ? LIMIT 1",
                [$companyId, $branchId]
            );
        }

        // 2. Fall back to company-wide branding (branch_id IS NULL)
        if (!$row) {
            $row = Database::selectOne(
                "SELECT * FROM company_brandings WHERE company_id = ? AND (branch_id IS NULL OR branch_id = 0) LIMIT 1",
                [$companyId]
            );
        }

        // 3. Fall back to companies table legacy columns if table is fresh
        $compRow = Database::selectOne(
            "SELECT id, name, legal_name, logo_url, stamp_signature_url, updated_at FROM companies WHERE id = ? LIMIT 1",
            [$companyId]
        );

        $defaultLogo = !empty($compRow['logo_url']) ? $compRow['logo_url'] : self::DEFAULT_LOGO;
        $defaultStampSig = !empty($compRow['stamp_signature_url']) ? $compRow['stamp_signature_url'] : self::DEFAULT_STAMP_SIGNATURE;

        $logoPath = $row['logo_path'] ?? $defaultLogo;
        $sigPath = $row['signature_path'] ?? null;
        $stampPath = $row['stamp_path'] ?? null;

        // Auto-heal: verify custom upload files exist on disk, fallback cleanly if missing
        $rootPub = self::getRootPath() . '/public';
        if (!empty($logoPath) && str_starts_with($logoPath, '/uploads/') && !is_file($rootPub . $logoPath)) {
            $logoPath = self::DEFAULT_LOGO;
        }
        if (!empty($sigPath) && str_starts_with($sigPath, '/uploads/') && !is_file($rootPub . $sigPath)) {
            $sigPath = null;
        }
        if (!empty($stampPath) && str_starts_with($stampPath, '/uploads/') && !is_file($rootPub . $stampPath)) {
            $stampPath = null;
        }

        // If row is not created yet, seed company-wide defaults
        if (!$row && $compRow) {
            self::seedDefaultCompanyBranding($companyId, $defaultLogo, $defaultStampSig);
            return self::getCompanyBranding($companyId, $branchId);
        }

        $updatedAt = $row['updated_at'] ?? ($compRow['updated_at'] ?? date('Y-m-d H:i:s'));
        $cacheBuster = '?v=' . strtotime((string)$updatedAt);

        $branding = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'logo_path' => $logoPath,
            'logo_url' => $logoPath . $cacheBuster,
            'is_custom_logo' => !empty($logoPath) && !str_starts_with($logoPath, '/assets/images/'),
            'signature_path' => $sigPath,
            'signature_url' => $sigPath ? ($sigPath . $cacheBuster) : null,
            'is_custom_signature' => !empty($sigPath) && !str_starts_with($sigPath, '/assets/images/'),
            'stamp_path' => $stampPath,
            'stamp_url' => $stampPath ? ($stampPath . $cacheBuster) : null,
            'is_custom_stamp' => !empty($stampPath) && !str_starts_with($stampPath, '/assets/images/'),
            'signature_width' => (int)($row['signature_width'] ?? 140),
            'signature_height' => (int)($row['signature_height'] ?? 56),
            'signature_position' => $row['signature_position'] ?? 'right',
            'stamp_width' => (int)($row['stamp_width'] ?? 65),
            'stamp_height' => (int)($row['stamp_height'] ?? 65),
            'stamp_position' => $row['stamp_position'] ?? 'right',
            'show_signature_invoice' => (bool)($row['show_signature_invoice'] ?? 1),
            'show_signature_quotation' => (bool)($row['show_signature_quotation'] ?? 1),
            'show_signature_purchase' => (bool)($row['show_signature_purchase'] ?? 1),
            'show_stamp_invoice' => (bool)($row['show_stamp_invoice'] ?? 1),
            'show_stamp_quotation' => (bool)($row['show_stamp_quotation'] ?? 1),
            'show_stamp_purchase' => (bool)($row['show_stamp_purchase'] ?? 1),
            'updated_at' => $updatedAt,
        ];

        self::$cache[$cacheKey] = $branding;
        return $branding;
    }

    /**
     * Retrieve global company logo URL.
     */
    public static function getCompanyLogo(int $companyId = 1, ?int $branchId = null): string
    {
        $branding = self::getCompanyBranding($companyId, $branchId);
        return $branding['logo_url'];
    }

    /**
     * Retrieve authorized signature information and display configuration.
     */
    public static function getCompanySignature(int $companyId = 1, ?int $branchId = null): array
    {
        $b = self::getCompanyBranding($companyId, $branchId);
        return [
            'url' => $b['signature_url'],
            'path' => $b['signature_path'],
            'is_custom' => $b['is_custom_signature'],
            'width' => $b['signature_width'],
            'height' => $b['signature_height'],
            'position' => $b['signature_position'],
            'show_invoice' => $b['show_signature_invoice'],
            'show_quotation' => $b['show_signature_quotation'],
            'show_purchase' => $b['show_signature_purchase'],
        ];
    }

    /**
     * Retrieve company stamp information and display configuration.
     */
    public static function getCompanyStamp(int $companyId = 1, ?int $branchId = null): array
    {
        $b = self::getCompanyBranding($companyId, $branchId);
        return [
            'url' => $b['stamp_url'],
            'path' => $b['stamp_path'],
            'is_custom' => $b['is_custom_stamp'],
            'width' => $b['stamp_width'],
            'height' => $b['stamp_height'],
            'position' => $b['stamp_position'],
            'show_invoice' => $b['show_stamp_invoice'],
            'show_quotation' => $b['show_stamp_quotation'],
            'show_purchase' => $b['show_stamp_purchase'],
        ];
    }

    /**
     * Upload and save a branding asset (logo, signature, or stamp).
     *
     * @param int $companyId
     * @param int|null $branchId
     * @param string $type 'logo', 'signature', or 'stamp'
     * @param array $file $_FILES entry
     * @param int|null $userId
     * @return array Result array ['success' => bool, 'message' => string, 'path' => string, 'url' => string]
     */
    public static function saveAsset(int $companyId, ?int $branchId, string $type, array $file, ?int $userId = null): array
    {
        if (!in_array($type, ['logo', 'signature', 'stamp'], true)) {
            return ['success' => false, 'message' => 'Invalid asset type specified.'];
        }

        // 1. Upload Error check
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error'] ?? -1) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size (2MB).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'File upload error occurred.',
            };
            return ['success' => false, 'message' => $msg];
        }

        // 2. File Size check (2MB max)
        if (($file['size'] ?? 0) > self::MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds maximum allowed limit of 2MB.'];
        }

        if (($file['size'] ?? 0) <= 0) {
            return ['success' => false, 'message' => 'Uploaded file is empty.'];
        }

        // 3. Extension check
        $originalName = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => "Unsupported file extension '.{$ext}'. Allowed: PNG, JPG, JPEG, WEBP, SVG."];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            return ['success' => false, 'message' => 'Temporary uploaded file could not be read.'];
        }

        // 4. MIME Type Verification
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : mime_content_type($tmpPath);
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($ext === 'svg') {
            // Some systems identify SVG as text/plain or text/xml
            if (!in_array($mime, ['image/svg+xml', 'image/svg', 'text/xml', 'text/plain'], true)) {
                return ['success' => false, 'message' => 'Invalid MIME type for SVG image.'];
            }
            // Sanitize SVG content
            $svgContent = file_get_contents($tmpPath);
            if (!self::isValidAndSafeSvg($svgContent)) {
                return ['success' => false, 'message' => 'SVG file failed security validation: contains disallowed scripts, objects, or malicious payloads.'];
            }
        } else {
            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                return ['success' => false, 'message' => "Invalid file format detected: {$mime}."];
            }
            // Verify raster image dimensions and integrity
            $imgInfo = @getimagesize($tmpPath);
            if (!$imgInfo || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                return ['success' => false, 'message' => 'Corrupted or invalid image file.'];
            }
        }

        // 5. Ensure upload destination directory exists
        $uploadDir = self::getUploadDirectory();
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        // 6. Generate safe unique filename
        $safePrefix = "branding_{$type}_c{$companyId}_" . ($branchId ? "b{$branchId}" : "main");
        $randomHash = bin2hex(random_bytes(6));
        $newFilename = "{$safePrefix}_{$randomHash}_{$ext}." . $ext;
        $targetAbsolutePath = $uploadDir . '/' . $newFilename;
        $relativeWebPath = '/uploads/branding/' . $newFilename;

        // 7. Move file securely
        $moved = @move_uploaded_file($tmpPath, $targetAbsolutePath);
        if (!$moved) {
            // Fallback copy if move_uploaded_file fails in synthetic/CLI environments
            $moved = @copy($tmpPath, $targetAbsolutePath);
            @unlink($tmpPath);
        }

        if (!$moved || !file_exists($targetAbsolutePath)) {
            return ['success' => false, 'message' => 'Failed to persist uploaded asset to disk.'];
        }

        // 8. Retrieve existing asset to delete old custom file after saving new one
        $existing = self::getCompanyBranding($companyId, $branchId);
        $oldPath = match ($type) {
            'logo' => $existing['logo_path'],
            'signature' => $existing['signature_path'],
            'stamp' => $existing['stamp_path'],
        };

        // 9. Persist to company_brandings table
        self::ensureRecordExists($companyId, $branchId);
        $colName = "{$type}_path";
        Database::execute(
            "UPDATE company_brandings SET {$colName} = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))",
            [$relativeWebPath, $userId, $companyId, $branchId, $branchId]
        );

        // 10. Synchronize with legacy columns on companies and branches
        if ($branchId === null || $branchId === 0) {
            if ($type === 'logo') {
                Database::execute("UPDATE companies SET logo_url = ?, updated_at = NOW() WHERE id = ?", [$relativeWebPath, $companyId]);
            } elseif ($type === 'signature' || $type === 'stamp') {
                Database::execute("UPDATE companies SET stamp_signature_url = ?, updated_at = NOW() WHERE id = ?", [$relativeWebPath, $companyId]);
            }
        } else {
            if ($type === 'signature' || $type === 'stamp') {
                Database::execute("UPDATE branches SET stamp_signature_url = ?, updated_at = NOW() WHERE id = ? AND company_id = ?", [$relativeWebPath, $branchId, $companyId]);
            }
        }

        // 11. Clean up old custom file from disk if it was stored in /uploads/branding/
        if (!empty($oldPath) && str_starts_with($oldPath, '/uploads/branding/') && $oldPath !== $relativeWebPath) {
            $oldDiskPath = self::getRootPath() . '/public' . $oldPath;
            if (is_file($oldDiskPath)) {
                @unlink($oldDiskPath);
            }
        }

        // Invalidate cache
        self::clearCache();

        return [
            'success' => true,
            'message' => ucfirst($type) . ' uploaded and updated successfully.',
            'path' => $relativeWebPath,
            'url' => $relativeWebPath . '?v=' . time(),
        ];
    }

    /**
     * Remove an asset and reset it to default or NULL.
     */
    public static function removeAsset(int $companyId, ?int $branchId, string $type, ?int $userId = null): bool
    {
        if (!in_array($type, ['logo', 'signature', 'stamp'], true)) {
            return false;
        }

        $existing = self::getCompanyBranding($companyId, $branchId);
        $oldPath = match ($type) {
            'logo' => $existing['logo_path'],
            'signature' => $existing['signature_path'],
            'stamp' => $existing['stamp_path'],
        };

        // If custom uploaded file on disk, remove it safely
        if (!empty($oldPath) && str_starts_with($oldPath, '/uploads/branding/')) {
            $oldDiskPath = self::getRootPath() . '/public' . $oldPath;
            if (is_file($oldDiskPath)) {
                @unlink($oldDiskPath);
            }
        }

        $colName = "{$type}_path";
        $resetVal = ($type === 'logo') ? self::DEFAULT_LOGO : null;

        self::ensureRecordExists($companyId, $branchId);
        Database::execute(
            "UPDATE company_brandings SET {$colName} = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))",
            [$resetVal, $userId, $companyId, $branchId, $branchId]
        );

        // Sync legacy companies table
        if ($branchId === null || $branchId === 0) {
            if ($type === 'logo') {
                Database::execute("UPDATE companies SET logo_url = ?, updated_at = NOW() WHERE id = ?", [$resetVal, $companyId]);
            } elseif ($type === 'signature' || $type === 'stamp') {
                Database::execute("UPDATE companies SET stamp_signature_url = NULL, updated_at = NOW() WHERE id = ?", [$companyId]);
            }
        }

        self::clearCache();
        return true;
    }

    /**
     * Update display sizes, alignments, and document visibility switches.
     */
    public static function updateDisplaySettings(int $companyId, ?int $branchId, array $settings, ?int $userId = null): bool
    {
        self::ensureRecordExists($companyId, $branchId);

        $sigWidth = max(50, min(300, (int)($settings['signature_width'] ?? 140)));
        $sigHeight = max(20, min(150, (int)($settings['signature_height'] ?? 56)));
        $sigPos = in_array($settings['signature_position'] ?? 'right', ['left', 'center', 'right'], true) ? $settings['signature_position'] : 'right';

        $stampWidth = max(40, min(200, (int)($settings['stamp_width'] ?? 65)));
        $stampHeight = max(40, min(200, (int)($settings['stamp_height'] ?? 65)));
        $stampPos = in_array($settings['stamp_position'] ?? 'right', ['left', 'center', 'right'], true) ? $settings['stamp_position'] : 'right';

        $showSigInv = !empty($settings['show_signature_invoice']) ? 1 : 0;
        $showSigQuot = !empty($settings['show_signature_quotation']) ? 1 : 0;
        $showSigPurch = !empty($settings['show_signature_purchase']) ? 1 : 0;

        $showStampInv = !empty($settings['show_stamp_invoice']) ? 1 : 0;
        $showStampQuot = !empty($settings['show_stamp_quotation']) ? 1 : 0;
        $showStampPurch = !empty($settings['show_stamp_purchase']) ? 1 : 0;

        Database::execute(
            "UPDATE company_brandings SET 
                signature_width = ?,
                signature_height = ?,
                signature_position = ?,
                stamp_width = ?,
                stamp_height = ?,
                stamp_position = ?,
                show_signature_invoice = ?,
                show_signature_quotation = ?,
                show_signature_purchase = ?,
                show_stamp_invoice = ?,
                show_stamp_quotation = ?,
                show_stamp_purchase = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE company_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))",
            [
                $sigWidth, $sigHeight, $sigPos,
                $stampWidth, $stampHeight, $stampPos,
                $showSigInv, $showSigQuot, $showSigPurch,
                $showStampInv, $showStampQuot, $showStampPurch,
                $userId,
                $companyId, $branchId, $branchId,
            ]
        );

        self::clearCache();
        return true;
    }

    /**
     * Ensure a company_brandings database row exists for company/branch.
     */
    private static function ensureRecordExists(int $companyId, ?int $branchId): void
    {
        $existing = Database::selectOne(
            "SELECT id FROM company_brandings WHERE company_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL)) LIMIT 1",
            [$companyId, $branchId, $branchId]
        );

        if (!$existing) {
            Database::execute(
                "INSERT INTO company_brandings (company_id, branch_id, logo_path, signature_path, stamp_path, signature_width, signature_height, signature_position, stamp_width, stamp_height, stamp_position, show_signature_invoice, show_signature_quotation, show_signature_purchase, show_stamp_invoice, show_stamp_quotation, show_stamp_purchase, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, NULL, 140, 56, 'right', 65, 65, 'right', 1, 1, 1, 1, 1, 1, NOW(), NOW())",
                [$companyId, $branchId, self::DEFAULT_LOGO]
            );
        }
    }

    /**
     * Seed initial company branding row using existing company row data.
     */
    private static function seedDefaultCompanyBranding(int $companyId, string $defaultLogo, string $defaultStampSig): void
    {
        self::ensureRecordExists($companyId, null);
    }

    /**
     * Absolute filesystem root path of the application.
     */
    public static function getRootPath(): string
    {
        return defined('ROOT_PATH') ? \ROOT_PATH : dirname(__DIR__, 2);
    }

    /**
     * Absolute filesystem path to uploads/branding directory.
     */
    public static function getUploadDirectory(): string
    {
        return self::getRootPath() . '/public/uploads/branding';
    }

    /**
     * Strict XML and security check for SVG files.
     */
    public static function isValidAndSafeSvg(string $svg): bool
    {
        if (trim($svg) === '') {
            return false;
        }

        // Disallow dangerous keywords / handlers
        $dangerousPatterns = [
            '/<script/i',
            '/<\/script>/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/base64/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i',
            '/<iframe/i',
            '/<embed/i',
            '/<object/i',
            '/<!ENTITY/i',
            '/<!DOCTYPE[^>]*\[/i', // XML entity expansion (Billion Laughs)
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $svg)) {
                return false;
            }
        }

        // Verify root tag is <svg
        return (stripos($svg, '<svg') !== false && stripos($svg, '</svg>') !== false);
    }
}
