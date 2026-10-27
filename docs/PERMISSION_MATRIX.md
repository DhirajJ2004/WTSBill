# WTSBill ERP — Role-Based Access Control (RBAC) Permission Matrix

This document defines the definitive permission matrix implemented in WTSBill ERP across all roles, modules, and actions.

## 1. Roles Overview

| Role | Slug | System Role | Primary Responsibility |
|---|---|---|---|
| **Administrator** | `admin` | Yes | Unrestricted universal access across all modules, configuration, security, user administration, and audit trails. |
| **Accountant** | `accountant` | Yes | Financial receivables/payables, payments, receipts, tax invoices, quotations, and expenses. Strictly isolated from inventory adjustments, users, and system administration. |
| **Inventory Manager** | `inventory_manager` | Yes | Warehouse stock balances, add/remove stock in-out, physical reconciliation stock adjustments (including edit/delete actions), catalog items, and categories. Strictly isolated from sales, accounting, and user administration. |
| **Sales Executive** | `sales_executive` | Yes | Customer quotations, pro-forma orders, tax invoices (own/branch), and delivery challans. |
| **Viewer** | `viewer` | Yes | Read-only general dashboard access; no write or financial operation privileges. Default lowest-privilege role. |

---

## 2. Definitive Permission Matrix Table

| Module | Action | Permission Slug | Administrator | Accountant | Inventory Manager | Sales Executive | Viewer |
|---|---|---|:---:|:---:|:---:|:---:|:---:|
| **Dashboard** | View | `dashboard.view` | ✅ | ❌ | ❌ | ✅ | ✅ |
| **Dashboard** | View Payments | `dashboard.payments.view` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Dashboard** | View Inventory | `dashboard.inventory.view` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Tax Invoices** | View | `invoices.view` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Tax Invoices** | Create | `invoices.create` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Tax Invoices** | Edit | `invoices.edit` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Tax Invoices** | Delete | `invoices.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Tax Invoices** | Print | `invoices.print` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Quotations** | View | `quotations.view` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Quotations** | Create | `quotations.create` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Quotations** | Edit | `quotations.edit` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Quotations** | Delete | `quotations.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Quotations** | Print | `quotations.print` | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Delivery Challans** | View | `challans.view` | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Delivery Challans** | Create | `challans.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Delivery Challans** | Edit | `challans.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Delivery Challans** | Delete | `challans.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Delivery Challans** | Print | `challans.print` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Expenses** | View | `expenses.view` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Expenses** | Create | `expenses.create` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Expenses** | Edit | `expenses.edit` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Expenses** | Delete | `expenses.delete` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Payments & Receipts** | View | `payments.view` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Payments & Receipts** | Create | `payments.create` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Payments & Receipts** | Edit | `payments.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Payments & Receipts** | Delete | `payments.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Payments & Receipts** | Print | `payments.print` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Warehouse Balances** | View | `inventory.view` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Management** | Add Stock | `inventory.stock.add` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Management** | Remove Stock | `inventory.stock.remove` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Adjustments** | View | `inventory.adjustment.view` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Adjustments** | Create | `inventory.adjustment.create` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Adjustments** | Edit | `inventory.adjustment.edit` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Stock Adjustments** | Delete | `inventory.adjustment.delete` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Items & Catalog** | View | `items.view` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Items & Catalog** | Create | `items.create` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Items & Catalog** | Edit | `items.edit` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Items & Catalog** | Delete | `items.delete` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Categories** | View | `categories.view` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Categories** | Create | `categories.create` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Categories** | Edit | `categories.edit` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Categories** | Delete | `categories.delete` | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Customers** | View | `customers.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Customers** | Create | `customers.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Customers** | Edit | `customers.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Customers** | Delete | `customers.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Suppliers** | View | `suppliers.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Suppliers** | Create | `suppliers.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Suppliers** | Edit | `suppliers.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Suppliers** | Delete | `suppliers.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Purchase Bills** | View | `bills.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Purchase Bills** | Create | `bills.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Purchase Bills** | Edit | `bills.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Purchase Bills** | Delete | `bills.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Purchase Bills** | Print | `bills.print` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Accounting** | View | `accounting.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Accounting** | Create | `accounting.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Accounting** | Edit | `accounting.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Accounting** | Delete | `accounting.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Reports** | View | `reports.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Reports** | Export | `reports.export` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Users** | View | `users.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Users** | Create | `users.create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Users** | Edit | `users.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Users** | Delete | `users.delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Users** | Manage | `users.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Roles** | View | `roles.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Roles** | Manage | `roles.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Settings** | View | `settings.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Settings** | Edit | `settings.edit` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Audit Logs** | View | `audit.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Audit Logs** | Export | `audit.export` | ✅ | ❌ | ❌ | ❌ | ❌ |
