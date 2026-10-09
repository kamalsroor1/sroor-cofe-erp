# Task for Antigravity: UX fixes (frontend only)

You are working in a **separate git worktree**: `D:\projects\sroor-antigravity`, branch `feature/ux-fixes-antigravity`.
Another agent team is building Phase 1 W2 in `D:\projects\sroor` (branch `feature/multi-tenant`) at the same time. **Never touch `D:\projects\sroor`.**

## Read first
1. `AGENTS.md` (project rules: RTL-first, dark+light, CSS variables, no hardcoded text, thin views, skeleton loaders, 44px touch targets).
2. `docs/04-ux-ui/ux-review-2026-10-09/README.md` (the UX review; screenshots in `screens/`).
3. `.claude/rules/frontend-vue.md` and `.claude/rules/localization.md`.

Reply/code comments: English. User-facing text: Arabic (primary) + English, through `backend/lang/{ar,en}/*.php` only.

## Setup (inside the worktree)
```
cd backend
composer install
npm ci
```
Do not copy or create `.env` with real values. For a local preview, copy `.env.example` to `.env`, run `php artisan key:generate`, use sqlite. Never connect to any server.

## Scope: fix these items from the UX review

| # | Item (review section) | Notes |
|---|---|---|
| 1 | Dashboard welcome shows literal `app:` (§4.2) | `DashboardWelcomeBanner.vue`: pass `{ app: companyName }` |
| 2 | G9 status badges wrap/cut | `StatusBadge` `whitespace-nowrap` + shorter labels |
| 3 | G7 KPI cards on mobile | `MetricCard` compact mode, 2-column grid under `sm` (daily-journal is the reference) |
| 4 | G1 tables on tablet | `DataTable`: wrap in `overflow-x-auto` with edge shadow, or card layout under `lg` (do NOT touch `SpaLayout.vue`) |
| 5 | G8 small touch targets (tables, roles page select-all links, header-less icon buttons) | min 44px on `pointer: coarse`. Not POS. |
| 6 | G11 emoji instead of lucide icons | roles page, tenants list badges are out of scope (super-admin); fix the rest except POS |
| 7 | G12 English/technical terms in Arabic UI | reports (Revenue/COGS/Gross Profit/WAC), purchase-create |
| 8 | G13 semantic colors | negative values red / positive green via `useMoney` in daily journal |
| 9 | G14 random category icons | neutral default icon (`Folder`/`Tag`) + explicit lucide picker in the category form |
| 10 | G15 error state | shared `ErrorState` component (icon + message + Retry `BaseButton`), wired into the pages' composables when `error` is set |
| 11 | G16 Vue console warnings (extraneous non-props attrs) | suppliers, expenses, stores modals: declare the props or stop passing them |
| 12 | G6 native `type="date"` inputs | replace with `BaseDatePicker` in `ExpensesFilterBar`, `DailyJournalView`, `CreateStockTransferHeaderCard` |
| 13 | G10 shift CTA wording duplicated | daily-journal page only: one term ("وردية") and one primary button on the page |

## Forbidden files (another team owns them right now)
- All PHP outside `backend/lang/` (no controllers, models, migrations, routes, config, tests in `backend/tests/` except `backend/tests/js/`).
- `resources/js/views/POS/**`, `resources/js/Components/POS/**`, `Layouts/SpaLayout.vue`, `Layouts/SuperAdminLayout.vue`
- `resources/js/views/SuperAdmin/**`, `Components/SuperAdmin/**`, `Composables/useSuperAdmin*.js`
- `resources/js/router/**`, `stores/**`, `Services/api.js`
- `Composables/useFormatters.js`, `useInvoiceShow.js`, `useUnits.js`, `useAppUpdate.js`, `helpers/decimal.js`, `helpers/formatters.js`
- `views/Invoices/InvoicePrintView.vue`, `views/Items/ItemsView.vue`, `ItemFormModal.vue`, `DesktopPrinterSettingsModal.vue`, `Auth/*` views
- `resources/js/helpers/defaultTranslations.{js,json}` (generated), `public/build/**`
- Lang files: `common.php`, `validation.php`, `pos.php`, `settings.php`, `inventory.php`, `super.php`, `subscription.php`, `billing.php`, `plans.php`, `central_auth.php`, `central_audit.php`, `console.php`, `branding.php`, `permissions.php`, `roles.php`, `app_update.php`
- `.github/**`, `deploy*`, root `*.py`, `backups/`, `.env*`

Allowed lang files (existing ones only): `dashboard.php`, `customers.php`, `contacts.php`, `expenses.php`, `purchases.php`, `reports.php`, `treasury.php`, `returns.php`, `invoices.php`, `activity.php`, `trash.php`, `profile.php`, `users.php`, `nav.php` (append new keys at the END of the file, ar and en in parity). If a key you need belongs in `common.php`, put it in the report instead.

## Rules
- Small commits on `feature/ux-fixes-antigravity`, Conventional Commits in English (`fix(ui): …`). **No AI/model names or attribution in commits.** Stage explicit paths (never `git add .` / `-A`). **Do not push. Do not merge.**
- Do not run `npm run build` or `php artisan lang:export` (generated files are rebuilt by the coordinator).

## Verify before each commit (from `backend/`)
```
npx eslint <changed files>
npx prettier --check <changed files>
npm run test:js
npx vite build --outDir ../../sroor-ag-build --emptyOutDir
php artisan test --filter='LangKeyParityTest|SpaTranslationKeysExistTest'
```

## Report
When done (or blocked), write `docs/handoff/antigravity-ux-fixes-report.md` in this worktree:
- item-by-item status (done / partial / skipped + why)
- files changed per item, commits (hash + message)
- lang keys added, keys needed in `common.php`
- commands run with real results; anything you could not verify (say so)
- before/after screenshots if you captured any (put them outside the repo)
