# WTSBill ERP — Enterprise Module Expansion & Enhancement Plan

**Date:** 2026-11-05  
**Version:** 2.1 Enterprise Architecture  
**Architect:** WTSBill Engineering & Architecture Team  

---

## 1. Architectural Strategy & Design Principles

The expansion follows strict enterprise principles to ensure zero regression, high performance, and compliance:

1. **Leverage Verified Database Schema:**
   All 124 tables exist with proper columns and types. No invasive schema changes or migrations are needed.
2. **Double-Entry Financial Separation:**
   Pre-sales commitments (Sales Orders, Quotations, Delivery Challans) and pre-purchase orders (Purchase Orders) are commercial documents. They must NOT generate journal entries in the General Ledger until converted into confirmed Tax Invoices or Inward Purchase Bills.
3. **Dedicated Numbering Sequences:**
   - Tax Invoices: strictly use `InvoiceNumberService::acquireInvoiceNumber()`.
   - Sales Orders: use prefix `SO-YYYY-XXXX`.
   - Delivery Challans: use prefix `DC-YYYY-XXXX`.
   - Purchase Orders: use prefix `PO-YYYY-XXXX`.
4. **Tenant & Branch Security:**
   Every controller and query enforces `company_id = Session::getCompanyId()` and optional `branch_id = Session::getBranchId()`. All actions are guarded by `Gate::can()`.
5. **Continuous Regression Testing:**
   After each phase, automated test suites are executed via `tests/run_tests.php` to maintain a 100% pass rate.

---

## 2. Phase-by-Phase Implementation Roadmap

### Phase 2: CRM & Party 360° Profile Enhancement (Modules 1 & 4)
- **Customer CRM Enhancements:**
  - Multiple billing & shipping addresses via `customer_addresses`.
  - Credit limit enforcement and payment terms display.
  - Customer-specific notes and contact person management.
  - Updating `CustomerController` and `views/parties/customer_profile.php`.
- **Supplier 360° Profile Page:**
  - Implement `SupplierController::profile(Request $req, mixed $id)`.
  - View `views/parties/supplier_profile.php` matching the 360° customer profile layout:
    - 4 KPI cards (Total Inward Billed, Total Paid, Outstanding Balance, Overdue Bills).
    - Tabs: Overview, Purchase Bills, Debit Notes, Payment History, Supplier Ledger, Statement Export.
  - Route `GET /suppliers/{id}`, `GET /suppliers/{id}/statement/csv`.

### Phase 3: Sales Order Management Workflow (Module 2)
- **Lifecycle:** Quotation &rarr; Sales Order &rarr; Delivery Challan &rarr; Tax Invoice.
- **Sales Order Controller & Service:**
  - `app/Services/SalesOrderService.php` and `app/Controllers/SalesOrderController.php`.
  - Routes:
    - `GET /sales/orders` (Order list with status filters: PENDING, CONFIRMED, PROCESSING, DISPATCHED, COMPLETED, CANCELLED).
    - `GET /sales/orders/create` & `POST /sales/orders/create`.
    - `GET /sales/orders/{id}` (Detail view with fulfillment and payment tracking).
    - `GET /sales/orders/{id}/print` (Clean printable order sheet).
    - `POST /sales/orders/{id}/convert-to-invoice` (1-click conversion into draft/final invoice).
    - `POST /sales/orders/{id}/convert-to-challan` (Fulfillment challan generation).
    - `POST /sales/quotations/{id}/convert-to-order` (Accept quotation & convert to SO).
- **Views:**
  - `views/sales/orders/index.php`, `views/sales/orders/create.php`, `views/sales/orders/show.php`, `views/sales/orders/print.php`.

### Phase 4: Advanced Inventory, Warehouses & Stock Transfers (Module 3)
- **Multi-Warehouse Management:**
  - `app/Services/WarehouseService.php` and `app/Controllers/WarehouseController.php`.
  - Routes:
    - `GET /inventory/warehouses`, `POST /inventory/warehouses`.
    - `GET /inventory/transfers`, `POST /inventory/transfers`.
    - `POST /inventory/transfers/{id}/approve`, `POST /inventory/transfers/{id}/dispatch`, `POST /inventory/transfers/{id}/receive`.
  - Warehouse-wise stock balances using `stock_balances`.
  - Fast-Moving & Slow-Moving product velocity report and low-stock alert calculation.
- **Views:**
  - `views/inventory/warehouses.php`, `views/inventory/transfers.php`.

### Phase 5: Procurement Lifecycle (Purchase Orders, Goods Receipts & Returns) (Module 4)
- **Procurement Workflow:** Purchase Order &rarr; Goods Receipt &rarr; Inward Bill &rarr; Return / Debit Note.
- **Purchase Order Controller:**
  - `app/Controllers/PurchaseOrderController.php`.
  - Routes:
    - `GET /purchases/orders`, `GET /purchases/orders/create`, `POST /purchases/orders/create`.
    - `GET /purchases/orders/{id}`, `GET /purchases/orders/{id}/print`.
    - `POST /purchases/orders/{id}/convert-to-bill`.
  - Inward Purchase Returns & Debit Notes views and controller endpoints.
- **Views:**
  - `views/purchases/orders/index.php`, `views/purchases/orders/create.php`, `views/purchases/orders/show.php`.

### Phase 6: Payments, Customer Advances & Receivables Aging (Module 5)
- **Customer Advances:**
  - Record unallocated advances in `customer_advances`.
  - Draw down advance balances against newly generated invoices.
- **Accounts Receivable Aging Report:**
  - Route `GET /reports/receivables-aging` (Buckets: 0-30 days, 31-60 days, 61-90 days, 90+ days).
  - Drilldown per customer and CSV export.

### Phase 7: Complete Double-Entry Accounting (Trial Balance, Cash Book, Bank Book) (Module 6)
- **Accounting Statements:**
  - Trial Balance (`GET /reports/trial-balance`) with Dr/Cr verification.
  - Cash Book (`GET /accounting/cash-book`) for cash drawer transactions.
  - Bank Book (`GET /accounting/bank-book`) for bank transactions with reconciliation tags.
  - Cash Flow Statement (`GET /reports/cash-flow`).

### Phase 8: GST Management, Registers & GSTR-3B Preparation (Module 7)
- **GST Compliance:**
  - GST Sales Register (`GET /reports/gst-sales-register`).
  - GST Purchase Register (`GET /reports/gst-purchase-register`).
  - GSTR-3B Preparation Summary (`GET /reports/gstr-3b`).

### Phase 9: Business Analytics & Executive BI Dashboard (Module 8)
- Real-time Executive BI Dashboard (`GET /analytics/bi`) with profit margins, top products, top customers, and branch comparison.

### Phase 10: In-App Notification Centre & AI Business Assistant (Modules 18, 17, 4)
- Notification Center (`GET /notifications`, `POST /notifications/read-all`).
- AI Business Assistant endpoint (`POST /api/ai/query`) providing secure natural language insights.

---

## 3. Verification & Governance Strategy
- For each phase:
  1. Implement controller, service, repository, and routes.
  2. Implement frontend views following White + Blue design tokens.
  3. Write dedicated feature test suite under `tests/Feature/`.
  4. Integrate test into `tests/run_tests.php`.
  5. Run test suite to verify 100% pass rate.
  6. Update documentation: `docs/FILE_REGISTRY.md`, `docs/REBUILD_PROGRESS.md`, `docs/REGRESSION_TEST_RESULTS.md`.

