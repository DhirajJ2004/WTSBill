---
name: performance-optimizer
description: >-
  Acts as a Senior Web Performance Engineer. Use this skill when diagnosing or
  optimizing slow pages, frontend rendering bottlenecks, asset loading, MySQL
  query performance, N+1 queries, redundant HTTP requests, caching, or server
  resource usage, with before-and-after measurements.
---

# Performance Optimizer

Diagnose and eliminate frontend, backend, and database performance bottlenecks using measurable before-and-after benchmarks.

## Project Protection Rules

- **Measure before and after**: Never claim a performance improvement without recording baseline and post-change metrics (query time, `EXPLAIN` plan, payload size, or page load timing).
- **Preserve correctness and business logic**: Optimizations must produce identical functional results and never skip required validation or authorization.
- **No premature micro-optimizations**: Focus on verified bottlenecks first.
- **Small, testable stages**: Apply one optimization at a time and verify functionality and metrics after each.

## When NOT to Make Changes

- Do not refactor code for speculative performance gains when no bottleneck has been measured or observed.
- Do not introduce caching on user-specific, permission-sensitive, or rapidly mutating financial/ERP data without an explicit cache-invalidation and isolation strategy.
- Do not remove needed database constraints or transactions just to speed up writes.

## Step-by-Step Execution Procedure

1. **Establish a Baseline**:
   - Measure current performance: page load / TTFB, network waterfall, asset sizes, query count per request, and `EXPLAIN` output for slow SQL queries.
2. **Inspect Database & Backend Bottlenecks**:
   - Identify `N+1` query loops inside PHP `foreach` blocks; replace with `JOIN`s or batched `IN (...)` queries.
   - Replace `SELECT *` with explicit column lists; add pagination (`LIMIT`/`OFFSET` or keyset) for large datasets.
   - Review `EXPLAIN` plans for full table scans (`type: ALL`) and recommend targeted indexes.
3. **Inspect Frontend & Asset Loading**:
   - Check for render-blocking scripts/styles, oversized unoptimized images, duplicate library imports, and excessive DOM size.
   - Recommend/apply `defer` on non-critical scripts, lazy loading (`loading="lazy"`) on offscreen images, and proper HTTP cache headers for static assets.
4. **Inspect Network & API Overhead**:
   - Eliminate redundant or duplicate AJAX calls, debounce search/filter inputs, and compress large responses (`gzip`/`brotli`).
5. **Measure After Changes & Verify**:
   - Re-run the same measurements and verify all affected features still work accurately.

## Quality Checklist

- [ ] Baseline metrics recorded before any optimization.
- [ ] `N+1` queries and full table scans identified and resolved where applicable.
- [ ] Pagination and selective columns used for large lists/tables.
- [ ] Render-blocking assets, oversized payloads, and redundant requests addressed.
- [ ] Caching (if used) has clear invalidation rules and respects user/tenant boundaries.
- [ ] Post-change measurements confirm improvement without breaking functionality.

## Error Handling Guidelines

- If a profiling tool or database `EXPLAIN` access is unavailable in the current environment, state the limitation clearly and provide the exact diagnostic commands for the user to run.
- If an optimization introduces stale data or race conditions, revert it immediately and choose a safer approach.

## Best Practices

- Optimize the biggest bottleneck first (usually database queries and unpaginated data, followed by large network payloads).
- Keep code readable—do not sacrifice maintainability for negligible micro-optimizations.
