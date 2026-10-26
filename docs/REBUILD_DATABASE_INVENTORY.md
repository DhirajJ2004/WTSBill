# WTSBill ERP — Database Schema & Table Inventory

**Document Version:** 1.0.0  
**Generated Date:** 2026-10-10  
**Target Database:** `wtsbill_db` (MySQL 8.0+ / MariaDB 10.4.32+)  
**Collation:** `utf8mb4_unicode_ci` | **Engine:** `InnoDB`  

---

## 1. Schema Overview & Structural Summary

A comprehensive programmatic audit of `wtsbill_db` via `information_schema` reveals:
- **Total Tables:** 161 tables
- **Tables with Populated Data:** 28 tables (seeded with active Indian manufacturing enterprise baseline)
- **Empty / Schema-Ready Tables:** 133 tables
- **Views:** 0 views (business logic decoupled from database views)
- **Stored Procedures / Functions:** 0 routines (portable Native PHP logic)
- **Triggers:** 0 triggers (ACID transactional integrity handled at the application service layer)
- **Foreign Key Constraints:** 10 explicit foreign key constraints (primarily in automation and notification sub-systems)

---

## 2. Functional Table Classification (All 161 Tables)

### 2.1 Multi-Tenant Organization & Branches (4 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `companies` | 1 | 1 | InnoDB | Root tenant company entity (`Wis Technosavvy Pvt Ltd`, GSTIN: `27AADCW7577N1ZE`) |
| `branches` | 2 | 2 | InnoDB | Operating locations (`Main Branch - Mumbai`, `Pune Regional Branch`) |
| `branch_settings` | 0 | 0 | InnoDB | Branch-specific configuration overrides (e.g. invoice prefixes, printers) |
| `branch_users` | 0 | 0 | InnoDB | Branch-to-user administrative assignments |

### 2.2 Users, Authentication, Roles & RBAC (8 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `users` | 5 | 5 | InnoDB | User credentials (Bcrypt), role strings, company associations |
| `roles` | 5 | 5 | InnoDB | Role definitions (`ADMIN`, `ACCOUNTANT`, `AUDITOR`, `INVENTORY_MANAGER`, `SALES_EXECUTIVE`) |
| `user_roles` | 5 | 5 | InnoDB | User-to-role assignment junction table |
| `permissions` | 0 | 0 | InnoDB | Granular system permission definitions (`invoices.create`, `reports.view`, etc.) |
| `role_permissions` | 0 | 0 | InnoDB | Role-to-permission mapping junction |
| `user_branches` | 7 | 7 | InnoDB | User-to-branch access permissions and default branch assignments |
| `user_sessions` | 0 | 0 | InnoDB | Active session token storage and device tracking |
| `personal_access_tokens` | 0 | 0 | InnoDB | Bearer API authentication tokens for external integrations |
| `password_resets` | 1 | 1 | InnoDB | Password reset tokens and expiration timestamps |

### 2.3 Parties: Customers & Suppliers (14 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `customers` | 21 | 21 | InnoDB | Customer master (GSTIN, PAN, credit limits, balances, payment terms) |
| `customer_addresses` | 0 | 0 | InnoDB | Multiple shipping and billing addresses per customer |
| `customer_advances` | 0 | 0 | InnoDB | Advance payment receipts and unallocated credits |
| `customer_contacts` | 0 | 0 | InnoDB | Customer contact persons and phone numbers |
| `customer_credits` | 0 | 0 | InnoDB | Available credit ledger balances |
| `customer_groups` | 0 | 0 | InnoDB | Customer segmentation (Wholesale, Retail, Distributor) |
| `customer_price_overrides` | 0 | 0 | InnoDB | Customer-specific pricing rules per SKU |
| `customer_tags` | 0 | 0 | InnoDB | Categorical tags for customer filtering |
| `suppliers` | 10 | 10 | InnoDB | Supplier master (GSTIN, PAN, bank accounts, current balance) |
| `supplier_addresses` | 0 | 0 | InnoDB | Supplier pickup and warehouse addresses |
| `supplier_bank_accounts` | 0 | 0 | InnoDB | Supplier bank details for payout processing |
| `supplier_bill_attachments`| 0 | 0 | InnoDB | PDF/Image attachments for supplier invoices |
| `supplier_contacts` | 0 | 0 | InnoDB | Supplier contact persons |
| `supplier_groups` | 0 | 0 | InnoDB | Vendor classification |

