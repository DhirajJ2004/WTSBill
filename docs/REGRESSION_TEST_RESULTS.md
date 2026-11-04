# WTSBill ERP — Regression Test Results Log

**Document Version:** 4.5.0  
**Last Verified:** 2026-11-04  
**Test Runner:** `tests/run_tests.php`  
**Execution Environment:** Native PHP 8.0 CLI + MariaDB 10.4.32 (`wtsbill_db`)

---

## 1. Test Suite Summary

```
==================================================================================
                         TEST VERIFICATION SUMMARY                                
==================================================================================
Total Assertions: 649
Passed:           649
Failed:           0
Pass Percentage:  100%

STATUS: ALL TEST SUITES PASSED (100%)
```

---

## 2. Test Execution Details by Section

| Section | Test Suite Name | Assertions | Status | Highlights |
| :---: | :--- | :---: | :---: | :--- |
| **01** | Core Router Unit Test | 18 | **PASS** | Exact match, regex params, middleware pipeline, 404 handler |
| **02** | Core Request Unit Test | 14 | **PASS** | Method detection, JSON parsing, bearer tokens, client IP |
| **03** | Core Response Unit Test | 14 | **PASS** | JSON envelopes, security headers, SQL error message redaction |
| **04** | Database Configuration Test | 18 | **PASS** | Env parameter validation, default connection loading |
| **05** | Database Connection Test | 21 | **PASS** | Live PDO connection, UTF-8 charset, credential redaction |
| **06** | Database Transaction Test | 12 | **PASS** | ACID commit, rollback safety, transaction savepoints |
| **07** | Project Bootstrap Test | 14 | **PASS** | Autoloader, config, session, and helper functions |
| **08** | Authentication Feature Test | 18 | **PASS** | Login, invalid password, logout, session expiration |
| **09** | Authorization Feature Test | 18 | **PASS** | Role-based access control (Admin, Accountant, Operator) |
| **10** | CSRF Protection Security Test | 10 | **PASS** | CSRF token validation, header extraction, 419 error response |
| **11** | Tenant Isolation Security Test | 8 | **PASS** | Multi-tenant scoping, automatic company_id, cross-tenant block |
| **12** | Authentication Security Audit | 8 | **PASS** | Bcrypt cost verification, timing attack mitigation |
| **13** | SQL Injection Defense Audit | 8 | **PASS** | PDO prepared statement parameterization, SQLi payloads blocked |
| **14** | XSS Protection Security Audit | 8 | **PASS** | HTML escaping via htmlspecialchars, JSON escaping |
| **15** | Access Control Matrix Audit | 8 | **PASS** | Route-level permission boundaries |
| **16** | Layout Rendering Feature Test | 24 | **PASS** | Master app shell, sidebar navigation, toast, modal partials |
| **17** | Frontend Integration Test | 30 | **PASS** | HSL CSS tokens, WTSBill client JS, form submission guards |
| **18** | Performance Smoke Test | 14 | **PASS** | View cache, statement cache, ETag 304, gzip |
| **19** | Database Integrity Test | 5 | **PASS** | 130+ tables verified, tenant columns, ACID rollback, unique numbers |
| **20** | Business Logic Parity Test | 7 | **PASS** | GST 50/50 splits, 18% IGST, round-off, stock equation, double-entry |
| **21** | Route & Method Parity Test | 4 | **PASS** | 21 routes return 200 OK, guest 302 redirect, clean 404, health API |
| **22** | Print Output Parity Test | 7 | **PASS** | @media print high contrast, number_to_words_inr Indian format |
| **23** | Full Workflow Regression Test | 8 | **PASS** | End-to-end commercial ERP cycle simulation |
| **24** | Product & Inventory Management | 8 | **PASS** | Modal rendering, validation 422, duplicate SKU block, persistence, edit |
| **25** | Global Placeholder Popup Fix | 21 | **PASS** | Zero alert() dialogs, 16 real modals, multi-module persistence |
| **26** | Branch Management & Switching | 33 | **PASS** | Header branch selector, session context, + Add Branch modal, DB persistence, validation |
| **27** | Balance Sheet Statement | 16 | **PASS** | Accountant-grade balance sheet, assets == liabilities + equity, exports |
| **28** | Inventory Stock & Warehouses | 11 | **PASS** | KPIs, stock adjustments, balanced journal posting, negative stock guard |
| **29** | Purchase Bills & Accounts Payable | 12 | **PASS** | White + Blue layout, bill persistence, payments, settle, duplicate |
| **30** | Tax Invoice Print & PDF Template | 7 | **PASS** | Seller/buyer details, GST split tables, amount in words |
| **31** | System Settings & Profile Edit | 6 | **PASS** | Company profile, branch settings, document numbering configs |
| **32** | Tax Invoice Edit & Delete Actions | 6 | **PASS** | Line items edit, customer balance rollback, deletion safety |
| **33** | Customer Deletion & Directory | 4 | **PASS** | Soft deletion, balance check guard, directory refresh |
| **34** | Audit Log Settings | 6 | **PASS** | Audit trail recording, action filtering, export |
| **35** | Dashboard Invoice View | 5 | **PASS** | Sales metrics, recent invoice quick links, status indicators |
| **36** | Quotations Print Option | 5 | **PASS** | Quotation document template, print layout |
| **37** | RBAC Role Isolation | 30 | **PASS** | Role boundary enforcement, default deny, audit logging |
| **38** | Dynamic Category Filter in Invoices | 15 | **PASS** | Hardware, Electrical, Plumbing category-filtered API, multi-line independent filtering, DB persistence, edit, validation 422 |
| **39** | Category Management (Manage, Edit, Delete) | 10 | **PASS** | Manage modal, Edit update, status toggles, deletion safeguard, 404 |
| **40** | Partial Payment Management & Multi-Installments | 13 | **PASS** | Status transitions, installment tracking, GL balance, overpayment blocks |
| **41** | Dedicated Quotation Management & Conversion | 11 | **PASS** | Quotation numbering, categories, quick customer modal, convert to invoice |
| **42** | Supplier Directory Deletion & Soft Guards | 7 | **PASS** | Modal confirmation, active listing exclusion, 404 boundary guards |
| **43** | Chart of Accounts (Edit & Delete Safeguards) | 10 | **PASS** | Core system account protection, update ledger, soft/hard removal |
| **44** | Dynamic Remaining Balance UPI QR Code | 10 | **PASS** | Vector SVG QR, exact remaining balance, verification status, settlement |
| **45** | WhatsApp & Email Document Sharing | 11 | **PASS** | Channel defaults, Cloud API + wa.me fallback, pure PHP PDF-1.4, history |
| **46** | Light & Dark Theme Switcher & Persistence | 6 | **PASS** | Header switch, users table & session storage, anti-FOUC, print isolation |
| **47** | Automatic Weekly Backup & Disaster Recovery | 11 | **PASS** | 7-day schedule, CLI runner, ZIP dumper, SHA-256 integrity, safety snapshots |
| **48** | 15-Minute Inactivity Timeout & Draft Saving | 11 | **PASS** | Inactivity timeout, background request bypass, user_drafts, multi-tab sync |


