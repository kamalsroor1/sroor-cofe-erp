# 02 — Backend production-readiness audit (Sroor ERP, `feature/multi-tenant`)

Audited on 2026-11-09 against the working tree, which includes the uncommitted PurchaseItem/PurchaseService change.

## Executive summary
1. The engineering base is strong. 2,920 PHPUnit tests are green and a MySQL concurrency job runs in CI. Money writes use bcmath with half-up rounding. POS checkout is atomic and idempotent. Provisioning and the VPS release pipeline are mature.
2. The weakest flow is **returns**. A sales return is not linked to any invoice: `invoice_id` is dropped by validation. Any user with `returns.manage` can return any item at any quantity and any price, and the customer is credited the full amount. **Deleting a return does not reverse stock or balance.** I reproduced both.
3. **Per-branch stock breaks for any branch created after its items.** New stores get no `store_stocks` rows. Sales there skip the branch stock check, and the branch totals stop adding up to the item total (reproduced).
4. **Paying for the SaaS is not enforced anywhere.** Suspended, expired or read-only tenants keep full API access, because the status is checked only by the workspace resolver. Plan features and limits are never checked. Nothing moves tenants through their lifecycle on a schedule. There is no billing or payment integration (the billing tables exist but nothing writes to them). There is no VAT and no ETA e-invoicing.
5. POS hardening is needed. Business errors (for example insufficient stock) come back as **HTTP 500**. The server trusts the client's unit price, discount and invoice date. Invoice, return, purchase and shift numbers are generated without a lock, so concurrent sales can collide and fail with a 500. The legacy deploy script migrates only tenant `2m` and hides failures with `|| true`.

## Test-suite result
- `php artisan test` (sqlite in-memory): **2,920 tests, 2,919 passed, 1 skipped, 0 failed, 35,195 assertions, ~395 s.**
- Uncommitted change (`PurchaseItem.php`, `PurchaseService.php`, plus untracked migration `2026_10_11_120000_add_wac_before_to_purchase_items_table.php` and `tests/Feature/PurchaseCostSnapshotReversalTest.php`): `pint --test` passes and `phpstan analyse` on the changed files reports 0 errors. Its tests ran inside the full green suite.
- Larastan baseline: **143 entries / 192 errors** (`backend/phpstan-baseline.neon`, level 5). It has gone 698 → 246 → 192 across its last commits and has not grown.
- Proof run: I wrote a throwaway test in the scratchpad, outside the repo, and ran it against sqlite. It reproduced BE-01, BE-02, BE-04, BE-07 and BE-08. Output:
  ```
  ITEM current_stock=70.000 MAIN store qty=100.000 BRANCH row exists=no
  INSUFFICIENT STOCK status=500 ...
  PRICE 0.001 status=201 net_total=0.005 date=2020-01-01
  RETURN create status=201 (no invoice) stock after return=107.000 balance=-6993.000; after delete stock=107.000 balance=-6993.000
  ```

---

## P0 — launch blockers

### BE-01 Sales returns are not anchored to a sale (any item, quantity and price)
- **Severity:** P0 · **Effort:** M
- **Evidence:**
  - `backend/app/Http/Requests/StoreReturnRequest.php:17-29`: there are no rules for `invoice_id` or `purchase_id`, so `validated()` drops them. The `ReturnDocumentDTO` fields therefore stay `null` (`app/DTOs/Returns/ReturnDocumentDTO.php:31-32`).
  - `backend/app/Services/ReturnService.php:84-113`: the service takes `unit_price` from the client. It does not check that the item was on the invoice, that the quantity is no more than sold minus already returned, or the invoice-level discount.
  - `refund_amount` is validated but never used.
- **Impact:**
  - A cashier or storekeeper can create stock and customer credit out of nothing. Proof: 7 kg of a 20 EGP item returned at 999 credited the customer 6,993 EGP and added 7 kg of stock.
  - A full return of a discounted invoice refunds more than the customer paid.
  - Return reports cannot be traced back to sales.
