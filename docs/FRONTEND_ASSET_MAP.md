# WTSBill ERP — Frontend Asset Map & Integration Architecture

**Document Version:** 1.0.0  
**Generated Date:** 2026-10-10  
**Architecture:** Native Vanilla CSS 3 + Native Vanilla ECMAScript (ES2022)  
**Dependencies:** Zero external frontend frameworks (No Bootstrap JS, No jQuery, No Tailwind runtime, No React/Vue)

---

## 1. Executive Summary & Asset Topology

The WTSBill ERP frontend is architected as an ultra-fast, zero-overhead client layer that delivers a modern, high-contrast, enterprise-grade dark/light user interface. It is engineered with vanilla CSS design tokens and modular vanilla JavaScript components, ensuring instant initial page loads (<50ms execution overhead), robust responsive mobile support, and seamless integration with the Native PHP 8+ backend.

### 1.1 Asset Directory Structure

```
public/
└── assets/
    ├── css/
    │   └── app.css           # Primary ERP design system & component library
    ├── js/
    │   └── app.js            # Modular client engine & interactive controllers
    ├── fonts/                # Inter / Roboto typography assets
    ├── images/               # Enterprise brand icons & vector emblems
    └── vendor/               # Approved static client libraries (if any)
```

---

## 2. CSS Design System & Token Inventory (`public/assets/css/app.css`)

### 2.1 Color Tokens & Theming (Curated HSL Palette)

| CSS Variable | Token Value | Purpose / Visual Context |
| :--- | :--- | :--- |
| `--color-primary` | `#3b82f6` (Blue 500) | Primary branding, active links, primary CTA buttons |
| `--color-primary-hover` | `#2563eb` (Blue 600) | Hover state for primary buttons & active items |
| `--color-primary-light` | `rgba(59, 130, 246, 0.12)` | Subtle blue tinted backgrounds & focus rings |
| `--color-success` | `#10b981` (Emerald 500) | Paid invoices, active tenant dots, valid inputs |
| `--color-success-bg` | `rgba(16, 185, 129, 0.12)` | Success badge background & toast indicator |
| `--color-danger` | `#ef4444` (Rose 500) | Overdue amounts, error validation, delete buttons |
| `--color-danger-bg` | `rgba(239, 68, 68, 0.12)` | Error alerts, invalid input glow, 419/403 toasts |
| `--color-warning` | `#f59e0b` (Amber 500) | Pending invoices, quotation drafts, warning alerts |
| `--color-info` | `#06b6d4` (Cyan 500) | GST tax collections, system info notifications |

### 2.2 Dark Theme (Default Enterprise Slate) vs Light Mode

| Property | Dark Theme (Default) | Light Theme (`[data-theme="light"]`) |
| :--- | :--- | :--- |
| `--bg-body` | `#0b1120` (Deep Slate) | `#f8fafc` (Slate 50) |
| `--bg-surface` | `#1e293b` (Slate 800) | `#ffffff` (Pure White) |
| `--bg-surface-elevated` | `#334155` (Slate 700) | `#f1f5f9` (Slate 100) |
| `--bg-sidebar` | `#0f172a` (Slate 900) | `#0f172a` (Consistent Dark Nav) |
| `--border-color` | `#334155` (Slate 700) | `#e2e8f0` (Slate 200) |
| `--text-primary` | `#f8fafc` (Near White) | `#0f172a` (Near Black) |
| `--text-secondary` | `#94a3b8` (Slate 400) | `#475569` (Slate 600) |
| `--text-muted` | `#64748b` (Slate 500) | `#94a3b8` (Slate 400) |

---

## 3. Layout Architecture & Master Shells

### 3.1 Primary Enterprise Shell (`views/layouts/app.php`)
- **Semantic Structure:**
  - `<div class="erp-container">`: Master flex container.
  - `<aside id="erp-sidebar">`: Fixed navigation sidebar across all 8 ERP modules.
  - `<div class="erp-main">`: Flexible workspace wrapper.
    - `<header class="erp-header">`: Sticky top bar with tenant badge, search, user dropdown.
    - `<main class="erp-content">`: Slot for page templates (`$content ?? ''`).
    - `<footer class="erp-footer">`: System metadata, copyright, runtime version.
  - `<div id="toast-container">`: Floating notifications anchor.
- **CSRF Token Embedding:** `<meta name="csrf-token" content="<?= e(Session::csrfToken()) ?>">`.

### 3.2 Authentication Shell (`views/layouts/auth.php`)
- **Layout Model:** Centered flexbox with radial gradient backdrop.
- **Card Container:** `.auth-container` (max-width `440px`).
- **Target Pages:** `/login`, `/forgot-password`, `/reset-password`.

