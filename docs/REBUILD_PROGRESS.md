# WTSBill ERP — Rebuild & Recovery Progress Tracker

**Document Version:** 3.0.0  
**Last Updated:** 2026-10-26  
**Recovery Lead:** Senior PHP, MySQL, JavaScript & Systems Engineering Team  

---

## 1. Chunk Status Overview

| Chunk | Module Title | Original Status | Current Recovery Status | Target Verification |
| :---: | :--- | :---: | :---: | :--- |
| **0** | Project Rules & Structure | Completed | **VERIFIED** | Conventions & directory standards |
| **1** | Source & Behavior Audit | Completed | **VERIFIED** | Baseline documentation & recovery audit |
| **2** | PHP Application Foundation | Completed | **VERIFIED** | PSR-4 autoloader, Config, Request, Response, Router |
| **3** | Database Layer & PDO | Completed | **VERIFIED** | PDO connection, ACID rollback, transaction savepoints |
| **4** | Authentication & Multi-Tenant | Completed | **VERIFIED** | Multi-tenant session, Bcrypt, CSRF, RBAC |
| **5** | Main Layout & Dashboard | Completed | **VERIFIED** | App shell, navigation, dark mode, toast/modal |
| **6** | Parties (Customers & Suppliers) | Partial (Read Only) | **COMPLETED & VERIFIED** | Real customer & supplier registration modals, edit modals, AJAX persistence |
| **7** | Products & Inventory Management | Broken (Placeholder) | **COMPLETED & VERIFIED** | Product registration modal, Category modal, Stock adjustment modal, CRUD |
| **8** | Sales & Tax Invoices | Partial (Read Only) | **COMPLETED & VERIFIED** | Full invoice creation form, tax calculation, detail modal, ledger posting |
| **9** | Quotations, Orders & Challans | Partial (Read Only) | **COMPLETED & VERIFIED** | Quotation modal, delivery challan modal, sequential numbering |
| **10** | Purchases & Accounts Payable | Partial (Read Only) | **COMPLETED & VERIFIED** | Purchase bill entry modal, supplier balance updates, AP tracking |
| **11** | Payments, Receipts & Expenses | Partial (Read Only) | **COMPLETED & VERIFIED** | Expense voucher modal, payment entries, category assignment |
| **12** | Double-Entry Accounting | Completed | **COMPLETED & VERIFIED** | Chart of Accounts ledger modal, multi-line journal entry modal, Debit == Credit |
| **13** | Financial & Compliance Reports | Completed | **COMPLETED & VERIFIED** | P&L, Balance Sheet, GST tax report, GSTR-1 JSON and CSV export streams |
| **14** | Users, Roles & Settings | Partial (Read Only) | **COMPLETED & VERIFIED** | User invitation modal, Bcrypt password hashing, role management |
| **15** | Standardized REST API | Completed | **VERIFIED** | Health check & authentication JSON envelopes |
| **16** | Printing & PDF Engine | Completed | **VERIFIED** | High-contrast `@media print` & `number_to_words_inr` |
| **17** | Frontend Integration & Assets | Completed | **VERIFIED** | CSS tokens, WTSBill namespace, modal/toast JS |
| **18** | PHP Performance & Loading | Completed | **VERIFIED** | View cache, statement cache, ETag 304, gzip |
| **19** | Security Regression Audit | Completed | **VERIFIED** | SQLi, XSS, CSRF, Tenant isolation |
| **20** | Full Regression & Parity | Completed | **VERIFIED** | Complete regression suite |
| **21** | Controlled Cutover Readiness | Completed | **VERIFIED** | Deployment runbook & rollback plan |
| **22** | **Global Placeholder Popup Fix** | Completed | **COMPLETED & VERIFIED** | Zero alert() dialogs, 16 real modals/forms, 329/329 tests (100%) |
| **23** | **Branch Switching & Management** | Completed | **COMPLETED & VERIFIED** | Header branch selector, session context, admin CRUD, 350/350 tests (100%) |
| **24** | **WhatsApp & Email Sharing** | Completed | **COMPLETED & VERIFIED** | Delivery channel defaults, Cloud API + wa.me fallback, pure PHP PDF-1.4, dispatch history |
| **25** | **Light/Dark Theme Persistence** | Completed | **COMPLETED & VERIFIED** | Header toggle, user table + session persistence, anti-FOUC, isolated print styles |
| **26** | **Automatic Weekly Backup** | Completed | **COMPLETED & VERIFIED** | 7-day schedule, CLI scheduler, ZIP packaging, SHA-256 integrity, safety snapshots |
| **27** | **Atomic Audit Log Cleanup** | Completed | **COMPLETED & VERIFIED** | Post-backup verified archiving, 7-day retention purge, security event preservation |
| **28** | **15-Min Inactivity & Drafts** | Completed | **COMPLETED & VERIFIED** | 900s inactivity timeout, background request bypass, multi-tab sync, auto-draft recovery |