- **Fix:**
  - Make `invoice_id` required for `sales_return` (or allow a missing invoice only with a separate permission) and `purchase_id` for purchase returns.
  - Validate each line against the invoice lines minus returns already made, while holding a lock on the invoice.
  - Price each line from the invoice line, net of its share of the invoice discount.
  - Record how the refund is paid (cash voucher vs account credit) through `refund_amount` / method.
  - Add tests for over-return, a foreign item, and a discounted invoice.

### BE-02 Deleting, restoring or force-deleting a return never reverses its stock or balance
- **Severity:** P0 · **Effort:** S
- **Evidence:**
  - `backend/app/Actions/Returns/DeleteReturnAction.php:13-18` is only `$returnDoc->delete()`.
  - `app/Actions/Trash/RestoreTrashRecordAction.php:28` and `ForceDeleteTrashRecordAction.php:28` do the same.
  - The test `tests/Feature/Api/ReturnsApiTest.php:191-216` asserts only the soft delete.
- **Impact:**
  - After a sales return is deleted, its stock stays in inventory. The proof showed 107 before the delete and 107 after.
  - `customers.current_balance` stays stale until some later recompute, then jumps.
  - A purchase-return delete leaves stock removed while the supplier balance is restored.
- **Fix:** Add a `CancelReturnAction`, or make delete do the reversal. In one transaction: lock the document, then the items in id order. Post reverse `StockMovement`s (`sales_return_cancel_out` / `purchase_return_cancel_in`). Block the delete when the stock has already been consumed. Recompute the customer or supplier balance. Restore from trash must re-apply all of this. Add reversal tests.

### BE-04 Branches created after their items have no `store_stocks` rows, so sales bypass the branch stock check
- **Severity:** P0 · **Effort:** S
- **Evidence:**
  - `backend/app/Actions/Stores/CreateStoreAction.php:19-41` never seeds `StoreStock`. Only item creation seeds rows, and only for stores that exist at that time (`app/Actions/Items/CreateItemAction.php:44-50`).
  - `backend/app/Services/StockService.php:76-93`: when `$storeStock` is null, the branch check and the branch decrement are both **skipped silently**. Only the tenant-wide `items.current_stock` is checked and decremented.
- **Impact:** Selling 30 units in a new branch passed, and set `items.current_stock` to 70. The main store still shows 100 and the branch has no row. Per-branch stock reports are wrong. Later the main store is refused sales it physically can make, because the item total is now lower than its shelf.
- **Fix:**
  - Seed `StoreStock` rows for all items inside `CreateStoreAction`.
  - In `deductStock`, treat a missing row as quantity 0 and throw the insufficient-stock error, unless a negative-stock setting is on.
  - Add a one-off command for live tenants that backfills the missing rows and reports any drift between `SUM(store_stocks)` and `items.current_stock`.

### BE-05 Subscription lifecycle is not enforced on API requests
- **Severity:** P0 · **Effort:** M
- **Evidence:**
  - `backend/app/Http/Middleware/ResolveApiTenancy.php:66-137` checks only `isPurged()` and `isProvisioned()`. It never checks status, trial expiry or `subscription_ends_at`.
  - `Tenant::isSuspended()` (`app/Models/Tenant.php:533-546`) is used only in `ResolveTenantWorkspaceAction.php:68`, the public resolver.
  - The comment there says read-only "writes get 423 from IDEN-3.5 (EnsureTenantActive)". That middleware does not exist; `TenantLifecycleException::readOnly()` is defined but never thrown.
  - `TenantLifecyclePolicy` (trial → read_only, active → past_due → read_only) is applied only by a super-admin action. There is no schedule for it in `routes/console.php`.
