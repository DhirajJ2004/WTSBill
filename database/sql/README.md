# WTSBill ERP — SQL Guidelines & Script Policy

This directory contains standalone SQL audit, backup, and diagnostic scripts.

---

## 1. Safety & Execution Policy

1. **Prepared Statements Only:** All application queries with variable parameters must strictly use PDO prepared statements via `Database::select()`, `Database::selectOne()`, `Database::insert()`, `Database::update()`, or `Database::delete()`.
2. **Transaction Boundaries:** Operations mutating multiple tables (e.g. Invoicing + Inventory + Journal Postings) must be wrapped inside `Database::transaction(callable)`.
3. **Savepoint Nesting:** Nested transactions use native MySQL/MariaDB savepoints (`SAVEPOINT TRANS_POINT_N`), guaranteeing safe partial rollbacks without breaking outer transactions.
4. **No Destructive SQL:** `DROP`, `TRUNCATE`, and column renames are prohibited.

---

## 2. Backup & Restore Commands

### Create Schema-Only Backup:
```bash
mysqldump -u root -p --no-data wtsbill_db > storage/backups/wtsbill_db_schema_$(date +%Y%m%d).sql
```

### Create Complete Database Backup:
```bash
mysqldump -u root -p wtsbill_db > storage/backups/wtsbill_db_full_$(date +%Y%m%d).sql
```

### Non-Destructive Table Verification Query:
```sql
SELECT table_name, table_rows, engine, collation 
FROM information_schema.tables 
WHERE table_schema = 'wtsbill_db';
```
