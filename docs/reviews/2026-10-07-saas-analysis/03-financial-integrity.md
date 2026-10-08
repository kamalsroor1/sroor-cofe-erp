# Financial & inventory integrity — Score 4/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

The low-level integrity primitives are solid. Money and quantity columns are DECIMAL(12,3) with decimal:3 casts. Arithmetic uses bcmath. Writes to invoices, purchases, stock and payments run inside DB::transaction with lockForUpdate on the Item, StoreStock, Customer and Supplier rows. Balances are recomputed from source documents, not incremented. The targeted test suites pass (about 67 tests on sqlite :memory:).

The business flows built on those primitives are not safe to sell yet. Nine high-severity findings survived adversarial verification, and none was refuted. They fall into five groups:

1. **Live POS contract.** The POS posts to /invoices. Validation silently drops `payments` (split tender) and `expenses` (fees), so the recorded net total and payment method differ from what the customer paid.
2. **No idempotency.** There is no idempotency key, and the F9 / Ctrl+Enter shortcut skips the isSubmitting guard, so a double-press or a retry after timeout creates duplicate confirmed invoices.
3. **Branch (store) isolation.** Per-branch stock is not enforced when the branch has no StoreStock row. store_id from the request body overrides the header and is never checked against the user's assigned stores. Invoice print routes are unauthenticated and expose customer PII by sequential id.
4. **Reversal paths.** Cancelling an invoice does not reverse its payments or treasury-paid expenses and ignores returns already posted. Returns are not linked to any invoice and have unbounded quantity and price. Deleting a return is a bare soft delete with no stock or balance reversal, and cashiers can do it.
5. **Cash reconciliation.** The shift close counts invoice cash twice (paid_amount plus the PAY-INV payment). It counts every sales return as both customer credit and a cash refund. Payments have no store_id, so other branches' cash leaks into each shift.

Net effect: a multi-branch tenant's stock, customer ledger, treasury and shift-close figures will diverge during normal daily use. Several fixes for weighted-average cost and restore logic exist on origin/main (d08edc92, PR #3) but not on feature/multi-tenant, which adds branch-drift risk.

The score reflects strong foundations but broken end-to-end flows. This needs to be fixed before onboarding paying multi-branch tenants.

## Top risks
- Unauthenticated invoice print routes leak every invoice: D:\projects\sroor\backend\routes\tenant.php:60-74 (/invoices/{id}/print, /print/thermal, /print/a4) sit outside the auth group and run Invoice::findOrFail($id). They render customer name, phone, address and current_balance (print-a4.blade.php:207-218, print-thermal.blade.php:174-178). backend/routes/web.php:56-68 has similar unauthenticated print routes and /daily-journal/print. The ids are sequential, so anyone who knows a tenant's domain can enumerate them.
- Shift close is wrong on every shift: D:\projects\sroor\backend\app\Services\ShiftService.php:81-105 sums invoice paid_amount AND the PAY-INV Payment rows created by InvoiceService.php:229-251, so cash sales are counted twice. Lines 126-138 also subtract every sales return's total_amount as a cash refund, while CustomerBalanceService already credits the customer. The Payment queries are not store-filtered. Every close records a false difference and sends a Telegram discrepancy alert.
- The live POS silently loses money data: PosView.vue:720-724 posts `expenses` and `payments` to /invoices, but StoreSalesInvoiceRequest.php:40-56 has no rules for them and CreateInvoiceDTO has no payments field. Fees are left out of net_total and paid_amount, and a split tender is booked as one Payment under one method (InvoiceService.php:241-251), which distorts the per-method treasury totals.
- Duplicate sales: no client_uuid or idempotency key exists anywhere. PosView.vue:814 (F9 / Ctrl+Enter) bypasses isSubmitting, and submitInvoice (line 700) never returns early when a submit is already running. An axios 30s timeout with no retry (Services/api.js:12) plus a server commit after the timeout leads to a second sale. Each duplicate deducts stock and moves balance and treasury again.
- Branch isolation is broken inside a tenant: StorePOSInvoiceRequest.php:22 and CreateInvoiceDTO::fromArray let the body store_id win. ApiTokenAuth.php:83-86 trusts X-Store-Id. The store.access middleware is registered (bootstrap/app.php:28) but used on no route. Invoice index, show and cancel (InvoiceController:40, CancelSalesInvoiceAction:22) are unscoped. A cashier in one branch can sell another branch's stock and read or cancel its invoices.
- Per-store stock is not enforced: StockService.php:59-73 skips both the store check and the store decrement when no StoreStock row exists. Any branch created after its items has no rows (CreateStoreAction does not seed them), and GetPOSBootstrapDataAction:64 shows master stock as that branch's stock. Per-branch inventory then stops adding up to the master stock.
- Invoice cancel is incomplete: InvoiceService::cancelInvoice (278-335) re-adds the full quantity and flips the status. It leaves the Payment rows, so the customer (often the shared walk-in) gets a credit while TreasuryService still counts the inflow. It leaves the treasury-paid Expense as an outflow. It ignores prior partial returns, so stock and the customer credit are double-reversed.
- Returns are an insider-fraud path: StoreReturnRequest.php:18-29 drops invoice_id, so returns are never linked to an invoice. ReturnService.php:63-89 accepts any item, quantity and client unit_price and restocks at the current cost. refund_amount is ignored. DeleteReturnAction.php:16-17 soft-deletes with no reversal, and restoring from Trash re-applies only the balance. The cashier and storekeeper roles hold returns.manage (PermissionsSeeder.php:93,107).

## Quick wins
- Move the /invoices/{id}/print* routes in routes/tenant.php:60-74 inside the auth group with can:invoices.view and a store check. Remove or protect the duplicate unauthenticated print and daily-journal routes in routes/web.php:56-68. Alternatively use URL::temporarySignedRoute for the popup and Electron print flows.
- In PosView.vue, add `if (isSubmitting.value) return;` at the top of submitInvoice, and check isSubmitting and !e.repeat in the F9 / Ctrl+Enter handler at line 814.
- ShiftService::calculateShiftTotals: count cash from one source only. Either exclude Payments with invoice_id not null from paymentsCollected, or sum Payments only. Filter by store. Add a shift test that runs one real cash sale through confirmInvoice.
- StockService::deductStock: treat a missing StoreStock row as quantity 0 and throw insufficient-stock. Have CreateStoreAction seed zero StoreStock rows for existing items. Fix the COALESCE fallback in GetPOSBootstrapDataAction:64 so a branch with no row shows 0, not master stock.
- Remove returns.manage from the cashier and storekeeper roles for delete. Gate ReturnController::destroy behind a manager-level permission until a proper cancel-with-reversal exists.
- Apply the store.access middleware to the /api/v1 invoice, POS, return and shift routes, and reject a body store_id that differs from the validated header store. Remove the frontend `|| 1` fallback in PosView.vue:708.
- Add the missing `payments.*` and `expenses` / `additional_expenses.*` rules to StoreSalesInvoiceRequest and carry them through CreateInvoiceDTO, or switch PosView to the existing /pos/checkout endpoint, which already accepts both. Add a feature test that posts the exact PosView payload.

## Strategic items
- Idempotent document creation: add a client_uuid column with a unique (store_id, client_uuid) index on invoices, and later on payments and returns, through a tenant migration. Look up any existing row by that key inside the transaction and return it. Generate the UUID per order in the POS store, so that it survives retries and a future offline queue for Capacitor and Electron.
- Redesign returns as proper source-linked documents: make invoice_id required for sales returns and purchase_id for purchase returns. Add invoice_item_id and cost_price to return_items. Lock the invoice lines and enforce returned quantity no greater than sold minus already returned with bcmath. Take price and cost from the original line. Add a refund_method / refund_amount that creates an explicit refund Payment or treasury movement. Replace delete with a cancel that reverses stock and balances, and block naive restore and force-delete from Trash.
- Complete the cancel semantics for invoices: decide the business policy (refund voucher vs. customer credit, including for the walk-in customer). Reverse or void the linked Payments and treasury-paid Expenses. Reverse only the unreturned quantity, or block the cancel when returns exist. Add tests for cancelling a cash invoice and cancelling after a partial return.
- Make branch a first-class dimension of money: add store_id to payments (and expenses where it is missing), add a store global scope or policy on Invoice, ReturnDocument, Payment and CashShift, and resolve the store only from a middleware-validated context. Add isolation tests that expect 403 for the wrong store.
- Single checkout contract: retire either /invoices or /pos/checkout for POS use, delete the dead posService.js and PosView.legacy paths, and pin the contract with contract tests that post the real SPA payloads.
- Reconcile with main: merge or rebase d08edc92 (weighted-average cost fixes, discount allocation, no physical deletion of movements, refusing naive restore) and PR #3 into feature/multi-tenant before further fixes, to avoid divergent integrity logic.
- Data repair tooling: write a read-only reconciliation command. It should compare SUM(store_stocks) with items.current_stock, list negative store rows, recompute customer and supplier balances against the stored values, and recompute shift expected cash. Run it against a backup of the production tenants to size the drift already present before shipping the fixes.
- Add concurrency and end-to-end integrity tests on MySQL, not only sqlite: two parallel sales, two parallel returns against one invoice, and the full sale-to-cancel and sale-to-return-to-shift-close flows, with assertions on stock, balance, treasury and shift totals.

## Strengths
- All money, quantity and stock columns in database/migrations/tenant are DECIMAL(12,3) with 'decimal:3' model casts. I found no float or double columns. Arithmetic uses bcmath at scale 3, and scale 6 for allocation ratios. Fractional quantities such as 0.750 kg are first-class.
- confirmInvoice, cancelInvoice, the purchase create, cancel and restore paths, supplier and customer payments, stock transfers and shift close each run in a single DB::transaction covering the document, items, stock movements, payments and the balance recompute. The rollback tests pass.
- lockForUpdate is used consistently on the Item, StoreStock, Customer, Supplier, Invoice and Purchase rows before mutation. A double cancel is rejected under lock. The unique(store_id, item_id) index plus the item lock serialize StoreStock firstOrCreate.
- Customer and supplier balances are recomputed from source documents under a row lock rather than incremented, so they are self-healing and safe to re-run.
- Every stock change writes a StockMovement with stock_before and stock_after. Invoice lines snapshot cost_price and total_cost for profit reporting. The weighted-average cost formula guards division by zero and negative stock.
- Approved documents are soft-deleted or cancelled by status (cancellation_in reverse movements). Audit logs are written inside the transaction. invoice_number, purchase_number and payment_number have unique indexes, checked with withTrashed lookups.
- Stock transfers lock the source and destination, block same-store transfers, and block a cancel that would drive the destination negative. Purchase cancel pre-checks stock so already-sold goods cannot go negative.
- FormRequests enforce positive amounts (min:0.01) on payments, collections and expenses. Tenant data is physically isolated by DB-per-tenant (stancl/tenancy), so the store-isolation issues stay within a tenant.

## Findings

### 1. [HIGH] No idempotency on sale submission, and the F9 / Ctrl+Enter shortcut skips the isSubmitting guard, so a double-press creates duplicate invoices
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:814`
- **Effort:** M
- **Evidence:** There is no client_uuid, idempotency key or request_id anywhere in app/, database/ or resources/js (grep returned nothing). submitInvoice (line 700) sets isSubmitting.value = true at line 705 but never returns early when it is already true. The button in POSCheckoutSummary.vue:203 is disabled while isSubmitting is true. The keyboard handler at line 814 (`F9` or `Ctrl+Enter`) only checks `!showSuccessModal.value && cart.value.length > 0`. The cart is cleared only after the POST resolves. useNativeBridge.js has no offline queue, only an isOnline flag. The axios client (Services/api.js:12) has a 30s timeout and no retry. On the backend, InvoiceService::confirmInvoice (line 26) has no dedupe either.
- **Impact:** A cashier presses F9 twice quickly, or the key auto-repeats. Two POST /api/v1/invoices requests carry the same cart, so two confirmed invoices are created and stock is deducted twice. On slow mobile or Capacitor networks, the commit can succeed after the 30s axios timeout. The UI then shows 'checkout failed' and keeps the cart, the cashier re-submits, and the sale is duplicated in stock, customer balance and treasury.
- **Recommendation:** Generate a client UUID per order in the POS store and send it as `client_uuid` (or an Idempotency-Key header). Add a tenant migration with a unique (store_id, client_uuid) column on invoices. Inside the transaction, look up an existing invoice by that key and return it instead of creating a new one. In submitInvoice, add `if (isSubmitting.value) return;` and make the F9 path check isSubmitting too.
- **Verification:** I confirmed this from the code. In PosView.vue:700-705, submitInvoice only returns early when the cart is empty. It sets isSubmitting=true but never checks it first. The keydown handler at PosView.vue:814-818 (F9 or Ctrl+Enter) only checks !showSuccessModal && cart.length>0, and it does not check e.repeat. While the first POST is in flight, the cart is still full and the success modal has not opened, because clearActiveOrder and showSuccessModal=true run only after the await at line 724. So a second press, or a held key that auto-repeats, sends one extra POST /invoices per keydown. The only guard is the disabled state on the buttons (POSCheckoutSummary.vue:203, POSCheckoutPanel.vue:156/167), and that does not cover the keyboard path. I grepped app/, database/, routes/, resources/js, config/ and bootstrap/ and found no idempotency key, client_uuid or request_id. I also found no Cache::lock. InvoiceController::store (lines 141-159) goes straight to the action with no dedupe. InvoiceService::confirmInvoice locks the customer and item rows with lockForUpdate, but that only makes the two transactions run one after the other. It does not stop the second one, so both commit as separate confirmed invoices with separate numbers. The 30s axios timeout with no retry is at Services/api.js:12. That makes the second scenario plausible: the server commits after the client has already given up, the cart is kept, and the cashier submits again. I am keeping high severity. Duplicates do show up as two invoice numbers and can be reversed with the cancel flow, but nothing warns anyone, and the extra sale changes stock, the customer balance and the treasury.

### 2. [HIGH] The live POS sends `payments` (split tender) and `expenses`, but POST /invoices validation silently drops both, so the recorded total and payment method can differ from what the customer paid
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreSalesInvoiceRequest.php:55`
- **Effort:** M
- **Evidence:** PosView.vue:724 posts to `/invoices` (not /pos/checkout). The payload includes `expenses: additionalExpenses.value` (line 720) and `payments: multiPayments.value` (line 721). StoreSalesInvoiceRequest::rules() only declares `additional_expenses` and has no `payments` or `expenses` keys, so `$request->validated()` strips them. CreateInvoiceDTO::fromArray/toArray do not carry `payments` at all. The frontend cartNetTotal (PosView.vue:470) adds customerExpensesTotal to the shown total. On the backend, confirmInvoice line 191-192 sets paid = server-computed netTotal without those expenses, and lines 241-251 create a single Payment using `payment_method`.
- **Impact:** (a) A delivery fee added in the POS is shown to the customer and collected in cash, but the invoice net_total and paid_amount leave it out. The drawer then holds more cash than the books show, and customer-account fees are never charged. (b) A split payment such as 100 cash + 200 Visa is booked as one 300 payment under one method. TreasuryService (which sums Payment by payment_method) then misstates the per-method balances, so cash and card reconciliations fail every day.
- **Recommendation:** Align the contract. Either have the POS call /pos/checkout, or add `payments.*` and `additional_expenses.*` (with an `expenses` alias) rules to StoreSalesInvoiceRequest and carry them through CreateInvoiceDTO. On the server, validate that sum(payments) equals paid_amount and is not more than net_total. Add a feature test that posts the exact PosView payload.
- **Verification:** Confirmed in the code. Nothing downstream puts the fields back.

1. In `backend/resources/js/views/POS/PosView.vue`, `submitInvoice` (lines 707-724) posts to `/invoices`. The payload carries `expenses: additionalExpenses.value` (line 720) and `payments: multiPayments` (line 721). Note the key is `expenses`, not `additional_expenses`.
2. `backend/routes/api.php:105` sends that request to `InvoiceController::store`. At `app/Http/Controllers/Api/InvoiceController.php:148`, store calls `CreateInvoiceDTO::fromArray($request->validated(), ...)`.
3. `app/Http/Requests/StoreSalesInvoiceRequest.php` rules (lines 40-56) declare only `additional_expenses`. They have no `payments` or `expenses` key, so `validated()` removes both fields the POS sends.
4. `app/DTOs/Invoices/CreateInvoiceDTO.php` has no `payments` property, so the field could not get through even if it were validated.
5. `InvoiceService::confirmInvoice` does support both features, but they are never reached from this path:
   - Line 130 reads `additional_expenses`, which is always empty here.
   - Line 185 computes `netTotal` as subtotal minus discount plus customer expenses. Without the expenses, the server net leaves out the fee the POS adds into `cartNetTotal` (PosView.vue:470).
   - Line 192: for a cash sale, `paid` equals the server `netTotal`.
   - Line 227 starts the split-payment branch on `$data['payments']`. Because that key is always missing, it is dead code on this path.
   - Lines 241-251 then create one Payment with `$data['payment_method']`.

`handleMultiPaymentConfirm` (PosView.vue:598-610) only sets `paidAmount`/`cashReceived` and `paymentType`. It never changes `paymentMethod`. So a 100 cash + 200 Visa split is booked as one Payment under whatever single method is selected.

The only other checkout path is `POSInvoiceDTO` (`/pos/checkout`), which accepts both `expenses` and `payments`. The live POS does not use it.

Both claimed impacts are real:
- **(a) Fee missing from the books:** a fee added at the POS is shown and collected, but it is missing from `net_total`/`paid_amount` and no `invoice_additional_expenses` row is created.
- **(b) Wrong payment method:** a split tender is recorded under one method, which distorts the per-method treasury totals.

This happens silently on ordinary POS use and affects money and reconciliation, so high is justified.

### 3. [HIGH] Per-store stock is not enforced when the branch has no StoreStock row, so a branch can sell stock that sits in another branch
- **Location:** `D:\projects\sroor\backend\app\Services\StockService.php:64`
- **Effort:** S
- **Evidence:** deductStock (line 47) checks only the master `Item.current_stock`. At the store level (lines 59-73) it loads StoreStock with lockForUpdate()->first(), then runs `if ($storeStock && bccomp(...) < 0) throw` and `if ($storeStock) { ...bcsub... }`. When no row exists, the store check and the store decrement are both skipped. StoreStock rows are only created by addStock's firstOrCreate (line 121).
- **Impact:** Branch A received 10 kg of an item and branch B never received any, so B has no StoreStock row. A cashier in B sells 10 kg. The master stock drops to 0, but A's StoreStock still shows 10 kg and A can try to sell stock that no longer exists. The per-branch inventory no longer adds up to the master stock, so branch stock reports, transfers and valuations are wrong.
- **Recommendation:** In deductStock, treat a missing StoreStock row as quantity 0 and throw an insufficient-stock domain exception unless a tenant setting explicitly allows negative stock. Add a test that sells from a store without a StoreStock row and asserts a rollback.
- **Verification:** The finding holds. In backend/app/Services/StockService.php, deductStock checks only the master Item.current_stock (line 47). The store-level check (line 64, `if ($storeStock && bccomp(...) < 0)`) and the store decrement (lines 70-73, `if ($storeStock)`) both run only when a StoreStock row exists. When the row is missing, the sale goes through against master stock alone, and the StockMovement is still saved with that store_id (line 84).

I looked for a guard elsewhere and found none that closes the gap:
(1) hasAvailableStock(), which does return false when the row is missing, is never called anywhere in app/. Its only occurrence is its own definition.
(2) InvoiceService (lines 61 and 100), ReturnService:148 and PurchaseService:286 call deductStock with no store-level pre-check of their own.
(3) CreateItemAction (lines 42-48) creates StoreStock rows, but only for stores that exist when the item is created.
(4) CreateStoreAction creates no StoreStock rows for existing items. So any branch added after its items were created has no rows at all, which is exactly the claimed scenario and a normal event in a multi-branch SaaS.
(5) The POS makes it worse. GetPOSBootstrapDataAction:64 shows `COALESCE(store_stocks.quantity, items.current_stock)`, so a branch with no row is shown the tenant-wide master stock as its own available stock.

Result: branch B can sell stock that physically sits in branch A. The sum of StoreStock rows then no longer matches Item.current_stock, and branch stock, transfers and valuation reports drift.

Severity stays high. The gap needs a missing row, but a branch created after items exist always produces one. This breaks the 'stock is per store' rule in .claude/rules/money-stock-integrity.md.

