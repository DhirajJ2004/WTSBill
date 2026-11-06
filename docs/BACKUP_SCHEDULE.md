# WTSBill ERP — Automatic 7-Day Backup Schedule & Automation Guide

**Document Reference:** `DOC-OPS-SCHEDULE-001`  
**Version:** 1.0.0  
**Effective Date:** 2026-11-06  
**Auditor / Architect:** Senior DevOps & Database Infrastructure Architect  
**Status:** PRODUCTION READY & VERIFIED  

---

## 1. Overview & Operational Policy

WTSBill ERP enforces an automated weekly disaster recovery policy:
- **Frequency:** Every 7 days (once per week).
- **Timezone:** Uses the server's configured operational timezone (e.g., `Asia/Kolkata` or `Europe/Berlin`).
- **Autonomous Execution:** Runs in the background via operating system task engines without requiring an administrator to log into the web UI.
- **Duplicate Prevention:** Safe interval gating skips redundant executions if the scheduler triggers more frequently than every 7 days.
- **Zero Data Loss:** Complete database dumps, uploaded documents, sanitized configuration, and audit logs are bundled into SHA-256 verified ZIP archives outside the public web root.

---

## 2. CLI Scheduler Runner (`bin/scheduler.php`)

The centralized command-line interface handles scheduled backups, verification, and audit retention:

```bash
# Run weekly automated backup with duplicate prevention
php bin/scheduler.php backup --weekly

# Force immediate backup creation regardless of previous runs
php bin/scheduler.php backup --force

# Check current backup schedule status, last run, and next due date
php bin/scheduler.php status

# Run post-backup atomic audit log cleanup (default 7 days retention)
php bin/scheduler.php cleanup-audit --retention=7

# Full weekly pipeline (Backup -> Verify -> Archive -> Purge Audit)
php bin/scheduler.php run-all
```

---

## 3. Windows Task Scheduler Setup (Windows / XAMPP / IIS)

To schedule automatic backups on Windows servers:

### Option A: Via PowerShell (Automated Setup)
Open PowerShell as **Administrator** and run:

```powershell
$action = New-ScheduledTaskAction `
    -Execute "C:\xampp\php\php.exe" `
    -Argument "c:\xampp\htdocs\WTSBill\bin\scheduler.php run-all" `
    -WorkingDirectory "c:\xampp\htdocs\WTSBill"

$trigger = New-ScheduledTaskTrigger `
    -Weekly `
    -DaysOfWeek Sunday `
    -At "02:00AM"

$principal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

Register-ScheduledTask `
    -TaskName "WTSBill Weekly Backup" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Description "Automated 7-day backup and audit retention for WTSBill ERP"
```

### Option B: Via Windows Task Scheduler GUI (`taskschd.msc`)
1. Open **Task Scheduler** (`Win + R` -> `taskschd.msc`).
2. Click **Create Task** in the right-hand Actions panel.
3. **General Tab:**
   - Name: `WTSBill Weekly Backup`
   - Security options: Select **Run whether user is logged on or not**.
   - Check **Run with highest privileges**.
4. **Triggers Tab:**
   - Click **New...**
   - Begin the task: **On a schedule**.
   - Settings: **Weekly**, recur every 1 week on **Sunday** at `02:00:00 AM`.
   - Enabled: **Checked**. Click **OK**.
5. **Actions Tab:**
   - Click **New...**
   - Action: **Start a program**.
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `c:\xampp\htdocs\WTSBill\bin\scheduler.php run-all`
   - Start in: `c:\xampp\htdocs\WTSBill`
   - Click **OK**.
6. **Conditions Tab:**
   - Uncheck "Start the task only if the computer is on AC power".
   - Check "Wake the computer to run this task" if applicable.
7. Click **OK** and provide administrator credentials if prompted.

---

## 4. Linux Crontab Setup (Ubuntu / Debian / CentOS / RedHat)

To schedule automatic weekly backups on Linux production environments:

### Step 1: Open Server Crontab
```bash
sudo crontab -e
```

### Step 2: Add Scheduled Job Entry
Add the following line to execute every Sunday at 02:00 AM:

```cron
# WTSBill ERP Automated 7-Day Backup & Audit Cleanup
0 2 * * 0 /usr/bin/php /var/www/wtsbill/bin/scheduler.php run-all >> /var/log/wtsbill_backup.log 2>&1
```

### Step 3: Daily Safe Polling Alternative (Duplicate Prevention)
Because `BackupService::isBackupDue()` enforces the 7-day interval check, you may safely run the scheduler daily. The system will execute once per week and skip on other days:

```cron
# Daily trigger — runs backup only if 7 days have passed since last successful run
0 2 * * * /usr/bin/php /var/www/wtsbill/bin/scheduler.php backup --weekly >> /var/log/wtsbill_backup.log 2>&1
```

---

## 5. Duplicate Run Prevention Architecture

To prevent duplicate archives when scheduled tasks overlap or trigger multiple times:

```mermaid
flowchart TD
    A["Scheduler Trigger (Cron / Windows Task)"] --> B["Check Concurrency Lock (.backup.lock)"]
    B -- Lock Active (<10m) --> C["Abort: Job Currently Running"]
    B -- No Lock --> D["Query Last Successful Backup from DB"]
    D --> E{"Has 7 Days Elapsed?"}
    E -- No (Recent Backup Exists) --> F["Log Skipped Status & Exit 0"]
    E -- Yes (or --force flag) --> G["Acquire Lock & Start Backup"]
    G --> H["Dump MySQL DB + Config + Audit + Uploads"]
    H --> I["Create ZIP & Optional AES-256 Encryption"]
    I --> J["Verify Archive & Calculate SHA-256 Checksum"]
    J -- Verification Pass --> K["Insert backup_records (SUCCESS)"]
    K --> L["Sync to Secondary Storage (if configured)"]
    L --> M["Run 7-Day Audit Log Cleanup"]
    M --> N["Release Concurrency Lock"]
    J -- Verification Fail --> O["Insert backup_records (FAILED)"]
    O --> P["Dispatch Critical Admin In-App Notification"]
    P --> N
```

---

## 6. Failure Handling, Retries & Notifications

If a backup fails at any point in the pipeline:
1. **Previous Backups Protected:** No existing archives or database records are removed.
2. **Error Logging:** The failure reason is recorded in `backup_records` (`status = 'FAILED'`, `error_message = ...`).
3. **Critical Admin Alert:** An in-app alert is generated in `in_app_notifications` with `priority = 'CRITICAL'`.
4. **Automated Retries:** `BackupService::createBackupWithRetry()` attempts up to 3 automatic retries with 0.5s backoff before reporting final failure.
5. **Zero Premature Purging:** Audit log retention cleanup is strictly prevented from executing if the backup failed.

---

## 7. Restoration & Disaster Recovery Verification

### Testing Restoration Safely:
1. Open the Backup Management dashboard at `/settings?tab=backups`.
2. Locate the backup archive and click **Restore**.
3. The system enforces safety by:
   - Verifying the SHA-256 checksum against database records before reading the archive.
   - Automatically taking a pre-restore safety snapshot (`SAFETY_PRE_RESTORE`).
   - Requiring the user to type `RESTORE` in the confirmation dialog.
   - Restoring schema and data in transaction blocks with foreign key validation.
   - Recording the outcome in `restore_logs`.

