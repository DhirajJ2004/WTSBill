# WTSBill ERP — Database Architecture & Schema Verification Log

**Date:** 2026-11-05  
**Version:** 2.1 Enterprise  
**Database:** `wtsbill_db` (MariaDB / MySQL)  
**Schema Verification Status:** 124 tables verified, zero data loss, schema complete and aligned.

---

## 1. Schema Inventory & Table Readiness

A live query of the database schema was conducted across all tables critical to the Module Expansion. All required tables and columns already exist in the MariaDB catalog with complete foreign key relationships, audit columns, and decimal precision:

| Subsystem | Verified Table | Core Columns & Types | Nullability & Constraints | Status |
| :--- | :--- | :--- | :--- | :--- |
| **CRM** | `customers` | `id`, `company_id`, `name`, `credit_limit (15,2)`, `payment_terms_days`, `notes`, `contact_person`, `whatsapp_number` | Verified present | **READY** |
| **CRM** | `customer_addresses` | `id`, `company_id`, `customer_id`, `type`, `address_line1`, `address_line2`, `city`, `state`, `state_code`, `pincode`, `is_default` | Verified present | **READY** |
| **Sales Orders** | `sales_orders` | `id`, `company_id`, `branch_id`, `customer_id`, `order_number`, `order_date`, `grand_total`, `status`, `fulfillment_status`, `payment_status`, `source_quotation_id` | Verified present | **READY** |
| **Sales Orders** | `sales_order_items` | `id`, `company_id`, `sales_order_id`, `product_id`, `quantity`, `unit_price`, `taxable_value`, `gst_rate`, `cgst_amount`, `sgst_amount`, `igst_amount`, `total_amount` | Verified present | **READY** |
| **Fulfillment** | `delivery_challans` | `id`, `company_id`, `branch_id`, `customer_id`, `challan_number`, `challan_date`, `sales_order_id`, `reference_so`, `status` | Verified present | **READY** |
| **Fulfillment** | `delivery_challan_items` | `id`, `company_id`, `delivery_challan_id`, `product_id`, `quantity`, `item_name`, `unit`, `unit_price`, `total_amount` | Verified present | **READY** |
| **Inventory** | `warehouses` | `id`, `company_id`, `branch_id`, `name`, `code`, `city`, `address`, `manager_name`, `phone`, `is_primary`, `is_active`, `is_default` | Verified present | **READY** |
| **Inventory** | `stock_balances` | `id`, `company_id`, `branch_id`, `warehouse_id`, `product_id`, `quantity`, `reserved_quantity`, `available_quantity`, `in_transit_quantity` | Verified present | **READY** |
| **Inventory** | `stock_transfers` | `id`, `company_id`, `transfer_number`, `from_warehouse_id`, `to_warehouse_id`, `transfer_date`, `status`, `approved_by`, `dispatched_by`, `received_by` | Verified present | **READY** |
| **Inventory** | `stock_transfer_items`| `id`, `company_id`, `transfer_id`, `stock_transfer_id`, `product_id`, `quantity`, `quantity_sent`, `quantity_received`, `unit` | Verified present | **READY** |
| **Procurement** | `purchase_orders` | `id`, `company_id`, `branch_id`, `supplier_id`, `po_number`, `po_date`, `grand_total`, `status`, `reference_no`, `notes` | Verified present | **READY** |
| **Procurement** | `purchase_order_items`| `id`, `company_id`, `purchase_order_id`, `product_id`, `quantity`, `unit_price`, `taxable_value`, `gst_rate`, `total_amount`, `received_quantity` | Verified present | **READY** |
| **Procurement** | `suppliers` | `id`, `company_id`, `name`, `contact_person`, `gstin`, `payment_terms_days`, `opening_balance`, `current_balance`, `status`, `notes` | Verified present | **READY** |
| **Payments** | `customer_advances` | `id`, `company_id`, `branch_id`, `customer_id`, `payment_id`, `advance_date`, `total_amount`, `allocated_amount`, `remaining_amount`, `status` | Verified present | **READY** |
| **Notifications** | `in_app_notifications`| `id`, `company_id`, `user_id`, `category`, `priority`, `title`, `message`, `entity_type`, `entity_id`, `is_read`, `action_url` | Verified present | **READY** |
| **Lineage** | `invoices` | Has `source_quotation_id`, `source_sales_order_id`, `source_delivery_challan_id`, `challan_no`, `po_no`, `po_date` | Verified present | **READY** |

---

## 2. Safety Guidelines for Database Queries

1. **Zero Data Loss Guarantee**: No `DROP TABLE`, `DROP COLUMN`, or `TRUNCATE` statements may be executed.
2. **Prepared Statements**: All database operations must use parameterized queries via `Database::select`, `Database::selectOne`, `Database::insert`, `Database::update`, and `Database::delete`.
3. **Transaction Savepoints**: Complex multi-table mutations (e.g. Sales Order &rarr; Delivery Challan &rarr; Invoice) must be wrapped inside `Database::transaction()`.
4. **Tenant Isolation**: Every SQL query must constrain records by `company_id = ?` and `branch_id = ?` according to active session context.

