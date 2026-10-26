# WTSBill ERP — Business Logic & Calculation Invariant Map

**Document Version:** 1.0.0  
**Generated Date:** 2026-10-10  
**Scope:** Mathematical formulas, GST splits, rounding rules, accounting invariants, stock updates, validations, and multi-tenant isolation.

---

## 1. GST & Taxation Engine Calculations

WTSBill ERP implements the standard statutory tax framework for the Indian Goods and Services Tax (GST) regime.

### 1.1 Place of Supply & Inter vs Intra-State Invariant
The determination of whether a sale is Intra-State (CGST + SGST) or Inter-State (IGST) is governed by comparing the Supplier's state code against the Recipient's place of supply:

$$\text{Supply Type} = \begin{cases} \text{Intra-State (CGST + SGST)}, & \text{if } \text{Supplier State Code} = \text{Place of Supply} \\ \text{Inter-State (IGST)}, & \text{if } \text{Supplier State Code} \neq \text{Place of Supply} \end{cases}$$

- **Supplier State Code:** Extracted from active Company / Branch profile (Default: Maharashtra `27`).
- **Place of Supply:** Customer's billing/shipping state code (e.g. `27` for Maharashtra, `24` for Gujarat, `29` for Karnataka).

### 1.2 Line Item Tax Computation Formulas
For each line item $i$ in an Invoice, Quotation, Sales Order, or Purchase Bill:

1. **Gross Amount:**
   $$\text{Gross}_i = \text{Quantity}_i \times \text{UnitPrice}_i$$

2. **Item Discount:**
   $$\text{DiscountAmount}_i = \begin{cases} \text{Gross}_i \times \left(\frac{\text{DiscountRate}_i}{100}\right), & \text{if rate specified} \\ \text{DiscountAmount}_i, & \text{if flat amount} \end{cases}$$

3. **Taxable Value:**
   $$\text{TaxableValue}_i = \text{Gross}_i - \text{DiscountAmount}_i$$

4. **Tax Rate Splits:**
   - **Case A: Intra-State ($\text{is\_igst} = 0$):**
     $$\text{CGSTRate}_i = \frac{\text{GSTRate}_i}{2}, \quad \text{SGSTRate}_i = \frac{\text{GSTRate}_i}{2}, \quad \text{IGSTRate}_i = 0.00$$
     $$\text{CGSTAmount}_i = \text{round}\left(\text{TaxableValue}_i \times \frac{\text{CGSTRate}_i}{100}, 2\right)$$
     $$\text{SGSTAmount}_i = \text{round}\left(\text{TaxableValue}_i \times \frac{\text{SGSTRate}_i}{100}, 2\right)$$
     $$\text{IGSTAmount}_i = 0.00$$
   - **Case B: Inter-State ($\text{is\_igst} = 1$):**
     $$\text{CGSTRate}_i = 0.00, \quad \text{SGSTRate}_i = 0.00, \quad \text{IGSTRate}_i = \text{GSTRate}_i$$
     $$\text{CGSTAmount}_i = 0.00, \quad \text{SGSTAmount}_i = 0.00$$
     $$\text{IGSTAmount}_i = \text{round}\left(\text{TaxableValue}_i \times \frac{\text{IGSTRate}_i}{100}, 2\right)$$

5. **Line Total:**
   $$\text{LineTax}_i = \text{CGSTAmount}_i + \text{SGSTAmount}_i + \text{IGSTAmount}_i$$
   $$\text{LineTotal}_i = \text{TaxableValue}_i + \text{LineTax}_i$$

### 1.3 Document Header Totals & Auto Round-Off
Header aggregation computes total taxable turnover, total taxes, and rounds grand total to whole rupees:

1. **Sub Total (Taxable Turnover):**
   $$\text{SubTotal} = \sum_{i=1}^{n} \text{TaxableValue}_i$$

