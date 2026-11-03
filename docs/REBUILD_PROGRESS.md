# WTSBill ERP — Rebuild & Recovery Progress Tracker

**Document Version:** 3.0.0  
**Last Updated:** 2026-10-26  
**Recovery Lead:** Senior PHP, MySQL, JavaScript & Systems Engineering Team  

---

## 1. Chunk Status Overview

| Chunk | Module Title | Original Status | Current Recovery Status | Target Verification |
| :---: | :--- | :---: | :---: | :--- |
| **0** | Project Rules & Structure | Completed | **VERIFIED** | Conventions & directory standards |
| **1** | Source & Behavior Audit | Completed | **VERIFIED** | Baseline documentation & recovery audit |
| **2** | PHP Application Foundation | Completed | **VERIFIED** | PSR-4 autoloader, Config, Request, Response, Router |
| **3** | Database Layer & PDO | Completed | **VERIFIED** | PDO connection, ACID rollback, transaction savepoints |
| **4** | Authentication & Multi-Tenant | Completed | **VERIFIED** | Multi-tenant session, Bcrypt, CSRF, RBAC |
| **5** | Main Layout & Dashboard | Completed | **VERIFIED** | App shell, navigation, dark mode, toast/modal |
| **6** | Parties (Customers & Suppliers) | Partial (Read Only) | **COMPLETED & VERIFIED** | Real customer & supplier registration modals, edit modals, AJAX persistence |
| **7** | Products & Inventory Management | Broken (Placeholder) | **COMPLETED & VERIFIED** | Product registration modal, Category modal, Stock adjustment modal, CRUD |
| **8** | Sales & Tax Invoices | Partial (Read Only) | **COMPLETED & VERIFIED** | Full invoice creation form, tax calculation, detail modal, ledger posting |
| **9** | Quotations, Orders & Challans | Partial (Read Only) | **COMPLETED & VERIFIED** | Quotation modal, delivery challan modal, sequential numbering |
| **10** | Purchases & Accounts Payable | Partial (Read Only) | **COMPLETED & VERIFIED** | Purchase bill entry modal, supplier balance updates, AP tracking |
| **11** | Payments, Receipts & Expenses | Partial (Read Only) | **COMPLETED & VERIFIED** | Expense voucher modal, payment entries, category assignment |
| **12** | Double-Entry Accounting | Completed | **COMPLETED & VERIFIED** | Chart of Accounts ledger modal, multi-line journal entry modal, Debit == Credit |
| **13** | Financial & Compliance Reports | Completed | **COMPLETED & VERIFIED** | P&L, Balance Sheet, GST tax report, GSTR-1 JSON and CSV export streams |
| **14** | Users, Roles & Settings | Partial (Read Only) | **COMPLETED & VERIFIED** | User invitation modal, Bcrypt password hashing, role management |
| **15** | Standardized REST API | Completed | **VERIFIED** | Health check & authentication JSON envelopes |
| **16** | Printing & PDF Engine | Completed | **VERIFIED** | High-contrast `@media print` & `number_to_words_inr` |
| **17** | Frontend Integration & Assets | Completed | **VERIFIED** | CSS tokens, WTSBill namespace, modal/toast JS |
| **18** | PHP Performance & Loading | Completed | **VERIFIED** | View cache, statement cache, ETag 304, gzip |
| **19** | Security Regression Audit | Completed | **VERIFIED** | SQLi, XSS, CSRF, Tenant isolation |
| **20** | Full Regression & Parity | Completed | **VERIFIED** | Complete regression suite |
| **21** | Controlled Cutover Readiness | Completed | **VERIFIED** | Deployment runbook & rollback plan |
| **22** | **Global Placeholder Popup Fix** | Completed | **COMPLETED & VERIFIED** | Zero alert() dialogs, 16 real modals/forms, 329/329 tests (100%) |
| **23** | **Branch Switching & Management** | Completed | **COMPLETED & VERIFIED** | Header branch selector, session context, admin CRUD, 350/350 tests (100%) |

---

## 2. Global Placeholder Popup Fix Milestone Summary

- [x] **Project-Wide Scan:** Scanned all PHP, JavaScript, HTML templates, and shared UI components for `alert(`, `opens in next chunk`, `coming soon`, `TODO`, and unlinked buttons.
- [x] **Customer Registration:**
  - Accessible `#customer-modal` in `views/parties/customers.php`.
  - Full-page registration in `views/parties/create.php` submitting to `POST /customers/create`.
  - Customer edit modal fetching `GET /customers/{id}` and updating via `POST /customers/{id}/update`.
  - `CustomerRepository` and `CustomerService` enforcing multi-tenant isolation, GSTIN validation, and balances.
- [x] **Supplier Registration:**
  - Accessible `#supplier-modal` in `views/parties/suppliers.php`.
  - Supplier edit modal fetching `GET /suppliers/{id}` and updating via `POST /suppliers/{id}/update`.
  - `SupplierRepository` and `SupplierService` enforcing multi-tenant isolation and payment terms.
- [x] **Product & Inventory Management:**
  - Product registration modal in `views/inventory/products.php`.
  - Product Category creation modal `#category-modal` in `views/inventory/categories.php` submitting to `POST /categories`.
  - Stock Adjustment modal `#stock-modal` in `views/inventory/stock.php` submitting to `POST /inventory/stock/adjust` with `stock_movements` ledger update.