### 4. [HIGH] store_id comes from the request body, takes priority over X-Store-Id, and is never checked against the user's store access
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StorePOSInvoiceRequest.php:22`
- **Effort:** M
- **Evidence:** StorePOSInvoiceRequest::prepareForValidation resolves `$this->input('store_id') ?? $this->header('X-Store-Id') ?? ...`, with the body first, and validates only `exists:stores,id`. In InvoiceController::store (line 143) the header comes first, but CreateInvoiceDTO::fromArray uses `isset($data['store_id']) ? (int)$data['store_id'] : $storeId`, so a validated body store_id overrides the header. ApiTokenAuth:83-86 copies the X-Store-Id header into the session without any access check. The StoreAccess middleware exists but no API route uses it (grep of routes/ for 'store.access' found nothing). The frontend sends `store_id: activeStore.value?.id || 1` (PosView.vue:708). InvoiceController::index (line 40) and show(), and CancelSalesInvoiceAction:22 (`Invoice::findOrFail`), apply no store scoping either.
- **Impact:** A cashier assigned only to branch B can post a sale with store_id=A and deduct A's stock, or list, view and cancel any branch's invoices by id or with store_id=all. Branch isolation inside a tenant, a selling point for multi-branch customers, is not enforced. A payload missing store_id silently falls back to store 1.
- **Recommendation:** Resolve the store only from a validated context: a middleware that checks the X-Store-Id header against the user's assigned stores (or an admin or cross-store permission) and binds it into the request. Ignore or reject a body store_id that does not match. Scope show, cancel and index by that store. Add isolation tests (wrong store gives 403).
- **Verification:** I confirmed this from the code. There is no branch (store) access check anywhere on the sale, list, view or cancel paths.

1. **POS checkout picks the store from the body first.** `StorePOSInvoiceRequest.php:22-26` resolves the store as body `store_id`, then the `X-Store-Id` header, then the session, then `getCurrentStore()`, then `Store::first()`. The only rule is `exists:stores,id` (line 66). `PosController::checkout` (line 52-55) passes `validated()` into `POSInvoiceDTO` (`storeId: (int)$data['store_id']`). `ProcessPOSInvoiceAction` hands that straight to `InvoiceService::confirmInvoice`, which uses `$data['store_id']` with no user-to-store check.

2. **The invoices endpoint also lets the body win.** `InvoiceController::store` (line 143) puts the header first. But `StoreSalesInvoiceRequest` validates `store_id` as `nullable|exists`, and `CreateInvoiceDTO::fromArray` line 28 (`isset($data['store_id']) ? ... : $storeId`) means a body value overrides the header.

3. **The header is trusted without a check.** `ApiTokenAuth.php:83-86` writes `X-Store-Id` into the session as `current_store_id`. `User::getCurrentStore()` then trusts it, checking only that the store is active.

4. **The access check exists but is never used.**
   - The `StoreAccess` middleware is registered as `store.access` in `bootstrap/app.php:28`.
   - A grep of `routes/` finds it on no route. The `/api/v1` group (`api.php:19,33`) only has `ResolveApiTenancy` and `ApiTokenAuth`.
   - There is no store global scope on `Invoice`.
   - The store-switch endpoint (`StoreController` around line 224-237) does check `store_user` / `default_store_id`. So per-branch restriction is clearly intended, but sending the header or body directly skips it.

5. **List, view and cancel are not scoped either.**
   - `InvoiceController::index` line 40-58 filters only when the client sends a store; `all`, empty or missing returns every branch.
   - `show` calls `GetInvoiceDetailsAction`, which does `findOrFail($id)` with no store check.
   - `CancelSalesInvoiceAction:22` does `Invoice::findOrFail`, and `CancelInvoiceRequest` checks only the `invoices.cancel` permission.

6. **The frontend falls back to store 1.** `PosView.vue:708` sends `store_id: activeStore.value?.id || 1`.

**Why high and not critical:** this is intra-tenant only. DB-per-tenant isolation still holds, and the caller must be an authenticated staff member with `pos.access` or `invoices.*`. Even so, a cashier assigned to one branch can deduct stock from another branch and can read or cancel other branches' invoices. That breaks the project's store-isolation rule and the integrity of per-branch inventory and treasury.

**Minor overstatement:** the "silently falls back to store 1" part comes from the frontend `|| 1`. On the server the fallback is session, then current or default store, then `Store::first()`.

### 5. [HIGH] Unauthenticated invoice print routes expose every invoice, with customer name, phone, address and balance, by sequential id
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** S
- **Evidence:** `/invoices/{id}/print`, `/invoices/{id}/print/thermal` and `/invoices/{id}/print/a4` sit only in the ['web', InitializeTenancyByDomain, PreventAccessFromCentralDomains] group, with no 'auth' middleware. They run `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)`. print-a4.blade.php:207-218 renders the customer name, phone and address. print-thermal.blade.php:174-178 renders the customer's current_balance.
- **Impact:** Anyone who knows a tenant's domain can enumerate /invoices/1/print, /invoices/2/print and so on. That exposes the tenant's whole sales history, prices and customer PII. This is a data leak to the public internet for every tenant.
- **Recommendation:** Put these routes behind auth plus the invoices.view or pos.access permission and store scoping. Alternatively, serve print data through the authenticated API, or use signed, short-lived URLs (URL::temporarySignedRoute) for the popup and Electron print flows.
- **Verification:** I confirmed this in the code. In backend/routes/tenant.php:60-74, the routes /invoices/{id}/print, /print/thermal and /print/a4 sit in the group ['web', InitializeTenancyByDomain, PreventAccessFromCentralDomains]. They are outside the Route::middleware('auth') group that starts at line 77. Each one runs Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) and returns the view. No auth, policy or permission check applies.

I looked for guards elsewhere and found none:
- The 'web' group in bootstrap/app.php:19 only appends StoreScope. StoreScope (app/Http/Middleware/StoreScope.php) does nothing unless Auth::check() is true, and it never denies a request.
- The Invoice model has no global scope and no booted() hook, so there is no store or user filtering.
- TenancyServiceProvider::mapRoutes loads tenant.php unconditionally.
- The ids are ordinary auto-increment ids. invoice_number is fillable but is not the lookup key.

The views do leak data:
- print-a4.blade.php:207-218 renders the customer's name, phone and address.
- print-thermal.blade.php:95 renders the customer's name.
- print-thermal.blade.php:174-178 renders customer->current_balance. This is controlled by the setting thermal_show_customer_balance, which defaults to true.
- Both views render line items and prices.

backend/routes/web.php:56-65 also defines /invoices/{id}/print/thermal and /print/a4 with no auth and no tenancy middleware. It also has an unauthenticated /daily-journal/print route starting at line 68. The exposure is therefore wider than the finding says, though those web.php routes resolve against whatever DB connection is the default.

An attacker only needs the tenant's domain or subdomain, which is easy to guess, plus a counter. High severity stands.

### 6. [HIGH] Cancel does not reverse payments or treasury-paid invoice expenses, and does not account for returns already posted, so stock and balances are double-reversed
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:294`
- **Effort:** M
- **Evidence:** cancelInvoice (lines 278-335) re-adds the full `$itemLine->quantity` for every line and sets status to cancelled. It does not touch Payment rows linked to the invoice, Expense rows created for treasury-paid additional expenses (lines 168-178), or ReturnDocument rows with this invoice_id. CustomerBalanceService::updateBalance computes confirmed invoices minus all payments minus all sales returns. ReturnService::createSalesReturn (line 43) never checks the invoice status or the quantity already returned.
- **Impact:** (1) Cancelling a 500 cash sale leaves the 500 Payment in place. The customer, often the shared walk-in 'عميل نقدي', ends with a -500 credit, and TreasuryService still counts 500 as inflow even if the cash was refunded. (2) A 5 kg invoice is partly returned (2 kg back into stock, return of 2 kg deducted from the balance) and is then cancelled. Stock gets another +5 kg, so it is 2 kg inflated, and the customer gets the 2 kg return credit a second time. (3) A treasury-paid shipping Expense stays as an outflow for a cancelled sale.
- **Recommendation:** In cancelInvoice: block the cancel (or net it) when returns exist and reverse only the unreturned quantity. Explicitly reverse or soft-delete linked payments, or record a refund voucher through TreasuryService. Soft-delete the generated Expense rows. In ReturnService, reject returns against cancelled invoices and quantities above the remaining sold quantity. Add tests for cancel after a partial return and cancel of a cash invoice.
- **Verification:** I confirmed this from the code and found nothing that guards against it.

1. `InvoiceService::cancelInvoice` (lines 278-335) only does three things: it re-adds the full `$itemLine->quantity` for every line through `StockService::addStock` (`cancellation_in`), sets `status=cancelled` and `remaining_amount=0`, and calls `CustomerBalanceService::updateBalance`.
   - It never touches the `Payment` rows created at lines 229-251 (each has `invoice_id` set).
   - It never touches the treasury-paid `Expense` created at lines 168-178.
   - It never looks at `ReturnDocument` rows.

2. `CustomerBalanceService::updateBalance` computes the balance as confirmed-invoice `net_total` minus all `Payment.amount` for the customer minus all `sales_return` `total_amount`. Payments are not filtered by invoice status. So a fully paid cash invoice of 500 goes from a balance of 0 to -500 after cancel. Nothing records a refund.

3. `TreasuryService` (lines 34-49) counts inflows as every `Payment` with a non-null `customer_id`, and outflows as every `Expense`. Neither query filters on the linked invoice's status. The 500 cash stays as an inflow, and the shipping expense stays as an outflow, after cancel.

4. `ReturnService::createSalesReturn` loads the invoice without checking its status. It never checks the quantity already returned against the invoice, and it adds stock with `unitCost=$item->cost_price`.

5. The return path goes `CreateReturnAction` -> `ReturnService` with no extra check. `StoreReturnRequest` has no `invoice_id` rule at all, so `$request->validated()` strips it. As a result, returns created through the API are never linked to an invoice. The cancel flow therefore has no way to detect a prior partial return. A 2 kg return followed by a 5 kg cancellation over-states stock by 2 kg, and the customer gets the return credit again on top of the cancelled invoice.

6. There are no observers on Invoice, Payment or Expense; the only observer is `TenantObserver`. `CancelSalesInvoiceAction` just delegates to the service. The route only checks the `invoices.cancel` permission and the admin role.

7. The existing test (`tests/Feature/InvoiceServiceTest.php` around lines 150-170) asserts only stock and status after cancel. It never checks the balance or the payments.

This breaks the project rule that cancelling must reverse the stock, balance and treasury effects. Severity stays high: this is the normal cash-sale cancel path, and it corrupts the customer ledger, treasury liquidity and stock.

### 7. [HIGH] Shift closing counts invoice cash twice (invoice paid_amount plus the PAY-INV payment created for the same sale)
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:102`
- **Effort:** S
- **Evidence:** calculateShiftTotals sums `Invoice paid_amount` for cash invoices (lines 81-85) and partial invoices (lines 95-99). It then adds `Payment where customer_id not null and payment_method = 'cash'` (lines 102-105) with no exclusion of invoice-generated payments. confirmInvoice always creates a Payment with invoice_id for paid amounts (InvoiceService.php:229-251). The payments query is also not filtered by store.
- **Impact:** Each 100 cash sale makes the expected drawer cash 200, so every shift close shows a phantom shortage equal to the cash sales. Payments from other branches are also added in. This is outside the invoice service itself but is a direct consequence of how invoices record payments.
- **Recommendation:** Use one source of truth for cash in. Either sum Payments only (filtered by store via the invoice or a store_id on payments, and by method), or exclude payments with invoice_id not null from paymentsCollected. Add a shift-total test with one cash sale.
- **Verification:** I confirmed this from the code. In backend/app/Services/ShiftService.php, calculateShiftTotals adds up Invoice.paid_amount for confirmed invoices with payment_type 'cash' (lines 81-85) and 'partial' (lines 95-99). It then adds every Payment with a non-null customer_id, payment_method 'cash' and created_at after the shift opened (lines 102-105). Nothing excludes payments that have an invoice_id, and the sum is added straight into totalCashIn (lines 107-111) and so into expected_cash_balance (line 141).

Every sale creates one of these payments. InvoiceService::confirmInvoice (InvoiceService.php:224-251) creates a 'PAY-INV-' Payment with customer_id set to the invoice's customer and invoice_id set whenever paid_amount is above 0. Its payment_method defaults to 'cash'. The customer always exists, because line 29 loads it with Customer::where(...)->lockForUpdate()->firstOrFail(). POS sales (ProcessPOSInvoiceAction), sales invoices (CreateSalesInvoiceAction) and blender invoices all go through confirmInvoice.

So a 100 cash sale adds 100 through the invoice sum and another 100 through the payments sum, making the expected drawer cash 200. closeShift (line 164) then records a false shortage equal to the cash sales, saves it to the shift and sends a Telegram discrepancy alert.

The store-isolation part also holds. The Payment model (app/Models/Payment.php) has no store_id in $fillable and no global scope, and the payments query has no store filter. Customer payments from every branch are therefore counted in each store's shift, and the supplier-payments query at lines 120-123 has the same problem.

No other guard exists: no observer or scope excludes invoice-linked payments. The shift tests (tests/Feature/Api/ShiftApiTest.php and ShiftsAndDailyJournalApiTest.php) hard-code expected_cash_balance and never run a real invoice and payment through the calculation, so they do not catch this. High severity is justified because it corrupts every shift close and the stored cash_difference.

### 8. [HIGH] Sales returns are not tied to any invoice: quantity, price and item are unbounded
- **Location:** `D:\projects\sroor\backend\app\Services\ReturnService.php:63`
- **Effort:** L
- **Evidence:** StoreReturnRequest (app/Http/Requests/StoreReturnRequest.php:18-29) has no rules for invoice_id, purchase_id or store_id, so $request->validated() drops them and ReturnDocumentDTO always gets invoice_id = null. createSalesReturn (lines 63-89) loops over client items with no check that the item was on an invoice, that qty <= sold - already returned, or that the customer matches. unit_price comes from the client (line 66, required 'numeric|min:0'), and lineTotal = qty * client price. Restock happens at the current item->cost_price (line 80), not the original invoice_items.cost_price. The return_items table has no cost column (2026_08_08_160008 lines 26-33). The cashier role is seeded with returns.manage (PermissionsSeeder.php:93).
- **Impact:** Any cashier can post a sales return for any customer, any item, any quantity and any unit price. That creates phantom stock and a customer credit or a drawer cash-out (see the shift finding) with no source document. Concurrent returns against the same invoice are also unbounded. This is a direct insider-fraud path for cash and stock, and it makes 'returned qty <= sold qty' impossible to enforce or audit.
- **Recommendation:** Add invoice_id (required for sales_return) and purchase_id to the request. Inside the transaction, lock the invoice and its invoice_items, then compute remaining = sold - SUM(non-deleted return_items for that invoice_item) with bcmath. Reject over-returns. Take unit_price and cost from the invoice line (pro-rata for discounts) instead of the client. Add invoice_item_id and cost_price to return_items through a new tenant migration. Add a concurrency test with two simultaneous returns.
- **Verification:** I confirmed the finding from the code, with small corrections.

**The request drops the invoice link.** `StoreReturnRequest` (backend/app/Http/Requests/StoreReturnRequest.php:18-29) only validates return_type, customer_id, supplier_id, return_date, refund_amount, reason and items.* (item_id, quantity min:0.001, unit_price numeric min:0). It has no rule for invoice_id or purchase_id, so `$request->validated()` drops them and `ReturnDocumentDTO::fromArray` (app/DTOs/Returns/ReturnDocumentDTO.php) always sets invoice_id and purchase_id to null. One correction: store_id is not lost. `ReturnController::store` (app/Http/Controllers/Api/ReturnController.php:120-125) fills it from the X-Store-Id header.

**Nothing in the service bounds the lines.** `ReturnService::createSalesReturn` (app/Services/ReturnService.php:63-89) loops over the client's items without checking:
- that the item was on an invoice,
- that the quantity is no more than sold minus already returned,
- that the customer matches.

unit_price comes straight from the client (line 66), lineTotal = bcmul(qty, client price) (line 67), and restocking uses the item's current cost_price (line 80). Even if invoice_id were passed, line 43 only loads the invoice for store_id and is never used to check anything. No lock is taken on the invoice either.

**The schema has no guard.** The migration (database/migrations/tenant/2026_08_08_160008_create_returns_and_items_tables.php) has no cost column and no constraint tying return_items to invoice_items.

**Cashiers have the permission.** PermissionsSeeder.php:93 gives the cashier role `returns.manage`, and storekeepers get it too (line 107). `StoreReturnRequest::authorize` accepts returns.create or returns.manage. I found no policy, observer or middleware that checks against the invoice.

**The financial effects are real.**
- `CustomerBalanceService` (around line 31) subtracts sum(total_amount) of sales returns from the customer's balance.
- `ShiftService` (around line 126) counts every sales return's total_amount as a cash refund out of the drawer. It does not use refund_amount, which the service ignores anyway.

So one return can both reduce the customer's debt and justify a drawer shortfall. `ProfitLossService` (around line 92) values returned stock at today's weighted_avg_cost or cost_price, not the original cost.

**Why high, not critical.** Abusing this needs an authenticated insider who holds returns.manage, not an outside attacker. Every return also writes an audit log entry (`AuditLogService` 'sales_return_created') and records user_id, so the fraud can be traced after the fact. It is still a real, easy path to cash and stock fraud, and there is no structural way to enforce returned qty <= sold qty.

### 9. [HIGH] A sales return is counted twice: as customer account credit and as a cash refund in the shift
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:126`
- **Effort:** M
- **Evidence:** ShiftService treats SUM(returns.total_amount) of every sales_return since the shift opened as a cash outflow in expectedCash (lines 126-138). At the same time, CustomerBalanceService::updateBalance (lines 30-38) subtracts the same sales returns from the customer's balance, and ReturnService::createSalesReturn calls it (line 94). The request's refund_amount is accepted (StoreReturnRequest:23, ReturnDocumentDTO:33) but never used by ReturnService. No Payment or treasury entry records an actual refund.
- **Impact:** Example: a cash invoice for 100 is fully paid, then returned. The customer's balance becomes -100 (store owes them 100), and the drawer's expected cash also drops by 100. The books show the refund as paid twice. Either the cashier can take 100 from the drawer and the customer still keeps a 100 credit, or the treasury and customer ledgers disagree permanently.
- **Recommendation:** Model the refund explicitly. A return creates a credit to the customer, and a separate refund Payment or treasury movement is created only for refund_amount (cash out), inside the same transaction. The shift then counts only the cash refund payments, not total_amount.
- **Verification:** I confirmed this from the code. Each sales return is counted twice: once as account credit for the customer and once as cash paid out of the shift drawer.

1. In ShiftService::calculateShiftTotals (D:\projects\sroor\backend\app\Services\ShiftService.php:126-129), $refunds is SUM(total_amount) of every sales_return in the store since the shift opened. It does not filter by payment method or refund flag. The sum is added to $totalOutflows (line 138) and subtracted from expected_cash_balance (line 142). closeShift then saves that figure (line 173).

2. ReturnService::createSalesReturn (D:\projects\sroor\backend\app\Services\ReturnService.php:40-104) runs this inside one transaction: create the ReturnDocument, add the stock back, set total_amount (line 91), then call customerBalanceService->updateBalance (line 94). It creates no Payment row and no treasury entry.

3. CustomerBalanceService::updateBalance (D:\projects\sroor\backend\app\Services\CustomerBalanceService.php:30-38) sets balance = invoices - payments - returns, so the full return amount always reduces the customer's balance. getCustomerLedger (lines 91-106) also posts the return as a credit.

4. refund_amount is validated (StoreReturnRequest.php:23) and carried in the DTO (ReturnDocumentDTO.php:18,33,49). The SPA sends it from a field labelled 'returns.refund_cash_from_drawer' with a 'refund_zero_hint' (ReturnFinancialSummary.vue:20-26, useCreateReturn.js:110). ReturnService never reads it. CreateReturnAction (D:\projects\sroor\backend\app\Actions\Returns\CreateReturnAction.php) just passes the data through. No observer, treasury hook or Payment record reflects the refund. TreasuryService has no return handling.

No guard elsewhere prevents this. customer_id is required for sales returns (StoreReturnRequest.php:20), so every sales return always credits a customer account. Example: a 100 cash invoice is paid in full and then returned. The customer's balance becomes -100, and the shift's expected cash also drops by 100, whatever the cashier entered as the refund. The opposite case is just as wrong: if the cashier sets refund 0 to mean "credit the account", the drawer is still expected to be 100 short, so the cash count shows a false 100 excess.

The impact is wrong financial records: the customer ledger and the cash/shift reconciliation can never agree, and the drawer total can be manipulated. High severity is justified.

### 10. [HIGH] Deleting a return does not reverse stock or balances and is available to cashiers
- **Location:** `D:\projects\sroor\backend\app\Actions\Returns\DeleteReturnAction.php:248`
- **Effort:** M
- **Evidence:** execute() is just `ReturnDocument::findOrFail($returnId)->delete()`: a soft delete (the model uses SoftDeletes) with no DB::transaction, no lock, no stock reversal and no balance recompute. ReturnController::destroy (line 138-150) only requires returns.manage, which the cashier and storekeeper roles have. CustomerBalanceService and SupplierBalanceService sum ReturnDocument with the default soft-delete scope. RestoreTrashRecordAction and ForceDeleteTrashRecordAction (app/Actions/Trash) handle 'returns' generically, without re-applying or reversing anything.
- **Impact:** After a sales return is deleted, the restocked goods stay in inventory, but on the next balance recompute the customer's debt comes back. For a purchase return, the deducted stock never comes back. Restoring from the trash then counts the return again in balances without moving stock. Stock and the customer/supplier ledgers drift apart with no movement trail.
- **Recommendation:** Replace delete with a cancel action. Inside DB::transaction: lock the return, reverse each line through StockService (deductStock for a sales return, addStock for a purchase return, with insufficient-stock checks), set status = cancelled, and recompute balances. Block restore and force-delete of returns in the Trash actions, or route them through the same reversal and re-apply logic. Restrict the action to a manager-level permission.
- **Verification:** The finding holds up. Only the cited line number is wrong: DeleteReturnAction.php is 19 lines long and the delete happens at lines 16-17, not line 248. What the code does:

