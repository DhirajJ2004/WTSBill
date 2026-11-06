# WTSBill ERP — UI / UX & Design System Audit Report

**Document Reference:** `DOC-QA-UIUX-001`  
**Execution Date:** 2026-11-06  
**Auditor / Architect:** Senior UI/UX Specialist & Frontend Systems Architect  
**Status:** COMPLETE & COMPLIANT  

---

## 1. Executive Summary

A comprehensive user interface and user experience (UI/UX) audit was conducted on WTSBill ERP. The objective was to ensure a professional, responsive, accessible, and distraction-free enterprise ERP experience across all screen sizes and workflows.

### Key Audit Highlights
1. **Strict Zero Native Popup Policy:** **100% compliance verified**. Zero blocking `alert()` or `confirm()` native browser dialogs exist anywhere in the application.
2. **Dual Theme Architecture:** Seamless Light and Dark mode toggling with persistent database storage in `users.theme` and session state.
3. **Printable Document Fidelity:** All printable invoices, quotations, purchase orders, and financial statements strictly enforce pure white backgrounds and dark high-contrast typography, regardless of the user's active screen theme.
4. **Responsive Layout Grid:** Tested and verified across Desktop (1920x1080), Laptop (1366x768), Tablet (768x1024), and Mobile (375x812) viewports.
5. **Ergonomic 360° Profiles:** Unified design language between Customer 360° Profile (`/customers/{id}`) and Supplier 360° Profile (`/suppliers/{id}`), featuring prominent KPI cards, actionable quick-buttons, and instant client-side table filtering.

---

## 2. Zero Native `alert()` / `confirm()` Verification

### 2.1 The Standard
Native browser dialogs (`window.alert()`, `window.confirm()`, `window.prompt()`) are synchronous, freeze the JavaScript execution thread, disrupt user workflows, look unstyled, and fail accessibility audits.

### 2.2 Modern ERP Notification Architecture
All application feedback utilizes custom non-blocking UI components:
- **Toast Notifications (`showToast(message, type)`):**
  Renders accessible, auto-dismissing notification toasts at the top-right of the viewport with semantic color accents:
  - `success` (Emerald Green `#10b981`)
  - `danger` / `error` (Rose Red `#ef4444`)
  - `warning` (Amber `#f59e0b`)
  - `info` (Sky Blue `#0284c7`)
- **Action Confirmation Overlays:**
  Dangerous actions (such as invoice deletion or stock transfer cancellations) trigger accessible HTML5 `<dialog>` or Tailwind overlay modals with distinct "Confirm" and "Cancel" buttons, full keyboard traps, and `Escape` key dismissal.

### 2.3 Automated Regression Proof
Automated test suite `tests/Feature/GlobalPopupFixTest.php` scans all PHP view files across `views/`, asserting that no unescaped native popups remain:
- **Assertions:** `POPUP-01` to `POPUP-06` passed with 100% compliance.

---

## 3. Light & Dark Theme System

### 3.1 Theme Architecture
- The root document element toggles via the `data-theme` attribute: `<html lang="en" data-theme="dark">` or `data-theme="light"`.
- User theme preference is saved to both `$_SESSION['theme']` and the database via `POST /user/theme`.
- CSS custom properties (`--bg-primary`, `--bg-secondary`, `--text-primary`, `--border-color`) govern the color scheme dynamically without layout recalculations.

### 3.2 Print Template Strict Isolation
- **Problem Addressed:** Users operating in dark mode previously faced inverted colors or dark backgrounds when attempting to print invoices or export PDFs.
- **Enforced Solution:**
  ```css
  @media print {
      body, html, [data-theme="dark"] {
          background-color: #ffffff !important;
          color: #000000 !important;
      }
      .no-print, .sidebar, .header, .btn-action {
          display: none !important;
      }
  }
  ```
- **Verification:** Section 46 (`THEME-06`) verifies print templates render pure white backgrounds and high-contrast dark text under both theme settings.

---

## 4. Screen Responsiveness & Layout Adaptability

| Viewport | Target Device | Navigation Behavior | Data Tables & Cards |
| :--- | :--- | :--- | :--- |
| **Desktop (≥ 1280px)** | Workstations & Monitors | Full sidebar expanded with categorized groupings | Multi-column comprehensive tables, side-by-side KPI cards |
| **Laptop (1024px – 1279px)** | Laptops & Ultrabooks | Sidebar auto-collapses or compacts | Horizontal scroll on extensive data tables with sticky primary column |
| **Tablet (768px – 1023px)** | iPads & Surface Tablets | Sidebar toggles via hamburger menu | 2x2 grid for KPI cards; responsive card views for line items |
| **Mobile (< 768px)** | Smartphones | Full off-canvas slide-out navigation | Stacked 1-column KPI cards; responsive summary cards for tables |

---

## 5. Ergonomic 360° Profile Design

### 5.1 Customer 360° Profile (`/customers/{id}`)
- **Header:** Customer name, code (`CUST-XXXX`), active status pill, business category, and quick actions (`+ Create Invoice`, `+ Create Quotation`, `+ Record Payment`, `Statement CSV`, `Print`).
- **Master Details Card:** GSTIN, PAN, Phone, Email, Payment Terms, Credit Limit, and Billing/Shipping addresses.
- **KPI Summary Cards:**
  1. Total Invoiced (₹) & Invoice Count
  2. Total Paid (₹) & Receipt Count
  3. Outstanding Balance (₹) & Unsettled Count
  4. Overdue Amount (₹) & Post-Agreed Due Date Count
  5. Total Quotations & Acceptance Ratio
  6. Credit Notes & Adjustments (₹)
- **Instant Status Filter Pills:**
  - Located on both the summary card and Tab 2 (Invoices).
  - Four distinct live pills: `All`, `Unpaid`, `Partial Payment`, `Paid`.
  - Clicking any filter instantly updates the table rows without page reload, syncing with URL query parameters for bookmarkability.

### 5.2 Supplier 360° Profile (`/suppliers/{id}`)
- Follows exact visual hierarchy and ergonomic parity with the Customer Profile:
  - Header with supplier details and quick actions (`+ Inward Bill`, `+ Purchase Order`, `+ Disburse Payment`, `Statement CSV`).
  - 4 Executive KPI Cards: Total Billed, Total Disbursed, Balance Due, Active Debit Notes.
  - Tabbed sections: Overview, Inward Bills, Disbursements, Debit Notes, and Chronological Supplier Ledger.

---

## 6. Form Usability & Inactivity Session Draft Recovery

### 6.1 Draft Form Recovery UX
When a user is inactive for 15 minutes, WTSBill saves any unfinished form data to `session_drafts` and redirects to the login screen with a friendly notification banner:
> *"Your session expired due to 15 minutes of inactivity. Any unfinished form data was saved as a draft."*

Upon re-authenticating, the user is seamlessly returned to their form with all line items, customer selections, and quantities intact, preventing frustrating data loss.

### 6.2 Dynamic UPI QR Code Display UX
- On invoice view and print templates, a dynamic UPI QR code is automatically generated for payment.
- As installments are recorded, the QR code payment amount dynamically updates to the exact remaining balance due.
- Once an invoice is fully settled (`amount_due = 0.00`), the QR code is automatically hidden and replaced with a clean "PAID" badge, preventing accidental overpayments.

---

## 7. UI/UX Audit Sign-Off

The user interface of WTSBill ERP meets modern enterprise ERP standards. The elimination of jarring popups, inclusion of instant filtering pills, unified profile ergonomics, and robust print formatting provide a seamless, highly productive experience for accounting and operations staff.

