# WTSBill ERP — Branch Switching & Multi-Location System Architecture

**Document Version:** 1.0.0  
**Implementation Date:** 2026-10-26  
**Architect:** Senior PHP, MySQL, JavaScript & Systems Engineering Team  

---

## 1. Executive Summary

WTSBill ERP now features a complete, production-grade **Branch Management and Context-Switching System**. Authorized users can switch seamlessly between operating branches directly from the header without logging out or losing their session. All transactions, invoice sequences, inventory movements, and dashboard calculations operate within the selected branch context, backed by multi-tenant isolation and strict role-based access control.

---

## 2. Database Schema & Reused Infrastructure

The implementation reuses the existing normalized MySQL schema without redundant tables:

### 2.1 `branches` Table
Stores operating branch locations:
- `id` (BIGINT UNSIGNED, PK)
- `company_id` (BIGINT UNSIGNED, FK, multi-tenant scope)
- `name` (VARCHAR 255, e.g. "Main Branch - Mumbai", "Pune Regional Branch")
- `code` / `branch_code` (VARCHAR 30, unique per company, e.g. "BOM-01", "PUN-02")
- `legal_name` (VARCHAR 255)
- `gstin` (VARCHAR 15, Indian GSTIN compliance)
- `pan` (VARCHAR 10)
- `address`, `city`, `state`, `state_code`, `pincode`
- `phone`, `email`
- `is_main_branch` (TINYINT 1, default 0, flags HQ)
- `is_active` (TINYINT 1, default 1)
- `created_at`, `updated_at`, `deleted_at`

### 2.2 `user_branches` Table
Explicit branch assignment matrix:
- `id` (BIGINT UNSIGNED, PK)
- `company_id` (BIGINT UNSIGNED)
- `user_id` (BIGINT UNSIGNED)
- `branch_id` (BIGINT UNSIGNED)
- `created_at`, `updated_at`

### 2.3 `branch_users` Table
Stores per-user default branch preferences:
- `user_id`, `branch_id`, `company_id`, `is_default`, `can_switch`

### 2.4 Transaction Scoping
Every core transactional table stores `branch_id`:
- `invoices`, `invoice_items`
- `purchases`
- `quotations`
- `delivery_challans`
- `expenses`
- `payments`, `receipts`
- `stock_movements`, `stock_balances`
- `journal_entries`, `journal_lines`
- `warehouses`, `bank_accounts`

---

## 3. Security, Authorization & Business Rules

1. **Role-Based Branch Visibility:**
   - **Administrators / Owners (`ADMIN`, `SUPERADMIN`, `OWNER`):** Automatically authorized to access and switch between all active branches within their company. Can create, edit, deactivate, and assign branches.
   - **Staff / Accountants / Managers:** Restricted strictly to branches explicitly assigned to their user ID in `user_branches`. Cannot switch to unassigned branches.
2. **Single-Branch User Experience:**
   - If a user is authorized for only one branch, the application renders a clean static indicator badge (`[Pin] Branch Name (Active)`). Interactive switching controls and carets are omitted.
3. **Multi-Branch Switching Controls:**
   - If a user has $\ge 2$ authorized branches, an interactive header dropdown displays the active branch with a location pin, branch label, and caret.
   - Switching executes via `POST /branches/switch` sending CSRF token and `branch_id`.
   - The server verifies:
     1. User is authenticated.
     2. Target branch exists and belongs to user's `company_id`.
     3. Target branch is active (`is_active = 1`).
     4. User has permission to access it (`isUserAuthorized`).
4. **Server-Side Session Context:**
   - Server session stores:
     - `current_branch_id`: Active operating branch ID.
     - `current_branch_name`: Name of active branch.
     - `current_branch_code`: Code of active branch.
   - Browser cookies or URL parameters are never trusted without server-side validation.
5. **Main Organization Branch Invariant:**
   - The main HQ branch (`is_main_branch = 1`) cannot be deactivated while other operations depend on it.

---

## 4. Endpoints & Controller Actions

| HTTP Method | Route | Controller & Action | Middleware | Description |
|:---:|---|---|---|---|
| `POST` | `/branches/switch` | `BranchController@switch` | `[$auth, $csrf]` | Switches active branch context in session |
| `GET` | `/branches` | `BranchController@index` | `[$auth]` | Admin branch management view |
| `POST` | `/branches` | `BranchController@store` | `[$auth, $csrf]` | Creates a new branch (with permissions & validation) |
| `GET` | `/api/branches` | `BranchController@list` | `[$auth]` | Dynamic authorized switcher branch list for dropdown |
| `GET` | `/branches/list` | `BranchController@list` | `[$auth]` | Authorized branch list endpoint |
| `GET` | `/branches/{id}` | `BranchController@show` | `[$auth]` | Fetches branch details for modal editing |
| `POST` | `/branches/{id}/update` | `BranchController@update` | `[$auth, $csrf]` | Updates branch details |
| `POST` | `/branches/{id}/toggle-status` | `BranchController@toggleStatus` | `[$auth, $csrf]` | Activates or deactivates branch |
| `POST` | `/branches/{id}/assign-users` | `BranchController@assignUsers` | `[$auth, $csrf]` | Syncs user assignments for branch |

---

## 5. Add Branch Feature Architecture & Integration

1. **Header Dropdown Integration:**
   - The branch switcher in `views/partials/header.php` renders the currently active branch, all authorized branches, and an **+ Add Branch** button at the bottom for authorized administrators (`ADMIN`, `SUPERADMIN`, `OWNER`).
   - The Add Branch option is separated visually with a top border and soft primary accent background.
   - Non-administrators and ordinary staff users never see the Add Branch action or modal markup.
