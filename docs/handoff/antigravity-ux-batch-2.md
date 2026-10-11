# Task for Antigravity: UX batch 2 (frontend only)

**Start only after PR #4 review fixes are pushed** (all `[claude-code]` items answered). Same worktree `D:\projects\sroor-antigravity`, same branch `feature/ux-fixes-antigravity`, same PR #4 (new commits are added to it).

Follow everything in `docs/handoff/antigravity-operating-guide.md` and the rules / forbidden-files list in `docs/handoff/antigravity-ux-fixes.md` (still valid). Lead = Claude Opus 5.5 (medium), sub-agents = Gemini 3.8 (high). Sub-agents get `.claude/agents/frontend-vue.md` as role (Lane C uses `.claude/agents/qa-tester.md`).

## Items

### Lane A — tables on tablet/mobile (review G1, finishes item 4 of batch 1)
Convert these hand-written list tables to the shared `Components/Common/DataTable.vue` (card layout under `lg`, `overflow-x-auto` + edge shadows, built-in loading skeleton / empty / error states), keeping every column, action, slot, sort and pagination behaviour exactly as today:
`Customers/CustomersTable.vue`, `Customers/CustomerStatementTable.vue`, `Suppliers/SuppliersTable.vue`, `Suppliers/SupplierStatementTable.vue`, `Expenses/ExpensesTable.vue`, `Invoices/InvoicesTable.vue`, `Purchases/PurchasesTable.vue`, `Returns/ReturnsTable.vue`, `StockTransfers/StockTransfersTable.vue`, `StoreStocks/StoreStocksTable.vue`, `ItemMovements/ItemMovementsTable.vue`, `SmartReorder/SmartReorderTable.vue`, `Trash/TrashTable.vue`, `Users/UsersTable.vue`, `Dashboard/DashboardRecentInvoices.vue`.
If `DataTable` needs a new optional prop/slot, add it backward-compatibly (the 3 report tabs already use it). Do not convert document/detail tables (invoice lines, purchase/return/transfer details, A4/thermal print).
**Owns:** the files listed + `Components/Common/DataTable.vue`.

### Lane B — error state + mobile KPI grids on the remaining pages (review G15, G7)
1. Wire the shared `ErrorState` (from batch 1) into every remaining list/detail page whose composable has a fetch: customers, invoices (list only), purchases, returns, stock transfers, store stocks, item movements, smart reorder, trash, users, activity logs, reports, profile. Rules: show only when the request failed (never while loading), keep stale rows visible with an inline error bar when rows already exist, Retry reloads the **current** page/filters.
2. Apply the opt-in 2-column mobile KPI grid class (the one requested in the PR #4 review, no global CSS, no `!important`) to the metrics grids of those pages.
**Owns:** the matching `views/<Feature>/*View.vue`, `Components/<Feature>/*MetricsGrid*.vue` / KPI components, and `Composables/use<Feature>.js` for those features only (NOT `useFormatters`, `useInvoiceShow`, `useUnits`, `useAppUpdate`, `useSuperAdmin*`).
**Must not touch:** Lane A's table files.

### Lane C — Playwright page audits for everything touched in batch 1 + 2
Specs in `e2e/flows/<page>-ux-audit.spec.js` (reuse `e2e/utils/*`, auth state from `e2e/auth/`), projects desktop/tablet/mobile, for: dashboard, customers, suppliers, expenses, purchases, returns, stock transfers, store stocks, daily journal, reports, categories, roles, stores, trash, users.
Each spec asserts: page loads with **zero console errors/Vue warnings**, skeleton → content, RTL (`dir=rtl`), dark + light toggle, no horizontal page overflow at 390 / 820 / 1366, main list visible (table on desktop, cards on mobile), touch targets ≥ 44px for row actions on mobile, error state + Retry when the list API is mocked to 500 (`page.route`).
Selectors: roles / labels / `data-testid` (add `data-testid` attributes in Lane A/B components if missing — coordinate through the lead, never by translated text). No `waitForTimeout`. Local only (`http://127.0.0.1:8000`), never a remote URL.
**Owns:** `e2e/flows/*-ux-audit.spec.js`, `e2e/utils/*` additions.
Runs **after** Lanes A and B finish (it needs their testids).

## Verify (lead, before commits)
Everything from `antigravity-ux-fixes.md` (eslint, prettier --check, `npm run test:js`, vite build to the outside folder, `LangKeyParityTest|SpaTranslationKeysExistTest`), plus `npm run e2e:flow -- ux-audit` if a local dev server + e2e tenant can be started; if not, say so in the report (do not fake it).

## Commits / PR
Lane by lane, explicit paths, no AI names:
`refactor(ui): move list tables to the shared data table`, `fix(ui): error states and mobile kpi grids on remaining pages`, `test(e2e): ux audit specs for list pages`.
Report: `docs/handoff/antigravity-ux-batch-2-report.md` (per item status, files, keys, real results, not-verified list). Push the same branch, comment `[antigravity] batch 2 ready for review` on PR #4. Never merge.

## Next (do NOT start yet — wait for a `[claude-code]` go comment)
- SETG-5 full settings screen UI (needs W2 batch 2 backend: currencies, units, business-day cutoff).
- POS redesign from `docs/04-ux-ui/pos-mockups/` (after W2 batch 4 releases the POS files).
