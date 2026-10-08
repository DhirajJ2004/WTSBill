-- WTSBill ERP - MySQL Database Dump
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB / XAMPP phpMyAdmin

CREATE DATABASE IF NOT EXISTS `wtsbill_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `wtsbill_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `account_mappings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `account_mappings`;
CREATE TABLE `account_mappings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `mapping_key` VARCHAR(255) NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `accounting_periods`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `accounting_periods`;
CREATE TABLE `accounting_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `period_name` VARCHAR(255) NOT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `is_closed` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `is_locked` INT NOT NULL DEFAULT '0',
  `locked_by` VARCHAR(255) NULL DEFAULT NULL,
  `locked_at` DATETIME NULL DEFAULT NULL,
  `lock_reason` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `attachments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `attachable_type` VARCHAR(255) NOT NULL,
  `attachable_id` BIGINT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `user_name` VARCHAR(255) NULL DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `correlation_id` VARCHAR(255) NULL DEFAULT NULL,
  `before_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `after_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `metadata_json` VARCHAR(255) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
  `severity` VARCHAR(255) NOT NULL DEFAULT 'INFO',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `automation_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `automation_events`;
CREATE TABLE `automation_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` VARCHAR(255) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `triggered_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `metadata_json` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'CREATED',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `automation_execution_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `automation_execution_logs`;
CREATE TABLE `automation_execution_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `rule_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event_id` VARCHAR(255) NULL DEFAULT NULL,
  `action_type` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
  `result_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `automation_rules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `automation_rules`;
CREATE TABLE `automation_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `conditions_json` VARCHAR(255) NULL DEFAULT NULL,
  `actions_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `priority` INT NOT NULL DEFAULT '10',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `backup_records`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `backup_records`;
CREATE TABLE `backup_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `backup_type` VARCHAR(255) NOT NULL DEFAULT 'MANUAL',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT NOT NULL DEFAULT '0',
  `checksum` VARCHAR(255) NOT NULL,
  `storage_location` VARCHAR(255) NOT NULL DEFAULT 'LOCAL',
  `app_version` VARCHAR(255) NOT NULL DEFAULT '1.0.0',
  `schema_version` VARCHAR(255) NOT NULL DEFAULT '23.0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'COMPLETED',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `metadata_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_encrypted` INT NOT NULL DEFAULT '0',
  `retention_days` INT NOT NULL DEFAULT '30',
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_accounts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(255) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(255) NOT NULL,
  `ifsc_code` VARCHAR(255) NOT NULL,
  `branch_name` VARCHAR(255) NULL DEFAULT NULL,
  `current_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_primary` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `account_type` VARCHAR(255) NOT NULL DEFAULT 'CURRENT',
  `ifsc` VARCHAR(255) NULL DEFAULT NULL,
  `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `opening_balance_date` DATETIME NULL DEFAULT NULL,
  `ledger_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `bank_reconciliation_matches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_reconciliation_matches`;
CREATE TABLE `bank_reconciliation_matches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `reconciliation_id` BIGINT UNSIGNED NOT NULL,
  `bank_transaction_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `journal_line_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `match_type` VARCHAR(255) NOT NULL DEFAULT 'ONE_TO_ONE',
  `confidence_score` VARCHAR(255) NOT NULL DEFAULT 'HIGH',
  `matched_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `difference_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'MATCHED',
  `notes` TEXT NULL DEFAULT NULL,
  `matched_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `bank_reconciliation_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `bank_statement_row_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `match_confidence` VARCHAR(255) NOT NULL DEFAULT 'EXACT',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_reconciliations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_reconciliations`;
CREATE TABLE `bank_reconciliations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `statement_date` DATETIME NOT NULL,
  `statement_balance` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `statement_start_date` DATETIME NULL DEFAULT NULL,
  `statement_end_date` DATETIME NULL DEFAULT NULL,
  `statement_opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `statement_closing_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `book_opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `book_closing_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reconciled_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `difference_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `uncleared_deposits` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unpresented_cheques` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'OPEN',
  `completed_at` DATETIME NULL DEFAULT NULL,
  `completed_by` VARCHAR(255) NULL DEFAULT NULL,
  `reopened_at` DATETIME NULL DEFAULT NULL,
  `reopened_by` VARCHAR(255) NULL DEFAULT NULL,
  `reopen_reason` VARCHAR(255) NULL DEFAULT NULL,
  `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `closing_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `system_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unreconciled_difference` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reconciled_at` DATETIME NULL DEFAULT NULL,
  `reconciled_by` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_statement_imports`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_statement_imports`;
CREATE TABLE `bank_statement_imports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(255) NOT NULL DEFAULT 'CSV',
  `total_rows` INT NOT NULL DEFAULT '0',
  `valid_rows` INT NOT NULL DEFAULT '0',
  `invalid_rows` INT NOT NULL DEFAULT '0',
  `duplicate_rows` INT NOT NULL DEFAULT '0',
  `imported_rows` INT NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'PREVIEWED',
  `column_mapping_json` VARCHAR(255) NULL DEFAULT NULL,
  `imported_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `import_format` VARCHAR(255) NOT NULL DEFAULT 'CSV',
  `mapping_config_json` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_statement_rows`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_statement_rows`;
CREATE TABLE `bank_statement_rows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `import_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `row_index` INT NOT NULL DEFAULT '0',
  `transaction_date` DATETIME NOT NULL,
  `value_date` DATETIME NULL DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `debit_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_duplicate` INT NOT NULL DEFAULT '0',
  `is_valid` INT NOT NULL DEFAULT '1',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `bank_transaction_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `row_date` DATETIME NULL DEFAULT NULL,
  `debit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `match_status` VARCHAR(255) NOT NULL DEFAULT 'UNMATCHED',
  `duplicate_flag` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_transactions`;
CREATE TABLE `bank_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `transaction_date` DATETIME NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `balance_after` DECIMAL(15,4) NOT NULL,
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `is_reconciled` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `value_date` DATETIME NULL DEFAULT NULL,
  `transaction_type` VARCHAR(255) NOT NULL DEFAULT 'OTHER',
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `debit_credit` VARCHAR(255) NOT NULL DEFAULT 'CREDIT',
  `source` VARCHAR(255) NOT NULL DEFAULT 'MANUAL',
  `source_id` VARCHAR(255) NULL DEFAULT NULL,
  `reconciliation_status` VARCHAR(255) NOT NULL DEFAULT 'UNRECONCILED',
  `reconciled_at` DATETIME NULL DEFAULT NULL,
  `reconciled_by` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bank_transfers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bank_transfers`;
CREATE TABLE `bank_transfers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `transfer_number` VARCHAR(255) NOT NULL,
  `from_type` VARCHAR(255) NOT NULL DEFAULT 'BANK',
  `from_account_id` BIGINT UNSIGNED NOT NULL,
  `to_type` VARCHAR(255) NOT NULL DEFAULT 'BANK',
  `to_account_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `transfer_date` DATETIME NOT NULL,
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `batches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NOT NULL,
  `batch_number` VARCHAR(255) NOT NULL,
  `mfg_date` DATETIME NULL DEFAULT NULL,
  `expiry_date` DATETIME NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `purchase_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `branch_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `branch_settings`;
CREATE TABLE `branch_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `invoice_prefix` VARCHAR(255) NULL DEFAULT NULL,
  `quotation_prefix` VARCHAR(255) NULL DEFAULT NULL,
  `purchase_prefix` VARCHAR(255) NULL DEFAULT NULL,
  `receipt_prefix` VARCHAR(255) NULL DEFAULT NULL,
  `default_warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `logo_url` VARCHAR(255) NULL DEFAULT NULL,
  `terms_conditions` VARCHAR(255) NULL DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `settings_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `branch_users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `branch_users`;
