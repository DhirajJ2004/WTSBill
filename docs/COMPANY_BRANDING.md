# WTSBill ERP — Centralized Company Branding Architecture & Technical Specification

## 1. Overview & Objective
WTSBill ERP features a centralized Company Branding Management system located at `Settings → Company Branding` (`/settings/branding`).
Prior to this enhancement, branding assets (such as logos and signatures) were either referenced statically in layout files or stored as a single combined column in the `companies` table.

The centralized Company Branding module allows administrators to:
1. **Upload, preview, replace, and remove** three distinct corporate assets:
   - **Company Logo**: High-resolution, transparent corporate identity mark.
   - **Authorized Signature**: Official signatory signature image for invoices, quotations, and purchase orders.
   - **Company Stamp / Seal**: Official circular/oval company seal.
2. **Configure document display properties**:
   - Signature width, height, and alignment (`left`, `center`, `right`).
   - Stamp width, height, and alignment (`left`, `center`, `right`).
   - Granular document visibility toggles: Sales Invoices, Quotations, and Purchase Orders.
3. **Multi-Branch Override Support**:
   - Company-wide defaults apply automatically to all operating branches.
   - Specific operating branches can upload branch-specific branding overrides where legal entities or branch letterheads diverge.
4. **Instant Global Propagation & Cache-Busting**:
   - Updates propagate immediately across Website Header, Sidebar, Login / Auth screens, Dashboard, Invoice Print Layouts, Quotation Print Layouts, Purchase Order Layouts, and generated PDF attachments.
   - Timestamp query strings (`?v={timestamp}`) guarantee that neither browser nor proxy caches serve stale assets upon replacement.

---

## 2. Database Schema & Multi-Tenant Storage
Branding data is managed in the dedicated `company_brandings` table:

```sql
CREATE TABLE IF NOT EXISTS `company_brandings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `branch_id` INT UNSIGNED NULL DEFAULT NULL,
    `logo_path` VARCHAR(255) NULL DEFAULT '/assets/images/logo.png',
    `signature_path` VARCHAR(255) NULL DEFAULT NULL,
    `stamp_path` VARCHAR(255) NULL DEFAULT NULL,
    `signature_width` INT UNSIGNED NOT NULL DEFAULT 140,
    `signature_height` INT UNSIGNED NOT NULL DEFAULT 56,
    `signature_position` ENUM('left', 'center', 'right') NOT NULL DEFAULT 'right',
    `stamp_width` INT UNSIGNED NOT NULL DEFAULT 65,
    `stamp_height` INT UNSIGNED NOT NULL DEFAULT 65,
    `stamp_position` ENUM('left', 'center', 'right') NOT NULL DEFAULT 'right',
    `show_signature_invoice` TINYINT(1) NOT NULL DEFAULT 1,
    `show_signature_quotation` TINYINT(1) NOT NULL DEFAULT 1,
    `show_signature_purchase` TINYINT(1) NOT NULL DEFAULT 1,
    `show_stamp_invoice` TINYINT(1) NOT NULL DEFAULT 1,
    `show_stamp_quotation` TINYINT(1) NOT NULL DEFAULT 1,
    `show_stamp_purchase` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_brand_company_branch` (`company_id`, `branch_id`),
    CONSTRAINT `fk_brand_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Backward Compatibility & Synchronization
To prevent breaking existing third-party or legacy modules relying on `companies.logo_url` or `companies.stamp_signature_url`, `CompanyBrandingService::saveAsset()` and `CompanyBrandingService::removeAsset()` automatically update the matching columns on the parent `companies` table whenever company-wide assets are modified.

---

## 3. Storage & Upload Security
- **Upload Directory**: Isolated under `public/uploads/branding/`.
- **Extension & MIME Whitelist**:
  - Raster: `.png`, `.jpg`, `.jpeg`, `.webp` verified with PHP `getimagesize()` and `finfo_file()`.
  - Vector: `.svg` verified with strict XML validation and sanitization.
- **SVG Sanitization Engine (`CompanyBrandingService::isValidAndSafeSvg`)**:
  - Rejects files containing `<script>`, `javascript:`, inline event handlers (`onload`, `onclick`, `onerror`), `base64` embedded scripts, external entity declarations (`<!ENTITY`), and `<iframe>` elements.
- **File Size Cap**: Strict 2 MB limit enforced before filesystem write.
- **Filename Randomization**: Cryptographically random unique prefixes (`logo_1_main_{uniqid}_{hash}.ext`) prevent collisions, guessing, and directory traversal.
- **Safe File Cleanup**: Replacing or removing a custom asset unlinks the old file from disk, preventing orphaned storage accumulation. Default asset files under `/assets/` are protected and never unlinked.

---

## 4. Architecture & Controller Routes

### Web Routes (`routes/web.php`)
| Method | Route | Controller Action | Middleware |
|---|---|---|---|
| `GET` | `/settings/branding` | `BrandingController@index` | `AuthenticationMiddleware`, `can('settings.view')` |
| `POST` | `/settings/branding/upload` | `BrandingController@upload` | `AuthenticationMiddleware`, `CsrfMiddleware`, `can('settings.edit')` |
| `POST` | `/settings/branding/remove` | `BrandingController@remove` | `AuthenticationMiddleware`, `CsrfMiddleware`, `can('settings.edit')` |
| `POST` | `/settings/branding/settings` | `BrandingController@saveSettings` | `AuthenticationMiddleware`, `CsrfMiddleware`, `can('settings.edit')` |
| `GET` | `/api/settings/branding` | `BrandingController@index` | `AuthenticationMiddleware`, `can('settings.view')` |

### Service Methods (`app/Services/CompanyBrandingService.php`)
- `getCompanyBranding(int $companyId = 1, ?int $branchId = null): array`
- `getCompanyLogo(int $companyId = 1, ?int $branchId = null): string`
- `getCompanySignature(int $companyId = 1, ?int $branchId = null): array`
- `getCompanyStamp(int $companyId = 1, ?int $branchId = null): array`
- `saveAsset(int $companyId, ?int $branchId, string $type, array $file, int $userId): array`
- `removeAsset(int $companyId, ?int $branchId, string $type, int $userId): bool`
- `updateDisplaySettings(int $companyId, ?int $branchId, array $settings, int $userId): bool`
- `clearCache(): void`

---

## 5. UI Implementation (`views/settings/branding.php`)
- Styled with WTSBill's forest green design system (`#051F20`, `#0B2B26`, `#163832`, `#235347`, `#8EB69B`, `#DAF1DE`).
- Responsive 3-card grid:
  1. **Company Logo**: High-resolution preview, transparent background checkerboard, recommendation notes, file chooser, upload button, reset button.
  2. **Authorized Signature**: High-resolution preview, transparent background checkerboard, status badge, upload/replace button, remove button.
  3. **Company Stamp**: High-resolution preview, transparent background checkerboard, status badge, upload/replace button, remove button.
- **Document Display Settings Card**:
  - Signature width (px), height (px), alignment (`left`, `center`, `right`).
  - Stamp width (px), height (px), alignment (`left`, `center`, `right`).
  - Document inclusion switches: Sales Invoices, Quotations, and Purchase Orders.
- **Safety**: Client-side image preview before form submission and confirmation modal before deleting an asset.