---

## 3. Branch Switching & Add Branch Verified Assertions (Section 26)

- `BRANCH-01`: Administrator authenticates and establishes Company 1 session. **PASSED**
- `BRANCH-02`: Database contains active operating branches (>= 2 seeded branches). **PASSED**
- `BRANCH-03`: Session initializes with valid active branch context (Branch ID > 0). **PASSED**
- `BRANCH-04`: Authorized administrator successfully switches active branch to Pune (ID 2). **PASSED**
- `BRANCH-05`: Header partial renders active branch name and branch selector dropdown. **PASSED**
- `BRANCH-06`: Unauthenticated branch switch request is rejected with HTTP 401. **PASSED**
- `BRANCH-07`: Switching to non-existent branch ID is rejected with HTTP 422. **PASSED**
- `BRANCH-08`: Cross-tenant branch switch attempt is rejected (cannot access another company branch). **PASSED**
- `BRANCH-09`: Switching to inactive branch is rejected with HTTP 422. **PASSED**
- `BRANCH-10`: Restricted staff user cannot switch to unassigned branch (HTTP 403 Forbidden). **PASSED**
- `BRANCH-11`: Single-branch user header renders static branch badge without interactive dropdown. **PASSED**
- `BRANCH-12`: Administrator creates new branch via POST /branches (persists with ID). **PASSED**
- `BRANCH-13`: Duplicate branch code registration is rejected with HTTP 422. **PASSED**
- `BRANCH-14`: GET /branches/{id} returns branch details and assigned users for modal population. **PASSED**
- `BRANCH-15`: POST /branches/{id}/update modifies branch details in database. **PASSED**
- `BRANCH-16`: POST /branches/{id}/toggle-status deactivates/activates branch. **PASSED**
- `BRANCH-17`: Main HQ branch cannot be deactivated (invariant protection). **PASSED**
- `BRANCH-18`: POST /branches/{id}/assign-users updates user access in user_branches table. **PASSED**
- `BRANCH-19`: Newly created invoice records the currently active branch_id in database. **PASSED**
- `BRANCH-20`: Dashboard queries scope transaction metrics accurately to active branch. **PASSED**
- `BRANCH-21`: Administrator switches context back to Main Branch (ID 1) without state corruption. **PASSED**
- `BRANCH-22`: Header dropdown renders "+ Add Branch" button for authorized administrators. **PASSED**
- `BRANCH-23`: Ordinary staff user header does NOT contain "+ Add Branch" action. **PASSED**
- `BRANCH-24`: Header renders Add Branch modal with all schema-adapted form controls. **PASSED**
- `BRANCH-25`: Submitting empty branch name or code to POST /branches fails with HTTP 422. **PASSED**
- `BRANCH-26`: Submitting invalid email, phone, or PIN code returns HTTP 422 with specific field errors. **PASSED**
- `BRANCH-27`: Non-administrator staff attempt to create a branch is rejected with HTTP 403 Forbidden. **PASSED**
- `BRANCH-28`: Administrator creates new branch with contact person & full details (HTTP 201 persisted in MySQL). **PASSED**
- `BRANCH-29`: Active operating branch in session remains unchanged upon branch creation (no auto-switching). **PASSED**
- `BRANCH-30`: GET /api/branches returns newly created branch in authorized branch list for company. **PASSED**
- `BRANCH-31`: Duplicate branch code registration is rejected with HTTP 422 validation failure. **PASSED**
- `BRANCH-32`: Inactive branch is created with is_active = 0, but switching to it is rejected with HTTP 422. **PASSED**
- `BRANCH-33`: Authorized administrator can switch to newly created operating branch. **PASSED**

