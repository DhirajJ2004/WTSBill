# WTSBill ERP — Clean Demo Dataset Report

**Date:** November 26, 2026  
**Company:** `DEMO Apex Solutions Pvt Ltd` (Fictional GSTIN: `27DEMO00001Z1Z5`)  
**Scope:** Internally consistent, clearly labeled `DEMO` records across all ERP modules.

---

## 1. Demo Organization, Branches & Bank Accounts

| Entity | Name | Details |
|---|---|---|
| **Company** | `DEMO Apex Solutions Pvt Ltd` | State: Maharashtra (`27`), Currency: `INR`, FY Start: `2026-04-01` |
| **Branch 1 (Main)** | `DEMO Head Office - Mumbai` | Code: `HQ-MUM`, Mumbai, MH (`27`) |
| **Branch 2** | `DEMO Regional Hub - Pune` | Code: `BR-PUN`, Pune, MH (`27`) |
| **Bank Account 1** | `DEMO Primary Current A/C` | `DEMO National Bank`, Opening Balance: ₹ 2,50,000.00 |
| **Bank Account 2** | `DEMO Petty Cash` | Cash Drawer, Opening Balance: ₹ 25,000.00 |

---

## 2. Demo Users & Access Roles

Passwords are hashed using PHP `PASSWORD_BCRYPT`. **Change all temporary passwords immediately upon first production login.**

| ID | Name | Email | Role | Status |
|---|---|---|---|---|
| 1 | `Demo Administrator` | `admin@wtsbill.demo` | `ADMIN` (Full Access, Branches 1 & 2) | Active |
| 2 | `Demo Accountant` | `accountant@wtsbill.demo` | `ACCOUNTANT` (Finance, GST, Reports) | Active |
| 3 | `Demo Sales Executive` | `sales@wtsbill.demo` | `SALES_EXECUTIVE` (Sales & Customers only) | Active |

---

## 3. Master Entities Created

- **5 Demo Customers:**
  1. `DEMO Horizon Tech Ltd` (Maharashtra `27`) — Balance: ₹ 0.00
  2. `DEMO Sterling Logistics` (Maharashtra `27`) — Balance: ₹ 13,600.00
  3. `DEMO Crestline Retail` (Karnataka `29` — IGST) — Balance: ₹ 17,700.00
  4. `DEMO Beacon Manufacturing` (Maharashtra `27`) — Balance: ₹ 0.00
  5. `DEMO Summit Enterprises` (Gujarat `24` — IGST) — Balance: ₹ 0.00
- **3 Demo Suppliers:**
  1. `DEMO Vanguard Components` (Maharashtra `27`) — Balance: ₹ 0.00
  2. `DEMO Meridian Supplies` (Maharashtra `27`) — Balance: ₹ 5,900.00
  3. `DEMO Zenith Industrial` (Delhi `07`) — Balance: ₹ 0.00
- **3 Categories & 10 Demo Products (with Opening Stock & Stock Movements):**
  - `DEMO Electronics` (4 items: LED Smart Panel, Copper Cable Roll, Circuit Breaker, Digital Multimeter)
  - `DEMO Hardware` (3 items: Stainless Bolt Set, Industrial Valve 2in, Heavy Duty Wrench Kit)
  - `DEMO Office Supplies` (3 items: A4 Copier Paper Box, Thermal Receipt Rolls, Barcode Label Roll)

---

## 4. Transactional Demo Records (Internally Consistent)

| Module | Document # | Party | Total | Paid | Due | Status |
|---|---|---|---|---|---|---|
| **Invoice 1** | `INV-DEMO-001` | DEMO Horizon Tech Ltd | ₹ 11,800.00 | ₹ 11,800.00 | ₹ 0.00 | `PAID` |
| **Invoice 2** | `INV-DEMO-002` | DEMO Sterling Logistics | ₹ 23,600.00 | ₹ 10,000.00 | ₹ 13,600.00 | `PARTIAL` |
| **Invoice 3** | `INV-DEMO-003` | DEMO Crestline Retail | ₹ 17,700.00 | ₹ 0.00 | ₹ 17,700.00 | `UNPAID` |
| **Quotation 1** | `QT-DEMO-001` | DEMO Beacon Manufacturing | ₹ 10,620.00 | — | — | `SENT` |
| **Quotation 2** | `QT-DEMO-002` | DEMO Summit Enterprises | ₹ 5,192.00 | — | — | `ACCEPTED` |
| **Purchase 1** | `PB-DEMO-001` | DEMO Vanguard Components | ₹ 11,800.00 | ₹ 11,800.00 | ₹ 0.00 | `PAID` |
| **Purchase 2** | `PB-DEMO-002` | DEMO Meridian Supplies | ₹ 5,900.00 | ₹ 0.00 | ₹ 5,900.00 | `UNPAID` |
| **Credit Note** | `CN-DEMO-001` | DEMO Horizon Tech Ltd (`INV-DEMO-001`) | ₹ 1,180.00 | — | — | `ISSUED` |
| **Debit Note** | `DN-DEMO-001` | DEMO Vanguard Components (`PB-DEMO-001`) | ₹ 1,180.00 | — | — | `ISSUED` |
| **Expense 1** | `EXP-DEMO-001` | DEMO Park Properties | ₹ 15,000.00 | ₹ 15,000.00 | — | `BANK` |
| **Expense 2** | `EXP-DEMO-002` | DEMO Fiber Network | ₹ 2,360.00 | ₹ 2,360.00 | — | `UPI` |
| **Receipt 1** | `RCPT-DEMO-001` | DEMO Horizon Tech Ltd | ₹ 11,800.00 | — | — | `COMPLETED` |
| **Receipt 2** | `RCPT-DEMO-002` | DEMO Sterling Logistics | ₹ 10,000.00 | — | — | `COMPLETED` |
| **Payment 1** | `PAY-DEMO-001` | DEMO Vanguard Components | ₹ 11,800.00 | — | — | `COMPLETED` |
| **Journal** | `JE-DEMO-001` | Sales Revenue & Receivables | ₹ 53,100.00 | — | — | `POSTED` |