2. **Add Branch Modal UX & Safeguards:**
   - Matches the dark/light WTSBill design system.
   - Form fields: Branch Name (required), Branch Code (required & unique), Contact Person (optional), Phone Number (optional), Email (optional), Address (optional), City (optional), State (optional), PIN Code (optional), and Status (Active / Inactive).
   - Features client-side pre-validation, spinner loading state during submission, and clear inline error alerts without browser popups or alert dialogs.
3. **Database Persistence & Unique Constraints:**
   - Enforces unique branch codes per company both via service-level checks and a MariaDB unique constraint (`branches_company_branch_code_unique`).
   - Reuses existing `branches` table safely without resetting or duplicating tables.
4. **Dynamic Refresh Without Page Reload:**
   - On successful creation, the modal closes, an in-app success toast notifies the user, and `window.refreshBranchDropdown()` dynamically updates the dropdown menu.
   - The currently active branch remains unchanged unless the user explicitly switches.
   - Inactive branches display with an `INACTIVE` badge and cannot be switched into.

---

## 6. Automated Test Coverage

The feature is validated by 33 automated assertions in `tests/Feature/BranchSwitchingTest.php`:

- `BRANCH-01`: Administrator authenticates and establishes Company 1 session.
- `BRANCH-02`: Database contains active operating branches (>= 2 seeded branches).
- `BRANCH-03`: Session initializes with valid active branch context (Branch ID > 0).
- `BRANCH-04`: Authorized administrator successfully switches active branch to Pune (ID 2).
- `BRANCH-05`: Header partial renders active branch name and branch selector dropdown.
- `BRANCH-06`: Unauthenticated branch switch request is rejected with HTTP 401.
- `BRANCH-07`: Switching to non-existent branch ID is rejected with HTTP 422.
- `BRANCH-08`: Cross-tenant branch switch attempt is rejected (cannot access another company branch).
- `BRANCH-09`: Switching to inactive branch is rejected with HTTP 422.
- `BRANCH-10`: Restricted staff user cannot switch to unassigned branch (HTTP 403 Forbidden).
- `BRANCH-11`: Single-branch user header renders static branch badge without interactive dropdown.
- `BRANCH-12`: Administrator creates new branch via POST /branches (persists with ID).
- `BRANCH-13`: Duplicate branch code registration is rejected with HTTP 422.
- `BRANCH-14`: GET /branches/{id} returns branch details and assigned users for modal population.
- `BRANCH-15`: POST /branches/{id}/update modifies branch details in database.
- `BRANCH-16`: POST /branches/{id}/toggle-status deactivates/activates branch.
- `BRANCH-17`: Main HQ branch cannot be deactivated (invariant protection).
- `BRANCH-18`: POST /branches/{id}/assign-users updates user access in user_branches table.
- `BRANCH-19`: Newly created invoice records the currently active branch_id in database.
- `BRANCH-20`: Dashboard queries scope transaction metrics accurately to active branch.
- `BRANCH-21`: Administrator switches context back to Main Branch (ID 1) without state corruption.
- `BRANCH-22`: Header dropdown renders "+ Add Branch" button for authorized administrators.
- `BRANCH-23`: Ordinary staff user header does NOT contain "+ Add Branch" action.
- `BRANCH-24`: Header renders Add Branch modal with all schema-adapted form controls.
- `BRANCH-25`: Submitting empty branch name or code to POST /branches fails with HTTP 422.
- `BRANCH-26`: Submitting invalid email, phone, or PIN code returns HTTP 422 with specific field errors.
- `BRANCH-27`: Non-administrator staff attempt to create a branch is rejected with HTTP 403 Forbidden.
- `BRANCH-28`: Administrator creates new branch with contact person & full details (HTTP 201 persisted in MySQL).
- `BRANCH-29`: Active operating branch in session remains unchanged upon branch creation (no auto-switching).
- `BRANCH-30`: GET /api/branches returns newly created branch in authorized branch list for company.
- `BRANCH-31`: Duplicate branch code registration is rejected with HTTP 422 validation failure.
- `BRANCH-32`: Inactive branch is created with is_active = 0, but switching to it is rejected with HTTP 422.
- `BRANCH-33`: Authorized administrator can switch to newly created operating branch.

---

## 7. Real-Time Branch Data Scoping & Visual UI Styling

1. **Visual Presentation & Resilience:**
   - The branch switcher in `views/partials/header.php` is styled with dark-mode matching deep slate backgrounds (`#1e293b`), custom border outlines (`#334155`), clean typography, map-pin icon badges, and downward carets.
   - Inline styles and click fallbacks guarantee perfect rendering and interactivity regardless of browser asset caching.
   - `.htaccess` was updated with `no-cache, must-revalidate` for CSS/JS so style updates reflect immediately in all browsers.

2. **Branch-Scoped Data Distribution:**
   - **Branch 1 (Main Branch - Mumbai):** Contains Mumbai sales invoices, purchase bills, and operational expenses (Total Sales: ₹84,016.00 across 4 invoices).
   - **Branch 2 (Pune Regional Branch):** Contains dedicated Pune invoices (`INV-PUN-2026-001`, `INV-PUN-2026-002`), purchase bill (`PB-PUN-2026-001`), and Pune branch rent (`EXP-PUN-2026-01`).
   - Switching between branches dynamically updates the Dashboard metrics, recent invoice tables, sales invoice lists, and expense logs to reflect only the selected branch.