CREATE TABLE `branch_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `is_default` INT NOT NULL DEFAULT '0',
  `can_switch` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `branches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NULL DEFAULT NULL,
  `branch_code` VARCHAR(255) NULL DEFAULT NULL,
  `legal_name` VARCHAR(255) NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `pan` VARCHAR(255) NULL DEFAULT NULL,
  `address` TEXT NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `state_code` VARCHAR(255) NULL DEFAULT NULL,
  `pincode` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `is_main_branch` INT NOT NULL DEFAULT '0',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `business_communication_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `business_communication_settings`;
CREATE TABLE `business_communication_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `timezone` VARCHAR(255) NOT NULL DEFAULT 'Asia/Kolkata',
  `preferred_send_time` VARCHAR(255) NOT NULL DEFAULT '10:00',
  `quiet_hours_start` VARCHAR(255) NOT NULL DEFAULT '21:00',
  `quiet_hours_end` VARCHAR(255) NOT NULL DEFAULT '09:00',
  `email_configured` INT NOT NULL DEFAULT '0',
  `whatsapp_configured` INT NOT NULL DEFAULT '0',
  `sms_configured` INT NOT NULL DEFAULT '0',
  `settings_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `parent_category_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `chart_of_accounts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chart_of_accounts`;
CREATE TABLE `chart_of_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `account_code` VARCHAR(255) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `account_type` VARCHAR(255) NOT NULL,
  `current_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `parent_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `is_system_account` INT NOT NULL DEFAULT '0',
  `is_active` INT NOT NULL DEFAULT '1',
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `account_subtype` VARCHAR(255) NULL DEFAULT NULL,
  `nature` VARCHAR(255) NOT NULL DEFAULT 'DEBIT',
  `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `opening_balance_type` VARCHAR(255) NOT NULL DEFAULT 'DEBIT',
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `cheque_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cheque_events`;
CREATE TABLE `cheque_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `cheque_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(255) NULL DEFAULT NULL,
  `to_status` VARCHAR(255) NOT NULL,
  `event_date` DATETIME NOT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `event_type` VARCHAR(255) NULL DEFAULT NULL,
  `performed_by` VARCHAR(255) NULL DEFAULT NULL,
  `details_json` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `cheques`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cheques`;
CREATE TABLE `cheques` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `cheque_number` VARCHAR(255) NOT NULL,
  `cheque_date` DATETIME NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `payee` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'CLEARED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `cheque_type` VARCHAR(255) NOT NULL DEFAULT 'RECEIVED',
  `party_type` VARCHAR(255) NOT NULL DEFAULT 'CUSTOMER',
  `party_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `party_name` VARCHAR(255) NULL DEFAULT NULL,
  `bank_name` VARCHAR(255) NULL DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `deposit_bank_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `received_date` DATETIME NULL DEFAULT NULL,
  `issue_date` DATETIME NULL DEFAULT NULL,
  `deposit_date` DATETIME NULL DEFAULT NULL,
  `clearance_date` DATETIME NULL DEFAULT NULL,
  `bounce_date` DATETIME NULL DEFAULT NULL,
  `bounce_reason` VARCHAR(255) NULL DEFAULT NULL,
  `bounce_charges` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `communication_messages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `communication_messages`;
CREATE TABLE `communication_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `channel` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `template_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `template_code` VARCHAR(255) NULL DEFAULT NULL,
  `subject` VARCHAR(255) NULL DEFAULT NULL,
  `body` VARCHAR(255) NOT NULL,
  `variables_json` VARCHAR(255) NULL DEFAULT NULL,
  `attachment_path` VARCHAR(255) NULL DEFAULT NULL,
  `provider_name` VARCHAR(255) NULL DEFAULT NULL,
  `provider_reference` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'QUEUED',
  `attempts` INT NOT NULL DEFAULT '0',
  `max_attempts` INT NOT NULL DEFAULT '3',
  `next_retry_at` DATETIME NULL DEFAULT NULL,
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `sent_at` DATETIME NULL DEFAULT NULL,
  `delivered_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `communication_preferences`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `communication_preferences`;
CREATE TABLE `communication_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `party_type` VARCHAR(255) NOT NULL,
  `party_id` BIGINT UNSIGNED NOT NULL,
  `email_opt_in` INT NOT NULL DEFAULT '1',
  `whatsapp_opt_in` INT NOT NULL DEFAULT '1',
  `sms_opt_in` INT NOT NULL DEFAULT '1',
  `promotional_opt_in` INT NOT NULL DEFAULT '0',
  `preferred_channel` VARCHAR(255) NOT NULL DEFAULT 'WHATSAPP',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `communication_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `communication_templates`;
CREATE TABLE `communication_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `channel` VARCHAR(255) NOT NULL DEFAULT 'EMAIL',
  `template_code` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NOT NULL DEFAULT 'Sales',
  `subject` VARCHAR(255) NULL DEFAULT NULL,
  `body` VARCHAR(255) NOT NULL,
  `variables_json` VARCHAR(255) NULL DEFAULT NULL,
  `language` VARCHAR(255) NOT NULL DEFAULT 'en',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `companies`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `legal_name` VARCHAR(255) NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `pan` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `address_line1` VARCHAR(255) NULL DEFAULT NULL,
  `address_line2` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `pincode` VARCHAR(255) NULL DEFAULT NULL,
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `financial_year_start` VARCHAR(255) NOT NULL DEFAULT '04-01',
  `logo_url` VARCHAR(255) NULL DEFAULT NULL,
  `business_type` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `branch_management_enabled` INT NOT NULL DEFAULT '0',
  `multi_warehouse_enabled` INT NOT NULL DEFAULT '0',
  `allow_negative_stock` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `credit_note_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `credit_note_items`;
CREATE TABLE `credit_note_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `credit_note_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `credit_notes`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `credit_notes`;
CREATE TABLE `credit_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `credit_note_number` VARCHAR(255) NOT NULL,
  `credit_note_date` DATETIME NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `original_invoice_number` VARCHAR(255) NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'APPROVED',
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_addresses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'BILLING',
  `address_line1` VARCHAR(255) NOT NULL,
  `city` VARCHAR(255) NOT NULL,
  `state` VARCHAR(255) NOT NULL,
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `pincode` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `address_line2` VARCHAR(255) NULL DEFAULT NULL,
  `country` VARCHAR(255) NOT NULL DEFAULT 'India',
  `landmark` VARCHAR(255) NULL DEFAULT NULL,
  `is_default` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_advances`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_advances`;
CREATE TABLE `customer_advances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `advance_date` DATETIME NOT NULL,
  `total_amount` DECIMAL(15,4) NOT NULL,
  `allocated_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `remaining_amount` DECIMAL(15,4) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_contacts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_contacts`;
CREATE TABLE `customer_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `designation` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `is_primary` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_credits`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_credits`;
CREATE TABLE `customer_credits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `credit_type` VARCHAR(255) NOT NULL,
  `source_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `applied_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `remaining_amount` DECIMAL(15,4) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_groups`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_groups`;
CREATE TABLE `customer_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `discount_percentage` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `default_price_list_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_price_overrides`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_price_overrides`;
CREATE TABLE `customer_price_overrides` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `price` DECIMAL(15,4) NOT NULL,
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customer_tags`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_tags`;
CREATE TABLE `customer_tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `tag_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_group_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `company_name` VARCHAR(255) NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `pan` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `address_line1` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `pincode` VARCHAR(255) NULL DEFAULT NULL,
  `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `current_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit_limit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `payment_terms_days` INT NOT NULL DEFAULT '30',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `customer_type` VARCHAR(255) NOT NULL DEFAULT 'Business',
  `alt_phone` VARCHAR(255) NULL DEFAULT NULL,
  `tax_type` VARCHAR(255) NOT NULL DEFAULT 'Unregistered',
  `place_of_supply` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `notes` TEXT NULL DEFAULT NULL,
  `credit_period` INT NOT NULL DEFAULT '30',
  `opening_balance_type` VARCHAR(255) NOT NULL DEFAULT 'Debit',
  `default_price_list` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  `price_list_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reminder_enabled` INT NOT NULL DEFAULT '1',
  `preferred_channel` VARCHAR(255) NULL DEFAULT NULL,
  `whatsapp_number` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `debit_note_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `debit_note_items`;
