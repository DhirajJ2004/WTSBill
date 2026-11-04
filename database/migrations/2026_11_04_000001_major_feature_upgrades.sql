-- ============================================================================
-- WTSBill ERP — Major Feature Upgrades Migration
-- 1. User theme persistence
-- 2. Business communication & delivery settings
-- 3. In-flight form draft recovery
-- 4. Audit log cleanup execution logging
-- ============================================================================

-- 1. User Theme column
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `theme` VARCHAR(20) NOT NULL DEFAULT 'light' AFTER `status`;

-- 2. Communication Settings expansion
ALTER TABLE `business_communication_settings` 
    ADD COLUMN IF NOT EXISTS `default_invoice_channel` VARCHAR(30) NOT NULL DEFAULT 'BOTH' AFTER `company_id`,
    ADD COLUMN IF NOT EXISTS `default_quotation_channel` VARCHAR(30) NOT NULL DEFAULT 'BOTH' AFTER `default_invoice_channel`,
    ADD COLUMN IF NOT EXISTS `whatsapp_provider` VARCHAR(50) NOT NULL DEFAULT 'CLICK_TO_CHAT' AFTER `default_quotation_channel`,
    ADD COLUMN IF NOT EXISTS `whatsapp_phone_number_id` VARCHAR(100) NULL AFTER `whatsapp_provider`,
    ADD COLUMN IF NOT EXISTS `whatsapp_access_token` TEXT NULL AFTER `whatsapp_phone_number_id`,
    ADD COLUMN IF NOT EXISTS `whatsapp_business_account_id` VARCHAR(100) NULL AFTER `whatsapp_access_token`,
    ADD COLUMN IF NOT EXISTS `whatsapp_api_version` VARCHAR(20) NOT NULL DEFAULT 'v19.0' AFTER `whatsapp_business_account_id`,
    ADD COLUMN IF NOT EXISTS `whatsapp_template_name` VARCHAR(100) NULL AFTER `whatsapp_api_version`,
    ADD COLUMN IF NOT EXISTS `smtp_host` VARCHAR(191) NULL AFTER `whatsapp_template_name`,
    ADD COLUMN IF NOT EXISTS `smtp_port` INT NOT NULL DEFAULT 587 AFTER `smtp_host`,
    ADD COLUMN IF NOT EXISTS `smtp_username` VARCHAR(191) NULL AFTER `smtp_port`,
    ADD COLUMN IF NOT EXISTS `smtp_password` VARCHAR(255) NULL AFTER `smtp_username`,
    ADD COLUMN IF NOT EXISTS `smtp_encryption` VARCHAR(20) NOT NULL DEFAULT 'tls' AFTER `smtp_password`,
    ADD COLUMN IF NOT EXISTS `smtp_from_address` VARCHAR(191) NULL AFTER `smtp_encryption`,
    ADD COLUMN IF NOT EXISTS `smtp_from_name` VARCHAR(191) NULL AFTER `smtp_from_address`;

-- 3. In-flight Auto-Save Drafts Table
CREATE TABLE IF NOT EXISTS `user_drafts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `branch_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `form_type` VARCHAR(50) NOT NULL,
    `form_key` VARCHAR(100) NULL,
    `form_data_json` LONGTEXT NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_draft_user_form` (`company_id`, `user_id`, `form_type`),
    INDEX `idx_draft_form_key` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Audit Log Cleanup Reports Table
CREATE TABLE IF NOT EXISTS `audit_cleanup_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `backup_record_id` BIGINT UNSIGNED NULL,
    `retention_days` INT NOT NULL DEFAULT 7,
    `records_archived` INT NOT NULL DEFAULT 0,
    `records_deleted` INT NOT NULL DEFAULT 0,
    `backup_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'COMPLETED',
    `error_message` TEXT NULL,
    `executed_by` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM_SCHEDULER',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cleanup_backup` (`backup_record_id`),
    INDEX `idx_cleanup_company_created` (`company_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
