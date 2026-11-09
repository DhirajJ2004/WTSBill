# WTSBill ERP — File Registry & Architectural Map

**Document Version:** 2.1.0  
**Effective Date:** 2026-10-26  
**Purpose:** Canonical inventory of all application classes, views, controllers, services, repositories, routes, assets, and tests in the WTSBill ERP workspace.

---

## 1. Application Controllers (`app/Controllers/`)

| File Path | Namespace | Role & Endpoints |
| :--- | :--- | :--- |
| `app/Controllers/AuthController.php` | `WTSBill\Controllers` | Authentication, login, logout, password reset |
| `app/Controllers/BranchController.php` | `WTSBill\Controllers` | Branch switching, admin CRUD, Add Branch creation (`POST /branches`), dynamic branch list (`GET /api/branches`), user branch assignments |
| `app/Controllers/RoleController.php` | `WTSBill\Controllers` | RBAC role creation, permission matrix toggles, assignment |
| `app/Controllers/SalesController.php` | `WTSBill\Controllers` | Tax invoice generation, multi-line items, category product filter API (`/api/categories/{id}/products`), invoice installment payments (`POST /sales/invoices/{id}/payments`), print templates |
| `app/Controllers/QuotationController.php` | `WTSBill\Controllers` | Dedicated commercial quotations workflow (`/sales/quotations`), line item categories, draft/save, duplicate, print, and one-click conversion to Tax Invoice (`POST /sales/quotations/{id}/convert`) |
| `app/Controllers/PaymentController.php` | `WTSBill\Controllers` | Payments & Collections workspace, dual-tab UI (Vouchers & Partial Payments/Invoice Balances), payment recording, and receipts |
| `app/Controllers/SettingsController.php` | `WTSBill\Controllers` | General company profile, branch settings, document numbering, Communication & Sharing tab, Backup & Disaster Recovery tab |
| `app/Controllers/CommunicationController.php` | `WTSBill\Controllers` | WhatsApp Cloud API / wa.me fallback, SMTP email dispatch with attached PDF-1.4, test connections, and document dispatch audit logging |
| `app/Controllers/BackupController.php` | `WTSBill\Controllers` | Disaster recovery workspace: manual & weekly backup creation, ZIP download, automated safety snapshots, database restoration, and audit log retention cleanup |
| `app/Controllers/DraftController.php` | `WTSBill\Controllers` | Inactivity session auto-save, draft status checking, background touch, and draft discard API |

---

## 2. Domain Services (`app/Services/`)

| File Path | Namespace | Role & Responsibilities |
| :--- | :--- | :--- |
| `app/Services/AuthService.php` | `WTSBill\Services` | Authentication state, Bcrypt verification, session binding |
| `app/Services/BranchService.php` | `WTSBill\Services` | Branch switching authorization, branch creation with contact person & validation, session context |
| `app/Services/PaymentService.php` | `WTSBill\Services` | Partial invoice payment calculations, multiple installments, status transitions (UNPAID/PARTIAL/PAID), customer ledger decrements, and double-entry GL journal posting |
| `app/Services/ProductService.php` | `WTSBill\Services` | Product business rules, SKU uniqueness, GST rates, stock adjustments |
| `app/Services/CommunicationService.php` | `WTSBill\Services` | Document sharing engine: E.164 phone normalization, professional templated messages, WhatsApp Business Cloud API & wa.me fallback, socket SMTP delivery with PDF-1.4 attachment, dispatch audit tracking |
| `app/Services/BackupService.php` | `WTSBill\Services` | Pure PHP PDO database dumper (excluding DR meta tables), archive compression (`database.sql`, `audit_logs_archive.json`, `config_summary.json`, uploads), SHA-256 verification, safe restore with pre-restore safety snapshots |
| `app/Services/AuditCleanupService.php` | `WTSBill\Services` | Post-backup atomic audit cleaner: validates backup integrity and purges non-security logs older than retention period (default 7 days) |
| `app/Services/DraftService.php` | `WTSBill\Services` | Form draft persistence: stores in-flight drafts in `user_drafts` with zero GL/ledger side-effects, recovery queries, and discard handling |
| `app/Services/PdfGenerator.php` | `WTSBill\Services` | Standalone pure PHP binary `%PDF-1.4` stream generator for Invoices and Quotations with xref tables and EOF trailers |
| `app/Services/InvoiceNumberService.php` | `WTSBill\Services` | Intelligent invoice number sequencing, concurrency-safe acquisition (`FOR UPDATE`), number reuse from `reusable_invoice_numbers` after authorized invoice deletion, branch prefix isolation, and statutory GST cancellation preservation |