CREATE TABLE `debit_note_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `debit_note_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `debit_notes`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `debit_notes`;
CREATE TABLE `debit_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `purchase_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `debit_note_number` VARCHAR(255) NOT NULL,
  `debit_note_date` DATETIME NOT NULL,
  `original_purchase_number` VARCHAR(255) NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_reversal` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reason` VARCHAR(255) NOT NULL DEFAULT 'Other',
  `status` VARCHAR(255) NOT NULL DEFAULT 'APPROVED',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `delivery_challan_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `delivery_challan_items`;
CREATE TABLE `delivery_challan_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `delivery_challan_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `batch_no` VARCHAR(255) NULL DEFAULT NULL,
  `serial_no` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `delivery_challans`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `delivery_challans`;
CREATE TABLE `delivery_challans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `challan_number` VARCHAR(255) NOT NULL,
  `challan_date` DATETIME NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sales_order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reference_so` VARCHAR(255) NULL DEFAULT NULL,
  `delivery_address` VARCHAR(255) NULL DEFAULT NULL,
  `transport_details` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_audit_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_audit_logs`;
CREATE TABLE `document_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `generated_document_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `user_name` VARCHAR(255) NOT NULL DEFAULT 'System',
  `ip_address` VARCHAR(255) NOT NULL DEFAULT '127.0.0.1',
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `metadata_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_number_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_number_settings`;
CREATE TABLE `document_number_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `financial_year` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `prefix` VARCHAR(255) NOT NULL,
  `starting_number` INT NOT NULL DEFAULT '1',
  `current_number` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_numbering_configs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_numbering_configs`;
CREATE TABLE `document_numbering_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `prefix` VARCHAR(255) NOT NULL DEFAULT 'INV-',
  `suffix` VARCHAR(255) NULL DEFAULT NULL,
  `starting_number` INT NOT NULL DEFAULT '1',
  `current_number` INT NOT NULL DEFAULT '0',
  `number_padding` INT NOT NULL DEFAULT '4',
  `reset_frequency` VARCHAR(255) NOT NULL DEFAULT 'YEARLY',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_shares`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_shares`;
CREATE TABLE `document_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `generated_document_id` BIGINT UNSIGNED NOT NULL,
  `share_token` VARCHAR(255) NOT NULL,
  `channel` VARCHAR(255) NOT NULL DEFAULT 'LINK',
  `recipient_name` VARCHAR(255) NULL DEFAULT NULL,
  `recipient_contact` VARCHAR(255) NULL DEFAULT NULL,
  `allow_download` INT NOT NULL DEFAULT '1',
  `allow_print` INT NOT NULL DEFAULT '1',
  `password_hash` VARCHAR(255) NULL DEFAULT NULL,
  `access_count` INT NOT NULL DEFAULT '0',
  `max_access_count` INT NULL DEFAULT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `is_revoked` INT NOT NULL DEFAULT '0',
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_template_versions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_template_versions`;
CREATE TABLE `document_template_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT NOT NULL,
  `config_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `document_templates`;
CREATE TABLE `document_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `template_key` VARCHAR(255) NOT NULL,
  `template_name` VARCHAR(255) NOT NULL,
  `paper_size` VARCHAR(255) NOT NULL DEFAULT 'A4',
  `orientation` VARCHAR(255) NOT NULL DEFAULT 'PORTRAIT',
  `brand_color` VARCHAR(255) NOT NULL DEFAULT '#1e40af',
  `accent_color` VARCHAR(255) NOT NULL DEFAULT '#3b82f6',
  `font_family` VARCHAR(255) NOT NULL DEFAULT 'Inter',
  `logo_position` VARCHAR(255) NOT NULL DEFAULT 'LEFT',
  `watermark_enabled` INT NOT NULL DEFAULT '0',
  `watermark_text` VARCHAR(255) NULL DEFAULT NULL,
  `watermark_opacity` DECIMAL(15,4) NOT NULL DEFAULT '0.08',
  `show_bank_details` INT NOT NULL DEFAULT '1',
  `show_upi_qr` INT NOT NULL DEFAULT '1',
  `show_signature` INT NOT NULL DEFAULT '1',
  `show_stamp` INT NOT NULL DEFAULT '0',
  `show_hsn_summary` INT NOT NULL DEFAULT '1',
  `show_tax_breakdown` INT NOT NULL DEFAULT '1',
  `show_amount_in_words` INT NOT NULL DEFAULT '1',
  `show_terms` INT NOT NULL DEFAULT '1',
  `default_terms` VARCHAR(255) NULL DEFAULT NULL,
  `default_notes` VARCHAR(255) NULL DEFAULT NULL,
  `custom_css` VARCHAR(255) NULL DEFAULT NULL,
  `config_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_default` INT NOT NULL DEFAULT '0',
  `is_system` INT NOT NULL DEFAULT '0',
  `version` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `e_invoice_status_history`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `e_invoice_status_history`;
CREATE TABLE `e_invoice_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `e_invoice_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `remarks` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `e_invoices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `e_invoices`;
CREATE TABLE `e_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `irn` VARCHAR(255) NULL DEFAULT NULL,
  `ack_no` VARCHAR(255) NULL DEFAULT NULL,
  `ack_date` VARCHAR(255) NULL DEFAULT NULL,
  `signed_invoice` VARCHAR(255) NULL DEFAULT NULL,
  `signed_qr_data` VARCHAR(255) NULL DEFAULT NULL,
  `qr_code_url` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'NOT_REQUIRED',
  `is_sandbox` INT NOT NULL DEFAULT '1',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_remarks` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_date` DATETIME NULL DEFAULT NULL,
  `generated_by` VARCHAR(255) NULL DEFAULT NULL,
  `generated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `e_way_bill_status_history`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `e_way_bill_status_history`;
CREATE TABLE `e_way_bill_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `e_way_bill_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `remarks` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `e_way_bills`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `e_way_bills`;
CREATE TABLE `e_way_bills` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `ewb_number` VARCHAR(255) NULL DEFAULT NULL,
  `ewb_date` DATETIME NULL DEFAULT NULL,
  `valid_until` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'NOT_REQUIRED',
  `transport_mode` VARCHAR(255) NOT NULL DEFAULT 'ROAD',
  `transporter_id` VARCHAR(255) NULL DEFAULT NULL,
  `transporter_name` VARCHAR(255) NULL DEFAULT NULL,
  `transport_doc_no` VARCHAR(255) NULL DEFAULT NULL,
  `transport_doc_date` DATETIME NULL DEFAULT NULL,
  `vehicle_no` VARCHAR(255) NULL DEFAULT NULL,
  `vehicle_type` VARCHAR(255) NOT NULL DEFAULT 'REGULAR',
  `from_pincode` VARCHAR(255) NULL DEFAULT NULL,
  `to_pincode` VARCHAR(255) NULL DEFAULT NULL,
  `distance_km` INT NOT NULL DEFAULT '0',
  `is_sandbox` INT NOT NULL DEFAULT '1',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_remarks` VARCHAR(255) NULL DEFAULT NULL,
  `cancel_date` DATETIME NULL DEFAULT NULL,
  `generated_by` VARCHAR(255) NULL DEFAULT NULL,
  `generated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expense_categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expenses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `expense_number` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `payee` VARCHAR(255) NULL DEFAULT NULL,
  `expense_date` DATETIME NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `payment_mode` VARCHAR(255) NOT NULL DEFAULT 'Bank Transfer',
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `is_itc_eligible` INT NOT NULL DEFAULT '1',
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `export_jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `export_jobs`;
CREATE TABLE `export_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `data_type` VARCHAR(255) NOT NULL,
  `export_format` VARCHAR(255) NOT NULL DEFAULT 'CSV',
  `columns_json` VARCHAR(255) NULL DEFAULT NULL,
  `filters_json` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_size` INT NOT NULL DEFAULT '0',
  `download_token` VARCHAR(255) NULL DEFAULT NULL,
  `download_token_expires_at` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'QUEUED',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `total_records` INT NOT NULL DEFAULT '0',
  `correlation_id` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `completed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `generated_documents`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `generated_documents`;
CREATE TABLE `generated_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `source_type` VARCHAR(255) NOT NULL,
  `source_id` VARCHAR(255) NOT NULL,
  `document_number` VARCHAR(255) NOT NULL,
  `document_date` DATETIME NOT NULL,
  `template_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `version` INT NOT NULL DEFAULT '1',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `mime_type` VARCHAR(255) NOT NULL DEFAULT 'application/pdf',
  `file_size` INT NOT NULL DEFAULT '0',
  `checksum_hash` VARCHAR(255) NULL DEFAULT NULL,
  `snapshot_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_frozen` INT NOT NULL DEFAULT '0',
  `generated_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `goods_receipt_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `goods_receipt_items`;
CREATE TABLE `goods_receipt_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `goods_receipt_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity_ordered` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `quantity_received` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `goods_receipts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `goods_receipts`;
CREATE TABLE `goods_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `purchase_order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `grn_number` VARCHAR(255) NOT NULL,
  `grn_date` DATETIME NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_configurations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_configurations`;
