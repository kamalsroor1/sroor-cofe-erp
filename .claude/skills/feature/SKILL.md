---
name: feature
description: Run the full new-feature pipeline for Sroor ERP — backend-architect → frontend-vue (+ i18n-guardian) → qa-tester → quality-gate → code-reviewer → docs-historian — from a one-line feature description. Use when the user types /feature or asks to build a new feature end to end.
argument-hint: <feature description>
---

# /feature — new feature pipeline

The feature to build is: **$ARGUMENTS**

You are the orchestrator. Agents do the writing; you brief them, check their work, and report. Each agent sees only the brief you write, so every brief names the files, the contract, and what done looks like.

## 0. Scope (you, no agent)
- If `$ARGUMENTS` is empty or too vague to name an endpoint or a screen, ask the user one question and stop.
- Read the code the feature touches, plus `docs/pages/` and `docs/modules/` when they cover it.
- Write down the contract before delegating: routes, request fields, response shape, permission name, tables/columns, the Vue view and its components. Both the backend and frontend briefs quote this same contract.
- Skip any stage the feature does not need (backend-only → no frontend stage; UI-only → no backend stage) and say so in the final report.

## 1. Backend — `backend-architect`
Migration (central vs tenant), model, DTO, Form Request, Action, Resource, filter, policy/permission, route. Golden rules 1–5 and 9 apply. Wait for it to finish; the frontend depends on the real response shape.

## 2. Frontend + strings — `frontend-vue`, then `i18n-guardian`
- `frontend-vue`: thin orchestrator view, components, composable, store/router wiring, skeleton loaders, RTL + dark/light + touch.
- `i18n-guardian` after it: no hardcoded text in the new PHP or Vue, ar/en key parity, `php artisan lang:export`.

## 3. Tests — `qa-tester`
Feature test per new endpoint covering 200 / 422 / 401 / 403 / tenant isolation. Add rollback and concurrency tests when money, stock, or treasury is mutated. Add a Playwright spec only when the feature adds a user flow.

## 4. Gate — `quality-gatekeeper`
Runs the `quality-gate` skill on the changed files. Fix and re-run until green. The Larastan baseline must not grow.

## 5. Review — `code-reviewer` (+ `security-auditor`)
- `code-reviewer` on the full diff, always.
- `security-auditor` as well when the diff touches auth, tenancy, middleware, routes, uploads, or exports.
- Send confirmed findings back to the agent that owns the code, then re-run stage 4 on what changed.

## 6. Docs — `docs-historian`
`docs/history/YYYY-MM-DD/NN-slug.md`, plus the page/module doc when one exists or the feature adds a page.

## Rules for the whole run
- Verify each agent's report against the diff and the test output before moving on. A report is a claim.
- Never commit, push, deploy, or run a root-level live script. Stop at a clean working tree change.
- Final report to the user, in Arabic: what was built, files changed, test and gate results (including any failure), review findings left open, and the suggested commit message.