### 2.4 Products, Units, Categories & Inventory (20 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `products` | 50 | 50 | InnoDB | Master product catalog (SKU, HSN/SAC, pricing, GST rates, barcodes) |
| `product_barcodes` | 0 | 0 | InnoDB | Multiple barcodes and EAN/UPC per product |
| `product_prices` | 0 | 0 | InnoDB | Price list price points |
| `product_units` | 0 | 0 | InnoDB | Secondary units of measurement per product |
| `categories` | 1 | 1 | InnoDB | Product categories |
| `subcategories` | 0 | 0 | InnoDB | Product sub-categories |
| `units` | 4 | 4 | InnoDB | Units of measure (`Pcs`, `KG`, `Box`, `Mtrs`) |
| `unit_conversions` | 0 | 0 | InnoDB | Conversion ratios between primary and secondary units |
| `warehouses` | 1 | 1 | InnoDB | Warehouse master (`Warehouse A - Andheri MIDC`) |
| `stock_balances` | 50 | 50 | InnoDB | Current warehouse stock balances and reserved quantities |
| `stock_movements` | 1 | 1 | InnoDB | Audit log of all stock IN/OUT transactions |
| `stock_adjustments` | 0 | 0 | InnoDB | Physical inventory adjustment headers |
| `stock_adjustment_items`| 0 | 0 | InnoDB | Line items for inventory write-offs or additions |
| `stock_counts` | 0 | 0 | InnoDB | Periodic stock auditing batches |
| `stock_count_items` | 0 | 0 | InnoDB | Physical count line-item comparisons |
| `stock_transfers` | 0 | 0 | InnoDB | Inter-warehouse stock transfer headers |
| `stock_transfer_items` | 0 | 0 | InnoDB | Line items for inter-warehouse stock transfers |
| `batches` | 0 | 0 | InnoDB | Batch numbers and expiry dates for perishable/pharma goods |
| `serial_numbers` | 0 | 0 | InnoDB | Unique item serial tracking |
| `price_histories` | 0 | 0 | InnoDB | Historical selling and purchase price logs |
| `price_lists` | 0 | 0 | InnoDB | Tiered price list definitions (Wholesale, Retail, Export) |
| `price_list_items` | 0 | 0 | InnoDB | Price list items |

### 2.5 Sales, Invoices, Quotations & Deliveries (15 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `invoices` | 2 | 2 | InnoDB | Tax Invoices (GST calculations, discounts, roundoff, payment status) |
| `invoice_items` | 1 | 1 | InnoDB | Invoice line items (Qty, Price, HSN, CGST, SGST, IGST splits) |
| `quotations` | 1 | 1 | InnoDB | Sales quotations and estimates |
| `quotation_items` | 0 | 0 | InnoDB | Quotation line items |
| `sales_orders` | 0 | 0 | InnoDB | Confirmed sales order headers |
| `sales_order_items` | 0 | 0 | InnoDB | Sales order item lines |
| `delivery_challans` | 0 | 0 | InnoDB | Dispatch delivery challan headers |
| `delivery_challan_items`| 0 | 0 | InnoDB | Delivery challan item lines |
| `credit_notes` | 1 | 1 | InnoDB | Sales return credit notes |
| `credit_note_items` | 0 | 0 | InnoDB | Credit note line items |
| `sales_returns` | 0 | 0 | InnoDB | Goods return inspection headers |
| `sales_return_items` | 0 | 0 | InnoDB | Sales return line items |
| `recurring_invoices` | 0 | 0 | InnoDB | Auto-generating subscription invoices |
| `recurring_invoice_items`| 0 | 0 | InnoDB | Recurring invoice line items |
| `recurring_transactions`| 0 | 0 | InnoDB | Subscription transaction definitions |

### 2.6 Purchases & Accounts Payable (9 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `purchases` | 2 | 2 | InnoDB | Vendor purchase bills (Tax input, ITC eligibility, payment status) |
| `purchase_items` | 1 | 1 | InnoDB | Purchase bill line items |
| `purchase_orders` | 0 | 0 | InnoDB | Purchase orders issued to suppliers |
| `purchase_order_items` | 0 | 0 | InnoDB | Purchase order line items |
| `goods_receipts` | 0 | 0 | InnoDB | Goods Receipt Notes (GRN) received at warehouse |
| `goods_receipt_items` | 0 | 0 | InnoDB | GRN item line receipts |
| `debit_notes` | 1 | 1 | InnoDB | Purchase return debit notes |
| `debit_note_items` | 0 | 0 | InnoDB | Debit note item lines |
| `purchase_returns` | 0 | 0 | InnoDB | Purchase return transactions |
| `purchase_return_items` | 0 | 0 | InnoDB | Purchase return line items |

