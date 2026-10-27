# WTSBill ERP — Regression Test Results Log

**Document Version:** 4.0.0  
**Last Verified:** 2026-10-27  
**Test Runner:** `tests/run_tests.php`  
**Execution Environment:** Native PHP 8.0 CLI + MariaDB 10.4.32 (`wtsbill_db`)

---

## 1. Test Suite Summary

```
==================================================================================
                         TEST VERIFICATION SUMMARY                                
==================================================================================
Total Assertions: 407
Passed:           407
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
