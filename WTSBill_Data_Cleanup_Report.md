# WTSBill ERP — Pre-Production Data Cleanup Report

**Date:** November 26, 2026  
**Database:** `wtsbill_db` (MySQL/MariaDB 10.4 on `localhost:3306`)  
**Pre-Cleanup Backup:** `WTSBill_PreCleanup_Database_Backup.sql` (21,554,022 bytes)  
**Pre-Cleanup Files Backup:** `WTSBill_PreCleanup_Files_Backup.zip` (192,007 bytes)

---

## 1. Executive Summary

Prior to first live production deployment, all development, QA testing, and dummy business records were safely cleared from `wtsbill_db` and obsolete temporary files were removed from storage. System schema, migrations, RBAC permissions, tax rates, units, chart of accounts, and core application logic were 100% preserved.

---

## 2. Backup Verification Results

| Deliverable | File Path | Size | Status |
|---|---|---|---|
| **Full Database Dump** | `WTSBill_PreCleanup_Database_Backup.sql` | 21,554,022 bytes (~21.5 MB) | **Verified (routines + triggers + single-transaction)** |
| **Files & Config Archive** | `WTSBill_PreCleanup_Files_Backup.zip` | 192,007 bytes (~192 KB) | **Verified (`public/uploads`, `storage/logs`, `storage/cache`, `.env`)** |

---

## 3. Database Tables Cleaned (78 Tables)

All transactional and dummy entity tables were safely truncated inside a foreign-key-safe transaction (`SET FOREIGN_KEY_CHECKS = 0` → `TRUNCATE` → `SET FOREIGN_KEY_CHECKS = 1`):

- **Sales & Orders:** `invoices` (636 rows), `invoice_items` (612 rows), `quotations` (4 rows), `quotation_items` (8 rows), `delivery_challans` (92 rows), `delivery_challan_items` (91 rows), `credit_notes` (1 row), `credit_note_items`, `sales_orders` (90 rows), `sales_order_items` (90 rows), `sales_returns`, `reusable_invoice_numbers` (103 rows).
- **Purchases & Payables:** `purchases` (265 rows), `purchase_items` (1 row), `purchase_orders` (1 row), `purchase_order_items` (1 row), `debit_notes` (2 rows), `debit_note_items`, `purchase_returns`, `goods_receipts`.
- **Payments & Expenses:** `payments` (1,352 rows), `payment_allocations` (1,275 rows), `customer_advances` (81 rows), `customer_credits`, `expenses` (3 rows), `bank_transactions`, `bank_transfers`.
- **Accounting Ledger:** `journal_entries` (2,090 rows), `journal_entry_lines` (4,170 rows), `journal_lines`, `ledger_entries`.
- **Inventory & Catalog:** `stock_adjustments` (522 rows), `stock_adjustment_items` (496 rows), `stock_movements` (269 rows), `stock_balances` (7 rows), `stock_transfers`, `products` (50 rows), `categories` (6 rows), `warehouses` (3 rows).
- **Parties & CRM:** `customers` (55 rows), `suppliers` (21 rows), `parties` (50 rows).
- **Audit, Notifications & Temporary Logs:** `audit_logs` (10,905 rows), `audit_cleanup_logs` (235 rows), `backup_records` (736 rows), `restore_logs` (118 rows), `communication_messages` (584 rows), `in_app_notifications` (676 rows), `password_resets` (1 row), `user_drafts` (1 row).
- **Users & Companies (Re-seeded cleanly):** `users` (5 dev rows), `user_roles` (194 rows), `user_branches` (197 rows), `bank_accounts` (2 rows), `branches` (2 rows), `companies` (1 row).

---

## 4. Tables Retained Intact (System Master Configuration)

| Table | Retained Rows | Purpose |
|---|---|---|
| `permissions` | 72 | RBAC permission registry |
| `roles` | 6 | System role definitions (`admin`, `accountant`, `auditor`, `inventory_manager`, `sales_executive`, `viewer`) |
| `role_permissions` | 115 | Role-to-permission mappings |
| `chart_of_accounts` | 11 | Double-entry accounting chart |
| `tax_rates` | 5 | GST tax slabs (0%, 5%, 12%, 18%, 28%) |
| `units` | 4 | Measurement units (`PCS`, `BOX`, `KG`, etc.) |
| `company_brandings` | 1 | Branding configuration |
| `business_communication_settings` | 1 | Communication configuration |

---

## 5. File & Storage Cleanup

| Directory | Action Taken | Count Removed | Retained |
|---|---|---|---|
| `storage/backups/` | Removed old dev backup `.zip` files | **585 `.zip` files** | `.backup.lock` |
| `public/uploads/branding/` | Removed 70-byte dummy test PNG/SVG files | **80 dummy files** | Real logo (`branding_logo_c1_main_c8f8f5a1b6b8_png.png`) & UPI QR (`company_1_upi_qr.png`) |
| `storage/sessions/` | Cleared stale development session files | **310 session files** | Directory preserved |
