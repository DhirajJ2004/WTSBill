# WTSBill ERP — Enterprise Native PHP 8+ Architecture

Enterprise Billing, Indian GST Accounting, Multi-Tenant Inventory, and Accounts Management rebuilt in **Pure Native PHP 8+** and **MySQL/MariaDB via Native PDO prepared statements** with zero external framework runtime dependencies (100% independent from Laravel/Illuminate/Symfony).

---

## 1. Project Overview

- **Architecture:** Clean MVC + Domain Service + Repository Layer (Native PHP 8+)
- **Primary Namespace:** `WTSBill\` (PSR-4 autoloaded from `app/`)
- **Compatibility Namespace:** `App\` (aliased to `app/` for legacy interop)
- **PHP Compatibility:** PHP 8.0, 8.1, 8.2, 8.3+ (Strict typing, `declare(strict_types=1)`)
- **Database Engine:** MySQL 8.0+ / MariaDB 10.4.32+ (`wtsbill_db`)
- **Character Set & Collation:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Default Timezone & Currency:** `Asia/Kolkata` (`+05:30`), Indian Rupee (`₹` / INR)
- **Security Standard:** OWASP Top 10 compliance, Bcrypt password hashing, session fixation defense, CSRF token verification, and strict multi-tenant repository isolation (`company_id`).

---

## 2. Directory Structure

```
WTSBill/
├── app/
│   ├── Controllers/         # Application controllers (Auth, Dashboard, Invoices, etc.)
│   ├── Core/                # Foundational framework-free primitives
│   │   ├── Autoloader.php   # Standalone PSR-4 autoloader
│   │   ├── Config.php       # Thread-safe configuration manager
│   │   ├── Database.php     # Native PDO connection, statement caching & transaction manager
│   │   ├── Env.php          # Zero-dependency .env parser & loader
│   │   ├── Exceptions/      # HttpException, ValidationException, DatabaseException
│   │   ├── Logger.php       # Sanitizing PSR-3 style logger with credential redaction
│   │   ├── Request.php      # Unified HTTP request primitive (GET, POST, JSON, IP, Headers)
│   │   ├── Response.php     # HTTP response builder with security & performance headers (ETag, Server-Timing)
│   │   ├── Router.php       # Clean URL regex router with middleware pipeline support
│   │   ├── Session.php      # Secure native session & CSRF token manager
│   │   ├── Validator.php    # Server-side data validation engine
│   │   ├── View.php         # Safe template renderer with layout caching & HTML escaping
│   │   └── helpers.php      # Global utility functions (env, config, e, format_currency, number_to_words_inr)
│   ├── Middleware/          # Authentication, Authorization (RBAC), CSRF, Tenant isolation
│   ├── Models/              # Domain entity models
│   ├── Repositories/        # Tenant-scoped database query repositories
│   └── Services/            # Core business calculation & accounting services
├── config/                  # Structured configuration (app, database, session, security)
├── database/                # Schema DDL, migrations, seeders, and SQL backups
├── docs/                    # Complete architecture, regression, deployment & security documentation
│   ├── ARCHITECTURE.md
│   ├── CUTOVER_CHECKLIST.md # Controlled cutover sign-off checklist
│   ├── DEPLOYMENT.md        # Production deployment guide
│   ├── FRONTEND_ASSET_MAP.md
│   ├── PERFORMANCE_BASELINE.md
│   ├── PERFORMANCE_RESULTS.md
│   ├── REBUILD_FILE_MANIFEST.md
│   ├── REGRESSION_RESULTS.md# Full 300/300 regression test audit
│   ├── ROLLBACK_PLAN.md     # Emergency zero-downtime rollback procedures
│   └── SECURITY_AUDIT.md
├── public/                  # Web server document root (public gateway)
│   ├── assets/              # Native CSS design system, responsive JS, SVG icons
│   │   ├── css/app.css      # Semantic HSL design tokens, dark mode, @media print
│   │   └── js/app.js        # WTSBill client engine (Modals, Toasts, Dropdowns, CSRF fetch)
│   ├── .htaccess            # Apache URL rewrite, security headers, mod_deflate gzip, browser caching
│   └── index.php            # Primary front controller entry point
├── routes/
│   ├── web.php              # Web view route definitions (21 core ERP modules)
│   └── api.php              # RESTful API route definitions (/api/v1/..., /api/health)
├── storage/                 # Private runtime storage
│   ├── cache/               # Application & view path cache
│   ├── logs/                # Daily application audit logs
│   └── sessions/            # Native file session storage
├── tests/                   # 23 Unit, Feature, Integration, and Security test suites
│   ├── Feature/             # Full commercial workflow & layout tests
│   ├── Integration/         # Database, business logic, route parity & print tests
│   ├── Security/            # SQLi, XSS, CSRF, Tenant isolation, and Auth security tests
│   ├── Unit/                # Core component unit tests
│   └── run_tests.php        # Master CLI test runner (300 automated assertions)
├── .env.example             # Production environment template
├── .gitignore               # Strict git ignore rules protecting secrets & storage
├── composer.json            # PSR-4 composer manifest (optional, engine runs standalone)
└── index.php                # Root front controller proxy routing to public/index.php
```

---

## 3. Quick Start & Local Setup

### 3.1 Prerequisites
- PHP 8.0 or newer with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `ctype`.
- MySQL 8.0+ or MariaDB 10.4+.
- Apache with `mod_rewrite` enabled (or Nginx).

### 3.2 Environment Setup
1. Copy the environment configuration template:
   ```bash
   cp .env.example .env
   ```
2. Configure database credentials and environment in `.env`:
   ```ini
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=wtsbill_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 3.3 Running Locally (Built-in PHP Server)
```bash
php -S localhost:8000 public/index.php
```
Access the application at `http://localhost:8000`.

