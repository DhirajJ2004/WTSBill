-- WTSBill ERP Master Production Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Financial Columns: Strict DECIMAL(p, s)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `accounting_periods`;
CREATE TABLE `accounting_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `period_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_by` varchar(255) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `lock_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounting_periods_company_id_index` (`company_id`),
  KEY `accounting_periods_financial_year_index` (`financial_year`),
  KEY `accounting_periods_is_locked_index` (`is_locked`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `account_mappings`;
CREATE TABLE `account_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `mapping_key` varchar(50) NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_mappings_company_id_mapping_key_unique` (`company_id`,`mapping_key`),
  KEY `account_mappings_company_id_index` (`company_id`),
  KEY `account_mappings_mapping_key_index` (`mapping_key`),
  KEY `account_mappings_account_id_index` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=841 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `attachable_type` varchar(255) NOT NULL,
  `attachable_id` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `correlation_id` varchar(60) DEFAULT NULL,
  `before_data_json` longtext DEFAULT NULL,
  `after_data_json` longtext DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'SUCCESS',
  `severity` varchar(20) NOT NULL DEFAULT 'INFO',
  PRIMARY KEY (`id`),
  KEY `audit_logs_company_id_index` (`company_id`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_branch_id_index` (`branch_id`),
  KEY `audit_logs_correlation_id_index` (`correlation_id`),
  KEY `audit_logs_status_index` (`status`),
  KEY `audit_logs_severity_index` (`severity`),
  KEY `idx_audit_comp_created` (`company_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `automation_events`;
CREATE TABLE `automation_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `triggered_by` varchar(120) NOT NULL DEFAULT 'System',
  `metadata_json` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'CREATED',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `automation_events_event_id_unique` (`event_id`),
  KEY `automation_events_company_id_index` (`company_id`),
  KEY `automation_events_branch_id_index` (`branch_id`),
  KEY `automation_events_event_type_index` (`event_type`),
  KEY `automation_events_entity_type_index` (`entity_type`),
  KEY `automation_events_entity_id_index` (`entity_id`),
  CONSTRAINT `automation_events_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `automation_execution_logs`;
CREATE TABLE `automation_execution_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `event_id` varchar(64) DEFAULT NULL,
  `action_type` varchar(60) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'SUCCESS',
  `result_data_json` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `automation_execution_logs_company_id_index` (`company_id`),
  KEY `automation_execution_logs_rule_id_index` (`rule_id`),
  KEY `automation_execution_logs_event_id_index` (`event_id`),
  CONSTRAINT `automation_execution_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `automation_rules`;
CREATE TABLE `automation_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `conditions_json` text DEFAULT NULL,
  `actions_json` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 10,
  `created_by` varchar(120) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `automation_rules_company_id_index` (`company_id`),
  KEY `automation_rules_branch_id_index` (`branch_id`),
  KEY `automation_rules_event_type_index` (`event_type`),
  CONSTRAINT `automation_rules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `backup_records`;
CREATE TABLE `backup_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `backup_type` varchar(30) NOT NULL DEFAULT 'MANUAL',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `checksum` varchar(100) NOT NULL,
  `storage_location` varchar(100) NOT NULL DEFAULT 'LOCAL',
  `app_version` varchar(30) NOT NULL DEFAULT '1.0.0',
  `schema_version` varchar(30) NOT NULL DEFAULT '23.0',
  `status` varchar(30) NOT NULL DEFAULT 'COMPLETED',
  `error_message` text DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `retention_days` int(11) NOT NULL DEFAULT 30,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_records_company_id_index` (`company_id`),
  KEY `backup_records_backup_type_index` (`backup_type`),
  KEY `backup_records_checksum_index` (`checksum`),
  KEY `backup_records_status_index` (`status`),
  KEY `backup_records_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_number` varchar(255) NOT NULL,
  `ifsc_code` varchar(11) NOT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `account_type` varchar(30) NOT NULL DEFAULT 'CURRENT',
  `ifsc` varchar(11) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance_date` date DEFAULT NULL,
  `ledger_account_id` bigint(20) unsigned DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`),
  KEY `bank_accounts_company_id_index` (`company_id`),
  KEY `bank_accounts_branch_id_index` (`branch_id`),
  KEY `bank_accounts_account_type_index` (`account_type`),
  KEY `bank_accounts_ledger_account_id_index` (`ledger_account_id`),
  KEY `bank_accounts_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_reconciliations`;
CREATE TABLE `bank_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `statement_start_date` date DEFAULT NULL,
  `statement_end_date` date DEFAULT NULL,
  `statement_opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `statement_closing_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `book_opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `book_closing_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reconciled_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `difference_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uncleared_deposits` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unpresented_cheques` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'OPEN',
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` varchar(255) DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `reopened_by` varchar(255) DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `system_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unreconciled_difference` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliations_company_id_index` (`company_id`),
  KEY `bank_reconciliations_bank_account_id_index` (`bank_account_id`),
  KEY `bank_reconciliations_branch_id_index` (`branch_id`),
  KEY `bank_reconciliations_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_reconciliation_matches`;
CREATE TABLE `bank_reconciliation_matches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `reconciliation_id` bigint(20) unsigned NOT NULL,
  `bank_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `journal_line_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `match_type` varchar(30) NOT NULL DEFAULT 'ONE_TO_ONE',
  `confidence_score` varchar(20) NOT NULL DEFAULT 'HIGH',
  `matched_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `difference_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'MATCHED',
  `notes` text DEFAULT NULL,
  `matched_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_reconciliation_id` bigint(20) unsigned DEFAULT NULL,
  `bank_statement_row_id` bigint(20) unsigned DEFAULT NULL,
  `match_confidence` varchar(30) NOT NULL DEFAULT 'EXACT',
  PRIMARY KEY (`id`),
  KEY `bank_reconciliation_matches_company_id_index` (`company_id`),
  KEY `bank_reconciliation_matches_reconciliation_id_index` (`reconciliation_id`),
  KEY `bank_reconciliation_matches_bank_transaction_id_index` (`bank_transaction_id`),
  KEY `bank_reconciliation_matches_journal_entry_id_index` (`journal_entry_id`),
  KEY `bank_reconciliation_matches_journal_line_id_index` (`journal_line_id`),
  KEY `bank_reconciliation_matches_payment_id_index` (`payment_id`),
  KEY `bank_reconciliation_matches_match_type_index` (`match_type`),
  KEY `bank_reconciliation_matches_confidence_score_index` (`confidence_score`),
  KEY `bank_reconciliation_matches_status_index` (`status`),
  KEY `bank_reconciliation_matches_bank_reconciliation_id_index` (`bank_reconciliation_id`),
  KEY `bank_reconciliation_matches_bank_statement_row_id_index` (`bank_statement_row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_statement_imports`;
CREATE TABLE `bank_statement_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(20) NOT NULL DEFAULT 'CSV',
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `valid_rows` int(11) NOT NULL DEFAULT 0,
  `invalid_rows` int(11) NOT NULL DEFAULT 0,
  `duplicate_rows` int(11) NOT NULL DEFAULT 0,
  `imported_rows` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'PREVIEWED',
  `column_mapping_json` longtext DEFAULT NULL,
  `imported_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `import_format` varchar(20) NOT NULL DEFAULT 'CSV',
  `mapping_config_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_statement_imports_company_id_index` (`company_id`),
  KEY `bank_statement_imports_branch_id_index` (`branch_id`),
  KEY `bank_statement_imports_bank_account_id_index` (`bank_account_id`),
  KEY `bank_statement_imports_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_statement_rows`;
CREATE TABLE `bank_statement_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `import_id` bigint(20) unsigned NOT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `row_index` int(11) NOT NULL DEFAULT 0,
  `transaction_date` date NOT NULL,
  `value_date` date DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `debit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_duplicate` tinyint(1) NOT NULL DEFAULT 0,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `bank_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `row_date` date DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `match_status` varchar(30) NOT NULL DEFAULT 'UNMATCHED',
  `duplicate_flag` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `bank_statement_rows_company_id_index` (`company_id`),
  KEY `bank_statement_rows_import_id_index` (`import_id`),
  KEY `bank_statement_rows_bank_account_id_index` (`bank_account_id`),
  KEY `bank_statement_rows_transaction_date_index` (`transaction_date`),
  KEY `bank_statement_rows_reference_number_index` (`reference_number`),
  KEY `bank_statement_rows_is_duplicate_index` (`is_duplicate`),
  KEY `bank_statement_rows_bank_transaction_id_index` (`bank_transaction_id`),
  KEY `bank_statement_rows_match_status_index` (`match_status`),
  KEY `bank_statement_rows_duplicate_flag_index` (`duplicate_flag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_transactions`;
CREATE TABLE `bank_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `type` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `is_reconciled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `value_date` date DEFAULT NULL,
  `transaction_type` varchar(40) NOT NULL DEFAULT 'OTHER',
  `reference_number` varchar(100) DEFAULT NULL,
  `debit_credit` varchar(10) NOT NULL DEFAULT 'CREDIT',
  `source` varchar(40) NOT NULL DEFAULT 'MANUAL',
  `source_id` varchar(50) DEFAULT NULL,
  `reconciliation_status` varchar(30) NOT NULL DEFAULT 'UNRECONCILED',
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_transactions_company_id_index` (`company_id`),
  KEY `bank_transactions_bank_account_id_index` (`bank_account_id`),
  KEY `bank_transactions_branch_id_index` (`branch_id`),
  KEY `bank_transactions_transaction_type_index` (`transaction_type`),
  KEY `bank_transactions_reference_number_index` (`reference_number`),
  KEY `bank_transactions_debit_credit_index` (`debit_credit`),
  KEY `bank_transactions_source_index` (`source`),
  KEY `bank_transactions_source_id_index` (`source_id`),
  KEY `bank_transactions_reconciliation_status_index` (`reconciliation_status`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_transfers`;
CREATE TABLE `bank_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `from_type` varchar(20) NOT NULL DEFAULT 'BANK',
  `from_account_id` bigint(20) unsigned NOT NULL,
  `to_type` varchar(20) NOT NULL DEFAULT 'BANK',
  `to_account_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_transfers_company_id_transfer_number_unique` (`company_id`,`transfer_number`),
  KEY `bank_transfers_company_id_index` (`company_id`),
  KEY `bank_transfers_branch_id_index` (`branch_id`),
  KEY `bank_transfers_transfer_number_index` (`transfer_number`),
  KEY `bank_transfers_from_account_id_index` (`from_account_id`),
  KEY `bank_transfers_to_account_id_index` (`to_account_id`),
  KEY `bank_transfers_transfer_date_index` (`transfer_date`),
  KEY `bank_transfers_journal_entry_id_index` (`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `batch_number` varchar(255) NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  KEY `batches_company_id_index` (`company_id`),
  KEY `batches_product_id_index` (`product_id`),
  KEY `batches_warehouse_id_index` (`warehouse_id`),
  KEY `batches_branch_id_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `branch_code` varchar(30) DEFAULT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_main_branch` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `branches_company_id_index` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `branch_settings`;
CREATE TABLE `branch_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `invoice_prefix` varchar(30) DEFAULT NULL,
  `quotation_prefix` varchar(30) DEFAULT NULL,
  `purchase_prefix` varchar(30) DEFAULT NULL,
  `receipt_prefix` varchar(30) DEFAULT NULL,
  `default_warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  `settings_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_settings_branch_id_unique` (`branch_id`),
  KEY `branch_settings_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `branch_users`;
CREATE TABLE `branch_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `can_switch` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_users_company_id_branch_id_user_id_unique` (`company_id`,`branch_id`,`user_id`),
  KEY `branch_users_company_id_index` (`company_id`),
  KEY `branch_users_branch_id_index` (`branch_id`),
  KEY `branch_users_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `business_communication_settings`;
CREATE TABLE `business_communication_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Kolkata',
  `preferred_send_time` varchar(10) NOT NULL DEFAULT '10:00',
  `quiet_hours_start` varchar(10) NOT NULL DEFAULT '21:00',
  `quiet_hours_end` varchar(10) NOT NULL DEFAULT '09:00',
  `email_configured` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_configured` tinyint(1) NOT NULL DEFAULT 0,
  `sms_configured` tinyint(1) NOT NULL DEFAULT 0,
  `settings_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_communication_settings_company_id_unique` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_category_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  KEY `categories_company_id_index` (`company_id`),
  KEY `categories_parent_category_id_index` (`parent_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chart_of_accounts`;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `account_code` varchar(255) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_type` varchar(255) NOT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_account_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_system_account` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `account_subtype` varchar(50) DEFAULT NULL,
  `nature` varchar(10) NOT NULL DEFAULT 'DEBIT',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance_type` varchar(10) NOT NULL DEFAULT 'DEBIT',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chart_of_accounts_company_id_index` (`company_id`),
  KEY `chart_of_accounts_account_code_index` (`account_code`),
  KEY `chart_of_accounts_parent_account_id_index` (`parent_account_id`),
  KEY `chart_of_accounts_branch_id_index` (`branch_id`),
  KEY `chart_of_accounts_account_subtype_index` (`account_subtype`),
  KEY `chart_of_accounts_nature_index` (`nature`)
) ENGINE=InnoDB AUTO_INCREMENT=1752 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cheques`;
CREATE TABLE `cheques` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `cheque_number` varchar(255) NOT NULL,
  `cheque_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payee` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'CLEARED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `cheque_type` varchar(20) NOT NULL DEFAULT 'RECEIVED',
  `party_type` varchar(20) NOT NULL DEFAULT 'CUSTOMER',
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `party_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  `deposit_bank_id` bigint(20) unsigned DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `deposit_date` date DEFAULT NULL,
  `clearance_date` date DEFAULT NULL,
  `bounce_date` date DEFAULT NULL,
  `bounce_reason` varchar(255) DEFAULT NULL,
  `bounce_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`),
  KEY `cheques_company_id_index` (`company_id`),
  KEY `cheques_branch_id_index` (`branch_id`),
  KEY `cheques_cheque_type_index` (`cheque_type`),
  KEY `cheques_party_type_index` (`party_type`),
  KEY `cheques_party_id_index` (`party_id`),
  KEY `cheques_bank_account_id_index` (`bank_account_id`),
  KEY `cheques_deposit_bank_id_index` (`deposit_bank_id`),
  KEY `cheques_payment_id_index` (`payment_id`),
  KEY `cheques_journal_entry_id_index` (`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cheque_events`;
CREATE TABLE `cheque_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `cheque_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `event_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_type` varchar(40) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cheque_events_company_id_index` (`company_id`),
  KEY `cheque_events_cheque_id_index` (`cheque_id`),
  KEY `cheque_events_to_status_index` (`to_status`),
  KEY `cheque_events_event_date_index` (`event_date`),
  KEY `cheque_events_event_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `communication_messages`;
CREATE TABLE `communication_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `channel` varchar(30) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `template_code` varchar(60) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables_json` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `provider_name` varchar(50) DEFAULT NULL,
  `provider_reference` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'QUEUED',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_messages_company_id_index` (`company_id`),
  KEY `communication_messages_branch_id_index` (`branch_id`),
  KEY `communication_messages_channel_index` (`channel`),
  KEY `communication_messages_status_index` (`status`),
  CONSTRAINT `communication_messages_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `communication_preferences`;
CREATE TABLE `communication_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `party_type` varchar(30) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `email_opt_in` tinyint(1) NOT NULL DEFAULT 1,
  `whatsapp_opt_in` tinyint(1) NOT NULL DEFAULT 1,
  `sms_opt_in` tinyint(1) NOT NULL DEFAULT 1,
  `promotional_opt_in` tinyint(1) NOT NULL DEFAULT 0,
  `preferred_channel` varchar(30) NOT NULL DEFAULT 'WHATSAPP',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_preferences_company_id_index` (`company_id`),
  KEY `communication_preferences_party_id_index` (`party_id`),
  CONSTRAINT `communication_preferences_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `communication_templates`;
CREATE TABLE `communication_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'EMAIL',
  `template_code` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Sales',
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables_json` text DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_templates_company_id_index` (`company_id`),
  KEY `communication_templates_template_code_index` (`template_code`),
  CONSTRAINT `communication_templates_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address_line1` text DEFAULT NULL,
  `address_line2` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `pincode` varchar(10) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `financial_year_start` varchar(255) NOT NULL DEFAULT '04-01',
  `logo_url` varchar(255) DEFAULT NULL,
  `business_type` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `branch_management_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `multi_warehouse_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `allow_negative_stock` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `companies_gstin_index` (`gstin`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_notes`;
CREATE TABLE `credit_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `credit_note_number` varchar(255) NOT NULL,
  `credit_note_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `original_invoice_number` varchar(255) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'APPROVED',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credit_notes_company_id_index` (`company_id`),
  KEY `credit_notes_customer_id_index` (`customer_id`),
  KEY `credit_notes_invoice_id_index` (`invoice_id`),
  KEY `credit_notes_credit_note_number_index` (`credit_note_number`),
  KEY `credit_notes_branch_id_index` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_note_items`;
CREATE TABLE `credit_note_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `credit_note_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `credit_note_items_company_id_index` (`company_id`),
  KEY `credit_note_items_credit_note_id_index` (`credit_note_id`),
  KEY `credit_note_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address_line1` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `pincode` varchar(10) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms_days` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `customer_type` varchar(255) NOT NULL DEFAULT 'Business',
  `alt_phone` varchar(255) DEFAULT NULL,
  `tax_type` varchar(255) NOT NULL DEFAULT 'Unregistered',
  `place_of_supply` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `notes` text DEFAULT NULL,
  `credit_period` int(11) NOT NULL DEFAULT 30,
  `opening_balance_type` varchar(255) NOT NULL DEFAULT 'Debit',
  `default_price_list` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `price_list_id` bigint(20) unsigned DEFAULT NULL,
  `reminder_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `preferred_channel` varchar(50) DEFAULT NULL,
  `whatsapp_number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_company_id_index` (`company_id`),
  KEY `customers_customer_group_id_index` (`customer_group_id`),
  KEY `customers_gstin_index` (`gstin`),
  KEY `customers_price_list_id_index` (`price_list_id`),
  KEY `idx_cust_comp_active` (`company_id`,`is_active`),
  KEY `idx_cust_comp_name` (`company_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'BILLING',
  `address_line1` text NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `pincode` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address_line2` text DEFAULT NULL,
  `country` varchar(255) NOT NULL DEFAULT 'India',
  `landmark` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_company_id_index` (`company_id`),
  KEY `customer_addresses_customer_id_index` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_advances`;
CREATE TABLE `customer_advances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `advance_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_advances_company_id_index` (`company_id`),
  KEY `customer_advances_branch_id_index` (`branch_id`),
  KEY `customer_advances_customer_id_index` (`customer_id`),
  KEY `customer_advances_payment_id_index` (`payment_id`),
  KEY `customer_advances_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_contacts`;
CREATE TABLE `customer_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `customer_contacts_company_id_index` (`company_id`),
  KEY `customer_contacts_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_credits`;
CREATE TABLE `customer_credits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `credit_type` varchar(40) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `applied_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_credits_company_id_index` (`company_id`),
  KEY `customer_credits_branch_id_index` (`branch_id`),
  KEY `customer_credits_customer_id_index` (`customer_id`),
  KEY `customer_credits_credit_type_index` (`credit_type`),
  KEY `customer_credits_source_id_index` (`source_id`),
  KEY `customer_credits_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_groups`;
CREATE TABLE `customer_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `default_price_list_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_groups_company_id_index` (`company_id`),
  KEY `customer_groups_default_price_list_id_index` (`default_price_list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_price_overrides`;
CREATE TABLE `customer_price_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cust_price_overrides_uniq` (`company_id`,`customer_id`,`product_id`),
  KEY `customer_price_overrides_company_id_index` (`company_id`),
  KEY `customer_price_overrides_customer_id_index` (`customer_id`),
  KEY `customer_price_overrides_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_tags`;
CREATE TABLE `customer_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `tag_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_tags_company_id_index` (`company_id`),
  KEY `customer_tags_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `debit_notes`;
CREATE TABLE `debit_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `debit_note_number` varchar(255) NOT NULL,
  `debit_note_date` date NOT NULL,
  `original_purchase_number` varchar(255) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_reversal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) NOT NULL DEFAULT 'Other',
  `status` varchar(255) NOT NULL DEFAULT 'APPROVED',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `debit_notes_company_id_index` (`company_id`),
  KEY `debit_notes_branch_id_index` (`branch_id`),
  KEY `debit_notes_supplier_id_index` (`supplier_id`),
  KEY `debit_notes_purchase_id_index` (`purchase_id`),
  KEY `debit_notes_debit_note_number_index` (`debit_note_number`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `debit_note_items`;
CREATE TABLE `debit_note_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `debit_note_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `debit_note_items_company_id_index` (`company_id`),
  KEY `debit_note_items_debit_note_id_index` (`debit_note_id`),
  KEY `debit_note_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_challans`;
CREATE TABLE `delivery_challans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `challan_number` varchar(255) NOT NULL,
  `challan_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `sales_order_id` bigint(20) unsigned DEFAULT NULL,
  `reference_so` varchar(255) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `transport_details` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'DRAFT',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_challans_company_id_index` (`company_id`),
  KEY `delivery_challans_customer_id_index` (`customer_id`),
  KEY `delivery_challans_challan_number_index` (`challan_number`),
  KEY `delivery_challans_branch_id_index` (`branch_id`),
  KEY `delivery_challans_sales_order_id_index` (`sales_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_challan_items`;
CREATE TABLE `delivery_challan_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `delivery_challan_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `batch_no` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_challan_items_company_id_index` (`company_id`),
  KEY `delivery_challan_items_delivery_challan_id_index` (`delivery_challan_id`),
  KEY `delivery_challan_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_audit_logs`;
CREATE TABLE `document_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `generated_document_id` bigint(20) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_name` varchar(255) NOT NULL DEFAULT 'System',
  `ip_address` varchar(45) NOT NULL DEFAULT '127.0.0.1',
  `user_agent` text DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_audit_logs_company_id_index` (`company_id`),
  KEY `document_audit_logs_generated_document_id_index` (`generated_document_id`),
  KEY `document_audit_logs_action_index` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_numbering_configs`;
CREATE TABLE `document_numbering_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `prefix` varchar(30) NOT NULL DEFAULT 'INV-',
  `suffix` varchar(30) DEFAULT NULL,
  `starting_number` bigint(20) NOT NULL DEFAULT 1,
  `current_number` bigint(20) NOT NULL DEFAULT 0,
  `number_padding` int(11) NOT NULL DEFAULT 4,
  `reset_frequency` varchar(20) NOT NULL DEFAULT 'YEARLY',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_numbering_configs_company_id_index` (`company_id`),
  KEY `document_numbering_configs_branch_id_index` (`branch_id`),
  KEY `document_numbering_configs_document_type_index` (`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=351 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_number_settings`;
CREATE TABLE `document_number_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `financial_year` varchar(255) NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `prefix` varchar(255) NOT NULL,
  `starting_number` int(11) NOT NULL DEFAULT 1,
  `current_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_number_scope` (`company_id`,`branch_id`,`financial_year`(50),`document_type`(50)),
  KEY `document_number_settings_company_id_index` (`company_id`),
  KEY `document_number_settings_branch_id_index` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=443 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_shares`;
CREATE TABLE `document_shares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `generated_document_id` bigint(20) unsigned NOT NULL,
  `share_token` varchar(64) NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'LINK',
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` varchar(255) DEFAULT NULL,
  `allow_download` tinyint(1) NOT NULL DEFAULT 1,
  `allow_print` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) DEFAULT NULL,
  `access_count` int(10) unsigned NOT NULL DEFAULT 0,
  `max_access_count` int(10) unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_shares_share_token_unique` (`share_token`),
  KEY `document_shares_company_id_index` (`company_id`),
  KEY `document_shares_generated_document_id_index` (`generated_document_id`),
  KEY `document_shares_channel_index` (`channel`),
  KEY `document_shares_expires_at_index` (`expires_at`),
  KEY `document_shares_is_revoked_index` (`is_revoked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_templates`;
CREATE TABLE `document_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `template_key` varchar(50) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `paper_size` varchar(20) NOT NULL DEFAULT 'A4',
  `orientation` varchar(20) NOT NULL DEFAULT 'PORTRAIT',
  `brand_color` varchar(20) NOT NULL DEFAULT '#1e40af',
  `accent_color` varchar(20) NOT NULL DEFAULT '#3b82f6',
  `font_family` varchar(50) NOT NULL DEFAULT 'Inter',
  `logo_position` varchar(20) NOT NULL DEFAULT 'LEFT',
  `watermark_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `watermark_text` varchar(255) DEFAULT NULL,
  `watermark_opacity` decimal(3,2) NOT NULL DEFAULT 0.08,
  `show_bank_details` tinyint(1) NOT NULL DEFAULT 1,
  `show_upi_qr` tinyint(1) NOT NULL DEFAULT 1,
  `show_signature` tinyint(1) NOT NULL DEFAULT 1,
  `show_stamp` tinyint(1) NOT NULL DEFAULT 0,
  `show_hsn_summary` tinyint(1) NOT NULL DEFAULT 1,
  `show_tax_breakdown` tinyint(1) NOT NULL DEFAULT 1,
  `show_amount_in_words` tinyint(1) NOT NULL DEFAULT 1,
  `show_terms` tinyint(1) NOT NULL DEFAULT 1,
  `default_terms` text DEFAULT NULL,
  `default_notes` text DEFAULT NULL,
  `custom_css` text DEFAULT NULL,
  `config_json` longtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_templates_company_id_index` (`company_id`),
  KEY `document_templates_branch_id_index` (`branch_id`),
  KEY `document_templates_document_type_index` (`document_type`),
  KEY `document_templates_template_key_index` (`template_key`),
  KEY `document_templates_is_default_index` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_template_versions`;
CREATE TABLE `document_template_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned NOT NULL,
  `version_number` int(11) NOT NULL,
  `config_json` longtext DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_template_versions_company_id_index` (`company_id`),
  KEY `document_template_versions_template_id_index` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `expense_number` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `payee` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(255) NOT NULL DEFAULT 'Bank Transfer',
  `gstin` varchar(15) DEFAULT NULL,
  `is_itc_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `reference_no` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_company_id_index` (`company_id`),
  KEY `expenses_branch_id_index` (`branch_id`),
  KEY `expenses_expense_number_index` (`expense_number`),
  KEY `idx_exp_comp_date` (`company_id`,`expense_date`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_categories_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `export_jobs`;
CREATE TABLE `export_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `data_type` varchar(50) NOT NULL,
  `export_format` varchar(20) NOT NULL DEFAULT 'CSV',
  `columns_json` longtext DEFAULT NULL,
  `filters_json` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `download_token` varchar(80) DEFAULT NULL,
  `download_token_expires_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'QUEUED',
  `error_message` text DEFAULT NULL,
  `total_records` int(11) NOT NULL DEFAULT 0,
  `correlation_id` varchar(60) DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT 'System',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `export_jobs_download_token_unique` (`download_token`),
  KEY `export_jobs_company_id_index` (`company_id`),
  KEY `export_jobs_branch_id_index` (`branch_id`),
  KEY `export_jobs_data_type_index` (`data_type`),
  KEY `export_jobs_download_token_expires_at_index` (`download_token_expires_at`),
  KEY `export_jobs_status_index` (`status`),
  KEY `export_jobs_correlation_id_index` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `e_invoices`;
CREATE TABLE `e_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `irn` varchar(64) DEFAULT NULL,
  `ack_no` varchar(50) DEFAULT NULL,
  `ack_date` varchar(30) DEFAULT NULL,
  `signed_invoice` longtext DEFAULT NULL,
  `signed_qr_data` longtext DEFAULT NULL,
  `qr_code_url` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'NOT_REQUIRED',
  `is_sandbox` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `cancel_reason` varchar(10) DEFAULT NULL,
  `cancel_remarks` text DEFAULT NULL,
  `cancel_date` timestamp NULL DEFAULT NULL,
  `generated_by` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `e_invoices_company_id_invoice_id_unique` (`company_id`,`invoice_id`),
  KEY `e_invoices_company_id_index` (`company_id`),
  KEY `e_invoices_branch_id_index` (`branch_id`),
  KEY `e_invoices_invoice_id_index` (`invoice_id`),
  KEY `e_invoices_irn_index` (`irn`),
  KEY `e_invoices_ack_no_index` (`ack_no`),
  KEY `e_invoices_status_index` (`status`),
  KEY `e_invoices_is_sandbox_index` (`is_sandbox`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `e_invoice_status_history`;
CREATE TABLE `e_invoice_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `e_invoice_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `e_invoice_status_history_e_invoice_id_index` (`e_invoice_id`),
  KEY `e_invoice_status_history_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `e_way_bills`;
CREATE TABLE `e_way_bills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `ewb_number` varchar(30) DEFAULT NULL,
  `ewb_date` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'NOT_REQUIRED',
  `transport_mode` varchar(20) NOT NULL DEFAULT 'ROAD',
  `transporter_id` varchar(20) DEFAULT NULL,
  `transporter_name` varchar(255) DEFAULT NULL,
  `transport_doc_no` varchar(50) DEFAULT NULL,
  `transport_doc_date` date DEFAULT NULL,
  `vehicle_no` varchar(30) DEFAULT NULL,
  `vehicle_type` varchar(20) NOT NULL DEFAULT 'REGULAR',
  `from_pincode` varchar(10) DEFAULT NULL,
  `to_pincode` varchar(10) DEFAULT NULL,
  `distance_km` int(11) NOT NULL DEFAULT 0,
  `is_sandbox` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `cancel_reason` varchar(10) DEFAULT NULL,
  `cancel_remarks` text DEFAULT NULL,
  `cancel_date` timestamp NULL DEFAULT NULL,
  `generated_by` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `e_way_bills_company_id_invoice_id_index` (`company_id`,`invoice_id`),
  KEY `e_way_bills_company_id_ewb_number_index` (`company_id`,`ewb_number`),
  KEY `e_way_bills_company_id_index` (`company_id`),
  KEY `e_way_bills_branch_id_index` (`branch_id`),
  KEY `e_way_bills_invoice_id_index` (`invoice_id`),
  KEY `e_way_bills_ewb_number_index` (`ewb_number`),
  KEY `e_way_bills_status_index` (`status`),
  KEY `e_way_bills_transporter_id_index` (`transporter_id`),
  KEY `e_way_bills_is_sandbox_index` (`is_sandbox`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `e_way_bill_status_history`;
CREATE TABLE `e_way_bill_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `e_way_bill_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `e_way_bill_status_history_e_way_bill_id_index` (`e_way_bill_id`),
  KEY `e_way_bill_status_history_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `generated_documents`;
CREATE TABLE `generated_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_id` varchar(100) NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `document_date` date NOT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `mime_type` varchar(50) NOT NULL DEFAULT 'application/pdf',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `checksum_hash` varchar(64) DEFAULT NULL,
  `snapshot_json` longtext DEFAULT NULL,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 0,
  `generated_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `generated_documents_company_id_index` (`company_id`),
  KEY `generated_documents_branch_id_index` (`branch_id`),
  KEY `generated_documents_document_type_index` (`document_type`),
  KEY `generated_documents_source_type_index` (`source_type`),
  KEY `generated_documents_source_id_index` (`source_id`),
  KEY `generated_documents_document_number_index` (`document_number`),
  KEY `generated_documents_document_date_index` (`document_date`),
  KEY `generated_documents_template_id_index` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `goods_receipts`;
CREATE TABLE `goods_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `grn_number` varchar(255) NOT NULL,
  `grn_date` date NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'DRAFT',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipts_company_id_index` (`company_id`),
  KEY `goods_receipts_branch_id_index` (`branch_id`),
  KEY `goods_receipts_supplier_id_index` (`supplier_id`),
  KEY `goods_receipts_purchase_order_id_index` (`purchase_order_id`),
  KEY `goods_receipts_grn_number_index` (`grn_number`),
  KEY `goods_receipts_warehouse_id_index` (`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `goods_receipt_items`;
CREATE TABLE `goods_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `goods_receipt_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity_ordered` decimal(15,3) NOT NULL DEFAULT 0.000,
  `quantity_received` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_items_company_id_index` (`company_id`),
  KEY `goods_receipt_items_goods_receipt_id_index` (`goods_receipt_id`),
  KEY `goods_receipt_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gstin_validations`;
CREATE TABLE `gstin_validations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `gstin` varchar(15) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL,
  `state_name` varchar(100) DEFAULT NULL,
  `taxpayer_type` varchar(40) DEFAULT NULL,
  `registration_status` varchar(30) DEFAULT NULL,
  `is_format_valid` tinyint(1) NOT NULL DEFAULT 0,
  `is_portal_verified` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(40) NOT NULL DEFAULT 'LOCAL_CHECKSUM',
  `response_reference` varchar(255) DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gstin_validations_gstin_is_portal_verified_index` (`gstin`,`is_portal_verified`),
  KEY `gstin_validations_company_id_index` (`company_id`),
  KEY `gstin_validations_gstin_index` (`gstin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_configurations`;
CREATE TABLE `gst_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `gstin` varchar(15) NOT NULL,
  `composition_scheme` varchar(255) NOT NULL DEFAULT 'NO',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `gst_registered` tinyint(1) NOT NULL DEFAULT 1,
  `legal_business_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `registered_state` varchar(100) NOT NULL DEFAULT 'Maharashtra',
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `tax_registration_type` varchar(30) NOT NULL DEFAULT 'REGISTERED_REGULAR',
  `is_composition` tinyint(1) NOT NULL DEFAULT 0,
  `default_place_of_supply` varchar(100) NOT NULL DEFAULT 'Maharashtra',
  `einvoice_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `einvoice_threshold` decimal(15,2) NOT NULL DEFAULT 50000000.00,
  `eway_bill_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `eway_threshold` decimal(15,2) NOT NULL DEFAULT 50000.00,
  `filing_frequency` varchar(20) NOT NULL DEFAULT 'MONTHLY',
  `api_environment` varchar(20) NOT NULL DEFAULT 'SANDBOX',
  PRIMARY KEY (`id`),
  KEY `gst_configurations_company_id_index` (`company_id`),
  KEY `gst_configurations_branch_id_index` (`branch_id`),
  KEY `gst_configurations_pan_index` (`pan`),
  KEY `gst_configurations_state_code_index` (`state_code`),
  KEY `gst_configurations_tax_registration_type_index` (`tax_registration_type`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_document_snapshots`;
CREATE TABLE `gst_document_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` varchar(30) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `document_date` date NOT NULL,
  `seller_gstin` varchar(15) DEFAULT NULL,
  `seller_state_code` varchar(2) DEFAULT NULL,
  `buyer_gstin` varchar(15) DEFAULT NULL,
  `buyer_state_code` varchar(2) DEFAULT NULL,
  `place_of_supply` varchar(100) NOT NULL,
  `supply_type` varchar(20) NOT NULL DEFAULT 'INTRA_STATE',
  `gst_category` varchar(30) NOT NULL DEFAULT 'B2B',
  `is_reverse_charge` tinyint(1) NOT NULL DEFAULT 0,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cess_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_document_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `lines_snapshot_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gst_doc_snaps_unique` (`company_id`,`document_type`,`document_id`),
  KEY `gst_doc_snaps_date_cat_idx` (`company_id`,`document_date`,`gst_category`),
  KEY `gst_document_snapshots_company_id_index` (`company_id`),
  KEY `gst_document_snapshots_branch_id_index` (`branch_id`),
  KEY `gst_document_snapshots_document_type_index` (`document_type`),
  KEY `gst_document_snapshots_document_id_index` (`document_id`),
  KEY `gst_document_snapshots_document_number_index` (`document_number`),
  KEY `gst_document_snapshots_document_date_index` (`document_date`),
  KEY `gst_document_snapshots_seller_gstin_index` (`seller_gstin`),
  KEY `gst_document_snapshots_buyer_gstin_index` (`buyer_gstin`),
  KEY `gst_document_snapshots_place_of_supply_index` (`place_of_supply`),
  KEY `gst_document_snapshots_supply_type_index` (`supply_type`),
  KEY `gst_document_snapshots_gst_category_index` (`gst_category`),
  KEY `gst_document_snapshots_is_reverse_charge_index` (`is_reverse_charge`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_filing_periods`;
CREATE TABLE `gst_filing_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `period_name` varchar(20) NOT NULL,
  `return_type` varchar(20) NOT NULL DEFAULT 'GSTR1',
  `status` varchar(30) NOT NULL DEFAULT 'DRAFT',
  `summary_data_json` longtext DEFAULT NULL,
  `filing_date` date DEFAULT NULL,
  `arn_number` varchar(50) DEFAULT NULL,
  `filed_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gst_filing_periods_unique` (`company_id`,`financial_year`,`period_name`,`return_type`),
  KEY `gst_filing_periods_company_id_index` (`company_id`),
  KEY `gst_filing_periods_financial_year_index` (`financial_year`),
  KEY `gst_filing_periods_period_name_index` (`period_name`),
  KEY `gst_filing_periods_return_type_index` (`return_type`),
  KEY `gst_filing_periods_status_index` (`status`),
  KEY `gst_filing_periods_arn_number_index` (`arn_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_rates`;
CREATE TABLE `gst_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `rate` decimal(5,2) NOT NULL,
  `cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gst_rates_company_id_index` (`company_id`),
  KEY `gst_rates_rate_index` (`rate`),
  KEY `gst_rates_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_reconciliation`;
CREATE TABLE `gst_reconciliation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `period_month` varchar(10) NOT NULL,
  `return_type` varchar(20) NOT NULL DEFAULT 'GSTR2B',
  `status` varchar(30) NOT NULL DEFAULT 'IN_PROGRESS',
  `total_portal_records` int(11) NOT NULL DEFAULT 0,
  `total_books_records` int(11) NOT NULL DEFAULT 0,
  `matched_count` int(11) NOT NULL DEFAULT 0,
  `partial_match_count` int(11) NOT NULL DEFAULT 0,
  `mismatch_count` int(11) NOT NULL DEFAULT 0,
  `books_only_count` int(11) NOT NULL DEFAULT 0,
  `portal_only_count` int(11) NOT NULL DEFAULT 0,
  `imported_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gst_reconciliation_company_id_financial_year_period_month_index` (`company_id`,`financial_year`,`period_month`),
  KEY `gst_reconciliation_company_id_index` (`company_id`),
  KEY `gst_reconciliation_financial_year_index` (`financial_year`),
  KEY `gst_reconciliation_period_month_index` (`period_month`),
  KEY `gst_reconciliation_return_type_index` (`return_type`),
  KEY `gst_reconciliation_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_reconciliation_items`;
CREATE TABLE `gst_reconciliation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `reconciliation_id` bigint(20) unsigned NOT NULL,
  `gstin` varchar(15) NOT NULL,
  `party_name` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `books_taxable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `portal_taxable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `books_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `portal_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cess_diff` decimal(15,2) NOT NULL DEFAULT 0.00,
  `match_status` varchar(30) NOT NULL DEFAULT 'MISMATCH',
  `action_taken` varchar(30) NOT NULL DEFAULT 'NONE',
  `action_notes` text DEFAULT NULL,
  `reviewed_by` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gst_reconciliation_items_company_id_match_status_index` (`company_id`,`match_status`),
  KEY `gst_reconciliation_items_reconciliation_id_gstin_index` (`reconciliation_id`,`gstin`),
  KEY `gst_reconciliation_items_company_id_index` (`company_id`),
  KEY `gst_reconciliation_items_reconciliation_id_index` (`reconciliation_id`),
  KEY `gst_reconciliation_items_gstin_index` (`gstin`),
  KEY `gst_reconciliation_items_invoice_number_index` (`invoice_number`),
  KEY `gst_reconciliation_items_invoice_date_index` (`invoice_date`),
  KEY `gst_reconciliation_items_match_status_index` (`match_status`),
  KEY `gst_reconciliation_items_action_taken_index` (`action_taken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gst_transactions`;
CREATE TABLE `gst_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `reference_number` varchar(255) NOT NULL,
  `transaction_date` date NOT NULL,
  `taxable_value` decimal(15,2) NOT NULL,
  `cgst` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gst_transactions_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hsn_sac_master`;
CREATE TABLE `hsn_sac_master` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(20) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'HSN',
  `description` varchar(255) NOT NULL,
  `default_gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `default_cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hsn_sac_master_code_type_index` (`code`,`type`),
  KEY `hsn_sac_master_company_id_index` (`company_id`),
  KEY `hsn_sac_master_code_index` (`code`),
  KEY `hsn_sac_master_type_index` (`type`),
  KEY `hsn_sac_master_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `import_jobs`;
CREATE TABLE `import_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `data_type` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(20) NOT NULL DEFAULT 'CSV',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `valid_rows` int(11) NOT NULL DEFAULT 0,
  `warning_rows` int(11) NOT NULL DEFAULT 0,
  `error_rows` int(11) NOT NULL DEFAULT 0,
  `duplicate_rows` int(11) NOT NULL DEFAULT 0,
  `processed_rows` int(11) NOT NULL DEFAULT 0,
  `duplicate_action` varchar(30) NOT NULL DEFAULT 'SKIP',
  `mapping_config_json` longtext DEFAULT NULL,
  `validation_summary_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'UPLOADED',
  `error_message` text DEFAULT NULL,
  `error_report_path` varchar(500) DEFAULT NULL,
  `correlation_id` varchar(60) DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT 'System',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_jobs_company_id_index` (`company_id`),
  KEY `import_jobs_branch_id_index` (`branch_id`),
  KEY `import_jobs_data_type_index` (`data_type`),
  KEY `import_jobs_status_index` (`status`),
  KEY `import_jobs_correlation_id_index` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `import_row_logs`;
CREATE TABLE `import_row_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_job_id` bigint(20) unsigned NOT NULL,
  `row_index` int(11) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'VALID',
  `raw_data_json` longtext DEFAULT NULL,
  `parsed_data_json` longtext DEFAULT NULL,
  `issues_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_row_logs_import_job_id_index` (`import_job_id`),
  KEY `import_row_logs_row_index_index` (`row_index`),
  KEY `import_row_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `indian_states`;
CREATE TABLE `indian_states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indian_states_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `reference_po_number` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `place_of_supply` varchar(2) NOT NULL DEFAULT '27',
  `is_igst` tinyint(1) NOT NULL DEFAULT 0,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `round_off` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'PAID',
  `payment_mode` varchar(255) NOT NULL DEFAULT 'Bank Transfer',
  `notes` text DEFAULT NULL,
  `terms_and_conditions` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payment_status` varchar(255) NOT NULL DEFAULT 'UNPAID',
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_gstin` varchar(255) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `source_quotation_id` bigint(20) unsigned DEFAULT NULL,
  `source_sales_order_id` bigint(20) unsigned DEFAULT NULL,
  `source_delivery_challan_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_invoice_number` (`company_id`,`invoice_number`),
  KEY `invoices_company_id_index` (`company_id`),
  KEY `invoices_branch_id_index` (`branch_id`),
  KEY `invoices_customer_id_index` (`customer_id`),
  KEY `invoices_invoice_number_index` (`invoice_number`),
  KEY `invoices_source_quotation_id_index` (`source_quotation_id`),
  KEY `invoices_source_sales_order_id_index` (`source_sales_order_id`),
  KEY `invoices_source_delivery_challan_id_index` (`source_delivery_challan_id`),
  KEY `invoices_warehouse_id_index` (`warehouse_id`),
  KEY `idx_invoices_comp_status` (`company_id`,`status`),
  KEY `idx_invoices_comp_branch` (`company_id`,`branch_id`),
  KEY `idx_invoices_comp_cust` (`company_id`,`customer_id`),
  KEY `idx_invoices_comp_date` (`company_id`,`invoice_date`),
  KEY `idx_invoices_comp_created` (`company_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=328 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_company_id_index` (`company_id`),
  KEY `invoice_items_invoice_id_index` (`invoice_id`),
  KEY `invoice_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=312 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `in_app_notifications`;
CREATE TABLE `in_app_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'SYSTEM',
  `priority` varchar(20) NOT NULL DEFAULT 'NORMAL',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `read_status` varchar(100) DEFAULT NULL,
  `action_url` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `in_app_notifications_company_id_is_read_index` (`company_id`,`is_read`),
  KEY `in_app_notifications_company_id_index` (`company_id`),
  KEY `in_app_notifications_user_id_index` (`user_id`),
  KEY `in_app_notifications_category_index` (`category`),
  KEY `in_app_notifications_priority_index` (`priority`),
  KEY `in_app_notifications_is_read_index` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `entry_number` varchar(255) NOT NULL,
  `entry_date` date NOT NULL,
  `narration` text NOT NULL,
  `total_debit` decimal(15,2) NOT NULL,
  `total_credit` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `journal_number` varchar(50) DEFAULT NULL,
  `entry_type` varchar(40) NOT NULL DEFAULT 'MANUAL',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'POSTED',
  `is_system_generated` tinyint(1) NOT NULL DEFAULT 0,
  `original_journal_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_journal_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `posted_by` varchar(255) DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `reversed_by` varchar(255) DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_company_id_index` (`company_id`),
  KEY `journal_entries_entry_number_index` (`entry_number`),
  KEY `journal_entries_branch_id_index` (`branch_id`),
  KEY `journal_entries_financial_year_index` (`financial_year`),
  KEY `journal_entries_journal_number_index` (`journal_number`),
  KEY `journal_entries_entry_type_index` (`entry_type`),
  KEY `journal_entries_reference_type_index` (`reference_type`),
  KEY `journal_entries_reference_id_index` (`reference_id`),
  KEY `journal_entries_status_index` (`status`),
  KEY `journal_entries_is_system_generated_index` (`is_system_generated`),
  KEY `journal_entries_original_journal_id_index` (`original_journal_id`),
  KEY `journal_entries_reversal_journal_id_index` (`reversal_journal_id`),
  KEY `idx_je_comp_status_date` (`company_id`,`status`,`entry_date`),
  KEY `idx_je_comp_fy` (`company_id`,`financial_year`)
) ENGINE=InnoDB AUTO_INCREMENT=771 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journal_entry_lines`;
CREATE TABLE `journal_entry_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `journal_entry_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `debit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entry_lines_company_id_index` (`company_id`),
  KEY `journal_entry_lines_journal_entry_id_index` (`journal_entry_id`),
  KEY `journal_entry_lines_account_id_index` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journal_lines`;
CREATE TABLE `journal_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `journal_entry_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `party_type` varchar(30) DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `tax_category` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_lines_company_id_account_id_index` (`company_id`,`account_id`),
  KEY `journal_lines_company_id_journal_entry_id_account_id_index` (`company_id`,`journal_entry_id`,`account_id`),
  KEY `journal_lines_company_id_index` (`company_id`),
  KEY `journal_lines_branch_id_index` (`branch_id`),
  KEY `journal_lines_journal_entry_id_index` (`journal_entry_id`),
  KEY `journal_lines_account_id_index` (`account_id`),
  KEY `journal_lines_party_type_index` (`party_type`),
  KEY `journal_lines_party_id_index` (`party_id`),
  KEY `journal_lines_product_id_index` (`product_id`),
  KEY `journal_lines_cost_center_index` (`cost_center`),
  KEY `journal_lines_tax_category_index` (`tax_category`),
  KEY `idx_jl_comp_acc` (`company_id`,`account_id`),
  KEY `idx_jl_entry_acc` (`journal_entry_id`,`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2359 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ledger_entries`;
CREATE TABLE `ledger_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ledger_entries_company_id_index` (`company_id`),
  KEY `ledger_entries_account_id_index` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `migration_profiles`;
CREATE TABLE `migration_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `data_type` varchar(50) NOT NULL,
  `source_software` varchar(50) NOT NULL DEFAULT 'GENERIC',
  `mapping_json` longtext NOT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `migration_profiles_company_id_index` (`company_id`),
  KEY `migration_profiles_data_type_index` (`data_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_company_id_index` (`company_id`),
  KEY `notifications_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_events`;
CREATE TABLE `notification_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payload_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_events_company_id_index` (`company_id`),
  KEY `notification_events_branch_id_index` (`branch_id`),
  KEY `notification_events_event_type_index` (`event_type`),
  KEY `notification_events_entity_type_index` (`entity_type`),
  KEY `notification_events_entity_id_index` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `channel` varchar(30) NOT NULL,
  `recipient` varchar(191) DEFAULT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `provider_message_id` varchar(100) DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `idempotency_key` varchar(191) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_logs_idempotency_key_unique` (`idempotency_key`),
  KEY `notification_logs_company_id_entity_type_entity_id_index` (`company_id`,`entity_type`,`entity_id`),
  KEY `notification_logs_company_id_index` (`company_id`),
  KEY `notification_logs_branch_id_index` (`branch_id`),
  KEY `notification_logs_event_type_index` (`event_type`),
  KEY `notification_logs_entity_type_index` (`entity_type`),
  KEY `notification_logs_entity_id_index` (`entity_id`),
  KEY `notification_logs_channel_index` (`channel`),
  KEY `notification_logs_recipient_index` (`recipient`),
  KEY `notification_logs_template_id_index` (`template_id`),
  KEY `notification_logs_status_index` (`status`),
  KEY `notification_logs_provider_message_id_index` (`provider_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_preferences`;
CREATE TABLE `notification_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_company_id_user_id_category_unique` (`company_id`,`user_id`,`category`),
  KEY `notification_preferences_company_id_index` (`company_id`),
  KEY `notification_preferences_user_id_index` (`user_id`),
  KEY `notification_preferences_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_rules`;
CREATE TABLE `notification_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `rule_name` varchar(120) NOT NULL,
  `days_offset` int(11) NOT NULL DEFAULT 0,
  `channel_priority` varchar(100) NOT NULL DEFAULT 'WHATSAPP,EMAIL',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `config_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_rules_company_id_index` (`company_id`),
  KEY `notification_rules_event_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_templates`;
CREATE TABLE `notification_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `template_key` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'EMAIL',
  `category` varchar(60) NOT NULL DEFAULT 'PAYMENTS',
  `subject` varchar(200) DEFAULT NULL,
  `body_template` longtext NOT NULL,
  `variables_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_templates_company_id_index` (`company_id`),
  KEY `notification_templates_template_key_index` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `payment_number` varchar(255) NOT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `party_type` varchar(255) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_mode` varchar(255) NOT NULL DEFAULT 'NEFT/RTGS',
  `reference_no` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `payment_type` varchar(30) NOT NULL DEFAULT 'RECEIPT',
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unallocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `transaction_reference` varchar(255) DEFAULT NULL,
  `bank_reference` varchar(255) DEFAULT NULL,
  `utr` varchar(255) DEFAULT NULL,
  `cheque_number` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `cheque_bank` varchar(255) DEFAULT NULL,
  `cheque_status` varchar(30) NOT NULL DEFAULT 'RECEIVED',
  `notes` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'DRAFT',
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `updated_by` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_company_id_index` (`company_id`),
  KEY `payments_payment_number_index` (`payment_number`),
  KEY `payments_party_id_index` (`party_id`),
  KEY `payments_branch_id_index` (`branch_id`),
  KEY `payments_financial_year_index` (`financial_year`),
  KEY `payments_payment_type_index` (`payment_type`),
  KEY `payments_account_id_index` (`account_id`),
  KEY `payments_reference_number_index` (`reference_number`),
  KEY `payments_cheque_status_index` (`cheque_status`),
  KEY `payments_status_index` (`status`),
  KEY `payments_receipt_number_index` (`receipt_number`),
  KEY `payments_bank_account_id_index` (`bank_account_id`),
  KEY `idx_paym_comp_party` (`company_id`,`party_id`,`party_type`),
  KEY `idx_paym_comp_date` (`company_id`,`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_allocations`;
CREATE TABLE `payment_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `allocation_date` date NOT NULL DEFAULT '2026-10-03',
  `notes` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`),
  KEY `payment_allocations_company_id_index` (`company_id`),
  KEY `payment_allocations_payment_id_index` (`payment_id`),
  KEY `payment_allocations_document_id_index` (`document_id`),
  KEY `payment_allocations_branch_id_index` (`branch_id`),
  KEY `payment_allocations_allocation_date_index` (`allocation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateway_events`;
CREATE TABLE `payment_gateway_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(30) NOT NULL,
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `payload_json` longtext DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PROCESSED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateway_events_event_id_unique` (`event_id`),
  KEY `payment_gateway_events_provider_index` (`provider`),
  KEY `payment_gateway_events_event_type_index` (`event_type`),
  KEY `payment_gateway_events_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateway_settlements`;
CREATE TABLE `payment_gateway_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(40) NOT NULL,
  `settlement_id` varchar(100) NOT NULL,
  `settlement_date` date NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'MATCHED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateway_settlements_settlement_id_unique` (`settlement_id`),
  KEY `payment_gateway_settlements_company_id_index` (`company_id`),
  KEY `payment_gateway_settlements_provider_index` (`provider`),
  KEY `payment_gateway_settlements_bank_account_id_index` (`bank_account_id`),
  KEY `payment_gateway_settlements_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateway_transactions`;
CREATE TABLE `payment_gateway_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `payment_link_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'SIMULATION',
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_signature` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `status` varchar(30) NOT NULL DEFAULT 'SUCCESS',
  `is_sandbox` tinyint(1) NOT NULL DEFAULT 1,
  `webhook_event_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `raw_payload_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_gateway_transactions_company_id_index` (`company_id`),
  KEY `payment_gateway_transactions_payment_link_id_index` (`payment_link_id`),
  KEY `payment_gateway_transactions_provider_index` (`provider`),
  KEY `payment_gateway_transactions_gateway_order_id_index` (`gateway_order_id`),
  KEY `payment_gateway_transactions_gateway_payment_id_index` (`gateway_payment_id`),
  KEY `payment_gateway_transactions_status_index` (`status`),
  KEY `payment_gateway_transactions_is_sandbox_index` (`is_sandbox`),
  KEY `payment_gateway_transactions_webhook_event_id_index` (`webhook_event_id`),
  KEY `payment_gateway_transactions_payment_id_index` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateway_webhooks`;
CREATE TABLE `payment_gateway_webhooks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(40) NOT NULL,
  `event_id` varchar(120) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'RECEIVED',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gw_webhook_uniq` (`provider`,`event_id`),
  KEY `payment_gateway_webhooks_company_id_index` (`company_id`),
  KEY `payment_gateway_webhooks_provider_index` (`provider`),
  KEY `payment_gateway_webhooks_event_id_index` (`event_id`),
  KEY `payment_gateway_webhooks_event_type_index` (`event_type`),
  KEY `payment_gateway_webhooks_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_links`;
CREATE TABLE `payment_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `link_id` varchar(60) NOT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `provider` varchar(30) NOT NULL DEFAULT 'SIMULATION',
  `external_reference` varchar(100) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'CREATED',
  `allow_partial` tinyint(1) NOT NULL DEFAULT 0,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token` varchar(80) DEFAULT NULL,
  `original_invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_partial_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `min_amount` decimal(15,2) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_links_company_id_link_id_unique` (`company_id`,`link_id`),
  UNIQUE KEY `payment_links_token_unique` (`token`),
  KEY `payment_links_company_id_index` (`company_id`),
  KEY `payment_links_branch_id_index` (`branch_id`),
  KEY `payment_links_link_id_index` (`link_id`),
  KEY `payment_links_invoice_id_index` (`invoice_id`),
  KEY `payment_links_customer_id_index` (`customer_id`),
  KEY `payment_links_provider_index` (`provider`),
  KEY `payment_links_external_reference_index` (`external_reference`),
  KEY `payment_links_status_index` (`status`),
  KEY `payment_links_expires_at_index` (`expires_at`),
  KEY `payment_links_payment_id_index` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_link_events`;
CREATE TABLE `payment_link_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_link_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_link_events_payment_link_id_index` (`payment_link_id`),
  KEY `payment_link_events_event_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_refunds`;
CREATE TABLE `payment_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `financial_year` varchar(20) NOT NULL DEFAULT '2026-27',
  `refund_number` varchar(255) NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `party_type` varchar(30) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `refund_date` date NOT NULL,
  `refund_mode` varchar(30) NOT NULL DEFAULT 'CASH',
  `reference_no` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'POSTED',
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_mode` varchar(40) NOT NULL DEFAULT 'BANK_TRANSFER',
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_refunds_company_id_refund_number_unique` (`company_id`,`refund_number`),
  KEY `payment_refunds_company_id_index` (`company_id`),
  KEY `payment_refunds_branch_id_index` (`branch_id`),
  KEY `payment_refunds_financial_year_index` (`financial_year`),
  KEY `payment_refunds_refund_number_index` (`refund_number`),
  KEY `payment_refunds_payment_id_index` (`payment_id`),
  KEY `payment_refunds_party_type_index` (`party_type`),
  KEY `payment_refunds_party_id_index` (`party_id`),
  KEY `payment_refunds_refund_date_index` (`refund_date`),
  KEY `payment_refunds_status_index` (`status`),
  KEY `payment_refunds_bank_account_id_index` (`bank_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_reminders`;
CREATE TABLE `payment_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'WHATSAPP',
  `sent_date` date NOT NULL,
  `days_overdue` int(11) NOT NULL DEFAULT 0,
  `outstanding_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'SENT',
  `message_template_id` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_reminders_company_id_index` (`company_id`),
  KEY `payment_reminders_branch_id_index` (`branch_id`),
  KEY `payment_reminders_customer_id_index` (`customer_id`),
  KEY `payment_reminders_invoice_id_index` (`invoice_id`),
  KEY `payment_reminders_channel_index` (`channel`),
  KEY `payment_reminders_sent_date_index` (`sent_date`),
  KEY `payment_reminders_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_settlements`;
CREATE TABLE `payment_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `settlement_id` varchar(100) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'SIMULATION',
  `settlement_date` date NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `bank_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'SETTLED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_settlements_company_id_settlement_id_unique` (`company_id`,`settlement_id`),
  KEY `payment_settlements_company_id_index` (`company_id`),
  KEY `payment_settlements_settlement_id_index` (`settlement_id`),
  KEY `payment_settlements_provider_index` (`provider`),
  KEY `payment_settlements_settlement_date_index` (`settlement_date`),
  KEY `payment_settlements_bank_account_id_index` (`bank_account_id`),
  KEY `payment_settlements_bank_transaction_id_index` (`bank_transaction_id`),
  KEY `payment_settlements_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'default',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_user_id_index` (`user_id`),
  KEY `personal_access_tokens_company_id_index` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `price_histories`;
CREATE TABLE `price_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price_list_id` bigint(20) unsigned DEFAULT NULL,
  `old_price` decimal(15,2) NOT NULL,
  `new_price` decimal(15,2) NOT NULL,
  `changed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `changed_by_name` varchar(100) NOT NULL DEFAULT 'System',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_histories_company_id_index` (`company_id`),
  KEY `price_histories_product_id_index` (`product_id`),
  KEY `price_histories_price_list_id_index` (`price_list_id`),
  KEY `price_histories_changed_by_user_id_index` (`changed_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `price_lists`;
CREATE TABLE `price_lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `price_type` varchar(30) NOT NULL DEFAULT 'FIXED',
  `adjustment_value` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tax_mode` varchar(30) NOT NULL DEFAULT 'TAX_EXCLUSIVE',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_lists_company_id_index` (`company_id`),
  KEY `price_lists_branch_id_index` (`branch_id`),
  KEY `price_lists_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `price_list_items`;
CREATE TABLE `price_list_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `price_list_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `minimum_quantity` decimal(15,3) NOT NULL DEFAULT 1.000,
  `maximum_quantity` decimal(15,3) DEFAULT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_list_items_price_list_id_product_id_index` (`price_list_id`,`product_id`),
  KEY `price_list_items_company_id_index` (`company_id`),
  KEY `price_list_items_price_list_id_index` (`price_list_id`),
  KEY `price_list_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `subcategory_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `sales_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `current_stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `min_stock_alert` decimal(15,3) NOT NULL DEFAULT 10.000,
  `barcode` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `has_batch` tinyint(1) NOT NULL DEFAULT 0,
  `has_serial` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_type` varchar(255) NOT NULL DEFAULT 'Goods',
  `mrp` decimal(15,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `secondary_unit` varchar(255) DEFAULT NULL,
  `conversion_ratio` decimal(15,4) NOT NULL DEFAULT 1.0000,
  `opening_stock_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(15,3) NOT NULL DEFAULT 0.000,
  `is_expiry_tracked` tinyint(1) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `track_inventory` tinyint(1) NOT NULL DEFAULT 1,
  `track_batch` tinyint(1) NOT NULL DEFAULT 0,
  `track_serial` tinyint(1) NOT NULL DEFAULT 0,
  `track_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `allow_negative_stock` tinyint(1) NOT NULL DEFAULT 0,
  `reorder_quantity` decimal(15,3) NOT NULL DEFAULT 50.000,
  `valuation_method` varchar(255) NOT NULL DEFAULT 'WEIGHTED_AVERAGE',
  `default_warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `selling_price` decimal(15,2) DEFAULT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `opening_stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `min_stock_level` decimal(15,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_company_id_sku_unique` (`company_id`,`sku`),
  KEY `products_company_id_index` (`company_id`),
  KEY `products_category_id_index` (`category_id`),
  KEY `products_subcategory_id_index` (`subcategory_id`),
  KEY `products_sku_index` (`sku`),
  KEY `products_hsn_sac_index` (`hsn_sac`),
  KEY `products_barcode_index` (`barcode`),
  KEY `idx_products_comp_cat` (`company_id`,`category_id`),
  KEY `idx_products_comp_active` (`company_id`,`is_active`),
  KEY `idx_products_comp_name` (`company_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_barcodes`;
CREATE TABLE `product_barcodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `barcode` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_barcodes_company_id_barcode_unique` (`company_id`,`barcode`),
  KEY `product_barcodes_company_id_index` (`company_id`),
  KEY `product_barcodes_product_id_index` (`product_id`),
  KEY `product_barcodes_barcode_index` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_prices`;
CREATE TABLE `product_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price_type` varchar(255) NOT NULL DEFAULT 'RETAIL',
  `price` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_prices_company_id_index` (`company_id`),
  KEY `product_prices_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_units`;
CREATE TABLE `product_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `unit_name` varchar(255) NOT NULL,
  `conversion_factor` decimal(15,4) NOT NULL DEFAULT 1.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_units_company_id_index` (`company_id`),
  KEY `product_units_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_number` varchar(255) NOT NULL,
  `vendor_invoice_number` varchar(255) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `round_off` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'PAID',
  `notes` text DEFAULT NULL,
  `internal_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `place_of_supply` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) NOT NULL DEFAULT 'UNPAID',
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_gstin` varchar(255) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `freight_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `freight_taxable` tinyint(1) NOT NULL DEFAULT 0,
  `additional_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `additional_charges_taxable` tinyint(1) NOT NULL DEFAULT 0,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchases_company_id_index` (`company_id`),
  KEY `purchases_branch_id_index` (`branch_id`),
  KEY `purchases_supplier_id_index` (`supplier_id`),
  KEY `purchases_purchase_number_index` (`purchase_number`),
  KEY `purchases_purchase_order_id_index` (`purchase_order_id`),
  KEY `purchases_warehouse_id_index` (`warehouse_id`),
  KEY `idx_purchases_comp_status` (`company_id`,`status`),
  KEY `idx_purchases_comp_supp` (`company_id`,`supplier_id`),
  KEY `idx_purchases_comp_date` (`company_id`,`purchase_date`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `purchase_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL,
  `taxable_value` decimal(15,2) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cess_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_items_company_id_index` (`company_id`),
  KEY `purchase_items_purchase_id_index` (`purchase_id`),
  KEY `purchase_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `po_date` date NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ISSUED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_orders_company_id_index` (`company_id`),
  KEY `purchase_orders_supplier_id_index` (`supplier_id`),
  KEY `purchase_orders_po_number_index` (`po_number`),
  KEY `purchase_orders_branch_id_index` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) NOT NULL DEFAULT 'Item',
  `hsn_sac` varchar(10) DEFAULT NULL,
  `received_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_company_id_index` (`company_id`),
  KEY `purchase_order_items_purchase_order_id_index` (`purchase_order_id`),
  KEY `purchase_order_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_returns`;
CREATE TABLE `purchase_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `return_number` varchar(255) NOT NULL,
  `return_date` date NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_reversal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'COMPLETED',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_returns_company_id_index` (`company_id`),
  KEY `purchase_returns_supplier_id_index` (`supplier_id`),
  KEY `purchase_returns_return_number_index` (`return_number`),
  KEY `purchase_returns_branch_id_index` (`branch_id`),
  KEY `purchase_returns_purchase_id_index` (`purchase_id`),
  KEY `purchase_returns_warehouse_id_index` (`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_return_items`;
CREATE TABLE `purchase_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `purchase_return_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_return_items_company_id_index` (`company_id`),
  KEY `purchase_return_items_purchase_return_id_index` (`purchase_return_id`),
  KEY `purchase_return_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `quotation_number` varchar(255) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL,
  `total_tax` decimal(15,2) NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `round_off` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `converted_to_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `converted_invoice_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotations_company_id_index` (`company_id`),
  KEY `quotations_customer_id_index` (`customer_id`),
  KEY `quotations_quotation_number_index` (`quotation_number`),
  KEY `quotations_branch_id_index` (`branch_id`),
  KEY `quotations_converted_invoice_id_index` (`converted_invoice_id`),
  KEY `idx_quotations_comp_status` (`company_id`,`status`),
  KEY `idx_quotations_comp_cust` (`company_id`,`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `quotation_items_company_id_index` (`company_id`),
  KEY `quotation_items_quotation_id_index` (`quotation_id`),
  KEY `quotation_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_expenses`;
CREATE TABLE `recurring_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `category` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `frequency` varchar(255) NOT NULL DEFAULT 'MONTHLY',
  `next_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_expenses_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_invoices`;
CREATE TABLE `recurring_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `frequency` varchar(255) NOT NULL DEFAULT 'MONTHLY',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_invoice_date` date NOT NULL,
  `last_generated_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `payment_terms` varchar(255) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_invoices_company_id_index` (`company_id`),
  KEY `recurring_invoices_branch_id_index` (`branch_id`),
  KEY `recurring_invoices_customer_id_index` (`customer_id`),
  KEY `idx_recur_comp_status` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_invoice_items`;
CREATE TABLE `recurring_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `recurring_invoice_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_invoice_items_company_id_index` (`company_id`),
  KEY `recurring_invoice_items_recurring_invoice_id_index` (`recurring_invoice_id`),
  KEY `recurring_invoice_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_run_logs`;
CREATE TABLE `recurring_run_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned NOT NULL,
  `run_id` varchar(64) NOT NULL,
  `run_date` date NOT NULL,
  `generated_entity_type` varchar(60) DEFAULT NULL,
  `generated_entity_id` bigint(20) unsigned DEFAULT NULL,
  `generation_mode` varchar(30) NOT NULL DEFAULT 'AUTO_DRAFT',
  `status` varchar(30) NOT NULL DEFAULT 'SUCCESS',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recurring_run_logs_run_id_unique` (`run_id`),
  KEY `recurring_run_logs_company_id_index` (`company_id`),
  KEY `recurring_run_logs_template_id_index` (`template_id`),
  CONSTRAINT `recurring_run_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_templates`;
CREATE TABLE `recurring_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `template_number` varchar(50) NOT NULL,
  `transaction_type` varchar(40) NOT NULL DEFAULT 'SALES_INVOICE',
  `party_type` varchar(30) DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `payload_json` longtext NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `frequency` varchar(30) NOT NULL DEFAULT 'MONTHLY',
  `month_end_policy` varchar(30) NOT NULL DEFAULT 'LAST_DAY',
  `custom_cron` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_run_date` date NOT NULL,
  `last_run_date` date DEFAULT NULL,
  `generation_mode` varchar(30) NOT NULL DEFAULT 'AUTO_DRAFT',
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_by` varchar(120) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_templates_company_id_index` (`company_id`),
  KEY `recurring_templates_branch_id_index` (`branch_id`),
  KEY `recurring_templates_template_number_index` (`template_number`),
  KEY `recurring_templates_next_run_date_index` (`next_run_date`),
  CONSTRAINT `recurring_templates_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_transactions`;
CREATE TABLE `recurring_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `frequency` varchar(30) NOT NULL DEFAULT 'MONTHLY',
  `interval_count` int(11) NOT NULL DEFAULT 1,
  `month_end_policy` varchar(30) NOT NULL DEFAULT 'LAST_VALID_DAY',
  `missed_schedule_policy` varchar(30) NOT NULL DEFAULT 'GENERATE_MISSED',
  `pricing_policy` varchar(30) NOT NULL DEFAULT 'PRESERVE_TEMPLATE_PRICE',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_run_at` date NOT NULL,
  `last_run_at` date DEFAULT NULL,
  `total_occurrences` int(11) DEFAULT NULL,
  `remaining_occurrences` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `template_payload_json` longtext NOT NULL,
  `auto_post` tinyint(1) NOT NULL DEFAULT 1,
  `auto_send` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_transactions_company_id_status_next_run_at_index` (`company_id`,`status`,`next_run_at`),
  KEY `recurring_transactions_company_id_index` (`company_id`),
  KEY `recurring_transactions_branch_id_index` (`branch_id`),
  KEY `recurring_transactions_type_index` (`type`),
  KEY `recurring_transactions_next_run_at_index` (`next_run_at`),
  KEY `recurring_transactions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_transaction_histories`;
CREATE TABLE `recurring_transaction_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `recurring_transaction_id` bigint(20) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `performed_by_name` varchar(100) NOT NULL DEFAULT 'System',
  `details_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_transaction_histories_company_id_index` (`company_id`),
  KEY `recurring_transaction_histories_recurring_transaction_id_index` (`recurring_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recurring_transaction_runs`;
CREATE TABLE `recurring_transaction_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `recurring_transaction_id` bigint(20) unsigned NOT NULL,
  `occurrence_date` date NOT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `executed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'SUCCESS',
  `generated_entity_type` varchar(60) DEFAULT NULL,
  `generated_entity_id` bigint(20) unsigned DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rec_occ_uniq` (`recurring_transaction_id`,`occurrence_date`),
  KEY `recurring_transaction_runs_company_id_index` (`company_id`),
  KEY `recurring_transaction_runs_recurring_transaction_id_index` (`recurring_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `reminder_type` varchar(60) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `reminder_date` date NOT NULL,
  `schedule_type` varchar(30) NOT NULL DEFAULT 'BEFORE_DUE',
  `offset_days` int(11) NOT NULL DEFAULT 0,
  `frequency` varchar(30) NOT NULL DEFAULT 'ONCE',
  `max_count` int(11) NOT NULL DEFAULT 3,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `stop_condition` varchar(50) NOT NULL DEFAULT 'ON_PAID',
  `status` varchar(30) NOT NULL DEFAULT 'SCHEDULED',
  `priority` varchar(20) NOT NULL DEFAULT 'NORMAL',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reminders_company_id_index` (`company_id`),
  KEY `reminders_branch_id_index` (`branch_id`),
  KEY `reminders_reminder_type_index` (`reminder_type`),
  KEY `reminders_reminder_date_index` (`reminder_date`),
  CONSTRAINT `reminders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `report_audit_logs`;
CREATE TABLE `report_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(100) NOT NULL DEFAULT 'System',
  `report_key` varchar(60) NOT NULL,
  `action` varchar(50) NOT NULL,
  `filters_json` longtext DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '127.0.0.1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_audit_logs_company_id_index` (`company_id`),
  KEY `report_audit_logs_user_id_index` (`user_id`),
  KEY `report_audit_logs_report_key_index` (`report_key`),
  KEY `report_audit_logs_action_index` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `report_saved_views`;
CREATE TABLE `report_saved_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `report_key` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `filters_json` longtext DEFAULT NULL,
  `columns_json` longtext DEFAULT NULL,
  `sort_json` longtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_saved_views_company_id_index` (`company_id`),
  KEY `report_saved_views_user_id_index` (`user_id`),
  KEY `report_saved_views_report_key_index` (`report_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `report_schedules`;
CREATE TABLE `report_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `report_key` varchar(80) NOT NULL,
  `title` varchar(150) NOT NULL,
  `frequency` varchar(30) NOT NULL DEFAULT 'WEEKLY',
  `delivery_channel` varchar(30) NOT NULL DEFAULT 'EMAIL',
  `recipient` varchar(255) NOT NULL,
  `export_format` varchar(20) NOT NULL DEFAULT 'PDF',
  `filters_json` text DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(120) NOT NULL DEFAULT 'System',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_schedules_company_id_index` (`company_id`),
  KEY `report_schedules_branch_id_index` (`branch_id`),
  KEY `report_schedules_report_key_index` (`report_key`),
  CONSTRAINT `report_schedules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `report_snapshots`;
CREATE TABLE `report_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `report_key` varchar(60) NOT NULL,
  `period_type` varchar(30) NOT NULL DEFAULT 'FINANCIAL_YEAR',
  `period_label` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `dataset_json` longtext NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_snapshots_company_id_index` (`company_id`),
  KEY `report_snapshots_report_key_index` (`report_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `restore_logs`;
CREATE TABLE `restore_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `backup_record_id` bigint(20) unsigned NOT NULL,
  `safety_backup_id` bigint(20) unsigned DEFAULT NULL,
  `restore_mode` varchar(30) NOT NULL DEFAULT 'BUSINESS',
  `status` varchar(30) NOT NULL DEFAULT 'STARTED',
  `initiated_by` varchar(100) NOT NULL DEFAULT 'Admin',
  `ip_address` varchar(60) DEFAULT NULL,
  `checksum_verified` tinyint(1) NOT NULL DEFAULT 0,
  `schema_compatible` tinyint(1) NOT NULL DEFAULT 0,
  `tables_restored_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_logs_company_id_index` (`company_id`),
  KEY `restore_logs_backup_record_id_index` (`backup_record_id`),
  KEY `restore_logs_safety_backup_id_index` (`safety_backup_id`),
  KEY `restore_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_company_id_slug_unique` (`company_id`,`slug`),
  KEY `roles_company_id_index` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_permissions_role_id_index` (`role_id`),
  KEY `role_permissions_permission_id_index` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sales_orders`;
CREATE TABLE `sales_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `order_date` date NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'CONFIRMED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `fulfillment_status` varchar(255) NOT NULL DEFAULT 'CONFIRMED',
  `payment_status` varchar(255) NOT NULL DEFAULT 'UNPAID',
  `source_quotation_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_orders_company_id_index` (`company_id`),
  KEY `sales_orders_customer_id_index` (`customer_id`),
  KEY `sales_orders_order_number_index` (`order_number`),
  KEY `sales_orders_branch_id_index` (`branch_id`),
  KEY `sales_orders_source_quotation_id_index` (`source_quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sales_order_items`;
CREATE TABLE `sales_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `sales_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 9.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sales_order_items_company_id_index` (`company_id`),
  KEY `sales_order_items_sales_order_id_index` (`sales_order_id`),
  KEY `sales_order_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sales_returns`;
CREATE TABLE `sales_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `return_number` varchar(255) NOT NULL,
  `return_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `stock_impact` tinyint(1) NOT NULL DEFAULT 1,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_reversal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'COMPLETED',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_returns_company_id_index` (`company_id`),
  KEY `sales_returns_branch_id_index` (`branch_id`),
  KEY `sales_returns_customer_id_index` (`customer_id`),
  KEY `sales_returns_invoice_id_index` (`invoice_id`),
  KEY `sales_returns_return_number_index` (`return_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sales_return_items`;
CREATE TABLE `sales_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `sales_return_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `hsn_sac` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(15,2) NOT NULL,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_return_items_company_id_index` (`company_id`),
  KEY `sales_return_items_sales_return_id_index` (`sales_return_id`),
  KEY `sales_return_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheduler_jobs`;
CREATE TABLE `scheduler_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `job_key` varchar(100) NOT NULL,
  `job_type` varchar(60) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `locked_until` timestamp NULL DEFAULT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scheduler_jobs_company_id_index` (`company_id`),
  KEY `scheduler_jobs_job_key_index` (`job_key`),
  KEY `scheduler_jobs_job_type_index` (`job_type`),
  KEY `scheduler_jobs_status_index` (`status`),
  KEY `scheduler_jobs_locked_until_index` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'APPLIED',
  `execution_time_ms` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_migrations_version_unique` (`version`),
  KEY `schema_migrations_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `serial_numbers`;
CREATE TABLE `serial_numbers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'IN_STOCK',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_numbers_company_id_product_id_serial_number_unique` (`company_id`,`product_id`,`serial_number`),
  KEY `serial_numbers_company_id_index` (`company_id`),
  KEY `serial_numbers_warehouse_id_index` (`warehouse_id`),
  KEY `serial_numbers_product_id_index` (`product_id`),
  KEY `serial_numbers_serial_number_index` (`serial_number`),
  KEY `serial_numbers_branch_id_index` (`branch_id`),
  KEY `serial_numbers_purchase_id_index` (`purchase_id`),
  KEY `serial_numbers_invoice_id_index` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `adjustment_number` varchar(255) NOT NULL,
  `adjustment_date` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL DEFAULT 'Admin',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustments_company_id_index` (`company_id`),
  KEY `stock_adjustments_warehouse_id_index` (`warehouse_id`),
  KEY `stock_adjustments_adjustment_number_index` (`adjustment_number`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_adjustment_items`;
CREATE TABLE `stock_adjustment_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `adjustment_id` bigint(20) unsigned NOT NULL,
  `stock_adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'INCREASE',
  `adjustment_type` varchar(255) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustment_items_company_id_index` (`company_id`),
  KEY `stock_adjustment_items_adjustment_id_index` (`adjustment_id`),
  KEY `stock_adjustment_items_stock_adjustment_id_index` (`stock_adjustment_id`),
  KEY `stock_adjustment_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_balances`;
CREATE TABLE `stock_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `reserved_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `available_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `last_movement_id` bigint(20) unsigned DEFAULT NULL,
  `in_transit_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_balances_company_id_warehouse_id_product_id_unique` (`company_id`,`warehouse_id`,`product_id`),
  KEY `stock_balances_company_id_index` (`company_id`),
  KEY `stock_balances_warehouse_id_index` (`warehouse_id`),
  KEY `stock_balances_product_id_index` (`product_id`),
  KEY `stock_balances_branch_id_index` (`branch_id`),
  KEY `idx_sb_comp_prod_wh` (`company_id`,`product_id`,`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_counts`;
CREATE TABLE `stock_counts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `count_number` varchar(255) NOT NULL,
  `count_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'DRAFT',
  `counted_by` varchar(255) NOT NULL DEFAULT 'Admin',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_counts_company_id_index` (`company_id`),
  KEY `stock_counts_branch_id_index` (`branch_id`),
  KEY `stock_counts_warehouse_id_index` (`warehouse_id`),
  KEY `stock_counts_count_number_index` (`count_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_count_items`;
CREATE TABLE `stock_count_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `stock_count_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `system_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `physical_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `difference` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `adjustment_created` tinyint(1) NOT NULL DEFAULT 0,
  `adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_count_items_company_id_index` (`company_id`),
  KEY `stock_count_items_stock_count_id_index` (`stock_count_id`),
  KEY `stock_count_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `balance_after` decimal(15,3) NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `serial_number_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` varchar(255) NOT NULL DEFAULT 'OPENING_STOCK',
  `direction` varchar(255) NOT NULL DEFAULT 'IN',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(255) DEFAULT NULL,
  `movement_date` date NOT NULL DEFAULT '2026-10-03',
  `created_by` varchar(255) NOT NULL DEFAULT 'System',
  PRIMARY KEY (`id`),
  KEY `stock_movements_company_id_index` (`company_id`),
  KEY `stock_movements_warehouse_id_index` (`warehouse_id`),
  KEY `stock_movements_product_id_index` (`product_id`),
  KEY `stock_movements_branch_id_index` (`branch_id`),
  KEY `stock_movements_batch_id_index` (`batch_id`),
  KEY `stock_movements_serial_number_id_index` (`serial_number_id`),
  KEY `stock_movements_movement_type_index` (`movement_type`),
  KEY `stock_movements_direction_index` (`direction`),
  KEY `stock_movements_reference_number_index` (`reference_number`),
  KEY `stock_movements_movement_date_index` (`movement_date`),
  KEY `idx_sm_comp_prod` (`company_id`,`product_id`),
  KEY `idx_sm_comp_date` (`company_id`,`movement_date`)
) ENGINE=InnoDB AUTO_INCREMENT=477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `transfer_number` varchar(255) NOT NULL,
  `from_warehouse_id` bigint(20) unsigned NOT NULL,
  `to_warehouse_id` bigint(20) unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'IN_TRANSIT',
  `reference_no` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `from_branch_id` bigint(20) unsigned DEFAULT NULL,
  `to_branch_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `dispatched_by` varchar(100) DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `cancelled_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfers_company_id_index` (`company_id`),
  KEY `stock_transfers_transfer_number_index` (`transfer_number`),
  KEY `stock_transfers_from_warehouse_id_index` (`from_warehouse_id`),
  KEY `stock_transfers_to_warehouse_id_index` (`to_warehouse_id`),
  KEY `stock_transfers_from_branch_id_index` (`from_branch_id`),
  KEY `stock_transfers_to_branch_id_index` (`to_branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `transfer_id` bigint(20) unsigned NOT NULL,
  `stock_transfer_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity_sent` decimal(15,3) NOT NULL DEFAULT 0.000,
  `quantity_received` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfer_items_company_id_index` (`company_id`),
  KEY `stock_transfer_items_transfer_id_index` (`transfer_id`),
  KEY `stock_transfer_items_stock_transfer_id_index` (`stock_transfer_id`),
  KEY `stock_transfer_items_product_id_index` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subcategories_company_id_index` (`company_id`),
  KEY `subcategories_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address_line1` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `pincode` varchar(10) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms_days` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `alt_phone` varchar(255) DEFAULT NULL,
  `tax_type` varchar(255) NOT NULL DEFAULT 'Unregistered',
  `place_of_supply` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `credit_period` int(11) NOT NULL DEFAULT 30,
  `default_payment_mode` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_company_id_index` (`company_id`),
  KEY `suppliers_supplier_group_id_index` (`supplier_group_id`),
  KEY `suppliers_gstin_index` (`gstin`),
  KEY `idx_supp_comp_active` (`company_id`,`is_active`),
  KEY `idx_supp_comp_name` (`company_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_addresses`;
CREATE TABLE `supplier_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'OFFICE',
  `address_line1` text NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `state_code` varchar(2) NOT NULL DEFAULT '27',
  `pincode` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address_line2` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_addresses_company_id_index` (`company_id`),
  KEY `supplier_addresses_supplier_id_index` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_bank_accounts`;
CREATE TABLE `supplier_bank_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(255) NOT NULL,
  `ifsc_code` varchar(11) NOT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_bank_accounts_company_id_index` (`company_id`),
  KEY `supplier_bank_accounts_supplier_id_index` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_bill_attachments`;
CREATE TABLE `supplier_bill_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_bill_attachments_company_id_index` (`company_id`),
  KEY `supplier_bill_attachments_purchase_id_index` (`purchase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_contacts`;
CREATE TABLE `supplier_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_contacts_company_id_index` (`company_id`),
  KEY `supplier_contacts_supplier_id_index` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_groups`;
CREATE TABLE `supplier_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  KEY `supplier_groups_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `taggables`;
CREATE TABLE `taggables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` bigint(20) unsigned NOT NULL,
  `taggable_type` varchar(255) NOT NULL,
  `taggable_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `taggables_tag_id_index` (`tag_id`),
  KEY `taggables_taggable_id_index` (`taggable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_company_id_name_unique` (`company_id`,`name`),
  KEY `tags_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tax_rates`;
CREATE TABLE `tax_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tax_type` varchar(255) NOT NULL DEFAULT 'GST',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  KEY `tax_rates_company_id_index` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'QUANTITY',
  `decimal_precision` int(11) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  KEY `units_company_id_index` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `unit_conversions`;
CREATE TABLE `unit_conversions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `from_unit_id` bigint(20) unsigned NOT NULL,
  `to_unit_id` bigint(20) unsigned NOT NULL,
  `conversion_factor` decimal(15,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_conversions_company_id_index` (`company_id`),
  KEY `unit_conversions_from_unit_id_index` (`from_unit_id`),
  KEY `unit_conversions_to_unit_id_index` (`to_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'Admin',
  `current_company_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_current_company_id_index` (`current_company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_branches`;
CREATE TABLE `user_branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_branches_company_id_index` (`company_id`),
  KEY `user_branches_user_id_index` (`user_id`),
  KEY `user_branches_branch_id_index` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_roles_company_id_index` (`company_id`),
  KEY `user_roles_user_id_index` (`user_id`),
  KEY `user_roles_role_id_index` (`role_id`),
  KEY `idx_ur_comp_user` (`company_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `session_token` varchar(100) NOT NULL,
  `ip_address` varchar(60) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_info` varchar(100) DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_sessions_session_token_unique` (`session_token`),
  KEY `user_sessions_user_id_index` (`user_id`),
  KEY `user_sessions_company_id_index` (`company_id`),
  KEY `user_sessions_last_activity_at_index` (`last_activity_at`),
  KEY `user_sessions_is_revoked_index` (`is_revoked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `manager_name` varchar(100) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `warehouses_company_id_index` (`company_id`),
  KEY `warehouses_branch_id_index` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
