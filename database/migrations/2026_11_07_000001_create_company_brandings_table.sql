-- Migration: Create Centralized Company Branding Settings Table
CREATE TABLE IF NOT EXISTS company_brandings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    logo_path VARCHAR(255) NULL,
    signature_path VARCHAR(255) NULL,
    stamp_path VARCHAR(255) NULL,
    signature_width INT NOT NULL DEFAULT 140,
    signature_height INT NOT NULL DEFAULT 56,
    signature_position VARCHAR(30) NOT NULL DEFAULT 'right',
    stamp_width INT NOT NULL DEFAULT 65,
    stamp_height INT NOT NULL DEFAULT 65,
    stamp_position VARCHAR(30) NOT NULL DEFAULT 'right',
    show_signature_invoice TINYINT(1) NOT NULL DEFAULT 1,
    show_signature_quotation TINYINT(1) NOT NULL DEFAULT 1,
    show_signature_purchase TINYINT(1) NOT NULL DEFAULT 1,
    show_stamp_invoice TINYINT(1) NOT NULL DEFAULT 1,
    show_stamp_quotation TINYINT(1) NOT NULL DEFAULT 1,
    show_stamp_purchase TINYINT(1) NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company_branch (company_id, branch_id),
    INDEX idx_company_id (company_id),
    INDEX idx_branch_id (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

