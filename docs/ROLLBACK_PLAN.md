# WTSBill ERP — Emergency Rollback Plan

**Document Version:** 2.0.0  
**Effective Date:** 2026-10-26  
**Purpose:** Provide step-by-step, deterministic, and verifiable recovery procedures in the event of an unforeseen incident during or immediately following production cutover.

---

## 1. Rollback Authority & Triggers

A rollback is mandatory if **any** of the following conditions occur post-cutover:

| Trigger ID | Incident Condition | Severity | Action |
| :--- | :--- | :---: | :--- |
| **TRG-01** | Production 500 error rate exceeds 0.5% within 30 minutes of cutover | **P1 - Critical** | Immediate Code Rollback |
| **TRG-02** | Any calculation discrepancy detected in tax (GST splits), discounts, or round-off | **P1 - Critical** | Immediate Code Rollback |
| **TRG-03** | Any discrepancy detected in double-entry accounting ledger balance ($\sum \text{Debit} \neq \sum \text{Credit}$) | **P1 - Critical** | Immediate Full Rollback |
| **TRG-04** | Multi-tenant boundary leak (Tenant A observes Tenant B data) | **P0 - Blocker** | Emergency Maintenance + Rollback |
| **TRG-05** | Database connection exhaustion or locking deadlock on primary tables | **P1 - Critical** | Immediate Code Rollback |
| **TRG-06** | Key document printing (Invoices, Challans, Receipts) rendered unusable | **P2 - High** | Rollback or Fast-Fix (<15 min) |

---

## 2. Pre-Cutover Backup Artifacts

Prior to initiating cutover, the cutover lead must confirm that the following artifacts exist in `/var/backups/wtsbill/` (or `C:/xampp/backups/`):

1. **Database Dump File:** `wtsbill_pre_cutover_YYYYMMDD_HHMMSS.sql`
   - Generated with: `mysqldump --single-transaction --routines --triggers -u root -p wtsbill_db > backup.sql`
   - Verified file size (> 5 MB).
2. **Legacy Application Archive:** `wtsbill_legacy_v1_backup.tar.gz`
   - Snapshot of original application codebase and configuration.
3. **Legacy Git Commit Hash:** `git rev-parse HEAD` recorded.
4. **Active Session & Storage Snapshot:** `storage_snapshot.tar.gz`.

---

## 3. Rollback Procedures

### Scenario A: Fast Rollback (Code Revert Only)
*Use when database schema and data remain intact, but PHP application logic or routing produces unexpected errors.*

1. **Notify Incident Command & Place Temporary Maintenance Page:**
   ```bash
   cp /var/www/WTSBill/public/maintenance.html.sample /var/www/WTSBill/public/maintenance.html
   ```

2. **Revert Application Code via Git:**
   ```bash
   cd /var/www/WTSBill
   git checkout <PRE_CUTOVER_COMMIT_HASH_OR_TAG>
   ```

3. **Flush In-Memory Caches & Bytecode:**
   ```bash
   # Reset PHP OPcache
   php -r "if (function_exists('opcache_reset')) { opcache_reset(); }"
   # Clear application view & query cache
   rm -rf storage/cache/*
   ```

4. **Verify Application Health:**
   ```bash
   curl -f http://localhost/api/health
   php tests/run_tests.php
   ```

5. **Lift Maintenance Page:**
   ```bash
   rm -f /var/www/WTSBill/public/maintenance.html
   ```

---

### Scenario B: Full Rollback (Code + Database Restoration)
*Use when unexpected database mutations, corruptions, or migration anomalies have occurred.*

1. **Immediate Maintenance Mode & Stop Application Traffic:**
   ```bash
   systemctl stop nginx  # or systemctl stop apache2
   ```

2. **Terminate Active Database Connections to Target Database:**
   ```sql
   -- Run in MySQL CLI:
   SELECT CONCAT('KILL ', id, ';') FROM information_schema.processlist 
   WHERE db = 'wtsbill_db' AND command != 'Binlog Dump';
   ```

3. **Restore Database from Pre-Cutover Snapshot:**
   ```bash
   mysql -u root -p -e "DROP DATABASE IF EXISTS wtsbill_db; CREATE DATABASE wtsbill_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p wtsbill_db < /var/backups/wtsbill/wtsbill_pre_cutover_YYYYMMDD_HHMMSS.sql
   ```

4. **Revert Application Files:**
   ```bash
   cd /var/www/WTSBill
   git checkout <PRE_CUTOVER_COMMIT_HASH_OR_TAG>
   ```

5. **Verify Database Table Count & Record Integrity:**
   ```sql
   USE wtsbill_db;
   SELECT count(*) as total_tables FROM information_schema.tables WHERE table_schema = 'wtsbill_db';
   SELECT count(*) as total_invoices FROM invoices;
   SELECT count(*) as total_customers FROM customers;
   ```

6. **Flush OPcache and Restart Web Server:**
   ```bash
   php -r "if (function_exists('opcache_reset')) { opcache_reset(); }"
   systemctl start nginx  # or systemctl start apache2
   ```

7. **Verify Live Health Endpoint:**
   ```bash
   curl -f https://billing.wtsbill.in/api/health
   ```

---

### Scenario C: Web Server DocumentRoot Reversion
*If using dual-folder cutover (e.g., `/var/www/wtsbill_v1` and `/var/www/wtsbill_v2`):*

1. **Re-point Symlink to Previous Release:**
   ```bash
   ln -sfn /var/www/wtsbill_v1 /var/www/wtsbill_current
   ```
2. **Reload Web Server:**
   ```bash
   systemctl reload nginx  # or systemctl reload apache2
   ```
3. **Verify:** Check that the legacy endpoint is actively serving requests.

---

## 4. Post-Rollback Checklist & Validation

Following any rollback execution, perform these validations before declaring the rollback complete:

- [ ] **Database Connection:** Web application successfully connects to `wtsbill_db`.
- [ ] **User Authentication:** Admin login succeeds without session errors.
- [ ] **Data Verification:** Invoices, Customers, and Products match pre-cutover counts.
- [ ] **Double-Entry Equality:** `SELECT SUM(total_debit) - SUM(total_credit) FROM journal_entries;` returns `0.00`.
- [ ] **Error Logs Clean:** `storage/logs/wtsbill-YYYY-MM-DD.log` shows no unhandled exceptions.
- [ ] **Stakeholder Notification:** Notify management and users of recovery status.

---

## 5. Post-Mortem & Root Cause Analysis (RCA)

Within 24 hours of any rollback:
1. Preserve error logs from `storage/logs/` and web server logs.
2. Export failed query patterns or exception stack traces.
3. Convene engineering team to document:
   - Root Cause of Failure
   - Why Pre-Cutover Tests Missed the Anomaly
   - Remediation Plan and Re-Test Schedule
