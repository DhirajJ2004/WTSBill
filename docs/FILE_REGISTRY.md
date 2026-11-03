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
| `app/Controllers/SettingsController.php` | `WTSBill\Controllers` | General company profile, branch settings, document numbering |

---

## 2. Domain Services (`app/Services/`)

| File Path | Namespace | Role & Responsibilities |
| :--- | :--- | :--- |
| `app/Services/AuthService.php` | `WTSBill\Services` | Authentication state, Bcrypt verification, session binding |
| `app/Services/BranchService.php` | `WTSBill\Services` | Branch switching authorization, branch creation with contact person & validation, session context |
| `app/Services/PaymentService.php` | `WTSBill\Services` | Partial invoice payment calculations, multiple installments, status transitions (UNPAID/PARTIAL/PAID), customer ledger decrements, and double-entry GL journal posting |
| `app/Services/ProductService.php` | `WTSBill\Services` | Product business rules, SKU uniqueness, GST rates, stock adjustments |

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
| `views/settings/index.php` | Company & branch settings view |

---

## 7. Automated Test Suites (`tests/`)

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
| `tests/run_tests.php` | Master Runner | Executes all 44 test suites (610 assertions, 100% pass) |