(1) Delete is a bare soft delete. D:\projects\sroor\backend\app\Actions\Returns\DeleteReturnAction.php:16-17 runs `ReturnDocument::findOrFail($returnId)` and then `->delete()`. It has no DB::transaction, no lockForUpdate, no call to StockService and no balance recompute. There is no ReturnDocument observer or model `deleting`/`deleted` hook; app/Observers holds only TenantObserver. The model uses SoftDeletes (app/Models/ReturnDocument.php:11).

(2) Creating a return does move stock and balances. app/Services/ReturnService.php runs createSalesReturn (DB::transaction, lockForUpdate, stockService->addStock at line 77, customer balance at line 93) and createPurchaseReturn (deductStock at line 148, supplier current_balance at line 169). Deleting reverses none of this, so the stock moves made at creation stay in place.

(3) The balance services ignore deleted returns. CustomerBalanceService.php:30-32 and SupplierBalanceService.php:34 sum ReturnDocument with the default soft-delete scope. A later recompute therefore drops the deleted return and the customer or supplier debt comes back, while the stock stays moved. Until that recompute runs, the stored current_balance is stale.

(4) Restoring from the trash does nothing else either. app/Actions/Trash/RestoreTrashRecordAction.php:28 calls `restore()` on the return generically, with no stock or balance logic.

(5) Cashiers and storekeepers can delete. ReturnController::destroy (app/Http/Controllers/Api/ReturnController.php:138-150) checks only for admin or returns.manage. Route api.returns.destroy (routes/api.php:153) sits inside ApiTokenAuth with no extra middleware, and routes/tenant.php:203 adds only can:returns.manage. PermissionsSeeder.php gives returns.manage to cashier (line 93) and storekeeper (line 107). The frontend calls the endpoint from resources/js/Composables/useReturns.js:120.

There is also an extra problem beyond the claim: destroy uses findOrFail with no store scoping, so the delete is not limited to the user's store.

Approved documents are hard-coded as voidable but never deletable without reversal. Here stock and ledgers diverge silently and low-privilege roles can trigger it, so high severity is justified.

### 11. [HIGH] deductStock skips the per-store check when the StoreStock row is missing, so a store can sell another store's stock
- **Location:** `D:\projects\sroor\backend\app\Services\StockService.php:64`
- **Effort:** S
- **Evidence:** Lines 59-73: the StoreStock row is fetched with lockForUpdate()->first(). Only `if ($storeStock && bccomp(...) < 0)` throws, and only `if ($storeStock)` decrements. When the row doesn't exist, the only check is the global item->current_stock at line 47, which is then decremented. InvoiceService::confirmInvoice (line 100) relies on this for every POS sale.
- **Impact:** A branch that has never received an item can sell it as long as any other branch holds stock. items.current_stock is decremented while no store row changes, so SUM(store_stocks.quantity) > items.current_stock afterwards. Per-branch stock reports become wrong, and the next AdjustItemStockAction call resyncs current_stock to the store sum, which makes the sold stock reappear.
- **Recommendation:** When a storeId is resolved, treat a missing StoreStock row as 0 available and throw the insufficient-stock error, unless an explicit 'allow negative stock' setting is on. Add a test: store B has no row, store A has stock, a sale in B is rejected and nothing changes.
- **Verification:** The finding holds up in the code. In StockService::deductStock (backend/app/Services/StockService.php:45-80) the item is locked and checked against the global items.current_stock (line 47). The StoreStock row is then fetched with lockForUpdate()->first() (lines 59-62). The insufficient-stock exception is thrown only when `$storeStock && bccomp(...) < 0` (line 64), and the decrement happens only `if ($storeStock)` (line 70). items.current_stock is decremented regardless (lines 77-80). So when the row is missing, the per-store check and the per-store decrement are both silently skipped.

I found no guard elsewhere:
- InvoiceService::confirmInvoice (lines 59-108) only locks the Item and calls deductStock with the store id. All sales go through it: ProcessPOSInvoiceAction, CreateSalesInvoiceAction and CreateBlenderInvoiceAction.
- No observer creates StoreStock rows. app/Observers contains only TenantObserver.
- hasAvailableStock does return false for a missing row (lines 21-25), but nothing calls it.

The scenario is easy to reach. CreateItemAction (lines 44-50) creates StoreStock rows only for stores that already exist. CreateStoreAction (backend/app/Actions/Stores/CreateStoreAction.php) does NOT create rows for existing items. So any branch added after the catalogue exists has no StoreStock rows, and it can sell any item while other branches hold stock. Seeded items (Item::updateOrCreate in the seeders) and items created by PopulateRealisticTenantDataCommand also have no rows.

The resync effect is real too. AdjustItemStockAction (lines 65-67) sets current_stock = SUM(store_stocks.quantity), which brings back the stock that was sold.

Partial mitigation: items created through the API after all stores exist do get zero-quantity rows, so the bug needs a store-before-item gap. That gap is normal when a tenant opens a new branch. Severity stays high: this is a real inventory-integrity break across branches on the main sales path.

### 12. [HIGH] No store-access enforcement on store_id for stock adjustments, transfers and returns
- **Location:** `D:\projects\sroor\backend\routes\api.php:33`
- **Effort:** M
- **Evidence:** The /api/v1 authenticated group uses only ResolveApiTenancy and ApiTokenAuth. The store.access middleware (bootstrap/app.php:28, StoreAccess.php:27 reads input store_id) is not applied to these routes. AdjustStockRequest only validates store_id with exists:stores,id. StoreStockTransferRequest accepts any from_store_id/to_store_id, and its authorize() is satisfied by stores.view (line 13). ReturnController::store takes X-Store-Id or input store_id without checking access (lines 120-123), and ReturnController::show has no store filter (line 107).
- **Impact:** Inside a tenant, a user assigned to branch A can adjust stock, transfer stock out of, or post returns into branch B just by changing the id. A user with only stores.view can move inventory between any branches. This breaks branch isolation, which is a core selling point of a multi-branch SaaS.
- **Recommendation:** Apply store.access (or a policy) to every write that takes a store_id, check the user's access to both from_store_id and to_store_id on transfers, require transfers.create (already seeded for storekeepers but unused) instead of stores.view, and scope show() by accessible stores. Add isolation tests.
- **Verification:** I could not refute this; the code confirms it. In backend/routes/api.php:19-33 the v1 group applies only ResolveApiTenancy and ApiTokenAuth. The adjust route (line 93), the returns routes (150-153) and the transfers routes (156-159) add no further middleware. The 'store.access' alias is defined at bootstrap/app.php:28 but is never attached to any route: a grep of routes/ and app/ finds no use of store.access or StoreAccess::class. ApiTokenAuth only resolves the user. None of the models (ReturnDocument, StockTransfer, StoreStock) has a global scope or booted() store scope.

1. Stock adjustments: AdjustStockRequest.php authorize() checks admin or items.manage, and the rules check store_id only with exists:stores,id. ItemController::adjustStock (line 218) passes the DTO straight to AdjustItemStockAction, which runs firstOrCreate/lockForUpdate on StoreStock for any store_id. There is no membership check.
2. Transfers: StoreStockTransferRequest.php:13 authorize() returns true for stores.view. The rules accept any existing from_store_id/to_store_id. StockTransferService::createTransfer has no check on whether the user belongs to those stores. StockTransferPolicy exists, and its create() requires stores.manage or transfers.create, but the controller never calls it (no authorize() or Gate use). So a view-only permission is enough to move stock.
3. Returns: ReturnController::store (lines 120-123) takes the store from the X-Store-Id header or the store_id input with no check. ReturnController::show (line 107) loads any ReturnDocument by id with no store filter.

The only per-store check in the API is in StoreController::switchStore (around line 236).

Mitigating factors: this is within one tenant only. The tenant DB-per-tenant split still holds. The attacker must be a logged-in employee with items.manage, stores.view or returns.create. Even so, a view-only user can create transfers, and a user of one branch can write stock movements to any other branch. This breaks branch isolation, so high is justified.

### 13. [HIGH] A treasury-paid landed expense is posted as a payment to the supplier: the supplier balance is understated and the treasury counts the outflow twice
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:164`
- **Effort:** M
- **Evidence:** When paid_by != 'supplier_account' (StorePurchaseRequest allows 'treasury_cash'), createPurchase does Payment::create(['payment_number' => 'PAY-EXP-...', 'supplier_id' => $purchase->supplier_id, 'purchase_id' => ..., 'amount' => $expAmount]) (lines 170-179), and the amount is NOT added to net_total (lines 185-189). SupplierBalanceService::updateBalance (line 28) subtracts every Payment with that supplier_id. TreasuryService::getBalances counts the same row twice: once in $supplierOutflows (whereNotNull('supplier_id'), line 40) and again in $expensePayments (payment_number LIKE 'PAY-EXP-%', line 52), then adds the two together (line 57).
- **Impact:** Example: a purchase of 1000 plus 100 freight paid in cash to a third party. The supplier balance shows 900 when the business really owes 1000, so the supplier is underpaid by 100 and the statement is wrong. The cash treasury drops by 200 instead of 100, so the shift and treasury reconciliation is off by the expense amount every time. Both errors build up silently with each purchase that has treasury-paid expenses.
- **Recommendation:** Treasury-paid landed costs must not be supplier Payments. Record them as an Expense (or a treasury movement) with no supplier_id, or add a payment 'kind' column and exclude non-supplier kinds from SupplierBalanceService and from supplierOutflows. Add a regression test that asserts both the supplier balance and the treasury balance after a purchase with a treasury_cash expense. A data-repair command is needed for existing tenants.
- **Verification:** I confirmed this in the code. StorePurchaseRequest.php:33 accepts `additional_expenses.*.paid_by` with the value 'treasury_cash'. PurchaseDTO passes `additional_expenses` through, and CreatePurchaseAction calls PurchaseService::createPurchase, so the API can reach this path.

1. **Payment written against the supplier.** In PurchaseService.php:167-180, a non-'supplier_account' expense creates a Payment with payment_number 'PAY-EXP-...', supplier_id = the purchase's supplier, and amount = the expense.
2. **Amount left out of the purchase total.** Lines 185-189 add only $supplierExpensesTotal to net_total, so the treasury-paid expense never reaches it.
3. **Supplier balance understated.** SupplierBalanceService::updateBalance (lines 28-29) subtracts the sum of every Payment for that supplier_id, with no filter on payment_number. The supplier balance therefore drops by the expense amount even though the supplier was never paid it. getSupplierLedger also lists the payment as a debit labelled as a cash payment voucher.
4. **Treasury outflow counted twice.** TreasuryService::getBalances counts the same row in $supplierOutflows (whereNotNull supplier_id, lines 40-43) and again in $expensePayments (payment_number LIKE 'PAY-EXP-%', lines 52-55), then adds both (line 57). The statement method has the same double count in the opening balance (lines 212-225, sum at 241) and in the period movements (lines 264-282).

I found no guard that prevents this: the Payment model has no global scope, the only observer is TenantObserver, and no other code excludes PAY-EXP rows. The 1000 + 100 example holds: the supplier balance shows 900 and cash drops by 200.

I lowered the severity from critical to high because only purchases with treasury-paid landed expenses are affected, and nothing is lost or corrupted. The stored Payment rows are correct, so the numbers can be recalculated once the queries are fixed. The impact is real: supplier balances and statements are wrong, and treasury balances are understated each time this feature is used.

### 14. [HIGH] The supplier opening balance is wiped by the first balance recompute
- **Location:** `D:\projects\sroor\backend\app\Actions\Suppliers\CreateSupplierAction.php:24`
- **Effort:** M
- **Evidence:** CreateSupplierAction writes 'current_balance' => $dto->opening_balance. No opening_balance column exists in any tenant migration (grep found none). SupplierBalanceService::updateBalance (lines 17-47) rebuilds current_balance as purchases - payments - returns from scratch and overwrites it. ReturnService::createPurchaseReturn (lines 164-170) does the same. SuppliersApiTest::test_can_pay_supplier_and_decrease_balance only passes because it seeds a 5000 purchase that matches the 5000 'balance'.
- **Impact:** A tenant onboards a supplier with an opening debt of 15000. The first purchase, payment or return replaces the balance with the document-derived figure, so 15000 of payable disappears from the books and the supplier statement. The same applies to any negative (advance) opening balance.
- **Recommendation:** Persist the opening balance as its own column (new tenant migration, guarded with hasColumn), or as a ledger opening document. Include it in updateBalance and in getSupplierLedger. Remove the duplicated balance formula in ReturnService and call SupplierBalanceService::updateBalance instead. Check CustomerBalanceService for the same pattern.
- **Verification:** I confirmed this from the code. CreateSupplierAction.php:24 stores the request's opening_balance only in suppliers.current_balance. The suppliers tenant migration (2026_08_08_160003) has no opening_balance column, no other migration adds one, and no opening-balance document is created. The grep for opening_balance finds only DTOs, requests, translations and treasury deposit_type.

SupplierBalanceService::updateBalance (lines 17-47) rebuilds the balance from scratch as confirmed purchases minus payments minus purchase returns, then overwrites current_balance (line 43). Nothing adds the opening amount back. It is called from PurchaseService lines 224, 311, 404 and 445 and from PaymentService:120. ReturnService::createPurchaseReturn (lines 164-170) uses the same formula and overwrite.

So the first purchase, cancellation, payment or purchase return for a supplier wipes out an opening debt, or an opening advance if negative. getSupplierLedger also starts its running balance at '0.000' (line 118), so the opening amount never appears on the supplier statement either. The suppliers.current_balance total feeds the dashboard and treasury payable figures, so those under-report too.

I found no guard elsewhere: no observer, no extra column and no ledger entry. The cited test seeds a 5000 balance plus a matching 5000 purchase, which hides the bug.

Severity stays high. The loss is silent and permanent: the payable disappears from the books for any tenant that onboards suppliers with existing debt, which is a core onboarding step for a sellable SaaS. Not checked here: CustomerBalanceService and CreateCustomerAction:24 look like the same pattern but need their own check.

### 15. [HIGH] Cancelling a purchase soft-deletes every linked supplier payment, including real cash vouchers, so phantom cash returns to the treasury
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:300`
- **Effort:** M
- **Evidence:** cancelPurchase runs Payment::where('purchase_id', $lockedPurchase->id)->delete(). This also removes vouchers created later through PaymentService::recordSupplierPayment with that purchase_id, not only the inline payment created at purchase time. The treasury is derived from Payment rows (TreasuryService::getBalances lines 34-57), so deleting the rows reverses the cash outflow. No refund or receipt voucher is created. restorePurchase later restores ALL trashed payments for the purchase through withTrashed()->restore() (lines 376-378).
- **Impact:** Example: pay the supplier 1000 cash, then cancel the purchase because the goods went back. The treasury shows 1000 more cash than the drawer holds, and the supplier balance shows 0 when the supplier actually owes a 1000 refund. Nothing in the books records that the money is still owed. Cash reconciliation breaks and the refund claim is lost.
- **Recommendation:** Never delete payment vouchers on cancel. Leave them in place so the supplier ends with a negative balance (a receivable). Alternatively, require an explicit refund voucher that brings the cash back into the treasury. In both cases, unlink the payments from the cancelled document instead of deleting them, and make restore idempotent: restore only the payments that this cancel removed.
- **Verification:** I confirmed this in the code. In PurchaseService::cancelPurchase (D:\projects\sroor\backend\app\Services\PurchaseService.php:300), the call `Payment::where('purchase_id', $lockedPurchase->id)->delete()` soft-deletes every payment linked to the purchase (the Payment model uses SoftDeletes). Nothing filters by payment type or creator. PaymentService::recordSupplierPayment (app/Services/PaymentService.php:86-111) stores a caller-supplied purchase_id on later supplier vouchers. StoreSupplierPaymentVoucherRequest.php:23 accepts purchase_id, so vouchers paid after the purchase are deleted too. The inline payment created at purchase time is deleted the same way.

The treasury balance is computed live from Payment rows (TreasuryService::getBalances, lines 40-43, sums supplier payments by method). Soft-deleted rows drop out of that sum, so the cash outflow disappears. Because the rows vanish from past dates, balances for earlier periods change as well.

The supplier balance is also recomputed from confirmed purchases minus non-deleted payments (SupplierBalanceService::updateBalance, lines 22-41). After cancellation the purchase and its payments both drop out, so the supplier shows 0 and no refund is owed.

Nothing creates a refund or receipt voucher, a supplier credit, or an offsetting treasury entry. CancelPurchaseAction only calls the service. There is no guard in the Action, the DTO or the controller that blocks cancelling a purchase with payments. The only precondition is enough stock to reverse the quantities.

restorePurchase (lines 375-378) restores all trashed payments for the purchase through withTrashed()->restore(), as the finding says.

The only way to defend this is to read cancellation as meaning "the supplier refunded the cash". Even then, no refund is recorded, the treasury's history is rewritten, and there is no option to keep the money as a supplier credit. The treasury and the cash drawer will not reconcile, so the high severity stands.

### 16. [HIGH] Supplier voucher can be posted against another supplier's purchase or a cancelled purchase, with no remaining-balance check
- **Location:** `D:\projects\sroor\backend\app\Services\PaymentService.php:86`
- **Effort:** S
- **Evidence:** recordSupplierPayment locks Purchase::where('id', $purchaseId) with no supplier_id = $supplier->id check and no status == 'confirmed' check. It does newPaid = paid_amount + amount, and when it exceeds net_total it clamps remaining to 0 but stores paid_amount > net_total. StoreSupplierPaymentVoucherRequest only validates exists:purchases,id and amount min:0.01. There is no max: amount and no remaining_amount check.
- **Impact:** A typo in purchase_id marks supplier B's purchase as paid while reducing supplier A's balance. Purchase status and supplier balance then disagree, and the unpaid-purchases report hides a debt that still exists. Payments against cancelled purchases make the balance negative, and they are deleted again if the same purchase is restored and re-cancelled.
- **Recommendation:** Inside the transaction, require purchase.supplier_id === supplier.id and status === 'confirmed'. Reject an amount above remaining_amount with a 422 (or post the excess explicitly as an on-account advance). Add max:999999999.999 and decimal:0,3 validation. Use translated messages.
- **Verification:** The finding holds. I checked it against the code and found no guard anywhere. In backend/app/Services/PaymentService.php:86-104, recordSupplierPayment locks Purchase::where('id', $purchaseId) and does not check purchase.supplier_id == $supplier->id, status == 'confirmed', or the remaining balance. It sets paid_amount = paid_amount + amount with no cap and clamps remaining_amount to '0.000' and payment_status to 'paid', so stored paid_amount can exceed net_total. The Payment row is created with supplier_id = $supplier->id (A) and purchase_id = B's purchase. backend/app/Http/Requests/StoreSupplierPaymentVoucherRequest.php only has 'exists:purchases,id' and 'amount' => required|numeric|min:0.01. There is no max, no closure checking supplier or status, and no decimal:0,3. Its authorize() also lets anyone with 'daily_journal.view' post a voucher. The controller (backend/app/Http/Controllers/Api/PaymentController.php:112-117, route POST /payments/supplier-voucher at routes/api.php:117) passes validated input straight through. The Purchase and Payment models have only SoftDeletes, no global scope or observer that would block this. One nuance: supplier A's balance stays internally consistent, because SupplierBalanceService::updateBalance sums Payment by supplier_id and counts only confirmed purchases. The real damage is that purchase B's paid/remaining/payment_status becomes wrong. A payment against a cancelled purchase lowers the balance and can make it negative, since that purchase is excluded from the total but the payment still counts. The impact is actually worse than claimed. PurchaseService::cancelPurchase (backend/app/Services/PurchaseService.php:300) runs Payment::where('purchase_id', ...)->delete() and then recalculates only the purchase's own supplier (B). So cancelling B's purchase silently soft-deletes supplier A's misattached voucher and leaves A's current_balance stale. restorePurchase (lines ~376-381) then restores every trashed payment for that purchase_id regardless of supplier. Exploiting this needs an authenticated user with suppliers.manage or daily_journal.view, and it is mostly a mistake or misuse path rather than an attack. It still corrupts supplier ledgers and purchase payment state, so high is justified.