CREATE TABLE `gst_configurations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `gstin` VARCHAR(255) NOT NULL,
  `composition_scheme` VARCHAR(255) NOT NULL DEFAULT 'NO',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `gst_registered` INT NOT NULL DEFAULT '1',
  `legal_business_name` VARCHAR(255) NULL DEFAULT NULL,
  `trade_name` VARCHAR(255) NULL DEFAULT NULL,
  `pan` VARCHAR(255) NULL DEFAULT NULL,
  `registered_state` VARCHAR(255) NOT NULL DEFAULT 'Maharashtra',
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `tax_registration_type` VARCHAR(255) NOT NULL DEFAULT 'REGISTERED_REGULAR',
  `is_composition` INT NOT NULL DEFAULT '0',
  `default_place_of_supply` VARCHAR(255) NOT NULL DEFAULT 'Maharashtra',
  `einvoice_applicable` INT NOT NULL DEFAULT '0',
  `einvoice_threshold` DECIMAL(15,4) NOT NULL DEFAULT '50000000',
  `eway_bill_applicable` INT NOT NULL DEFAULT '1',
  `eway_threshold` DECIMAL(15,4) NOT NULL DEFAULT '50000',
  `filing_frequency` VARCHAR(255) NOT NULL DEFAULT 'MONTHLY',
  `api_environment` VARCHAR(255) NOT NULL DEFAULT 'SANDBOX',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_document_snapshots`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_document_snapshots`;
CREATE TABLE `gst_document_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `document_number` VARCHAR(255) NOT NULL,
  `document_date` DATETIME NOT NULL,
  `seller_gstin` VARCHAR(255) NULL DEFAULT NULL,
  `seller_state_code` VARCHAR(255) NULL DEFAULT NULL,
  `buyer_gstin` VARCHAR(255) NULL DEFAULT NULL,
  `buyer_state_code` VARCHAR(255) NULL DEFAULT NULL,
  `place_of_supply` VARCHAR(255) NOT NULL,
  `supply_type` VARCHAR(255) NOT NULL DEFAULT 'INTRA_STATE',
  `gst_category` VARCHAR(255) NOT NULL DEFAULT 'B2B',
  `is_reverse_charge` INT NOT NULL DEFAULT '0',
  `taxable_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cess_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_document_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `lines_snapshot_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_filing_periods`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_filing_periods`;
CREATE TABLE `gst_filing_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `period_name` VARCHAR(255) NOT NULL,
  `return_type` VARCHAR(255) NOT NULL DEFAULT 'GSTR1',
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `summary_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `filing_date` DATETIME NULL DEFAULT NULL,
  `arn_number` VARCHAR(255) NULL DEFAULT NULL,
  `filed_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_rates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_rates`;
CREATE TABLE `gst_rates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `rate` DECIMAL(15,4) NOT NULL,
  `cess_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `effective_from` DATETIME NULL DEFAULT NULL,
  `effective_to` DATETIME NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `is_system_default` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_reconciliation`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_reconciliation`;
CREATE TABLE `gst_reconciliation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `period_month` VARCHAR(255) NOT NULL,
  `return_type` VARCHAR(255) NOT NULL DEFAULT 'GSTR2B',
  `status` VARCHAR(255) NOT NULL DEFAULT 'IN_PROGRESS',
  `total_portal_records` INT NOT NULL DEFAULT '0',
  `total_books_records` INT NOT NULL DEFAULT '0',
  `matched_count` INT NOT NULL DEFAULT '0',
  `partial_match_count` INT NOT NULL DEFAULT '0',
  `mismatch_count` INT NOT NULL DEFAULT '0',
  `books_only_count` INT NOT NULL DEFAULT '0',
  `portal_only_count` INT NOT NULL DEFAULT '0',
  `imported_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_reconciliation_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_reconciliation_items`;
CREATE TABLE `gst_reconciliation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `reconciliation_id` BIGINT UNSIGNED NOT NULL,
  `gstin` VARCHAR(255) NOT NULL,
  `party_name` VARCHAR(255) NULL DEFAULT NULL,
  `invoice_number` VARCHAR(255) NOT NULL,
  `invoice_date` DATETIME NOT NULL,
  `books_taxable` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `portal_taxable` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `books_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `portal_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cess_diff` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `match_status` VARCHAR(255) NOT NULL DEFAULT 'MISMATCH',
  `action_taken` VARCHAR(255) NOT NULL DEFAULT 'NONE',
  `action_notes` VARCHAR(255) NULL DEFAULT NULL,
  `reviewed_by` VARCHAR(255) NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gst_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gst_transactions`;
CREATE TABLE `gst_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `reference_number` VARCHAR(255) NOT NULL,
  `transaction_date` DATETIME NOT NULL,
  `taxable_value` DECIMAL(15,4) NOT NULL,
  `cgst` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gstin_validations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gstin_validations`;
CREATE TABLE `gstin_validations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NOT NULL,
  `legal_name` VARCHAR(255) NULL DEFAULT NULL,
  `trade_name` VARCHAR(255) NULL DEFAULT NULL,
  `state_code` VARCHAR(255) NULL DEFAULT NULL,
  `state_name` VARCHAR(255) NULL DEFAULT NULL,
  `taxpayer_type` VARCHAR(255) NULL DEFAULT NULL,
  `registration_status` VARCHAR(255) NULL DEFAULT NULL,
  `is_format_valid` INT NOT NULL DEFAULT '0',
  `is_portal_verified` INT NOT NULL DEFAULT '0',
  `source` VARCHAR(255) NOT NULL DEFAULT 'LOCAL_CHECKSUM',
  `response_reference` VARCHAR(255) NULL DEFAULT NULL,
  `validated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `hsn_sac_master`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hsn_sac_master`;
CREATE TABLE `hsn_sac_master` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `code` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'HSN',
  `description` VARCHAR(255) NOT NULL,
  `default_gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `default_cess_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `import_jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `import_jobs`;