---

## 4. UI Component Library Mapping

| Component | HTML Classes | Description & States |
| :--- | :--- | :--- |
| **Buttons** | `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-success`, `.btn-danger`, `.btn-outline-*`, `.btn-sm`, `.btn-lg` | Standardized padding, flex centering, smooth hover transitions. |
| **Cards & KPI** | `.card`, `.card-header`, `.card-body`, `.card-footer`, `.kpi-card`, `.metric-value`, `.metric-delta` | Surface elevation with 1px border; delta positive/negative indicators. |
| **Data Tables** | `.table-container`, `.table`, `.table-striped`, `.table-hover` | Responsive scrolling, sticky headers, subtle hover highlight (`rgba(255,255,255,0.02)`). |
| **Form Inputs** | `.form-group`, `.form-label`, `.form-control`, `.form-select`, `.is-invalid`, `.invalid-feedback` | Border focus glow (`0 0 0 3px var(--color-primary-light)`), invalid state support. |
| **Input Groups** | `.input-group`, `.input-group-text` | Prepend and append addons for currency symbols (₹) and search buttons. |
| **Badges** | `.badge`, `.badge-primary`, `.badge-success`, `.badge-danger`, `.badge-warning`, `.badge-info` | Pill badges for transaction statuses (`PAID`, `PENDING`, `OVERDUE`, `CANCELLED`). |
| **Toast Alert** | `.toast-container`, `.toast`, `.toast-success`, `.toast-error`, `.toast-warning`, `.toast-info` | Fixed top-right stack, entrance slide-in animation, auto-dismiss. |
| **Modal Dialog** | `.modal-backdrop`, `.modal-dialog`, `.modal-header`, `.modal-body`, `.modal-footer` | Backdrop blur, center scale-up animation, keyboard `ESC` dismissal. |
| **Pagination** | `.pagination`, `.page-item`, `.page-link`, `.page-item.active`, `.page-item.disabled` | Clean navigation bar for multi-page tables. |
| **Charts** | `.chart-container`, `.chart-bar`, `.chart-legend` | Native CSS-rendered bar charts for sales and tax distributions. |

---

## 5. JavaScript Architecture (`public/assets/js/app.js`)

All client scripts operate under the global namespace `window.WTSBill`:

```javascript
window.WTSBill = {
    Sidebar:   { toggle(), close() },
    Dropdown:  { toggle(menuId), closeAll() },
    Toast:     { show(message, type, duration), dismiss(toast) },
    Modal:     { open(modalId), close(modalId) },
    Http:      { fetchWithCsrf(url, options) },
    Forms:     { init() },
    Tables:    { filter(inputId, tableId) },
    Charts:    { renderBars(containerId, data) }
};
```

### 5.1 CSRF AJAX Contract
Any fetch request initiated via `WTSBill.Http.fetchWithCsrf(url, options)` or `window.fetchWithCsrf(url, options)` automatically:
1. Reads `<meta name="csrf-token">` and injects `X-CSRF-TOKEN: <token>` into HTTP headers.
2. Appends `X-Requested-With: XMLHttpRequest`.
3. Sets `Accept: application/json` unless overridden.

### 5.2 Form Safeguards
Forms marked with `data-disable-on-submit` automatically disable the submit button upon trigger to prevent duplicate voucher or invoice creation in high-latency environments. Inputs with `data-uppercase` auto-capitalize GSTIN and PAN inputs in real time.

---

## 6. Responsive Breakpoint Specifications

| Viewport Width | Device Category | Sidebar Behavior | Header / Content Adjustments |
| :--- | :--- | :--- | :--- |
| **> 1024px** | Desktop Displays | Persistent fixed sidebar (`260px` width) | Full layout width, horizontal filter toolbars |
| **768px – 1024px** | Tablets / Small Laptops | Persistent sidebar | Wrapped filter bars, responsive scrollable tables |
| **< 768px** | Mobile Handhelds | Off-canvas drawer (`transform: translateX(-100%)`) toggled via `#sidebar-toggle` | Margin-left resets to 0; `#sidebar-overlay` darkens background |

---

## 7. Print Media Engine (`@media print`)

For GST tax invoices, delivery challans, and financial balance sheets:
- **Hidden Elements:** Sidebar, Header, Footer, Toasts, Buttons, and anything marked `.no-print`.
- **Content Expansion:** Main container resets margins and padding to 0.
- **High-Contrast Typography:** Body background resets to pure white (`#ffffff`) and text to solid black (`#000000`).
- **Paper Sizing:** Optimized for standard A4 portrait and landscape printing.