### 17. [HIGH] Shift expected cash double-counts every cash sale (invoice paid_amount plus the auto-created PAY-INV payment)
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:102`
- **Effort:** M
- **Evidence:** calculateShiftTotals sums Invoice.paid_amount for payment_type cash and partial (lines 81-99). It then adds all Payment rows with customer_id NOT NULL, payment_method='cash' and created_at >= opened_at (lines 102-111). InvoiceService::confirmInvoice (lines 225-253) always creates a 'PAY-INV-' Payment with customer_id set and method 'cash' by default for the paid amount, so the same money is counted twice. TreasuryController::summary (net_cash_today = paid_amount + today's customer receipts) and GetDailyJournalAction (lines 38-46, 64) repeat the same double count. ShiftApiTest and ShiftsAndDailyJournalApiTest create no sales inside a shift, so no test catches this.
- **Impact:** For a shift with 10,000 in cash sales, expected_cash_balance is about 20,000 above the real drawer. Every close shows a large false shortage, which makes the variance useless for spotting real theft, and a Telegram discrepancy alert fires on every shift. The Z-report persists the wrong expected and difference values.
- **Recommendation:** Use one source of truth for cash inflow: sum Payment rows (method cash, scoped to the shift's store, created between opened_at and closed_at) and stop adding invoice paid_amount on top. Better still, stamp shift_id on payments, expenses and returns. Add a ShiftApiTest that makes a cash sale and a debt collection inside the shift and asserts the exact expected_cash_balance.
- **Verification:** The double count is real; I confirmed it in the code. In ShiftService::calculateShiftTotals (backend/app/Services/ShiftService.php:81-111), cash inflow is computed as the sum of Invoice.paid_amount for confirmed invoices with payment_type 'cash' or 'partial', plus every Payment where customer_id is not null, payment_method is 'cash' and created_at >= opened_at.

InvoiceService::confirmInvoice (backend/app/Services/InvoiceService.php:224-253) always creates a 'PAY-INV-' Payment for the paid money, with customer_id set, invoice_id set and method defaulting to 'cash'. This happens on both branches: once per entry when a payments[] array is sent, or once for the whole paid amount otherwise. The same pattern repeats at lines 548/561, which is the update path.

All sales paths go through confirmInvoice: ProcessPOSInvoiceAction, CreateSalesInvoiceAction and CreateBlenderInvoiceAction. Nothing filters these rows out. The Payment model has no global scope, the shift query has no whereNull('invoice_id'), and no observer is involved. closeShift stores the inflated expected_cash_balance and cash_difference, and sends a Telegram discrepancy alert whenever the difference is not zero. GetActiveShiftAction shows the same inflated live figure.

The same double count appears in GetDailyJournalAction.php:38-63 (cashSales + partialSales + customerPayments) and in TreasuryController.php:43-46 (paid_amount + today's customer Payments). In the treasury summary it is even broader, because there is no payment_method filter there.

One correction to the claimed impact: 10,000 in cash sales inflates expected cash by about 10,000 (inflow counted as 20,000), not "about 20,000 above the real drawer." The false shortage is therefore roughly equal to the cash sales, not double them. That does not change the conclusion. Every shift with sales will show a false shortage, the alert fires on every close, and the stored Z-report values are wrong, so severity stays high.

Related issue: the customer and supplier Payment queries in the shift calculation are not filtered by store_id. Payments from other branches also leak into a store's shift total.

### 18. [HIGH] Customer opening balance is erased by the first balance recalculation
- **Location:** `D:\projects\sroor\backend\app\Services\CustomerBalanceService.php:16`
- **Effort:** M
- **Evidence:** CreateCustomerAction line 24 stores the opening balance directly as 'current_balance' => $dto->opening_balance, and the customers table has no opening_balance column. CustomerBalanceService::updateBalance later recomputes current_balance = confirmed invoices - payments - sales returns and overwrites the column (lines 38-39). The opening amount is not part of that formula. updateBalance runs on every invoice, payment, return and cancel.
- **Impact:** A customer migrated in with 5,000 of debt shows 100 after one 100 sale, so 5,000 of receivables silently disappears. getCustomerLedger also never shows an opening line. The same pattern probably exists for suppliers (SupplierBalanceService uses the same formula).
- **Recommendation:** Add an opening_balance DECIMAL(12,3) column (new tenant migration guarded with hasColumn, plus a data backfill decision). Include it in updateBalance and as the first ledger row, and add a test that creates a customer with an opening balance, sells and pays, then asserts the balance.
- **Verification:** Confirmed from the code. backend/app/Actions/Customers/CreateCustomerAction.php:24 writes 'current_balance' => $dto->opening_balance. The opening amount is stored nowhere else: the tenant migration 2026_08_08_160002_create_customers_table.php:18 has only current_balance, no later tenant migration adds an opening_balance column, and no opening Payment or Invoice row is created. CustomerBalanceService::updateBalance (lines 16-43) recomputes the balance as confirmed invoices net_total - payments - sales returns (line 38) and overwrites current_balance (lines 40-41). The opening amount is not in that formula. updateBalance runs on invoice confirm (InvoiceService:255), cancel and edit (InvoiceService:316, 575, 578, 649), payments (PaymentService:64) and sales returns (ReturnService:94). So the first such event resets the balance, for example 5000 opening plus a 100 credit sale ends up as 100. getCustomerLedger also starts runningBalance at '0.000' (line 111) and has no opening entry. I found no observer, mutator or other guard that keeps the amount. The supplier side has the same defect: CreateSupplierAction.php:24 does the same thing, and SupplierBalanceService::updateBalance (lines 17-47) uses the same formula without the opening amount. It is triggered from PurchaseService:224/311/404/445 and PaymentService:120. Receivables and payables silently disappear from the dashboard, treasury and report totals. High severity is justified for a financial ledger, especially for tenants moving in existing debt.

### 19. [HIGH] Purchase 'treasury-paid' extra expenses are double-counted in treasury and wrongly reduce supplier debt
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:40`
- **Effort:** M
- **Evidence:** PurchaseService lines 168-180 create a Payment with payment_number 'PAY-EXP-...' and supplier_id = $purchase->supplier_id for expenses with paid_by treasury_*. Those amounts are not added to net_total; only the supplier_account path adds them. TreasuryService::getBalances subtracts supplier payments (whereNotNull('supplier_id'), lines 40-43) and then subtracts 'PAY-EXP-%' payments again (lines 52-57). The same happens in getTreasuryReport (lines 212-225 and 264-280). SupplierBalanceService::updateBalance line 28 subtracts all payments for the supplier, PAY-EXP included.
- **Impact:** Freight of 500 paid in cash on a purchase lowers the cash treasury by 1,000. The supplier's payable also drops by 500 for money that went to a third party, so the business underpays the supplier or disputes their statement.
- **Recommendation:** Record treasury-paid purchase expenses without supplier_id: either as an Expense row or as a payment type/category column instead of the 'PAY-EXP' string prefix. Make every treasury query classify each payment exactly once. Add treasury and supplier-balance tests for a purchase with a treasury-paid expense.
- **Verification:** I confirmed this from the code, and the path can be reached through the normal API.

1. The input is accepted. StorePurchaseRequest:33 allows additional_expenses.*.paid_by to be 'treasury_cash', and supplier_id is required (line 19). PurchaseDTO passes additional_expenses through to the service (lines 34 and 50). CreatePurchaseAction:22 calls PurchaseService::createPurchase.

2. The service writes a supplier payment for an expense paid from the treasury. For paid_by other than 'supplier_account', PurchaseService:168-180 creates a Payment with payment_number 'PAY-EXP-...', supplier_id = $purchase->supplier_id and payment_method 'cash'. That amount is added only to additional_expenses_total and never to net_total (lines 184-189 add only supplierExpensesTotal).

3. The cash treasury balance drops twice. TreasuryService::getBalances subtracts every Payment with a non-null supplier_id (lines 40-43). It then subtracts payments matching 'PAY-EXP-%' again (lines 52-57), and both are added into totalOutflows (line 57). Nothing excludes PAY-EXP from the supplier query. getTreasuryReport repeats this for the opening balance (212-225, 243) and for the period (264-282). So 500 of freight paid in cash lowers the cash balance by 1,000. This also matters beyond reporting: transfer() checks available funds with getBalances (line 142), so the understated balance can block valid transfers. The ledger in buildLedgerEntries lists it only once, as a supplier payment, so the ledger and the summary disagree.

4. The supplier's debt is understated. SupplierBalanceService::updateBalance:27-28 subtracts every Payment for the supplier, PAY-EXP included. Purchases contribute only net_total, which leaves out the treasury-paid expense. The supplier's payable therefore drops by money that went to a third party.

I found no guard elsewhere: Payment.php has no global scope or boot hook, the only observer is TenantObserver, and no other code filters PAY-EXP. The claimed impact holds, so the high severity is justified.

### 20. [HIGH] Payments have no store_id, so per-branch treasury, shift and journal figures mix all branches
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:34`
- **Effort:** L
- **Evidence:** The payments table (2026_08_08_160007_create_payments_table.php) has no store_id, and 2026_08_11_180002_add_store_id_to_existing_tables.php skips payments. In getBalances, the Payment queries (lines 34-55) ignore $storeId while Expense and TreasuryTransfer queries apply it. The same gap is in ShiftService lines 102-105 and 120-123 (customer and supplier payments are not store-scoped), GetDailyJournalAction lines 43-52, and TreasuryController::summary.
- **Impact:** In a multi-branch tenant, branch A's cash balance and shift expected cash include collections and supplier payments made at branch B. Cash reconciliation per branch, which is a core selling point of the SaaS, is wrong as soon as a second branch exists.
- **Recommendation:** Add store_id to payments (tenant migration plus a backfill from invoice/purchase store_id), set it in every Payment::create, and filter by it in TreasuryService, ShiftService, GetDailyJournalAction and TreasuryController. Add a store-isolation test.
- **Verification:** The finding holds; I could not refute it. The payments table is created in backend/database/migrations/tenant/2026_08_08_160007_create_payments_table.php with no store_id column. The store-id backfill migration (2026_08_11_180002_add_store_id_to_existing_tables.php, lines 14 and 37) only covers invoices, purchases, expenses, returns, cash_shifts and stock_movements. The later migrations that touch payments (soft deletes, payment-method enum update) do not add one either. The Payment model has no store_id in $fillable and no global scope or booted() store scope.

Where the queries ignore the store:
- TreasuryService::getBalances: the three Payment sums at lines 34-37 (customer inflows), 40-43 (supplier outflows) and 52-55 (PAY-EXP) take no store filter, while Expense (line 47), TreasuryTransfer (lines 61/67/73) and CashShift (line 85) all apply ->when($storeId, ...).
- ShiftService: the cash payments collected (around lines 101-105) and supplier cash payments (around lines 119-123) are filtered only by created_at >= shift open time, while the Invoice, Expense and ReturnDocument queries in the same method filter by store_id. So a branch's expected drawer cash takes in customer receipts and supplier payouts from every branch made since its shift opened.
- GetDailyJournalAction (lines 42-52) and TreasuryController::summary (lines 46 and 49) sum Payment with no store filter either.

The schema leaves no way to scope these sums, because there is no store column and the code does not join through invoice_id or purchase_id. Per-branch cash reconciliation is wrong as soon as a tenant has two or more branches, and per-branch store isolation is a stated core rule (.claude/rules/multi-tenancy.md). High severity is justified: it is a money-reconciliation error, not a race or data corruption, and it does not cross tenant boundaries.

### 21. [HIGH] Sales returns and invoice cancellations are handled inconsistently between treasury, shift and customer balance
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:126`
- **Effort:** L
- **Evidence:** ReturnService::createSalesReturn creates no refund Payment and only calls updateBalance. ShiftService subtracts the full total_amount of every sales_return in the store as a cash refund (lines 126-129), even when the return was against a credit invoice. TreasuryService never subtracts returns at all. InvoiceService::cancelInvoice (lines 308-316) leaves the PAY-INV payments in place, so the cancelled cash sale's money stays as a treasury inflow, a shift 'payment collected' and a customer credit (balance = confirmed invoices - all payments).
- **Impact:** A return on a credit invoice makes the drawer look short in the shift but changes nothing in the treasury. A cancelled cash sale whose cash was handed back still shows as cash in the treasury, and the walk-in/cash customer builds up a negative balance. Treasury, shift and customer figures can never be reconciled with each other.
- **Recommendation:** Define refund semantics explicitly. A sales return should carry a refund_method/refund_amount and create a negative/refund Payment (outflow) when cash is returned. Cancelling a paid invoice should create a reversing payment or require an explicit refund choice. All reports should then read the same records.
- **Verification:** I confirmed this from the code. Nothing else in the code (action layer, observer or service) makes up for it.

1. **Returns create no refund Payment.** `ReturnService::createSalesReturn` (backend/app/Services/ReturnService.php:38-104) writes the ReturnDocument and its items, adds the stock back and calls `CustomerBalanceService::updateBalance`. It never creates a Payment. `CreateReturnAction` only calls `createReturn`, and no other `Payment::create` handles refunds (the others are in PaymentService, PurchaseService and InvoiceService).

2. **The shift treats every return as a cash refund.** `ShiftService::calculateShiftTotals` (lines 126-129) adds up `total_amount` for every `sales_return` in the store since the shift opened and counts it as `refunds` in cash outflows. It does not check how the original invoice was paid or how the refund was given, so a return on a credit invoice still lowers the expected drawer cash.

3. **The treasury never sees returns.** `TreasuryService::getBalances` (lines 33-57) counts customer Payments as inflows and supplier payments, expenses and transfers as outflows. TreasuryService contains no reference to ReturnDocument, sales_return or refunds.

4. **Cancelling an invoice leaves its payments in place.** `InvoiceService::cancelInvoice` (lines 278-335) puts the stock back, sets status to cancelled and remaining_amount to 0, then recalculates the balance. It does not touch the PAY-INV Payments created at lines 228-251. Only `deleteInvoice` (line 634) and `updateInvoice` (line 542) remove them. This leaves:
   - **Customer balance:** `CustomerBalanceService::updateBalance` (confirmed invoices − all payments − returns) leaves out the cancelled invoice but keeps its payment, so the customer ends up with a negative balance (a credit).
   - **Treasury:** the payment still counts as an inflow.
   - **Shift:** `paymentsCollected` (ShiftService lines 101-104) still counts it, although `cashSales` correctly drops the cancelled invoice.

One correction to the evidence: the customer balance formula does subtract returns (confirmed invoices − payments − returns), not just "invoices − all payments". This does not change the conclusion. A return on a cash invoice is still both credited to the customer and taken out of the shift drawer, so it is counted twice.

Overall, the treasury, shift and customer figures cannot be reconciled with each other. I am keeping the severity at high.

### 22. [HIGH] X-Store-Id is not validated against the user's store access, and shift close, Z-report and expense edit/delete are not store-scoped
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:83`
- **Effort:** M
- **Evidence:** ApiTokenAuth copies any numeric X-Store-Id into the session without an access check (lines 83-86). The /api/v1 group in routes/api.php uses only ResolveApiTenancy and ApiTokenAuth, with no StoreAccess/StoreScope. ShiftController::close accepts any shift_id, and CloseShiftAction does CashShift::where('status','open')->findOrFail($id) with no store or user check. zReport loads any shift by id. ExpenseController::update/show use Expense::findOrFail($id) without a store filter. ShiftPolicy, PaymentPolicy, ExpensePolicy and TreasuryPolicy exist but no controller calls authorize().
- **Impact:** A cashier with pos.sell at branch A can close branch B's open shift with an arbitrary counted cash amount, read B's Z-report, open shifts or record expenses for B, and edit or delete any branch's expenses. The second isolation layer inside a tenant is broken for cash operations.
- **Recommendation:** Apply StoreAccess middleware to the API group, or validate X-Store-Id against the user's stores in ApiTokenAuth. In the shift and expense actions, scope lookups to the allowed store and use the existing Policies via $this->authorize(). Add isolation tests.
- **Verification:** Confirmed in code; the expense part of the claimed impact is overstated.

1. `backend/app/Http/Middleware/ApiTokenAuth.php:83-86` writes any numeric X-Store-Id header into `session('current_store_id')` without an access check.

2. The `/api/v1` group (`backend/routes/api.php:19`, `33`) applies only ResolveApiTenancy and ApiTokenAuth. StoreScope is only added to the web group (`backend/bootstrap/app.php:19-21`). The `store.access` alias (StoreAccess) is registered but used on no route (grep found no usage).

3. The app does mean to limit users to their assigned stores. `StoreController` (around line 224-237) refuses to switch to a store the user is not assigned to. But X-Store-Id and `store_id` input skip that check, and `User::getCurrentStore()` (`backend/app/Models/User.php:75-80`) trusts the session value.

4. Shifts:
   - `ShiftController::close` takes `shift_id` from raw input.
   - `CloseShiftAction` runs `CashShift::where('status','open')->findOrFail($dto->shift_id)` with no store or user check.
   - `CloseShiftRequest::authorize` only checks admin, `daily_journal.view` or `pos.sell`.
   - `ShiftService::closeShift` then saves `actual_cash_balance` and `cash_difference` from the caller's number.
   - `zReport` runs `GetShiftZReportAction` (`CashShift::findOrFail($shiftId)`) with no store check.
   - `open` uses the header's store directly.
   - CashShift and Expense have no global scopes.

5. Expenses: `show` (line 166), `update` (179) and `destroy` (201) use `Expense::findOrFail($id)` with no store filter.

6. Policies: no controller calls `authorize()`. Even `ShiftPolicy` would not help, because it checks permissions only and never compares stores.

Why the impact is overstated:
- Editing, deleting or creating expenses needs `expenses.manage` (StoreExpenseRequest, UpdateExpenseRequest, and the `destroy` check). A plain `pos.sell` cashier cannot do it; only a manager-level user from another branch can.
- Expense delete is a soft delete, and shift closes are written to the activity log.
- This is an insider risk inside one tenant; it does not cross tenants.

Even so, a `pos.sell` cashier can close another branch's open shift with any counted-cash figure and read its Z-report. That breaks store isolation for cash reconciliation, so high severity stays.

### 23. [HIGH] The P&L summary and comprehensive report ignore sales returns, so revenue, gross profit and net profit are overstated
- **Location:** `D:\projects\sroor\backend\app\Actions\Reports\GetProfitLossReportAction.php:21`
- **Effort:** M
- **Evidence:** total_sales, total_cogs, gross_profit and net_profit are built only from Invoice::where('status','confirmed') (lines 21-55). ReturnDocument is never queried. A grep for 'sales_return|ReturnDocument' in app/Actions/Reports, app/Actions/Dashboard, DashboardAnalyticsService and InventoryAnalyticsService returns nothing. GetItemsProfitabilityReportAction, GetStoresComparativeReportAction, ProfitService::getPeriodicProfits (used by the dashboards) and InventoryAnalyticsService's ABC analysis also omit returns. Only ProfitLossService subtracts returns, and it is reachable only from ReportPrintController, which no route uses.
- **Impact:** Each sales return puts stock back (StockService::addStock 'sales_return_in') and lowers the customer's debt. The /api/v1/reports/summary and /reports/comprehensive endpoints still count the full invoice as revenue and profit. A shop with a 5% return rate sees revenue and gross profit overstated by about that much on every API report and dashboard tile.
- **Recommendation:** Build one shared ProfitService method that returns net revenue (confirmed invoices minus sales returns in the period and store) and net COGS (invoice-line cost snapshot minus returned-line cost snapshot). Use it from every report action and from both dashboard actions. Delete the parallel ProfitLossService logic or route it through the same method. Add ReportsApiTest cases with a return in the period.
- **Verification:** The finding holds up against the code. In D:\projects\sroor\backend\app\Actions\Reports\GetProfitLossReportAction.php, lines 21-55 build total_sales, total_cogs, gross_profit and net_profit only from Invoice::where('status','confirmed') net_total and total_cost, minus expenses. Grepping app/Actions/Reports for ReturnDocument, sales_return or returns finds nothing.

I looked for a guard that would make up for this and found none:
- ReturnService::createSalesReturn (app/Services/ReturnService.php:38-104) creates a ReturnDocument and its items, puts stock back with StockService::addStock('sales_return_in') and recalculates the customer balance. It never changes the original Invoice's net_total, total_cost or status, so the invoice is still counted in full.
- No file matching *Return* writes net_total or total_cost.
- Sales returns can be created by users through POST /api/v1/returns (routes/api.php:152) and tenant.php:202.
- The affected endpoints are live: /api/v1/reports/summary and /reports/comprehensive (routes/api.php:138-139, ReportController::summary and ::comprehensive) both call GetProfitLossReportAction.
- ProfitLossService does subtract sales returns (lines 81-83). Its only caller is ReportPrintController:541, and no route file references ReportPrintController, so the correct calculation cannot be reached.
- ProfitService.php and DashboardAnalyticsService.php contain no ReturnDocument or sales_return references.

Impact: revenue, COGS, gross profit, margin and net profit on the main P&L report are overstated by the value of sales returns. COGS is also overstated because returned stock is added back but its cost is never removed. The two errors partly cancel in gross profit, but revenue is still overstated by the full sale value. This is a reporting error, not corrupted stock or balance data. Even so, it misstates the system's headline financial statement, and no route reaches a correct alternative, so high severity is defensible.

### 24. [HIGH] Deleting or restoring a return through the trash, or force-deleting it, never reverses or re-applies its stock and balance effects
- **Location:** `D:\projects\sroor\backend\app\Actions\Trash\RestoreTrashRecordAction.php:32`
- **Effort:** L
- **Evidence:** The trash whitelist is items, customers, suppliers, stores, expenses and returns. Both the restore action and the force-delete action (line 32 in each) only call $model->restore() or $model->forceDelete(), with no transaction, no stock call and no balance recompute. DeleteReturnAction (app/Actions/Returns/DeleteReturnAction.php:16-17) is just ReturnDocument::findOrFail($id)->delete(), with no transaction and no stock reversal. CustomerBalanceService::updateBalance and SupplierBalanceService::updateBalance recompute from SUM(total_amount), which excludes soft-deleted rows, but nothing calls them on delete or restore. On force delete, the DB cascades return_items (returns migration line 28), while the stock_movements rows that point to the return through source_type/source_id are left behind.
- **Impact:** Example: a sales return of 10 kg is deleted. Stock keeps the 10 kg (phantom inventory). The customer's stored current_balance stays reduced until some unrelated recalculation suddenly raises it. Restoring the return leaves the balance stale in the other direction. A purchase return that is deleted leaves the stock deducted, while the supplier balance later jumps back up. Force delete then destroys the document and its lines permanently, leaving orphaned movements. None of this is audited. Any user with returns.manage can trigger it.
- **Recommendation:** Use cancel instead of delete for returns: add a status column, and give the cancel action a DB::transaction that locks the rows and reverses stock through StockService and the balance through the balance services. Remove 'returns' (and 'expenses') from the trash restore and force-delete whitelist, or route those types through domain actions that re-validate stock and re-apply effects in one transaction. Never force-delete financial documents.
- **Verification:** I confirmed this from the code and found no guard that prevents it.

- **Delete:** `DeleteReturnAction::execute` (`app/Actions/Returns/DeleteReturnAction.php:16-17`) only runs `ReturnDocument::findOrFail($id)->delete()`. It has no `DB::transaction`, no stock call, no balance recompute and no audit log. `ReturnController::destroy` calls it directly, gated only by admin or `returns.manage` (`routes/tenant.php:203`, `routes/api.php:153`).
- **Restore and force delete:** `RestoreTrashRecordAction:32` only calls `$model->restore()`, and `ForceDeleteTrashRecordAction:32` only calls `$model->forceDelete()`. Both whitelist `'returns'`. `TrashController` adds a `trash.access` permission check and nothing else.
- **No other guard:** `ReturnDocument` has no observer and no booted hooks. `AppServiceProvider` registers only `TenantObserver`, and `app/Observers` contains only `TenantObserver.php`. `ReturnService` has only the create methods and `generateUniqueNumber`, with no cancel or reverse method. Soft deletes really are in place: the trait is on the model and `returns` is in `2026_08_11_200000_add_soft_deletes_to_all_tables.php`, so the delete succeeds instead of failing.
- **The effects that never get undone:** creating a return does change stock and balances. `ReturnService::createSalesReturn` runs inside a transaction, calls `stockService->addStock(... source: $returnDoc, movementType: 'sales_return_in')` and then `customerBalanceService->updateBalance`. The purchase path does the opposite.
- **Balance drift:** `CustomerBalanceService::updateBalance` (lines 16-44) recomputes from `ReturnDocument::where(...)->sum('total_amount')`. The default `SoftDeletes` scope leaves deleted returns out of that sum, so the stored `current_balance` stays wrong until some later invoice or payment triggers a recompute, and then it jumps.
- **Force delete:** `return_items` is `cascadeOnDelete` (`2026_08_08_160008_create_returns_and_items_tables.php:28`). The polymorphic `stock_movements` rows have no FK cascade, so they are left orphaned.

The impact is as described: phantom or missing stock, stale balances that later jump, and permanent loss of the document. It also breaks the project rule that approved documents must be cancelled with their effect reversed, never deleted. One point is softer than claimed: "no audit" applies to delete, restore and force delete, but creation is audited. High is the right severity.

### 25. [HIGH] Treasury report double-counts treasury-paid purchase landed costs and does not filter payments by store
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:212`
- **Effort:** M
- **Evidence:** PurchaseService:169-178 creates PAY-EXP-* payments with supplier_id set. TreasuryService counts them twice: once in $priorSupplierOutflows/$periodSupplierOutflows (whereNotNull('supplier_id'), lines 212-215 and 263-267) and again in $priorExpensePayments/$periodExpensePayments (payment_number like 'PAY-EXP-%', lines 222-225 and 276-280). The same pattern appears at lines 39-57. None of the Payment queries in the account loop apply $storeId, while Expense and TreasuryTransfer queries do. SupplierBalanceService:28-29 also subtracts these PAY-EXP payments from the supplier balance, even though the treasury-paid expense was never added to the purchase net_total (PurchaseService:184-188 adds only supplier_account expenses).
- **Impact:** Every landed cost paid in cash, such as freight, lowers the reported cash and wallet balance twice and also wrongly lowers what the shop owes the supplier. Store-filtered treasury balances mix all branches' customer receipts and supplier payments with a single branch's expenses, so per-branch cash positions are meaningless.
- **Recommendation:** Count PAY-EXP payments in exactly one bucket (exclude them from the supplier outflow query, or drop the separate bucket). Exclude them from SupplierBalanceService, or don't set supplier_id on them and use a payee/type column instead. Add store_id to payments, or filter through the invoice/purchase relation consistently in every Payment query. Add a regression test.
- **Verification:** I confirmed this from the code. PurchaseService.php:169-179 creates a Payment numbered 'PAY-EXP-...' with supplier_id set for every treasury-paid additional expense. The API can reach this path: StorePurchaseRequest.php:33 allows paid_by=treasury_cash, PurchaseDTO passes additional_expenses through, and CreatePurchaseAction calls PurchaseService.

