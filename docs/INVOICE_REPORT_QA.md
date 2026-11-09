# WTSBill ERP — Invoice Payment Status Report QA Verification Matrix

## 1. Test Suite Summary

- **Test Suite**: `\Tests\Feature\InvoicePaymentStatusReportTest`
- **Execution Script**: `c:\xampp\php\php.exe tests\run_tests.php`
- **Total Suite Assertions**: 15 / 15 Passed (100%)
- **System Total Assertions**: 833 / 833 Passed (100%)
- **Test Framework**: Native PHP 8+ Architectural Test Runner

---

## 2. Comprehensive QA Test Matrix (All 13 Scenarios)

| Scenario # | Test ID | Target Capability | Description / Verification | Expected Status / Result | Status |
| :---: | :---: | :--- | :--- | :--- | :---: |
| **01** | `INVREP-03` | Invoice with No Payment | Created invoice with ₹11,800.00 total. No payment allocations recorded. | `UNPAID`<br>Paid: ₹0.00<br>Balance: ₹11,800.00 | **PASS** |
| **02** | `INVREP-04` | Single Partial Payment | Created invoice with ₹23,600.00 total. Allocated ₹10,000.00 payment. | `PARTIALLY_PAID`<br>Paid: ₹10,000.00<br>Balance: ₹13,600.00 | **PASS** |
| **03** | `INVREP-05` | Multiple Partial Payments | Created invoice with ₹35,400.00 total. Applied two allocations: ₹5,400.00 (UPI) + ₹10,000.00 (Bank). | `PARTIALLY_PAID`<br>Paid: ₹15,400.00<br>Balance: ₹20,000.00 | **PASS** |
| **04** | `INVREP-06` | Fully Paid Invoice | Created invoice with ₹11,800.00 total. Allocated ₹11,800.00 matching payment. | `PAID`<br>Paid: ₹11,800.00<br>Balance: ₹0.00 | **PASS** |
| **05** | `INVREP-07` | Overdue Unpaid Invoice | Invoice with past due date (`CURDATE() - 10 days`) and ₹0.00 paid. | `is_overdue = true`<br>`UNPAID`<br>Balance: ₹5,900.00 | **PASS** |
| **06** | `INVREP-08` | Overdue Partially Paid | Invoice with past due date, ₹9,440.00 total, and ₹4,440.00 paid. | `is_overdue = true`<br>`PARTIALLY_PAID`<br>Balance: ₹5,000.00 | **PASS** |
| **07** | `INVREP-09` | Cancelled Invoice Exclusion | Invoice marked `status = 'CANCELLED'`. Must be excluded from active report. | Excluded from table & KPI totals | **PASS** |
| **08** | `INVREP-10` | Cancelled Payment Exclusion | Invoice created with payment marked `status = 'CANCELLED'`. | Payment excluded<br>`UNPAID`<br>Paid: ₹0.00<br>Balance: ₹8,260.00 | **PASS** |
| **09** | `INVREP-11` | Customer with Multiple Invoices | Customer having 8 distinct invoices across branches. | All 8 invoices rendered independently without row collision | **PASS** |
| **10** | `INVREP-13` | Filter by Date & Due Date | Date range filtering narrows base dataset to qualifying period. | Exact period matches returned | **PASS** |
| **11** | `INVREP-12` | Filter by Operating Branch | Filter by Branch 2 isolates branch 2 documents and excludes branch 1. | Only Branch 2 invoices returned | **PASS** |
| **12** | `INVREP-13` | Multi-Criteria Filtering | Filter combining `customer_name`, `payment_status = partially_paid`, and `overdue = overdue`. | Exactly 1 qualifying record returned (`INV-QA-OVD-PART`) | **PASS** |
| **13** | `INVREP-14`<br>`INVREP-15` | Excel (.xlsx) Export & Reconciliation | Genuine `.xlsx` ZIP container generated containing 9 columns, KPI block, and verified totals. | Valid `.xlsx`<br>Headers match<br>XML totals == DB totals | **PASS** |

---

## 3. Security & Access Control Assertions

| Test ID | Security Check | Verification Details | Result |
| :---: | :--- | :--- | :---: |
| `INVREP-01` | Authentication Wall | Unauthenticated request to `/reports/invoice-payment-status` and `/reports/invoice-payment-status/export` strictly blocked. | **PASS** |
| `INVREP-14` | Formula Injection Defense | String cells starting with `=`, `+`, `-`, or `@` prepended with `'` to prevent spreadsheet command execution. | **PASS** |
| `INVREP-02` | UI XSS Prevention | Customer names, invoice numbers, and phone numbers escaped via `htmlspecialchars()` in HTML and XML sanitizers. | **PASS** |

---

## 4. UI & Responsive Design Verification

1. **KPI Summary Cards**:
   - 4 summary cards (Pending Invoices, Paid Invoices, Partially Paid Invoices, Total Net Outstanding) rendered with correct amounts and counts.
2. **Dynamic Filter Form**:
   - Filter inputs for dates, due dates, customer name, mobile, branch, status, overdue flag, and search query.
   - Includes Clear Filters button returning to default state.
3. **Data Table Presentation**:
   - Exact 9 columns with sortable headers.
   - Direct hyperlinks to view invoice details (`/sales/invoices/{id}`).
   - Overdue badge highlights invoices that require urgent collection follow-up.
   - Filtered totals footer provides immediate reconciliation for the active view.
4. **Single-Expanded Sidebar Navigation**:
   - Reports group automatically expands when on `/reports/invoice-payment-status`.
   - All other accordion groups (Sales, Purchases, Items, Parties, Finance, GST, Settings) remain smoothly collapsed.