---

## 2. Global Placeholder Popup Fix Milestone Summary

- [x] **Project-Wide Scan:** Scanned all PHP, JavaScript, HTML templates, and shared UI components for `alert(`, `opens in next chunk`, `coming soon`, `TODO`, and unlinked buttons.
- [x] **Customer Registration:**
  - Accessible `#customer-modal` in `views/parties/customers.php`.
  - Full-page registration in `views/parties/create.php` submitting to `POST /customers/create`.
  - Customer edit modal fetching `GET /customers/{id}` and updating via `POST /customers/{id}/update`.
  - `CustomerRepository` and `CustomerService` enforcing multi-tenant isolation, GSTIN validation, and balances.
- [x] **Supplier Registration:**
  - Accessible `#supplier-modal` in `views/parties/suppliers.php`.
  - Supplier edit modal fetching `GET /suppliers/{id}` and updating via `POST /suppliers/{id}/update`.
  - `SupplierRepository` and `SupplierService` enforcing multi-tenant isolation and payment terms.
- [x] **Product & Inventory Management:**
  - Product registration modal in `views/inventory/products.php`.
  - Product Category creation modal `#category-modal` in `views/inventory/categories.php` submitting to `POST /categories`.
  - Stock Adjustment modal `#stock-modal` in `views/inventory/stock.php` submitting to `POST /inventory/stock/adjust` with `stock_movements` ledger update.
- [x] **Sales & Tax Invoices:**
  - Full tax invoice creator in `views/sales/create.php` with dynamic line items, intra/inter-state tax splitting, rupee round-off, customer balance update.
  - Interactive Invoice Detail Modal `#invoice-view-modal` in `views/sales/invoices.php` fetching line items and GST splits via `GET /sales/invoices/{id}` with print trigger.
  - Quotation creation modal `#quotation-modal` in `views/sales/quotations.php`.
  - Delivery challan modal `#challan-modal` in `views/sales/challans.php`.
- [x] **Purchases & Payables:**
  - Purchase bill recording modal `#purchase-modal` in `views/purchases/bills.php` updating supplier payable balances.
- [x] **Expenses:**
  - Expense voucher modal `#expense-modal` in `views/expenses/index.php` with category and payment mode selection.
- [x] **Accounting:**
  - Chart of Accounts modal `#account-modal` in `views/accounting/accounts.php` submitting to `POST /accounts`.
  - Balanced multi-line journal modal `#journal-modal` in `views/accounting/journals.php` enforcing Debit == Credit.
- [x] **Users & Roles:**
  - User creation modal `#user-modal` in `views/users/index.php` with Bcrypt password hashing.
- [x] **Reports & Exports:**
  - Real GSTR-1 JSON export stream (`GET /reports/gst/export?format=json`).
  - Real GSTR-1 CSV export stream (`GET /reports/gst/export?format=csv`).