2. **Aggregated Taxes:**
   $$\text{TotalCGST} = \sum_{i=1}^{n} \text{CGSTAmount}_i, \quad \text{TotalSGST} = \sum_{i=1}^{n} \text{SGSTAmount}_i, \quad \text{TotalIGST} = \sum_{i=1}^{n} \text{IGSTAmount}_i$$
   $$\text{TotalTax} = \text{TotalCGST} + \text{TotalSGST} + \text{TotalIGST}$$

3. **Pre-Rounding Grand Total:**
   $$\text{RawTotal} = \text{SubTotal} + \text{TotalTax} - \text{HeaderDiscount} + \text{ShippingCharges}$$

4. **Statutory Round-Off:**
   $$\text{GrandTotal} = \text{round}(\text{RawTotal})$$
   $$\text{RoundOff} = \text{GrandTotal} - \text{RawTotal} \quad (-0.50 \le \text{RoundOff} \le +0.49)$$

---

## 2. Double-Entry Accounting Invariant & Automated Postings

Every financial transaction updates the General Ledger via balanced double-entry accounting journals:

### 2.1 The Fundamental Balance Invariant
For every journal entry, the sum of all debit line postings must identically equal the sum of all credit line postings:

$$\sum_{j=1}^{m} \text{DebitAmount}_j = \sum_{j=1}^{m} \text{CreditAmount}_j$$

Entries violating this condition are aborted and rolled back.

### 2.2 Standard Automated Ledger Postings Matrix

#### A. Tax Invoice Confirmation
| Account Name | Account Type | Debit / Credit | Amount |
| :--- | :--- | :--- | :--- |
| **Accounts Receivable (Customer)** | Current Asset | **DEBIT** | $\text{GrandTotal}$ |
| **Sales Revenue Account** | Operating Income | **CREDIT** | $\text{SubTotal}$ |
| **Output CGST Payable** | Current Liability | **CREDIT** | $\text{TotalCGST}$ |
| **Output SGST Payable** | Current Liability | **CREDIT** | $\text{TotalSGST}$ |
| **Output IGST Payable** | Current Liability | **CREDIT** | $\text{TotalIGST}$ |
| **Round-Off Ledger** | Indirect Expense/Income | **DR / CR** | $|\text{RoundOff}|$ |

#### B. Vendor Purchase Bill Confirmation
| Account Name | Account Type | Debit / Credit | Amount |
| :--- | :--- | :--- | :--- |
| **Purchase Inventory Account** | Direct Expense / Inventory | **DEBIT** | $\text{SubTotal}$ |
| **Input CGST Credit** | Current Asset (ITC) | **DEBIT** | $\text{TotalCGST}$ |
| **Input SGST Credit** | Current Asset (ITC) | **DEBIT** | $\text{TotalSGST}$ |
| **Input IGST Credit** | Current Asset (ITC) | **DEBIT** | $\text{TotalIGST}$ |
| **Accounts Payable (Supplier)** | Current Liability | **CREDIT** | $\text{GrandTotal}$ |

#### C. Customer Payment Voucher (Receipt)
| Account Name | Account Type | Debit / Credit | Amount |
| :--- | :--- | :--- | :--- |
| **Bank Account / Cash in Hand** | Current Asset | **DEBIT** | $\text{AmountReceived}$ |
| **Accounts Receivable (Customer)** | Current Asset | **CREDIT** | $\text{AmountReceived}$ |

#### D. Operational Expense Payment
| Account Name | Account Type | Debit / Credit | Amount |
| :--- | :--- | :--- | :--- |
| **Specific Expense Account** (e.g., Rent) | Operating Expense | **DEBIT** | $\text{TaxableExpense}$ |
| **Input CGST / SGST Credit** (if eligible) | Current Asset | **DEBIT** | $\text{ExpenseTax}$ |
| **Bank Account / Cash in Hand** | Current Asset | **CREDIT** | $\text{TotalPaid}$ |

---

## 3. Inventory Movements & Stock Balance Invariant

### 3.1 Stock Balance Formula
For every product $P$ in warehouse $W$:

$$\text{CurrentBalance}(P, W) = \text{OpeningQuantity} + \sum \text{Stock IN} - \sum \text{Stock OUT}$$