- **Impact:** A suspended, cancelled, expired-trial or past-due tenant keeps full read/write access. That covers any client that already has a token, or anyone who sends `X-Tenant` and logs in; the Android/Electron shells skip the resolver. The owner cannot cut off a customer who does not pay.
- **Fix:**
  - Add an `EnsureTenantActive` middleware after tenancy is initialized on all tenant routes:
    - `Blocked` → 403 `subscription.access_blocked`;
    - `read_only` → 423 on non-GET requests;
    - expired trial or subscription → treated as read_only.
  - Add a scheduled `tenants:apply-lifecycle` command, run hourly through `TransitionTenantStatusAction`, so tenants move status automatically.
  - Add tests per status covering a direct `X-Tenant` API call and login.

### BE-06 The legacy deploy path migrates one tenant, swallows failures and mutates data
- **Severity:** P0 (operational) · **Effort:** S to retire, M to unify
- **Evidence:** `deploy_root_baraa.py:123-129` (repo root), which CLAUDE.md calls the "sanctioned" deploy. On every deploy it runs:
  - `tenants:migrate --tenants=2m --force || true`: only one tenant is migrated, and any error is ignored;
  - a `tinker` call that deletes the tenant row `tenant_sroor`;
  - central seeders;
  - `tenant:populate-realistic-data 2m`.

  The proper path is `.github/workflows/release.yml` → `scripts/ops/deploy.sh`, which does a pre-migration backup, `tenants:migrate`, health check and automatic rollback.
- **Impact:** With more than one paying tenant, every tenant except `2m` is left on the old schema. The new code then fails at runtime on their missing columns. One failed migration is invisible. A deploy can delete or seed production data.
- **Fix:**
  - Launch only through `release.yml` / `deploy.sh` (VPS).
  - Retire `deploy_root_baraa.py` and `deploy.yml`, or rewrite them to run `tenants:migrate --force` for all tenants with no `|| true`, and with no tinker or populate steps.
  - Update CLAUDE.md so it no longer calls the old script sanctioned.
  - Have the `devops-engineer` agent own this.

---

## P1 — before the first customers

### BE-07 Business rule violations return HTTP 500 "Server Error"
- **Severity:** P1 · **Effort:** M
- **Evidence:**
  - There are 57 `throw new Exception/DomainException/RuntimeException(...)` with hardcoded Arabic in `app/Services` and `app/Actions`. Examples: `StockService.php:67,86` (insufficient stock), `InvoiceService.php:131,500,507`, `ShiftService.php:54`, `TreasuryService.php:131-148`, `PurchaseService.php:292,306`, `StockTransferService.php:49,90`.
  - `bootstrap/app.php:102-151` maps only authentication, authorization, throttling and validation exceptions.
  - The invoice, POS, return, purchase, shift and treasury controllers do not catch anything.
  - Proof: the insufficient-stock checkout returned **500**. The existing test only asserts `>= 400` (`PosCheckoutIntegrityApiTest.php:621`).
- **Impact:** With `APP_DEBUG=false` the cashier sees "Server Error" instead of "insufficient stock", "invoice already cancelled" or "shift already open". Every such event is also logged and alerted as a server fault.
- **Fix:**
  - Add a `DomainRuleException` (or `BusinessRuleException`) with a translation key and params, rendered as 409/422 in `bootstrap/app.php`.
  - Replace the generic throws with `__()` keys (ar + en).
  - Tighten the test to assert 409/422 and the message key.

### BE-08 POS and invoice endpoints trust the client's price, discount and date
- **Severity:** P1 · **Effort:** M
- **Evidence:**
  - `app/Http/Requests/StorePOSInvoiceRequest.php:72-90`: `items.*.unit_price` is just `numeric|min:0`; discounts have no maximum; `invoice_date` is a free `date`.
  - `InvoiceService::persistConfirmedInvoice` (`InvoiceService.php:134-191`) uses these values as sent.
  - `items.min_selling_price` is never enforced on the server (grep: used only in item CRUD and the bootstrap payload).
  - Proof: 5 kg at unit price 0.001, dated 2020-01-01, was accepted with **201**.