In TreasuryService, those payments match both whereNotNull('supplier_id') and payment_number LIKE 'PAY-EXP-%', and both sums are added to outflows. This happens in getBalances at lines 40-43 and 52-57, and in getTreasuryReport at lines 212-225 and 243 for the opening balance, and lines 264-282 for the period. No query excludes PAY-EXP from the supplier sum. So each cash landed cost is deducted twice. getBalances also feeds the balance check in transfer() at line 142, so the overstated deduction can wrongly block a valid treasury transfer.

The store filtering problem is also real. Payment has no store_id column and no global scope (Payment.php has only SoftDeletes). None of the Payment sums in getBalances or the getTreasuryReport account loop apply $storeId. The Expense, TreasuryTransfer and CashShift queries in those same loops do filter by store. buildLedgerEntries does filter by store through whereHas on invoice or purchase, so the ledger and the summary disagree.

The supplier balance claim also holds. SupplierBalanceService.php:23-29 subtracts every payment carrying supplier_id, including PAY-EXP ones. PurchaseService.php:184-189 adds only supplier_account expenses to net_total, so a cash-paid freight cost wrongly lowers what the shop owes the supplier.

One part is overstated. Validation allows only treasury_cash, so through the API this affects the cash drawer, not wallets. That does not reduce the impact. Severity stays high: the integrity bug affects cash balances, the transfer guard and supplier payables.

### 26. [MEDIUM] The live stock-adjustment endpoint allows negative stock and silently overwrites items.current_stock
- **Location:** `D:\projects\sroor\backend\app\Actions\Items\AdjustItemStockAction.php:35`
- **Effort:** M
- **Evidence:** For waste_out or stock_adjustment_out, newStoreQty = bcsub(current, qty) with no bccomp < 0 guard (lines 35-39). Lines 64-66 then set item->current_stock = StoreStock::where('item_id')->sum('quantity'), which replaces the master stock with the store sum. stock_before and stock_after record store-level quantities (lines 51-52), while StockService records item-global quantities. The document number is `count(today's movements) + 1` (lines 41-43), so it isn't unique. The safe StockService::adjustStock (with its negative guard) is never called from app/; it is only called from tests (StockAdjustmentFeatureTest:39,65,93). StockService::depositStock is also unused.
- **Impact:** POST /api/v1/items/{id}/adjust-stock can drive a branch to negative stock, and the master stock can jump whenever it had drifted from the store rows (for example after the deductStock gap). The passing tests cover dead code, so the real endpoint has no coverage of these rules.
- **Recommendation:** Make the endpoint delegate to one StockService method that has a negative guard, keeps item and store stock consistent through deltas rather than a SUM overwrite, and generates document numbers under a lock. Remove or merge the unused adjustStock and depositStock paths, and move the feature tests to the API endpoint.
- **Verification:** Mostly confirmed, but parts are overstated.

Confirmed in the code:
- In AdjustItemStockAction.php:35-39, `waste_out` and `stock_adjustment_out` compute `bcsub(current, qty)` with no `bccomp` check against zero. Nothing else stops it:
  - AdjustStockRequest only requires `quantity` min:0.001 and `store_id` exists:stores.
  - The `store_stocks.quantity` column (tenant migration 2026_08_11_180000:35) is a plain decimal with no unsigned or check constraint.
  - No allow-negative setting exists.
  - So a user with `items.manage` or the admin role can drive a branch below zero through POST /api/v1/items/{id}/adjust-stock (routes/api.php:93, ItemController:218).
- `stock_before` and `stock_after` (lines 51-52) are store-level values. StockService::adjustStock and depositStock record item-global values, so the ledger semantics are inconsistent.
- The document number is a daily count plus 1 (lines 41-43). This collides when movements are soft-deleted or requests run concurrently. The count also includes every movement type, not just ADJ rows. `document_number` has no unique index, so duplicates insert silently.
- StockService::adjustStock and depositStock are called only from tests: StockAdjustmentFeatureTest and FractionalWeightSaleTest:46. Nothing in app/ calls them.

Overstated:
1. "Silently overwrites `current_stock`": recalculating `item.current_stock` as the sum of the store rows inside the same transaction, with the item row locked, is arguably the correct way to keep the derived total in line with per-store stock, which the rules treat as the source of truth. It drops any earlier drift rather than causing the drift itself.
2. "The real endpoint has no coverage": tests/Feature/Api/ItemsApiTest.php:307 does exercise POST /adjust-stock. It covers only the happy path for adjustment-in; there is no test for negative stock or adjustment-out.
3. The endpoint needs `items.manage` or the admin role, so the actor is a privileged user making a manual correction, not a cashier.

The missing negative-stock guard on a live endpoint breaks the integrity rules, so this is real but medium, not high.

### 27. [MEDIUM] P&L recomputes historical COGS with today's weighted average cost and ignores waste and adjustments
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:74`
- **Effort:** M
- **Evidence:** For each historical invoice line, COGS = quantity * current item->weighted_avg_cost (line 74), even though invoice_items.cost_price stores the cost at the time of sale (InvoiceService:93). Return COGS uses the same current-WAC logic (line 93). No waste_out, stock_adjustment_out or adjustment_in movement value is included anywhere in ProfitLossService or ProfitService. Grep shows those types only in ExportService and GetItemMovementsAction.
- **Impact:** Past-period gross profit changes every time a new purchase moves the WAC. Inventory write-downs and shrinkage (waste_out) never reach the P&L, so profit is overstated by the full value of lost stock.
- **Recommendation:** Use invoice_items.cost_price (and return_items.cost_price once added) for COGS. Add an inventory-loss/gain line computed from adjustment and waste movements (quantity * unit_cost) for the period and store.
- **Verification:** The finding is real but narrower than claimed.

**Confirmed in code:**
- `ProfitLossService.php:74` sets the cost of each historical invoice line to the item's current `weighted_avg_cost`, falling back to `cost_price`.
- `ProfitLossService.php:93` uses the same current-cost logic for return lines.
- This ignores the cost stored at the time of sale: `invoice_items.cost_price` (`InvoiceService:93`/`398`) and `invoices.total_cost` (`InvoiceService:221`/`537`). So past-period COGS in this service changes whenever the weighted average cost moves.
- Stock written off or lost never reaches any P&L. `AdjustItemStockAction` writes a `StockMovement` with a `unit_cost` and creates no `Expense`. The only `Expense::create` calls outside the expense action and a demo-data command are the two in `InvoiceService` (lines 168/479). Neither `ProfitLossService`, `ProfitService` nor `GetProfitLossReportAction` reads waste or stock-adjustment movements.

**Overstated:**
- `ProfitLossService` is only called from `ReportPrintController.php:541-542` (`printProfitLossReport`, the printed A4 P&L).
- The main API P&L is `ReportController` → `GetProfitLossReportAction`. It adds up `invoice.total_cost`, the historical cost, so it does not have the current-cost problem.
- The dashboards use `ProfitService::getPeriodicProfits`, which also adds up `invoice.total_cost`.

**Net effect:**
- The current-cost COGS bug affects only the printed P&L, which will disagree with the on-screen P&L for the same period.
- The missing waste and adjustments affect every P&L. Profit is overstated by the value of lost stock, and adjustments that add stock are ignored too.

That is a real accounting gap but not a corruption of stored data, so medium rather than high.

### 28. [MEDIUM] Purchase cancel does not reverse the weighted-average cost, and restore applies it a second time
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:282`
- **Effort:** M
- **Evidence:** cancelPurchase only calls stockService->deductStock. Neither it nor StockService::deductStock touches weighted_avg_cost or cost_price. After the cancel, item.cost_price stays equal to the cancelled landed cost and the WAC still includes the cancelled lot. restorePurchase (lines 351-360) blends the lot into the WAC again. InvoiceService lines 82-83 use weighted_avg_cost as COGS. A fix ('Reverse WAC on purchase cancel/restore', commit d08edc92) exists on main but is NOT an ancestor of HEAD on feature/multi-tenant (git merge-base --is-ancestor returned false).
- **Impact:** Example: stock 10 @ 100, then a mistaken purchase of 10 @ 200 brings the WAC to 150. Cancelling it leaves 10 units valued at 150 instead of 100. Every later sale overstates COGS by 50 per unit and understates profit. The inventory valuation is wrong, and a cancel/restore cycle compounds the error.
- **Recommendation:** Port the WAC reversal from main (d08edc92): on cancel, back the lot out of the WAC using the purchase line's landed cost_price, and restore the previous cost_price. Add tests for cancel and restore that assert the WAC. Audit the other fixes on main that are missing from the SaaS branch.
- **Verification:** The cancel half is confirmed on feature/multi-tenant. In backend/app/Services/PurchaseService.php, createPurchase blends the lot into the item at lines 118-126 (it sets item.cost_price to the landed cost and item.weighted_avg_cost to the new WAC). cancelPurchase (lines 246-330) checks stock, then only calls stockService->deductStock (line 286), soft-deletes the payments and updates the status. Nothing in it writes weighted_avg_cost or cost_price. backend/app/Services/StockService.php never mentions weighted_avg_cost; its only cost writes are cost_price at line 202, in a different path. No observer touches weighted_avg_cost. InvoiceService.php lines 82-84 use weighted_avg_cost, falling back to cost_price, as effectiveCost and store it as the invoice line's cost_price. So after a cancel, the cancelled lot's cost stays in the WAC and in cost_price, and every later sale books wrong COGS and profit. The finding's example (10@100 + 10@200 -> 150, cancel leaves 150) holds. This is reachable: PurchaseController line 156 -> CancelPurchaseAction -> cancelPurchase, and deletePurchase (line 433) also goes through cancelPurchase. git merge-base --is-ancestor d08edc92 HEAD returned 1, so the WAC fix commit is not on this branch. The restore half is overstated. restorePurchase (lines 334-410) would blend the lot into the WAC a second time, but grep finds no caller in app/, routes/ or resources/js: no route, action or UI. It is dead code today, so the cancel/restore compounding cannot happen in practice. I lowered the severity to medium because the damage is wrong costing and profit reporting, not lost cash or stock. It only shows up when a purchase is cancelled after its cost changed the WAC, and the restore escalation cannot be reached.

### 29. [MEDIUM] Cancelling a purchase ignores existing purchase returns: stock is deducted twice and the supplier balance goes negative
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:258`
- **Effort:** M
- **Evidence:** The cancel sufficiency check and the reversal loop use the full itemLine->quantity. They never look at ReturnDocument rows with purchase_id = this purchase. ReturnService::createPurchaseReturn has already deducted that stock (line 148). After the cancel, the purchase is excluded from updateBalance (status 'confirmed' filter) but its returns are still subtracted (SupplierBalanceService lines 34-36, no status/purchase filter). PurchaseService has no reference to returns at all.
- **Impact:** Example: purchase 100 kg, return 20 kg to the supplier, then cancel the purchase. If other stock exists, 100 kg is deducted again instead of 80, so 20 kg of stock is destroyed on paper. The supplier balance becomes -200 (the supplier appears to owe a refund for goods already credited). Both the inventory and the payables ledger are corrupted.
- **Recommendation:** In cancelPurchase, compute the net quantity per item as purchased minus already returned, and reverse only that. Block the cancel when any purchase return exists unless those returns are reversed first. Also validate in createPurchaseReturn that the returned quantity does not exceed the purchased quantity minus earlier returns, and that the purchase belongs to the supplier.
- **Verification:** The core mechanism checks out in the code, but the finding overstates it.

**Confirmed:**
- PurchaseService::cancelPurchase (D:\projects\sroor\backend\app\Services\PurchaseService.php:258-295) checks and deducts the full $itemLine->quantity with movementType 'purchase_cancel_out'.
- It never looks at returns, and Purchase has no returns relation.
- ReturnService::createPurchaseReturn (D:\projects\sroor\backend\app\Services\ReturnService.php:148) has already deducted the returned quantity.
- SupplierBalanceService::updateBalance (D:\projects\sroor\backend\app\Services\SupplierBalanceService.php:23-36) counts only purchases with status 'confirmed', but subtracts all purchase_return totals for the supplier, with no status or purchase filter.
- No guard was found in CancelPurchaseAction, the PurchaseController::cancel permission check, or PurchaseService::deletePurchase (which also calls cancelPurchase).
- So "purchase 100, return 20, cancel" removes 100 more from stock, if other stock covers it, and leaves the supplier at -(return value).

**Why it is overstated:**
1. The evidence says the code "never looks at ReturnDocument rows with purchase_id = this purchase". But such rows effectively never exist. StoreReturnRequest has no purchase_id rule, and ReturnController::store passes $request->validated() to the DTO, so purchase_id is always null for API-created returns. Returns are linked only to the supplier. The real defect is that returns are not tied to purchases and cancel has no guard for that, not that cancel ignores linked returns.
2. A remedy exists: DELETE /returns/{id} (ReturnController::destroy -> DeleteReturnAction). A user can reverse the return before cancelling, so the damage needs that step to be skipped.
3. The sufficiency check blocks the cancel when the remaining stock is below the full purchased quantity. The stock damage only happens when other stock of the item exists.

It is real but depends on the workflow and is recoverable, so medium rather than high.

### 30. [MEDIUM] daily_journal.view (a read permission) is enough to create supplier disbursement vouchers
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreSupplierPaymentVoucherRequest.php:15`
- **Effort:** S
- **Evidence:** authorize() returns true when the user has admin || suppliers.manage || can('daily_journal.view'). The POST /api/v1/payments/supplier-voucher route has no other permission middleware (routes/api.php line 117).
- **Impact:** A user whose role only allows viewing the daily journal can post cash payouts to any supplier. Each payout reduces the treasury balance and the supplier debt. That is an authorization hole on a money-out operation in a SaaS product sold to shops with staff roles.
- **Recommendation:** Require a write permission (suppliers.manage or a dedicated payments.create / supplier_payments.create), never a view permission. Add a test that a daily_journal.view-only user gets 403. Apply the same check to customer receipts (StoreCustomerPaymentReceiptRequest).
- **Verification:** The finding holds. StoreSupplierPaymentVoucherRequest.php:13-15 authorizes a user who has role admin, OR suppliers.manage, OR daily_journal.view. Operator precedence does not save it: `??` binds looser than `||` in PHP, so daily_journal.view alone is enough. The route at backend/routes/api.php:117 (POST /payments/supplier-voucher) has no `can:` middleware of its own. PaymentController::supplierVoucher (line 112) calls paymentService->recordSupplierPayment directly, with no extra authorize call. PaymentPolicy::createSupplierVoucher (app/Policies/PaymentPolicy.php:35-40) uses the same three permissions, so it adds no stricter check. The default seed makes this reachable: PermissionsSeeder gives the 'cashier' role daily_journal.view but not suppliers.manage. A plain cashier can therefore record supplier disbursements that cut the treasury and the supplier's balance. I lowered the severity from high to medium for three reasons. (1) The overlap looks like a deliberate, codebase-wide convention: daily_journal.view also gates expense creation (StoreDailyJournalExpenseRequest), shift open/close and customer receipts. GetRolesMatrixAction.php:106 even labels the permission as opening and closing shifts and approving the Z-Report, so it already works as a drawer-operator permission and not a pure read. (2) A cashier can already take money out through expenses under the same permission, so this endpoint opens no new money-out path; it is one instance of a permission-design flaw. (3) It needs an authenticated tenant user, and the payout is recorded against a named supplier, so it can be audited. The real defect is the missing dedicated permission (for example payments.supplier_voucher / treasury.disburse) on a money-out operation.