### 2.7 Payments, Banking & Gateway Processing (18 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `bank_accounts` | 1 | 1 | InnoDB | Bank accounts (`HDFC Bank`, IFSC, opening balance) |
| `bank_transactions` | 0 | 0 | InnoDB | Bank ledger debit/credit postings |
| `bank_transfers` | 0 | 0 | InnoDB | Contra transfers between bank accounts or cash drawers |
| `bank_reconciliations` | 0 | 0 | InnoDB | Monthly bank reconciliation statements |
| `bank_reconciliation_matches`| 0 | 0 | InnoDB | Matched statement-to-ledger transactions |
| `bank_statement_imports`| 0 | 0 | InnoDB | Uploaded CSV/Excel bank statements |
| `bank_statement_rows` | 0 | 0 | InnoDB | Parsed bank statement records |
| `cheques` | 0 | 0 | InnoDB | Cheque register (Issued / Received) |
| `cheque_events` | 0 | 0 | InnoDB | Cheque clearance, bounce, and cancel lifecycle |
| `payments` | 0 | 0 | InnoDB | Payment receipts and voucher entries |
| `payment_allocations` | 0 | 0 | InnoDB | Invoice allocation mapping for partial/full payments |
| `payment_refunds` | 0 | 0 | InnoDB | Customer and vendor refunds |
| `payment_reminders` | 0 | 0 | InnoDB | Due date notification triggers |
| `payment_settlements` | 0 | 0 | InnoDB | Gateway settlement records |
| `payment_links` | 0 | 0 | InnoDB | Online payment link generator |
| `payment_link_events` | 0 | 0 | InnoDB | Payment link webhook triggers |
| `payment_gateway_events`| 0 | 0 | InnoDB | Gateway events (Razorpay, Cashfree, UPI) |
| `payment_gateway_settlements`| 0 | 0 | InnoDB | Payment gateway batch settlements |
| `payment_gateway_transactions`| 0 | 0 | InnoDB | Gateway transaction logs |
| `payment_gateway_webhooks`| 0 | 0 | InnoDB | Incoming webhook payloads |

### 2.8 Expenses & Double-Entry Accounting (10 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `expenses` | 1 | 1 | InnoDB | Operational expenses (`Rent & Facilities`, ₹65,000 + GST) |
| `expense_categories` | 0 | 0 | InnoDB | Expense classification categories |
| `recurring_expenses` | 0 | 0 | InnoDB | Scheduled automated expenses |
| `chart_of_accounts` | 11 | 11 | InnoDB | Standard Chart of Accounts (Assets, Liabilities, Equity, Income, Expenses) |
| `journal_entries` | 1 | 1 | InnoDB | Double-entry journal voucher headers |
| `journal_entry_lines` | 2 | 2 | InnoDB | Double-entry line items ($\sum \text{Debit} = \sum \text{Credit}$) |
| `journal_lines` | 0 | 0 | InnoDB | Overlapping journal line table schema |
| `ledger_entries` | 0 | 0 | InnoDB | Denormalized general ledger lookup |
| `accounting_periods` | 0 | 0 | InnoDB | Financial year and period locks (e.g. FY 2026-27) |
| `account_mappings` | 0 | 0 | InnoDB | Automated ledger posting rules for invoices, bills, payments |

