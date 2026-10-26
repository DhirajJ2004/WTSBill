# WTSBill ERP — Database Schema Documentation

**Target Database:** `wtsbill_db`  
**Engine:** `InnoDB`  
**Collation:** `utf8mb4_unicode_ci`  
**Character Set:** `utf8mb4`  
**Total Tables:** 161 (28 tables seeded with active Indian enterprise baseline data)  

---

## 1. Schema Safety & Operational Policies

1. **Zero Destructive Modifications:** Never run `DROP TABLE`, `TRUNCATE TABLE`, or destructive `ALTER TABLE` operations on the database.
2. **Never Auto-Import on Live Environments:** Database schemas must never be wiped or auto-imported automatically during runtime or deployment.
3. **Decimal Precision Preservation:**
   - Currency & Financial Amounts: `decimal(15,2)` (e.g. Subtotals, GST amounts, Grand totals, Ledger balances).
   - Inventory Quantities: `decimal(15,3)` (e.g. stock quantities, piece counts, weight in KG).
   - Tax Rates & Percentages: `decimal(5,2)` (e.g. GST rates: 0.00%, 5.00%, 12.00%, 18.00%, 28.00%).
4. **Multi-Tenant Column Invariant:** Every domain table includes `company_id` (bigint unsigned) and optional `branch_id` (bigint unsigned) to enforce strict isolation.

---

## 2. Populated Master & Transaction Tables Summary

| Table | Populated Records | Key Fields | Purpose |
| :--- | :--- | :--- | :--- |
| `companies` | 1 | `name`, `gstin`, `state_code` | Root company profile (`Wis Technosavvy Pvt Ltd`) |
| `branches` | 2 | `branch_code`, `gstin`, `state_code` | Operating locations (`Mumbai`, `Pune`) |
| `users` | 5 | `name`, `email`, `role`, `status` | User accounts with Bcrypt passwords |
| `roles` | 5 | `name`, `slug` | RBAC roles (`ADMIN`, `ACCOUNTANT`, `AUDITOR`, etc.) |
| `customers` | 21 | `name`, `gstin`, `current_balance` | Customer master with credit limits |
| `suppliers` | 10 | `name`, `gstin`, `current_balance` | Vendor master |
| `products` | 50 | `name`, `sku`, `hsn_sac`, `price` | Product catalog with HSN codes |
| `stock_balances`| 50 | `warehouse_id`, `product_id`, `quantity` | Physical inventory stock levels |
| `invoices` | 2 | `invoice_number`, `grand_total` | Tax invoices with GST calculations |
| `purchases` | 2 | `purchase_number`, `grand_total` | Vendor bills with ITC input |
| `chart_of_accounts` | 11 | `account_code`, `account_type` | Standard double-entry ledger master |
| `journal_entries` | 1 | `entry_number`, `total_debit` | Balanced manual journal vouchers |

*(For exhaustive schema definitions of all 161 tables, refer to `docs/REBUILD_DATABASE_INVENTORY.md`).*
