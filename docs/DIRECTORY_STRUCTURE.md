# WTSBill ERP — Directory Structure & Conventions

**Document Version:** 1.0.0  
**Generated Date:** 2026-10-10  
**Namespace Mapping:** `WTSBill\` $\rightarrow$ `app/`  

---

## 1. Directory Tree & Purpose

```
WTSBill/
├── app/
│   ├── Controllers/             # Request handlers; orchestrate services and return views/JSON
│   ├── Core/                    # Framework-independent runtime engine
│   │   ├── Autoloader.php       # PSR-4 native class loader
│   │   ├── Config.php           # Dot-notation config reader
│   │   ├── Database.php         # PDO connection & ACID transaction manager
│   │   ├── Env.php              # Environment variable parser
│   │   ├── Exceptions/          # HTTP & validation exception classes
│   │   │   ├── HttpException.php
│   │   │   └── ValidationException.php
│   │   ├── Logger.php           # Sanitizing log writer
│   │   ├── Request.php          # HTTP request abstraction
│   │   ├── Response.php         # HTTP response abstraction & security headers
│   │   ├── Router.php           # Regex route compiler & dispatcher
│   │   ├── Session.php          # Session & CSRF manager
│   │   ├── View.php             # Safe template renderer with layout support
│   │   └── helpers.php          # Global utility functions (env, config, e, format_*)
│   ├── Helpers/                 # Module-specific helper functions
│   ├── Middleware/              # HTTP middleware guards (Auth, CSRF, RBAC, TenantScope)
│   ├── Models/                  # Pure domain entities and data transfer objects
│   ├── Repositories/            # Data access layer using PDO prepared statements
│   ├── Services/                # Pure business logic, calculations, and invariants
│   ├── Support/                 # General utility classes (Str, Arr, Math, DateTime)
│   └── Validators/              # Dedicated input validation rules
│
├── config/                      # Configuration files (all read from environment variables)
│   ├── app.php                  # Application name, timezone, debug mode, keys
│   ├── database.php             # MySQL/MariaDB connection settings
│   ├── security.php             # CORS, CSRF, bcrypt, and header policies
│   └── session.php              # Session cookie lifetime, path, and security settings
│
├── database/                    # Database asset management
│   ├── migrations/              # Incremental schema evolution scripts
│   ├── schema/                  # Clean SQL schema definitions
│   ├── seeders/                 # Default master data seeders (taxes, units, chart of accounts)
│   └── sql/                     # Raw database migration and restore scripts
│
├── docs/                        # Architecture, discovery, and rebuild documentation
│   ├── ARCHITECTURE.md          # Core architecture specifications
│   ├── DIRECTORY_STRUCTURE.md   # This document
│   ├── REBUILD_BASELINE.md      # Baseline behavior report of all 12 modules
│   ├── REBUILD_BUSINESS_LOGIC_MAP.md # GST, double-entry, and stock formulas
│   ├── REBUILD_DATABASE_INVENTORY.md # Audit of all 161 database tables
│   ├── REBUILD_DEPENDENCY_MAP.md# Runtime dependencies & security boundary
│   ├── REBUILD_FILE_MANIFEST.md # Master file registry & chunk execution log
│   └── REBUILD_ROUTE_INVENTORY.md # Detailed route catalog
│
├── public/                      # Web root; accessible to the web server
│   ├── assets/                  # Public static assets
│   │   ├── css/                 # Stylesheets
│   │   ├── fonts/               # Web fonts
│   │   ├── images/              # Logos, icons, UI graphics
│   │   ├── js/                  # JavaScript files
│   │   └── vendor/              # Third-party frontend libraries
│   ├── uploads/                 # User-uploaded invoices, receipts, and attachments
│   ├── .htaccess                # Apache URL rewriting rules
│   └── index.php                # Public front controller / HTTP entry point
│
├── routes/                      # Route declarations
│   ├── api.php                  # Versioned REST API endpoints
│   └── web.php                  # Web application UI routes
│
├── storage/                     # Ephemeral file storage (gitignored)
│   ├── cache/                   # Configuration & query cache files
│   ├── logs/                    # Sanitized daily application log files
│   └── sessions/                # Native session payload files
│
├── tests/                       # Automated test suites
│   ├── Feature/                 # End-to-end feature workflow tests
│   ├── Integration/             # System integration tests
│   │   └── ProjectBootstrapTest.php
│   ├── Security/                # Security penetration & injection regression tests
│   ├── Unit/                    # Isolated component unit tests
│   │   ├── CoreRequestTest.php
│   │   ├── CoreResponseTest.php
│   │   └── CoreRouterTest.php
│   └── run_tests.php            # Standalone CLI test runner
│
├── scripts/                     # Operational maintenance and test scripts
│   ├── run_chunk1_test.php      # Chunk 1 baseline verification test suite
│   └── .gitkeep
│
├── .env                         # Active local environment variables
├── .env.example                 # Environment variables template
├── .gitignore                   # Git exclusion rules
├── composer.json                # Composer manifest with PSR-4 autoloading
├── index.php                    # Root forwarder to public/index.php
└── README.md                    # Project overview, setup, and testing instructions
```

---

## 2. Architectural Conventions

1. **Namespace Structure:** Every class inside `app/` belongs to the `WTSBill\` root namespace (e.g., `WTSBill\Controllers\InvoiceController`, `WTSBill\Services\TaxService`).
2. **Backward Compatibility:** All foundational classes in `WTSBill\Core` maintain an identical `class_alias` to `App\Core` so existing test scripts continue to run seamlessly.
3. **No Empty Implementations:** Subdirectories in `app/` (`Controllers`, `Models`, `Services`, `Repositories`) remain clean and unpopulated until their respective business module chunks are executed.
4. **Environment Isolation:** Secrets, API credentials, and database passwords must never be stored in code. They are configured in `.env` and read via `env()`.