CREATE TABLE `import_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `data_type` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(255) NOT NULL DEFAULT 'CSV',
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_size` INT NOT NULL DEFAULT '0',
  `total_rows` INT NOT NULL DEFAULT '0',
  `valid_rows` INT NOT NULL DEFAULT '0',
  `warning_rows` INT NOT NULL DEFAULT '0',
  `error_rows` INT NOT NULL DEFAULT '0',
  `duplicate_rows` INT NOT NULL DEFAULT '0',
  `processed_rows` INT NOT NULL DEFAULT '0',
  `duplicate_action` VARCHAR(255) NOT NULL DEFAULT 'SKIP',
  `mapping_config_json` VARCHAR(255) NULL DEFAULT NULL,
  `validation_summary_json` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'UPLOADED',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `error_report_path` VARCHAR(255) NULL DEFAULT NULL,
  `correlation_id` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `completed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `import_row_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `import_row_logs`;
CREATE TABLE `import_row_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_job_id` BIGINT UNSIGNED NOT NULL,
  `row_index` INT NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'VALID',
  `raw_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `parsed_data_json` VARCHAR(255) NULL DEFAULT NULL,
  `issues_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `in_app_notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `in_app_notifications`;
CREATE TABLE `in_app_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `category` VARCHAR(255) NOT NULL DEFAULT 'SYSTEM',
  `priority` VARCHAR(255) NOT NULL DEFAULT 'NORMAL',
  `title` VARCHAR(255) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_read` INT NOT NULL DEFAULT '0',
  `read_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `type` VARCHAR(255) NULL DEFAULT NULL,
  `read_status` VARCHAR(255) NULL DEFAULT NULL,
  `action_url` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `indian_states`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `indian_states`;
CREATE TABLE `indian_states` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `invoice_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL,
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL,
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `invoices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(255) NOT NULL,
  `reference_po_number` VARCHAR(255) NULL DEFAULT NULL,
  `invoice_date` DATETIME NOT NULL,
  `due_date` DATETIME NULL DEFAULT NULL,
  `place_of_supply` VARCHAR(255) NOT NULL DEFAULT '27',
  `is_igst` INT NOT NULL DEFAULT '0',
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `round_off` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `grand_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount_paid` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount_due` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'PAID',
  `payment_mode` VARCHAR(255) NOT NULL DEFAULT 'Bank Transfer',
  `notes` TEXT NULL DEFAULT NULL,
  `terms_and_conditions` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `payment_status` VARCHAR(255) NOT NULL DEFAULT 'UNPAID',
  `customer_name` VARCHAR(255) NULL DEFAULT NULL,
  `customer_gstin` VARCHAR(255) NULL DEFAULT NULL,
  `billing_address` VARCHAR(255) NULL DEFAULT NULL,
  `shipping_address` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  `source_quotation_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `source_sales_order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `source_delivery_challan_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `journal_entries`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `entry_number` VARCHAR(255) NOT NULL,
  `entry_date` DATETIME NOT NULL,
  `narration` VARCHAR(255) NOT NULL,
  `total_debit` DECIMAL(15,4) NOT NULL,
  `total_credit` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `journal_number` VARCHAR(255) NULL DEFAULT NULL,
  `entry_type` VARCHAR(255) NOT NULL DEFAULT 'MANUAL',
  `reference_type` VARCHAR(255) NULL DEFAULT NULL,
  `reference_id` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'POSTED',
  `is_system_generated` INT NOT NULL DEFAULT '0',
  `original_journal_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reversal_journal_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reversal_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `posted_by` VARCHAR(255) NULL DEFAULT NULL,
  `posted_at` DATETIME NULL DEFAULT NULL,
  `reversed_by` VARCHAR(255) NULL DEFAULT NULL,
  `reversed_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `journal_entry_lines`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `journal_entry_lines`;
CREATE TABLE `journal_entry_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `debit_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `description` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `journal_lines`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `journal_lines`;
CREATE TABLE `journal_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `debit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `description` TEXT NULL DEFAULT NULL,
  `party_type` VARCHAR(255) NULL DEFAULT NULL,
  `party_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `cost_center` VARCHAR(255) NULL DEFAULT NULL,
  `tax_category` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `ledger_entries`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ledger_entries`;
CREATE TABLE `ledger_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `entry_date` DATETIME NOT NULL,
  `reference_type` VARCHAR(255) NULL DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `debit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `credit` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `migration_profiles`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `migration_profiles`;
CREATE TABLE `migration_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `data_type` VARCHAR(255) NOT NULL,
  `source_software` VARCHAR(255) NOT NULL DEFAULT 'GENERIC',
  `mapping_json` VARCHAR(255) NOT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notification_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_events`;
CREATE TABLE `notification_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME NOT NULL,
  `payload_json` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'PENDING',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notification_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `channel` VARCHAR(255) NOT NULL,
  `recipient` VARCHAR(255) NULL DEFAULT NULL,
  `template_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'PENDING',
  `provider_message_id` VARCHAR(255) NULL DEFAULT NULL,
  `attempt_count` INT NOT NULL DEFAULT '0',
  `last_error` VARCHAR(255) NULL DEFAULT NULL,
  `idempotency_key` VARCHAR(255) NULL DEFAULT NULL,
  `sent_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notification_preferences`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_preferences`;
CREATE TABLE `notification_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `category` VARCHAR(255) NOT NULL,
  `in_app_enabled` INT NOT NULL DEFAULT '1',
  `email_enabled` INT NOT NULL DEFAULT '0',
  `whatsapp_enabled` INT NOT NULL DEFAULT '0',
  `sms_enabled` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notification_rules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_rules`;
CREATE TABLE `notification_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `rule_name` VARCHAR(255) NOT NULL,
  `days_offset` INT NOT NULL DEFAULT '0',
  `channel_priority` VARCHAR(255) NOT NULL DEFAULT 'WHATSAPP,EMAIL',
  `is_active` INT NOT NULL DEFAULT '1',
  `config_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notification_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_templates`;
CREATE TABLE `notification_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `template_key` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `channel` VARCHAR(255) NOT NULL DEFAULT 'EMAIL',
  `category` VARCHAR(255) NOT NULL DEFAULT 'PAYMENTS',
  `subject` VARCHAR(255) NULL DEFAULT NULL,
  `body_template` VARCHAR(255) NOT NULL,
  `variables_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `password_resets`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_allocations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_allocations`;
CREATE TABLE `payment_allocations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `allocated_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `allocation_date` DATETIME NOT NULL DEFAULT '2026-09-23',
  `notes` TEXT NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_gateway_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateway_events`;
CREATE TABLE `payment_gateway_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `payload_json` VARCHAR(255) NULL DEFAULT NULL,
  `processed_at` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'PROCESSED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_gateway_settlements`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateway_settlements`;
CREATE TABLE `payment_gateway_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(255) NOT NULL,
  `settlement_id` VARCHAR(255) NOT NULL,
  `settlement_date` DATETIME NOT NULL,
  `gross_amount` DECIMAL(15,4) NOT NULL,
  `fee_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `net_amount` DECIMAL(15,4) NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'MATCHED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_gateway_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateway_transactions`;
CREATE TABLE `payment_gateway_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `payment_link_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `provider` VARCHAR(255) NOT NULL DEFAULT 'SIMULATION',
  `gateway_order_id` VARCHAR(255) NULL DEFAULT NULL,
  `gateway_payment_id` VARCHAR(255) NULL DEFAULT NULL,
  `gateway_signature` VARCHAR(255) NULL DEFAULT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `fee_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `net_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `status` VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
  `is_sandbox` INT NOT NULL DEFAULT '1',
  `webhook_event_id` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `raw_payload_json` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_gateway_webhooks`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateway_webhooks`;
CREATE TABLE `payment_gateway_webhooks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `signature` VARCHAR(255) NULL DEFAULT NULL,
  `payload_json` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'RECEIVED',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_link_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_link_events`;