### 31. [MEDIUM] No idempotency on purchase creation or supplier payments: a double click or retry records duplicates
- **Location:** `D:\projects\sroor\backend\app\Services\PaymentService.php:80`
- **Effort:** M
- **Evidence:** recordSupplierPayment and PurchaseService::createPurchase take no client key. payment_number is generated with uniqid() (lines 107, 171, 212), so two identical requests both pass the unique index. The supplier lockForUpdate only serializes the two requests; both still commit. git log shows no supplier-payment duplicate fix on this branch. The only 'duplicate payment' fix (PR #3, 930250b4) is about invoice edits and is only on origin/main, not on feature/multi-tenant.
- **Impact:** A double tap on 'Pay supplier' or a mobile/Electron network retry records two vouchers. The treasury drops twice and the supplier balance is understated. A duplicate purchase doubles the stock and the payable.
- **Recommendation:** Add a nullable unique idempotency_key / client_uuid column (new tenant migration) to payments and purchases. Accept it from the client, and inside the transaction return the existing row when the key is already present. As a minimum stop-gap, also disable double submit in the frontend.
- **Verification:** The finding is real: the server has no idempotency, so a repeated request records a second payment or purchase. The impact is overstated, so I lowered it from high to medium.

What the code shows:
- **Supplier payment:** `PaymentService::recordSupplierPayment` (backend/app/Services/PaymentService.php:80-130) locks the supplier and purchase rows, then always creates a new Payment. The payment number is `'PAY-SUPP-' . strtoupper(uniqid())` (line 107), so the unique index on `payment_number` (database/migrations/tenant/2026_08_08_160007_create_payments_table.php:13) never catches a duplicate.
- **Supplier payment request:** `StoreSupplierPaymentVoucherRequest` takes only supplier_id, amount, purchase_id, payment_date, payment_method and notes. There is no client key or nonce.
- **Purchase creation:** `PurchaseService::createPurchase` (backend/app/Services/PurchaseService.php:26) makes a fresh purchase number every time. `supplier_invoice_ref` is only `nullable|string` in StorePurchaseRequest:21, with no unique rule. A replayed purchase therefore adds the stock and the amount owed to the supplier a second time.
- **No guard elsewhere:** I searched app/, routes/, bootstrap/, config/ and resources/js for "idempot", "Idempotency-Key", "X-Request-Id" and "client_uuid" and found nothing. No middleware, observer or database rule blocks a repeat.
- **Locks do not help:** `lockForUpdate` only makes the two requests run one after the other; both still commit.

Why it is lower than claimed:
- The supplier payment screen (resources/js/Composables/useSuppliers.js:157-180) sets `isSubmittingPayment` while the request is in flight, which limits the simple double-click case. I did not check that the button is actually disabled by this flag.
- I found no automatic retry in the frontend HTTP client, so the "network retry" case needs a user to resubmit by hand, or a lost response followed by a resubmit.

The risk that remains is real: a duplicate submit from an Electron or mobile client, two tabs, or the separate routes (`POST /suppliers/{id}/pay` and `/payments/supplier-voucher`) can each record a duplicate. That would lower the treasury twice, understate the supplier balance, or double the stock and payable.

Medium severity, not high: a deliberate resubmit is needed, a client-side guard exists, and the duplicate is a visible, cancellable document rather than silent corruption.

### 32. [MEDIUM] ProfitLossService and ABC analysis compute COGS from the item's current WAC instead of the invoice-line cost_price snapshot
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:74`
- **Effort:** M
- **Evidence:** ProfitLossService:74: $itemCost = (string)($item->item?->weighted_avg_cost ?: ($item->item?->cost_price ?: '0.000')); and line 93 does the same for return lines. InventoryAnalyticsService:68 also uses $item->weighted_avg_cost to compute COGS for sold quantities. But InvoiceService:82-93 snapshots invoice_items.cost_price at sale time, and GetItemsProfitabilityReportAction:28 correctly uses SUM(quantity * cost_price).
- **Impact:** Historical profit changes every time a purchase moves the WAC. Last month's P&L printed today differs from the one printed last month. The ABC profit, profit_share and class (A/B/C) for a past period are wrong whenever costs have moved. ABC analysis is returned inside /api/v1/reports/inventory, so this reaches the live API.
- **Recommendation:** Always aggregate SUM(invoice_items.quantity * invoice_items.cost_price) or invoices.total_cost. Add a cost_price snapshot column to return_items (see the related finding) and use it for return COGS.
- **Verification:** The code matches the finding, but the impact is overstated. ProfitLossService.php:74 and :93 do take COGS from the item's current weighted_avg_cost (falling back to cost_price) rather than the invoice line snapshot. InventoryAnalyticsService.php:68 does the same for the ABC analysis. InvoiceService.php:82-93 does store invoice_items.cost_price (the effective WAC at sale time), and that column exists as DECIMAL(12,3) in tenant migration 2026_08_08_160005 line 37. So the snapshot is available and ignored. However, ProfitLossService is only called from ReportPrintController.php:541, and ReportPrintController is not referenced by any route (no match in routes/ or bootstrap/; /reports/print in web.php is a separate closure). It is effectively dead code. The live P&L API (ReportController -> GetProfitLossReportAction) sums the invoice-level total_cost snapshot at line 37, which is correct. The P&L half of the impact ("last month's P&L printed today differs") therefore does not reach users. The ABC half is real and live: GetInventoryValuationReportAction.php:99 calls getAbcAnalysis, which is served at /api/v1/reports/inventory (api.php:144) and /reports/export-abc (tenant.php:207). Historical ABC COGS, gross profit, margin and A/B/C class all change whenever WAC moves. These numbers are analytics only and are never written back to the ledger, stock or balances, and results are cached for 15 minutes. That makes this a reporting-accuracy bug, not a ledger-integrity bug, so I rate it medium, not high.

### 33. [MEDIUM] Force-deleting a soft-deleted store wipes its store_stocks rows through a DB cascade and orphans its stock movements, expenses and shifts
- **Location:** `D:\projects\sroor\backend\app\Actions\Trash\ForceDeleteTrashRecordAction.php:26`
- **Effort:** M
- **Evidence:** 'stores' => Store::onlyTrashed()->findOrFail($id) followed by $model->forceDelete(). In 2026_08_11_180000_create_stores_and_stocks_tables.php:33, store_stocks.store_id has cascadeOnDelete. The store_id columns added to invoices, stock_movements, expenses and other tables in 2026_08_11_180002 are plain unsignedBigInteger with no FK. Store::getDeletionBlockers (Store.php:145-162) runs only at soft-delete time, checks quantity > 0, counts only non-trashed invoices and purchases, and ignores stock_movements, expenses, cash shifts and treasury transfers. Force delete re-checks nothing.
- **Impact:** Store A has soft-deleted invoices, expenses or shift history, and an admin purges it from the trash. All its store_stocks rows are deleted, and every movement, expense and shift row keeps a dangling store_id. Reports that join or group by store lose that history permanently (treasury_transfers.store_id is nulled).
- **Recommendation:** Block force delete of stores entirely, or re-run a stricter blocker check that includes withTrashed documents, stock_movements, expenses, cash_shifts and treasury_transfers. Change the store_stocks FK to restrict through a new migration.
- **Verification:** The finding is real, but its impact is overstated.

Confirmed in the code:
- ForceDeleteTrashRecordAction.php:26-32 runs Store::onlyTrashed()->findOrFail($id)->forceDelete() and does not re-check anything.
- The only guard is DeleteStoreAction (Store::canBeDeleted/getDeletionBlockers), and it runs only at soft-delete time.
- The route is guarded only by trash.access or the admin role (TrashController.php:76-80, routes/tenant.php:232, api.php:193).
- store_stocks.store_id and store_user.store_id use cascadeOnDelete (create_stores_and_stocks_tables.php:33, 48).
- invoices, purchases, expenses, returns, cash_shifts and stock_movements.store_id are plain unsignedBigInteger columns with no FK (2026_08_11_180002:14-19). No later migration adds one.
- treasury_transfers.store_id and activity_logs.store_id are nullOnDelete.

Corrections that lower the severity:
1. Expenses are not ignored. getDeletionBlockers (Store.php:165-168) counts them, but only non-trashed rows. The real gap is soft-deleted expenses: DeleteExpenseAction exists and 'expenses' is a trash type.
2. Stock transfers have restrictOnDelete FKs (create_stock_transfers_tables.php:27-28), so force delete fails for any store with transfers.
3. I found no invoice or purchase delete path in app/. These documents are cancelled, not soft-deleted, so a cancelled invoice still blocks deletion. The "soft-deleted invoices" scenario is mostly theoretical.
4. The cascade only removes store_stocks rows with quantity <= 0, because the soft-delete guard blocks any store with quantity > 0. These rows hold min_stock and custom_selling_price config, not on-hand value. The one exception is negative-stock rows, which pass the guard and are silently erased.

What remains true: cash_shifts and stock_movements are never checked. AdjustItemStockAction.php:45 creates adjustment movements with no invoice or purchase behind them. So a store with only adjustments, shifts, or soft-deleted expenses can be soft-deleted and then purged. Its shift and movement rows keep a dangling store_id, its treasury_transfers lose their store_id, and its activity logs are nulled. The result is permanent loss of per-store attribution of historical data. No stock balance or money total is corrupted.

This is a gap in referential integrity and audit trail, reachable only by an admin or a trash.access user. Medium, not high.

### 34. [MEDIUM] Reports and the API have no store-access enforcement, so any reports.view user can read any branch through store_id
- **Location:** `D:\projects\sroor\backend\app\DTOs\Reports\ReportFilterDTO.php:44`
- **Effort:** M
- **Evidence:** ReportFilterDTO:44-45 takes $data['store_id'] (any int) in preference to the header, and store_id=all falls back to the header. FilterReportRequest validates store_id only as ['nullable']. StoreScope and StoreAccess are registered only on the web group (bootstrap/app.php:20,27-28). The /api/v1 group in routes/api.php:19,33 uses only ResolveApiTenancy and ApiTokenAuth. GetStoresComparativeReportAction ignores store scope entirely and returns sales, profit and margin for every active store. GetProfitLossReportAction:58 sums customer debt across all stores whatever the store filter is. Both report actions return company-wide Item.current_stock valuation when no store is set.
- **Impact:** A branch manager or cashier with reports.view gets profit, margin and receivables for every other branch through ?store_id=N or the stores tab. This breaks the 'store is the second isolation layer' rule.
- **Recommendation:** Apply store.access middleware to the /api/v1 tenant group. In buildDTO, check that the requested store_id is in the user's accessible stores (403 otherwise), and gate cross-store or 'all' views behind a dedicated permission. Scope the stores comparative report to the user's accessible stores.
- **Verification:** The code backs up the mechanics of this finding, but the impact is overstated.

**Confirmed in the code:**
- `ReportFilterDTO.php:44-45` uses the request's `store_id` as-is when present, and falls back to the header store for 'all' or empty.
- `ReportController::buildDTO` passes `$request->all()` with no store check.
- `FilterReportRequest` validates `store_id` only as `['nullable']`, and its `authorize()` checks only admin, `reports.view` or `reports.advanced`.
- The `/api/v1` report routes (`routes/api.php:19,33,138-147`) use only `ResolveApiTenancy` and `ApiTokenAuth`.
- `ApiTokenAuth` copies `X-Store-Id` into the session without checking it, so the header is not trusted either.
- `StoreAccess` is worse than the finding says. It is only aliased (`bootstrap/app.php:28`) and grep finds no route or group that applies it. `StoreScope` only sets a default session store and never checks membership.
- `Invoice`, `Expense` and `Customer` have no global store scope.
- `GetStoresComparativeReportAction` returns sales, profit and margin for every active store.
- `GetProfitLossReportAction:58` sums active customers' `current_balance` across the whole tenant, whatever the store filter.
- When `store_id` is null, the action values stock from `Item.current_stock` company-wide.

So a user with `reports.view` who is assigned only to one store through `store_user` can read another store's P&L with `?store_id=N`. This breaks the rule in `.claude/rules/multi-tenancy.md` that a user without access to a store must get a 403.

**Why the severity is lower:**
- In `PermissionsSeeder`, only `admin` (which `StoreAccess` itself treats as all-store) and `accountant` get `reports.view`. Cashier and storekeeper do not, so the claim that a cashier can do this is wrong under the default roles.
- The `reports.view` description in the seeder includes "مقارنة الفروع" (branch comparison). That means the stores comparison tab is meant to be cross-store for holders of this permission.
- The leak is read-only and stays inside one tenant. Nothing is modified and no other tenant is exposed.

The real exposure is an accountant who is meant to be limited to one branch, or a custom role that a tenant admin gives `reports.view` through `RoleController`. That makes this a real but medium-severity gap in store isolation, not high.

### 35. [MEDIUM] Lock order is inconsistent: confirm locks customer then items, while cancel/update/delete lock invoice, then items, then customer; item locks are also not sorted
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:295`
- **Effort:** S
- **Evidence:** confirmInvoice locks Customer at line 29, then each Item in payload order at line 61 (StoreStock is locked inside deductStock). cancelInvoice locks Invoice at line 285, then Items at line 295, then Customer last through customerBalanceService->updateBalance (CustomerBalanceService:18 lockForUpdate). updateInvoice and deleteInvoice follow the same order. Item ids are never sorted. DB::transaction is called with the default single attempt.
- **Impact:** (a) Sale T1 for customer C locks C and waits on item A, while cancel T2 of another C invoice holds A and waits on C. InnoDB detects the deadlock and rolls one back, which surfaces as HTTP 500. The shared walk-in customer makes this likely during busy hours. (b) Two sales for different customers with items [A,B] and [B,A] can also deadlock. Neither case corrupts data, since the rollback is clean, but sales fail at the till.
- **Recommendation:** Use one lock order everywhere: customer, then items sorted by id ascending, then store_stock. In cancel, lock the customer before the items. Sort `$data['items']` by item_id before locking (keep the original line order for display). Consider DB::transaction($cb, 3) for deadlock retry, and add a concurrency test on MySQL.

### 36. [MEDIUM] Overpayment, unsupported payment types and unvalidated expense fields get through validation
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreSalesInvoiceRequest.php:44`
- **Effort:** S
- **Evidence:** `payment_type` allows `bank_transfer`, but the invoices.payment_type column is `enum('cash','credit','partial')` (migration 2026_08_08_160005 line 17), and confirmInvoice treats any type other than cash or partial as credit (line 195). `paid_amount` is only `min:0` with no maximum. confirmInvoice:193-202 stores paid_amount larger than net_total and clamps only remaining_amount. `additional_expenses` is a bare array with no `*.amount`, `*.paid_by` or `*.allocation_method` rules, and paid_by drives Expense creation with `str_replace('treasury_','',$paidBy)` as the payment method (line 166). StorePOSInvoiceRequest has no partial type, no discount_type/discount_value rules (so POS invoice-level discounts are dropped) and no check that sum(payments.*.amount) matches the total.
- **Impact:** Partial sale of 1,000 with paid_amount 5,000: the invoice shows paid 5,000 > net 1,000, a 5,000 payment is booked, and the customer gets a hidden 4,000 credit. ShiftService then counts 5,000 as cash sales. payment_type=bank_transfer gives an SQL enum error (HTTP 500) on MySQL. A crafted paid_by creates treasury expenses under arbitrary method keys that the per-method treasury never reports.
- **Recommendation:** Remove bank_transfer from payment_type (it is a method). Validate paid_amount <= computed net_total inside the Action and throw a 422 ValidationException. Add rules for additional_expenses.*.amount (numeric, min:0.001, decimal:0,3), paid_by (in:customer_account,treasury_<active methods>) and allocation_method (an enum). Require sum(payments) = paid_amount.

### 37. [MEDIUM] Business errors (insufficient stock, already cancelled, permission) are thrown as generic Exception with Arabic literals, so the client gets HTTP 500
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:280`
- **Effort:** S
- **Evidence:** cancelInvoice:280-282 and deleteInvoice:605-607 throw `new Exception("عفواً، لا يملك صلاحية ...")` when the user lacks hasRole('admin'), which overrides the `invoices.cancel` permission already checked in CancelInvoiceRequest. StockService:48 and :67 throw generic Exception for insufficient stock. confirmInvoice:69 throws DomainException with a hardcoded Arabic message. bootstrap/app.php maps only Authentication, Spatie Unauthorized and ValidationException.
- **Impact:** A user granted invoices.cancel but without the admin role always gets a 500, so the permission is meaningless. With APP_DEBUG off, a cashier who oversells sees 'Server Error' instead of 'insufficient stock'. The messages are untranslated, which breaks the English locale.
- **Recommendation:** Create domain exceptions (InsufficientStockException → 409, InvoiceAlreadyCancelledException → 409) with __() keys in lang/ar and lang/en. Render them in bootstrap/app.php. Remove the hardcoded hasRole('admin') checks and rely on the FormRequest permission or a Policy.

### 38. [MEDIUM] Invoice numbers come from an unlocked MAX+1 read; the unique index prevents duplicates, but concurrent sales fail with 500 instead of retrying
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:693`
- **Effort:** S
- **Evidence:** generateUniqueNumber (lines 672-715) reads the last `INV-{STORE}-{Ymd}-%` with a plain query (no lockForUpdate, no sequence row), then checks exists() in a loop. invoice_number is `->unique()` (migration 160005 line 13), which is global within the tenant DB, and per store through the store-code prefix. Tenants are isolated by their own DB. Expense numbers use `'EXP-'.date('Ymd').'-'.substr(uniqid(),-4)` (lines 167 and 478) against a unique expense_number. Sorting invoice_number as a string breaks after 9999 per day (only a slow loop, not wrong output).
- **Impact:** Two cashiers in the same branch, selling to different customers (so the customer lock does not serialize them), compute the same next number. The second INSERT hits the unique key and the whole sale rolls back with a 500. The 4-hex-char expense number has about a 1/65k collision chance per pair per day, with the same rollback effect.
- **Recommendation:** Keep a per-store `document_sequences` row (store_id, type, date) locked with lockForUpdate inside the transaction, or catch the unique violation and retry. Generate expense numbers through the same sequencer.

### 39. [MEDIUM] No server-side price floor: unit_price and line discounts come from the client unchecked
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:72`
- **Effort:** M
- **Evidence:** confirmInvoice uses `(string)$line['unit_price']` and `discount_amount` exactly as sent. Validation is only `numeric|min:0`. CustomerPricingHelper is used only by GetCustomerLastSoldPriceAction, not by invoice creation. There is no min-price, cost floor or price-override permission check.
- **Impact:** Any user with pos.access can post unit_price 0.000, or a line discount equal to the line total, and take goods out of stock for free without a manager override. This is a common shrinkage and fraud vector that a sellable retail SaaS needs to control.
- **Recommendation:** Resolve the expected price on the server (item, store custom_selling_price or the customer tier). Require a `pos.override_price` or discount-limit permission when the submitted price or discount differs beyond a configured threshold. Store the original price for auditing.

### 40. [MEDIUM] updateInvoice and deleteInvoice have no routes but are unsafe: they hard-delete StockMovement rows, deduct stock on cancelled invoices, and duplicate expenses
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:368`
- **Effort:** M
- **Evidence:** Callers: deleteInvoice is only called from tests/Feature/InvoiceServiceTest.php:201 and :257, and updateInvoice has no callers. routes/api.php has no PUT or DELETE /invoices route, and UpdateInvoiceRequest is unused. Both hard-delete `StockMovement::where(source_type, Invoice)->where(source_id)->delete()` (update lines 368-370, delete lines 637-639). The StockMovement model has no SoftDeletes, so the rows, including the reversal movements just written, are physically removed. Payment, Invoice and InvoiceItem use SoftDeletes, so `Payment::...->delete()` (lines 542 and 634) is a soft delete, contrary to the lead's note. The customer balance is recomputed (lines 574-579 and 648-650). updateInvoice runs its reversal only `if status === 'confirmed'` (line 348) but always deducts the new lines (line 404) and never changes status. Its expense cleanup (lines 438-443) soft-deletes AdditionalExpense and Payment by payment_id (never set), but not the Expense rows created at lines 168 and 479.
- **Impact:** These paths are dormant today. If someone wires up 'edit invoice' or 'delete invoice', then: (1) the stock ledger loses history and stock_before/stock_after no longer chain, so audits and FIFO or valuation reconstructions break; (2) editing a cancelled invoice deducts stock while the invoice stays cancelled and outside the balance; (3) each edit adds another treasury Expense for the same shipping fee. Deleting a confirmed invoice also violates the rule against physically deleting approved documents.
- **Recommendation:** Do not expose delete; cancel is the only path for confirmed invoices. For edits, follow cancel-and-reissue (or reverse with compensating movements) and never delete StockMovement rows. Reject edits on non-confirmed invoices, and reverse the generated Expense rows. Remove or rewrite deleteInvoice and its tests so they assert soft cancellation.

### 41. [MEDIUM] Cancelling an invoice after a partial return restocks the returned quantity a second time
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:294`
- **Effort:** S
- **Evidence:** cancelInvoice adds back every invoice line's full quantity (lines 294-300) and does not look at returns for that invoice. Because returns never store invoice_id (see the returns finding), it cannot even detect them.
- **Impact:** Sell 10, return 4 (stock +4), then cancel the invoice (stock +10). Stock is overstated by 4, and the return credit to the customer stays as well.
- **Recommendation:** After linking returns to invoices, either block cancelling an invoice that has active returns or reverse only the net quantity (sold - returned) and void the returns in the same transaction.

### 42. [MEDIUM] WAC only updates on purchases: deposits, adjustment-ins and returns don't blend cost, and depositStock overwrites cost_price
- **Location:** `D:\projects\sroor\backend\app\Services\StockService.php:201`
- **Effort:** M
- **Evidence:** The WAC formula is in PurchaseService::calculateWeightedAverageCost (lines 454-471). It is correct and guards zero/negative stock. It is only called on purchase create and restore. depositStock sets cost_price = costPrice and leaves weighted_avg_cost unchanged (lines 201-203). AdjustItemStockAction stock_deposit_in accepts unit_cost but only writes it to the movement. Sales returns re-add stock at the current cost. WAC is a single item-level value shared by all stores. Movements written by deductStock and by transfers record unit_cost = item->cost_price (last purchase cost, StockService:89, StockTransferService:111,127), while the invoice uses WAC (InvoiceService:82-84).
- **Impact:** Opening balances or manual receipts at a different cost never affect COGS. The stock-movement ledger values outflows at last-purchase cost, which doesn't match invoice COGS, so inventory valuation from movements and from invoices diverge. Purchase cancellation also doesn't unwind the WAC.
- **Recommendation:** Centralise inbound costing in StockService::addStock (recompute WAC under the existing item lock for purchase, deposit and adjustment-in) and record unit_cost = WAC on outbound movements. Decide explicitly whether WAC is global or per store and document it.

### 43. [MEDIUM] stock_before and stock_after mean different things depending on the code path
- **Location:** `D:\projects\sroor\backend\app\Services\StockTransferService.php:109`
- **Effort:** S
- **Evidence:** StockService::deductStock and addStock record item-global before/after (StockService:77-78, 140-141). Transfers record store-level before/after (StockTransferService:109-110, 125-126, 223-239), and so does AdjustItemStockAction (51-52). All rows sit in the same stock_movements table with a store_id. Under concurrency, the values are internally consistent because the item row is locked first in every path.
- **Impact:** A per-store or per-item running ledger can't be rebuilt or reconciled from stock_movements, because consecutive rows for the same item mix two bases. Audit reports that show 'balance after' are wrong for one of the two families.
- **Recommendation:** Standardise on store-level before/after (stock is per store) for all movements, optionally adding item-level columns, and fix it in StockService.

### 44. [MEDIUM] Rows are locked in request order instead of a sorted order, so deadlocks are possible
- **Location:** `D:\projects\sroor\backend\app\Services\StockTransferService.php:54`
- **Effort:** S
- **Evidence:** Items are locked one by one as the loop over $data['items'] proceeds (StockTransferService:54-63, InvoiceService:58-59, ReturnService:63-64, PurchaseService:66-67). Nothing sorts the item ids, although the money-stock rules (section 3) require a consistent lock order.
- **Impact:** Two concurrent documents with items [A,B] and [B,A] can deadlock on MySQL. One transaction is rolled back with a 500 error at the POS during busy hours. Data stays consistent, but sales fail.
- **Recommendation:** Sort the lines by item_id (or pre-lock all items with whereIn()->orderBy('id')->lockForUpdate()) before processing.

### 45. [MEDIUM] The blend quote and the blend invoice disagree: cardamom is hardcoded, never invoiced or deducted, and kg conversion assumes the unit
- **Location:** `D:\projects\sroor\backend\app\Actions\Blends\CalculateBlendCostAction.php:61`
- **Effort:** M
- **Evidence:** The quote adds cardamom at hardcoded 1.5/2.5 EGP per gram using float math (lines 61-67), and uses (float), round() and percentages throughout (16, 31-34, 71). CreateBlenderInvoiceAction writes cardamom only into the notes string (122-124), so it is never billed or deducted from stock. Every component is converted with bcdiv(grams,'1000',4), which assumes the item unit is kg, and the scale-4 quantity is later truncated to scale 3 by bcsub/bcmul in StockService and InvoiceService. The invoice itself is created through InvoiceService::confirmInvoice, so the transaction and item locks are present.
- **Impact:** The customer is quoted a price that includes cardamom but is invoiced without it, and cardamom stock is never consumed. Items stocked in grams or pieces would be deducted 1000 times too little. The truncation leaves sub-gram drift. The hardcoded EGP prices don't fit a generic multi-tenant SaaS.
- **Recommendation:** Make the add-on a real item line (a configurable item, not a constant), compute the quote with bcmath strings, convert using the item's unit, and round once at scale 3 before passing quantities on.

### 46. [MEDIUM] Purchase returns are valued at a client-supplied price and are not bounded by the purchased quantity
- **Location:** `D:\projects\sroor\backend\app\Services\ReturnService.php:137`
- **Effort:** M
- **Evidence:** unit_price is required from the client (StoreReturnRequest:28), and purchase_id is dropped by validation. There is no check against purchase_items quantity or cost. The supplier balance is recomputed with an inline copy of SupplierBalanceService's formula (lines 164-170) instead of calling the service.
- **Impact:** A user can reduce what the business owes a supplier by any amount through an inflated return price. Duplicating the formula risks drift when the service changes.
- **Recommendation:** Link purchase returns to purchase lines, cap the quantity at purchased - already returned, value the return at the purchase line's landed cost, and call SupplierBalanceService::updateBalance.

### 47. [MEDIUM] Purchase discount is not allocated into landed cost, and a zero WAC fallback is wrong because '0.000' is truthy
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:120`
- **Effort:** M
- **Evidence:** Line 105: landedUnitCost = baseCost + allocated expense, with no share of discount_amount. The discount reduces net_total only (line 186). Line 120: currentWac: (string)($item->weighted_avg_cost ?: $item->cost_price). weighted_avg_cost is cast 'decimal:3', so the value is the string "0.000", which is truthy, and the fallback to cost_price never runs. StockService::depositStock (opening balance) sets cost_price but not the WAC. discount_amount is only min:0, so a discount larger than the subtotal gives a negative net_total. All of these are fixed on main by d08edc92 and missing here.
- **Impact:** Inventory is valued at gross cost while the supplier is owed the net amount, so COGS is overstated by the discount. Items with WAC 0 and existing stock get their WAC diluted toward 0 on the next purchase, so profit is overstated. Negative-total purchases are possible.
- **Recommendation:** Allocate the discount pro-rata by value into the line landed cost. Use bccomp(weighted_avg_cost, '0', 3) > 0 instead of ?:. Validate discount_amount < subtotal. Cherry-pick or port d08edc92 to the SaaS branch.

### 48. [MEDIUM] Supplier statement has no opening balance for date ranges, orders by creation time instead of document date, and returns floats
- **Location:** `D:\projects\sroor\backend\app\Services\SupplierBalanceService.php:115`
- **Effort:** M
- **Evidence:** getSupplierLedger filters by from/to date but starts $runningBalance at '0.000'. No pre-period balance is computed, and the supplier opening balance is ignored. Entries are sorted by created_at timestamp (line 116) while they are filtered and displayed by purchase_date/payment_date, and there is no tie-breaker. Cancelled purchases are excluded (status='confirmed') and their payments drop out through the soft delete. Purchase returns are always included, even for cancelled purchases. Treasury-paid PAY-EXP rows appear as supplier payments. Values are cast to (float) (lines 130-141), and GetSupplierStatementAction re-adds the floats with bcadd((string)float).
- **Impact:** A statement filtered to one month shows a running balance that starts at 0 and does not match current_balance. Backdated documents appear out of order with misleading running balances. Supplier reconciliations sent to vendors will be wrong.
- **Recommendation:** Compute the opening balance as the sum of all credits minus debits before from_date (plus the stored opening balance). Sort by (document date, created_at, id). Keep the amounts as strings in the API. Exclude non-supplier payment kinds. Add tests for a mid-period range and for backdated documents.

### 49. [MEDIUM] No store-access check on purchases: any user can post to, read or cancel purchases of any branch
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\PurchaseController.php:124`
- **Effort:** M
- **Evidence:** store() takes the store from the raw X-Store-Id header or the store_id input. ApiTokenAuth (lines 83-86) only copies the header into the session and validates neither access nor existence. show() and cancel() use Purchase::findOrFail($id) with no store filter. index() accepts X-Store-Id: all from any user. None of these routes have a StoreAccess middleware (routes/api.php lines 79-83).
- **Impact:** A cashier limited to branch A can add stock to branch B, see B's purchases and costs, or cancel B's purchases (which reverses B's stock), all inside the same tenant. The second isolation layer is broken for purchases.
- **Recommendation:** Validate that the X-Store-Id store exists and that the user may access it (central middleware). Scope show/cancel by the accessible store ids. Restrict 'all' to users with a cross-store permission. Add isolation tests.

### 50. [MEDIUM] An on-account supplier payment never settles any purchase, so purchase remaining_amount and payment_status drift from the supplier balance
- **Location:** `D:\projects\sroor\backend\app\Actions\Suppliers\PaySupplierAction.php:23`
- **Effort:** M
- **Evidence:** PaySupplierAction calls recordSupplierPayment without purchase_id, so no purchase's paid_amount, remaining_amount or payment_status is updated. Overpayment beyond the supplier balance is allowed (only amount min:0.01). PurchaseController::index sums remaining_amount to build 'unpaid_total'.
- **Impact:** After paying a supplier in full from the supplier screen, every purchase still shows unpaid and the purchases 'unpaid_total' KPI is inflated. The two payables figures the user sees disagree.
- **Recommendation:** Allocate on-account payments to open purchases FIFO inside the same transaction (locking them in id order), or make unpaid_total derive from supplier balances. Decide on and enforce an overpayment policy.

### 51. [MEDIUM] closeShift does not lock the shift: a concurrent double close overwrites the closing figures
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:159`
- **Effort:** S
- **Evidence:** CloseShiftAction reads the open shift outside any transaction (line 23). closeShift opens DB::transaction but never re-reads it with lockForUpdate or re-checks status (lines 161-177), then unconditionally updates status, closed_at, actual_cash_balance and cash_difference. The Telegram call (line 199) also runs inside the transaction, which goes against the 'no external I/O inside a transaction' rule.
- **Impact:** A double-tapped close, or two devices closing the same shift, both succeed. The last write wins on actual_cash_balance and closed_at, and two activity logs and two Telegram alerts are produced. The audited cash count can be silently replaced.
- **Recommendation:** Inside the transaction, re-fetch with CashShift::whereKey($id)->lockForUpdate()->first(), throw a 409 if status !== 'open', and dispatch the Telegram notification with DB::afterCommit or a queued job.

### 52. [MEDIUM] openShift has no transaction, lock or DB constraint, and the shift-number scheme collides after a soft delete
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:44`
- **Effort:** S
- **Evidence:** openShift checks getActiveShift() and then creates the shift with no DB::transaction and no lockForUpdate. cash_shifts has no unique constraint on (store_id, status='open'). shift_number is COUNT(today's non-trashed shifts)+1 (lines 49-50) against a globally unique shift_number column. Two concurrent opens for the same store only fail by accident, with a 500 on the unique-number violation. Once any of today's shifts is soft-deleted (CashShift uses SoftDeletes), the count drops and the next generated number matches an existing live number, so every later open that day fails. The user is taken from Auth::id() ?? 1 and the $userId passed to OpenShiftAction is ignored.
- **Impact:** After a deleted shift, a branch cannot open a shift for the rest of the day (unique violation, 500). Concurrent opens for the same store at the moment of the race return an unexpected server error rather than a clean conflict.
- **Recommendation:** Wrap openShift in a transaction that locks the store row (Store::lockForUpdate) before the active-shift check. Generate numbers from a per-store sequence (or max+1 under lock, including trashed rows). Return a translated 409.

### 53. [MEDIUM] Z-report expenses are always 0 and supplier payouts are not persisted, so the Z-report does not reconcile
- **Location:** `D:\projects\sroor\backend\app\Actions\Shifts\GetShiftZReportAction.php:30`
- **Effort:** M
- **Evidence:** calculateShiftTotals returns total_expenses and total_supplier_paid, but closeShift does not write them (lines 166-177) and cash_shifts has no such columns (create_cash_shifts_table migration). GetShiftZReportAction returns (float)($shift->total_expenses ?? 0). GetDailyJournalAction uses a different formula: status != 'cancelled' instead of = 'confirmed', no refunds, payments by payment_date instead of created_at, and float sums passed into bcadd (lines 37-66). The opening cash it uses is that of the currently open shift, whatever the requested date.
- **Impact:** The printed Z-report shows opening + sales + collections - 0 expenses - refunds, which does not equal the expected_cash_balance it prints, so cashiers and owners cannot reconcile the slip. The shift close, Z-report and daily journal give three different cash figures for the same day.
- **Recommendation:** Add total_expenses and total_supplier_paid columns (new migration) and persist them on close. Have GetDailyJournalAction reuse the same ShiftService calculation. Remove the float sums and keep strings until serialization.

### 54. [MEDIUM] Customer receipt with invoice_id: no ownership, status or overpayment checks
- **Location:** `D:\projects\sroor\backend\app\Services\PaymentService.php:32`
- **Effort:** M
- **Evidence:** StoreCustomerPaymentReceiptRequest validates invoice_id only with exists:invoices,id. PaymentService::recordCustomerPayment locks the invoice but never checks invoice.customer_id == customer_id or that status is 'confirmed'. newPaid = paid_amount + amount is stored without a cap, and remaining is clamped to 0 (lines 34-47). Payments without invoice_id are never allocated to open invoices.
- **Impact:** A receipt can mark customer B's invoice as 'paid' while the money reduces customer A's balance. A cancelled invoice can receive payments. paid_amount can exceed net_total. Invoice-level remaining_amount/payment_status drift from the customer's real balance once on-account collections are used, which breaks aging and 'unpaid invoices' views.
- **Recommendation:** Validate that the invoice belongs to the customer and is confirmed. Reject (409) or explicitly split overpayment into on-account credit. Implement FIFO allocation of on-account receipts across open invoices, or stop exposing invoice-level remaining_amount as authoritative.

### 55. [MEDIUM] No idempotency on customer receipts or collect-payment: a retry or double submit records the payment twice
- **Location:** `D:\projects\sroor\backend\app\Actions\Customers\CollectCustomerPaymentAction.php:25`
- **Effort:** M
- **Evidence:** recordCustomerPayment always creates a new Payment with 'PAY-CUST-' . uniqid(). There is no client request id/idempotency key column or check. git log shows no duplicate-payment or idempotency fix (searched for 'duplicate', 'double', 'idempot', 'dedup'). The PaymentPolicy commit 3b6cb6c2 added authorization only, and that policy is not invoked by PaymentController today.
- **Impact:** A flaky mobile connection or a double tap on 'collect' creates two receipts. The customer's balance drops twice, and the treasury shows cash that was never received.
- **Recommendation:** Accept a client-generated idempotency_key (uuid) on receipt, collect-payment and POS checkout, and store it in a unique column (new tenant migration). Return the existing payment on replay.

### 56. [MEDIUM] Customer statement with from_date starts the running balance at 0 (no carried-forward opening)
- **Location:** `D:\projects\sroor\backend\app\Services\CustomerBalanceService.php:118`
- **Effort:** S
- **Evidence:** getCustomerLedger filters invoices, payments and returns by date and then starts $runningBalance = '0.000' (line 118) without computing the balance before from_date. Rows are filtered by document date but sorted by created_at timestamp, so backdated documents land out of order. GetCustomerStatementAction casts every figure to float for the response. Cancelled invoices are excluded but their PAY-INV payments are still listed as credits; soft-deleted rows are correctly excluded.
- **Impact:** A statement for 'this month' shows a running balance that does not match current_balance, which undermines trust in statements sent to customers. A cancelled cash sale shows up as an unexplained credit.
- **Recommendation:** Compute the opening balance as of from_date - 1 (plus the customer opening balance) and seed the running balance with it. Sort by document date then created_at. Represent cancelled invoices and their refunds explicitly.

### 57. [MEDIUM] Payment methods accepted by validation are invisible in treasury balances
- **Location:** `D:\projects\sroor\backend\app\Enums\PaymentMethod.php:70`
- **Effort:** S
- **Evidence:** PaymentMethod::isActive() returns true only for cash, instapay and e_wallet, and getBalances/getTreasuryReport loop over activeMethods() only. StoreCustomerPaymentReceiptRequest allows visa, bank_transfer, check and other. CollectCustomerPaymentRequest allows 'wallet' and 'bank', which are not even enum values. StoreExpenseRequest allows visa, bank_transfer and check.
- **Impact:** Money collected by card or bank transfer, or expenses paid by bank, never appears in any treasury account or total liquidity. 'wallet'/'bank' payments are orphaned strings that no report can classify.
- **Recommendation:** Validate payment_method with Rule::enum(PaymentMethod::class) limited to the active or tenant-enabled methods, in one shared rule. Include every enum case that has movements in the treasury report.

### 58. [MEDIUM] Treasury cash balance adds the open shift's opening float on top of movement-derived cash
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:83`
- **Effort:** M
- **Evidence:** For the physical cash method, getBalances adds opening_cash_balance of the latest open shift, which is the latest across all stores when storeId is null (lines 83-91). getTreasuryReport does the same to the opening balance even for historical date ranges (lines 247-255).
- **Impact:** The cash balance jumps up when a shift opens and drops when it closes, even though no money moved. With storeId null, it uses an arbitrary branch's float. Historical treasury reports change depending on whether a shift is open right now.
- **Recommendation:** Model the drawer float as an explicit treasury movement (a transfer from the safe to the drawer at open and back at close), or leave it out of the treasury entirely.

### 59. [MEDIUM] Credit limit is not enforced (column does not exist)
- **Location:** `D:\projects\sroor\backend\app\Http\Resources\CustomerResource.php:21`
- **Effort:** M
- **Evidence:** credit_limit appears only in CustomerResource ((float)($this->credit_limit ?? 0)) and PopulateRealisticTenantDataCommand. No tenant migration creates the column, it is not in Customer::$fillable, and InvoiceService::confirmInvoice does no limit check for credit/partial sales.
- **Impact:** Wholesale tenants cannot cap a customer's debt. The UI may display a limit of 0 suggesting a feature that does not exist, and the demo-data command would fail on a real tenant DB.
- **Recommendation:** Product decision: either add credit_limit DECIMAL(12,3) and enforce it inside confirmInvoice after locking the customer (bccomp(current_balance + remaining, limit)), with a permission-gated override, or remove the field from the resource.

### 60. [MEDIUM] return_items has no cost snapshot, and sales returns re-enter stock at the last purchase price instead of the original sale cost
- **Location:** `D:\projects\sroor\backend\database\migrations\tenant\2026_08_08_160008_create_returns_and_items_tables.php:30`
- **Effort:** M
- **Evidence:** return_items has only quantity, unit_price and total_price (lines 30-32). There is no cost_price column. ReturnService::createSalesReturn calls addStock(unitCost: $item->cost_price, ...) instead of the original invoice line's cost_price, and it loads $invoice without lockForUpdate or any check that the returned quantity does not exceed what was sold.
- **Impact:** Return COGS cannot be reversed accurately, so any profit report that tries to net returns has to fall back to current cost. The stock movement unit_cost for the return is wrong, and a customer can return more than they bought.
- **Recommendation:** Add a new tenant migration (guarded with Schema::hasColumn) that adds return_items.cost_price decimal(12,3). Fill it from the matching invoice_items.cost_price, pass that value as unitCost, and validate the returned quantity against the quantity sold minus earlier returns.

### 61. [MEDIUM] Trash endpoints in routes/api.php have no permission middleware, and the controller check passes any admin and has no audit log
- **Location:** `D:\projects\sroor\backend\routes\api.php:191`
- **Effort:** S
- **Evidence:** api.php:191-193 registers /trash, /trash/{type}/{id}/restore and /trash/{type}/{id}/force inside the ApiTokenAuth group only, while routes/tenant.php:230-232 adds can:trash.access. TrashController checks `$user && !$user->hasRole('admin') && !$user->can('trash.access')` inline. TrashPolicy exists but is unused. Neither action writes to AuditLogService. Exception messages are hardcoded Arabic (ForceDeleteTrashRecordAction:29, RestoreTrashRecordAction:29), and the controller uses `__('common.restored_success') ?: 'literal'` fallbacks.
- **Impact:** Permanent deletion of financial records (expenses, returns) is gated only by an inline check and leaves no audit trail of who purged what. That is unacceptable for a sellable accounting SaaS and blocks any later dispute resolution. It is tenant-scoped because the models live on the tenant connection, so this is not a cross-tenant problem.
- **Recommendation:** Apply can:trash.access (or a separate trash.force_delete permission) at the route level and authorize through TrashPolicy. Log every restore and force delete through AuditLogService. Move messages to lang/ar and lang/en.

### 62. [MEDIUM] Inventory adjustments and write-downs never reach any P&L or COGS figure
- **Location:** `D:\projects\sroor\backend\app\Services\StockService.php:295`
- **Effort:** S
- **Evidence:** Stock adjustments create 'stock_adjustment_in' and 'stock_adjustment_out' movements with unit_cost (StockService:295-311). A grep for stock_adjustment shows it only in AdjustItemStockAction, GetItemMovementsAction, AdjustStockRequest, ExportService and StockService. No report action or profit service reads it. Transfers are correctly excluded, since they don't affect P&L.
- **Impact:** Shrinkage, spoilage (common in by-kilo goods) and count losses never appear as an expense. Gross and net profit are overstated by the cost of lost stock, and the gap only shows up as unexplained inventory valuation drops.
- **Recommendation:** Add an 'inventory shrinkage/adjustments' line to P&L: SUM(quantity * unit_cost) of stock_adjustment_out minus stock_adjustment_in for the period and store.

### 63. [MEDIUM] P&L and ABC caches are never invalidated, and their keys are not tenant-scoped while CacheTenancyBootstrapper is disabled
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:29`
- **Effort:** S
- **Evidence:** The cache key is "erp_pnl_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}", but clearCache() forgets only 'erp_pnl_all' or 'erp_pnl_{id}', which never match. InventoryAnalyticsService:14-21 versus :29 has the same mismatch. A grep shows neither clearCache is ever called. config/tenancy.php:36 has CacheTenancyBootstrapper commented out, and the keys contain no tenant id. CACHE_STORE defaults to database (.env.example:41).
- **Impact:** ABC analysis inside /api/v1/reports/inventory shows stale data for up to 15 minutes after sales. More seriously, with a file or redis cache driver, or if the database cache store is first resolved on the central connection (queue workers, long-lived processes), tenant B can be served tenant A's cached ABC or P&L for the same date range. That would be a cross-tenant financial data leak. Whether it happens depends on the production cache driver, which I did not verify.
- **Recommendation:** Enable CacheTenancyBootstrapper (with a taggable store) or prefix keys with tenant('id'). Fix clearCache to use tags or a versioned key, and call it after invoice, return and purchase commits.

### 64. [MEDIUM] Expenses can be force-deleted through the trash, permanently removing cash-out records that treasury balances are derived from
- **Location:** `D:\projects\sroor\backend\app\Actions\Trash\ForceDeleteTrashRecordAction.php:27`
- **Effort:** M
- **Evidence:** 'expenses' => Expense::onlyTrashed()->findOrFail($id) is followed by forceDelete(). TreasuryService derives balances on read from SUM(expenses.amount) (lines 217-220, 270-274), so soft delete and restore stay internally consistent. Expenses have no status or approval flag, and DeleteExpenseAction:17-18 simply soft-deletes. CreateExpenseAction:62-64 numbers expenses with count()+1 per day without a lock, and Store::getMainStore() can be a silent fallback.
- **Impact:** Deleting an expense silently raises the opening and current cash balance of past periods, so closed shifts and earlier reports no longer reconcile. Force delete makes this permanent and untraceable. Concurrent creates can produce duplicate expense_numbers.
- **Recommendation:** Treat posted expenses as documents: void them with a reason and an audit entry instead of deleting, and disallow force delete. Generate expense numbers under a lock or with a sequence table.

### 65. [LOW] Stock movements are hard-deleted on invoice edit, invoice delete and purchase delete, including the reversal movements just created
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:637`
- **Effort:** M
- **Evidence:** StockMovement does not use SoftDeletes (it is absent from the SoftDeletes model list). deleteInvoice first adds 'cancellation_in' movements with source = the invoice (lines 615-627), then runs StockMovement::where('source_type', Invoice::class)->where('source_id', id)->delete() (lines 637-639). That wipes both the original 'sales_out' and the reversal just written. updateInvoice:367-370 does the same, and PurchaseService::deletePurchase:437-439 deletes the purchase_in and purchase_cancel_out rows. deleteInvoice's permission check is a hardcoded hasRole('admin') with an Arabic exception (line 605-606). Routes: tenant.php:93 can:invoices.delete. tenant.php:94 points /invoices/{id}/restore at InvoiceController::restore, which does not exist.
- **Impact:** After an invoice edit or delete, the item movement card and any rebuild from movements no longer reconcile with stock_before/stock_after chains: there are gaps and the audit trail is gone. Store stock totals still end up right, but nobody can prove why. A fraudulent cashier-admin can edit invoices without a trace in the stock ledger. The restore route returns 500.
- **Recommendation:** Never delete stock movements. Keep the original and reversal rows (reversal is already done), and use cancel status instead of delete for confirmed invoices and purchases. Remove the broken restore route or implement it through a transactional action that re-validates stock.
- **Verification:** The code does what the finding says, but no production code path can reach it, so it is a latent defect and not high severity.

What I confirmed in the code:
- backend/app/Models/StockMovement.php does not use SoftDeletes. Invoice, InvoiceItem and Purchase do.
- InvoiceService::updateInvoice (lines 348-370) and deleteInvoice (lines 615-639) first write 'cancellation_in' movements with the invoice as the source. They then run StockMovement::where(source_type, source_id)->delete(), which hard-deletes the original sales_out rows and the reversal rows just written.
- PurchaseService::deletePurchase (lines 433-439) hard-deletes the purchase movements in the same way.
- deleteInvoice has a hardcoded hasRole('admin') check with an Arabic exception message (lines 605-606).

Why it is overstated:
- Outside the service file itself, nothing in app/ calls updateInvoice, deleteInvoice or deletePurchase. The only references are tests/Feature/InvoiceServiceTest.php (lines 201 and 257) and stale compiled Livewire views in storage/framework/views from the removed Livewire UI.
- routes/tenant.php lines 90-94 map invoices.edit, invoices.update, invoices.destroy and invoices.restore to Api\InvoiceController methods named edit, update, destroy and restore. None of these exist: the controller only has index, show, store and cancel. So all four routes fail with an error (500) before any service code runs, not only the restore route.
- routes/api.php (lines 103-106) exposes only index, show, store and cancel for invoices. For purchases, neither api.php nor tenant.php has a delete route; only cancel is exposed.
- Live edit and delete flows therefore cannot currently erase the stock ledger, and the claimed exploit (a cashier-admin silently editing invoices) cannot happen through HTTP today.

Remaining real risk:
- This is dead code that breaks the no-hard-delete and audit-trail rules. Anyone who wires these routes back up would bring the ledger wipe back with them.
- The four routes above are broken and return 500.
- The test suite calls deleteInvoice, which treats the hard-delete behaviour as expected.

Severity is lowered to low.

### 66. [LOW] Float use in POSInvoiceDTO and the discrete-unit check; percentage discount truncates instead of rounding
- **Location:** `D:\projects\sroor\backend\app\DTOs\POSInvoiceDTO.php:17`
- **Effort:** S
- **Evidence:** POSInvoiceDTO declares `public readonly float $discountValue` and `float $paidAmount`, converted with `(float)` and then `(string)$this->paidAmount`. The discrete-unit check at InvoiceService.php:68 uses `fmod((float)$qty, 1.0)`. Line 120 computes `bcdiv(bcmul($subtotal,$discountValue,4),'100',3)`, which truncates. All persisted totals in confirm, update and cancel otherwise use bcmath at scale 3. number_format((float)) appears only in log descriptions (line 268), WhatsApp text (GetInvoiceDetailsAction) and the index summary (InvoiceController total_sales cast to float for display).
- **Impact:** Small amounts cast float→string can become E-notation (e.g. '1.0E-5'), which makes bcmath throw a ValueError (500). Percentage discounts lose up to 0.001 per invoice through truncation. The financial impact is small, but these break the bcmath-only rule. The POS DTO also drops discount_value because the POS request never validates it.
- **Recommendation:** Change the DTO props to string with `(string)` casts at the boundary. Do the discrete-unit check with bcmod or by comparing bcadd($qty,'0',0) to $qty. Round half-up explicitly (bcadd with ±0.0005 at scale 4, then truncate to 3) for percentage discounts.

### 67. [LOW] ConcurrencyTest is sequential, and its post-exception integrity assertions never run
- **Location:** `D:\projects\sroor\backend\tests\Feature\ConcurrencyTest.php:101`
- **Effort:** S
- **Evidence:** The test calls `$this->expectException(Exception::class)` and then confirmInvoice. The code after it (`$this->item->refresh(); ... stock remains 0.250`) is unreachable because the exception ends the test method. Both sales run one after the other on sqlite :memory:, so no real lock contention is exercised. I ran `php artisan test --filter="InvoiceServiceTest|ConcurrencyTest|PosApiTest|InvoiceApiTest|InvoicesAndPosApiTest"` and all 28 tests passed (149 assertions). No test covers idempotency, cancel after a return, store isolation for invoices, or the real PosView payload.
- **Impact:** The suite gives false confidence that rollback integrity and concurrency are verified.
- **Recommendation:** Wrap the second sale in try/catch and assert the stock, movements and invoice count afterwards. Add a MySQL-backed concurrency test (two processes or connections). Add tests for double submit with the same client_uuid, cancel after a partial return, a store without StoreStock, and a split-tender POS payload.

### 68. [LOW] Undefined variable $purchase in createPurchaseReturn
- **Location:** `D:\projects\sroor\backend\app\Services\ReturnService.php:118`
- **Effort:** S
- **Evidence:** `$storeId = $data['store_id'] ?? ($purchase?->store_id ?? ...)`, but $purchase is never assigned in this method.
- **Impact:** When store_id is null (no X-Store-Id, no current store and no main store), PHP raises an undefined-variable warning, which Laravel turns into an ErrorException (500). This path is unlikely today because the controller usually supplies a store.
- **Recommendation:** Load the purchase (Purchase::find($purchaseId)) before resolving the store, or remove the reference.

### 69. [LOW] Transfer and return numbers are generated without a lock
- **Location:** `D:\projects\sroor\backend\app\Services\StockTransferService.php:272`
- **Effort:** S
- **Evidence:** generateUniqueNumber reads the last number with an unlocked query and then increments it (StockTransferService:272-298, ReturnService:183-209). Unique indexes exist on transfer_number and return_number.
- **Impact:** Two concurrent creations can pick the same number. The unique index prevents duplicates, but the loser gets a 500 and the whole document rolls back.
- **Recommendation:** Use a locked sequence or counter row per prefix/branch, or retry on unique violation.

### 70. [LOW] Cancel/restore errors are generic exceptions with Arabic literals and surface as HTTP 500
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:252`
- **Effort:** S
- **Evidence:** cancelPurchase and restorePurchase throw \Exception with hard-coded Arabic strings (for example lines 252, 264, 276). bootstrap/app.php has no renderer for generic Exception, and PurchaseController::cancel does not catch it. The controllers also use the fallback `__('...') ?: 'literal'` and the default reason 'إلغاء من النظام'. payment_method is missing from StorePurchaseRequest, so validated() drops it and inline purchase payments are always recorded as 'cash'.
- **Impact:** A normal business conflict ('stock already sold') returns 500 and hides the message in production. The en locale gets Arabic text. Inline payments made by Instapay or wallet are booked to the cash drawer, which skews the per-method treasury balances.
- **Recommendation:** Throw a domain exception that maps to 409 with __() keys in lang/ar and lang/en. Add payment_method to StorePurchaseRequest with the PaymentMethod enum values.

### 71. [LOW] deletePurchase hard-deletes stock movements (dead code today)
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:437`
- **Effort:** S
- **Evidence:** StockMovement::where(source_type Purchase, source_id)->delete(). StockMovement does not use SoftDeletes, so this is a physical delete that also removes the cancel movement just created. grep shows no callers of deletePurchase in app/.
- **Impact:** If this is wired up later, the stock audit trail for the purchase is destroyed, which breaks the no-physical-delete rule.
- **Recommendation:** Remove the method, or make it cancel plus soft delete only. Never delete movements.

### 72. [LOW] Lock ordering and number generation
- **Location:** `D:\projects\sroor\backend\app\Services\PurchaseService.php:68`
- **Effort:** S
- **Evidence:** Items are locked in request order, not sorted, and the supplier is locked last (inside updateBalance), while recordSupplierPayment and createPurchaseReturn lock the supplier first. generateUniqueNumber (lines 473-499) reads the last number without a lock; the unique index on purchase_number turns a race into an exception rather than a duplicate.
- **Impact:** Concurrent purchases, sales or returns on overlapping items can deadlock, and concurrent purchases can collide on the number. Either way the transaction rolls back with a 500 and nothing is corrupted.
- **Recommendation:** Sort item ids before locking, lock the supplier first in createPurchase, and generate numbers under a locked per-store sequence row.

### 73. [LOW] Smart reorder: division by zero and global stock in per-store mode
- **Location:** `D:\projects\sroor\backend\app\Services\ReorderAssistantService.php:56`
- **Effort:** S
- **Evidence:** bcdiv($qtySold, (string)$analysisDays, 3) with analysis_days taken unclamped from the request (PurchaseController::smartReorder), so 0 throws DivisionByZeroError. currentStock uses item->current_stock (global) even when storeId filters sales. Display-only float conversions remain: (int)floor((float)bcdiv(...)) on line 63, number_format((float)...) on line 75, and (float)total_estimated_cost in the Action. The `weighted_avg_cost ?:` fallback has the '0.000' truthy issue here too.
- **Impact:** analysis_days=0 returns a 500. Per-branch reorder suggestions are wrong for multi-store tenants. The float use has no money effect.
- **Recommendation:** Clamp analysis_days and target_cover_days to at least 1 and a sane maximum. Use StoreStock quantity when storeId is set.

### 74. [LOW] TreasuryService::transfer checks the balance outside the transaction with nothing to lock (currently unreachable)
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:142`
- **Effort:** M
- **Evidence:** The sufficiency check calls getBalances() before DB::transaction (lines 142-151). Balances are aggregates with no lockable row, and the check uses the balance as of the transfer date (which ignores later outflows when backdated) plus the store-unscoped Payment sums. Amount and fee use bcmath correctly. A negative fee is silently set to 0, and messages are hardcoded Arabic. There is no route, controller or action calling transfer() (searched app/ and routes/), so the feature is currently unreachable in the SPA branch.
- **Impact:** No impact today. Once wired, two concurrent transfers could both pass the check and overdraw an account, and branch-scoped checks would use other branches' collections.
- **Recommendation:** Before exposing it, add a treasury_accounts row per store and method (or a per-store advisory lock row), lock it with lockForUpdate inside the transaction, re-check against the current balance, and move it into a TransferTreasuryAction with a FormRequest and translated errors.

### 75. [LOW] Expense create, update and delete: treasury effect is implicit; no period/shift lock; number generation races
- **Location:** `D:\projects\sroor\backend\app\Actions\Expenses\CreateExpenseAction.php:23`
- **Effort:** S
- **Evidence:** Expenses affect the treasury only because TreasuryService sums the expenses table. Delete is a soft delete (Expense uses SoftDeletes), so the sums reverse automatically and a Trash restore re-applies it. UpdateExpenseAction can change the amount, date or method of an expense inside an already-closed shift with no check. expense_number = count(today by created_at, non-trashed)+1 without a lock against a unique column, so concurrent creates, or creates after a soft delete, hit a unique violation. The store comes from an unvalidated X-Store-Id.
- **Impact:** Editing or deleting an old cash expense silently changes past treasury balances and makes closed shifts disagree with the treasury. Occasional 500s when numbering collides.
- **Recommendation:** Block edits and deletes of expenses dated inside a closed shift (or require a reversing entry). Generate numbers under a lock, including trashed rows, and validate store access.

### 76. [LOW] Hardcoded Arabic user-facing messages and float casts in the financial responses
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\ShiftController.php:122`
- **Effort:** M
- **Evidence:** ShiftController, PaymentController, TreasuryService, ShiftService and InvoiceService build user messages with Arabic literals and number_format((float)...) plus a hardcoded 'ج.م' currency. GetShiftZReportAction, GetDailyJournalAction, GetCustomerStatementAction and TreasuryController return money as (float). ExpenseController uses __('x') ?: 'literal' fallbacks.
- **Impact:** This breaks the English locale and the multi-currency SaaS positioning. Float JSON can show 0.1+0.2-style artefacts in clients.
- **Recommendation:** Move the text to lang/ar and lang/en keys and return money as decimal strings.

### 77. [LOW] Report responses cast bcmath totals to PHP float, and dashboard totals are summed with Collection::sum (float addition)
- **Location:** `D:\projects\sroor\backend\app\Services\DashboardAnalyticsService.php:33`
- **Effort:** S
- **Evidence:** DashboardAnalyticsService:33,50,63,110-114 use $collection->sum('net_total'), which adds decimal strings with native +, then cast to (string). The results then feed bcdiv for basket size (line 36-37) and payment-method breakdowns. GetProfitLossReportAction:58 casts customer debt with (float). Lines 101-115, plus every field in GetItemsProfitability, GetInventoryValuation and GetStoresComparative, are returned as (float). InventoryAnalyticsService:158-159 does `(float)bccomp(...) > 0 ? (float)bcmul(...) : 0.0`, which works only by precedence accident and is used just for the A/B/C threshold. GetInventoryValuationReportAction:93/95 filters in/zero stock with float comparison. In the tenant schema itself, every money and quantity column is decimal(12,3), and model casts are decimal:3.
- **Impact:** At decimal(12,3) magnitudes, float sums can produce visible errors such as 0.1+0.2, so a sum may come back as '1234.5600000001' and a bcdiv on it would carry the error. Report totals sent as JSON floats can drift in the last digit across thousands of invoices, and exports could disagree with on-screen totals. No stored balances are affected.
- **Recommendation:** Aggregate with SQL SUM on the query builder (->sum() on the query, not on the collection) or with bcadd loops. Return money as strings in API payloads and let the frontend format them. Change the ABC threshold to bccomp against '80.00' and '95.00'.

### 78. [INFO] Lead correction: deleteInvoice soft-deletes payments; it does not hard-delete them
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:636`
- **Effort:** S
- **Evidence:** Payment uses the SoftDeletes trait (Payment.php line 11), so Payment::where('invoice_id', ...)->delete() sets deleted_at. The deleted payment still disappears from every treasury, shift and balance sum, retroactively changing past cash figures. The hardcoded hasRole('admin') check and the Arabic exception remain as the lead noted.
- **Impact:** The audit trail rows survive in the DB, but deleting an invoice whose cash was collected in a closed shift silently lowers historical treasury balances.
- **Recommendation:** Replace delete with cancel plus an explicit refund/reversal entry for paid invoices.

### 79. [INFO] Precision schema audit: the tenant schema is clean, while central plan and subscription money uses decimal(10,2)
- **Location:** `D:\projects\sroor\backend\database\migrations\2019_09_15_000005_create_plans_and_features_tables.php:17`
- **Effort:** S
- **Evidence:** All 59 money and quantity columns in database/migrations/tenant are decimal(12,3). There are no float, double or other precisions; the only integers are framework or sort-order columns (jobs, cache, sort_order, pos_sort_order, pos_sales_count). Central: plans.price_monthly and price_yearly are decimal(10,2), subscriptions.amount is decimal(10,2), and Plan and Subscription cast them to decimal:2. There are no 'float', 'double' or 'integer' casts on money or quantity columns in any model. StockTransferItem casts only quantity, which matches its schema.
- **Impact:** No tenant data-precision risk. The central billing precision differs from the house rule, but 2-decimal SaaS billing is acceptable if that is intentional.
- **Recommendation:** Document the deliberate exception for central billing, or align it to (12,3) in a new central migration. Keep the existing tenant discipline.

## Open questions
- Is /api/v1/pos/checkout (StorePOSInvoiceRequest and ProcessPOSInvoiceAction) still meant to be used? The live PosView.vue posts to /invoices, and only PosView.legacy.grid.vue and posService.js (which hits a non-existent /pos/invoices) reference the POS endpoint.
- Is 'edit invoice' or 'delete invoice' planned for the SPA? updateInvoice and deleteInvoice and UpdateInvoiceRequest exist but have no routes. They should be redesigned before they are exposed.
- When a cash sale is cancelled, should the paid amount become customer credit, or should a refund be posted to the treasury? The current code implicitly does the first, even for the shared walk-in customer.
- Should selling below zero per store ever be allowed by a tenant setting? StockService currently enforces only the master Item stock when no StoreStock row exists.
- Is MySQL in production on REPEATABLE READ (the default)? That determines how often the unlocked number generation collides on the unique index under concurrent branch sales.
- Is weighted-average cost meant to be global per item or per store? Today it is global, and transfers carry the last purchase cost_price, not WAC, on their movements.
- Is a sales return supposed to refund cash or create store credit? The refund_amount field is accepted but unused, and the shift and customer-balance logic disagree.
- Should negative stock ever be allowed (for example a tenant setting for by-weight shops)? No such setting exists; the rules depend on the code path.
- Does MySQL in production run in strict mode? Scale-4 kg quantities from the blender are inserted into decimal(12,3) columns, and the rounding vs. bcmath truncation behaviour differs.
- Is any production client calling POST /items/{id}/adjust-stock with waste_out today? If so, branches may already hold negative StoreStock rows and items.current_stock values that were resynced from store sums. A read-only reconciliation query (SUM(store_stocks) vs items.current_stock, and store rows < 0) should be run on a backup.
- Commit d08edc92 (WAC fixes, discount allocation, no physical deletion of movements, refusing naive restore) and PR #3 (930250b4) are on origin/main but not on feature/multi-tenant. Is the SaaS branch supposed to be rebased or merged from main? Several findings here are already fixed on main.
- Business policy: when a paid purchase is cancelled, should the supplier become a receivable (keep the payments) or should a refund voucher be required? The current code silently deletes the payments.
- Should paying a supplier more than the open balance be allowed (as an advance), or blocked with a 422?
- Payments have no store_id column, so supplier payments and the PAY-EXP rows cannot be attributed to a branch treasury. Is a per-branch treasury a requirement for the SaaS?
- Are there production tenants with treasury_cash landed expenses or supplier opening balances already recorded? A one-off repair or recompute command would be needed after the fix.
- Business rule for cancelling a paid cash invoice: is the money refunded to the customer (treasury outflow needed) or kept as customer credit? Today it is neither explicitly.
- Should a sales return against a cash invoice refund cash from the drawer, and against a credit invoice only reduce debt? A refund_method field is needed to tell them apart.
- Should TreasuryService::transfer be exposed in the SPA? It has no route on this branch, so it is unclear whether a treasury-transfer screen is planned.
- Is a shift per store (current behaviour) or per cashier/terminal intended? The answer decides the right uniqueness constraint and lock target.
- Do production tenants already hold customers created with a non-zero opening balance? If so, a backfill is needed before fixing updateBalance, because their opening amounts may already have been overwritten.
- Which cache driver does production use (CACHE_STORE)? With file or redis, and CacheTenancyBootstrapper disabled, the un-prefixed erp_abc_* and erp_pnl_* keys are shared across tenants.
- Is ReportPrintController (the only user of ProfitLossService) meant to be routed? No route references it, so ProfitLossService looks like dead code with logic that differs from the API P&L.
- Should sales and purchase returns be cancellable documents with a status (they currently have no status column), and should the trash expose them at all?
- ReturnService::createPurchaseReturn line ~117 references an undefined $purchase when store_id is not supplied. Under Laravel's error handler this would throw an ErrorException. Another sub-agent should confirm this with a test.
- Should store-level P&L allocate central expenses (store_id NULL)? Today they are silently dropped when a store filter is applied, and the dashboard and reports never show them per store.