### 2.9 GST Compliance, E-Invoicing & E-Way Bills (13 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `tax_rates` | 5 | 5 | InnoDB | GST rate master (0%, 5%, 12%, 18%, 28%) |
| `gst_rates` | 0 | 0 | InnoDB | Extended GST rates table |
| `gst_configurations` | 0 | 0 | InnoDB | GST registration parameters, threshold limits, composite flags |
| `gst_document_snapshots`| 0 | 0 | InnoDB | Immutable audit snapshot of invoices at filing time |
| `gst_filing_periods` | 0 | 0 | InnoDB | Monthly/quarterly filing records (GSTR-1, GSTR-3B) |
| `gstin_validations` | 0 | 0 | InnoDB | GSTIN portal API verification cache |
| `gst_reconciliation` | 0 | 0 | InnoDB | GSTR-2B purchase reconciliation batches |
| `gst_reconciliation_items`| 0 | 0 | InnoDB | GSTR-2B vs purchase bill line comparisons |
| `gst_transactions` | 0 | 0 | InnoDB | Real-time tax transaction log |
| `hsn_sac_master` | 0 | 0 | InnoDB | Standard Indian HSN & SAC 8-digit directory |
| `indian_states` | 0 | 0 | InnoDB | Indian state codes (01 to 38) and UT master |
| `e_invoices` | 0 | 0 | InnoDB | IRP generated IRN, QR code, Ack Number |
| `e_invoice_status_history`| 0 | 0 | InnoDB | E-Invoice generation, cancellation history |
| `e_way_bills` | 0 | 0 | InnoDB | E-Way Bill Number (EBN), vehicle numbers, validities |
| `e_way_bill_status_history`| 0 | 0 | InnoDB | E-Way Bill lifecycle updates |

### 2.10 Document Numbering, Templates & Printing (8 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `document_numbering_configs`| 0 | 0 | InnoDB | Primary sequential numbering config (prefix, padding, reset frequency) |
| `document_number_settings`| 0 | 0 | InnoDB | Overlapping document numbering format settings |
| `document_templates` | 0 | 0 | InnoDB | Print template designs (Classic, Modern, Tax Invoice, Thermal) |
| `document_template_versions`| 0 | 0 | InnoDB | Revision history of print templates |
| `document_audit_logs` | 0 | 0 | InnoDB | Document creation, edit, print, download logs |
| `document_shares` | 0 | 0 | InnoDB | Public shareable links for customer invoices |
| `generated_documents` | 0 | 0 | InnoDB | PDF storage references |
| `attachments` | 0 | 0 | InnoDB | Polymorphic attachments across ERP entities |

### 2.11 Reports, Analytics, Audit & Automations (36 Tables)
| Table Name | Approx Rows | Exact Rows | Engine | Description & Tenant Scope |
| :--- | :--- | :--- | :--- | :--- |
| `report_saved_views` | 0 | 0 | InnoDB | User saved filters and table view configurations |
| `report_schedules` | 0 | 0 | InnoDB | Scheduled automated email reports |
| `report_snapshots` | 0 | 0 | InnoDB | Cached pre-aggregated report runs |
| `report_audit_logs` | 0 | 0 | InnoDB | Report export and view tracking |
| `audit_logs` | 0 | 0 | InnoDB | Centralized change log of critical record mutations |
| `backup_records` | 0 | 0 | InnoDB | Database dump records and storage paths |
| `restore_logs` | 0 | 0 | InnoDB | Backup restoration logs |
| `export_jobs` | 0 | 0 | InnoDB | Asynchronous CSV/Excel data export jobs |
| `import_jobs` | 0 | 0 | InnoDB | Bulk party/product Excel import jobs |
| `import_row_logs` | 0 | 0 | InnoDB | Row-by-row failure/success import logs |
| `migration_profiles` | 0 | 0 | InnoDB | Legacy data migration profiles |
| `scheduler_jobs` | 0 | 0 | InnoDB | Background recurring task definitions |
| `schema_migrations` | 0 | 0 | InnoDB | Database migration tracker |
| `tags` | 0 | 0 | InnoDB | Universal entity tags |
| `taggables` | 0 | 0 | InnoDB | Polymorphic tag association table |
| `notifications` | 0 | 0 | InnoDB | User notifications |
| `in_app_notifications`| 0 | 0 | InnoDB | In-app alerts |
| `notification_events` | 0 | 0 | InnoDB | Event triggers (Invoice created, Payment received) |
| `notification_logs` | 0 | 0 | InnoDB | SMS, WhatsApp, Email delivery logs |
| `notification_preferences`| 0 | 0 | InnoDB | User notification channel preferences |
| `notification_rules` | 0 | 0 | InnoDB | Automatic notification triggering rules |
| `notification_templates`| 0 | 0 | InnoDB | Message copy templates |
| `business_communication_settings`| 0 | 0 | InnoDB | SMTP, WhatsApp API, SMS gateway credentials |
| `communication_messages`| 0 | 0 | InnoDB | Outgoing customer communications |
| `communication_preferences`| 0 | 0 | InnoDB | Party opt-in / opt-out preferences |
| `communication_templates`| 0 | 0 | InnoDB | WhatsApp/Email message templates |
| `reminders` | 0 | 0 | InnoDB | Task and payment reminder alerts |
| `recurring_templates` | 0 | 0 | InnoDB | Reusable automation templates |
| `recurring_transaction_histories`| 0 | 0 | InnoDB | Execution history of recurring jobs |
| `recurring_transaction_runs`| 0 | 0 | InnoDB | Run audit for recurring workflows |
| `recurring_run_logs` | 0 | 0 | InnoDB | Job execution diagnostic logs |
| `automation_events` | 0 | 0 | InnoDB | Webhook event broadcaster |
| `automation_execution_logs`| 0 | 0 | InnoDB | Automation rule execution results |
| `automation_rules` | 0 | 0 | InnoDB | Triggers and automated actions |

