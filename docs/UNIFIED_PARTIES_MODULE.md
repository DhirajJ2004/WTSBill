# WTSBill ERP — Unified Parties Module

## Overview
The **Unified Parties Module** provides a consolidated directory and 360° lifecycle manager for all **Customers**, **Suppliers**, and **Dual-Role Parties** (marked as **Both**) in WTSBill ERP. It replaces the separate Customers and Suppliers menu entries with a single, unified **Parties** navigation item while strictly preserving existing business logic, routes, relational integrity, and legacy customer/supplier records.

---

## 1. Architecture & Data Model

### Central `parties` Table
- `id` (BIGINT UNSIGNED, Primary Key)
- `company_id` (BIGINT UNSIGNED, Multi-tenant Isolation)
- `party_code` (VARCHAR, Auto-generated monotonic sequence, e.g. `PRT-0001`)
- `party_type` (`Customer` | `Supplier` | `Both`)
- `name` (VARCHAR, Party / Business Name)
- `contact_person` (VARCHAR, Primary Contact)
- `phone` / `mobile` (VARCHAR, Mobile contact)
- `email` (VARCHAR, Official email)
- `gstin` (VARCHAR, 15-character GSTIN)
- `pan` (VARCHAR, 10-character PAN)
- `billing_address` / `shipping_address` (TEXT)
- `city` / `state` (VARCHAR)
- `opening_balance` (DECIMAL, Starting debit/credit)
- `credit_limit` (DECIMAL, Maximum allowed credit)
- `payment_terms` (VARCHAR, e.g. `NET30`, `Immediate`)
- `notes` (TEXT)
- `customer_id` (BIGINT UNSIGNED, Linked foreign key into `customers`)
- `supplier_id` (BIGINT UNSIGNED, Linked foreign key into `suppliers`)
- `is_active` (TINYINT, 1 = Active, 0 = Inactive)
- `created_at` / `updated_at` / `deleted_at` (Timestamps)

### Dual-Role Synchronization (`Both`)
When a party is created or updated as **Both**:
1. **Single Party Record**: Exactly one row is created in `parties` (`party_type = 'Both'`).
2. **Linked Roles**: Automatically creates/syncs an entry in `customers` (`party_id` reference) and `suppliers` (`party_id` reference).
3. **No Duplicate Identity**: The master entity remains singular. Changes to contact details or addresses propagate atomically across the synchronized customer and supplier rows.

---

## 2. User Interface & Workflows

### Unified Parties Hub (`/parties`)
- **Quick-Filter Tabs**: All Parties | Customers | Suppliers | Both (with dynamic badge counts).
- **Search Toolbar**: Search by Name, Mobile, Email, GSTIN, or Code with debounce.
- **Filter Dropdowns**: Filter by City/State, Active/Inactive status, and Outstanding Balance.
- **Combined Data Table**: Columns: Party Name, Party Code, Party Type, Contact Person, Phone Number, Email, GSTIN, City/State, Outstanding Balance, Status, Actions.
- **Server-Side Pagination**: Handles large datasets cleanly without DOM bloat.
- **Add / Edit Modal**: Single unified modal supporting Customer, Supplier, and Both roles.

### 360° Party Profile (`/parties/{id}`)
- **Header & Dual-Role Badges**: Displays unified contact parameters, GSTIN, PAN, and address.
- **KPI Summary Cards**:
  - Net Balance / Outstanding Liability
  - Total Billed / Invoiced Volume
  - Total Payments & Receipts
  - Active Orders & Challans
- **Role-Aware Document Tabs**:
  - **Sales Invoices** (for Customer & Both)
  - **Purchase Bills** (for Supplier & Both)
  - **Quotations** (for Customer & Both)
  - **Purchase Orders** (for Supplier & Both)
  - **Consolidated Payments & Receipts**
  - **Consolidated Running Ledger Statement**
- **Statement CSV Export**: One-click download of the running statement (`/parties/{id}/statement/csv`).

---

## 3. Navigation & Sidebar
- Exactly **ONE** sidebar entry named **Parties** pointing directly to `/parties`.
- Separate Customers and Suppliers links removed from the visible sidebar.
- Existing legacy routes (`/customers`, `/suppliers`, `/customers/{id}`, `/suppliers/{id}`) remain fully functional for complete backwards compatibility.