- **Impact:** Cashier fraud and mistakes: below-cost sales, 100% discounts, and backdated sales that move revenue into closed days and shifts and distort reports.
- **Fix:**
  - When the price differs from the price-tier price, or is below `min_selling_price`, require a permission (`pos.override_price`).
  - Add a maximum discount percentage per role/setting.
  - Pin `invoice_date` to the business date unless the user has `invoices.backdate`.
  - Add `decimal:0,3` and sane maximums on quantities and prices; scientific notation such as `1e3` passes `numeric` and then breaks bcmath.

### BE-09 Document number generation is not locked, so concurrent documents collide
- **Severity:** P1 · **Effort:** S–M
- **Evidence:**
  - `InvoiceService::generateUniqueNumber` (`InvoiceService.php:893-937`) does an unlocked `MAX` then `+1` inside the transaction. `invoices.invoice_number` is `UNIQUE`.
  - The same pattern appears in `ReturnService.php:225-252`, `PurchaseService.php:742`, `ShiftService.php:93-105`, and in `uniqid()`-based numbers for expenses, payments and adjustments.
  - The only thing serializing invoices is the customer row lock. Two cashiers in one branch selling to different named customers at the same moment get the same number.
  - `confirmInvoice` rethrows a unique violation that is not on `client_uuid` (`InvoiceService.php:59-74`) → 500.
- **Impact:** Checkouts fail at random under load, typically during a rush, and the error is a 500.
- **Fix:** Add a tenant `document_sequences` table (`store_id`, `type`, `date` → `last`). Increment it with `lockForUpdate()` inside the same transaction. Alternatively, retry once on a unique violation. Lexicographic ordering also breaks after 9,999 documents per day; use a numeric sequence column.

### BE-03 Cancelling an invoice ignores prior returns and leaves the payment in place
- **Severity:** P1 · **Effort:** M
- **Evidence:** `InvoiceService::cancelInvoice` (`InvoiceService.php:497-554`) restocks every full line quantity and sets `remaining_amount` to 0. It leaves the `PAY-INV-*` payments untouched.
  - `CustomerBalanceService::updateBalance` (`CustomerBalanceService.php:15-42`) still subtracts those payments, so a cancelled cash sale becomes customer credit.
  - `TreasuryService::getBalances` (`TreasuryService.php:36-39`) still counts the cash.
  - The shift calculation excludes it (`ShiftService.php:130`), so treasury and shift disagree.
  - The admin check is `hasRole('admin')` inside the service (`:499`) with a hardcoded Arabic message.
- **Impact:**
  - Cancelling after a partial return restocks the returned goods twice. This becomes reachable once BE-01 links returns to invoices; today returns are unlinked, so the double stock comes from cancel plus a free-form return.
  - The walk-in customer builds up negative balance.
  - The treasury shows cash that was refunded.
- **Fix:**
  - Block the cancel when returns exist, or cancel only the un-returned quantity.
  - On cancel, write a refund voucher or soft-delete the invoice payments, choosing explicitly between refund cash and keep as credit.
  - Move the admin check into the FormRequest or a policy.
  - Add tests for treasury, shift and balance after a cancel.

### BE-10 Shift expected cash treats every sales return as a cash refund
- **Severity:** P1 · **Effort:** S
- **Evidence:** `ShiftService.php:153-160` subtracts the `total_amount` of every `sales_return` in the window. Returns never create a cash voucher; they credit the customer balance (`ReturnService.php:118`).
- **Impact:** A return credited to a customer's account makes the drawer look short and triggers false discrepancy alerts. A walk-in return is counted twice: once as customer credit and once as drawer cash out.
- **Fix:** Model the refund method on the return (cash voucher with a store, or account credit). The shift should count only cash refund vouchers.

### BE-11 Treasury per-branch balances mix every branch's money
- **Severity:** P1 · **Effort:** S
- **Evidence:** `TreasuryService::getBalances` (`TreasuryService.php:36-57`) filters expenses and transfers by `storeId`, but **not** customer, supplier or expense `Payment` rows. `payments.store_id` exists (`2026_10_11_100000_...`) and there is a `forStore()` scope used by shifts.
  - `transfer()` checks balance sufficiency **outside** the transaction (`:144-150`), with no lock.