---

## 4. Dynamic Product Category Filter Verified Assertions (Section 38)

- `CATFLT-01`: Administrator authenticates and establishes company session context. **PASSED**
- `CATFLT-02`: Hardware category selection loads only products belonging to Hardware category. **PASSED**
- `CATFLT-03`: Electrical category selection loads only products belonging to Electrical category. **PASSED**
- `CATFLT-04`: Plumbing category selection loads only products belonging to Plumbing category. **PASSED**
- `CATFLT-05`: Empty category (Accessories) returns HTTP 200 with 0 products and useful status for UI message. **PASSED**
- `CATFLT-06`: Querying non-existent category ID returns HTTP 404 with error envelope. **PASSED**
- `CATFLT-07`: Invoice create form renders Category column, Select Category option, and categories data script. **PASSED**
- `CATFLT-08`: Generate invoice with independent categories across multiple line items succeeds (HTTP 201). **PASSED**
- `CATFLT-09`: Database invoice_items table persists exact matching category_id for each line item. **PASSED**
- `CATFLT-10`: Tax and grand total calculation formulas preserved with accurate CGST/SGST splitting. **PASSED**
- `CATFLT-11`: GET /sales/invoices/{id}/edit renders populated line items with category_id in existing-items-data JSON. **PASSED**
- `CATFLT-12`: Update invoice saves modified category and product while recomputing totals correctly. **PASSED**
- `CATFLT-13`: Backend rejects mismatched category and product combination with HTTP 422. **PASSED**
- `CATFLT-14`: Backend rejects non-existent or unavailable product ID with HTTP 422. **PASSED**
- `CATFLT-15`: Unauthenticated request to category products API is strictly rejected with HTTP 401. **PASSED**

