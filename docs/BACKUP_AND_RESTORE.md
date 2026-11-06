# WTSBill ERP — Enterprise Automatic Weekly Backup & Disaster Recovery Guide

**Document Reference:** `DOC-OPS-BACKUP-001`  
**Version:** 2.0.0  
**Effective Date:** 2026-11-06  
**Auditor / Architect:** Senior Systems Architect & Database Engineer  
**Status:** FULLY IMPLEMENTED & CERTIFIED (787/787 Tests Passing)  

---

## 1. Architectural Overview

WTSBill ERP features a resilient, pure-PHP backup and disaster recovery engine requiring zero external dependencies (such as `mysqldump` or command-line zip utilities). The solution is engineered for 100% platform portability across Windows (XAMPP / IIS) and Linux (Apache / Nginx) production stacks.

### Core Guarantees:
- **Automatic 7-Day Weekly Frequency:** Automated weekly backup runs automatically without requiring an administrator to log in.
- **Duplicate Run Prevention:** Built-in threshold check prevents duplicate archives if cron or Windows Task Scheduler runs more frequently than every 7 days.
- **Pure PHP Engine:** Uses Native PDO with buffered cursors for MariaDB/MySQL database exports and `\ZipArchive` with optional AES-256 encryption.
- **Tamper-Evident Integrity:** Every backup generates a cryptographic SHA-256 hash stored in `backup_records` and verified prior to any download or restore.
- **Pre-Restore DR Safety Snapshot:** Before executing any database restoration, the engine automatically creates a safety snapshot (`SAFETY_PRE_RESTORE`), preventing accidental data loss during rollbacks.
- **DR Metadata Preservation:** Disaster recovery ledger tables (`backup_records`, `restore_logs`, `audit_cleanup_logs`) are protected during restoration to ensure historical audit trails are never overwritten.
- **Web-Root Isolation:** Archives are stored strictly in `storage/backups/`, shielded with Apache `.htaccess` deny rules and nginx access blocks. Direct web access returns HTTP 403. Downloads require an authenticated admin session via `GET /settings/backups/{id}/download`.
- **Secondary Storage Sync:** Supports automated copying of backup archives to secondary storage destinations (NAS, network shares, or mounted cloud sync directories).

---

## 2. Backup Archive Structure & Contents

Each generated backup archive is named according to standard timestamp format:  
`storage/backups/WTSBill_Backup_YYYY-MM-DD_HH-MM-SS.zip`

### Archive Contents:
```
WTSBill_Backup_2026-11-06_08-54-31.zip
├── database.sql                  # Complete SQL schema & data dump across all tables
├── audit_logs_archive.json       # JSON export of audit trail prior to cleanup
├── config_summary.json           # Sanitized application settings (passwords redacted)
├── metadata.json                 # Verification metadata & checksums
└── uploads/                      # System attachments, signatures, logos & documents
    ├── logos/
    └── documents/
```

### Manifest Schema (`metadata.json`):
```json
{
  "file_name": "WTSBill_Backup_2026-11-06_08-54-31.zip",
  "backup_type": "SCHEDULED_WEEKLY",
  "created_at": "2026-11-06 08:54:31",
  "created_by": "CLI_SCHEDULER",
  "timezone": "Europe/Berlin",
  "frequency": "7_DAYS_WEEKLY",
  "audit_logs_count": 142,
  "uploaded_files_count": 8,
  "database_dump_size": 245120,
  "is_encrypted": 0
}
```

---

## 3. Automated Weekly Scheduler & Duplicate Run Prevention

The backup engine includes a standalone CLI scheduler runner located at `bin/scheduler.php`.

### CLI Commands:
```bash
# Run weekly automated backup (safely skips if already created within 7 days)
php bin/scheduler.php backup --weekly

# Force immediate backup creation regardless of 7-day schedule
php bin/scheduler.php backup --force

# Inspect backup schedule status, last backup, and next due date
php bin/scheduler.php status

# Run post-backup atomic audit log cleanup (default 7 days retention)
php bin/scheduler.php cleanup-audit --retention=7

# Execute combined weekly pipeline (Backup -> Verify -> Archive -> Purge Audit)
php bin/scheduler.php run-all
```

### Duplicate Run Prevention Logic:
```php
public static function isBackupDue(int $companyId, int $intervalDays = 7): bool
{
    $last = self::getLastSuccessful($companyId);
    if (!$last) return true;

    $lastTimestamp = strtotime((string)$last['created_at']);
    $secondsSinceLast = time() - $lastTimestamp;
    return $secondsSinceLast >= ($intervalDays * 86400);
}
```
If a scheduled task runs daily or hourly, `isBackupDue()` returns `false` until the full 7-day interval has elapsed, skipping duplicate archive generation with a clean exit status.

