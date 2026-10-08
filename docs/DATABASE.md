# WTSBill ERP — Database Specification

## 1. Core Database Architecture

* **Database Engine:** MySQL 8.0+ / MariaDB 10.4+
* **Storage Engine:** 100% InnoDB
* **Collation:** `utf8mb4_unicode_ci`
* **Total Tables:** 161
* **Total Indexes:** 875

## 2. Strict Financial Precision Standards

* All monetary and tax calculations use `DECIMAL(15, 2)` or `DECIMAL(15, 4)`.
* Zero floating-point data types (`FLOAT` or `DOUBLE`) are permitted for:
  * `amount`, `subtotal`, `tax`, `discount`, `total`, `balance`, `debit`, `credit`, `rate`, `price`

## 3. Universal Accounting Invariant

For every journal voucher posted:
$$\sum \text{Debit} = \sum \text{Credit}$$

## 4. Multi-Tenant Scoping

All 148 tenant-owned tables feature a foreign/indexed `company_id` column automatically filtered via `App\Traits\BelongsToTenant`.
