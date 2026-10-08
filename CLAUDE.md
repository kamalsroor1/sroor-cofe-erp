# Sroor Coffee ERP — AI Operating Manual

Multi-tenant ERP + POS (invoicing, inventory, treasury, shifts, reports) for coffee/retail stores.
Owner communicates in **Egyptian Arabic** — reply in Arabic; keep code, identifiers, commits, and these AI files in English.

> This file is the entry point. Detailed rules live in `.claude/rules/` (auto-loaded by path), specialist agents in `.claude/agents/`.
> `AGENTS.md` is the cross-tool mirror of the same rules (Codex / Cursor / Gemini). If the two ever disagree, `.claude/rules/` wins — then fix `AGENTS.md`.

## Stack (verified against the code, not the old docs)

| Layer | Tech |
|---|---|
| Backend | PHP 8.3+, Laravel 13, Sanctum (Bearer tokens), spatie/laravel-permission, **stancl/tenancy v3** (DB-per-tenant) |
| Frontend | **Pure Vue 3 SPA** — `<script setup>`, Pinia, Vue Router, Tailwind CSS v4, Vite 8, axios, SweetAlert2, lucide icons |
| Mobile | **Capacitor 8** Android shell around the same SPA (`backend/capacitor.config.json`, `backend/android/`) |
| Desktop | **Electron** wrapper (`desktop/`) |
| Tests | PHPUnit 12 (sqlite `:memory:`), Playwright E2E (`e2e/`, config at repo root) |
| Monitoring | Telescope, Pulse, Telegram notifications |

**Removed — never reintroduce:** Livewire, Inertia, Blade-driven pages, Alpine, NativePHP. Docs under `docs/history/` still mention them; that is history, not guidance.

## Repo map

```
backend/                     ← the Laravel app. Almost all work happens here.
  app/Actions/<Domain>/      single-purpose classes, one public execute()
  app/DTOs/<Domain>/         final readonly typed DTOs with fromArray()/toArray()
  app/Http/Controllers/Api/  thin JSON controllers (routes: /api/v1/*)
  app/Http/Requests/         Form Requests (all validation)
  app/Http/Resources/        API Resources (response shaping)
  app/Http/Middleware/       ResolveApiTenancy, StoreScope, StoreAccess, ApiTokenAuth
  app/Filters/<Domain>/      Pipeline query filters
  app/Services/              shared domain engines (Stock, Invoice, Treasury, Profit…)
  app/Policies/ Observers/ Scopes/ Enums/ Jobs/ Console/Commands/
  database/migrations/       CENTRAL db (tenants, domains, plans, app_versions…)
  database/migrations/tenant TENANT db (items, invoices, stock, treasury…)
  lang/{ar,en}/*.php         the ONLY translation source
  resources/js/              views/ Components/ Composables/ stores/ Services/ Layouts/ router/ helpers/
  routes/                    api.php, tenant.php, web.php
  tests/Feature/Api/         one *ApiTest per controller
e2e/, playwright.config.js   Playwright suites (desktop / tablet / mobile projects)
desktop/                     Electron shell
docs/                        architecture, per-page/module docs, docs/history/YYYY-MM-DD/
*.py at repo root            ad-hoc deploy / live-server scripts — see security rule below
```

## Commands (run from `backend/`)

```bash
composer dev                          # serve + queue + pail + vite
php artisan test                      # full PHPUnit suite
php artisan test --filter=CustomersApiTest
npm run build                         # runs lang:export first, then vite build
php artisan lang:export               # regenerate frontend translations from lang/*.php
./vendor/bin/pint --dirty             # format changed PHP files
composer analyse                      # Larastan level 5 vs phpstan-baseline.neon (must stay green, never grow the baseline)
npm run lint                          # ESLint (Vue/JS); also in desktop/. lint:dirty = changed files only
npm run format:check                  # Prettier, read-only; also in desktop/
npm run format:dirty                  # before committing: Prettier ONLY on files you changed — never format the whole tree
npm run e2e:desktop                   # Playwright (also e2e:mobile, e2e:tablet, e2e:flow)
```

## The 10 golden rules

