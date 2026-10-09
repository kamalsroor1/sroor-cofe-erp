# Running the UX task with multiple Antigravity agents

The 13 items in `antigravity-ux-fixes.md` are split into 3 lanes that run **in parallel**, then 1 lane that runs **after** them.
Lanes never edit the same file. If a lane needs a file owned by another lane, it writes the needed change in its report instead of editing.

**Parallel agents do NOT commit** (they share one git index). They only edit and verify. The final lane (Lane 4) commits everything, lane by lane, with explicit paths.

Every agent first reads: `docs/handoff/antigravity-ux-fixes.md` (forbidden files, rules, verify commands) and `AGENTS.md`.

---

## Lane 1 — shared base components (items 2, 3, 4, 5)
- StatusBadge: `whitespace-nowrap` + shorter labels (G9)
- MetricCard: compact mode, 2-column grid under `sm` (G7)
- DataTable: `overflow-x-auto` + edge shadow, or card layout under `lg` (G1)
- Touch targets ≥44px on `pointer: coarse` for table action icons and icon buttons in base components (G8)

**Owns:** `resources/js/Components/Base/**` (or wherever StatusBadge / MetricCard / DataTable / BaseButton live), `resources/css/**` if needed.
**Lang:** none (put needed keys in the report).
**Must not touch:** any `views/**`, any composable, any page-level component.

## Lane 2 — dashboard, reports, daily journal, dates (items 1, 7, 8, 12, 13)
- Dashboard welcome `:app` bug (`DashboardWelcomeBanner.vue`)
- English/technical terms in reports and purchase-create (G12)
- Negative red / positive green in daily journal via `useMoney` (G13)
- Replace native `type="date"` with `BaseDatePicker` in `ExpensesFilterBar`, `DailyJournalView`, `CreateStockTransferHeaderCard` (G6)
- One shift term and one primary CTA on the daily-journal page (G10)

**Owns:** `views/Dashboard*`, `Components/Dashboard/**`, `views/Reports/**`, `Components/Reports/**`, `views/DailyJournal/**`, `Components/DailyJournal/**`, `Components/Expenses/ExpensesFilterBar.vue`, `Components/StockTransfers/CreateStockTransferHeaderCard.vue`, `views/Purchases/**` and `Components/Purchases/**` (terms only).
**Lang:** `dashboard.php`, `reports.php`, `treasury.php`, `purchases.php` (append at end, ar+en).

## Lane 3 — categories, Vue warnings, emoji (items 6, 9, 11)
- Neutral default category icon + explicit lucide picker in the category form (G14)
- Fix "extraneous non-props attributes" warnings in suppliers, expenses, stores modals (G16)
- Replace emoji with lucide icons outside POS and super-admin (G11)

**Owns:** `views/Categories/**` / `Components/Categories/**` (or the category form wherever it lives, NOT `ItemFormModal.vue`), `Components/DynamicIcon*`, `views/Suppliers/**`, `Components/Suppliers/**`, `Components/Expenses/**` except `ExpensesFilterBar.vue`, `views/Stores/**`, `Components/Stores/**`, `views/Roles/**`, `Components/Roles/**`.
**Lang:** `contacts.php`, `expenses.php`, `users.php` (append at end, ar+en).

---

## Lane 4 — AFTER lanes 1–3 finish: error state + commits (item 10)
1. Shared `ErrorState` component (icon + message + Retry `BaseButton`) in base components; wire it into page composables/views that show zeros or "empty" on a failed request (G15). Skip forbidden files.
2. Run all verify commands from `antigravity-ux-fixes.md` on everything changed.
3. Commit lane by lane with explicit paths (`git add -- <paths>`), Conventional Commits in English, no AI names:
   `fix(ui): base components …`, `fix(ui): dashboard, reports and daily journal …`, `fix(ui): categories, modal warnings and icons …`, `feat(ui): shared error state …`
4. Write `docs/handoff/antigravity-ux-fixes-report.md` (combine the lane reports).
**Do not push or merge.**