- [x] **Automated Regression Suite:**
  - Created `tests/Feature/GlobalPopupFixTest.php` with 21 new end-to-end assertions (`POPUP-01` through `POPUP-21`).
  - Created `tests/Feature/PartialPaymentTest.php` with 13 assertions (`PARTPAY-01` through `PARTPAY-13`).
  - Created `tests/Feature/QuotationManagementTest.php` with 11 assertions (`QUOTE-01` through `QUOTE-11`).
  - Created `tests/Feature/CommunicationSharingTest.php` with 11 assertions (`COMM-01` through `COMM-11`).
  - Created `tests/Feature/ThemePersistenceTest.php` with 6 assertions (`THEME-01` through `THEME-06`).
  - Created `tests/Feature/BackupAndCleanupTest.php` with 11 assertions (`BACKUP-01` through `BACKUP-11`).
  - Created `tests/Feature/SessionIdleDraftTest.php` with 11 assertions (`DRAFT-01` through `DRAFT-11`).
  - Total test suite assertions: **649 / 649 Passed (100% Pass Rate)**.

- [x] **Partial Payment Management (Feature A):**
  - Payment statuses dynamically maintained: `UNPAID`, `PARTIALLY_PAID` (`PARTIAL`), `PAID`, `OVERDUE`.
  - Formula: `Total Paid = SUM(valid allocations); Outstanding Balance = Grand Total - Total Paid`.
  - Accessible `#record-payment-modal` with auto-computed balance due, max payable constraints, and payment modes (Cash, UPI, Bank Transfer, Cheque, Card).
  - Multiple installments against the same invoice; each stored as distinct records in `payments` and `payment_allocations`.
  - Payment history table in `#invoice-view-modal` showing date, receipt #, mode, reference #, recorded by, amount, status, and printable receipt.
  - Dedicated "Partial Payments & Invoice Balances" tab in `/payments` with receivables KPI cards and filters (Customer, Status, Date Range, Search).
  - Double-entry GL integration: Dr Bank/Cash (100200/100100) and Cr Accounts Receivable (100300) with customer ledger decrement.

- [x] **Complete Quotation Management & Conversion (Feature B):**
  - Dedicated commercial quotation creation (`/sales/quotations/create`) and editing (`/sales/quotations/{id}/edit`) matching Tax Invoice layout.
  - Visible `+ New Customer` action button opening quick AJAX modal (`#new-customer-modal`) to register and select customer immediately without page reload or loss of entered quotation line items.
  - Dynamic line item product category filtering (Category dropdown filters Product dropdown independently per row).
  - Real-time commercial calculations: Taxable Subtotal, CGST + SGST (or IGST), Round Off, Grand Total.
  - Quotation actions: Save as Draft, Save & Generate Quotation, Preview Modal, Print, Edit, Duplicate, Delete.
  - One-click Convert to Invoice workflow (`POST /sales/quotations/{id}/convert`): generates sequential Tax Invoice (`INV-YYYY-XXXX`), copies line items, sets quotation `status = 'CONVERTED'`, links `converted_invoice_id`, and blocks duplicate conversion.

- [x] **Dynamic Remaining Balance UPI QR Code & Payment Reconciliation:**
  - Automated real-time recalculation of outstanding balance from confirmed payments: `Outstanding Balance = Grand Total - SUM(confirmed payments)`.
  - Dynamic generation of ISO/IEC 18004 Model 2 vector SVG UPI QR codes formatted specifically for the exact remaining balance (`am=REMAINING_AMOUNT`).
  - Strict merchant UPI configuration from company settings (`upi_id`, `upi_display_name`, `payment_instructions`) with zero hardcoded sample IDs.
  - Automatic QR hiding and "PAID IN FULL" status display when balance is fully settled (₹0.00 due).
  - Helpful merchant configuration warning when UPI is not set up, safely suppressing invalid QR generation.
  - Payment reconciliation workflows distinguishing pending/unverified payments from confirmed payments.
  - Duplicate transaction reference defense enforcing HTTP 422 safeguards.
  - Synchronized across Invoice Details Modal (`#invoice-view-modal`), Printable Invoice (`/sales/invoices/{id}/print`), and REST APIs.

