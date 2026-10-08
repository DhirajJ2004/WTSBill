# WTSBill ERP — Directory Structure Specification

```
WTSBill/
│
├── public/                             # Public Web Document Root
│   ├── index.php                       # Public entrypoint proxy
│   ├── assets/                         # Static Frontend Assets
│   │   ├── css/                        # Responsive CSS tokens and stylesheets
│   │   ├── js/                         # Vanilla JS framework (InstantNav, Theme, Toast)
│   │   ├── images/                     # Logos, icons, and illustrations
│   │   ├── fonts/                      # Offline/local typography assets
│   │   └── vendor/                     # Third-party frontend libraries
│   └── uploads/                        # User-uploaded documents and bill attachments
│
├── app/                                # Core Application Namespace (App\*)
│   ├── Controllers/                    # HTTP Request Controllers
│   ├── Models/                         # Eloquent ORM Domain Entities
│   ├── Services/                       # Business Logic & Financial Calculations
│   ├── Repositories/                   # Persistence Abstraction Layer
│   ├── Middleware/                     # Auth, CSRF & Tenant Boundary Guards
│   ├── Validators/                     # Server-side Input Validation
│   ├── Helpers/                        # Centralized Global Helper Functions
│   └── Support/                        # Database Connection & Error Redaction Engines
│
├── config/                             # Centralized Environment Configurations
│   ├── app.php                         # Application name, debug mode, timezone
│   ├── database.php                    # PDO / MySQL connection options
│   ├── auth.php                        # Session lifetime and password policies
│   └── permissions.php                 # Standard RBAC role-permission matrix
│
├── routes/                             # Route Definitions
│   ├── web.php                         # SSR Clean Web View Routes
│   └── api.php                         # Versioned REST API Endpoints (/api/v1/...)
│
├── views/                              # Server-Side Rendered Presentation Templates
│   ├── layouts/                        # Header, Navbar, Sidebar, Footer, Print Controls
│   ├── components/                     # Reusable UI partials & widgets
│   ├── auth/                           # Login, Forgot Password, Select Company
│   ├── dashboard/                      # KPI metric tiles & chart widgets
│   ├── sales/                          # Invoices, Quotations, Credit Notes, Challans
│   ├── purchases/                      # Purchase Bills, Debit Notes, Goods Receipts
│   ├── inventory/                      # Stock register, Warehouses, Adjustments
│   ├── parties/                        # Customers & Suppliers directories
│   ├── payments/                       # Payment register & Receipt vouchers
│   ├── expenses/                       # Expense vouchers & category managers
│   ├── accounting/                     # Chart of Accounts, Journals, General Ledger
│   ├── reports/                        # Trial Balance, P&L, Balance Sheet, GSTR-1
│   ├── settings/                       # Company profile, GST config, Preferences
│   └── users/                          # User accounts & RBAC assignments
│
├── database/                           # Database Layer
│   ├── migrations/                     # Versioned schema migrations
│   ├── seeders/                        # SystemSeeder & DemoSeeder
│   ├── schema/                         # Master schema.sql & metadata catalog
│   └── sql/                            # Cold backups & historical SQL dumps
│
├── storage/                            # Runtime Storage
│   ├── logs/                           # Application & security error logs
│   ├── cache/                          # Template & calculation cache
│   └── sessions/                       # Session storage files
│
├── tests/                              # Automated Verification Suites
│   ├── Unit/                           # Tax, currency, and calculation unit tests
│   ├── Feature/                        # Complete invoice, purchase, and stock lifecycles
│   ├── Integration/                    # Multi-tenant isolation & double-entry checks
│   └── Security/                       # SQLi, XSS, CSRF, and IDOR fuzzers
│
├── scripts/                            # Operational CLI Scripts & Test Runners
├── docs/                               # Architecture, Directory, Database & Route Specs
├── _archive/                           # Quarantined & Archived Review Files
│   └── review/                         # Manual debug scripts and review manifests
├── composer.json                       # PHP Dependencies & PSR-4 Autoloading
├── .env.example                        # Safe environment template
├── .gitignore                          # Git tracking exclusion rules
└── README.md                           # Master project documentation
```