- **Impact:** A branch treasury shows inflows from all branches, so transfers between accounts are approved against the wrong balance. Two concurrent transfers can overdraw.
- **Fix:** Apply `Payment::forStore($storeId)` to every payment sum. Run the sufficiency check inside the transaction, serialized on a per-store treasury lock row.

### BE-12 Plan features and limits are never enforced
- **Severity:** P1 · **Effort:** M
- **Evidence:** `TenantEntitlementService` and `EntitlementFeatures` resolve features and limits. However, `FeatureUnavailableException` and `PlanLimitExceededException` are **never thrown**: grep finds no `PlanLimitExceededException::`, `Feature::active` or `hasFeature(` in `app/Http`, `app/Actions` or `app/Services`. `CreateStoreAction`, user creation and item creation have no limit checks.
- **Impact:** Every tenant gets every module and unlimited stores and users, whatever plan it pays for. Tiered pricing cannot be sold.
- **Fix:**
  - Add a `feature:<key>` route middleware backed by `TenantFeatureManagerInterface`, on each module route group.
  - Add `assertWithinLimit(LimitResource::Stores|Users|Items)` in the Create*Actions, under a lock.
  - Add a test per gated module and limit.

### BE-13 No SaaS billing or payment integration
- **Severity:** P1 (P0 if self-serve signup is planned) · **Effort:** L
- **Evidence:** `BillingInvoice`, `BillingPayment`, `BillingSequenceService`, `CreditBalanceService` and `FounderPricingService` exist, but nothing in `app/Actions`, `app/Http` or `routes` creates billing invoices or payments. There are no billing routes and no gateway (no Paymob, Fawry, Kashier or Stripe in composer or config).
- **Impact:** All collection, renewal and dunning is manual. Super-admin can change status, but there is no invoice to the customer, no payment record and no automatic `subscription_ends_at` extension.
- **Fix:**
  - Phase 1 (manual, M): a super-admin "record payment" action → `BillingInvoice` + `BillingPayment` → extend `subscription_ends_at` → audit. Combined with BE-05 this gives a working manual billing loop.
  - Phase 2 (L): a Paymob or Fawry checkout plus a signed webhook, running the same action idempotently.

### BE-14 No VAT and no Egyptian ETA e-invoice / e-receipt support
- **Severity:** P1 (market-dependent) · **Effort:** L
- **Evidence:** The tenant `invoices` and `invoice_items` tables have no tax columns (`database/migrations/tenant/2026_08_08_160005_create_invoices_and_items_tables.php`). There is no tax engine and no ETA client, signing, UUID or QR. Only legal-info print settings exist.
- **Impact:** These shops cannot serve VAT-registered customers or B2B sales, and cannot comply when the ETA e-receipt mandate reaches their segment.
- **Fix:**
  - Add tax fields per line and per document (rate, amount, tax-inclusive flag) with bcmath. The schema change is M.
  - Then add an ETA e-receipt and e-invoice integration as a queued, tenant-aware job after commit: per-tenant credentials and certificate, submission status and retry. This is L.
  - Have `product-researcher` confirm which phase of the mandate applies to coffee retail.

---

## P2 — soon

### BE-15 The walk-in customer is a global hot row, and its balance is re-summed in full on every sale
- **Severity:** P2 · **Effort:** M
- **Evidence:** Every POS sale locks the customer row (`InvoiceService.php:80`). Walk-in sales all share one customer (`StorePOSInvoiceRequest::normalizeWalkInCustomerId`). `CustomerBalanceService::updateBalance` then runs `SUM` over **all** of that customer's invoices, payments and returns (`CustomerBalanceService.php:20-31`).
- **Impact:** All branches' walk-in sales are serialized on one row, and each sale's cost grows linearly with history. At about 300 sales/day, each checkout scans more than 100k rows within a year.
- **Fix:** Update the balance incrementally with `bcadd` under the existing lock, and keep a nightly reconcile job. Or skip balance upkeep for the walk-in customer.

