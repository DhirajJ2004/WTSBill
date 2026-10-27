-- =====================================================================
-- WTSBill ERP — Quotation Print Rebuild & Extended Metadata Migration
-- Adds required GST quotation metadata, commercial fields, and terms
-- =====================================================================

ALTER TABLE quotations
    ADD COLUMN kind_attn VARCHAR(255) NULL AFTER reference_no,
    ADD COLUMN payment_days INT NOT NULL DEFAULT 0 AFTER kind_attn,
    ADD COLUMN place_of_supply_state_code VARCHAR(10) NULL AFTER payment_days,
    ADD COLUMN terms_json TEXT NULL AFTER terms;
