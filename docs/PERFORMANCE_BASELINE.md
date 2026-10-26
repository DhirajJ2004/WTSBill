# WTSBill ERP — Performance Baseline Report (Chunk 18)

**Date:** 2026-10-26  
**Test Environment:**
- **OS:** Windows 11 Enterprise (x64)
- **Web Servers:**
  - Built-in PHP CLI Server: `PHP 8.2+ (php -S localhost:8000 -t public public/index.php)`
  - Apache HTTP Server: `XAMPP 2.4.x (mod_rewrite enabled, port 80)`
- **Database:** MariaDB / MySQL 10.4.x on `127.0.0.1:3306` with `wtsbill_db`
- **PHP OPcache:** Default CLI settings
- **Client Runner:** PowerShell 7.x Native `System.Diagnostics.Stopwatch` benchmark harness

---

## 1. Baseline Performance Measurements (Pre-Optimization)

Each representative endpoint was queried 10 consecutive times following 2 warm-up requests under authenticated session context (`anil.d@wtsbill.in`, Company #1, Branch #1).

| Endpoint | Category | Sample Count | Avg Latency (ms) | Min Latency (ms) | Max Latency (ms) |
| :--- | :--- | :---: | :---: | :---: | :---: |
| `/api/health` | JSON API / Connectivity | 10 | 34.47 ms | 19.26 ms | 76.03 ms |
| `/` | Dashboard / Overview | 10 | 33.37 ms | 29.69 ms | 44.05 ms |
| `/sales/invoices` | Sales / Tax Invoices Table | 10 | 38.59 ms | 26.01 ms | 54.86 ms |
| `/inventory/stock` | Inventory / Stock Valuations | 10 | 71.70 ms | 57.78 ms | 82.59 ms |
| `/purchases/bills` | Purchases / Inward Invoices | 10 | 28.38 ms | 25.89 ms | 30.39 ms |
| `/reports/profit-loss` | Financial / P&L Report | 10 | 27.16 ms | 24.57 ms | 30.24 ms |

---

## 2. Identified Bottlenecks & Architectural Overhead

1. **View Resolution Disk I/O Overhead:**
   Every HTTP request resolving a master layout and partials (`app.php`, `partials/sidebar.php`, `partials/header.php`, `partials/footer.php`, `partials/toast.php`, view template) makes un-cached `file_exists()` and `realpath()` system calls.

2. **Repetitive Prepared Statement Compilation:**
   `Database::select()` and `Database::selectOne()` recreate PDO prepared statements for identical queries across repeated operations during a single request lifecycle.

3. **Absence of Server-Timing Diagnostics:**
   No standard `Server-Timing` headers are returned, preventing client-side performance observers from diagnosing database vs application overhead.

4. **Missing Static Asset Caching & Deflate Compression:**
   `public/.htaccess` lacks `mod_deflate` compression and `mod_expires` / `Cache-Control` headers for `.css`, `.js`, and `.svg` files, resulting in unnecessary re-transfers on subsequent navigations.

5. **Safe Idempotent Query Cache:**
   Static/lookup data (such as tenant master settings and active financial periods) can be cached in memory with clear tenant isolation and invalidation triggers.