---

## 5. Category Management Verified Assertions (Section 39)

- `CATMGT-01`: Admin user authenticates and establishes session context for categories management. **PASSED**
- `CATMGT-02`: GET /categories renders Actions column with Manage, Edit, and Delete action buttons. **PASSED**
- `CATMGT-03`: POST /categories creates new test category successfully. **PASSED**
- `CATMGT-04`: GET /categories/{id} returns category details and linked products structure. **PASSED**
- `CATMGT-05`: POST /categories/{id}/update successfully edits category name and code. **PASSED**
- `CATMGT-06`: POST /categories/{id}/toggle-status changes status from ACTIVE to INACTIVE. **PASSED**
- `CATMGT-07`: POST /categories/{id}/toggle-status changes status from INACTIVE back to ACTIVE. **PASSED**
- `CATMGT-08`: Deleting a category with linked products is strictly rejected with HTTP 422 safeguard. **PASSED**
- `CATMGT-09`: POST /categories/{id}/delete permanently deletes category with 0 linked products. **PASSED**
- `CATMGT-10`: GET /categories/{non_existent_id} cleanly returns HTTP 404 not found. **PASSED**

---

## 6. Table Layout & Column Alignment Normalization

- **Product Categories (`views/inventory/categories.php`)**:
  - Enforced `table-layout: fixed; width: 100%;` preventing uncontrolled browser stretching.
  - Set `#`: 50px centered, `Category Name`: 180px, `Code`: 110px centered, `Description`: flexible (`width: auto`), `Linked Products`: 130px centered, `Status`: 95px centered, `Actions`: 200px centered with `Manage`, `Edit`, and `Delete` action buttons.
  - Eliminated the wide empty channel between `Description` and `Linked Products`.
- **Product & Item Catalog (`views/inventory/products.php`)**:
  - Enforced `table-layout: fixed; width: 100%;`.
  - Balanced column distributions: `#`: 50px centered, `Product Name & SKU`: flexible (`width: auto; min-width: 200px`) absorbing container surplus, `Category`: 130px, `HSN / SAC`: 100px centered, `Selling Price`: 115px right-aligned, `GST %`: 75px centered, `Current Stock`: 125px right-aligned, `Status`: 90px centered, `Actions`: 135px centered.
  - Eliminated the gap between `GST %` and `Current Stock`.
- **Warehouse Stock Inventory & Audit Log Tables (`views/inventory/stock.php`)**:
  - Enforced compact padding (`0.4rem 0.25rem`) across all 14 columns on `#table-balances`, eliminating ~270px of whitespace bloat.
  - Allowed Product & SKU name and Warehouse name to wrap cleanly (`white-space: normal; line-height: 1.25;`), preventing rigid cells from forcing 1500px+ widths.
  - Condensed action buttons to `.btn-icon-compact` (20x20px) and streamlined column widths so all 14 columns fit within ~873px (well within the 985px container on 1024px displays).
  - Eliminated the bottom horizontal scrollbar ("slide bar on down side") completely across Tab 1 (`#table-balances`), Tab 2 (`#table-history`), and Tab 3 (`#table-ledger`).
