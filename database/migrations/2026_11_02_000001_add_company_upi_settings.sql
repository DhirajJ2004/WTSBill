-- Migration: Add UPI Display Name and Payment Instructions to Companies Table
ALTER TABLE `companies` 
ADD COLUMN IF NOT EXISTS `upi_display_name` VARCHAR(255) NULL AFTER `upi_id`,
ADD COLUMN IF NOT EXISTS `payment_instructions` TEXT NULL AFTER `upi_display_name`;