### BE-16 Deadlock risk: items are locked in request order and deadlocks are not retried
- **Severity:** P2 · **Effort:** S
- **Evidence:** `InvoiceService.php:121-123`, `ReturnService.php:84-85,160-161` and `cancelInvoice:513-514` lock items in input order. Purchases sort correctly (`PurchaseService::lockItemsInIdOrder`). `DB::transaction()` uses the default single attempt.
- **Impact:** Two baskets `[A, B]` and `[B, A]` at the same moment cause a MySQL 1213 deadlock, which becomes a 500 and a lost sale.
- **Fix:** Pre-lock every item in ascending id order at the start of the transaction, reusing `lockItemsInIdOrder`. Use `DB::transaction($fn, 3)` on checkout.

### BE-17 Missing composite indexes and non-sargable date filters on large tables
- **Severity:** P2 · **Effort:** S
- **Evidence:**
  - `invoices` has only single-column indexes. `store_id` was added on its own (`2026_08_11_180002`).
  - `InvoiceController::index` uses `whereDate('invoice_date', …)` (`:79-85`), which defeats the index, and runs three full-range aggregate queries (`SUM` of `net_total`, `SUM` of `paid_amount`, a count) as floats on every page (`:97-100`).
  - `payments` has no index on `created_at`, which the shift window uses (`ShiftService.php:125`), and none on `payment_method`.
  - `stock_movements` has separate `source_type` and `source_id` indexes, and no `(item_id, id)` index for the new snapshot query (`PurchaseService.php:681-685`).
- **Fix:** Add a new tenant migration with `hasIndex` guards for:
  - `invoices(store_id, invoice_date, status)` and `invoices(customer_id, status)`;
  - `payments(store_id, payment_method, created_at)` and `payments(customer_id)`;
  - `stock_movements(source_type, source_id)` and `stock_movements(item_id, movement_type, id)`;
  - `invoice_items(item_id, invoice_id)`.

  Replace `whereDate` with `where('invoice_date', '>=', …)`. Give list summaries a default date window.

### BE-18 Unbounded report and ledger queries
- **Severity:** P2 · **Effort:** M
- **Evidence:**
  - `GetProfitLossReportAction.php:26` loads every invoice in the range with `->get()`.
  - `CustomerBalanceService::getCustomerLedger` (`:52-95`) and `SupplierBalanceService.php:62-100` load the full history.
  - `GetItemMovementsAction.php:50` loads all movements to compute totals.
- **Impact:** Memory and time-outs. The walk-in customer statement and yearly P&L will run out of memory on real data.
- **Fix:** Do the aggregates in SQL (`SUM`/`GROUP BY`), paginate ledgers with an opening-balance row, and chunk exports.

### BE-19 Shift open and close are not concurrency-safe, and Telegram is called inside the transaction
- **Severity:** P2 · **Effort:** S
- **Evidence:**
  - `ShiftService::openShift` (`:45-83`) checks for an existing open shift with no transaction and no lock, so a double tap opens two shifts with the same `SHIFT-…-NNN`.
  - `closeShift` (`:194-247`) neither locks nor re-checks the status. `CloseShiftAction.php:22` checks it outside the lock.
  - It calls `TelegramService::sendShiftDiscrepancyNotification` **synchronously inside the DB transaction** (`:238-244`); the HTTP timeout is 10 s per call (`TelegramService.php:83`).
- **Fix:** Lock the store row (or a shift sequence row) while opening. Re-read the shift with `lockForUpdate` and check it is `open` before closing. Dispatch the Telegram notification as a job with `afterCommit()`.

### BE-20 Queue and scheduler reliability
- **Severity:** P2 · **Effort:** M
- **Evidence:**
  - `routes/console.php:13-16` starts `queue:work --stop-when-empty --max-time=55` every minute from cron. This is the shared-host pattern: jobs wait up to 60 s, and long jobs depend on `withoutOverlapping`.
  - The Telegram daily summary, low-stock and overdue-shift jobs are **disabled** by default because they are not tenant-aware (`console.php:26-45`), so these advertised features do nothing.
  - `ProvisionTenantJob` has a 900 s timeout, which needs `retry_after` > 900 on its connection.
