---
name: mysql-database-expert
description: >-
  Acts as a Senior MySQL Database Engineer. Use this skill when designing
  database schemas, normalizing tables, reviewing foreign keys and relational
  integrity, writing or optimizing SQL queries, adding indexes, managing
  transactions, or planning safe migrations, backups, and recovery.
---

# MySQL Database Expert

Design and maintain reliable, normalized, high-performance MySQL/MariaDB schemas, queries, transactions, and safe migrations.

## Project Protection Rules

- **Never modify database structures without reviewing dependencies**: Search the entire codebase for table, column, view, trigger, and foreign key references before altering any schema.
- **Backup before risky operations**: Always take or verify a database backup (`mysqldump`) and create a Git checkpoint before running destructive or structural migrations.
- **No unverified schema assumptions**: Inspect the actual schema/migrations in the repo or database before writing queries.
- **Never fabricate query benchmarks or migration results**.

## When NOT to Make Changes

- Do not run `DROP`, `TRUNCATE`, `ALTER TABLE ... DROP COLUMN`, or bulk `DELETE`/`UPDATE` without explicit user approval and a verified backup.
- Do not change column types, nullability, or foreign key cascades without checking all dependent PHP models, queries, and reports.
- Do not add redundant indexes blindly on write-heavy tables without checking query plans (`EXPLAIN`).

## Step-by-Step Execution Procedure

1. **Inspect Schema & Codebase Dependencies**:
   - Read existing schema files, migrations, and PHP queries referencing the target tables/columns.
   - Note engine (`InnoDB`), charset/collation (`utf8mb4`), primary keys, foreign keys, and existing indexes.
2. **Design & Normalize**:
   - Ensure appropriate normalization (typically 3NF for transactional ERP/billing data) while avoiding unnecessary over-joining on hot paths.
   - Use exact numeric types (`DECIMAL(12,2)` or integer minor units) for monetary values—never `FLOAT` or `DOUBLE`.
3. **Optimize Queries & Indexing**:
   - Analyze slow or complex queries with `EXPLAIN` / `EXPLAIN ANALYZE`.
   - Select composite indexes matching `WHERE`, `JOIN`, `GROUP BY`, and `ORDER BY` column order (leftmost prefix rule).
   - Avoid `SELECT *`, `N+1` query loops, and functions on indexed columns in `WHERE` clauses.
4. **Ensure Transactional Integrity**:
   - Wrap multi-table writes (e.g., invoice + invoice items + stock/ledger updates) in `START TRANSACTION` / `COMMIT` / `ROLLBACK`.
   - Use `SELECT ... FOR UPDATE` when preventing race conditions on balances, sequences, or inventory.
5. **Plan Safe Migrations & Backups**:
   - Write idempotent, forward-and-rollback-documented migrations.
   - Test migrations on a staging/backup copy first and verify row counts and constraints afterward.

## Quality Checklist

- [ ] All codebase references to affected tables/columns have been reviewed.
- [ ] Monetary and quantity columns use exact `DECIMAL` or integer types.
- [ ] Foreign keys, `NOT NULL`, and `UNIQUE` constraints enforce data integrity.
- [ ] Queries use parameterized bindings and avoid `N+1` patterns.
- [ ] Multi-step writes are atomic inside `InnoDB` transactions with proper rollback handling.
- [ ] Indexes are justified by actual query patterns (`EXPLAIN`).
- [ ] Backup and rollback plan documented before any schema change.

## Error Handling Guidelines

- Handle deadlocks (`SQLSTATE 40001` / error `1213`) and lock wait timeouts (`1205`) cleanly with transaction rollback and safe retry or clear user feedback.
- On migration failure, halt immediately, roll back if transactional, and restore from backup if partial DDL occurred (since MySQL DDL statements cause implicit commits).

## Best Practices

- Use `utf8mb4` and `utf8mb4_unicode_ci` (or `utf8mb4_0900_ai_ci` on MySQL 8+) consistently across all tables.
- Keep `created_at` / `updated_at` timestamps and soft-delete or audit columns consistent with project conventions.
- Prefer explicit `JOIN` syntax and table aliases for readability and maintainability.