- [x] **Accounting Balance Sheet & General Ledger Drill-Down (Feature 1):**
  - Refined Balance Sheet categorized into standard accounting groups: Assets (Cash, Bank, AR, Inventory, Fixed, Other), Liabilities (AP, Expenses, Loans, Current, Long-term), and Equity (Capital, Retained Earnings, Current Period P&L).
  - Strict double-entry accounting integrity ($Total Assets = Total Liabilities + Total Equity$) with zero artificial adjustments.
  - Clickable Balance Sheet accounts with interactive AJAX quick-preview modal (`#accountLedgerModal`) linking directly to full General Ledger.
  - Dedicated General Ledger page (`/accounting/ledger`) with multi-filtering (Date range, Branch, Account, Type, Customer/Supplier, Voucher #), running Dr/Cr balances, pagination, and CSV export (`/accounting/ledger/export`).

- [x] **Complete 360-Degree Customer Profile & History (Feature 2):**
  - Dedicated Customer Profile view (`/customers/{id}`) accessible anywhere customer names are shown in ERP.
  - Comprehensive customer identity header: Code (`CUST-0001`), Contact, Phone, Email, Billing & Shipping Address, GSTIN, Registration Date, Status, Assigned Branch.
  - 8 Financial Summary KPI dashboard cards: Total Invoiced, Total Paid, Outstanding Balance, Overdue Amount, Total Quotations, Accepted Quotations, Pending Quotations, Credit Notes & Adjustments.
  - 7 Tabbed Panels: (1) Overview, (2) Invoices, (3) Payments, (4) Quotations, (5) Credit Notes, (6) Ledger & Statements, (7) Activity History.
  - Interactive Outstanding Invoices table with Days Overdue calculations and direct "+ Pay" modal trigger.
  - Seamless action buttons: Edit Customer, Create Invoice (pre-selected customer), Create Quotation (pre-selected customer), Record Payment modal, Statement CSV export, and Share Statement.

- [x] **Universal WhatsApp & Email PDF Sharing (Feature 3):**
  - Universal Share action on invoice and quotation details and listings.
  - WhatsApp PDF dispatch via Meta WhatsApp Business Cloud API (`type: "document"`, signed public URL, caption) with fallback to wa.me click-to-chat.
  - Email PDF dispatch with real PDF-1.4 attachment generated via `PdfGenerator`.
  - Secure signed public PDF viewing link (`/doc/invoices/{id}/pdf?token={hash}`).

- [x] **Invoice & Quotation List Status Filters & Pagination (Feature 4):**
  - Tax Invoice status pills tabs with live counts: All, Pending / Unpaid, Partially Paid, Paid, Overdue (`status != 'PAID' AND due_date < CURDATE()`).
  - Invoice multi-field filter bar: Search, Branch, Date Range (from/to), Min/Max amount, Reset.
  - Quotation lifecycle status pills: All, Draft, Sent, Accepted, Converted, Rejected, Expired.
  - Quotation linked invoice payment status dropdown: All, Unpaid / Pending, Partially Paid, Paid.
  - Responsive pagination footer across both registers.

## Milestone 16: Invoice Number Reuse After Invoice Deletion & Statutory Compliance (Completed 2026-11-05)
- **Reusable Invoice Numbers Pool:** Implemented `reusable_invoice_numbers` table tracking released sequence numbers, prefixes, branches, and deletion audit details.
- **Concurrency-Safe Acquisition:** `InvoiceNumberService::acquireInvoiceNumber` uses row-level `FOR UPDATE` locking inside database transactions to ensure zero race conditions or duplicate voucher assignments.
- **Ascending Gap Fill:** Deleted numbers are reused in strictly ascending order before incrementing to the next monotonic sequence.
- **Branch Numbering Isolation:** Branch-specific prefixes (e.g., Pune `INV-PUN-`, Mumbai `INV-BOM-`) strictly isolate reusable sequence pools to their respective branches.
- **Statutory Accounting Rules:** Invoices with confirmed payments (`amount_paid > 0` or status `PAID`/`PARTIAL`) or inside closed accounting periods are strictly blocked from deletion (HTTP 422).
- **GST Cancellation Separation:** Cancelled invoices (`POST /sales/invoices/{id}/cancel`) retain their numbers and log cancellation reasons for GST tax audit reconciliation; only authorized/permanent deletion releases numbers to the reusable pool.
- **Master Test Suite:** 10 new feature assertions in Section 52 bringing the total to **690 / 690 passing assertions (100%)**.

## Milestone 17: Complete ERP Module Expansion & Architectural Scaling (Completed 2026-11-06)
- **Module 1 — Customer Relationship Management (CRM) & Supplier 360° Profile:**
  - Expanded customer management with multi-address book (`customer_addresses` table for separate billing and shipping addresses).
  - Customer interaction notes (`customer_notes` table) and primary contact person attribution.
  - Complete 360-degree Supplier Profile (`/suppliers/{id}`) with identity header, 4 KPI cards (Total Billed, Total Disbursed, Balance Due, Active Debit Notes), 5 tabbed sections (Overview, Inward Bills, Disbursements, Debit Notes, Supplier Ledger), and ledger CSV export (`/suppliers/{id}/statement/csv`).
- **Module 2 — Sales Order Management Workflow:**
  - Complete commercial order fulfillment workflow: Customer Enquiry → Quotation → Sales Order → Delivery Challan → Tax Invoice.
  - Autonomous sales order lifecycle with dedicated sequence generator (`SO-YYYY-XXXX`).
  - Line-item tracking of ordered, delivered, and pending quantities with one-click conversion to Tax Invoice (`/sales/orders/{id}/convert-invoice`).
  - Document lineage tracking preserving references between quotations, sales orders, delivery challans, and invoices without premature general ledger postings.
- **Module 3 — Advanced Inventory, Warehouses & Stock Transfers:**
  - Multi-warehouse directory (`/inventory/warehouses`) tracking warehouse locations, capacity, SKU counts, and total inventory value.
  - Inter-warehouse stock transfers (`/inventory/transfers`) with sequential transfer codes (`TRF-YYYY-XXXX`).
  - Atomic stock deductions from origin and increments at destination wrapped in transactional database locks.
  - Inventory stock turnover and velocity analytics (`/inventory/velocity`) categorizing items into Fast, Moderate, Slow, and Dead Moving tiers.
- **Module 4 — Supplier Procurement Lifecycle (PO → GRN → Inward Bill → Debit Note):**
  - Complete procurement workflow with sequential Purchase Orders (`PO-YYYY-XXXX`).
  - Goods Received Note (GRN) verification modal tracking received vs pending quantities.
  - One-click inward purchase bill creation from POs (`POST /purchases/orders/{id}/convert-bill`).
  - Supplier Debit Notes (`/purchases/debit-notes`) against purchase bills calculating tax reversals (CGST, SGST, IGST) and reducing accounts payable.
- **Module 5 — Payments, Customer Advances & Receivables Aging:**
  - Customer advance receipt management (`/payments/advances`) with unallocated advance balance pool.
  - Advance settlement allocation modal against outstanding invoices with atomic balance adjustments.
  - Comprehensive Accounts Receivable Aging report (`/reports/receivables-aging`) structuring debts into 0-30, 31-60, 61-90, and 90+ days overdue buckets with CSV export.
- **Module 6 — Double-Entry Accounting Reports:**
  - Accountant-grade Trial Balance (`/accounting/trial-balance`) grouping accounts into standard double-entry groups (ASSET, LIABILITY, EQUITY, INCOME, EXPENSE) with real-time balance check (`Total Debits == Total Credits`).
  - Cash Book (`/accounting/cash-book`) recording Cash in Hand opening balance, receipts, payments, running balance, and closing cash.
  - Commercial Bank Book (`/accounting/bank-book`) with multi-bank account selector, deposits, withdrawals, and verified running balance.
  - Cash Flow Statement (`/reports/cash-flow`) categorizing cash movements into Operating, Investing, and Financing activities.
- **Module 7 — GST Management & Tax Compliance:**
  - Statutory GSTR-3B Summary report (`/reports/gstr-3b`) structuring Table 3.1 Outward Supplies, Table 4.0 Eligible ITC, and Table 6.1 Net Tax Payable in Cash.
  - Official GST Portal JSON schema export (`/reports/gstr-3b/export-json`) and return table CSV export.
  - B2B (15-character GSTIN) vs B2C Sales Register (`/reports/gst-sales-register`) with CGST/SGST/IGST breakdown and CSV export.
  - Inward Purchase Register (`/reports/gst-purchase-register`) with supplier compliance verification and CSV export.
- **Module 8 — Business Analytics & Executive BI Dashboard:**
  - Executive BI Dashboard (`/analytics/bi`) tracking Revenue, COGS, Operating Overhead, Gross Profit, Gross Margin %, Net Profit, and Net Margin %.
  - 6-month visual revenue vs expense trend time-series, branch performance benchmarking, top 5 selling products by turnover, and top 5 customer accounts.
  - Executive management CSV export (`/analytics/bi/export`).
- **Module 9 — Central In-App Notification Center:**
  - In-app notification center (`/notifications`) with real-time unread counter API (`/api/notifications/unread-count`).
  - Automated system alert generator for critical low-stock products (`current_stock <= min_stock_alert`) and overdue customer invoices (`due_date < CURDATE() AND amount_due > 0`).
  - Read status toggles and bulk mark-all-read endpoint (`POST /api/notifications/mark-all-read`).
- **Module 10 — Enterprise Notification Hub & Inactivity Defense:**
  - Automated scanner for critical low-stock products (`current_stock <= min_stock_alert`) and overdue debts (`due_date < CURDATE()`).
  - 15-minute idle session timeout with automatic draft preservation and login recovery banner.
- **Master Test Suite Verification:**
  - **780 Total Assertions across 61 test suites (100% pass rate, 0 failures)**.

---

## Milestone 18: Complete AI Assistant Removal, Customer Profile Filtering & Enterprise QA Audit (Completed 2026-11-06)
- **AI Assistant Complete Decommissioning & Purge:**
  - Removed all AI routes (`GET /assistant`, `POST /api/assistant/query`) from `routes/web.php`.
  - Deleted `app/Controllers/AiAssistantController.php`, `app/Services/AiAssistantService.php`, and `views/ai/` directory.
  - Removed AI Assistant sidebar navigation item from `config/menu.php`.
  - Converted test suite Section 61 into `tests/Feature/NotificationCenterHubTest.php`, actively asserting HTTP 404 on removed routes and zero AI menu items (`AIREM-01` to `AIREM-03`).
- **Customer Profile Live Payment Status Filters:**
  - Implemented instant client-side filtering pills (`All`, `Unpaid`, `Partial Payment`, `Paid`) and search inputs on Customer Profile (`/customers/{id}`) in both summary card and Tab 2 (Invoices).
  - Synchronized filter selection with URL query parameters for seamless bookmarking.
- **Enterprise Settings UI Hardening:**
  - Safely removed the unconfigured "Communication & Document Sharing" tab and form inputs from Enterprise Settings (`/settings`), eliminating user confusion while preserving WhatsApp Web click-to-chat sharing.
- **Enterprise QA Audit & Quality Sign-Off:**
  - Completed comprehensive QA inspection across all 20 functional modules (A to T).
  - Validated ₹50,000 multi-installment partial payment scenario (₹10k, ₹15k, overpayment boundary guard, ₹25k final settlement with UPI QR suppression).
  - Generated complete suite of 10 audit and quality assurance documents in `docs/`.
  - Maintained **100% test pass rate: 780 / 780 assertions passing across 61 test suites**.

---

## Milestone 19: Automatic 7-Day Weekly Backup & Disaster Recovery Hardening (Completed 2026-11-06)
- **Automatic 7-Day Weekly Scheduler:**
  - Integrated `isBackupDue(companyId, 7)` and `runScheduledWeeklyBackup()` in `BackupService`.
  - Prevents duplicate archive creation if scheduled tasks or cron jobs execute more than once within the 7-day window.
  - Standalone CLI runner `bin/scheduler.php` with commands: `backup [--weekly] [--force]`, `status`, `cleanup-audit`, `run-all`.
  - Ready-to-use setups documented for both Windows Task Scheduler and Linux Crontab.
- **Enterprise Storage, Security & Encryption:**
  - Backup archives written strictly to dedicated directory outside public web root (`storage/backups/`) protected with `.htaccess` `Deny from all`.
  - Added native PKWARE AES-256 archive encryption support (`ZipArchive::EM_AES_256`).
  - Added secondary storage destination synchronization support (`BACKUP_SECONDARY_PATH`).
- **Comprehensive Verification & Admin Alerting:**
  - Verifies database dumps, audit logs, configuration, metadata, and calculates SHA-256 integrity checksums.
  - On failure: preserves previous backups, records error in `backup_records` (`status = 'FAILED'`), and dispatches critical in-app notification to administrators via `NotificationService`.
  - Includes safe automated retry policy (`createBackupWithRetry()`).
- **Backup Dashboard UI Enhancements:**
  - Enhanced Backup Management dashboard (`/settings?tab=backups`):
    - 3 prominent KPI cards: Last Successful Backup, Next Scheduled Run (Frequency: Every 7 Days), and SHA-256 Verified indicator.
    - Verified Backup History table with Created At, Archive Name, Type, Size, SHA-256 Checksum, Status, and Actions.
    - Authorized actions: + Create Backup Now, Download, Restore (with pre-restore safety snapshot), and Delete Backup with confirmation modal.
    - Safety policy prevents deleting the only remaining successful backup archive.
- **Test Suite Verification:**
  - Added assertions `BACKUP-12` through `BACKUP-18` in `tests/Feature/BackupAndCleanupTest.php`.
  - **787 Total Assertions across 61 test suites passing at 100% (0 failures)**.

---

### Milestone 20: Centralized Company Branding Management (Settings → Company Branding)
- **Centralized Branding Architecture:**
  - Dedicated multi-tenant database table `company_brandings` with branch-level overrides, dimension controls, and document visibility switches.
  - Bidirectional backward-compatible synchronization with `companies.logo_url` and `companies.stamp_signature_url`.
- **Three Separate Asset Upload Cards:**
  - **Company Logo**: High-res preview with transparency checkerboard, 2MB size cap, supported PNG, WebP, SVG with strict anti-XSS XML sanitization.
  - **Authorized Signature**: Transparent PNG preview, replace/remove buttons, custom document dimensions (width, height, alignment).
  - **Company Stamp / Seal**: Transparent PNG preview, replace/remove buttons, custom document dimensions (width, height, alignment).
- **Global Automatic Propagation:**
  - Updates propagate dynamically across Header, Sidebar (`views/partials/sidebar.php`), Login (`views/auth/login.php`), Forgot/Reset Password, Sales Invoices (`views/sales/print.php`), Quotations (`views/sales/quotation_print.php`), Purchase Orders (`views/purchases/orders/print.php`), and PDFs.
  - Automated timestamp cache-busting (`?v={timestamp}`) prevents browser and proxy caching issues.
- **Security & Authorization Controls:**
  - File verification with `getimagesize()` and MIME inspection; malicious `<script>` SVG payloads rejected with HTTP 422.
  - Isolated file storage under `public/uploads/branding/` with unique cryptographically random names.
  - Removal confirmation modal and automated deletion of replaced/removed custom assets from disk.
- **Test Suite Verification:**
  - Added 10 assertions in Section 62 (`tests/Feature/CompanyBrandingTest.php`).
  - **797 Total Assertions across 62 test suites passing at 100% (0 failures)**.

---

### Milestone 21: WhatsApp & Email Document Sharing System
- **Universal Share Modal (`views/partials/share_modal.php`):**
  - Integrated across Invoice listing table, Quotation listing table, Invoice print preview, and Quotation print preview.
  - Document summary with document number, customer name, grand total, and balance.
  - Quick action buttons for on-screen document preview and direct PDF download.
  - WTSBill enterprise Forest Green theme (`#051F20`, `#0B2B26`, `#235347`, `#8EB69B`, `#DAF1DE`).
- **Multi-Modal WhatsApp Sharing:**
  - **Method A (Web Share API)**: Passes generated `Invoice_{number}.pdf` or `Quotation_{number}.pdf` directly into native WhatsApp file sharing on supported mobile and modern browsers.
  - **Method B (WhatsApp Business Cloud API)**: Meta Graph API integration with secure document links and official WhatsApp Business delivery.
  - **Fallback**: Automated PDF download combined with prefilled click-to-chat (`wa.me`) link with sanitized E.164 phone numbers.
- **Enterprise SMTP Email Delivery:**
  - Direct socket-based SMTP client with TLS/SSL encryption and `AUTH LOGIN` support.
  - Auto-fetches saved customer email, allows custom CC recipients, customizable subject, and body text.
  - Standards-compliant `multipart/mixed` MIME architecture attaching true generated PDF binaries.
  - Strict provider response verification (`250 OK`), avoiding false delivery claims.
- **PDF Generation & Security:**
  - Enriched `PdfGenerator.php` producing PDF-1.4 documents with company branding, tax tables, bank details, dynamic UPI QR codes, and terms.
  - Anti-tampering HMAC SHA-256 tokens for secure public PDF downloads.
  - Full audit logging in `communication_messages` table tracking entity, recipient, channel, status, and error details.
- **Settings Integration:**
  - Dedicated Communication & Sharing tab under Settings (`/settings?tab=communication`) for SMTP and WhatsApp API credentials, default toggles, message templates, and diagnostic test senders.
- **Test Suite Verification:**
  - Added 20 assertions in Section 45 (`tests/Feature/CommunicationSharingTest.php`).

## Milestone 21: Centralized Company Branding Management (Completed 2026-11-07)
- **Settings UI (`/settings/branding`):**
  - Three dedicated upload cards: Company Logo, Authorized Signature, and Company Stamp.
  - Dimension guidelines, supported format badges (PNG, SVG, JPG, WEBP), and live asset previews.
  - Document display size controls (Width/Height) and document visibility toggles (Show on Invoices, Quotations, Purchase Bills).
- **Centralized Database Storage (`company_brandings` table):**
  - Multi-tenant and branch-scoped storage with fallback to company defaults.
  - Secure uploads directory (`public/uploads/branding`) with file type verification and malicious SVG script sanitization.
- **Global Asset Propagation:**
  - `CompanyBrandingService` automatically provides cache-busting URLs across Website Header, Sidebar, Dashboard, Tax Invoices, Quotations, and Purchase Orders.

## Milestone 23: Invoice Payment Status Report & Office Open XML (.xlsx) Export (Completed 2026-11-07)
- **Report Location & Single-Expanded Accordion Navigation:**
  - Dedicated financial report at `/reports/invoice-payment-status`, nested under Reports in `includes/menu.php` and `config/menu.php`.
  - Seamlessly integrates with the single-expanded sidebar accordion navigation, automatically highlighting and expanding Reports on load.
- **Real-Time KPI Summary Cards:**
  - **Pending Invoices (Unpaid)**: Count, Original Invoiced Amount, Total Outstanding.
  - **Paid Invoices (Fully Settled)**: Count, Original Invoiced Amount, Total Amount Received.
  - **Partially Paid Invoices**: Count, Original Invoiced Amount, Amount Received, Remaining Balance.
  - **Total Outstanding**: Combined accounts receivable exposure across all open invoices.
- **Accrual Reconciliation & Integrity Guards:**
  - Reconciles finalized invoice `grand_total` against confirmed payment allocations (`allocated_amount` where payment is not cancelled and not deleted).
  - Strictly excludes cancelled/voided invoices and reversed payments.
  - Handles single and multiple partial payment installments without double counting.
- **Multi-Dimensional Filter Suite:**
  - Date ranges (`date_from`, `date_to`), Due date ranges (`due_date_from`, `due_date_to`), Invoice Number, Customer Name, Mobile Number, Operating Branch, Payment Status, Overdue Status, and keyword search with Clear Filters button.
- **Detailed 9-Column Report Table:**
  - Invoice Number (hyperlinked to `/sales/invoices/{id}`), Invoice Date, Customer Name, Mobile Number, Invoice Amount, Paid Amount, Remaining Balance, Due Date (with Overdue indicator), and Payment Status badge.
- **Genuine Office Open XML (.xlsx) Export:**
  - Server-side binary generation using native PHP `ZipArchive` (not CSV renamed to .xlsx).
  - Strict 9-column schema, frozen headers, KPI block, styled currency number cells (`#,##0.00`), and formula injection sanitization.
- **Architectural Test Verification:**
  - 15 new assertions in Section 64 (`tests/Feature/InvoicePaymentStatusReportTest.php`).
  - **833 Total Assertions across 64 test suites passing at 100% (0 failures)**.