- **Fix:** In production use Horizon or Supervisor on the VPS, which is already prepared in the release pipeline. Make the Telegram jobs loop over `Tenant::cursor()` with `$tenant->run()` and per-tenant settings. Add a health check for failed jobs.

### BE-21 Purchase returns are not linked to purchases, and supplier balance logic is duplicated
- **Severity:** P2 · **Effort:** S–M
- **Evidence:**
  - `ReturnService::createPurchaseReturn` (`:135-223`) has the same missing purchase link as BE-01 and no quantity check against the purchase.
  - It recomputes the supplier balance inline (`:205-211`) instead of calling `SupplierBalanceService::updateBalance`.
  - `PurchaseService::cancelPurchase` soft-deletes the supplier payments (`:345`), so the treasury acts as if the supplier refunded the cash. **Question:** is that the intended business rule?
- **Fix:** Apply the BE-01 linking to purchase returns, reuse `SupplierBalanceService`, and decide explicitly between refund voucher and supplier credit.

### BE-22 Hardcoded Arabic text in PHP
- **Severity:** P2 (P1 for strings users see, such as exceptions) · **Effort:** L, done incrementally
- **Evidence:** 675 non-comment lines with Arabic across 81 files under `app/`. Top files:

  | File | Lines |
  |---|---|
  | `ReportPrintController.php` | 175 |
  | `TelegramService.php` | 92 |
  | `InvoiceService.php` | 27 |
  | `Models/ActivityLog.php` | 26 |
  | `TreasuryService.php` | 19 |
  | `PurchaseService.php` | 17 |
  | `Enums/PaymentMethod.php` | 14 |

  21 of these lines are `throw new …('<Arabic>')`. There are also hardcoded unit names and an Arabic `DomainException` in `InvoiceService.php:128-131`.
- **Fix:** Start with exceptions (fold into BE-07), then enum labels, then ActivityLog descriptions (store a key plus params), then print and Telegram text.

### BE-23 Blender invoices use 4-decimal quantities that drift from the 3-decimal stock
- **Severity:** P2 · **Effort:** S
- **Evidence:** `CreateBlenderInvoiceAction.php:27` computes `bcdiv(grams, '1000', 4)`. 12.5 g becomes `0.0125`. The invoice line is stored as DECIMAL(12,3), and MySQL rounds it to 0.013, while `bcsub(…, 3)` deducts 0.012 from stock.
- **Fix:** `Decimal::round(bcdiv(grams, '1000', 12))` before building the line.

### BE-24 POS idempotency is optional
- **Severity:** P2 · **Effort:** S
- **Evidence:** `StorePOSInvoiceRequest.php:73` has `client_uuid` as `nullable`. A retry after a network timeout creates a duplicate sale unless the client sends the key or the `Idempotency-Key` header.
- **Fix:** Make `client_uuid` required on `/pos/checkout`, keeping it optional on `/invoices` for the back office, and confirm that the POS, Android and Electron clients always send it.

---

## P3 — nice to have

### BE-25 Dead, unsafe code in `InvoiceService::updateInvoice` and `deleteInvoice`
- **Severity:** P3 · **Effort:** S
- **Evidence:** `InvoiceService.php:559-891` has no callers (no route or Action). The code:
  - hard-deletes `StockMovement`s, destroying the audit trail;
  - never deletes treasury-paid shipping `Expense`s, so they are duplicated on every edit;
  - edits cancelled invoices, deducting stock while the status stays cancelled;
  - does not reconcile split payments with `paid_amount`.
- **Fix:** Delete both methods now, so nobody wires them up later.

### BE-26 Thin-controller violations that carry risk
- **Severity:** P3 · **Effort:** M
- **Evidence:**
  - `InvoiceController::index` (`:37-122`) builds filters as an if-ladder, checks permissions inline, and returns float summaries.
  - Controllers reach 355 lines (`ItemController`) and 309 lines (`SuperAdminApiController`).
  - Several Actions are thin wrappers around god-services (`InvoiceService` is 938 lines, `PurchaseService` 770).
