# WTSBill ERP — Bug Tracker & Defect Resolution Log

**Document Reference:** `DOC-QA-BUGTRACKER-001`  
**Last Updated:** 2026-11-06  
**Auditor / Lead QA:** Senior QA Engineer & PHP Application Architect  
**Status:** ALL REPORTED DEFECTS RESOLVED  

---

## 1. Defect Summary & Resolution Dashboard

| Defect ID | Severity | Category | Description | Root Cause | Status | Fixed In |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **BUG-01** | High | Accounting | Trial balance opening equity double-entry mismatch | Historical contra-entry balance computation did not net debits against credits before trial aggregation | **RESOLVED** | `ExtendedAccountingService.php` |
| **BUG-02** | Medium | Inventory | Category code auto-generation collision on similar names | Substring prefix generation lacked uniqueness fallback for identical initial letters | **RESOLVED** | `CategoryService.php` |
| **BUG-03** | Medium | UI / UX | Native browser `alert()` and `confirm()` modals used in legacy views | Legacy JavaScript snippets relied on synchronous browser dialogs, violating ERP UI standards | **RESOLVED** | `GlobalPopupFixTest.php` & Views |
| **BUG-04** | High | Payments | Multi-installment partial payment overpayment edge case | Missing server-side check allowing payment allocation greater than `amount_due` | **RESOLVED** | `PaymentController.php` |
| **BUG-05** | Medium | Invoicing | Invoice number gap on invoice deletion | Deleted numbers were not prioritized before incrementing global sequence counter | **RESOLVED** | `InvoiceNumberService.php` |
| **BUG-06** | Low | Settings | Communication & Document Sharing UI displayed non-working WhatsApp Cloud API form | Static mock form present in settings without configured Meta API backend | **RESOLVED** | `SettingsController.php` & `views/settings/index.php` |
| **BUG-07** | Medium | CRM | Missing live payment status filters on Customer Profile invoice list | Customer invoice view listed all invoices without instant status filtering pills (`PAID`, `PARTIAL`, `UNPAID`) | **RESOLVED** | `views/parties/customer_profile.php` |
| **BUG-08** | High | Architectural | Redundant AI Assistant routes, controller, and sidebar menu items | AI Assistant feature deprecated and required complete removal to declutter ERP | **RESOLVED** | `config/menu.php`, `routes/web.php` |
| **BUG-09** | Low | Test Harness | NotificationService method signature mismatch in QA suite | Test passed associative array instead of required scalar parameters to `createNotification()` | **RESOLVED** | `NotificationCenterHubTest.php` |
| **BUG-10** | Medium | Invoicing | WhatsApp share button without PDF attachment capability caused user confusion | Native web WhatsApp Click-to-Chat protocol (`wa.me`) only supports text payload, not direct PDF binary attachment | **RESOLVED** | Views & Share Architecture |

---

## 2. Detailed Defect Investigations & Resolutions

### BUG-01: Trial Balance Opening Balance Double-Entry Integrity
- **Module:** Double-Entry Accounting (`/accounting/trial-balance`)
- **Severity:** High
- **Symptom:** In specific branch databases with manual opening balance journal vouchers, the Trial Balance showed a minor difference between total debits and total credits.
- **Root Cause Analysis:** The SQL aggregation queried debit balances and credit balances independently. When an equity account had an opening credit balance and subsequent debit adjustments, the debit balance was aggregated into total assets/expenses instead of offsetting the equity balance.
- **Resolution Applied:** Refactored `ExtendedAccountingService::getTrialBalance()` to calculate net normal balances according to account classification:
  - Assets & Expenses: `Balance = Debits - Credits` (Debit balance)
  - Liabilities, Equity & Income: `Balance = Credits - Debits` (Credit balance)
  - Zero balances are cleanly suppressed, and both debit and credit columns strictly balance (`total_debits === total_credits`).
- **Regression Assertion:** `ACC-03` strictly asserts `total_debits == total_credits && is_balanced === true`.

---

### BUG-02: Product Category Code Collision
- **Module:** Inventory & Products (`/categories`)
- **Severity:** Medium
- **Symptom:** Creating two product categories with similar starting letters (e.g., "Hardware" and "Hand Tools") resulted in duplicate code conflicts when auto-generation was active.
- **Root Cause Analysis:** Auto-generator used `strtoupper(substr($name, 0, 3))` without querying for existing codes in the current tenant.
- **Resolution Applied:** Implemented unique sequential suffixing: `CAT-HRD-01`, `CAT-HRD-02`, checking against database unique indexes before saving.
- **Regression Assertion:** Section 8 Integration Tests pass with unique category creation.

---

### BUG-03: Native Browser `alert()` and `confirm()` Dialog Usage
- **Module:** Global UI/UX Architecture
- **Severity:** Medium
- **Symptom:** Older JavaScript files in legacy views triggered blocking native browser popups (`alert("Payment saved")` or `confirm("Are you sure?")`), which disrupt user workflow, look unpolished, and fail automated accessibility audits.
- **Root Cause Analysis:** Legacy template scripts written before the standardized notification and modal system was introduced.
- **Resolution Applied:** Completely audited all views and replaced native popups with:
  - Inline accessible toast notifications (`showToast(message, 'success'|'danger')`).
  - Styled HTML5 `<dialog>` or custom Tailwind/CSS overlay confirmation modals.
- **Regression Assertion:** `GlobalPopupFixTest.php` scans all PHP view files across the codebase, asserting zero unescaped `alert(` or `confirm(` statements.

---

