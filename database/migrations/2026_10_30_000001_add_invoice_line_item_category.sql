-- =====================================================================
-- WTSBill ERP — Tax Invoice Line Item Category Schema Migration
-- Adds category_id column and index to invoice_items table
-- =====================================================================

ALTER TABLE invoice_items
    ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED NULL AFTER product_id;

SET @dbname = DATABASE();
SET @tablename = "invoice_items";
SET @indexname = "idx_invoice_items_category";
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