- **Fix:** Move filters to Pipeline (`app/Filters/Invoices/*`) and permissions to FormRequest or policy. Split `InvoiceService` into Confirm, Cancel and Numbering actions as each area is touched.

### BE-27 Larastan baseline debt
- **Severity:** P3 · **Effort:** M
- **Evidence:** 143 entries / 192 errors in `phpstan-baseline.neon`, level 5. The trend is healthy (698 → 192).
- **Fix:** Keep shrinking it, money services first.

### BE-28 Money returned as JSON floats in reports
- **Severity:** P3 · **Effort:** M
- **Evidence:** 179 `(float)` / `round()` occurrences in Services and Actions, mostly reports and dashboards (`GetDashboardOverviewAction` 21, `GetDailyJournalAction` 17, `GetInventoryValuationReportAction` 15, `CalculateBlendCostAction` 14). They are for display only, but the API emits floats.
- **Fix:** Emit money as strings from resources and let the frontend format it.

### BE-29 Rule and docs drift
- **Severity:** P3 · **Effort:** S
- **Evidence:** `.claude/rules/multi-tenancy.md` says `Plan`, `PlanFeature` and `Subscription` do not pin the central connection; all of them now use `UsesCentralConnection` (verified). CLAUDE.md and the security rule call `deploy_root_baraa.py` the sanctioned deploy (see BE-06).
- **Fix:** Update the rules.

### BE-30 Integer-quantity check uses floats and hardcoded unit names
- **Severity:** P3 · **Effort:** S
- **Evidence:** `InvoiceService.php:128-132` does `fmod((float) $qty, 1.0)` against an inline Arabic/English unit list.
- **Fix:** Add an `allows_fraction` flag on the item or unit (it relates to `is_weighted`) and check with `bccomp(bcmod($qty, '1', 3), '0', 3)`.

---

## Uncommitted change review (PurchaseItem / PurchaseService, BUG-03 follow-up)
- **Verdict: acceptable to commit together with its untracked migration and test.**
- What I checked:
  - The new columns are `decimal(12,3)` and nullable, have `decimal:3` casts, and are in `$fillable`.
  - The migration is guarded by `hasTable` and `hasColumn` and has a working `down()`.
  - The snapshot is taken before each line is applied, in both create and restore.
  - `canRestoreCostSnapshot` takes the exact path only when three things hold: this purchase's own `purchase_in` or `purchase_restore_in` is the item's latest non-transfer movement; replaying the lines from the snapshot reproduces the current average; and `cost_price` is still owned by this purchase. Otherwise it falls back to the formula path.
  - Everything runs inside the existing locked transaction, after `lockItemsInIdOrder`.
  - `pint --test` passes, Larastan reports 0 errors, and the suite is green.
- Nits:
  - Commit `database/migrations/tenant/2026_10_11_120000_add_wac_before_to_purchase_items_table.php` and `tests/Feature/PurchaseCostSnapshotReversalTest.php` in the same commit.
  - The latest-movement query should get the `stock_movements(item_id, movement_type, id)` index from BE-17.
  - Legacy rows (snapshot NULL) keep the formula path, as intended.

## Coverage map (controllers vs tests)
- Every `app/Http/Controllers/Api/*Controller` has at least one Feature test under `tests/Feature/Api`, `tests/Feature/Branding` or `tests/Feature/Central`.
- The gaps are in behaviour, not in missing files:
  - return delete or trash with stock and balance reversal (BE-02);
  - over-return and returns linked to invoices (BE-01);
  - store created after its items (BE-04);
  - lifecycle enforcement on direct `X-Tenant` calls (BE-05);
  - a 409/422 contract for business errors (BE-07);
  - treasury per-store isolation (BE-11);
  - concurrent invoice numbering (BE-09), which belongs in the existing MySQL `mysql-only` group.