- **Master Test Suite Verification**:
  - `tests/run_tests.php`: 583 of 583 assertions passed (100% pass rate). **PASSED**

---

## 7. Partial Payment Management & Multi-Installments (Feature A)

- `PARTPAY-01`: Administrator authenticates and establishes company session context. **PASSED**
- `PARTPAY-02`: Create test invoice for ₹50,000 succeeds with HTTP 201. **PASSED**
- `PARTPAY-03`: Invoice grand total is ₹50,000 with amount_due equal to grand_total. **PASSED**
- `PARTPAY-04`: First payment of ₹10,000 returns HTTP 201 with success envelope. **PASSED**
- `PARTPAY-05`: Invoice status becomes PARTIAL, amount_paid is ₹10,000, amount_due is ₹40,000. **PASSED**
- `PARTPAY-06`: Customer balance decreased by exact payment amount ₹10,000. **PASSED**
- `PARTPAY-07`: Second payment of ₹15,000 keeps status PARTIAL, amount_paid ₹25,000, amount_due ₹25,000. **PASSED**
- `PARTPAY-08`: Overpayment greater than remaining balance is strictly rejected with HTTP 422. **PASSED**
- `PARTPAY-09`: Zero or negative payment amount is strictly rejected with HTTP 422. **PASSED**
- `PARTPAY-10`: Final payment clears balance to 0 and transitions invoice status to PAID. **PASSED**
- `PARTPAY-11`: Payment history contains all 3 distinct installments totaling ₹50,000. **PASSED**
- `PARTPAY-12`: GET /sales/invoices/{id}/payments returns list of payments with HTTP 200. **PASSED**
- `PARTPAY-13`: GET /payments?tab=invoices returns invoice_payments list and invoice_kpi summary. **PASSED**

---

## 8. Dedicated Quotation Management & Invoice Conversion (Feature B)

- `QUOTE-01`: Administrator authenticates and establishes quotation session context. **PASSED**
- `QUOTE-02`: Create sales quotation with multi-category items succeeds with HTTP 201. **PASSED**
- `QUOTE-03`: Quotation persists sequential number format (QT-YYYYMM-XXXX) and status SENT. **PASSED**
- `QUOTE-04`: Quotation items accurately persist distinct category_id on each line item. **PASSED**
- `QUOTE-05`: Quotation calculations correctly compute subtotal (₹21,500) and grand total (₹25,370). **PASSED**
- `QUOTE-06`: GET /sales/quotations/{id} returns quotation details and item categories via JSON. **PASSED**
- `QUOTE-07`: POST /sales/quotations/{id}/update successfully updates status to ACCEPTED and recalculates total. **PASSED**
- `QUOTE-08`: POST /sales/quotations/{id}/duplicate creates a clone with new quotation number and DRAFT status. **PASSED**
- `QUOTE-09`: POST /sales/quotations/{id}/convert generates sequential Tax Invoice with HTTP 201. **PASSED**
- `QUOTE-10`: Quotation is marked CONVERTED and invoice links back to source_quotation_id. **PASSED**
- `QUOTE-11`: Attempting to re-convert an already converted quotation is strictly rejected with HTTP 422. **PASSED**

---

## 9. Supplier Deletion & Vendor Directory Management (Section 42)

- `SUPDEL-01`: Supplier directory view renders Delete button in table actions. **PASSED**
- `SUPDEL-02`: Supplier edit modal renders Delete Supplier button in modal footer. **PASSED**
- `SUPDEL-03`: Test supplier is created and verified active. **PASSED**
- `SUPDEL-04`: Supplier appears in active supplier directory before deletion. **PASSED**
- `SUPDEL-05`: POST /suppliers/{id}/delete deletes supplier and returns HTTP 200 with success JSON. **PASSED**
- `SUPDEL-06`: Supplier is cleanly excluded from active supplier listing after deletion. **PASSED**
- `SUPDEL-07`: Deleting non-existent supplier returns HTTP 404 Not Found. **PASSED**