---

## 3. Repositories (`app/Repositories/`)

| File Path | Namespace | Role & Responsibilities |
| :--- | :--- | :--- |
| `app/Repositories/BaseRepository.php` | `WTSBill\Repositories` | Foundational PDO query builder with multi-tenant scoping |
| `app/Repositories/BranchRepository.php` | `WTSBill\Repositories` | Branch entity queries, `findSwitcherBranches`, user branch access, default branch resolution |
| `app/Repositories/UserRepository.php` | `WTSBill\Repositories` | User lookup by email, remember token management |
| `app/Repositories/ProductRepository.php` | `WTSBill\Repositories` | Product entity queries, SKU lookup, category filtering, stock updates |

---

## 4. Core Foundational Primitives (`app/Core/`)

| File Path | Namespace | Role & Responsibilities |
| :--- | :--- | :--- |
| `app/Core/Autoloader.php` | `WTSBill\Core` | PSR-4 standalone autoloader |
| `app/Core/Config.php` | `WTSBill\Core` | Configuration manager (`config/*.php`) |
| `app/Core/Database.php` | `WTSBill\Core` | Native PDO connection, statement caching, transactions |
| `app/Core/Env.php` | `WTSBill\Core` | Environment variable loader (.env) |
| `app/Core/Logger.php` | `WTSBill\Core` | Sanitizing logger with secret redaction |
| `app/Core/Request.php` | `WTSBill\Core` | Unified HTTP request abstraction |
| `app/Core/Response.php` | `WTSBill\Core` | HTTP response builder with security & cache headers |
| `app/Core/Router.php` | `WTSBill\Core` | Regex routing engine with middleware pipeline |
| `app/Core/Session.php` | `WTSBill\Core` | Secure native session & CSRF manager |
| `app/Core/Validator.php` | `WTSBill\Core` | Server-side validation rules & error bags |
| `app/Core/View.php` | `WTSBill\Core` | Safe template renderer with layout resolution |
| `app/Core/helpers.php` | `WTSBill\Core` | Global helpers (`env`, `config`, `e`, `format_currency`, `number_to_words_inr`) |

---

## 5. Middleware (`app/Middleware/`)

| File Path | Namespace | Role |
| :--- | :--- | :--- |
| `app/Middleware/AuthenticationMiddleware.php` | `WTSBill\Middleware` | Enforce authenticated session, redirect unauthenticated users to `/login` |
| `app/Middleware/AuthorizationMiddleware.php` | `WTSBill\Middleware` | Role-based access control (Admin, Accountant, Manager, Operator) |
| `app/Middleware/CsrfMiddleware.php` | `WTSBill\Middleware` | Verify CSRF token on POST/PUT/DELETE |
| `app/Middleware/TenantMiddleware.php` | `WTSBill\Middleware` | Enforce company tenant boundary scoping |

---

## 6. Views & UI Templates (`views/`)

