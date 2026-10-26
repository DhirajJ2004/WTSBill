# WTSBill ERP — Rebuild Baseline & System Discovery Report

**Document Version:** 1.0.0  
**Inspection Date:** 2026-10-10  
**Target Environment:** Native PHP 8.2+ on XAMPP (Windows), MySQL 8.0 / MariaDB 10.4.32+  
**Execution Context:** `c:\xampp\htdocs\WTSBill`  

---

## 1. Executive Summary & Project Identification

The WTSBill ERP project is a high-performance Enterprise Billing, GST Accounting, and Multi-Tenant Inventory management system. The architecture is built with **Native PHP 8+** and **MySQL/MariaDB via Native PDO prepared statements**, eliminating any heavyweight framework runtimes (zero Laravel/Illuminate dependencies).

### 1.1 Environment & System Parameters
- **Project Root Directory:** `c:\xampp\htdocs\WTSBill`
- **PHP Executable:** `C:\xampp\php\php.exe` (PHP 8.2+)
- **Database Engine:** MySQL 8.0+ / MariaDB 10.4.32+
- **Database Name:** `wtsbill_db`
- **Host / Port:** `localhost:3306` (User: `root`, Password: none)
- **Character Set / Collation:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Default Timezone:** `Asia/Kolkata` (`+05:30`)
- **Autoloading Scheme:** Composer PSR-4 mapped to `App\` namespace (`app/` directory), helper file `app/Core/helpers.php`

---

## 2. Baseline Verification Test Execution Report

A non-destructive automated test suite was executed against the existing codebase and live database schema using `scripts/run_chunk1_test.php`.

### 2.1 Test Execution Output Summary
```
==================================================================================
           STARTING WTSBILL CHUNK 1 BASELINE ARCHITECTURE TEST SUITE              
==================================================================================

--- SECTION 1: ENVIRONMENT & CONFIGURATION ---
[PASS] ENV-01 | Env file loaded successfully
[PASS] ENV-02 | Read APP_NAME via env() helper
[PASS] ENV-03 | Read DB_DATABASE via env() helper
[PASS] ENV-04 | Default value fallback when key missing
[PASS] CONF-01 | Config repository bootstrapped
[PASS] CONF-02 | Read app.timezone from config
[PASS] CONF-03 | Read database default connection
[PASS] CONF-04 | Read nested database credentials

--- SECTION 2: NATIVE PDO DATABASE & SCHEMA INTEGRITY ---
[PASS] DB-01 | Native PDO Connection established
[PASS] DB-02 | PDO error mode is ERRMODE_EXCEPTION
[PASS] DB-03 | Connection character set is utf8mb4
[PASS] DB-04 | All 161 core database tables verified in wtsbill_db
[PASS] DB-05 | Prepared statement selectOne executes safely

--- SECTION 3: ACID TRANSACTION & SAVEPOINT INTEGRITY ---
[PASS] TX-01 | ACID transaction rollback restores database state
[PASS] TX-02 | ACID transaction callback commits successfully
[PASS] TX-03 | Cleanup of test record verified

--- SECTION 4: HTTP REQUEST & RESPONSE ENGINE ---
[PASS] REQ-01 | Request method identified correctly
[PASS] REQ-02 | Request path extracted cleanly
[PASS] REQ-03 | Request query parameter accessible
[PASS] REQ-04 | Request JSON payload parsed into input()
[PASS] REQ-05 | Bearer token extracted from headers
[PASS] REQ-06 | expectsJson() detects API request
[PASS] RES-01 | Response status code is 201
[PASS] RES-02 | Response success payload structure valid
[PASS] RES-03 | Database error redaction prevents SQL leak

--- SECTION 5: ROUTER & DISPATCH CYCLE ---
[PASS] ROUT-01 | Exact route match dispatches correctly
[PASS] ROUT-02 | Parameterized route extracts ID correctly
[PASS] ROUT-03 | Unmatched route returns 404 status

==================================================================================
                       CHUNK 1 VERIFICATION REPORT SUMMARY                        
==================================================================================
Total Assertions: 28
Passed:           28
Failed:           0
Pass Percentage:  100%

