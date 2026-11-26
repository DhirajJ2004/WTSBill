# WTSBill ERP — Production Readiness Report

**Date:** November 26, 2026  
**Environment:** Production-Hardened Local Verification (`APP_ENV=production`, `APP_DEBUG=false`)  
**Browser Verification:** Playwright Chromium (100% Pass Rate)

---

## 1. Security & Production Hardening Checks

| Check | Implementation | Verification Result |
|---|---|---|
| **Debug & Error Display** | Updated `.env` to `APP_ENV=production` and `APP_DEBUG=false`. | **PASS** (Visitors do not see stack traces) |
| **Sensitive File Protection** | Updated `.htaccess` to block `/.env`, `*.sql`, `*.zip`, `*.log`, `*.md`, `composer.json`, and internal directories (`app/`, `config/`, `storage/`, `vendor/`). | **PASS** (`/.env` & `.sql` return HTTP 404/403) |
| **Password Security** | All user passwords hashed via `PASSWORD_BCRYPT`. Zero plaintext passwords stored in DB. | **PASS** |
| **CSRF & Authentication** | CSRF tokens enforced on login and all state-changing `POST` routes; RBAC tested via `sales@wtsbill.demo`. | **PASS** (Sales Exec blocked from `/users` with HTTP 403) |
| **Branding Assets** | Company logo & UPI QR preserved cleanly in `public/uploads/branding/`. | **PASS** |

---

## 2. Browser Testing Results (Post-Cleanup & Demo Setup)

All tests were executed live against Apache & MySQL (`wtsbill_db`):

- **Security Probe:** `/.env`, `/WTSBill_PreCleanup_Database_Backup.sql`, `/composer.json`, `/storage/logs/app.log` → **Blocked (HTTP 404/403)**.
- **Demo Admin Login & Dashboard:**
  - Logged in as `admin@wtsbill.demo`.
  - Verified KPI cards display exact demo values: Sales **₹ 53,100.00**, Purchases **₹ 17,700.00**, Receivables **₹ 31,300.00**, Payables **₹ 5,900.00**, Customers **5**, Products **10**.
  - Verified all 4 interactive SVG Donut Charts render accurately.
- **Module & Route Coverage (25/25 Routes HTTP 200):**
  - `/sales/invoices`, `/sales/invoices/1`, `/sales/invoices/1/print`
  - `/sales/quotations`, `/sales/credit-notes`, `/sales/credit-notes/1`
  - `/purchases/bills`, `/purchases/bills?id=1`, `/purchases/debit-notes`
  - `/expenses`, `/items`, `/parties`, `/customers`, `/suppliers`, `/payments`
  - `/accounting/cash-bank`, `/reports/receivables-aging`, `/reports/gst`, `/reports/gstr-3b`, `/reports/gst-registers`
  - `/reports/profit-loss`, `/reports/balance-sheet`, `/reports/trial-balance`, `/reports/invoice-payment-status`, `/settings`
- **Excel Export:** `/reports/invoice-payment-status/export` generated valid `.xlsx` file (3,415 bytes, HTTP 200).
- **RBAC Enforcement:** `sales@wtsbill.demo` received **HTTP 403 Forbidden** when attempting to access `/users`.

---

## 3. Remaining Hosting & Deployment Requirements

Before pointing your live domain to the production server, configure the following environment values in `.env` on your live host:

1. **`APP_URL` & `ALLOWED_ORIGINS`:** Update to your live HTTPS domain (e.g., `https://erp.yourdomain.com`).
2. **Production Database Credentials:** Set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and a strong `DB_PASSWORD`.
3. **Rotate Secrets:** Generate fresh production values for `APP_KEY` and `JWT_SECRET`.
4. **HTTPS / SSL Certificate:** Ensure TLS/HTTPS is enabled on the web server and set `SESSION_SECURE_COOKIE=true`.
5. **Apache/Nginx DocumentRoot:** Point the live web server's `DocumentRoot` directly to `/public` as best practice (in addition to the hardened root `.htaccess`).
6. **Scheduled Backups:** Configure a daily cron job or Windows Task Scheduler to back up `wtsbill_db` and `public/uploads/`.
7. **Demo Account Passwords:** Log in as `admin@wtsbill.demo`, change the temporary password immediately, and remove/deactivate the demo users before onboarding live staff.