### 3.2 Inventory Movement Events
| Event | Movement Type | Direction | Quantity Impact | Reference Entity |
| :--- | :--- | :--- | :--- | :--- |
| Purchase Bill / Goods Receipt | `PURCHASE` | `IN` | $+Q$ | `purchases.id` |
| Sales Invoice / Delivery Challan | `SALE` | `OUT` | $-Q$ | `invoices.id` |
| Sales Return / Credit Note | `SALES_RETURN` | `IN` | $+Q$ | `credit_notes.id` |
| Purchase Return / Debit Note | `PURCHASE_RETURN` | `OUT` | $-Q$ | `debit_notes.id` |
| Stock Adjustment Positive | `ADJUSTMENT_ADD` | `IN` | $+Q$ | `stock_adjustments.id` |
| Stock Adjustment Negative | `ADJUSTMENT_SUB` | `OUT` | $-Q$ | `stock_adjustments.id` |

### 3.3 Negative Stock Prevention Invariant
When `companies.allow_negative_stock == 0`:

$$\text{Assert: } \text{AvailableQuantity}(P, W) \ge \text{RequestedQuantity}$$

If $\text{AvailableQuantity} < \text{RequestedQuantity}$, the order/invoice transaction is rejected with an `InsufficientStockException`.

---

## 4. Sequential Document Numbering Engine

Document numbering follows strict transactional sequence rules configured in `document_numbering_configs`:

### 4.1 Number Formatting Syntax
$$\text{DocumentNumber} = \text{Prefix} + \text{str\_pad}(\text{CurrentNumber} + 1, \text{Padding}, \text{'0'}, \text{STR\_PAD\_LEFT}) + \text{Suffix}$$

- **Invoice Example:** Prefix: `INV-`, Padding: 4, Current: 450 $\rightarrow$ `INV-0451`
- **Quotation Example:** Prefix: `QT-`, Padding: 4, Current: 12 $\rightarrow$ `QT-0013`
- **Purchase Example:** Prefix: `PO-`, Padding: 4, Current: 88 $\rightarrow$ `PO-0089`

### 4.2 Concurrency & Gapless Invariant
To prevent race conditions, the sequence counter must be acquired and locked inside a database transaction:
```sql
SELECT current_number FROM document_numbering_configs 
WHERE company_id = :company_id AND document_type = :doc_type 
FOR UPDATE;
```
After successful document insertion, the counter increments atomically:
```sql
UPDATE document_numbering_configs 
SET current_number = current_number + 1 
WHERE company_id = :company_id AND document_type = :doc_type;
```

---

## 5. Validation Rules & Data Integrity Checks

| Field / Domain | Format / Regex / Condition | Error Action |
| :--- | :--- | :--- |
| **GSTIN** | `^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$` | Reject with `Invalid GSTIN Format` |
| **PAN** | `^[A-Z]{5}[0-9]{4}[A-Z]{1}$` | Must match characters 3–12 of GSTIN |
| **State Code** | Must be 2-digit numeric (`01` to `38`) | Must match first 2 digits of GSTIN |
| **HSN/SAC Code** | 4, 6, or 8 digits (`^[0-9]{4,8}$`) | Standard HSN validation |
| **Email** | `filter_var($email, FILTER_VALIDATE_EMAIL)` | Reject invalid format |
| **Phone** | Standard 10-digit mobile or STD telephone | Validation warning |
| **Unit Price** | $\text{UnitPrice} \ge 0.00$ | Reject negative pricing |
| **Quantity** | $\text{Quantity} > 0.000$ | Reject zero or negative line quantity |

---

## 6. Multi-Tenant Isolation Architecture

1. **Company Scope:** All queries on business tables MUST append:
   ```sql
   WHERE company_id = :company_id
   ```
2. **Branch Scope:** If `companies.branch_management_enabled == 1`, queries further scope:
   ```sql
   AND (branch_id = :branch_id OR branch_id IS NULL)
   ```
3. **Session Verification:** `company_id` is never trusted from client input; it is strictly loaded from the authenticated user's session (`$_SESSION['current_company_id']`).
