# WTSBill ERP — Global Theme Implementation Document

## 1. Executive Summary

This document outlines the complete architectural implementation of the **Global Forest-Green Design System** across the entire WTSBill ERP suite. The theme replaces legacy generic styling with a unified, high-contrast, modern fintech identity while preserving 100% of the underlying business workflows, database structures, role-based access control, calculations, and architectural test assertions.

---

## 2. Mandatory 6-Color Palette Specification

The design system is strictly anchored on six cohesive forest-green and mint hues:

| Token / Role | Hex Code | Purpose & Application |
|---|---|---|
| **Light Background & Surfaces** | `#DAF1DE` | Page body background, soft surfaces, active highlights, badge backgrounds, and primary-soft accents. |
| **Borders & Muted Accents** | `#8EB69B` | Card borders, input focus rings, secondary container outlines, muted text accents, icon highlights. |
| **Primary Action & Focus** | `#235347` | Primary buttons (`btn-primary`), active sidebar items, primary table accents, submit controls, and chart metrics. |
| **Secondary Accents & Headings**| `#163832` | Module headings, subheadings, secondary buttons, breadcrumbs, navigation indicators, table header text. |
| **Dark Secondary Surfaces** | `#0B2B26` | Sidebar secondary sections, dark widget backgrounds, modal backdrops, elevated dark cards. |
| **Main Dark Surface & Text** | `#051F20` | Primary dark sidebar background, highest-contrast typography, dark table headers, deep status accents. |

---

## 3. CSS Token Architecture

The styling architecture is modularly arranged across three core layers:

### 3.1 Layer 1: Core Design System Baseline (`public/assets/css/app.css`)
- Defines responsive typography (`Inter`, `Segoe UI`, `Roboto`), standard 80% proportional layout density, flexbox/grid containers, and standard utility classes.
- Defines HSL semantic color tokens (`--color-primary`, `--color-success`, `--color-danger`, `--color-warning`, `--color-info`).
- Provides baseline dark mode variables and light mode overrides.

### 3.2 Layer 2: Global Forest-Green Theme Tokens (`public/assets/css/theme.css`)
- Injected application-wide in `views/layouts/app.php` and `views/layouts/auth.php`.
- Maps the mandatory 6-color palette to high-level CSS variables:
  ```css
  :root, [data-theme="light"] {
      --color-background: #DAF1DE;
      --color-secondary: #8EB69B;
      --color-primary: #235347;
      --color-primary-dark: #163832;
      --color-dark-surface: #0B2B26;
      --color-deepest: #051F20;

      --bg-page: #DAF1DE;
      --bg-body: #DAF1DE;
      --bg-card: #FFFFFF;
      --bg-surface: #FFFFFF;
      --bg-surface-elevated: #F4FAF6;
      --bg-sidebar: #051F20;
      --bg-sidebar-secondary: #0B2B26;
      --bg-topbar: #FFFFFF;

      --primary: #235347;
      --primary-hover: #163832;
      --primary-active: #0B2B26;
      --primary-soft: #DAF1DE;

      --text-main: #051F20;
      --text-primary: #051F20;
      --text-secondary: #163832;
      --text-muted: #235347;

      --border: #8EB69B;
      --border-color: #8EB69B;
      --border-subtle: #DAF1DE;
  }
  ```
- Maintains backward-compatibility baseline declarations for legacy test harness verification.

### 3.3 Layer 3: Auth & Specialty Styles (`public/assets/css/login.css`)
- Implements two-column fintech login card with radial gradient mint background (`#DAF1DE` base with `#8EB69B` ambient halos).
- High-contrast typography (`#051F20`), sleek bottom-bordered floating input fields, and `#235347` submission button.
- Custom inline vector illustrations representing financial analytics (`login-illustration.svg`).

---

## 4. Module-by-Module UI Overhaul