CREATE TABLE `payment_link_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_link_id` BIGINT UNSIGNED NOT NULL,
  `event_type` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(255) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `payload_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_links`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_links`;
CREATE TABLE `payment_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `link_id` VARCHAR(255) NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `amount_paid` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `provider` VARCHAR(255) NOT NULL DEFAULT 'SIMULATION',
  `external_reference` VARCHAR(255) NULL DEFAULT NULL,
  `url` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'CREATED',
  `allow_partial` INT NOT NULL DEFAULT '0',
  `expiry_date` DATETIME NULL DEFAULT NULL,
  `paid_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `token` VARCHAR(255) NULL DEFAULT NULL,
  `original_invoice_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_partial_allowed` INT NOT NULL DEFAULT '0',
  `min_amount` DECIMAL(15,4) NULL DEFAULT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_refunds`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_refunds`;
CREATE TABLE `payment_refunds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `refund_number` VARCHAR(255) NOT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `party_type` VARCHAR(255) NOT NULL,
  `party_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `refund_date` DATETIME NOT NULL,
  `refund_mode` VARCHAR(255) NOT NULL DEFAULT 'CASH',
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'POSTED',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `payment_mode` VARCHAR(255) NOT NULL DEFAULT 'BANK_TRANSFER',
  `bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_reminders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_reminders`;
CREATE TABLE `payment_reminders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `channel` VARCHAR(255) NOT NULL DEFAULT 'WHATSAPP',
  `sent_date` DATETIME NOT NULL,
  `days_overdue` INT NOT NULL DEFAULT '0',
  `outstanding_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'SENT',
  `message_template_id` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_settlements`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_settlements`;
CREATE TABLE `payment_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `settlement_id` VARCHAR(255) NOT NULL,
  `provider` VARCHAR(255) NOT NULL DEFAULT 'SIMULATION',
  `settlement_date` DATETIME NOT NULL,
  `gross_amount` DECIMAL(15,4) NOT NULL,
  `fee_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `net_amount` DECIMAL(15,4) NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `bank_transaction_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'SETTLED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `payment_number` VARCHAR(255) NOT NULL,
  `party_type` VARCHAR(255) NOT NULL,
  `party_id` BIGINT UNSIGNED NOT NULL,
  `payment_date` DATETIME NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `payment_mode` VARCHAR(255) NOT NULL DEFAULT 'NEFT/RTGS',
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `financial_year` VARCHAR(255) NOT NULL DEFAULT '2026-27',
  `payment_type` VARCHAR(255) NOT NULL DEFAULT 'RECEIPT',
  `allocated_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unallocated_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `transaction_reference` VARCHAR(255) NULL DEFAULT NULL,
  `bank_reference` VARCHAR(255) NULL DEFAULT NULL,
  `utr` VARCHAR(255) NULL DEFAULT NULL,
  `cheque_number` VARCHAR(255) NULL DEFAULT NULL,
  `cheque_date` DATETIME NULL DEFAULT NULL,
  `cheque_bank` VARCHAR(255) NULL DEFAULT NULL,
  `cheque_status` VARCHAR(255) NOT NULL DEFAULT 'RECEIVED',
  `notes` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `updated_by` VARCHAR(255) NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `receipt_number` VARCHAR(255) NULL DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `permissions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `module` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `personal_access_tokens`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT 'default',
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `price_histories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `price_histories`;
CREATE TABLE `price_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `price_list_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `old_price` DECIMAL(15,4) NOT NULL,
  `new_price` DECIMAL(15,4) NOT NULL,
  `changed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `changed_by_name` VARCHAR(255) NOT NULL DEFAULT 'System',
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `price_list_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `price_list_items`;
CREATE TABLE `price_list_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `price_list_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `minimum_quantity` DECIMAL(15,4) NOT NULL DEFAULT '1',
  `maximum_quantity` DECIMAL(15,4) NULL DEFAULT NULL,
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `effective_from` DATETIME NULL DEFAULT NULL,
  `effective_to` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `price_lists`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `price_lists`;
CREATE TABLE `price_lists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `price_type` VARCHAR(255) NOT NULL DEFAULT 'FIXED',
  `adjustment_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_mode` VARCHAR(255) NOT NULL DEFAULT 'TAX_EXCLUSIVE',
  `is_default` INT NOT NULL DEFAULT '0',
  `is_active` INT NOT NULL DEFAULT '1',
  `effective_from` DATETIME NULL DEFAULT NULL,
  `effective_to` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `product_barcodes`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_barcodes`;
CREATE TABLE `product_barcodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `barcode` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `product_prices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_prices`;
CREATE TABLE `product_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `price_type` VARCHAR(255) NOT NULL DEFAULT 'RETAIL',
  `price` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `product_units`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_units`;
