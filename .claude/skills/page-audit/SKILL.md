---
name: page-audit
description: Run the page-audit pipeline for one Sroor ERP SPA page — frontend-vue → i18n-guardian → qa-tester (E2E) → quality-gate → code-reviewer → docs-historian. Use when the user types /page-audit or asks to audit, clean up, or bring a page up to the project rules.
argument-hint: <page or view name, e.g. customers, PosView>
---

# /page-audit — page audit pipeline

The page to audit is: **$ARGUMENTS**

You are the orchestrator. Each agent sees only the brief you write.

## 0. Locate (you, no agent)
- If `$ARGUMENTS` is empty, ask which page and stop.
- Resolve the name to its view under `backend/resources/js/views/`, its components, composables, store, route, and the API endpoints it calls.
- Read `docs/PAGE_AUDIT_PROMPT.md` (the audit checklist), the page's file in `docs/pages/` if it exists, and its entry in `docs/pages-audit-log.md`.
- List the violations you can already see, so the briefs are specific.
- The audit covers this one page. Shared components it uses are changed only when the page needs it, and every other caller is checked.

## 1. Structure and UI — `frontend-vue`
Thin orchestrator view (≈50–80 lines) with markup in `Components/<Feature>/` and logic in `Composables/`; skeleton shimmer loaders and error/empty states; RTL-first, dark + light, touch targets, CSS-variable theming; money and quantities handled as decimal strings; no console warnings. Behaviour stays the same unless a bug is found — bugs are reported, and fixed only when small and inside the page.

## 2. Strings — `i18n-guardian`
No hardcoded Arabic or English in the page's files, ar/en key parity, `php artisan lang:export`.

## 3. E2E — `qa-tester`
Playwright spec for the page's main flow under `e2e/`, run on the desktop project and on mobile/tablet when the layout differs. Report real results; a spec that cannot run locally is reported as not run.

## 4. Gate — `quality-gatekeeper`
Runs the `quality-gate` skill on the changed files, including `npm run build`.

## 5. Review — `code-reviewer`
On the full diff. Confirmed findings go back to the owning agent; then re-run stage 4.

## 6. Docs — `docs-historian`
Update `docs/pages/<page>.md` and `docs/pages-audit-log.md`, and write the `docs/history/YYYY-MM-DD/NN-slug.md` entry.

## Rules for the whole run
- Backend problems found on the way (missing authorization, wrong response shape) are listed in the report as follow-ups, not fixed here.
- Never commit, push, or deploy.
- Final report to the user, in Arabic: what changed, view line count before and after, build / E2E / gate results (including any failure), open findings and follow-ups, and the suggested commit message.