STATUS: ALL CHUNK 1 ARCHITECTURAL CRITERIA VERIFIED & PASSED (100%)
```

---

## 3. Discovered Data Baseline (Enterprise Seed Context)

The database `wtsbill_db` contains pre-seeded operational enterprise data representing an active Indian manufacturing and trading business:

### 3.1 Company Profile
- **Company Name:** `Wis Technosavvy Pvt Ltd`
- **Legal Name:** `Wis Technosavvy Pvt Ltd`
- **GSTIN:** `27AADCW7577N1ZE`
- **PAN:** `AADCW7577N`
- **State / State Code:** Maharashtra / `27`
- **Registered Address:** Office No-B-7, 2nd floor, Shreya Business Hub, Pari chowk, Opp CNG Pump, Narhe, Pune, Maharashtra 411041
- **Email / Phone:** `info@wtsindia.co.in` / `020 47252364`
- **Currency:** `INR` (Symbol: `₹`)
- **Financial Year Start:** `04-01` (April 1st)

### 3.2 Operating Branches
1. **Branch 1 (Main Branch):**
   - Name: `Main Branch - Mumbai`
   - Branch Code: `BOM-01`
   - Address: Bandra Kurla Complex, Mumbai, Maharashtra (State Code 27)
   - GSTIN: `27AAAAA0000A1Z5`
   - Is Main Branch: `1`
2. **Branch 2 (Regional Branch):**
   - Name: `Pune Regional Branch`
   - Branch Code: `PUN-02`
   - State: Maharashtra (State Code 27)
   - Is Main Branch: `0`

### 3.3 Active User Accounts
| User ID | Full Name | Email Address | Assigned Role | Company ID | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | Anil Desai | `anil.d@wtsbill.in` | `ADMIN` | 1 | `ACTIVE` |
| **2** | Priya Sharma | `priya.s@wtsbill.in` | `ACCOUNTANT` | 1 | `ACTIVE` |
| **3** | Rohan Mehta | `rohan.m@external.com` | `AUDITOR` | 1 | `ACTIVE` |
| **4** | Suresh Patel | `suresh.p@wtsbill.in` | `INVENTORY_MANAGER` | 1 | `ACTIVE` |
| **5** | Neha Gupta | `neha.g@wtsbill.in` | `SALES_EXECUTIVE` | 1 | `ACTIVE` |

### 3.4 Established Roles
- `ADMIN` (Slug: `admin`): Full administrative permissions across all modules, company settings, and branches.
- `ACCOUNTANT` (Slug: `accountant`): Full financial management, invoices, journal entries, ledger audits, tax returns.
- `AUDITOR` (Slug: `auditor`): Read-only inspection of ledgers, trial balance, P&L, balance sheet, and audit logs.
- `INVENTORY_MANAGER` (Slug: `inventory_manager`): Product catalog, warehouse stock balances, batch transfers, and goods receipt.
- `SALES_EXECUTIVE` (Slug: `sales_executive`): Quotations, sales orders, delivery challans, and customer management.

### 3.5 Core Enterprise Master Records
- **Warehouses:** 1 active warehouse (`Warehouse A - Andheri MIDC`, Code: `WH-A`, Mumbai).
- **Units of Measure:** 4 active standard units:
  - Pieces (`Pcs`, Quantity, precision: 0)
  - Kilograms (`KG`, Weight, precision: 3)
  - Boxes (`Box`, Quantity, precision: 0)
  - Meters (`Mtrs`, Length, precision: 2)
- **Tax Rates (GST Master):**
  - GST 0% (0.00%)
  - GST 5% (5.00% — CGST 2.5% + SGST 2.5%)
  - GST 12% (12.00% — CGST 6.0% + SGST 6.0%)
  - GST 18% (18.00% — CGST 9.0% + SGST 9.0%)
  - GST 28% (28.00% — CGST 14.0% + SGST 14.0%)
- **Product Catalog:** 50 distinct SKUs with opening inventory, selling prices, cost prices, and HSN codes.
- **Parties:** 21 Customers (with credit limits, state codes, and GSTINs) and 10 Suppliers.
- **Chart of Accounts:** 11 pre-configured double-entry accounting accounts including Cash in Hand, Bank, Sales Account, Purchase Account, Accounts Receivable, Accounts Payable, and Tax ledgers.

---

## 4. Detailed Baseline Workflows Analysis

This section documents the current behavior, verified state, and implementation roadmap for every critical business workflow based on the existing codebase and database schema.

### 4.1 Login, Logout, Session Expiry & Access Control
- **Current State:**
  - Database stores 5 users with Bcrypt hashed passwords (`password` column: `$2y$10$...`).
  - Native Session management and authentication controllers are scheduled for Chunk 3.
  - Native PDO lookup verified with safe prepared queries (`users` table by `email`).
  - Tables available: `users`, `roles`, `user_roles`, `user_sessions`, `personal_access_tokens`, `password_resets`.
- **Target Invariant:**
  - Password verification via `password_verify()`.
  - Secure session creation (`session_regenerate_id(true)`).
  - Multi-tenant tenant context isolation (`company_id` and `branch_id` stored in session).
  - RBAC verification via role slug check on every protected endpoint.

### 4.2 Dashboard & Business Summaries
- **Current State:**
  - Public gateway (`public/index.php`) serves the initial system status and links to `/api/health`.
  - Real database metrics are available in `wtsbill_db`:
    - 2 Invoices (Total Sales: ₹1,34,520.00)
    - 2 Purchases (Total Spend: ₹3,55,770.00)
    - 50 Products (Active stock count: 50 items)
    - 21 Customers, 10 Suppliers
    - Cash/Bank balance: ₹12,40,500.00 (HDFC Bank)
- **Target Invariant:**
  - Dynamic KPI cards computing real-time counts, month-to-date sales, accounts receivable, and accounts payable.

### 4.3 Party Creation & Updates (Customers & Suppliers)
- **Current State:**
  - Customers table: 21 rows. Fields include `name`, `company_name`, `gstin`, `pan`, `email`, `phone`, `city`, `state`, `state_code`, `credit_limit`, `current_balance`, `opening_balance`.
  - Suppliers table: 10 rows. Fields include `name`, `gstin`, `pan`, `email`, `phone`, `current_balance`.
  - Party tags and credit limits are represented in the schema.
- **Target Invariant:**
  - Mandatory GSTIN format validation (`^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$`).
  - Automatic extraction of PAN from characters 3–12 of GSTIN.
  - Automatic state determination from the first 2 digits of GSTIN.
  - Strict tenant isolation (`company_id` scoping).

### 4.4 Product Creation, Inventory Changes & Stock Validation
- **Current State:**
  - 50 products populated in `products`.
  - 50 matching records in `stock_balances`.
  - 1 initial stock movement in `stock_movements` (Type: `PURCHASE`, Quantity: 25.000, Reference ID: 2).
  - Company setting `allow_negative_stock` = `0` (negative stock prohibited).
- **Target Invariant:**
  - Inventory movements must create an immutable audit record in `stock_movements`.
  - Stock validation prevents invoice submission if `available_quantity < requested_quantity` and `allow_negative_stock == 0`.
  - Stock balance formula: $\text{Current Balance} = \text{Opening} + \sum \text{Stock IN} - \sum \text{Stock OUT}$.

### 4.5 Invoice Creation, Editing, Printing & Numbering
- **Current State:**
  - Verified sample record `invoices` (ID: 1):
    - Invoice Number: `INV-2023-0451`
    - Sub Total: ₹60,000.00
    - Discount Rate: 5.00% (Discount Amount: ₹3,000.00)
    - Taxable Value: ₹57,000.00
    - Place of Supply: `27` (Intra-state Maharashtra)
    - CGST: ₹5,130.00 (9%) | SGST: ₹5,130.00 (9%) | IGST: ₹0.00
    - Grand Total: ₹67,260.00
  - Table `document_numbering_configs` defines sequential format: Prefix `INV-`, 4-digit padding, yearly reset.
- **Target Invariant:**
  - Tax calculation rule: If `company.state_code === customer.state_code`, apply CGST + SGST (50% of GST rate each). If different, apply IGST (100% of GST rate).
  - Round off formula: `round_off = round(grand_total) - grand_total` (precision: 2 decimals, limit $\pm 0.50$).
  - Sequential document number generated atomically inside transaction.

### 4.6 Quotation, Sales Order & Delivery Challan Workflows
- **Current State:**
  - 1 Quotation verified in `quotations` (`QT-QA-1791632269`, Grand Total: ₹1,180.00, Status: `SENT`).
  - Schema contains full support for `sales_orders`, `sales_order_items`, `delivery_challans`, and `delivery_challan_items`.
  - `quotations` table contains conversion flags: `converted_to_invoice`, `converted_invoice_id`.
- **Target Invariant:**
  - Quotation -> Sales Order -> Delivery Challan -> Tax Invoice conversion pipeline.
  - Delivery Challan triggers stock deduction (`stock_movements` type: `DELIVERY_CHALLAN_DISPATCH`).

### 4.7 Purchase Bill Creation & Stock Updates
- **Current State:**
  - 2 Purchase records in `purchases`.
  - Sample Purchase 1 (`ACME/23-24/492`): Subtotal ₹1,50,750.00, CGST ₹13,567.50, SGST ₹13,567.50, Total ₹1,77,885.00, Status: `PAID`.
  - Purchase receipt linked to `stock_movements` entry ID 1 (quantity: 25.000).
- **Target Invariant:**
  - Saving a confirmed Purchase Bill immediately increments `stock_balances` and appends an entry to `stock_movements` (Direction: `IN`).
  - Creates accounts payable liability in Accounts Payable ledger.

### 4.8 Payment Recording, Allocation & Receipt Printing
- **Current State:**
  - Schema includes `payments`, `payment_allocations`, `payment_refunds`, `bank_accounts`.
  - Active bank account: `HDFC Bank` (`50200048899211`, IFSC `HDFC0000240`, Current Balance: ₹12,40,500.00).
- **Target Invariant:**
  - Payment vouchers can be allocated to single or multiple invoices.
  - Partial payments reduce `invoices.amount_due` and update `payment_status` (`PARTIALLY_PAID` or `PAID`).
  - Decrements/increments Bank Account current balance and writes double-entry journal records.

### 4.9 Expenses & Accounting Journal Entries
- **Current State:**
  - 1 Expense record verified (`EXP-2026-0089`, ₹65,000.00 + ₹11,700.00 GST, Payee: K Raheja Corp, Category: Rent & Facilities).
  - 1 Manual Journal Entry verified in `journal_entries` (`JV-QA-1791632269`, Date: 2026-10-10, Total Debit: ₹3,000.00, Total Credit: ₹3,000.00).
  - Matching line items in `journal_entry_lines`: Line 1 Debit Cash ₹3,000.00; Line 2 Credit ₹3,000.00.
- **Target Invariant:**
  - Strict Double-Entry Invariant: $\sum \text{Debit} = \sum \text{Credit}$ enforced prior to database write. Transactions with unbalanced totals are rejected.

### 4.10 Reports, Exports & Filters
- **Current State:**
  - Database contains specialized report tables: `report_saved_views`, `report_schedules`, `report_snapshots`, `report_audit_logs`.
  - Financial reporting requires aggregation queries on `journal_entry_lines` and `invoices`.
- **Target Invariant:**
  - Real-time generation of GSTR-1 (B2B, B2CL, B2CS, HSN Summary), Trial Balance, Profit & Loss Statement, Balance Sheet, and Customer Aging Reports.

### 4.11 User Management & Role Permissions
- **Current State:**
  - 5 users, 5 roles, and 7 user-branch mappings populated.
  - Branch assignment table `user_branches` maps users to specific branches (`user_id`, `branch_id`, `is_default`).
- **Target Invariant:**
  - Role-based route middleware blocks unauthorized actions (e.g., Sales Executive cannot access Chart of Accounts or Bank Reconciliation).

### 4.12 API Authentication & Authorization
- **Current State:**
  - `App\Core\Request::getBearerToken()` extracts Bearer tokens from incoming HTTP `Authorization` headers.
  - `App\Core\Request::expectsJson()` accurately differentiates between HTML view requests and REST API calls.
  - `config/app.php` contains active JWT secret key.
  - Table `personal_access_tokens` exists for API token storage.
- **Target Invariant:**
  - Standardized JSON envelope: `{"success": true, "message": "...", "data": ...}`.
  - Database exception redaction prevents leaking SQL queries or table names.

---

## 5. Architectural Invariants Summary

| Domain | Invariant Rule | Verification Mechanism |
| :--- | :--- | :--- |
| **Accounting** | $\sum \text{Debit} = \sum \text{Credit}$ for every transaction | Validated in journal entry line summation |
| **Taxation** | Intra-state = CGST + SGST (50/50); Inter-state = IGST (100%) | Validated against company vs party state code |
| **Inventory** | Stock cannot be negative if `allow_negative_stock == 0` | Validated in stock balance checker before dispatch |
| **Tenant Isolation** | Every domain table scoped by `company_id` | Enforced in SQL WHERE clause of all queries |
| **Security** | Zero raw SQL concatenation; 100% prepared statements | Native PDO prepared bindings with named parameters |
| **Numbering** | Continuous, gapless sequential numbering | Database row lock / transaction-safe increment |