---

## 4. Backup Storage, Encryption & Secondary Destination

### 4.1 Storage Outside Public Web Root
Backups are written exclusively to `storage/backups/`, which is located outside the public web root (`public/`). A `.htaccess` file with `Deny from all` is automatically created.

### 4.2 AES-256 Archive Encryption
When configured via `BACKUP_ENCRYPTION_PASSWORD` in `.env` or passed via admin API:
- Files inside the ZIP are encrypted using PKWARE AES-256 (`ZipArchive::EM_AES_256`).
- Archive flag `is_encrypted = 1` is recorded in `backup_records`.
- Restoring encrypted archives requires the matching encryption key.

### 4.3 Secondary Storage Destination Sync
If configured via `BACKUP_SECONDARY_PATH`:
- Upon verified creation of the primary archive, a secondary copy is automatically written to the designated secondary path (e.g. `//nas-server/backups/wtsbill` or a mounted cloud storage volume).
- Storage location is marked as `LOCAL_AND_SECONDARY` in `backup_records`.

---

## 5. Verification & Failure Handling

### 5.1 Verification Checklist:
1. Verify archive file exists on disk and `filesize > 0`.
2. Verify ZIP archive opens and is readable via `ZipArchive`.
3. Verify required files are included (`database.sql`, `audit_logs_archive.json`, `config_summary.json`, `metadata.json`).
4. Calculate SHA-256 checksum and store in `backup_records`.
5. Verify `database.sql` contains valid `CREATE TABLE` and `INSERT INTO` statements.

### 5.2 Failure Handling & Admin Alerting:
If backup creation fails (e.g. disk full, permissions error, lock conflict):
- Previous successful backups are strictly preserved.
- The failure is logged in `backup_records` with `status = 'FAILED'` and the exact `error_message`.
- An urgent in-app notification is dispatched to all administrators via `NotificationService::createNotification()` with `priority = 'CRITICAL'`.
- Transient errors trigger automated retry with exponential backoff (up to 3 attempts via `createBackupWithRetry()`).

---

## 6. Backup Dashboard & Authorized Actions

The administrator Backup Management dashboard is accessible under **Enterprise System Settings > Backup & Disaster Recovery** (`/settings?tab=backups`):

### Displayed Information:
- **Last Successful Backup:** Timestamp, filename, and file size in KB/MB.
- **Next Scheduled Run:** Calculated 7-day interval date and overdue status.
- **Backup Frequency:** "Every 7 Days (Automatic Weekly)".
- **Backup Integrity:** SHA-256 Verified indicator.
- **Backup History Table:** Date, filename, type (`SCHEDULED_WEEKLY`, `MANUAL`, `SAFETY_PRE_RESTORE`), size, checksum, and verification status.

### Authorized Actions:
1. **+ Create Backup Now (`POST /settings/backups/create`):** Triggers immediate on-demand verified backup.
2. **Download Backup (`GET /settings/backups/{id}/download`):** Streams the ZIP archive directly to the administrator with verified headers.
3. **Restore Backup (`POST /settings/backups/{id}/restore`):** Opens confirmation modal requiring user to type `RESTORE`. Automatically generates a safety snapshot (`SAFETY_PRE_RESTORE`) before restoring database tables.
4. **Delete Backup with Confirmation (`POST /settings/backups/{id}/delete`):**
   - Opens modal confirming permanent deletion from disk.
   - **Safety Policy:** Strictly blocks deletion of the only remaining successful backup archive.
   - Deletes file from disk, updates record status to `DELETED`, and logs to `audit_logs`.

---

## 7. Seven-Day Audit Log Retention Policy

To balance regulatory compliance with optimal query performance:
1. **Zero Data Loss Invariant:** If backup generation fails or checksum verification fails, audit log cleanup is **immediately aborted**.
2. **Archived First:** Eligible audit logs are exported into `audit_logs_archive.json` inside the backup ZIP before any database rows are deleted.
3. **Retention Threshold:** Default retention window is **7 days**. Logs newer than 7 days are never purged.
4. **Security Exemption:** High-severity security events (`AUTH_FAILURE`, `PERMISSION_VIOLATION`, `UNAUTHORIZED_ACCESS`, `RESTORE_EXECUTION`) are permanently retained in `audit_logs`.
5. **Cleanup Trail:** Every purge action records an audit record in `audit_cleanup_logs` specifying `records_archived`, `records_deleted`, `retention_days`, and `backup_record_id`.