CREATE TABLE `product_units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `unit_name` VARCHAR(255) NOT NULL,
  `conversion_factor` DECIMAL(15,4) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `subcategory_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `sales_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `purchase_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `current_stock` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `min_stock_alert` DECIMAL(15,4) NOT NULL DEFAULT '10',
  `barcode` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `has_batch` INT NOT NULL DEFAULT '0',
  `has_serial` INT NOT NULL DEFAULT '0',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `product_type` VARCHAR(255) NOT NULL DEFAULT 'Goods',
  `mrp` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `wholesale_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_tax_inclusive` INT NOT NULL DEFAULT '0',
  `cess_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `secondary_unit` VARCHAR(255) NULL DEFAULT NULL,
  `conversion_ratio` DECIMAL(15,4) NOT NULL DEFAULT '1',
  `opening_stock_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reorder_level` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `is_expiry_tracked` INT NOT NULL DEFAULT '0',
  `image_url` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  `track_inventory` INT NOT NULL DEFAULT '1',
  `track_batch` INT NOT NULL DEFAULT '0',
  `track_serial` INT NOT NULL DEFAULT '0',
  `track_expiry` INT NOT NULL DEFAULT '0',
  `allow_negative_stock` INT NOT NULL DEFAULT '0',
  `reorder_quantity` DECIMAL(15,4) NOT NULL DEFAULT '50',
  `valuation_method` VARCHAR(255) NOT NULL DEFAULT 'WEIGHTED_AVERAGE',
  `default_warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `selling_price` DECIMAL(15,4) NULL DEFAULT NULL,
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `opening_stock` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `min_stock_level` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `purchase_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `purchase_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL,
  `taxable_value` DECIMAL(15,4) NOT NULL,
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cess_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cess_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `purchase_order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `purchase_order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit_price` DECIMAL(15,4) NOT NULL,
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NOT NULL DEFAULT 'Item',
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `received_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `purchase_orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `po_number` VARCHAR(255) NOT NULL,
  `po_date` DATETIME NOT NULL,
  `grand_total` DECIMAL(15,4) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ISSUED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `expected_delivery` DATETIME NULL DEFAULT NULL,
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `notes` TEXT NULL DEFAULT NULL,
  `terms` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `purchase_return_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_return_items`;
CREATE TABLE `purchase_return_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `purchase_return_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `purchase_returns`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_returns`;
CREATE TABLE `purchase_returns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `return_number` VARCHAR(255) NOT NULL,
  `return_date` DATETIME NOT NULL,
  `grand_total` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `purchase_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `refund_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_reversal_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'COMPLETED',
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `purchases`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `purchase_number` VARCHAR(255) NOT NULL,
  `vendor_invoice_number` VARCHAR(255) NULL DEFAULT NULL,
  `purchase_date` DATETIME NOT NULL,
  `due_date` DATETIME NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `round_off` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `grand_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount_paid` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `amount_due` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'PAID',
  `notes` TEXT NULL DEFAULT NULL,
  `internal_remarks` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `purchase_order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `place_of_supply` VARCHAR(255) NULL DEFAULT NULL,
  `payment_status` VARCHAR(255) NOT NULL DEFAULT 'UNPAID',
  `supplier_name` VARCHAR(255) NULL DEFAULT NULL,
  `supplier_gstin` VARCHAR(255) NULL DEFAULT NULL,
  `billing_address` VARCHAR(255) NULL DEFAULT NULL,
  `shipping_address` VARCHAR(255) NULL DEFAULT NULL,
  `freight_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `freight_taxable` INT NOT NULL DEFAULT '0',
  `additional_charges` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `additional_charges_taxable` INT NOT NULL DEFAULT '0',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `quotation_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `quotation_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit_price` DECIMAL(15,4) NOT NULL,
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `quotations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `quotation_number` VARCHAR(255) NOT NULL,
  `quotation_date` DATETIME NOT NULL,
  `valid_until` DATETIME NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL,
  `total_tax` DECIMAL(15,4) NOT NULL,
  `grand_total` DECIMAL(15,4) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `billing_address` VARCHAR(255) NULL DEFAULT NULL,
  `shipping_address` VARCHAR(255) NULL DEFAULT NULL,
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `round_off` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `notes` TEXT NULL DEFAULT NULL,
  `terms` VARCHAR(255) NULL DEFAULT NULL,
  `converted_to_invoice` INT NOT NULL DEFAULT '0',
  `converted_invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_expenses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_expenses`;
CREATE TABLE `recurring_expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'MONTHLY',
  `next_date` DATETIME NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_invoice_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_invoice_items`;
CREATE TABLE `recurring_invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `recurring_invoice_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL,
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_invoices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_invoices`;
CREATE TABLE `recurring_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `template_name` VARCHAR(255) NOT NULL,
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'MONTHLY',
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `next_invoice_date` DATETIME NOT NULL,
  `last_generated_date` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `payment_terms` VARCHAR(255) NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `grand_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_run_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_run_logs`;
CREATE TABLE `recurring_run_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `run_id` VARCHAR(255) NOT NULL,
  `run_date` DATETIME NOT NULL,
  `generated_entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `generated_entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `generation_mode` VARCHAR(255) NOT NULL DEFAULT 'AUTO_DRAFT',
  `status` VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_templates`;
CREATE TABLE `recurring_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `template_number` VARCHAR(255) NOT NULL,
  `transaction_type` VARCHAR(255) NOT NULL DEFAULT 'SALES_INVOICE',
  `party_type` VARCHAR(255) NULL DEFAULT NULL,
  `party_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `payload_json` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'MONTHLY',
  `month_end_policy` VARCHAR(255) NOT NULL DEFAULT 'LAST_DAY',
  `custom_cron` VARCHAR(255) NULL DEFAULT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `next_run_date` DATETIME NOT NULL,
  `last_run_date` DATETIME NULL DEFAULT NULL,
  `generation_mode` VARCHAR(255) NOT NULL DEFAULT 'AUTO_DRAFT',
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `version` INT NOT NULL DEFAULT '1',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_transaction_histories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_transaction_histories`;
CREATE TABLE `recurring_transaction_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `recurring_transaction_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `performed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `performed_by_name` VARCHAR(255) NOT NULL DEFAULT 'System',
  `details_json` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_transaction_runs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_transaction_runs`;
CREATE TABLE `recurring_transaction_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `recurring_transaction_id` BIGINT UNSIGNED NOT NULL,
  `occurrence_date` DATETIME NOT NULL,
  `scheduled_at` DATETIME NOT NULL,
  `executed_at` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
  `generated_entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `generated_entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `attempt_count` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recurring_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recurring_transactions`;
CREATE TABLE `recurring_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `type` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'MONTHLY',
  `interval_count` INT NOT NULL DEFAULT '1',
  `month_end_policy` VARCHAR(255) NOT NULL DEFAULT 'LAST_VALID_DAY',
  `missed_schedule_policy` VARCHAR(255) NOT NULL DEFAULT 'GENERATE_MISSED',
  `pricing_policy` VARCHAR(255) NOT NULL DEFAULT 'PRESERVE_TEMPLATE_PRICE',
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `next_run_at` DATETIME NOT NULL,
  `last_run_at` DATETIME NULL DEFAULT NULL,
  `total_occurrences` INT NULL DEFAULT NULL,
  `remaining_occurrences` INT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `template_payload_json` VARCHAR(255) NOT NULL,
  `auto_post` INT NOT NULL DEFAULT '1',
  `auto_send` INT NOT NULL DEFAULT '0',
  `created_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `reminders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reminder_type` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(255) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `recipient` VARCHAR(255) NULL DEFAULT NULL,
  `due_date` DATETIME NULL DEFAULT NULL,
  `reminder_date` DATETIME NOT NULL,
  `schedule_type` VARCHAR(255) NOT NULL DEFAULT 'BEFORE_DUE',
  `offset_days` INT NOT NULL DEFAULT '0',
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'ONCE',
  `max_count` INT NOT NULL DEFAULT '3',
  `sent_count` INT NOT NULL DEFAULT '0',
  `stop_condition` VARCHAR(255) NOT NULL DEFAULT 'ON_PAID',
  `status` VARCHAR(255) NOT NULL DEFAULT 'SCHEDULED',
  `priority` VARCHAR(255) NOT NULL DEFAULT 'NORMAL',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `report_audit_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_audit_logs`;
CREATE TABLE `report_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `user_name` VARCHAR(255) NOT NULL DEFAULT 'System',
  `report_key` VARCHAR(255) NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `filters_json` VARCHAR(255) NULL DEFAULT NULL,
  `ip_address` VARCHAR(255) NOT NULL DEFAULT '127.0.0.1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `report_saved_views`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_saved_views`;
CREATE TABLE `report_saved_views` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `report_key` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `filters_json` VARCHAR(255) NULL DEFAULT NULL,
  `columns_json` VARCHAR(255) NULL DEFAULT NULL,
  `sort_json` VARCHAR(255) NULL DEFAULT NULL,
  `is_default` INT NOT NULL DEFAULT '0',
  `is_favorite` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `report_schedules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_schedules`;
CREATE TABLE `report_schedules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `report_key` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `frequency` VARCHAR(255) NOT NULL DEFAULT 'WEEKLY',
  `delivery_channel` VARCHAR(255) NOT NULL DEFAULT 'EMAIL',
  `recipient` VARCHAR(255) NOT NULL,
  `export_format` VARCHAR(255) NOT NULL DEFAULT 'PDF',
  `filters_json` VARCHAR(255) NULL DEFAULT NULL,
  `last_run_at` DATETIME NULL DEFAULT NULL,
  `next_run_at` DATETIME NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `report_snapshots`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_snapshots`;
CREATE TABLE `report_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `report_key` VARCHAR(255) NOT NULL,
  `period_type` VARCHAR(255) NOT NULL DEFAULT 'FINANCIAL_YEAR',
  `period_label` VARCHAR(255) NOT NULL,
  `start_date` DATETIME NULL DEFAULT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `dataset_json` VARCHAR(255) NOT NULL,
  `is_locked` INT NOT NULL DEFAULT '0',
  `locked_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `restore_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `restore_logs`;
CREATE TABLE `restore_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `backup_record_id` BIGINT UNSIGNED NOT NULL,
  `safety_backup_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `restore_mode` VARCHAR(255) NOT NULL DEFAULT 'BUSINESS',
  `status` VARCHAR(255) NOT NULL DEFAULT 'STARTED',
  `initiated_by` VARCHAR(255) NOT NULL DEFAULT 'Admin',
  `ip_address` VARCHAR(255) NULL DEFAULT NULL,
  `checksum_verified` INT NOT NULL DEFAULT '0',
  `schema_compatible` INT NOT NULL DEFAULT '0',
  `tables_restored_json` VARCHAR(255) NULL DEFAULT NULL,
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `role_permissions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `sales_order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales_order_items`;
CREATE TABLE `sales_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `sales_order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit_price` DECIMAL(15,4) NOT NULL,
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '18',
  `cgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_rate` DECIMAL(15,4) NOT NULL DEFAULT '9',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sales_orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales_orders`;
