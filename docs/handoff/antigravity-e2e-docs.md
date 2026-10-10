# Task for Antigravity: business-flow E2E tests + page/module docs refresh

Worktree: `D:\projects\sroor-antigravity-e2e`, branch `feature/e2e-docs-antigravity` (from `feature/multi-tenant` at `9d486424`). Never touch `D:\projects\sroor`, `D:\projects\sroor-antigravity` or `D:\projects\sroor-antigravity-settings`.
Follow `docs/handoff/antigravity-operating-guide.md`. Lead = Claude Opus 5.5 (medium); sub-agents = Gemini 3.8 (high).
**No application code changes at all** in this task: only `e2e/**` (Lane E) and `docs/pages/**`, `docs/modules/**` (Lane D). If a test finds a real app bug: do NOT fix it — write it in the report (steps, expected, actual, file:line if known) and mark the test `test.fail()` with a comment linking the report entry. Never weaken an assertion to make it pass.

## Lane E — business-flow E2E (role: `.claude/agents/qa-tester.md`, rules: `.claude/rules/testing.md`)
Read existing `e2e/` (config `playwright.config.js` at repo root, `e2e/auth/*`, `e2e/utils/*`, the `*-flow.spec.js` files) and reuse their helpers. Do **not** modify `e2e/auth/login.setup.js` or other shared files except adding new helpers in `e2e/utils/flows/*`.
New specs `e2e/flows/<name>-business-flow.spec.js` (desktop project; mobile where noted). Each test creates its own data through the API or UI and verifies **server state** through the API afterwards (stock, balances, treasury, totals as exact decimal strings — half-up rounding at 3 dp):
1. **POS cash sale**: open shift → sell 2 items (one weighted `0.250` kg) → pay cash with change → invoice exists, stock decreased exactly, shift cash total increased, receipt printable.
2. **POS credit sale** to a customer → customer balance increased; then **payment** from customer statement → balance back.
3. **Split payment** (cash + card) → totals per method in the shift.
4. **Shift close** with counted cash (match and shortage) → shortage recorded; requires `daily_journal.close_shift`.
5. **Purchase invoice** from supplier → stock increased, supplier balance increased, weighted average cost updated.
6. **Sales return** (full line) → refund equals the paid line total exactly; stock back.
7. **Stock transfer** between two stores the user can access → source decreased, destination increased; a user without access to the source store gets 403.
8. **Expense** → treasury decreased, appears in daily journal.
9. **Store isolation**: cashier of store A cannot see store B data in POS (403 / no data).
10. (mobile project) POS cash sale on 390px works end-to-end.
Rules: selectors by role / label / `data-testid` only (add nothing to app code — if a testid is missing, use role/label and list the missing testids in the report); no `waitForTimeout`; local URL only; `page.route` only to force failures. Run every spec for real against a local tenant (`php artisan serve` + `npm run dev` or a built app) and paste the **real** `npx playwright test ... --reporter=list` output in the report.

## Lane D — docs refresh (role: `.claude/agents/docs-historian.md`, rules: `.claude/rules/docs-and-history.md`)
Update every file in `docs/pages/*.md` and `docs/modules/*.md` to match the **current code** on this branch (Arabic prose, English identifiers): route, permission names (they were renamed in W2 — read `database/seeders/PermissionsSeeder.php`, `routes/api.php`, `routes/tenant.php`, `routes/central.php`, `resources/js/router/index.js`), API endpoints + request/response shape, components/composables used, store scoping (`X-Store-Id` now verified; 403 `store_access_denied`), business-day cutoff + half-up rounding where money/dates appear, known gaps. Super-admin pages: describe the new central console (separate central login + mandatory 2FA, admin host). Mark anything you could not verify as "غير متحقق". Do not invent features. Add `docs/pages/README.md` index if missing.

## Verify + deliver
- Lane E: real Playwright output; `npx eslint e2e` if configured, `npx prettier --check` on new e2e files.
- Lane D: links resolve, no secrets/phone numbers/customer workspace codes in docs.
- Commits: `test(e2e): business flow specs for pos, shifts, purchases, returns, transfers and expenses`, `docs(pages): refresh page and module docs to the current code`. Explicit paths, no AI names.
- Report `docs/handoff/antigravity-e2e-docs-report.md` (real results, bugs found, missing testids, docs marked "غير متحقق").
- Push `feature/e2e-docs-antigravity`, open a **draft** PR to `feature/multi-tenant`, comment `[antigravity] ready for review`. Never merge.
