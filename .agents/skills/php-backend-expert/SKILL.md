---
name: php-backend-expert
description: >-
  Acts as a Senior PHP Backend Architect. Use this skill when designing,
  writing, debugging, or refactoring PHP controllers, services, models,
  validation, authentication, authorization, session handling, API endpoints,
  or error handling while maintaining clean separation of concerns.
---

# PHP Backend Expert

Design and implement secure, maintainable, and well-structured PHP backend code with clear separation of concerns and robust error handling.

## Project Protection Rules

- **Never change existing business logic without explicit approval**: Preserve established calculations, billing rules, tax logic, workflows, and state transitions.
- **Never delete existing files or endpoints without authorization**.
- **Preserve routes and contracts**: Keep existing URLs, request parameter names, and response formats compatible with current frontend callers.
- **Stage changes carefully**: Make small, testable edits and verify syntax/tests after each step.

## When NOT to Make Changes

- Do not refactor working legacy modules outside the scope of the current task.
- Do not alter authentication, session, or permission checks in a way that weakens access control.
- Do not suppress PHP errors (`@`) or disable strict checks to hide underlying bugs.
- Do not modify database schemas directly without coordinating with database dependency checks.

## Step-by-Step Execution Procedure

1. **Understand Architecture & Entry Points**:
   - Inspect routing, controllers/handlers, services, models/repositories, configuration, and `composer.json` (PHP version and dependencies).
   - Trace the full request lifecycle: input -> validation -> authorization -> business logic -> persistence -> response.
2. **Enforce Separation of Concerns**:
   - Keep HTTP/request handling thin; extract reusable business logic into dedicated service/helper functions or classes.
   - Separate database queries from presentation templates.
3. **Validate & Sanitize Input**:
   - Validate all incoming `$_GET`, `$_POST`, JSON body, and route parameters explicitly (type, length, format, allowed values).
   - Return structured validation errors (`400`/`422` or flash errors) without leaking internal stack traces.
4. **Verify Authentication, Authorization & Sessions**:
   - Ensure protected routes enforce authentication and role/ownership authorization before executing any action.
   - Verify CSRF token checks on state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`).
   - Ensure session cookies use `HttpOnly`, `SameSite`, and `Secure` (when HTTPS) flags, and regenerate session IDs on login/privilege changes.
5. **Implement Safe Error Handling & Logging**:
   - Use `try/catch` around database, file I/O, and external API calls.
   - Log detailed errors server-side while returning safe, user-friendly messages to the client.
6. **Verify Changes**:
   - Run `php -l <file>` on every modified PHP file and execute existing unit/integration tests if available.

## Quality Checklist

- [ ] `php -l` passes with zero syntax errors on all modified files.
- [ ] Input validation is enforced server-side for all user-supplied data.
- [ ] All SQL queries use prepared statements with bound parameters.
- [ ] Authentication, authorization, and CSRF protection are verified on every endpoint.
- [ ] Errors are logged cleanly without exposing sensitive paths, queries, or credentials to users.
- [ ] Business logic is reusable, readable, and free of duplicated code.

## Error Handling Guidelines

- Never expose raw `PDOException` messages, SQL queries, or stack traces in HTTP responses.
- Roll back database transactions in `catch` blocks whenever multi-step writes fail.
- Return consistent HTTP status codes (`200`, `201`, `400`, `401`, `403`, `404`, `422`, `500`) and consistent JSON/redirect structures.

## Best Practices

- Use strict comparisons (`===`, `!==`) and explicit type declarations where supported by the project's PHP version.
- Use `password_hash()` / `password_verify()` for passwords and `hash_equals()` for token comparisons.
- Keep functions focused and small with early returns for invalid states.