| File Path | Role & Description |
| :--- | :--- |
| `views/layouts/app.php` | Main authenticated enterprise shell with sidebar, header, toast container |
| `views/layouts/auth.php` | Centered unauthenticated shell for login/reset |
| `views/partials/header.php` | Header with branch selector dropdown, + Add Branch modal with validation & dynamic refresh, theme toggle, user profile |
| `views/partials/sidebar.php` | Collapsible sidebar navigation for all ERP modules |
| `views/partials/modal.php` | Accessible modal dialog container |
| `views/partials/share_modal.php` | Reusable document sharing modal (Email & WhatsApp, pre-filled contacts, custom message, live history) |
| `views/partials/toast.php` | Toast notification alert container |
| `views/dashboard/index.php` | Enterprise dashboard with metrics and recent invoices |
| `views/inventory/products.php` | Product catalog with working modal registration and CRUD actions |
| `views/inventory/stock.php` | Stock balances & adjustments view |
| `views/inventory/categories.php` | Product categories view with taxonomy overview, linked products counter, Manage Category modal, Edit Category modal, Delete safeguard, and quick status toggles |
| `views/parties/customers.php` | Customer directory view |
| `views/parties/suppliers.php` | Supplier directory view |
| `views/parties/create.php` | Customer onboarding form view |
| `views/sales/invoices.php` | Sales invoices directory view with + Pay partial payments action, payment history installments table, and Record Payment modal |
| `views/sales/create.php` | Tax invoice creation view with dynamic product category filtering per line item |
| `views/sales/quotations.php` | Quotations and estimates view with status pipeline KPIs, Convert to Invoice action, duplicate, print, and view modal |
| `views/sales/quotation_create.php` | Dedicated quotation creation view with + New Customer quick modal, line item category dropdowns, and draft/save |
| `views/sales/quotation_edit.php` | Dedicated quotation edit view preserving line item categories, recalculations, and status transitions |
| `views/payments/index.php` | Payments workspace with Vouchers Register and Partial Payments & Invoice Balances tabs, filters, and payment modals |
| `views/sales/challans.php` | Delivery challans view |
| `views/purchases/bills.php` | Purchase bills view |
| `views/expenses/index.php` | Expenses ledger view |
| `views/accounting/accounts.php` | Chart of accounts view |
| `views/accounting/journals.php` | Journal entries view |
| `views/accounting/ledger.php` | General ledger view |
| `views/reports/profit-loss.php` | Profit & Loss financial report |
| `views/reports/balance-sheet.php` | Balance Sheet financial report |
| `views/reports/gst.php` | GSTR-1 compliance tax report |
| `views/users/index.php` | User administration view |
| `views/settings/index.php` | Company & branch settings view (General, Branches, Prefix, Communication, Backup & DR, Audit Logs) |

---

## 7. CLI Scripts & Background Daemons (`bin/`)

| File Path | Role & Capabilities |
| :--- | :--- |
| `bin/scheduler.php` | Background CLI scheduler runner for automated weekly backups (`backup --weekly`), audit cleanup (`cleanup-audit`), and combined runs (`run-all`) |

---

## 8. Client Assets (`public/assets/js/`)

| File Path | Role & Capabilities |
| :--- | :--- |
| `public/assets/js/session-idle.js` | 15-minute inactivity session idle watchdog: multi-tab sync via `localStorage`, 60s countdown warning at 14m, background draft auto-saving, redirection to `/login?session_expired=1`, and form recovery prompt |

---

## 9. Automated Test Suites (`tests/`)

