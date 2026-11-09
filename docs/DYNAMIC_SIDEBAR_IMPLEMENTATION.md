# WTSBill ERP — Dynamic Single-Expanded Sidebar Accordion Navigation

## 1. Executive Summary

This document details the architectural fix and verification of the single-expanded accordion navigation in the WTSBill ERP left sidebar. Previously, multiple category groups could remain open simultaneously, leading to vertical clutter and inconsistent navigation state across page reloads.

The updated controller enforces the strict rule: **at any moment, exactly zero or one main sidebar submenu may be expanded**.

---

## 2. Architecture & Design Principles

### 2.1 Single State Management (`openMenu`)
- A single state variable `openMenu` (string or `null`) tracks the currently active/expanded menu group.
- Clicking a menu group toggles its state:
  - If the clicked group is already open (`openMenu === targetId`), `openMenu` is set to `null` (collapsing it).
  - Otherwise, `openMenu` is set to `targetId`, opening the clicked submenu and immediately collapsing any previously expanded submenu.
- Independent multi-group state arrays have been deprecated and replaced with a single serialized identifier in `sessionStorage` (`wtsbill_open_nav_group`).

### 2.2 Server-Side Deterministic Rendering (PHP)
Before rendering the sidebar loop in `views/partials/sidebar.php`:
1. The server identifies whether the current URL route matches any category child item across all menu groups (`$activeParentGroupId`).
2. If an active child route exists (e.g. `/sales/invoices` under `sales`), only that parent group is rendered with `has-active` and expanded (`isCollapsed = false`).
3. All other menu groups are rendered as `collapsed` (`isCollapsed = true`, `display: none`).
4. If the user is on an unnested page (e.g. `/` Dashboard or `/parties`), all accordion submenus are rendered collapsed (`isCollapsed = true`).

### 2.3 Active Route & Refresh Persistence
- On direct navigation or page refresh:
  - The URL route's parent menu is automatically detected and expanded.
  - The active child link receives `.active` highlighting (`#235347` background with `#8EB69B` indicator bar).
  - The parent header receives `.has-active` highlighting (`rgba(35, 83, 71, 0.35)` background with bright text).
  - Unrelated menus remain collapsed.

### 2.4 Event Isolation & Mobile Viewport Handling
- Submenu links (`.nav-group-items a.nav-item`) have click event propagation stopped (`e.stopPropagation()`) to prevent accidentally toggling the parent menu accordion.
- On mobile viewports (`window.innerWidth <= 768px`), clicking any navigation link automatically triggers `window.closeSidebar()` to close the off-canvas drawer.

---

## 3. Supported Expandable Menus & Aliases

The single-accordion behavior applies across all expandable sidebar sections:
| Main Menu Label | Internal ID | Submenu Categories |
| :--- | :--- | :--- |
| **Sales** | `sales` | Tax Invoices, Quotations, Sales Orders, Delivery Challans, Credit Notes |
| **Purchases** | `purchases` | Purchase Bills, Expenses, Debit Notes |
| **Items** | `inventory` *(alias: `items`)* | Items (tabbed catalog, categories, stock, transfers) |
| **Parties** | `parties` | Unified Customers & Suppliers Hub |
| **Finance** | `finance` *(alias: `money`)* | Payments & Receipts, Cash & Bank, Receivables Aging |
| **GST** | `gst` | GSTR-1 Compliance, GSTR-3B, GST Registers |
| **Reports** | `reports` | Profit & Loss, BI Analytics |
| **Accounting** | `accounting` | Chart of Accounts, Journal Entries, General Ledger, Trial Balance, Balance Sheet, Cash Flow |
| **Settings** | `settings` | Company Profile, Company Branding, Users & Roles, Invoice Templates, Warehouses & Godowns |

---

## 4. Test Verification Results

### 4.1 Server-Side Render Suite (`test_sidebar_accordion.php`)
- **TEST 1**: Direct visit to `/` (Dashboard) -> **0 open submenus** (PASS).
- **TEST 2**: Direct visit to `/sales/invoices` -> **1 open submenu (`sales`)** (PASS).
- **TEST 3**: Direct visit to `/purchases/bills` -> **1 open submenu (`purchases`)** (PASS).
- **TEST 4**: Direct visit to `/payments` (Finance) -> **1 open submenu (`finance`)** (PASS).
- **TEST 5**: Direct visit to `/items` (Items) -> **1 open submenu (`inventory`)** (PASS).
- **TEST 6**: Direct visit to `/settings/branding` -> **1 open submenu (`settings`)** (PASS).
- **TEST 7**: Direct visit to `/parties` -> **0 open submenus** (PASS).

### 4.2 Interactive Client-Side Simulation (`test_accordion_interaction.js`)
- Initial state on `/`: `openMenu = null`, open groups: `[]` (PASS).
- Click **Sales**: `openMenu = 'sales'`, open groups: `['sales']` (PASS).
- Click **Purchases**: `openMenu = 'purchases'`, open groups: `['purchases']`, Sales closes automatically (PASS).
- Click **Finance**: `openMenu = 'finance'`, open groups: `['finance']`, Purchases closes automatically (PASS).
- Click **GST**: `openMenu = 'gst'`, open groups: `['gst']`, Finance closes automatically (PASS).
- Click **GST again**: `openMenu = null`, open groups: `[]`, collapses to zero (PASS).
- Direct visit to `/sales/invoices`: `openMenu = 'sales'`, open groups: `['sales']` (PASS).
- Page refresh on `/sales/invoices`: `openMenu = 'sales'`, parent remains open (PASS).
- Click **Items** (alias): `openMenu = 'inventory'`, open groups: `['inventory']` (PASS).
- Click **Settings**: `openMenu = 'settings'`, open groups: `['settings']`, Items closes automatically (PASS).

### 4.3 Master Test Suite Verification (`tests/run_tests.php`)
- **Total Assertions**: 818
- **Passed**: 818
- **Failed**: 0
- **Pass Rate**: 100%
- Zero regressions across core router, authentication, security RBAC, database transactions, GST compliance, layout rendering, and all ERP feature modules.

