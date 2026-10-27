-- WTSBill ERP — Enterprise RBAC Permissions, Roles & Audit Trail Migration
-- Migration: 2026_10_27_000003_rbac_permissions_and_audit_logs.sql

-- 1. Ensure columns on roles table
ALTER TABLE `roles` 
    ADD COLUMN IF NOT EXISTS `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`;

-- 2. Ensure columns on permissions table
ALTER TABLE `permissions` 
    ADD COLUMN IF NOT EXISTS `action` VARCHAR(100) NULL AFTER `module`,
    ADD COLUMN IF NOT EXISTS `description` TEXT NULL AFTER `action`;

-- 3. Ensure role_id on users table
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `role_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 5 AFTER `role`;

-- 4. User permission overrides table
CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `permission_id` BIGINT(20) UNSIGNED NOT NULL,
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_permission` (`user_id`, `permission_id`),
    KEY `idx_upo_user` (`user_id`),
    KEY `idx_upo_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Ensure extended audit log columns
ALTER TABLE `audit_logs`
    ADD COLUMN IF NOT EXISTS `user_role` VARCHAR(100) NULL AFTER `user_name`,
    ADD COLUMN IF NOT EXISTS `session_id_hash` VARCHAR(64) NULL AFTER `user_role`,
    ADD COLUMN IF NOT EXISTS `module` VARCHAR(100) NULL AFTER `action`,
    ADD COLUMN IF NOT EXISTS `entity_label` VARCHAR(255) NULL AFTER `entity_id`,
    ADD COLUMN IF NOT EXISTS `request_url` VARCHAR(500) NULL AFTER `metadata_json`,
    ADD COLUMN IF NOT EXISTS `http_method` VARCHAR(10) NULL AFTER `request_url`,
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `old_values` LONGTEXT NULL AFTER `before_data_json`,
    ADD COLUMN IF NOT EXISTS `new_values` LONGTEXT NULL AFTER `after_data_json`;

-- Index for suspicious event detection
ALTER TABLE `audit_logs`
    ADD KEY IF NOT EXISTS `idx_audit_suspicious` (`action`, `created_at`, `ip_address`);
