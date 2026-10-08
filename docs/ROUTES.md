# WTSBill ERP — Complete Route Registry

## 1. Web Views (Server-Rendered SSR)

| HTTP Method | Route URL | Target View Template | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | `views/dashboard.php` | Yes (Redirects to `/login`) |
| `GET` | `/login` | `views/auth/login.php` | No |
| `POST`| `/login` | `views/auth/login.php` | No |
| `GET` | `/logout` | `backend/public/index.php` | Yes |
| `GET` | `/forgot-password` | `views/auth/forgot_password.php`| No |
| `GET` | `/reset-password` | `views/auth/reset_password.php` | No |
| `GET` | `/select-company` | `views/auth/select_company.php` | Yes |
| `GET` | `/switch-company` | `backend/public/index.php` | Yes |
| `GET` | `/dashboard` | `views/dashboard.php` | Yes |
| `GET` | `/invoices` | `views/sales/invoices.php` | Yes |
| `GET` | `/create-invoice` | `views/sales/create_invoice.php`| Yes |
| `GET` | `/quotations` | `views/sales/quotations.php` | Yes |
| `GET` | `/create-quotation`| `views/sales/create_quotation.php`| Yes |
| `GET` | `/recurring-invoices`| `views/sales/recurring_invoices.php`| Yes |
| `GET` | `/credit-notes` | `views/sales/credit_notes.php` | Yes |
| `GET` | `/debit-notes` | `views/purchases/debit_notes.php`| Yes |
| `GET` | `/purchases` | `views/purchases/purchases.php` | Yes |
| `GET` | `/inventory` | `views/inventory/index.php` | Yes |
| `GET` | `/parties` | `views/parties/index.php` | Yes |
| `GET` | `/payments` | `views/payments/index.php` | Yes |
| `GET` | `/expenses` | `views/expenses/index.php` | Yes |
| `GET` | `/accounting` | `views/accounting/index.php` | Yes |
| `GET` | `/reports` | `views/reports/index.php` | Yes |
| `GET` | `/settings` | `views/settings/index.php` | Yes |
| `GET` | `/users` | `views/settings/users.php` | Yes |
| `GET` | `/audit-logs` | `views/settings/audit_logs.php` | Yes |

## 2. Versioned REST API Endpoints (`/api/v1/...`)

| HTTP Method | API Endpoint | Controller Handler | Auth Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/login` | `AuthController@login` | No |
| `POST` | `/api/v1/auth/logout` | `AuthController@logout` | Yes |
| `GET` | `/api/v1/auth/me` | `AuthController@me` | Yes |
| `GET` | `/api/v1/dashboard/metrics` | `DashboardController@getMetrics` | Yes |
| `GET` | `/api/v1/invoices` | `InvoiceController@index` | Yes |
| `POST` | `/api/v1/invoices` | `InvoiceController@store` | Yes |
| `GET` | `/api/v1/invoices/{id}` | `InvoiceController@show` | Yes |
| `GET` | `/api/v1/customers` | `CustomerController@index` | Yes |
| `POST` | `/api/v1/customers` | `CustomerController@store` | Yes |
| `GET` | `/api/v1/suppliers` | `SupplierController@index` | Yes |
| `GET` | `/api/v1/products` | `ProductController@index` | Yes |
| `GET` | `/api/v1/inventory/warehouses` | `InventoryController@getWarehouses` | Yes |
| `GET` | `/api/v1/purchases` | `PurchaseController@index` | Yes |
| `GET` | `/api/v1/payments` | `PaymentController@index` | Yes |
| `GET` | `/api/v1/expenses` | `ExpenseController@index` | Yes |
| `GET` | `/api/v1/trial-balance` | `AccountingController@getTrialBalance` | Yes |
| `GET` | `/api/v1/reports/profit-loss` | `AccountingController@getProfitLoss` | Yes |
| `GET` | `/api/v1/reports/balance-sheet` | `AccountingController@getBalanceSheet`| Yes |
| `GET` | `/api/v1/reports/gstr1` | `GSTController@getGSTR1` | Yes |
| `GET` | `/api/v1/users` | `UserController@index` | Yes |