---

## 3. Discovered Foreign Key Constraints

There are 10 explicitly defined foreign key constraints in `wtsbill_db`:

| Constraint Name | Source Table & Column | Referenced Target |
| :--- | :--- | :--- |
| `automation_events_company_id_foreign` | `automation_events.company_id` | `companies.id` |
| `automation_execution_logs_company_id_foreign`| `automation_execution_logs.company_id` | `companies.id` |
| `automation_rules_company_id_foreign` | `automation_rules.company_id` | `companies.id` |
| `communication_messages_company_id_foreign` | `communication_messages.company_id` | `companies.id` |
| `communication_preferences_company_id_foreign`| `communication_preferences.company_id` | `companies.id` |
| `communication_templates_company_id_foreign` | `communication_templates.company_id` | `companies.id` |
| `recurring_run_logs_company_id_foreign` | `recurring_run_logs.company_id` | `companies.id` |
| `recurring_templates_company_id_foreign` | `recurring_templates.company_id` | `companies.id` |
| `reminders_company_id_foreign` | `reminders.company_id` | `companies.id` |
| `report_schedules_company_id_foreign` | `report_schedules.company_id` | `companies.id` |

---

## 4. Primary Core Table Schemas & Indexes

### `invoices`
- **Columns:** `id` (PK, auto_increment), `company_id` (Indexed), `branch_id`, `warehouse_id`, `customer_id` (Indexed), `invoice_number` (Unique/Indexed), `reference_po_number`, `invoice_date`, `due_date`, `place_of_supply`, `is_igst`, `sub_total`, `discount_rate`, `discount_amount`, `cgst_amount`, `sgst_amount`, `igst_amount`, `total_tax`, `round_off`, `grand_total`, `amount_paid`, `amount_due`, `status`, `payment_mode`, `notes`, `terms_and_conditions`, `created_at`, `updated_at`, `deleted_at`.

### `invoice_items`
- **Columns:** `id` (PK, auto_increment), `company_id`, `invoice_id` (Indexed), `product_id` (Indexed), `item_name`, `hsn_sac`, `quantity`, `unit`, `unit_price`, `discount_rate`, `discount_amount`, `taxable_value`, `gst_rate`, `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `igst_rate`, `igst_amount`, `total_amount`, `created_at`, `updated_at`.

### `stock_balances`
- **Columns:** `id` (PK, auto_increment), `company_id` (Indexed), `warehouse_id` (Indexed), `product_id` (Indexed), `quantity`, `reserved_quantity`, `available_quantity`, `in_transit_quantity`, `branch_id`, `created_at`, `updated_at`.

### `chart_of_accounts`
- **Columns:** `id` (PK, auto_increment), `company_id` (Indexed), `account_code`, `account_name`, `account_type` (`ASSET`, `LIABILITY`, `EQUITY`, `INCOME`, `EXPENSE`), `nature` (`DEBIT`, `CREDIT`), `opening_balance`, `current_balance`, `is_system_account`, `is_active`.

### `journal_entries` & `journal_entry_lines`
- `journal_entries`: `id` (PK), `company_id`, `entry_number`, `entry_date`, `narration`, `total_debit`, `total_credit`, `financial_year`, `status`, `created_at`.
- `journal_entry_lines`: `id` (PK), `company_id`, `journal_entry_id`, `account_id`, `debit_amount`, `credit_amount`, `description`.
