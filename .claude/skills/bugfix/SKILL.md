---
name: bugfix
description: Run the bug pipeline for Sroor ERP — debugger (reproduce, root cause, minimal fix) → qa-tester (regression test) → quality-gate → code-reviewer — from a bug description. Use when the user types /bugfix or reports a bug, failing test, 500, or wrong total/balance/stock figure to be fixed end to end.
argument-hint: <bug description, error text, or failing test name>
---

# /bugfix — bug pipeline

The bug to fix is: **$ARGUMENTS**

You are the orchestrator. Each agent sees only the brief you write.

## 0. Intake (you, no agent)
- If `$ARGUMENTS` is empty, ask the user what is failing and stop.
- Collect what is already known: exact error text, endpoint or page, tenant/store, steps, and whether it is local or production.
- A production-only bug is investigated from code and logs the user provides. Do not connect to the live server or run any root-level `check_*` / `fix_*` script.

## 1. Fix — `debugger`
Brief it to: reproduce first (a failing test or a concrete request), find the root cause, apply the smallest correct fix, and report cause + files changed. If it cannot reproduce, it reports that and changes nothing — relay that to the user instead of guessing a fix.

## 2. Regression test — `qa-tester`
A test that fails without the fix and passes with it. For money, stock, or treasury bugs also assert the stored figures as decimal strings and cover the rollback path. Run the related suite, and the full suite if a shared Service, middleware, tenancy, or auth was touched.

## 3. Gate — `quality-gatekeeper`
Runs the `quality-gate` skill on the changed files until green.

## 4. Review — `code-reviewer` (+ `security-auditor`)
- `code-reviewer` on the diff, always.
- `security-auditor` as well when the fix touches auth, tenancy, middleware, routes, or uploads.
- Confirmed findings go back to `debugger`; then re-run stage 3.

## 5. Docs (only when non-obvious)
`docs-historian` writes a `docs/history/` entry when the root cause was surprising or the fix changes behaviour. Skip for trivial fixes.

## Rules for the whole run
- Keep the fix minimal. A legacy violation next to the bug is mentioned in the report, not refactored.
- If the bug left bad data behind (wrong balances, stock), report the affected scope and a proposed correction — never run a data fix yourself.
- Never commit, push, or deploy.
- Final report to the user, in Arabic: root cause in one or two sentences, the fix, the regression test name, test and gate results (including any failure), and the suggested commit message.