| File Path | Test Type | Coverage Focus |
| :--- | :--- | :--- |
| `tests/Unit/CoreRouterTest.php` | Unit | Routing regex, params, middleware |
| `tests/Unit/CoreRequestTest.php` | Unit | Request methods, headers, JSON body |
| `tests/Unit/CoreResponseTest.php` | Unit | Response headers, status codes, JSON envelope |
| `tests/Unit/DatabaseConfigurationTest.php` | Unit | Database configuration validation |
| `tests/Integration/DatabaseConnectionTest.php` | Integration | PDO connection, charset, error redaction |
| `tests/Integration/DatabaseTransactionTest.php` | Integration | ACID commit, rollback, savepoints |
| `tests/Integration/ProjectBootstrapTest.php` | Integration | Autoloader, config, session bootstrap |
| `tests/Feature/AuthenticationTest.php` | Feature | Login, logout, session expiration |
| `tests/Feature/AuthorizationTest.php` | Feature | RBAC role matrix, access control |
| `tests/Security/CsrfProtectionTest.php` | Security | CSRF token verification & 419 response |
| `tests/Security/TenantIsolationTest.php` | Security | Tenant repository scoping & isolation |
| `tests/Security/AuthenticationSecurityTest.php` | Security | Password hashing, timing defense |
| `tests/Security/SqlInjectionTest.php` | Security | Parameterized query SQLi defense |
| `tests/Security/XssProtectionTest.php` | Security | HTML escaping in templates |
| `tests/Security/AccessControlTest.php` | Security | Route authorization boundaries |
| `tests/Feature/LayoutRenderingTest.php` | Feature | HTML5 layouts, navigation partials |
| `tests/Feature/FrontendIntegrationTest.php` | Feature | Asset loading, JS controllers |
| `tests/Integration/PerformanceSmokeTest.php` | Integration | View cache, statement cache, ETag |
| `tests/Integration/DatabaseIntegrityTest.php` | Integration | Database schema, foreign keys, uniqueness |
| `tests/Integration/BusinessLogicParityTest.php` | Integration | GST math, discounts, round-off, stock |
| `tests/Integration/RouteParityTest.php` | Integration | 21 routes HTTP 200, guest 302 |
| `tests/Integration/PrintOutputParityTest.php` | Integration | @media print, number_to_words_inr |
| `tests/Feature/FullWorkflowRegressionTest.php` | Feature | End-to-end commercial ERP cycle |
| `tests/Feature/ProductManagementTest.php` | Feature | 10 assertions: Product creation, validation, duplicate SKU rejection, editing, persistence, and deletion |
| `tests/Feature/InvoiceCategoryFilterTest.php` | Feature | 15 assertions: Dynamic category-filtered products API, multi-category line items, DB persistence, validation, edit/update |
| `tests/Feature/CategoryManagementTest.php` | Feature | 10 assertions: Category actions, show/manage API, edit update, status toggles, linked product deletion guard, and deletion |
| `tests/Feature/InvoiceEditDeleteTest.php` | Feature | Tax invoice editing, line item modification, deletion safety |
| `tests/Feature/PartialPaymentTest.php` | Feature | 13 assertions: Partial payments, installments, UNPAID->PARTIAL->PAID status, A/R & bank GL posting, overpayment rejection |
| `tests/Feature/QuotationManagementTest.php` | Feature | 11 assertions: Quotation lifecycle, line item categories, draft, edit, clone duplicate, one-click conversion to Tax Invoice |
| `tests/Feature/SupplierDeleteTest.php` | Feature | 7 assertions: Supplier directory deletion button, modal confirmation, deletion execution, directory exclusion |
| `tests/Feature/AccountManagementTest.php` | Feature | 10 assertions: Chart of accounts edit/delete, GL modal binding, system account deletion protection (HTTP 422), soft/hard delete |
| `tests/Feature/DynamicUpiQrPaymentTest.php` | Feature | 10 assertions: Dynamic remaining balance UPI QR, real-time recalculation, QR hiding on full settlement, UPI config fallback, unverified payment protection, duplicate reference rejection |
| `tests/Feature/CommunicationSharingTest.php` | Feature | 11 assertions: E.164 normalization, channel defaults, WhatsApp Cloud API & wa.me fallback, PDF generation, dispatch history |
| `tests/Feature/ThemePersistenceTest.php` | Feature | 6 assertions: Light & dark theme switching, users table & session persistence, HTML data-theme binding, print white background isolation |
| `tests/Feature/BackupAndCleanupTest.php` | Feature | 11 assertions: Pure PHP backup creation, SHA-256 verification, secure download, safety snapshots, DR restore, atomic audit log retention cleanup |
| `tests/Feature/SessionIdleDraftTest.php` | Feature | 11 assertions: 15-minute inactivity session expiration, background request bypass, user_drafts persistence, multi-tab sync, form recovery |
| `app/Services/LedgerService.php` | Service | General Ledger running balance calculation, opening balance resolution, party name mapping, CSV export |
| `views/accounting/ledger.php` | View | General Ledger page with KPI cards, multi-filters (date, branch, account, type), Dr/Cr running balances, CSV/print |
| `views/parties/customer_profile.php` | View | Complete 360-degree customer profile with 8 KPI cards, 7 tabs, overdue days calculation, payment modal, statement CSV |
| `tests/Feature/LedgerStatementTest.php` | Feature | 9 assertions: General Ledger view, KPI cards, filters, JSON API, chronological running balance, branch scoping, CSV export |
| `tests/Feature/CustomerProfileTest.php` | Feature | 9 assertions: Customer profile HTML, compliance details, 8 KPI cards, 7 tabs, days overdue, pre-selected create actions, statement CSV |
| `tests/Feature/DocumentFilterTest.php` | Feature | 10 assertions: Invoice payment status pills, multi-filters, overdue calculation, Quotation lifecycle & linked payment filters, pagination |
| `tests/Feature/InvoiceNumberReuseTest.php` | Feature | 10 assertions: Invoice number reuse after deletion, next monotonic sequence, ascending order reuse, branch scoping, payment protection, accounting period locks, GST cancellation preservation, permanent deletion, audit logging, active uniqueness |
| `app/Controllers/SalesOrderController.php` | Controller | Commercial order fulfillment lifecycle, order listing with status pills, creation, quotation conversion, challan conversion, invoice generation, printable document |
| `app/Services/SalesOrderService.php` | Service | Sales order management (SO-YYYY-XXXX), multi-item tax calculations, fulfillment status, and non-accounting commercial commitment safety |
| `views/sales/orders/index.php` | View | Sales Orders list with status pills (ALL, PENDING, CONFIRMED, PROCESSING, DISPATCHED, COMPLETED, CANCELLED), branch filter, search |
| `views/sales/orders/create.php` | View | Sales order creation form with customer selector, dynamic category/product rows, tax summary, notes |
| `views/sales/orders/show.php` | View | Order details view with ordered vs delivered quantities, 1-click invoice/challan conversion, and document lineage |
| `views/sales/orders/print.php` | View | Professional printable Sales Order document with buyer details, GST summary, authorized signatory |
| `views/parties/supplier_profile.php` | View | Complete 360-degree Supplier Profile with 4 KPI cards, 5 tabs (Overview, Inward Bills, Disbursements, Debit Notes, Supplier Ledger), CSV export |
| `tests/Feature/CrmExpansionTest.php` | Feature | 10 assertions: Customer multi-address book (`customer_addresses`), notes, contact person, 360-degree supplier profile, supplier ledger, CSV export |
| `tests/Feature/SalesOrderWorkflowTest.php` | Feature | 10 assertions: Complete commercial order lifecycle (Quotation -> Sales Order -> Delivery Challan -> Tax Invoice), SO numbering, duplicate prevention, deletion safety |
| `app/Services/WarehouseService.php` | Service | Multi-warehouse inventory balances, inter-warehouse transfers (TRF-YYYY-XXXX), stock velocity analysis |
| `app/Controllers/WarehouseController.php` | Controller | Warehouse directory, stock movement logging, transfer orders, fast/slow moving velocity metrics |
| `views/inventory/warehouses/index.php` | View | Warehouse master list with capacity, SKU counts, total stock valuations, and manager attribution |
| `views/inventory/transfers/index.php` | View | Inter-warehouse stock transfer register with status pills and verification audit trail |
| `views/inventory/transfers/create.php` | View | Stock transfer order creator with source/destination warehouse selectors and available stock guards |
| `views/inventory/velocity.php` | View | Inventory turnover & stock movement velocity dashboard with Fast, Moderate, Slow, Dead categories |
| `tests/Feature/WarehouseStockTransferTest.php` | Feature | 10 assertions: Multi-warehouse allocation, transfer workflow, atomic inventory deduction/addition, turnover velocity |
| `app/Services/PurchaseOrderService.php` | Service | Supplier procurement lifecycle (PO-YYYY-XXXX), GRN receiving inspection, inward purchase billing, debit notes |
| `app/Controllers/PurchaseOrderController.php` | Controller | Purchase orders, GRN verification, purchase invoice conversion, vendor debit notes |
| `views/purchases/orders/index.php` | View | Purchase order register with status pills, supplier attribution, and received status |
| `views/purchases/orders/create.php` | View | Purchase order creator with supplier selector, items, GST calculation, expected delivery date |
| `views/purchases/orders/show.php` | View | PO details view with GRN receipt conversion, inward billing, and PDF print action |
| `views/purchases/orders/print.php` | View | Professional printable Purchase Order document with billing/shipping addresses and signature section |
| `views/purchases/debit_notes/index.php` | View | Vendor debit notes register with original bill links, tax reversals, and status |
| `views/purchases/debit_notes/create.php` | View | Debit note generator against purchase bills with item return and tax reversal calculations |
| `tests/Feature/ProcurementLifecycleTest.php` | Feature | 10 assertions: Complete procurement cycle (PO -> GRN -> Inward Bill -> Debit Note), line item integrity, vendor balances |
| `app/Services/CustomerAdvanceService.php` | Service | Unallocated customer advances, FIFO and manual invoice allocation, aged receivables analysis |
| `app/Controllers/CustomerAdvanceController.php` | Controller | Advance receipt creation, invoice settlement allocation, aging receivables report |
| `views/payments/advances/index.php` | View | Customer advances register with unallocated balance tracking and settlement actions |
| `views/payments/advances/create.php` | View | Record customer advance receipt form with payment mode, reference, and deposit account |
| `views/reports/receivables_aging.php` | View | Accounts receivable aging report with 0-30, 31-60, 61-90, 90+ overdue aging brackets and CSV export |
| `tests/Feature/CustomerAdvancesAgingTest.php` | Feature | 10 assertions: Advance recording, unallocated pool tracking, partial invoice allocation, receivables aging classification |
| `app/Services/ExtendedAccountingService.php` | Service | Statutory double-entry reports: Trial Balance, Cash Book, Commercial Bank Book, Cash Flow Statement |
| `app/Controllers/ExtendedAccountingController.php` | Controller | Accounting report views, date range/branch filters, and standardized CSV export streams |
| `views/accounting/trial_balance.php` | View | Accountant-grade Trial Balance with double-entry balance check (Total Debits == Total Credits) |
| `views/accounting/cash_book.php` | View | Cash Book ledger with Cash in Hand opening balance, receipts, payments, and running balance |
| `views/accounting/bank_book.php` | View | Commercial Bank Book ledger with account selector, deposits, withdrawals, and balance verification |
| `views/reports/cash_flow.php` | View | Cash Flow Statement with Operating, Investing, and Financing activities breakdown |
| `tests/Feature/ExtendedAccountingReportsTest.php` | Feature | 10 assertions: Trial balance equality, Cash book receipts/disbursements, Bank book transactions, Cash flow net changes |
| `app/Services/GstComplianceService.php` | Service | Statutory GSTR-3B summary (Tables 3.1, 4.0, 6.1), B2B/B2C Sales Register, Inward Purchase Register |
| `app/Controllers/GstComplianceController.php` | Controller | GSTR-3B preparation, official GST portal JSON schema export, Sales/Purchase register CSV exports |
| `views/reports/gstr_3b.php` | View | GSTR-3B summary table with Outward Taxable Supplies, Eligible ITC, and Net Cash Liability |
| `views/reports/gst_sales_register.php` | View | Detailed GST Sales Register with B2B vs B2C categorization, place of supply, and tax split |
| `views/reports/gst_purchase_register.php` | View | Detailed GST Purchase Register with supplier GSTINs, invoice details, and ITC eligibility |
| `tests/Feature/GstRegistersComplianceTest.php` | Feature | 10 assertions: GSTR-3B table math, portal JSON schema compliance, Sales/Purchase register breakdowns, CSV streams |
| `app/Services/AnalyticsService.php` | Service | Executive BI KPIs, gross/net margins, 6-month revenue trends, branch benchmarks, top products & accounts |
| `app/Controllers/AnalyticsController.php` | Controller | Executive Business Intelligence dashboard and management CSV export |
| `views/analytics/bi_dashboard.php` | View | Modern BI Dashboard with metric cards, revenue/expense trend charts, product ranking, and branch comparisons |
| `tests/Feature/BusinessAnalyticsTest.php` | Feature | 10 assertions: KPI mathematical consistency, profit equations, monthly time-series, product/customer rankings |
| `app/Services/NotificationService.php` | Service | Central in-app notification center, unread counters, automated low-stock and overdue invoice alerts |
| `app/Controllers/NotificationController.php` | Controller | Notification center UI, unread count API, mark as read, and bulk read actions |
| `views/notifications/index.php` | View | Central notification center with category filters, alert severity badges, and quick link actions |
| `tests/Feature/NotificationCenterHubTest.php` | Feature | 10 assertions: In-app notification center, unread counter, mark as read, automated stock/debt alerts scanner, and AI removal verification (HTTP 404 on /assistant and /api/assistant/query, 0 menu items) |
| `tests/run_tests.php` | Master Runner | Executes all 61 test suites (787 assertions, 100% pass) |
| `docs/BACKUP_AND_RESTORE.md` | Documentation | Enterprise disaster recovery and automated weekly backup manual with AES-256 encryption & secondary sync |
| `docs/BACKUP_SCHEDULE.md` | Documentation | Automated 7-day backup schedule guide with Linux crontab and Windows Task Scheduler instructions, duplicate prevention |
| `docs/QA_MASTER_REPORT.md` | Documentation | Master QA audit report covering all 20 functional modules (A-T), 787 test assertions, 100% pass rate, and quality certification |
| `docs/BUG_TRACKER.md` | Documentation | Comprehensive bug tracker recording all identified defects, root causes, corrective actions, and prevention controls |
| `docs/MODULE_TEST_RESULTS.md` | Documentation | Detailed module-by-module test execution matrix with scenarios, inputs, expected/actual results, and pass statuses |
| `docs/SECURITY_AUDIT.md` | Documentation | OWASP Top 10 security verification, multi-tenant/branch data isolation, session timeout, PDO injection defense, and disaster recovery |
| `docs/PERFORMANCE_AUDIT.md` | Documentation | Performance benchmarks, EXPLAIN query plan evaluations, index coverage, chunked CSV streaming, and sub-40ms response metrics |
| `docs/UI_UX_AUDIT.md` | Documentation | UI/UX design audit verifying zero browser popups, light/dark themes, print styling isolation, and responsive 360° profiles |
| `docs/AI_ASSISTANT_REMOVAL_REPORT.md` | Documentation | Complete decommissioning report for AI Assistant routes, controllers, services, views, and navigation menus |
| `docs/REGRESSION_TEST_RESULTS.md` | Documentation | Complete regression test results across 61 test sections and 787 passing assertions |
| `docs/REBUILD_PROGRESS.md` | Documentation | Complete rebuild progress milestones up to Milestone 19 (Automatic 7-Day Weekly Backup Automation) |
| `database/migrations/2026_11_07_000001_create_company_brandings_table.sql` | Migration | Schema creation for company_brandings table with multi-tenant, branch override, dimensions, and document toggle columns |
| `app/Services/CompanyBrandingService.php` | Service | Centralized branding service managing logo, signature, and stamp storage, sanitization, cache busting, and branch scoping |
| `app/Controllers/BrandingController.php` | Controller | Centralized branding management endpoints for logo, signature, and stamp uploads, resizing, and removal |
| `views/settings/branding.php` | View | Settings > Company Branding page with three upload cards, dimension guides, previews, and toggle settings |
| `tests/Feature/CompanyBrandingTest.php` | Feature | 10 assertions: Logo/signature/stamp upload, SVG sanitization, size settings, branch overrides, cache-busting URLs |
| `database/migrations/2026_11_07_000002_create_parties_table.sql` | Migration | Schema creation for unified parties table with party_type, customer_id, supplier_id, and statutory columns |
| `app/Repositories/PartyRepository.php` | Repository | Multi-role querying, sequential code generator (PRT-XXXX), server-side filtering, and pagination |
| `app/Services/PartyService.php` | Service | Unified party business logic, dual-role synchronization, 360° profile aggregation, and statement export |
| `app/Controllers/PartyController.php` | Controller | Endpoints for unified parties list, create, profile, update, status toggle, and CSV statements |
| `views/parties/index.php` | View | Unified parties directory with role tabs (All, Customers, Suppliers, Both), search bar, and add/edit modal |
| `views/parties/profile.php` | View | 360° party profile with KPI cards, role-aware transaction tabs (Invoices, Bills, Orders), and ledger |
| `tests/Feature/UnifiedPartiesTest.php` | Feature | 15 assertions: Single parties sidebar entry, role sync, dual-role identity, filters, 360° profile, statement export |
| `docs/UNIFIED_PARTIES_MODULE.md` | Documentation | Architectural specification and implementation details for the Unified Parties Module |
| `app/Controllers/BrandingController.php` | Controller | Controller managing company branding settings view, secure file uploads, asset deletion, and document display configurations |
| `views/settings/branding.php` | View | Settings UI view with 3 upload cards (Logo, Signature, Stamp), live previews, display dimensions, and removal confirmation |
| `tests/Feature/CompanyBrandingTest.php` | Feature | 10 assertions: default branding, settings UI, PNG/SVG upload, malicious SVG rejection, disallowed extensions, display settings, multi-branch override, and asset removal |
| `docs/COMPANY_BRANDING.md` | Documentation | Comprehensive architecture manual, database specification, security rules, and integration guide for Company Branding |
| `docs/BRANDING_QA_REPORT.md` | Documentation | Complete QA test report covering all 12 verification criteria with 100% pass rate |
| `views/partials/share_modal.php` | View | Universal sharing modal with preview, WhatsApp direct attach/Cloud API/wa.me fallback, SMTP Email delivery, and audit history |
| `tests/Feature/CommunicationSharingTest.php` | Feature | 20 assertions: Settings persistence, E.164 normalization, PDF generation, invoice/quote sharing, SMTP dispatch, HMAC tokens, security controls |
| `docs/INVOICE_QUOTATION_SHARING.md` | Documentation | Architectural specification and integration guide for Invoice and Quotation WhatsApp & Email sharing |
| `docs/WHATSAPP_INTEGRATION.md` | Documentation | Meta WhatsApp Business Cloud API, Web Share API Method A, and click-to-chat fallback integration manual |
| `docs/EMAIL_INTEGRATION.md` | Documentation | Native socket SMTP client, TLS/SSL encryption, multipart/mixed MIME PDF attachment, and delivery verification manual |
| `docs/SHARING_QA_REPORT.md` | Documentation | Comprehensive QA test matrix and verification results for Communication & Sharing across 20 test assertions (100% pass) |
| `docs/DYNAMIC_SIDEBAR_IMPLEMENTATION.md` | Documentation | Architecture, single-state accordion controller, test verification results for sidebar navigation |
| `app/Services/InvoicePaymentStatusReportService.php` | Service | Comprehensive financial reconciliation service for unpaid, partially paid, and settled invoices with native OpenXML (.xlsx) generation |
| `views/reports/invoice_payment_status.php` | View | Invoice Payment Status Report with 4 KPI summary cards, multi-dimensional filters, data table, and Excel export trigger |
| `tests/Feature/InvoicePaymentStatusReportTest.php` | Feature | 15 assertions: Unpaid/partial/paid categorization, overdue detection, cancelled exclusions, multi-filter, genuine .xlsx OpenXML export |
| `docs/INVOICE_PAYMENT_STATUS_REPORT.md` | Documentation | Architectural specification, calculation rules, formula definitions, and user manual for Invoice Payment Status Report |
| `docs/INVOICE_EXCEL_EXPORT.md` | Documentation | Technical specification for genuine Office Open XML (.xlsx) workbook packaging, 9-column schema, and formula injection defense |
| `docs/INVOICE_REPORT_QA.md` | Documentation | Comprehensive QA test matrix and verification results for Invoice Payment Status Report across 15 assertions (100% pass) |
| `docs/FILE_REGISTRY.md` | Documentation | Authoritative inventory and registry of all application codebase and documentation files |
