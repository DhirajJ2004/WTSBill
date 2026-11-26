# Project AI Agent Skills

This directory contains reusable workspace Agent Skills stored in `.agents/skills/` for automatic discovery by Antigravity.

## Available Skills

| Skill | Purpose | When to Use |
| :--- | :--- | :--- |
| `ui-ux-pro-designer` | SaaS & ERP UI/UX design, design tokens, hierarchy, typography, spacing, and accessibility. | Designing new screens, auditing visual consistency, or improving layout/usability. |
| `frontend-developer` | Clean HTML, CSS, JavaScript, responsive layouts, form validation, and interactive components. | Building or fixing UI components, modals, filters, AJAX calls, or client-side bugs. |
| `php-backend-expert` | Secure PHP architecture, separation of concerns, validation, auth, sessions, and error handling. | Building or refactoring controllers, services, API endpoints, or backend logic. |
| `mysql-database-expert` | Normalized MySQL schemas, foreign keys, indexing, query optimization, transactions, and migrations. | Designing tables, optimizing slow queries, reviewing `EXPLAIN` plans, or planning migrations. |
| `browser-qa-tester` | Real-browser end-to-end QA testing of navigation, forms, CRUD, search, pagination, and console/network errors. | Verifying live application workflows, UI responsiveness, and post-change regressions. |
| `senior-code-reviewer` | Rigorous code review for bugs, security issues, duplication, edge cases, and maintainability. | Reviewing diffs, pull requests, or modules before merging or deploying. |
| `web-security-expert` | OWASP Top 10 auditing and hardening (SQLi, XSS, CSRF, auth, sessions, uploads, secrets). | Security audits, hardening endpoints, or reviewing sensitive auth/upload flows. |
| `performance-optimizer` | Frontend, backend, network, and MySQL performance profiling with before/after benchmarks. | Diagnosing slow pages, N+1 queries, large payloads, or caching opportunities. |

## How Antigravity Discovers & Invokes Skills

Antigravity automatically discovers skills located in `.agents/skills/<skill-name>/SKILL.md` at the workspace root. Using **progressive disclosure**, only each skill's `name` and `description` are loaded initially; the full `SKILL.md` instructions are loaded on demand when a task matches or when you explicitly request the skill.

### Automatic Invocation

Describe your task naturally. When your request matches a skill's `description`, the agent automatically reads and follows that skill's `SKILL.md`.

### Manual Invocation

You can explicitly invoke any skill in your prompt by naming it or referencing its `SKILL.md` file:

- `Use the ui-ux-pro-designer skill to audit the invoice creation page layout.`
- `Use the frontend-developer skill to fix the responsive table overflow and modal validation.`
- `Use the php-backend-expert skill to refactor the billing controller with proper validation and error handling.`
- `Use the mysql-database-expert skill to review indexes and foreign keys on the invoices and payments tables.`
- `Use the browser-qa-tester skill to test the full customer CRUD and invoice search workflow in a real browser.`
- `Use the senior-code-reviewer skill to review my recent git changes for bugs and edge cases.`
- `Use the web-security-expert skill to audit all POST endpoints for CSRF, SQLi, and authorization checks.`
- `Use the performance-optimizer skill to profile the dashboard load time and eliminate N+1 queries.`

## Example Prompts

- **Design + Frontend**: *"Use `ui-ux-pro-designer` and `frontend-developer` to redesign the customer filter bar for mobile and desktop without changing form field names."*
- **Backend + Database**: *"Use `php-backend-expert` and `mysql-database-expert` to make invoice creation atomic inside a transaction with proper rollback."*
- **Security + Code Review**: *"Use `senior-code-reviewer` and `web-security-expert` to audit `app/` and `routes/` and report findings by severity."*
- **QA + Performance**: *"Use `browser-qa-tester` to verify the billing workflow in the browser, then use `performance-optimizer` to measure and reduce slow queries."*

## How to Add a New Skill

1. Create a new lowercase, hyphenated folder under `.agents/skills/`, e.g. `.agents/skills/my-new-skill/`.
2. Create `SKILL.md` inside that folder starting with valid YAML frontmatter:

```markdown
---
name: my-new-skill
description: >-
  Third-person summary of what this skill does and the exact situations
  when the agent should activate it.
---

# My New Skill

Step-by-step instructions, checklists, and guardrails...
```

3. Optionally add `references/`, `scripts/`, `examples/`, or `resources/` subdirectories and link to them with relative paths from `SKILL.md`.
4. Verify the folder name matches `name` in the frontmatter and test by asking the agent to use the new skill.

## How to Maintain and Update Skills

- **Keep `description` accurate**: Update the frontmatter `description` whenever the skill's scope expands so automatic triggering stays reliable.
- **Keep `SKILL.md` concise**: Move large reference tables or checklists into `references/` and link to them so context usage stays lean.
- **Enforce project protection rules**: Ensure updates preserve non-destructive defaults, backup requirements, and honest reporting rules.
- **Review periodically**: After major architecture or stack changes, update skill procedures and verify frontmatter syntax across all `.agents/skills/*/SKILL.md` files.