CREATE TABLE `sales_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `order_number` VARCHAR(255) NOT NULL,
  `order_date` DATETIME NOT NULL,
  `grand_total` DECIMAL(15,4) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'CONFIRMED',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `expected_delivery` DATETIME NULL DEFAULT NULL,
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `notes` TEXT NULL DEFAULT NULL,
  `fulfillment_status` VARCHAR(255) NOT NULL DEFAULT 'CONFIRMED',
  `payment_status` VARCHAR(255) NOT NULL DEFAULT 'UNPAID',
  `source_quotation_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sales_return_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales_return_items`;
CREATE TABLE `sales_return_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `sales_return_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `hsn_sac` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` DECIMAL(15,4) NOT NULL,
  `taxable_value` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `gst_rate` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `cgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `sgst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `igst_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_amount` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sales_returns`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales_returns`;
CREATE TABLE `sales_returns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `return_number` VARCHAR(255) NOT NULL,
  `return_date` DATETIME NOT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `stock_impact` INT NOT NULL DEFAULT '1',
  `sub_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_tax` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `grand_total` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `refund_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `tax_reversal_amount` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'COMPLETED',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `scheduler_jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `scheduler_jobs`;
CREATE TABLE `scheduler_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `job_key` VARCHAR(255) NOT NULL,
  `job_type` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'PENDING',
  `started_at` DATETIME NULL DEFAULT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  `attempt_count` INT NOT NULL DEFAULT '0',
  `last_error` VARCHAR(255) NULL DEFAULT NULL,
  `locked_until` DATETIME NULL DEFAULT NULL,
  `locked_by` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `schema_migrations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL DEFAULT '1',
  `status` VARCHAR(255) NOT NULL DEFAULT 'APPLIED',
  `execution_time_ms` INT NOT NULL DEFAULT '0',
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `serial_numbers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `serial_numbers`;
CREATE TABLE `serial_numbers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `serial_number` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'IN_STOCK',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `purchase_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_adjustment_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_adjustment_items`;
CREATE TABLE `stock_adjustment_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `adjustment_id` BIGINT UNSIGNED NOT NULL,
  `stock_adjustment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'INCREASE',
  `adjustment_type` VARCHAR(255) NULL DEFAULT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_adjustments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NOT NULL,
  `adjustment_number` VARCHAR(255) NOT NULL,
  `adjustment_date` DATETIME NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'Admin',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_balances`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_balances`;
CREATE TABLE `stock_balances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reserved_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `available_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `last_movement_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `in_transit_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `stock_count_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_count_items`;
CREATE TABLE `stock_count_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `stock_count_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `system_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `physical_quantity` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `difference` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `adjustment_created` INT NOT NULL DEFAULT '0',
  `adjustment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_counts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_counts`;
CREATE TABLE `stock_counts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `warehouse_id` BIGINT UNSIGNED NOT NULL,
  `count_number` VARCHAR(255) NOT NULL,
  `count_date` DATETIME NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'DRAFT',
  `counted_by` VARCHAR(255) NOT NULL DEFAULT 'Admin',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_movements`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `balance_after` DECIMAL(15,4) NOT NULL,
  `reference_type` VARCHAR(255) NULL DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `batch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `serial_number_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `movement_type` VARCHAR(255) NOT NULL DEFAULT 'OPENING_STOCK',
  `direction` VARCHAR(255) NOT NULL DEFAULT 'IN',
  `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `total_cost` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `movement_date` DATETIME NOT NULL DEFAULT '2026-09-23',
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_transfer_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `transfer_id` BIGINT UNSIGNED NOT NULL,
  `stock_transfer_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `quantity_sent` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `quantity_received` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `unit` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock_transfers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `transfer_number` VARCHAR(255) NOT NULL,
  `from_warehouse_id` BIGINT UNSIGNED NOT NULL,
  `to_warehouse_id` BIGINT UNSIGNED NOT NULL,
  `transfer_date` DATETIME NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'IN_TRANSIT',
  `reference_no` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `from_branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `to_branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NULL DEFAULT NULL,
  `approved_by` VARCHAR(255) NULL DEFAULT NULL,
  `dispatched_by` VARCHAR(255) NULL DEFAULT NULL,
  `received_by` VARCHAR(255) NULL DEFAULT NULL,
  `cancelled_by` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `subcategories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `supplier_addresses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `supplier_addresses`;
CREATE TABLE `supplier_addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'OFFICE',
  `address_line1` VARCHAR(255) NOT NULL,
  `city` VARCHAR(255) NOT NULL,
  `state` VARCHAR(255) NOT NULL,
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `pincode` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `address_line2` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `supplier_bank_accounts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `supplier_bank_accounts`;
CREATE TABLE `supplier_bank_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(255) NOT NULL,
  `ifsc_code` VARCHAR(255) NOT NULL,
  `branch_name` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `account_name` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `supplier_bill_attachments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `supplier_bill_attachments`;
CREATE TABLE `supplier_bill_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `purchase_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `supplier_contacts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `supplier_contacts`;
CREATE TABLE `supplier_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `supplier_groups`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `supplier_groups`;
CREATE TABLE `supplier_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `suppliers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `supplier_group_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `company_name` VARCHAR(255) NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `pan` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `address_line1` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `state_code` VARCHAR(255) NOT NULL DEFAULT '27',
  `pincode` VARCHAR(255) NULL DEFAULT NULL,
  `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `current_balance` DECIMAL(15,4) NOT NULL DEFAULT '0',
  `payment_terms_days` INT NOT NULL DEFAULT '30',
  `is_active` INT NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `alt_phone` VARCHAR(255) NULL DEFAULT NULL,
  `tax_type` VARCHAR(255) NOT NULL DEFAULT 'Unregistered',
  `place_of_supply` VARCHAR(255) NULL DEFAULT NULL,
  `contact_person` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `credit_period` INT NOT NULL DEFAULT '30',
  `default_payment_mode` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `taggables`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `taggables`;
CREATE TABLE `taggables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  `taggable_type` VARCHAR(255) NOT NULL,
  `taggable_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `tags`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `color` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `tax_rates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tax_rates`;
CREATE TABLE `tax_rates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `rate` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `tax_type` VARCHAR(255) NOT NULL DEFAULT 'GST',
  `effective_from` DATETIME NULL DEFAULT NULL,
  `effective_to` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `unit_conversions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `unit_conversions`;
CREATE TABLE `unit_conversions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `from_unit_id` BIGINT UNSIGNED NOT NULL,
  `to_unit_id` BIGINT UNSIGNED NOT NULL,
  `conversion_factor` DECIMAL(15,4) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `units`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `short_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'QUANTITY',
  `decimal_precision` INT NOT NULL DEFAULT '0',
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `user_branches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_branches`;
CREATE TABLE `user_branches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `user_roles`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `user_sessions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(255) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `device_info` VARCHAR(255) NULL DEFAULT NULL,
  `last_activity_at` DATETIME NULL DEFAULT NULL,
  `is_revoked` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `role` VARCHAR(255) NOT NULL DEFAULT 'Admin',
  `current_company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `status` VARCHAR(255) NOT NULL DEFAULT 'ACTIVE',
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `remember_token` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for table `warehouses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `is_primary` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `address` TEXT NULL DEFAULT NULL,
  `is_active` INT NOT NULL DEFAULT '1',
  `manager_name` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `state` VARCHAR(255) NULL DEFAULT NULL,
  `pincode` VARCHAR(255) NULL DEFAULT NULL,
  `is_default` INT NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
-- Dump completed.