---

## 10. Chart of Accounts (Edit & Delete Options) (Section 43)

- `ACCMGT-01`: GET /accounts renders Actions column with Edit and Delete buttons. **PASSED**
- `ACCMGT-02`: Account modal renders Delete Account button in footer. **PASSED**
- `ACCMGT-03`: POST /accounts creates new test general ledger account with HTTP 201. **PASSED**
- `ACCMGT-04`: Test account is persisted to chart_of_accounts table. **PASSED**
- `ACCMGT-05`: GET /accounts/{id} returns account details payload via JSON. **PASSED**
- `ACCMGT-06`: POST /accounts/{id}/update successfully edits account name and description. **PASSED**
- `ACCMGT-07`: Deleting core system account (Cash in Hand) is strictly rejected with HTTP 422 safeguard. **PASSED**
- `ACCMGT-08`: POST /accounts/{id}/delete permanently deletes unused test account with HTTP 200. **PASSED**
- `ACCMGT-09`: Test account is completely removed from chart_of_accounts table. **PASSED**
- `ACCMGT-10`: Deleting non-existent account returns HTTP 404 Not Found. **PASSED**

---

## 11. Dynamic Remaining Balance UPI QR Code & Payment Reconciliation (Section 44)

- `UPIQR-01`: Administrator authenticates and configures merchant UPI ID, payee name, and payment instructions. **PASSED**
- `UPIQR-02`: Create test invoice for ₹50,000 with initial outstanding balance of ₹50,000 and initial QR for ₹50,000. **PASSED**
- `UPIQR-03`: First payment of ₹20,000 succeeds -> Total received ₹20,000, Remaining balance ₹30,000, Status PARTIAL, dynamic QR amount is ₹30,000. **PASSED**
- `UPIQR-04`: Additional payment of ₹10,000 succeeds -> Total received ₹30,000, Remaining balance ₹20,000, Status PARTIAL, dynamic QR amount updates to ₹20,000. **PASSED**
- `UPIQR-05`: Unverified UPI payment instruction is recorded as PENDING_VERIFICATION and invoice balance remains unchanged (₹20,000 due). **PASSED**
- `UPIQR-06`: Payment verification confirms transaction, updates status to COMPLETED, and reduces outstanding balance to ₹15,000. **PASSED**
- `UPIQR-07`: Submitting duplicate payment transaction reference is strictly rejected with HTTP 422 safeguard. **PASSED**
- `UPIQR-08`: Final payment clears outstanding balance to ₹0.00, invoice status becomes PAID, and UPI QR is hidden. **PASSED**
- `UPIQR-09`: When no merchant UPI is configured, system suppresses QR code and renders helpful configuration message. **PASSED**
- `UPIQR-10`: Printable invoice renders exact remaining balance (₹30,000), payment received (₹20,000), and scannable vector SVG QR. **PASSED**

---

## 12. WhatsApp & Email Document Sharing (Section 45)

- `COMM-01`: Administrator authenticates and establishes communication context. **PASSED**
- `COMM-02`: Phone numbers accurately normalized to E.164 digit strings (+91 Indian mobile standards). **PASSED**
- `COMM-03`: POST /settings/communication persists channel defaults and provider configurations. **PASSED**
- `COMM-04`: buildInvoiceMessage produces professional message with totals and remaining balance. **PASSED**
- `COMM-05`: getClickToChatUrl generates valid wa.me link with URL-encoded message. **PASSED**
- `COMM-06`: PdfGenerator generates valid PDF-1.4 binary stream with xref and EOF trailer. **PASSED**
- `COMM-07`: POST /sales/invoices/{id}/share initiates multi-channel dispatch. **PASSED**
- `COMM-08`: GET /sales/invoices/{id}/communication-history returns logged dispatch records. **PASSED**
- `COMM-09`: POST /sales/quotations/{id}/share initiates quotation WhatsApp dispatch. **PASSED**
- `COMM-10`: Invalid recipient email or missing phone is safely rejected with error feedback. **PASSED**
- `COMM-11`: WhatsApp click-to-chat fallback is strictly logged as CLICK_TO_CHAT_GENERATED, never DELIVERED. **PASSED**

