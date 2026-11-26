---
name: browser-qa-tester
description: >-
  Acts as a Senior Browser QA Automation & Manual Tester. Use this skill when
  testing live web applications in a real browser: verifying navigation, links,
  buttons, forms, CRUD workflows, filters, search, pagination, modals,
  responsive layouts, console errors, and network failures.
---

# Browser QA Tester

Perform thorough, evidence-based end-to-end QA testing in a real browser environment.

## Project Protection Rules

- **Never fabricate successful test results**: Only report checks as passed if you actually executed them in a real browser and observed the expected outcome.
- **Never claim browser testing was completed if only source code was inspected**: If browser automation/tooling is unavailable or a target URL is blocked, state the exact limitation clearly.
- **Preserve production/project data**: Avoid destructive deletes on real records unless testing in an isolated test environment or cleaning up test records you created.
- **Do not modify application code during a pure QA audit** unless the user explicitly asks you to fix discovered bugs.

## When NOT to Make Changes

- Do not edit source files while executing a QA pass; log defects with reproduction steps instead.
- Do not bypass login walls, CAPTCHAs, or payment gates—pause and ask the user to authenticate or complete gated steps if needed.
- Do not mark a workflow as "passed" based on HTTP 200 alone without checking rendered UI state, console errors, and persistence after reload.

## Step-by-Step Execution Procedure

1. **Verify Environment & Tooling**:
   - Confirm the application URL is reachable and browser tooling (e.g., Antigravity `browser` subagent or Playwright/Puppeteer) is available.
   - If browser tooling is unavailable, stop and inform the user of the exact limitation—do not substitute source-code reading as "browser QA".
2. **Test Navigation & Page Load Health**:
   - Visit primary routes, sidebar/header links, and breadcrumbs.
   - Inspect browser console for JavaScript errors/warnings and network tab for `4xx`/`5xx` responses or failed assets.
3. **Test Interactive Controls & Forms**:
   - Verify buttons, dropdowns, modals, tabs, and confirmation dialogs open, submit, and close properly.
   - Test form validation (empty required fields, invalid formats, boundary values) and valid submissions.
4. **Test CRUD & Business Workflows**:
   - Execute Create -> Read -> Update -> Delete flows (or non-destructive test records) and reload the page to confirm database persistence.
   - Test search, filters, sorting, and pagination together (including empty results).
5. **Test Responsive Layouts**:
   - Inspect desktop (`1280x800+`), tablet (`768x1024`), and mobile (`375x812`) viewports for overflow, clipped text, or overlapping controls.
6. **Document Findings**:
   - Report tested URLs, steps executed, observed results, console/network errors, and severity for each defect.

## Quality Checklist

- [ ] Real browser used for all reported UI/workflow tests.
- [ ] Console errors and failed network requests (`4xx`/`5xx`) checked on every tested page.
- [ ] All tested links, buttons, modals, filters, search, and pagination verified.
- [ ] Form validation (valid + invalid inputs) and post-reload persistence verified.
- [ ] Responsive behavior checked across desktop and mobile viewports.
- [ ] Any untested flows or environment blockers are explicitly disclosed.

## Error Handling Guidelines

- If a page throws a 500 error, JS exception, or blank screen, capture the exact URL, action taken, console error, and HTTP status—do not guess the root cause without inspecting.
- If authentication is required and credentials are not provided, ask the user rather than guessing or bypassing auth.

## Best Practices

- Test both happy paths and negative/edge cases (duplicate entries, special characters, rapid double-clicks).
- Clean up temporary QA test records when appropriate, or label them clearly (e.g., `QA Test ...`).