1. **Money & quantities are `DECIMAL(12,3)` + `bcmath` strings.** Never `float`/`double`, never `+ - * /` on money in PHP.
2. **Every stock/balance/treasury mutation runs inside `DB::transaction()`**, and reads the row it is about to change with **`lockForUpdate()`**.
3. **Tenant isolation is sacred.** Tenant data lives in the tenant DB; central models (`Tenant`, `Domain`, `Plan`, `AppVersion`, `CentralUser`…) must pin the central connection via `getConnectionName()`. Never leak across tenants.
4. **Thin controllers.** Request → Form Request → DTO → Action `execute()` → Resource. No `$request->validate()`, no business logic, no query-building `if` ladders in controllers (use `app/Filters` + `Pipeline`).
5. **One Action = one operation**, in `app/Actions/<Domain>/<Verb><Noun>Action.php`. `app/Services/` is for shared engines reused by several Actions — don't grow god-services.
6. **Zero hardcoded user-facing text** (Arabic or English), backend or frontend. Keys go in `backend/lang/ar/*.php` **and** `backend/lang/en/*.php`; the frontend files are generated — never hand-edit `defaultTranslations.*`.
7. **Thin orchestrator views.** `views/<Feature>/<Feature>View.vue` ≈ 50–80 lines: fetch, loading/error state, grid, wire props/events. Markup lives in `Components/<Feature>/`, logic in `Composables/`.
8. **UI must be RTL-first, dark+light, touch-friendly, themed via CSS variables** (`var(--color-primary)`…), with **skeleton shimmer loaders** — never a blank screen or a lone spinner.
9. **Authorization on every endpoint** (permission / policy) plus store scoping (`X-Store-Id`). Every new endpoint ships with a Feature test covering 200 / 422 / 401 / 403 / tenant isolation.
10. **Never touch production on your own.** No deploy scripts, no `*_live*`/`check_*`/`fix_*` root scripts, no `git push`, no destructive DB commands unless the user explicitly asks in that message.

## Legacy code warning

Not all existing code obeys these rules (e.g. `PosView.vue` is ~840 lines, some controllers build filters inline, some Actions return hardcoded Arabic strings). **Do not copy those patterns and do not mass-refactor them unasked.** New and touched code follows the rules; when a legacy violation sits in your way, fix it only within the scope of the task and mention it.

## How to work on a task

1. **Understand** — read the relevant code first; check `docs/pages/`, `docs/modules/` if the feature has them.
2. **Delegate** to the matching agent (table below) for anything non-trivial. Independent pieces → launch agents in parallel.
3. **Implement** in small verifiable steps; run the narrowest relevant test after each step.
4. **Verify** — `php artisan test --filter=…` for backend, `npm run build` for frontend. Report real results, including failures.
5. **Review** — run `code-reviewer` on the diff for any change touching money, stock, tenancy, or auth; add `security-auditor` for auth/tenancy/upload/route changes.
6. **Document** — for feature-level work, `docs-historian` writes `docs/history/YYYY-MM-DD/NN-slug.md`. Skip for trivial fixes.
7. **Commit only when asked.** Conventional Commits (`feat(pos): …`, `fix(api): …`), English, scoped.

## Agent roster (`.claude/agents/`)

| Agent | Use it for | Writes code? |
|---|---|---|
| `backend-architect` | migrations, models, Actions, DTOs, Form Requests, Resources, filters, policies, tenancy, services | yes |
| `frontend-vue` | views, components, composables, Pinia stores, router, Tailwind, RTL/dark/touch, skeletons, Capacitor/Electron bridges | yes |
| `qa-tester` | PHPUnit Feature/Unit tests, concurrency & rollback tests, Playwright E2E | tests only |
| `debugger` | reproduce → root-cause → minimal fix for any bug, failing test, or 500 | yes (minimal) |
| `i18n-guardian` | hunt hardcoded strings, add ar/en keys, keep key parity, run `lang:export` | yes (lang + call sites) |
| `code-reviewer` | review a diff against every rule here; finds bugs, doesn't fix | no (read-only) |
| `security-auditor` | tenant isolation, authz gaps, injection, secrets, mass assignment, uploads | no (read-only) |
| `docs-historian` | history logs, page/module docs, master architecture doc | docs only |
| `quality-gatekeeper` | Pint, Larastan (level 5 + baseline), ESLint, Prettier: gate runs, lint/type fixes, baseline shrinking, the one-time format pass | yes (no behaviour changes) |
| `devops-engineer` | GitHub Actions CI, VPS deploy pipeline, provisioning runbooks, backups (Google Drive), queue/cron, env standards | yes (never touches production unasked) |
| `product-researcher` | web research on competitors: features, settings, workflows, pricing, UX; compares with our code and writes decision-ready recommendations | docs only |

**Project skills (`.claude/skills/`)**: `quality-gate` (pre-commit checks), `larastan-fixing`, `ci-pipeline`, `production-ops`. Agents preload them via the `skills:` frontmatter.

**Typical pipelines**
- New feature: `backend-architect` → `frontend-vue` (+ `i18n-guardian`) → `qa-tester` → `quality-gatekeeper` (gate) → `code-reviewer` → `docs-historian`
- Before every commit: `quality-gatekeeper` runs the `quality-gate` skill on the changed files
- CI failure / infra / deploy / backups: `devops-engineer` (+ `security-auditor` for anything touching secrets or access)
- Bug: `debugger` → `qa-tester` (regression test) → `code-reviewer`
- Controller audit (`docs/CONTROLLER_AUDIT_PROMPT.md`): `qa-tester` (tests first) → `backend-architect` (refactor) → `code-reviewer`
- Page audit: `frontend-vue` → `i18n-guardian` → `qa-tester` (E2E) → `docs-historian`
