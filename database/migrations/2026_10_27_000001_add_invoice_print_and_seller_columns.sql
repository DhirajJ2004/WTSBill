-- =====================================================================
-- WTSBill ERP — Tax Invoice Print Rebuild & Extended Metadata Migration
-- Adds required GST invoice metadata, buyer and seller fields
-- =====================================================================

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS challan_no VARCHAR(100) NULL AFTER reference_po_number,
    ADD COLUMN IF NOT EXISTS po_no VARCHAR(100) NULL AFTER challan_no,
    ADD COLUMN IF NOT EXISTS po_date DATE NULL AFTER po_no,
    ADD COLUMN IF NOT EXISTS bill_credit_days INT NULL DEFAULT 0 AFTER due_date,
    ADD COLUMN IF NOT EXISTS delivery_by VARCHAR(255) NULL AFTER bill_credit_days,
    ADD COLUMN IF NOT EXISTS receipt_mode VARCHAR(100) NULL DEFAULT 'Credit' AFTER delivery_by,
    ADD COLUMN IF NOT EXISTS lr_no VARCHAR(100) NULL AFTER receipt_mode,
    ADD COLUMN IF NOT EXISTS place_of_supply_state_code VARCHAR(10) NULL AFTER place_of_supply;

ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS msme_no VARCHAR(100) NULL AFTER pan,
    ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS upi_id VARCHAR(255) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS stamp_signature_url VARCHAR(255) NULL AFTER logo_url,
    ADD COLUMN IF NOT EXISTS terms_and_conditions TEXT NULL AFTER business_type;

ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS msme_no VARCHAR(100) NULL AFTER pan,
    ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS upi_id VARCHAR(255) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS stamp_signature_url VARCHAR(255) NULL AFTER address,
    ADD COLUMN IF NOT EXISTS terms_and_conditions TEXT NULL AFTER is_active;

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS contact_person VARCHAR(255) NULL AFTER name;