---

## 13. Light & Dark Theme Switcher & Persistence (Section 46)

- `THEME-01`: User authenticates and establishes session context for theme management. **PASSED**
- `THEME-02`: POST /api/user/theme persists dark theme preference to users table & session. **PASSED**
- `THEME-03`: app.php layout renders data-theme="dark" when session theme is dark. **PASSED**
- `THEME-04`: POST /user/theme persists light theme preference to users table & session. **PASSED**
- `THEME-05`: app.php layout renders data-theme="light" when session theme is light. **PASSED**
- `THEME-06`: Print templates strictly enforce pure white backgrounds and dark text regardless of user theme. **PASSED**

---

## 14. Automatic Weekly Backup & Disaster Recovery (Section 47)

- `BACKUP-01`: Administrator authenticates and establishes backup authorization context. **PASSED**
- `BACKUP-02`: BackupService creates ZIP archive outside public web root with status SUCCESS. **PASSED**
- `BACKUP-03`: ZIP archive contains verified database.sql, audit archive, sanitized config, and manifest. **PASSED**
- `BACKUP-04`: SHA-256 checksum in backup_records matches calculated file hash. **PASSED**
- `BACKUP-05`: GET /settings/backups/{id}/download serves application/zip stream with filename header. **PASSED**
- `BACKUP-06`: getNextScheduled calculates 7-day automatic weekly backup interval. **PASSED**
- `BACKUP-07`: restoreBackup creates an automatic safety snapshot before restoring database tables. **PASSED**
- `BACKUP-08`: Restore operation is recorded in restore_logs with status SUCCESS and checksum verification. **PASSED**
- `BACKUP-09`: Atomic weekly cleanup archives logs first and purges records older than retention period. **PASSED**
- `BACKUP-10`: Critical security events and recent logs within retention window are preserved. **PASSED**
- `BACKUP-11`: Audit cleanup summary is persisted in audit_cleanup_logs with backup reference. **PASSED**

---

## 15. 15-Minute Inactivity Timeout & Draft Saving (Section 48)

- `DRAFT-01`: User authenticates and session inactivity lifetime is configured to 900 seconds (15 minutes). **PASSED**
- `DRAFT-02`: Qualifying user request refreshes last_activity_at timestamp. **PASSED**
- `DRAFT-03`: Background request with X-Session-KeepAlive: 0 strictly preserves inactivity timer. **PASSED**
- `DRAFT-04`: Inactivity exceeding 15 minutes (900 seconds) terminates session on server-side. **PASSED**
- `DRAFT-05`: DraftService::saveDraft persists form data to user_drafts table with status DRAFT. **PASSED**
- `DRAFT-06`: Unsaved form drafts are strictly isolated and never appear in invoices or accounting journals. **PASSED**
- `DRAFT-07`: DraftService::getDraft cleanly recovers structured form data and items. **PASSED**
- `DRAFT-08`: POST /api/drafts/auto-save accepts background auto-save payload with HTTP 200. **PASSED**
- `DRAFT-09`: GET /api/drafts/check verifies active draft availability for client banner prompt. **PASSED**
- `DRAFT-10`: DraftService::discardDraft cleanly purges restored draft after form submission or user discard. **PASSED**
- `DRAFT-11`: session-idle.js exists and is linked in app.php layout for multi-tab activity monitoring. **PASSED**




