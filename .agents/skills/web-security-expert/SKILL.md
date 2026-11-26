---
name: web-security-expert
description: >-
  Acts as a Senior Application Security Engineer. Use this skill when auditing
  or hardening web applications against SQL injection, XSS, CSRF, broken
  authentication/authorization, insecure session management, unsafe file
  uploads, secret exposure, and access control flaws.
---

# Web Security Expert

Identify and remediate web application security vulnerabilities while preserving existing business workflows and user experience.

## Project Protection Rules

- **Never break existing workflows**: Design security fixes (e.g., CSRF tokens, escaping, prepared statements, RBAC checks) so legitimate user flows continue working seamlessly.
- **Never expose or log secrets**: Never print `.env` values, API keys, database passwords, or session tokens in outputs, reports, or commits.
- **No unauthorized destructive changes**: Do not delete files or alter database schemas without explicit approval.
- **Never bypass security controls**: Do not disable auth, CSRF, or validation checks to make something "work".

## When NOT to Make Changes

- Do not modify application files when asked only for a security audit/assessment—deliver a prioritized vulnerability report instead.
- Do not blindly apply output encoding or CSP headers that break legitimate rich-text/JS functionality without testing the affected views.
- Do not change password hashing or session formats in a way that locks out existing users without a migration path.

## Step-by-Step Execution Procedure

1. **Map Attack Surface**:
   - Identify entry points: public routes, authenticated routes, API endpoints, file upload handlers, form submissions, and admin actions.
2. **Audit Core Vulnerability Classes (OWASP Top 10)**:
   - **SQL Injection**: Verify every query uses parameterized prepared statements; flag string concatenation/interpolation in SQL.
   - **XSS (Stored/Reflected/DOM)**: Verify context-appropriate output escaping (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` in PHP, `textContent` in JS).
   - **CSRF**: Verify all state-changing requests validate an unguessable per-session CSRF token with `hash_equals()`.
   - **Authentication & Sessions**: Check password hashing (`password_hash`/`password_verify`), brute-force protections, session regeneration on login, and cookie flags (`HttpOnly`, `Secure`, `SameSite`).
   - **Authorization & IDOR**: Verify every endpoint checks both authentication *and* role/resource ownership (never trust client-supplied `user_id` or `role`).
   - **File Uploads**: Check extension allowlists, MIME/magic-byte validation, randomized filenames, size limits, and non-executable storage outside webroot or with execution disabled.
   - **Secrets & Configuration**: Check `.gitignore`, `.env`, debug flags (`display_errors`), and security headers.
3. **Propose or Implement Minimal-Risk Fixes**:
   - Apply fixes incrementally and verify both security effectiveness and functional workflow compatibility.

## Quality Checklist

- [ ] Zero unparameterized SQL queries with user input.
- [ ] All user-controlled output is properly escaped for its context (HTML, attribute, JS, URL).
- [ ] State-changing endpoints enforce CSRF validation.
- [ ] Authentication, session flags, and role/ownership checks verified on every protected route.
- [ ] File uploads restrict types, sizes, names, and execution permissions.
- [ ] No hardcoded credentials, API keys, or sensitive debug output in responses or repo.

## Error Handling Guidelines

- Security failures (invalid CSRF, unauthorized access, invalid file type) must fail closed (`403`/`400`/`401`) without revealing internal system details.
- Log security-relevant events (failed logins, access denied, invalid tokens) without logging passwords or raw session tokens.

## Best Practices

- Follow defense-in-depth: validate input, parameterize queries, escape output, and enforce least-privilege access control.
- Prefer centralized auth/CSRF middleware or helper functions over ad-hoc per-file checks.