### 3.4 Running on XAMPP / Apache
1. Clone or place the project in `C:/xampp/htdocs/WTSBill`.
2. Access `http://localhost/WTSBill/` directly (the root `index.php` routes to `public/index.php`).
3. For production, point your Apache `DocumentRoot` directly to `C:/xampp/htdocs/WTSBill/public`.

---

## 4. Automated Test Suite

The project includes an integrated automated test suite that runs directly through the PHP CLI with **zero third-party testing dependencies**:

```bash
php tests/run_tests.php
```

### Test Suite Coverage (300 Assertions, 100% Pass Rate):
1. **Core Architecture:** Router, Request, Response, Config, Env, Autoloader (Sections 1–4).
2. **Database & Transactions:** Live PDO connection, charset, ACID transaction commit & rollback, BaseRepository (Sections 5–7).
3. **Authentication & RBAC:** Multi-tenant login, Bcrypt hashing, role matrix, session fixation defense (Sections 8–11).
4. **Security Audits:** CSRF token verification, SQL injection parameterized queries, XSS escaping, Tenant boundary isolation (Sections 12–15).
5. **Frontend & Layouts:** Master shells, navigation links, toast alerts, modal dialogs, HSL CSS tokens, client JS (Sections 16–17).
6. **Performance & Caching:** View path cache, PDO statement cache, ETag 304 match, Server-Timing, gzip compression (Section 18).
7. **Database Integrity:** Schema table verification (>=130 tables), tenant columns, orphan prevention, unique numbering (Section 19).
8. **Business Logic Parity:** Intra-state GST (9%+9%), Inter-state IGST (18%), discount precision, half-up rupee round-off, stock balances, double-entry ledger balance, Balance Sheet equality (Section 20).
9. **Route & HTTP Parity:** 21 authenticated ERP modules, unauthenticated redirect, 404 handler, API health status (Section 21).
10. **Print & Document Output:** High-contrast `@media print` rules, element stripping, Indian number words (`number_to_words_inr`), currency formatting (Section 22).
11. **Full Workflow Regression:** End-to-end commercial cycle (Auth -> Customer -> Product -> Invoice -> Numbering -> Journals -> P&L -> Logout) (Section 23).

---

## 5. Production Cutover & Deployment

- Refer to [`docs/DEPLOYMENT.md`](file:///c:/xampp/htdocs/WTSBill/docs/DEPLOYMENT.md) for web server vhost configurations (Apache / Nginx), OPcache tuning, and directory permissions.
- Refer to [`docs/CUTOVER_CHECKLIST.md`](file:///c:/xampp/htdocs/WTSBill/docs/CUTOVER_CHECKLIST.md) for the verified step-by-step production cutover procedure.
- Refer to [`docs/ROLLBACK_PLAN.md`](file:///c:/xampp/htdocs/WTSBill/docs/ROLLBACK_PLAN.md) for emergency rollback steps and database restoration.
- Refer to [`docs/REGRESSION_RESULTS.md`](file:///c:/xampp/htdocs/WTSBill/docs/REGRESSION_RESULTS.md) for the certified regression audit sign-off.

---

## 6. Default Demo Credentials

For development and verification testing:
- **Admin User:** `anil.d@wtsbill.in` / `password123` (Role: `ADMIN`, Company ID: `1`)
- **Accountant User:** `accountant@wtsbill.in` / `password123` (Role: `ACCOUNTANT`, Company ID: `1`)

---

## 7. License & Credits

Built for enterprise billing and Indian tax compliance. All rights reserved.