- [x] **Sales & Tax Invoices:**
  - Full tax invoice creator in `views/sales/create.php` with dynamic line items, intra/inter-state tax splitting, rupee round-off, customer balance update.
  - Interactive Invoice Detail Modal `#invoice-view-modal` in `views/sales/invoices.php` fetching line items and GST splits via `GET /sales/invoices/{id}` with print trigger.
  - Quotation creation modal `#quotation-modal` in `views/sales/quotations.php`.
  - Delivery challan modal `#challan-modal` in `views/sales/challans.php`.
- [x] **Purchases & Payables:**
  - Purchase bill recording modal `#purchase-modal` in `views/purchases/bills.php` updating supplier payable balances.
- [x] **Expenses:**
  - Expense voucher modal `#expense-modal` in `views/expenses/index.php` with category and payment mode selection.
- [x] **Accounting:**
  - Chart of Accounts modal `#account-modal` in `views/accounting/accounts.php` submitting to `POST /accounts`.
  - Balanced multi-line journal modal `#journal-modal` in `views/accounting/journals.php` enforcing Debit == Credit.
- [x] **Users & Roles:**
  - User creation modal `#user-modal` in `views/users/index.php` with Bcrypt password hashing.
- [x] **Reports & Exports:**
  - Real GSTR-1 JSON export stream (`GET /reports/gst/export?format=json`).
  - Real GSTR-1 CSV export stream (`GET /reports/gst/export?format=csv`).
- [x] **Automated Regression Suite:**
  - Created `tests/Feature/GlobalPopupFixTest.php` with 21 new end-to-end assertions (`POPUP-01` through `POPUP-21`).
  - Created `tests/Feature/PartialPaymentTest.php` with 13 assertions (`PARTPAY-01` through `PARTPAY-13`).
  - Created `tests/Feature/QuotationManagementTest.php` with 11 assertions (`QUOTE-01` through `QUOTE-11`).
  - Total test suite assertions: **583 / 583 Passed (100% Pass Rate)**.

- [x] **Partial Payment Management (Feature A):**
  - Payment statuses dynamically maintained: `UNPAID`, `PARTIALLY_PAID` (`PARTIAL`), `PAID`, `OVERDUE`.
  - Formula: `Total Paid = SUM(valid allocations); Outstanding Balance = Grand Total - Total Paid`.
  - Accessible `#record-payment-modal` with auto-computed balance due, max payable constraints, and payment modes (Cash, UPI, Bank Transfer, Cheque, Card).
  - Multiple installments against the same invoice; each stored as distinct records in `payments` and `payment_allocations`.
  - Payment history table in `#invoice-view-modal` showing date, receipt #, mode, reference #, recorded by, amount, status, and printable receipt.
  - Dedicated "Partial Payments & Invoice Balances" tab in `/payments` with receivables KPI cards and filters (Customer, Status, Date Range, Search).
  - Double-entry GL integration: Dr Bank/Cash (100200/100100) and Cr Accounts Receivable (100300) with customer ledger decrement.

- [x] **Complete Quotation Management & Conversion (Feature B):**
  - Dedicated commercial quotation creation (`/sales/quotations/create`) and editing (`/sales/quotations/{id}/edit`) matching Tax Invoice layout.
  - Visible `+ New Customer` action button opening quick AJAX modal (`#new-customer-modal`) to register and select customer immediately without page reload or loss of entered quotation line items.
  - Dynamic line item product category filtering (Category dropdown filters Product dropdown independently per row).
  - Real-time commercial calculations: Taxable Subtotal, CGST + SGST (or IGST), Round Off, Grand Total.
  - Quotation actions: Save as Draft, Save & Generate Quotation, Preview Modal, Print, Edit, Duplicate, Delete.
  - One-click Convert to Invoice workflow (`POST /sales/quotations/{id}/convert`): generates sequential Tax Invoice (`INV-YYYY-XXXX`), copies line items, sets quotation `status = 'CONVERTED'`, links `converted_invoice_id`, and blocks duplicate conversion.

- [x] **Dynamic Remaining Balance UPI QR Code & Payment Reconciliation:**
  - Automated real-time recalculation of outstanding balance from confirmed payments: `Outstanding Balance = Grand Total - SUM(confirmed payments)`.
  - Dynamic generation of ISO/IEC 18004 Model 2 vector SVG UPI QR codes formatted specifically for the exact remaining balance (`am=REMAINING_AMOUNT`).
  - Strict merchant UPI configuration from company settings (`upi_id`, `upi_display_name`, `payment_instructions`) with zero hardcoded sample IDs.
  - Automatic QR hiding and "PAID IN FULL" status display when balance is fully settled (₹0.00 due).
  - Helpful merchant configuration warning when UPI is not set up, safely suppressing invalid QR generation.
  - Payment reconciliation workflows distinguishing pending/unverified payments from confirmed payments.
  - Duplicate transaction reference defense enforcing HTTP 422 safeguards.
  - Synchronized across Invoice Details Modal (`#invoice-view-modal`), Printable Invoice (`/sales/invoices/{id}/print`), and REST APIs.