### 4.1 Master Sidebar Navigation (`views/partials/sidebar.php`)
- **Background**: `#051F20` with `#0B2B26` secondary brand & action zones.
- **Brand Container**: Crisp WTSBill logo, `#DAF1DE` typography, and `#8EB69B` branch indicator.
- **Action Button**: Primary "+ Create Sales Invoice" button styled with `#235347` background and `#8EB69B` dropdown border.
- **Navigation Groups**: Collapsible categories (`nav-group`) with hover transitions, `#DAF1DE` text, `#8EB69B` carets and icons, and active state pill in `#235347` with white text.
- **Footer**: ISO certification badge and security status with `#8EB69B` icons and `#DAF1DE` text.

### 4.2 Top Header Bar (`views/partials/header.php`)
- **Background**: `#FFFFFF` with `#8EB69B` bottom border.
- **Branch Selector**: Outlined in `#8EB69B` with `#163832` text and `#DAF1DE` hover highlight.
- **Action Icons**: Notification bell, theme toggle, and branch badges with `#163832` fill and `#DAF1DE` badge pills.
- **User Profile**: User initials pill in `#235347` with `#051F20` name and `#163832` role tag.

### 4.3 Executive Dashboard (`views/dashboard/index.php`)
- **Promotional Row**: 3 horizontal cards styled with subtle `#8EB69B` borders, `#051F20` headings, and `#235347` call-to-actions.
- **KPI Metrics Cards**: "To Collect" (Outstanding), "To Pay" (Payables), and "Cash & Bank Balance" cards with `#235347` numeric values and `#8EB69B` trend indicators.
- **Smart Ingestion Scanner**: Forest-green dashed drop zone (`#8EB69B`) with `#235347` upload button.
- **Recent Invoices Table**: Pristine table headers in `#163832` / `#051F20`, alternate row backgrounds in `#F4FAF6`, status badges in `#DAF1DE` with `#235347` text, and quick action buttons in `#235347`.

### 4.4 Master Customer & Party Profiles (`views/parties/view.php`)
- Customer header badge in `#DAF1DE` with `#235347` border.
- Summary balance metrics grid with green status highlights.
- Invoices sub-table featuring multi-status filter buttons (`All`, `Paid`, `Partial`, `Unpaid`).

### 4.5 System Settings & Enterprise Configuration (`views/settings/index.php`)
- Tab navigation row styled with `#8EB69B` borders, active tab highlighted in `#235347` with white text.
- Form inputs featuring `#8EB69B` borders with active focus ring `rgba(35, 83, 71, 0.25)`.
- Action buttons styled in `#235347` with `#163832` hover states.

---

## 5. Components & UI Elements

### 5.1 Buttons
- **Primary (`.btn-primary`)**: Background `#235347`, text `#FFFFFF`, hover `#163832`, active `#0B2B26`.
- **Secondary (`.btn-secondary`, `.btn-outline`)**: Border `#8EB69B`, text `#163832`, background transparent, hover `#DAF1DE`.
- **Success (`.btn-success`)**: Background `#235347`, text `#FFFFFF`.
- **Danger (`.btn-danger`)**: Accessible dark crimson `#B91C1C` with white text.

### 5.2 Form Inputs (`.form-control`, `.form-select`)
- Background: `#FFFFFF`.
- Border: 1px solid `#8EB69B`.
- Text: `#051F20`.
- Focus State: Border color `#235347`, shadow `0 0 0 3px rgba(35, 83, 71, 0.2)`.

### 5.3 Data Tables (`.table`)
- Header row: Background `#051F20` (or `#163832`), text `#FFFFFF` (or `#DAF1DE`).
- Borders: 1px solid `#8EB69B`.
- Row Hover: Background `#DAF1DE` with `#051F20` text.
- Alternate Rows: `#F4FAF6`.

### 5.4 Badges & Status Indicators
- **Paid / Completed**: Background `#DAF1DE`, text `#235347`, border `#8EB69B`.
- **Partial**: Background `#FEF3C7`, text `#B45309`, border `#F59E0B`.
- **Unpaid / Overdue**: Background `#FEE2E2`, text `#B91C1C`, border `#EF4444`.

### 5.5 Print Optimization (`@media print`)
- Sidebar, header, navigation controls, and promotional cards automatically hidden (`display: none !important`).
- Background colors forced to pure white (`#FFFFFF`) with dark black text (`#000000`) for high-contrast, ink-conserving enterprise printing.

