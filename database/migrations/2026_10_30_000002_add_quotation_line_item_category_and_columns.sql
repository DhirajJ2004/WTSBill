-- =====================================================================
-- WTSBill ERP — Quotation Line Item Category & Quotation Columns Migration
-- Adds category_id column and index to quotation_items table
-- Adds deleted_at timestamp to quotations table for soft deletion
-- =====================================================================

ALTER TABLE quotation_items
    ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED NULL AFTER product_id;

SET @dbname = DATABASE();
SET @tablename = "quotation_items";
SET @indexname = "idx_quotation_items_category";
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND INDEX_NAME = @indexname
    ) > 0,
    "SELECT 1",
    CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (category_id)")
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;

ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

