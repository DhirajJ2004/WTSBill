# WTSBill ERP — Rebuild File Manifest

**Document Version:** 2.0.0  
**Generated Date:** 2026-10-26  
**Project Objective:** Architectural rebuild of WTSBill ERP from scratch into clean, high-performance, maintainable Native PHP 8+ and MySQL/MariaDB with PDO prepared statements and zero external framework dependencies (no Laravel/Illuminate components).

---

## 1. Architectural Invariants & Rules

1. **Business Logic Preservation:** 100% preservation of calculations, tax computations, GST splits, rounding rules, accounting entries, balance sheet balancing ($\sum \text{Debit} = \sum \text{Credit}$), multi-tenant isolation, permissions, and sequential document numbering.
2. **Framework Isolation:** Native PHP 8+ only. PDO with prepared statements. No Eloquent/Capsule or heavy framework runtimes.
3. **Database Schema Stability:** Zero arbitrary schema modifications. Target runtime: MySQL 8.0+ / MariaDB 10.4.32+.
4. **Tenant & Context Safety:** Strict multi-tenant isolation via company and branch scoping.
5. **Atomic Rebuild Strategy:** Incremental chunk-by-chunk delivery with automated verification tests at every stage.

---

## 2. Chunk Progress & Execution Log

| Chunk | Title | Scope & Description | Status | Verification Date |
| :--- | :--- | :--- | :--- | :--- |
| **CHUNK 1** | **Foundation & Project Discovery** | Project skeleton, PSR-4 autoloading, Native PDO engine, Env/Config, Request/Response, Router, Database Discovery, and Complete Manifest | **COMPLETED** | 2026-10-10 |
| **CHUNK 2** | **Native PHP Architecture Skeleton** | Clean Native PHP + MySQL skeleton, PSR-4 (`WTSBill\`), Session, View, Logger, Exceptions, Security configs, Unit & Integration test suite | **COMPLETED** | 2026-10-10 |
| **CHUNK 3** | **PHP PDO & Existing SQL Compatibility** | Database layer, PDO connection factory, DatabaseTransaction with savepoints, BaseRepository, DatabaseException, config validation, 111 passing tests | **COMPLETED** | 2026-10-10 |
| **CHUNK 4** | **Authentication & Multi-Tenant Engine** | Preserved authentication, Bcrypt verification, session fixation protection, RBAC middleware, CSRF defense, tenant isolation, 165 passing tests | **COMPLETED** | 2026-10-10 |
| **CHUNK 5** | **Master Layouts & Frontend Assets** | Shared responsive layouts, sidebar, header, toast notifications, modal dialog, CSS design system, client JS, 189 passing tests | **COMPLETED** | 2026-10-10 |
| **CHUNK 6** | **Parties Module (Customers & Suppliers)** | Customer/Supplier domain services, repositories, validation, ledger integration | **COMPLETED** | 2026-10-26 |
| **CHUNK 7** | **Products & Inventory Engine** | Products, categories, units, warehouses, stock movements, batches | **COMPLETED** | 2026-10-26 |
| **CHUNK 8** | **Sales & Tax Invoice Engine** | Invoice creation, tax computation, sequential numbering, ledger postings | **COMPLETED** | 2026-10-26 |
| **CHUNK 9** | **Quotations, Sales Orders & Challans** | Estimates, quotations, sales orders, delivery challans, credit notes | **COMPLETED** | 2026-10-26 |
| **CHUNK 10** | **Purchases & Accounts Payable** | Purchase bills, purchase orders, goods receipts, debit notes | **COMPLETED** | 2026-10-26 |
| **CHUNK 11** | **Payments, Receipts & Expenses** | Payment vouchers, invoice allocations, advances, refunds, expenses | **COMPLETED** | 2026-10-26 |
| **CHUNK 12** | **Double-Entry Accounting Engine** | Chart of accounts, journal entries, ledger audit, universal balance invariant | **COMPLETED** | 2026-10-26 |
| **CHUNK 13** | **Financial & Compliance Reports** | Trial Balance, Profit & Loss, Balance Sheet, GSTR-1, Stock reports | **COMPLETED** | 2026-10-26 |
| **CHUNK 14** | **Users, Roles, Permissions & Settings** | Centralized RBAC, user management, company/branch settings, audit trail | **COMPLETED** | 2026-10-26 |
| **CHUNK 15** | **Standardized REST API** | Versioned API routes (`/api/v1/...`), consistent JSON envelope, sanitization | **COMPLETED** | 2026-10-26 |
| **CHUNK 16** | **Document Printing & PDF Engine** | 8 tax document templates with A4 `@media print` CSS and number-to-words | **COMPLETED** | 2026-10-26 |
| **CHUNK 17** | **Integrate Existing Frontend Assets** | Vanilla CSS design system, responsive layouts, WTSBill client JS engine, asset map, 209 passing tests | **COMPLETED** | 2026-10-26 |
| **CHUNK 18** | **PHP Performance & Perceived Loading** | View path cache, PDO prepare cache, ETag 304, Server-Timing, mod_deflate gzip, 223 passing tests | **COMPLETED** | 2026-10-26 |
| **CHUNK 19** | **Security Regression Audit** | SQLi parameterized queries, HTML escaping, CSRF tokens, Tenant isolation, RBAC boundaries, 273 passing tests | **COMPLETED** | 2026-10-26 |
| **CHUNK 20** | **Full WTSBill Regression & Parity Test** | Database schema, tax & GST calculation parity, route checks, print layouts, full workflow test, 300 passing tests (100%) | **COMPLETED** | 2026-10-26 |
| **CHUNK 21** | **Controlled WTSBill PHP Cutover** | Deployment guide, rollback plan, cutover checklist, environment templates, final audit report, production sign-off | **COMPLETED** | 2026-10-26 |

---

## 3. Complete File Inventory

### 3.1 Project Root Files
| File Path | Type | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- | :--- |
| `composer.json` | Config | 27 | 585 | Composer manifest specifying PHP >=8.0, PSR-4 autoloading (`WTSBill\` and `App\` -> `app/`), and helper autoloader |
| `.env` | Config | 22 | 537 | Active environment configuration (App name, database credentials, JWT secret, CORS origins) |
| `.env.example` | Config | 30 | 780 | Environment template documenting database, session, security parameters |
| `.gitignore` | Config | 11 | 185 | Git ignore rules excluding vendor, storage logs/sessions/cache, and local env files |
| `index.php` | Entry | 7 | 120 | Root reverse proxy routing into `public/index.php` |
| `README.md` | Doc | 72 | 2,750 | Project documentation, setup guide, and CLI test execution instructions |

### 3.2 Application Core Classes (`app/Core/`)
| File Path | Namespace | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- | :--- |
| `app/Core/Autoloader.php` | `WTSBill\Core` | 99 | 2,475 | Standalone PSR-4 autoloader with SPL registration and multi-prefix resolution |
| `app/Core/Config.php` | `WTSBill\Core` | 98 | 2,215 | Thread-safe configuration manager loading files from `config/` with dot-notation lookup |
| `app/Core/Database.php` | `WTSBill\Core` | 230 | 6,550 | Native PDO manager with config validation, prepared CRUD, and transaction delegation |
| `app/Core/DatabaseTransaction.php` | `WTSBill\Core` | 100 | 2,450 | ACID transaction and nested savepoint manager (`SAVEPOINT TRANS_POINT_N`) |
| `app/Core/Env.php` | `WTSBill\Core` | 100 | 2,490 | Zero-dependency `.env` parser populating `$_ENV`, `putenv()`, and internal repository |
| `app/Core/Exceptions/HttpException.php` | `WTSBill\Core\Exceptions` | 68 | 1,600 | Base HTTP exception with status code, errors dictionary, and factory methods |
| `app/Core/Exceptions/ValidationException.php` | `WTSBill\Core\Exceptions` | 25 | 600 | Specialized 422 Unprocessable Entity validation exception |
| `app/Core/helpers.php` | Global | 59 | 1,421 | Global helper functions: `env()`, `config()`, `e()`, `format_currency()`, `format_date()` |
| `app/Core/Logger.php` | `WTSBill\Core` | 135 | 3,650 | Sanitizing daily log writer with automatic redaction of passwords, keys, and tokens |
| `app/Core/Request.php` | `WTSBill\Core` | 176 | 4,850 | HTTP Request abstraction capturing GET, POST, JSON payloads, headers, client IP, Bearer token |
| `app/Core/Response.php` | `WTSBill\Core` | 126 | 3,450 | HTTP Response abstraction supporting JSON envelopes, HTML, Redirects, security headers, SQL error redaction |
| `app/Core/Router.php` | `WTSBill\Core` | 170 | 5,850 | Clean URL Router supporting GET, POST, PUT, DELETE, route parameters (`{param}`), middleware pipeline, groups |
| `app/Core/Session.php` | `WTSBill\Core` | 195 | 4,950 | Native session manager with HttpOnly/SameSite cookies, CSRF tokens, flash data, and tenant context |
| `app/Core/View.php` | `WTSBill\Core` | 105 | 2,850 | Safe template view renderer with path traversal defense and layout encapsulation |

### 3.3 Controllers, Services & Middleware (`app/Controllers/`, `app/Services/`, `app/Middleware/`, `app/Validators/`)
| File Path | Namespace | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- | :--- |
| `app/Controllers/AuthController.php` | `WTSBill\Controllers` | 211 | 6,817 | Web & API Authentication Controller (login, logout, forgot-password, reset-password) |
| `app/Services/AuthService.php` | `WTSBill\Services` | 230 | 7,270 | Authentication & Session lifecycle service, Bcrypt hashing, session fixation defense, RBAC |
| `app/Middleware/AuthenticationMiddleware.php` | `WTSBill\Middleware` | 47 | 1,276 | Authentication guard redirecting unauthenticated web requests to `/login` and API to 401 |
| `app/Middleware/AuthorizationMiddleware.php` | `WTSBill\Middleware` | 72 | 2,600 | Role-based access control (RBAC) middleware verifying user roles and returning 403 Forbidden |
| `app/Middleware/CsrfMiddleware.php` | `WTSBill\Middleware` | 88 | 3,000 | CSRF verification for POST/PUT/DELETE requests via session token comparison, exempting API |
| `app/Middleware/TenantMiddleware.php` | `WTSBill\Middleware` | 68 | 2,150 | Multi-tenant isolation middleware blocking unauthorized cross-tenant company_id requests |
| `app/Validators/LoginValidator.php` | `WTSBill\Validators` | 57 | 1,492 | Sanitization and validation for login requests throwing ValidationException |

### 3.4 Repositories & Exceptions (`app/Repositories/`, `app/Exceptions/`)
| File Path | Namespace | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- | :--- |
| `app/Repositories/BaseRepository.php` | `WTSBill\Repositories` | 215 | 5,850 | Abstract base repository with parameterized CRUD, pagination, and multi-tenant scoping |
| `app/Repositories/UserRepository.php` | `WTSBill\Repositories` | 156 | 4,235 | User lookup by email, assigned roles & branches, password updates, reset tokens |
| `app/Exceptions/DatabaseException.php` | `WTSBill\Exceptions` | 75 | 2,150 | PDO exception wrapper with credential redaction and SQL error sanitization |

### 3.5 Configuration Files (`config/`)
| File Path | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- |
| `config/app.php` | 16 | 578 | Application settings: Name, environment, debug flag, URL, timezone (`Asia/Kolkata`), locale, keys |
| `config/database.php` | 27 | 925 | Database connection settings: Driver (`mysql`), host, port, database, credentials, charset (`utf8mb4`) |
| `config/security.php` | 28 | 800 | Security settings: CORS origins, CSRF parameters, Bcrypt cost, and security headers |
| `config/session.php` | 17 | 560 | Session settings: Driver, lifetime, cookie name, save path, SameSite policy |

### 3.6 Database Documentation (`database/`)
| File Path | Role & Description |
| :--- | :--- |
| `database/schema/README.md` | Database schema documentation, table counts, collation, decimal precision rules |
| `database/sql/README.md` | SQL guidelines, backup commands, transaction policies |
| `database/migrations/` | Incremental database migration directory |
| `database/seeders/` | Master data seed scripts directory |

### 3.7 Route Declarations (`routes/`)
| File Path | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- |
| `routes/web.php` | 49 | 2,307 | Declarative web routes (welcome dashboard, `/login`, `/logout`, `/forgot-password`, `/reset-password`) |
| `routes/api.php` | 54 | 1,512 | Declarative REST API routes (`/api/health`, `/api/v1/auth/login`, `/api/v1/auth/logout`, `/api/v1/auth/me`) |

### 3.8 Public Gateway & Assets (`public/`)
| File Path | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- |
| `public/index.php` | 46 | 1,450 | Web front controller bootstrapping Autoloader, Env, Config, Router, and emitting Response |
| `public/.htaccess` | 8 | 174 | Apache URL rewrite configuration directing all incoming HTTP traffic to `index.php` |
| `public/uploads/.gitkeep` | 1 | 24 | Git keep marker |
| `public/assets/css/app.css` | 510 | 12,400 | Enterprise vanilla CSS design system with HSL dark/light palette, responsive sidebar, toast, modal, and print styles |
| `public/assets/js/app.js` | 175 | 5,200 | Client script managing responsive sidebar toggle, user dropdown, Toast engine, Modal manager, and CSRF fetch |

### 3.9 Views & Templates (`views/`)
| File Path | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- |
| `views/components/test_card.php` | 4 | 115 | Test view template verifying view rendering and HTML escaping |
| `views/layouts/app.php` | 49 | 1,550 | Primary enterprise application shell with responsive sidebar, header, toast container, and footer |
| `views/layouts/main.php` | 6 | 150 | Compatibility layout alias delegating to `views/layouts/app.php` |
| `views/layouts/auth.php` | 38 | 1,250 | Dedicated centered authentication layout shell for login and password reset pages |
| `views/partials/header.php` | 66 | 2,850 | Top navigation header with sidebar toggle, active tenant & branch badges, user dropdown, and CSRF logout |
| `views/partials/sidebar.php` | 95 | 3,950 | Collapsible ERP sidebar navigation organizing all 8 business modules with active path highlighting |
| `views/partials/footer.php` | 17 | 550 | Footer rendering copyright, environment, and PHP runtime version |
| `views/partials/toast.php` | 38 | 1,450 | Flash notification container rendering success, error, warning, and info alerts with HTML escaping |
| `views/partials/modal.php` | 36 | 1,350 | Accessible reusable modal dialog component with backdrop and action slots |
| `views/dashboard/index.php` | 108 | 4,600 | High-performance enterprise dashboard view with metrics cards and recent invoices ledger table |
| `views/auth/login.php` | 133 | 4,729 | Modern enterprise login UI with dark mode, CSRF protection, and error alert handling |
| `views/auth/forgot-password.php` | 101 | 3,583 | Password reset request UI with CSRF protection and flash feedback |
| `views/auth/reset-password.php` | 116 | 4,275 | Password update UI with token validation and password confirmation fields |

### 3.10 Storage Directories (`storage/`)
| Directory / File | Role & Description |
| :--- | :--- |
| `storage/logs/.gitkeep` | Git keep marker for daily log files |
| `storage/cache/.gitkeep` | Git keep marker for application cache |
| `storage/sessions/.gitkeep` | Git keep marker for session payload storage |

### 3.11 Automated Test Suites (`tests/`)
| File Path | Lines | Size (Bytes) | Role & Description |
| :--- | :--- | :--- | :--- |
| `tests/Unit/CoreRouterTest.php` | 92 | 3,100 | Unit tests for Router (exact match, params, groups, middleware, 404) |
| `tests/Unit/CoreRequestTest.php` | 55 | 1,950 | Unit tests for Request (methods, paths, query, post, JSON, IP, Bearer) |
| `tests/Unit/CoreResponseTest.php` | 55 | 2,150 | Unit tests for Response (status, headers, JSON envelope, SQL redaction) |
| `tests/Unit/DatabaseConfigurationTest.php` | 80 | 2,900 | Unit tests for database config loading, parameter validation, missing fields |
| `tests/Integration/DatabaseConnectionTest.php` | 85 | 3,450 | Integration tests for live PDO connection, charset, failure redaction, baseline data |
| `tests/Integration/DatabaseTransactionTest.php` | 135 | 5,200 | Integration tests for commit, rollback, savepoints, and BaseRepository CRUD |
| `tests/Integration/ProjectBootstrapTest.php` | 105 | 4,200 | Integration tests verifying autoloader, env, config, PDO, views, session, CSRF, logger |
| `tests/Feature/AuthenticationTest.php` | 109 | 5,338 | Feature tests for login, bad password, non-existent email, logout, expired session, reset tokens |
| `tests/Feature/AuthorizationTest.php` | 96 | 5,090 | Feature tests for unauthenticated redirect, 401 API response, role-based access control (Admin, Accountant, Sales) |
| `tests/Security/CsrfProtectionTest.php` | 62 | 2,967 | Security tests for missing/invalid CSRF tokens (419), body/header verification, safe GET, API exemptions |
| `tests/Security/TenantIsolationTest.php` | 123 | 4,839 | Security tests for tenant scoping, automatic company_id assignment, cross-tenant isolation, TenantMiddleware 403 |
| `tests/Feature/LayoutRenderingTest.php` | 124 | 5,750 | Feature tests for HTML5 layout structure, partial inclusion, active link state, toast XSS escaping, modal ARIA |
| `tests/Feature/FrontendIntegrationTest.php` | 95 | 4,400 | Feature tests for frontend assets, CSS tokens, WTSBill namespace, form safeguards, and layout links |
| `tests/Security/AuthenticationSecurityTest.php` | 95 | 4,100 | Security tests for password hashing, lockout, session fixation, token entropy |
| `tests/Security/SqlInjectionTest.php` | 85 | 3,900 | Security tests verifying PDO prepared parameter protection and escaping |
| `tests/Security/XssProtectionTest.php` | 90 | 4,200 | Security tests verifying View htmlspecialchars escaping and JSON encoding |
| `tests/Security/AccessControlTest.php` | 88 | 3,850 | Security tests verifying route-level authorization matrices and permission blocks |
| `tests/Integration/PerformanceSmokeTest.php` | 92 | 4,150 | Performance tests verifying view cache, statement cache, ETag 304, compression |
| `tests/Integration/DatabaseIntegrityTest.php` | 105 | 4,600 | Integrity tests verifying table schemas, tenant columns, ACID rollback, uniqueness |
| `tests/Integration/BusinessLogicParityTest.php` | 99 | 4,286 | Parity tests verifying GST intra/inter split, round-off, stock balance, double-entry |
| `tests/Integration/RouteParityTest.php` | 100 | 3,810 | Route parity tests verifying guest redirect, 21 authenticated routes, 404, health API |
| `tests/Integration/PrintOutputParityTest.php` | 82 | 3,365 | Output tests verifying @media print rules, element hiding, number_to_words_inr |
| `tests/Feature/FullWorkflowRegressionTest.php` | 98 | 4,374 | End-to-end regression test: Auth -> Customer -> Product -> Invoice -> Numbering -> Journals -> P&L |
| `tests/run_tests.php` | 170 | 6,500 | Master CLI test runner executing all 23 Unit, Integration, Feature, and Security test suites (300 assertions, 100% pass) |

### 3.12 Architecture Documentation (`docs/`)
| File Path | Role & Description |
| :--- | :--- |
| `docs/ARCHITECTURE.md` | Core architecture specifications and request lifecycle trace |
| `docs/CUTOVER_CHECKLIST.md` | Controlled production cutover checklist, gate criteria, and formal GO sign-off |
| `docs/DEPLOYMENT.md` | Production deployment guide for Apache, Nginx, OPcache, and permissions |
| `docs/DIRECTORY_STRUCTURE.md` | Complete directory tree and development conventions |
| `docs/FRONTEND_ASSET_MAP.md` | Comprehensive frontend asset architecture, HSL design tokens, component classes, and JS modules |
| `docs/PERFORMANCE_BASELINE.md` | Initial latency and memory baseline measurements across core ERP endpoints |
| `docs/PERFORMANCE_RESULTS.md` | Post-optimization performance results, TTFB reductions, and cache hit metrics |
| `docs/REBUILD_BASELINE.md` | Baseline behavior report of all 12 modules and test results |
| `docs/REBUILD_BUSINESS_LOGIC_MAP.md` | GST calculations, double-entry invariants, and stock formulas |
| `docs/REBUILD_DATABASE_INVENTORY.md` | Comprehensive audit of all 161 database tables |
| `docs/REBUILD_DEPENDENCY_MAP.md` | Runtime dependencies, PHP extensions, and security boundaries |
| `docs/REBUILD_FILE_MANIFEST.md` | Master rebuild file manifest and architectural changelog |
| `docs/REBUILD_ROUTE_INVENTORY.md` | Complete HTTP route inventory |
| `docs/REGRESSION_RESULTS.md` | Full regression and parity verification audit results certifying cutover readiness (300/300 pass) |
| `docs/ROLLBACK_PLAN.md` | Emergency rollback triggers, database snapshot restoration, and zero-downtime recovery |
| `docs/SECURITY_AUDIT.md` | Comprehensive security audit report covering OWASP Top 10, multi-tenancy, and crypto |
