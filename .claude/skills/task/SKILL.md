---
name: task
description: General task runner for Sroor ERP — classifies any task, routes it to the matching pipeline (/feature, /bugfix, /page-audit) or composes the right agents for it, then gates, reviews, and reports. Use when the user types /task, or gives a task that is not clearly a new feature, a bug, or a page audit (refactor, controller audit, tests, i18n sweep, CI/infra, research, docs, tooling).
argument-hint: <task description>
---

# /task — general task runner

The task is: **$ARGUMENTS**

You are the orchestrator. Each agent sees only the brief you write, so every brief names the files, the goal, and what done looks like.

## 0. Understand and classify (you, no agent)
- If `$ARGUMENTS` is empty or cannot be turned into a concrete outcome, ask the user one question and stop.
- Read the code the task touches before deciding anything.
- If it is plainly one of these, follow that skill instead and stop reading here:
  - new feature end to end → `feature`
  - bug, failing test, 500, wrong figure → `bugfix`
  - audit of one SPA page → `page-audit`

## 1. Size it
- **Small** (a question, one edit, a handful of tool calls): do it yourself. No agents. Run the narrowest relevant test, and go to stage 4 only if code changed.
- **Anything else**: pick agents from the table, in the order shown. Independent pieces run in parallel in one message; pieces that touch the same files run one after another.

## 2. Route

| Task type | Agents, in order |
|---|---|
| Backend refactor / controller audit (`docs/CONTROLLER_AUDIT_PROMPT.md`) | `qa-tester` (lock current behaviour first) → `backend-architect` → `code-reviewer` |
| Backend change (endpoint, schema, Action, service) | `backend-architect` → `qa-tester` |
| Frontend change (component, composable, store, styling) | `frontend-vue` → `i18n-guardian` when text changed |
| Tests only | `qa-tester` |
| Translation sweep / missing keys | `i18n-guardian` |
| Lint, format, Larastan, baseline | `quality-gatekeeper` |
| CI, deploy pipeline, backups, queue/cron, env | `devops-engineer` (+ `security-auditor` when secrets or access are involved) |
| Security or pre-release audit | `security-auditor` — findings only; fixes are a separate step the user approves |
| Competitor / feature research | `product-researcher` |
| Documentation refresh | `docs-historian` |
| Mixed | split into the rows above; say which piece goes to which agent |

## 3. Check each result
A report is a claim. Read the diff and the test output before moving on, and send problems back to the agent that owns the code.

## 4. Gate and review (whenever code changed)
- `quality-gatekeeper` runs the `quality-gate` skill on the changed files until green.
- `code-reviewer` on the diff when it touches money, stock, treasury, tenancy, auth, or migrations, and for any change larger than a few lines.
- `security-auditor` as well for auth, tenancy, middleware, routes, uploads, or exports.
- Confirmed findings go back to the owning agent; then re-run the gate on what changed.

## 5. Docs
`docs-historian` writes `docs/history/YYYY-MM-DD/NN-slug.md` for feature-level work, audits, and non-obvious fixes. Skip for trivial changes.

## Rules for the whole run
- Stay inside the task. Legacy violations nearby are mentioned in the report, not refactored.
- Never commit, push, deploy, connect to production, or run a root-level live script. A task that needs one of those stops and asks, even when the wording seems to imply it.
- Final report to the user, in Arabic: what was done and by which agents, files changed, test and gate results (including any failure), open findings or follow-ups, and the suggested commit message when code changed.