### BUG-04: Multi-Installment Partial Payment Overpayment Guard
- **Module:** Payments & Receipts (`/payments`)
- **Severity:** High
- **Symptom:** Attempting to record a payment installment higher than the remaining balance on an invoice could cause negative balance due or incorrect ledger entries.
- **Root Cause Analysis:** `PaymentController` relied on client-side max attribute validation without enforcing strict server-side boundary checks within the database transaction.
- **Resolution Applied:**
  ```php
  if ($amount > $invoice['amount_due']) {
      return Response::json([
          'success' => false,
          'message' => 'Payment amount exceeds outstanding balance due.'
      ], 422);
  }
  ```
- **Regression Assertion:** Verified in Section 40 (`PARTIAL-04`: Payment of ₹30,000 on remaining ₹25,000 balance strictly rejected with HTTP 422).

---

### BUG-05: Invoice Number Sequence Gap Following Deletion
- **Module:** Sales & Billing (`/invoices`)
- **Severity:** Medium
- **Symptom:** When a draft or erroneously created invoice was deleted, its sequential invoice number was skipped, creating a gap in invoice sequencing.
- **Root Cause Analysis:** The invoice number generator always calculated `MAX(sequence_number) + 1` without inspecting the pool of deleted or unused numbers.
- **Resolution Applied:** Enhanced `InvoiceNumberService::getNextInvoiceNumber()`:
  - First queries `deleted_invoice_numbers` table (or checks sequence gaps) within an exclusive database transaction lock (`SELECT ... FOR UPDATE`).
  - Assigns the lowest available reusable number if found; otherwise increments the branch sequence.
  - Ensures uniqueness and full branch isolation.
- **Regression Assertion:** Verified in Section 51 (`INVNUM-01` to `INVNUM-08`).

---

### BUG-06: Communication & Document Sharing Setting Form
- **Module:** System Settings (`/settings`)
- **Severity:** Low
- **Symptom:** The "Communication & Document Sharing" tab displayed input fields for WhatsApp Cloud API tokens and SMTP credentials that were confusing to users since cloud credentials were unconfigured.
- **Root Cause Analysis:** Extraneous feature tab in settings UI that was not aligned with the standalone click-to-chat architecture.
- **Resolution Applied:** Safely removed the `Communication & Document Sharing` tab button and form container from `views/settings/index.php` and cleaned up `SettingsController.php` tab handling.
- **Regression Assertion:** Verified via clean settings page rendering and clean test execution.

---

### BUG-07: Customer Profile Invoice Status Filtering
- **Module:** Parties & CRM (`/customers/{id}`)
- **Severity:** Medium
- **Symptom:** When viewing a customer's 360° profile with dozens or hundreds of historical invoices, users could not easily filter by payment status (`PAID`, `PARTIAL`, `UNPAID`).
- **Root Cause Analysis:** Invoices card rendered a static table without dynamic client-side or server-side filtering pills.
- **Resolution Applied:** Added live filter pills (`All`, `Unpaid`, `Partial Payment`, `Paid`) and search inputs to both the main summary card and the Invoices tab in `views/parties/customer_profile.php`. Synced with URL query parameters for bookmarkable filter state.
- **Regression Assertion:** Verified in Section 54 (`CRM-01` to `CRM-10`).

---

### BUG-08: AI Assistant Decommissioning & Purging
- **Module:** Whole Application
- **Severity:** High
- **Symptom:** AI Assistant links in navigation and open API endpoints consumed memory and created an unfinished/distracting element in the core accounting ERP.
- **Root Cause Analysis:** AI feature set was experimental and unnecessary for production double-entry bookkeeping and GST filing.
- **Resolution Applied:** Completely removed routes, controller (`AiAssistantController.php`), service (`AiAssistantService.php`), views (`views/ai/`), and navigation item (`config/menu.php`). Replaced test suite with explicit removal verifications.
- **Regression Assertion:** `AIREM-01`, `AIREM-02`, and `AIREM-03` assert HTTP 404 on removed routes and zero AI menu items.

---

### BUG-09: Test Runner Type Signature in NotificationCenterHubTest
- **Module:** Testing Infrastructure
- **Severity:** Low
- **Symptom:** Test execution threw a `TypeError: Argument #1 ($companyId) must be of type int, array given`.
- **Root Cause Analysis:** Test mock passed an associative array into `createNotification()` instead of positional scalar arguments.
- **Resolution Applied:** Corrected argument list in `tests/Feature/NotificationCenterHubTest.php` to match `createNotification(int $companyId, ?int $userId, string $title, string $message, string $category, string $priority, ?string $actionUrl)`.
- **Regression Assertion:** Section 61 tests now pass 100%.

---

### BUG-10: WhatsApp Share Button Clarification
- **Module:** Invoices & Billing
- **Severity:** Medium
- **Symptom:** Users clicking "WhatsApp PDF" expected a PDF binary file to be automatically transmitted through WhatsApp Web, but only pre-filled text and payment link appeared.
- **Root Cause Analysis:** WhatsApp Web Click-to-Chat protocol (`https://wa.me/?text=...`) does not support automated binary file attachments due to browser sandboxing and WhatsApp's official API specifications.
- **Resolution Applied:** Clarified UI action labels to standard "Share via WhatsApp" and provided invoice download link within the message text so recipients can view/download the signed PDF directly.
- **Regression Assertion:** Section 45 (`COMM-01` to `COMM-06`) verifies click-to-chat URL formation.

---

## 3. Bug Prevention & Quality Measures

1. **Automated Continuous Regression:** All 780 tests execute against the live MySQL database, ensuring zero regressions on every commit.
2. **Transaction Isolation:** Financial transactions (payments, invoice creation, stock transfers) are wrapped in `Database::transaction()` blocks to eliminate partial state corruption.
3. **Strict Validation Pipeline:** All requests pass through centralized validation filters ensuring numeric ranges, string sanitization, and tenant authorization.
4. **Zero-Popup Architecture:** Strictly enforced modern UI standards with zero blocking browser popups.

