# 03 — POS page: UI/UX + code audit, design tokens, proposed layout

Scope: `backend/resources/js/views/POS/PosView.vue` (955 lines; the rules file says ~840, it has grown) and everything it pulls in: `Components/POS/*` (22 files, 12 in use), `Composables/usePosOrders.js`, `usePosCheckout.js`, `helpers/posCheckout.js`, `helpers/posCartLines.js`, `useFormatters`, `useConnectivity`, `useDesktopHardware`, `useAudioFeedback`, `Layouts/SpaLayout.vue`, `Components/Navigation/MobileBottomNav.vue`, `resources/css/app.css`, `Composables/useTheme.js`. I also read the API it talks to: `InvoiceController@store` / `StoreSalesInvoiceRequest`, `InvoiceService`, `ItemController@index` / `ItemResource`, `PosController` / `GetPOSBootstrapDataAction`, `StorePosSettingResource`, `PosQuickKeyResource`, and the shift routes.

Method: static reading only. Nothing was edited, built or run, and **nothing was checked in a browser**. Severity reflects impact on a cashier's shift and on money correctness.

Severity scale: **P0** = can sell the wrong thing or book money wrongly. **P1** = large friction or a correctness risk the cashier will hit weekly. **P2** = clear UX/quality defect. **P3** = polish or hygiene.
Effort: **S** < 1 day, **M** 1–3 days, **L** > 3 days (frontend only unless noted).

---

## 0. Snapshot of today's POS

```
SpaLayout (DesktopSidebar forced to mini w-20, no top navbar on /pos; MobileBottomNav still fixed on < md)
└─ PosView.vue  (dir="rtl" hardcoded, 955 lines: fetching, search, debounce, cart math, printing HTML, keymap)
   ├─ POSSkeleton                (first load only)
   ├─ POSHeader                  back, logo+version, store <select>, shift badge, search+dropdown, disabled "menu (coming soon)",
   │                             customer button, retail/wholesale toggle, clear cart
   ├─ <section> flex-1           POSCartTable (#header slot = POSOrderTabs) + POSCheckoutPanel
   ├─ <main> 350/400/440px       POSProductGrid → POSProductButton (24 per page, client-side paging)
   ├─ POSCategorySidebar 48–192px
   └─ modals: POSCustomerModal, POSSuccessModal, POSExpensesModal, POSMultiPaymentModal
```

Endpoints actually consumed: `GET /items?per_page=300`, `GET /items?category_id=&per_page=200`, `GET /items?search=&per_page=30` (debounced 250 ms), `GET /customers?per_page=200`, `GET /customers?search=`, `GET /shifts/current`, `GET /categories?active_only=1`, `POST /customers`, `POST /invoices` (+ `Idempotency-Key`), `GET /invoices/{id}` (Electron print).
Endpoints built for the POS but **not used**: `GET /pos/bootstrap` (returns `is_weighted`, `pos_settings` with scale-barcode config and `max_discount_percent`, `quick_keys`, `default_customer`), `POST /pos/checkout`, `POST /pos/quick-customer`, `GET /pos/last-price`, `GET /pos/quick-keys`. `App\Services\Pos\ScaleBarcodeParser` exists but no route calls it.

What is already good and must be kept:
- Idempotent checkout (`client_uuid` + `Idempotency-Key`, reset when the fingerprint changes): `PosView.vue:553-569, 804-823`.
- Exact scale-3 net/change/payload math in `helpers/posCheckout.js` mirroring `InvoiceService`, and server-owned change on the success modal (`PosView.vue:825-826`).
- Offline honesty: checkout blocked with a visible reason (`POSCheckoutPanel.vue:196-204`, `PosView.vue:784-792`).
- A layout-shaped skeleton for first load (`POSSkeleton.vue`).
- Multi-order tabs persisted locally, debounced + abortable remote search, audio feedback, `data-testid` hooks used by `e2e/flows/pos-*.spec.js` (keep every testid in a refactor).

### Cashier speed today vs. target

| Scenario | Today (actions) | Target with the proposed layout |
|---|---|---|
| A. Scanner, 1 item, exact cash | scan → (if the code is not in the 300 preloaded items: wait, then press Enter again) → F9 → Enter to dismiss success modal = **3–4** | scan → F9 = **2** (non-blocking completion bar) |
| B. Touch, 3 items, customer pays with a 200 note | 3 tile taps + tap cash field + OS keyboard "200" + tap Save + tap "New invoice" = **~9 taps + soft keyboard** | 3 tiles + Pay + "200" quick-bill (completes) = **5 taps, no keyboard** |
| C. 0.5 kg of coffee by weight | not possible from a tile (adds 1); must type 0.5 in a `step=1` field; "−" on 0.5 deletes the line | tile → weight sheet "½ kg" = **2 taps** |
| D. Credit sale to a named customer | customer button → search → pick → Credit → Save → dismiss = **6 + typing** | same count, but Credit is blocked until a named customer is set |

---

## A. Findings

### Summary table

| ID | Sev | Title | Effort |
|---|---|---|---|
| POS-01 | P0 | Barcode scan can add the wrong item or be silently lost | M |
| POS-02 | P0 | Credit / partial sale allowed on the walk-in customer | S (+S backend) |
| POS-03 | P1 | Initial-load failure shows an empty POS with no error or retry | S |
| POS-04 | P1 | POS uses the heavy admin `/items` endpoint (N+1 deletion checks) instead of `/pos/bootstrap` | M (+M backend) |
| POS-05 | P1 | Product grid only shows the locally loaded subset; counts are misleading | M |
| POS-06 | P1 | Selling by weight is not supported in the live POS (weight picker exists but is unused) | M |
| POS-07 | P1 | Discount: 4 fixed % chips only, no amount/custom/line discount, no permission or cap | M (+S backend) |
| POS-08 | P1 | Payment panel: tendered amount is overwritten, partial is confusing, card is hidden, no quick bills | M |
| POS-09 | P1 | Customer picked from remote search shows as "General cash" | S |
| POS-10 | P1 | Shift integration: no gate, no open/close/X-report, stale shift after store switch | M (+S backend) |
| POS-11 | P1 | No recent sales / reprint / return from the POS | M |
| POS-12 | P1 | Phone layout: stacked header, catalog above cart, 56px category strip at the bottom, bottom nav covers checkout | L |
| POS-13 | P1 | Many critical controls are below the 44px touch target | M |
| POS-14 | P1 | Thin-view rule broken (955-line view) plus ~1,600 lines of dead POS code | L |
| POS-15 | P2 | Receipt printing: incomplete, float formatting, unescaped HTML, drawer kicks on credit sales | M |
| POS-16 | P2 | Keyboard map: few shortcuts, not modal-aware, mislabeled, no qty multiplier | S |
| POS-17 | P2 | Held orders: wrong storage scope, hardcoded Arabic title, 16px close button, write per keystroke | M |
| POS-18 | P2 | Price tier and price editing: wholesale = min price, no repricing, customer tier ignored, no guard | M |
| POS-19 | P2 | Store switch inside POS: no confirmation, stale prices/shift, silent `store_id || 1` | S |
| POS-20 | P2 | Clear cart has no confirmation or undo | S |
| POS-21 | P2 | Search dropdown: never closes on outside click, 2-line rows, no a11y semantics | S |
| POS-22 | P2 | Out-of-stock items added with no warning; stock not visible in the cart | S |
| POS-23 | P2 | Hardcoded text, emoji icons and magic money values | S |
| POS-24 | P2 | Forced `dir="rtl"` and physical classes: English renders RTL | S |
| POS-25 | P2 | Accessibility gaps: icon buttons without labels, custom modals without focus trap/Esc, 9–10px text | M |
| POS-26 | P2 | On-primary text hardcoded `text-slate-950`; inconsistent semantic colours | S |
| POS-27 | P2 | Loading states after first paint: spinners/no feedback; `animate-in` classes are no-ops | S |
| POS-28 | P2 | Poor-network/offline: no cached catalog, CDN fonts, no header connectivity indicator | M |
| POS-29 | P2 | Float math in displayed subtotal/discount/line totals vs. exact net | S |
| POS-30 | P2 | Invoice notes not exposed although API and order model support them | S |
| POS-31 | P2 | No permission awareness in the POS UI | S (+S backend) |
| POS-32 | P2 | POS posts to generic `/invoices` instead of hardened `/pos/checkout`; backend hardcoded Arabic error | S (+S backend) |
| POS-33 | P3 | Disabled "Show menu — coming soon" button while F10 toggles the same thing | S |
| POS-34 | P3 | Cart renders two full DOM trees; new line not scrolled into view or highlighted | S |
| POS-35 | P3 | Re-render/persistence cost: deep watcher + JSON.stringify per keystroke, duplicated price helper | S |
| POS-36 | P3 | Success modal blocks the next sale; print-without-change gives no visual confirmation | S |
| POS-37 | P3 | Header spends space on branding/version; customer phone line repeats "General cash" | S |
| POS-38 | P3 | Direct `Swal.fire` and hex colours instead of `helpers/alert.js` | S |

### Details

#### POS-01 — Barcode scan can add the wrong item or be silently lost — P0 — M
- **Evidence**: `PosView.vue:457-472` (local `includes()` on name/code, merged with remote, no exact-match ranking), `PosView.vue:355-400` (remote search debounced 250 ms), `PosView.vue:610-615` (Enter adds `results[highlighted] || results[0]`), `POSHeader.vue:82` (`@keydown.enter.prevent="$emit('select-highlighted')"`), `PosView.vue:714` (only 300 items preloaded). Backend: `ItemController.php:70-74` searches `LIKE %q%` on name/code/notes. There is no barcode column (code doubles as barcode). `ScaleBarcodeParser` is not exposed.
- **Problem**: a wedge scanner types the code and sends Enter within ~50 ms. (a) If the code is not among the 300 preloaded items, the result list is still empty at Enter (the remote call has not fired yet), so the scan does nothing and the dropdown later shows results the cashier must press Enter on again. (b) If the code is a prefix of another code (`123` vs `1234`) or appears inside a product name, the first `includes()` match wins, so **a different item is sold**. (c) Scale labels (embedded weight/price, configured in POS settings, POSB-2) are not parsed at all.
- **Fix**: a `usePosScanner` composable: detect scan bursts (inter-key < 30 ms, Enter-terminated) or treat any Enter on a code-like string as a scan; resolve against an exact `Map(code → item)`; on miss call a new `GET /pos/lookup?code=` immediately (no debounce) that does exact code match + scale-label parsing and returns `{ item, quantity, unit_price }`; never fall back to substring for scans; on miss play the error tone and show "Code X not found" inline while keeping the text selected. Typed (non-scan) search keeps the dropdown but ranks exact code > code prefix > name.
- **Backend gap** (backend-architect): `GET /api/v1/pos/lookup?code=` using `ScaleBarcodeParser` + exact `items.code` (and a future barcode table), store-scoped stock, `pos.access`.

#### POS-02 — Credit / partial sale allowed on the walk-in customer — P0 — S (+S backend)
- **Evidence**: `POSCheckoutPanel.vue:102-126` (Credit/Partial always enabled), `PosView.vue:814-816` (`customer_id: selectedCustomerId` may be `null`), `StoreSalesInvoiceRequest.php:28-44` maps `null` to the walk-in customer; `InvoiceService.php:389-411` has no walk-in check.
- **Problem**: a cashier can book a credit (or partial) invoice on "General cash". This creates an uncollectable receivable on an anonymous account, and it is an easy way to hide cash that was actually taken.
- **Fix**: in the UI, disable Credit/Partial while no named customer is selected; tapping them opens the customer picker with an explanation. Show the customer balance (and credit limit if any) on the customer chip once one is picked.
- **Backend gap**: reject `payment_type in (credit, partial)` when the customer resolves to the walk-in customer (422, translated message).

#### POS-03 — Initial-load failure shows an empty POS — P1 — S
- **Evidence**: `PosView.vue:709-733` (`catch` → `console.error`, then the skeleton is replaced by an empty catalog); `PosView.vue:716-717` swallow shift/category errors into empty values; `handleCategorySelect` and `handleSwitchStore` do the same (`:752-756`, `:776-778`).
- **Problem**: on a bad connection the cashier sees "no items in this category" and "no shift open", which look like data problems, not a network error. There is no retry.
- **Fix**: an `error` ref in the session composable; render `Components/Common/ErrorState.vue` with Retry in place of the workspace; partial failures (shift, categories) as an `InlineErrorBar` with retry. Remove `console.error`.

#### POS-04 — Heavy admin endpoint instead of `/pos/bootstrap` — P1 — M (+M backend)
- **Evidence**: `PosView.vue:713-718, 381, 743, 765` call `/items`. `ItemResource.php:57-58` computes `can_be_deleted` and `deletion_blockers` for every row, and `Item::getDeletionBlockers()` (`Item.php:220-228+`) runs queries per item, twice per item. That is hundreds of queries per POS load, per category tap (200 items) and per search keystroke (30 items). `/pos/bootstrap` (`GetPOSBootstrapDataAction`) already returns a lean POS shape including `is_weighted`, `pos_settings` and `quick_keys`, but it loads **all** items and all customers unpaginated and auto-creates categories inside a GET.
- **Fix**: frontend: `usePosSession` calls `/pos/bootstrap` once (store, shift, settings, quick keys, categories, default customer); `usePosCatalog` pages items from a lean endpoint.
- **Backend gap**: (1) a lean paged `GET /pos/items?search=&category_id=&cursor=&per_page=` (no deletion blockers, store stock, `is_weighted`, retail/wholesale price); (2) make bootstrap stop returning every item/customer (return pinned + top sellers, first N customers) and move category auto-creation out of the GET.

#### POS-05 — Grid shows only the loaded subset; counts mislead — P1 — M
- **Evidence**: `POSProductGrid.vue:91-128` filters/pages the in-memory `items` (300 + merged category pages of 200); `POSCategorySidebar.vue:134-137` shows the server total in "All items (N)"; `PosView.vue:745-750` keeps appending, never evicts.
- **Problem**: in a 2,000+ item store, "All items (2,140)" shows 300; a category with 260 items shows 200; "Newest" sorts only what happens to be loaded. `isLoadingCategoryItems` (`PosView.vue:237, 739`) is never rendered, so taps on a category look unresponsive on a slow line.
- **Fix**: server-paged grid per category with infinite scroll and skeleton tiles; client cache keyed by category; "Favorites" = pinned + quick keys + top sellers from the server.

#### POS-06 — Selling by weight is not supported in the live POS — P1 — M
- **Evidence**: `PosView.vue:571-590` always adds `qty = 1` and never reads `is_weighted`; `POSCartTable.vue:117, 247` qty input `step="1" min="1"`; `helpers/posCartLines.js:47-55` `decreaseLineQty` removes a line at ≤ 1, so "−" on 0.5 kg deletes it. `POSWeightPickerModal.vue` (⅛, ¼, ½, 1 kg presets + custom) and `POSCartItem.vue` (weight-aware stepper using `stepQuantity`, `WEIGHT_STEP = 0.25`) exist but nothing imports them.
- **Problem**: for a coffee shop selling beans by weight this is the core flow. The cashier must type decimals into a field that suggests integers.
- **Fix**: tile/scan of an `is_weighted` item opens the weight sheet (reuse `POSWeightPickerModal`, made a bottom sheet on phone); cart stepper uses `stepQuantity`; qty entry via numpad with decimals; show the unit next to the qty everywhere. Detect weight via `is_weighted`, not the unit string (`POSCartItem.vue:58` compares to `'كجم'`).

#### POS-07 — Discount — P1 — M (+S backend)
- **Evidence**: `POSCheckoutPanel.vue:24-51` (0/5/10/15/20 % chips, `px-1.5 py-0.5 text-[10px]` ≈ 20 px tall). No fixed-amount input although `discount_type: fixed` is supported (`StoreSalesInvoiceRequest.php:57-58`). No line discount although `items.*.discount_amount` is accepted. Permission `invoices.discount` exists (`PermissionsSeeder.php`) but nothing checks it, and `max_discount_percent` (POSB-2) is stored but never enforced or read.
- **Problem**: cashiers can't give "10 EGP off" or 7.5 %; any cashier can give 20 %; chips are hard to hit on touch.
- **Fix**: a Discount popover/sheet: % / amount toggle, numpad, presets from settings, cap from `pos_settings.max_discount_percent`, hidden/disabled without `invoices.discount`. Line discount in the line editor (POS-34 / layout spec).
- **Backend gap**: enforce `invoices.discount` and `max_discount_percent` server-side in the checkout request/action.

#### POS-08 — Payment panel — P1 — M
- **Evidence**: `PosView.vue:536-546` resets `cashReceived` to the net on every net change, so a tendered amount typed early is wiped by the next scan. `helpers/posCheckout.js:115-118` uses `cashReceived` as the partial paid amount, but switching to Partial leaves it equal to the net, so the first submit fails with `pos.partial_amount_invalid`. Method chips (`POSCheckoutPanel.vue:137-163`) offer cash/instapay/smart_wallet; card (`visa`) and bank transfer are only in the split modal (`POSMultiPaymentModal.vue:218-224`), which uses `e_wallet` while the panel uses `smart_wallet` (the server aliases it, `ValidatesCheckoutPayments.php:29-42`). Cash field is `h-8` with a 10px "change" label; no quick-tender buttons; the split button is an emoji `💳` with only a `title`.
- **Fix**: a Payment sheet (spec below): large net, method tiles (Cash, Card, Instapay, Wallet, Bank, Credit, Split), tender field driven by `POSNumpad` + quick bills (Exact, next round 5/10/50/100/200/500 above net), change in large type, Partial shown as "Paid now / Remaining on account". Keep `tendered` separate from `net` so it is never overwritten; re-validate only on submit. Use one method key set (`e_wallet`).

#### POS-09 — Remote-picked customer shows as "General cash" — P1 — S
- **Evidence**: `PosView.vue:474-483` resolves `selectedCustomer` from `customers` (the first 200 only); `selectCustomer` (`:686-689`) stores just the id; `remoteCustomerResults` is cleared on the next query.
- **Problem**: the invoice goes to customer #812 while the header says "General cash". After a reload, held orders lose the displayed customer too.
- **Fix**: store a customer snapshot `{ id, name, phone, price_tier, current_balance }` on the order and display that.

#### POS-10 — Shift integration — P1 — M (+S backend)
- **Evidence**: `POSHeader.vue:54-61` (10px text badge); nothing in the POS can open or close a shift (only `useDailyJournal.js:97,128,184`); `handleSwitchStore` (`PosView.vue:759-780`) does not refetch `/shifts/current`, so the header shows the previous branch's shift. No check in `InvoiceService`/requests that a shift is open, although `treasury.no_open_shift_warning` says sales need one.
- **Fix**: `PosShiftGate`: when no shift is open, an overlay over the workspace with "Open shift" (opening float; permission `daily_journal.close_shift`) or "Continue without shift" if the store allows it. A shift pill in the top bar opens a Shift menu: X-report (`/shifts/{id}/z-report` preview), cash in/out (expenses), close shift → Z-report print. Refetch the shift on store switch.
- **Backend gap**: per-store setting "require an open shift to sell" + enforcement in checkout.

#### POS-11 — No recent sales / reprint / return from the POS — P1 — M
- **Evidence**: no component or call in the POS; `printLastInvoice` (`PosView.vue:857`) only knows the last invoice, and only until the next sale.
- **Fix**: a "Recent sales" drawer (this shift, last 20, search by number): reprint, open return (`/returns` create flow prefilled with the invoice), cancel (permission `invoices.cancel`). Verify `/invoices` supports a `shift_id` / today filter; otherwise **backend gap**.

#### POS-12 — Phone layout — P1 — L
- **Evidence**: `POSHeader.vue:5` (`flex-col md:flex-row` → three stacked rows ≈ 200 px before any content); `PosView.vue:40-118` uses `flex-col lg:flex-row` with the catalog `order-1` (above the cart) and the category sidebar `order-3` (below the checkout); `POSCategorySidebar.vue:3` `w-14` on phone renders a 56px-wide column at the very bottom; `POSProductGrid.vue:37` `grid-cols-1` under 640px (one product per row); `SpaLayout.vue:289` gives POS `pb-0`, while `MobileBottomNav.vue:5` is `fixed bottom-0 z-40` on `< md`, so it covers the last ~64px (the Save/Print buttons) of the POS scroll area. The whole page scrolls, so the total and the pay button scroll off-screen.
- **Fix**: dedicated phone layout (spec below): hide `MobileBottomNav` on `/pos`, sticky bottom ticket bar with safe-area padding, category chips row, 2–3 column grid, ticket and payment as bottom sheets.

#### POS-13 — Touch targets below 44px — P1 — M
- **Evidence** (approximate rendered heights):
  - Store `<select>` `h-6` (24px): `POSHeader.vue:37`
  - Clear-search `✕` (~24px): `POSHeader.vue:88-98`
  - Retail/Wholesale toggle `min-h-[36px]`: `POSHeader.vue:293, 305`
  - Order tab ≈ 30px, tab close `w-4 h-4` (16px): `POSOrderTabs.vue:7, 36`
  - Discount chips ≈ 20px: `POSCheckoutPanel.vue:24-51`
  - Payment type ≈ 32px, method chips ≈ 26px, split `💳` ≈ 26px: `POSCheckoutPanel.vue:92-163`
  - Cash field `h-8`, Save & print `h-8` (32px): `POSCheckoutPanel.vue:177, 226`
  - Expenses link (11px text, ~16px): `POSCheckoutPanel.vue:56-70`
  - Grid pager `p-1.5` (~28px): `POSProductGrid.vue:15-29`
  - Category items ≈ 34px: `POSCategorySidebar.vue:20…98`
  - Cart qty ± `w-9` (36px), mobile delete 36px, retail/wholesale price badges ≈ 20px: `POSCartTable.vue:47-96, 105-127`
  - Split modal close/trash ≈ 32px: `POSMultiPaymentModal.vue:25-30, 96-102`
- **Fix**: POS minimum 48px for primary actions and 44px for everything else, with an 8px gap between adjacent destructive and non-destructive targets. The `coarse:` custom variant already exists in `app.css:4`.

#### POS-14 — Thin-view rule broken and dead code — P1 — L
- **Evidence**: `PosView.vue` is 955 lines. It contains: 11 computed get/set proxies to the active order (`:258-326`), two copies of a debounce/abort search engine (`:350-455`), cart math, store switching, checkout, a 60-line thermal HTML template (`:857-918`) and the keymap. Unused files: `POSCartItem`, `POSCategoryBar`, `POSCheckoutSummary`, `POSCustomerBar`, `POSCustomerPickerModal`, `POSItemCard`, `POSNumpad`, `POSQuickCustomerModal`, `POSQuickPinnedItems`, `POSWeightPickerModal`, plus `Composables/usePOSCart.js`, `usePOSCategoryColors.js`, `useKeyboardShortcuts.js` and `Services/posService.js`. `posService.js` imports raw `axios` and posts to a non-existent `/pos/invoices`, which breaks the "only `Services/api.js`" rule.
- **Fix**: split as in the component tree below. Reuse `POSWeightPickerModal`, `POSNumpad`, `POSCartItem` and `POSQuickPinnedItems` where they fit, and delete the rest along with `posService.js` and `usePOSCart.js`. Target view ≤ 90 lines.

#### POS-15 — Receipt printing — P2 — M
- **Evidence**: `PosView.vue:857-918`.
  - Uses `parseFloat(...).toFixed(2)` instead of the formatter.
  - Hardcodes `text-align: right`.
  - Interpolates `item.item_name` and `companyName` into HTML without escaping. The print window is sandboxed (`desktop/src/hardware/printerManager.js:17-19`), so this breaks markup rather than allowing RCE.
  - The receipt has no discount, payment lines, tendered/change, customer, cashier, branch address/phone or tax line.
  - `printThermalReceipt` returns `false` when no printer is configured (`useDesktopHardware.js:53-54`), but only thrown errors fall back to the browser print.
  - The drawer decision reads `paymentType.value` after `clearActiveOrder()` has reset the order to `cash` (the print is triggered before the reset but awaits a GET first), so **the drawer opens on credit sales**.
  - The drawer only opens when printing.
  - On Capacitor, `window.open` of the print route is the only path.
- **Fix**: a `usePosReceipt` composable that renders the existing `InvoiceShowThermalReceipt.vue` (or a server receipt payload) to HTML. Capture `payment_type` and payments from the server response before clearing. Open the drawer on any cash portion when the store setting is on, whether or not the receipt prints. Handle the `false` return. Keep a "Reprint last" action.

#### POS-16 — Keyboard map — P2 — S
- **Evidence**: `PosView.vue:920-939` (F2 search, F4 new order, F9 / Ctrl+Enter submit, F10 toggle catalog, Enter closes success). The handler is global and not modal-aware: F9 with the customer modal open submits the sale. The label `pos.confirm_and_approve_f9` says "F9 / Enter" but plain Enter does not submit. `useKeyboardShortcuts.js` is unused. The on-screen legend exists only in Electron (`DesktopShortcutsModal`).
- **Fix**: `usePosShortcuts` with scopes (base / modal / payment). Add:
  - F3: customer
  - F6: discount
  - F7: hold
  - F8: recent sales
  - Del: remove selected line
  - +/−: qty of selected line
  - `n*` prefix: qty multiplier (`3*code`)
  - Esc: close sheet
  - Enter on the payment sheet: complete
  - ?: shortcut legend

  Put F-key hints on the buttons (`kbd`, hidden on coarse pointers).

#### POS-17 — Held orders — P2 — M
- **Evidence**: `usePosOrders.js:7-9`, key `pos_multi_orders_${tenant}` (not per store or user), so tabs follow a cashier into another branch and to the next cashier, carrying old prices. `usePosOrders.js:15` hardcodes the title `طلب #${n}`, which `POSOrderTabs.vue:19` prefers over the translated key, so English shows Arabic. The close button is 16px (`POSOrderTabs.vue:36`). `usePosOrders.js:131-136` deep-watches and `JSON.stringify`s every order to localStorage on every keystroke. No rename, note or customer label on a tab.
- **Fix**: key by tenant + store + user; drop the stored title (render `pos.order_tab_title`); show the customer name and item count on the tab; long-press/⋯ to rename or add a note; debounce persistence (300 ms). Optional backend: server-side parked orders so they survive device changes.

#### POS-18 — Price tier and price editing — P2 — M
- **Evidence**:
  - `PosView.vue:343-348`, `POSHeader.vue:370-375` and `POSProductButton.vue:70-77` (the same helper duplicated 3×, plus a 4th copy in `POSWeightPickerModal.vue`) treat `min_selling_price` as the wholesale price.
  - Changing the tier does not reprice existing lines; there is no watcher.
  - The customer's `price_tier` (in `POSCustomerResource`) is ignored.
  - The price inputs (`POSCartTable.vue:134-141, 264-271`) accept 0 or any value below the minimum, with no permission check and no warning.
- **Fix**: one `getTierPrice(item, tier)` helper. On a tier change, ask "Reprice N lines?". Selecting a customer auto-applies their tier. Price edits sit behind a permission and show a below-minimum warning.
- **Backend gap**: a real `price_wholesale` field separate from the floor price, and enforcement of the minimum selling price unless the user has a permission.

#### POS-19 — Store switch inside the POS — P2 — S
- **Evidence**: `PosView.vue:759-780` (no confirmation with a non-empty cart, no shift refetch, cart keeps the old branch's prices); `PosView.vue:813` `store_id: activeStore.value?.id || 1` (the money rules forbid silently defaulting to store 1); `POSHeader.vue:45` emoji in the `<option>`.
- **Fix**: move the store switch into the shift/store menu; confirm when the cart is not empty; refetch session data (shift, settings, quick keys) and reprice; send `store_id` only when it is known, otherwise block checkout with a message.

#### POS-20 — Clear cart without confirmation or undo — P2 — S
- **Evidence**: `POSHeader.vue:317-325` sits next to the tier toggle; `PosView.vue:623-632` wipes immediately. It does not reset the customer or tier.
- **Fix**: an undo toast (5 s) or `confirmDialog`; move the button to the ticket header's ⋯ menu; reset the customer and tier too, or explicitly keep them.

#### POS-21 — Search dropdown — P2 — S
- **Evidence**: `POSHeader.vue:84` sets focus true, but nothing sets it false except Esc or adding an item, so the dropdown stays over the cart after clicking elsewhere. Rows are two-line and ≈ 88px, so about 4 fit in `max-h-96` (`:107-218`). The "+ Add" pill is hover-only (`:211-215`). The input has no `role="combobox"`, `aria-expanded` or `aria-activedescendant`.
- **Fix**: use `onClickOutside` (`@vueuse/core` is already installed); single-line 52px rows (name · code · stock · price); combobox ARIA; highlight the exact code match first.

#### POS-22 — Out-of-stock adds silently — P2 — S
- **Evidence**: `PosView.vue:571-590` has no stock check; the cart shows no stock. The server is the authority.
- **Fix**: a soft warning chip on the line ("stock 0") and an error tone. Block only when the store setting forbids negative stock; let the server decide.

#### POS-23 — Hardcoded text, emoji, magic values — P2 — S
- **Evidence**:
  - `POSProductGrid.vue:158` returns the literal `'نتائج البحث'` (the key it tests is wrong too).
  - `usePosOrders.js:15` hardcodes `طلب #`.
  - Emoji used as icons: `POSHeader.vue:45` 🏬, `:97` ✕, `:258,265` 🚀; `POSCheckoutPanel.vue:162` 💳; `POSCartTable.vue:59,82,203,226` 🏷️ 🏪; `:302` 🛒.
  - `POSExpensesModal.vue:47-78` hardcodes preset amounts 50/20/10/30.
  - `POSProductButton.vue:14` uses an `ITM-${id}` fallback.
  - Currency is appended as a separate `$t('common.currency')` span everywhere instead of being part of the money formatter.
  - Dead files contain more: `POSQuickCustomerModal.vue:29`, and `POSCartItem.vue:58` / `POSItemCard.vue:29` compare to `'كجم'`.
- **Fix**: keys in `lang/{ar,en}/pos.php`; lucide icons (`Store`, `X`, `CreditCard`, `Tag`, `Warehouse`, `ShoppingCart`); expense presets from settings; a `formatMoney(v, { withCurrency: true })` variant.

#### POS-24 — Forced RTL — P2 — S
- **Evidence**: `PosView.vue:4`, `POSSkeleton.vue:4`, `POSProductGrid.vue:2`, `POSCategorySidebar.vue:16`, `POSMultiPaymentModal.vue:7`, `POSExpensesModal.vue:4` all hardcode `dir="rtl"`. `App.vue:4` and `app.blade.php:2` also force RTL app-wide. Physical classes: `POSHeader.vue:37` `pr-5 pl-2`, `:48` `right-1`, `POSCustomerModal.vue:162` `pr-2`, `POSExpensesModal.vue:137` `text-right`. Directional icons (`ArrowRight` back, `ChevronRight/Left` pager) are not flipped.
- **Fix**: remove the local `dir` attributes and let the root follow the locale (the app-wide fix is a separate task). Use logical utilities, and add `rtl:rotate-180` to directional icons or swap them by `dir`.

#### POS-25 — Accessibility — P2 — M
- **Evidence**:
  - Icon-only buttons rely on `title` only, with no `aria-label`: clear search, tab close, split `💳`, pager chevrons, modal close in `POSMultiPaymentModal.vue:25-30` and `POSExpensesModal.vue:33-39`.
  - Qty, price and cash inputs have no label (`POSCartTable.vue:112-120, 242-250`; `POSCheckoutPanel.vue:169-178`).
  - The two custom modals bypass `AppModal`, so they have no Esc handling or focus trap.
  - 9–10px text is used for stock, codes and labels across `POSProductButton.vue`, `POSHeader.vue` and `POSCheckoutPanel.vue`.
  - `text-slate-400` on white (#94a3b8 on #ffffff) is ≈ 2.6:1.
  - State is shown by colour alone (stock dot, payment type).
- **Fix**: `aria-label`s; visually hidden labels; port both modals to `AppModal`; 12px minimum (11px only for badges); slate-500 minimum for secondary text on light surfaces; add text or icons next to colour states.

#### POS-26 — Theming of on-primary content — P2 — S
- **Evidence**: every primary surface uses `text-slate-950` (`POSHeader.vue:18`, `POSCheckoutPanel.vue:211`, …). `useTheme.js:155` sets `--color-primary-text`: `#ffffff` for 7 of 8 presets and computed for custom colours, but nothing uses it, so indigo/blue/purple/rose tenants get dark text on a mid-tone primary (≈ 3.8:1 on indigo). `POSMultiPaymentModal.vue:18, 92, 183` uses `bg-primary … text-white` and `green-*`/`red-*` while the rest uses `emerald-*`/`rose-*`. Other one-offs: `bg-[#fef3c7]` at `POSCategorySidebar.vue:23` and `dark:text-cyan-400` at `POSHeader.vue:37`.
- **Fix**: add `.text-on-primary { color: var(--color-primary-text) }` (and an `@theme` token) and use it on every `bg-theme-primary`. Standardise success = emerald, danger = rose, warning = amber, accent-2 = purple (see tokens).

#### POS-27 — Loading states after first paint — P2 — S
- **Evidence**: category fetch has no visual state (`PosView.vue:739`); remote search shows a spinner box (`POSHeader.vue:222-232`); the customer modal shows a spinner (`POSCustomerModal.vue:80-85`); `animate-in fade-in slide-in-from-top-2 zoom-in-95` classes are used, but `tw-animate-css` / `tailwindcss-animate` is not installed (`package.json`), so they do nothing.
- **Fix**: skeleton tiles in the grid, three skeleton rows in the search dropdown and customer list, and a submitting overlay on the ticket. Either add the tiny animation utilities to `app.css` or remove the classes.

#### POS-28 — Poor network / offline — P2 — M
- **Evidence**:
  - Checkout blocks correctly offline.
  - A reload while offline gives an empty POS (no cached catalog).
  - Fonts come from Google Fonts (`app.blade.php:63-66`), so Electron/Capacitor at a shop without internet falls back to system fonts.
  - Offline state appears only near the pay button, not in the top bar.
  - There is no timeout UX on a hanging submit beyond the spinner.
- **Fix**: cache the last bootstrap and catalog pages (IndexedDB) for read-only browsing and cart building; a connectivity pill in the top bar; self-host Cairo/Tajawal; a "still trying… (safe to retry, same invoice)" message after 8 s, since idempotency already makes retries safe. The offline sale queue stays Phase 2 (OFFL-1).

#### POS-29 — Float math in displayed numbers — P2 — S
- **Evidence**: `PosView.vue:500-519` (`cartSubtotal`, `discountAmount`, `customerExpensesTotal` with `parseFloat`), `:533-534` (`Number(...)`), `POSCartTable.vue:144, 278` (`item.quantity * item.unit_price`), `POSOrderTabs.vue:76-82`, `PosView.vue:643-646`. Meanwhile `checkoutNet` uses `helpers/decimal.js`.
- **Problem**: subtotal − discount + extras on screen can differ from the net by 0.001–0.01; line totals can show float artefacts before formatting.
- **Fix**: derive every displayed figure from the decimal helpers (`dMul`, `dSum`, `dPercent`) inside `usePosCheckout`, and pass strings to components.

#### POS-30 — Invoice notes not exposed — P2 — S
- **Evidence**: `usePosOrders.js:28` has `notes: ''`; `StoreSalesInvoiceRequest.php:59` accepts `notes`; the payload (`PosView.vue:811-821`) never sends them; there is no UI.
- **Fix**: a notes entry in the ticket ⋯ menu and in the line editor (line notes need a **backend** field if wanted).

#### POS-31 — No permission awareness in the UI — P2 — S (+S backend)
- **Evidence**: no `authStore` permission checks in `views/POS` or `Components/POS` (grep). Discount, price edit, credit, store switch and clear are visible to everyone.
- **Fix**: gate with the auth store (`invoices.discount`, a price-edit permission, `invoices.cancel`, `daily_journal.close_shift`). **Backend**: `invoices.discount` is not enforced anywhere.

#### POS-32 — Generic `/invoices` instead of `/pos/checkout` — P2 — S (+S backend)
- **Evidence**: `PosView.vue:823` posts to `/invoices` (`StoreSalesInvoiceRequest`). Its `prepareForValidation` (`:28-44`) creates the walk-in customer inline with literal Arabic names, and runs before `authorize()`. `/pos/checkout` uses `StorePOSInvoiceRequest` + `ResolveWalkInCustomerAction` (SEC-1 L13 hardened). `InvoiceService.php:126-130` throws a hardcoded Arabic `DomainException` for fractional qty on discrete units, and the unit list is a hardcoded Arabic/English array.
- **Fix**: switch the POS to `/pos/checkout` once response parity is confirmed (both return `InvoiceResource`).
- **Backend gap**: translate the discrete-unit error, and decide unit discreteness by a unit flag rather than a name list.

#### POS-33 — "Coming soon" button vs F10 — P3 — S
- **Evidence**: `POSHeader.vue:254-267` is disabled with a 🚀 badge, but `PosView.vue:927-929` toggles the catalog with F10.
- **Fix**: ship it as the "Scan mode / Catalog mode" toggle (layout spec) or remove both.

#### POS-34 — Cart DOM and feedback — P3 — S
- **Evidence**: `POSCartTable.vue:14` (mobile cards) and `:153` (desktop table) are both always rendered and hidden with CSS. New lines are appended at the bottom with no scroll-into-view or highlight; the qty bump of an existing line has no visual feedback. The `totalQty` prop is declared but unused (`:323`).
- **Fix**: render one variant with `useMediaQuery`; scroll to and flash the affected line (150 ms primary-light background); show total qty in the ticket header.

#### POS-35 — Re-render and persistence cost — P3 — S
- **Evidence**:
  - The deep watcher + stringify on every mutation (`usePosOrders.js:131-136`).
  - `checkoutFingerprint` stringifies the whole cart on every change (`PosView.vue:554-564`).
  - `getCategoryColor` runs `categories.find` per tile, twice (`POSProductGrid.vue:142-150`).
  - `favoriteItemsCount` filters the full array (`PosView.vue:242-247`).
  - `items` is a deep `ref` of hundreds of objects.
- **Fix**: debounce persistence; `shallowRef` for the catalog; `Map` lookups for categories; a single price helper; virtualise the grid if pages exceed ~100 tiles.

#### POS-36 — Success modal blocks the next sale — P3 — S
- **Evidence**: `PosView.vue:829-835` shows a modal after every non-print sale. Print-without-change shows nothing at all (only a chime).
- **Fix**: a non-blocking "Sale #123 complete · Change 15.00 · Reprint" bar on top of the new ticket, dismissed by the next scan or tap. Keep the modal only when change > 0 and the store opts in.

#### POS-37 — Header spends space on branding — P3 — S
- **Evidence**: `POSHeader.vue:16-30` (Zap logo, "fast POS" title, `v{{ appVersion }}`); `:280-282` shows the "General cash" label again as the phone line; `PosView.vue:230` hardcodes the version fallback `'1.0.10'`.
- **Fix**: move the version to the ⋯ menu/About; the customer chip shows name + balance.

#### POS-38 — Direct Swal and hex colours — P3 — S
- **Evidence**: `PosView.vue:177, 647-658` (`confirmButtonColor: '#e11d48'`), `:703, 786, 794, 800, 846`.
- **Fix**: `confirmDialog`, `notifyWarning` and `notifyError` from `helpers/alert.js`.

### Backend gaps to hand to `backend-architect`
1. `GET /pos/lookup?code=`: exact code + `ScaleBarcodeParser`, store stock, `pos.access` (POS-01).
2. Lean paged `GET /pos/items` (no deletion blockers). Slim `/pos/bootstrap` (no full item/customer dump) and move category auto-creation out of the GET (POS-04/05).
3. Reject credit/partial on the walk-in customer (POS-02).
4. Enforce `invoices.discount` and `max_discount_percent` (POS-07/31).
5. Per-store "require open shift to sell" + enforcement (POS-10).
6. Today/shift filter on invoices for the recent-sales drawer, if missing (POS-11).
7. Receipt payload (payments, tendered, change, cashier, branch contact) or a receipt render endpoint (POS-15).
8. A separate wholesale price, and minimum-price enforcement behind a permission (POS-18).
9. Translate the discrete-unit `DomainException`; flag units instead of a name list (POS-32).
10. Confirm `/pos/checkout` parity so the POS can move off `/invoices` (POS-32).

---

## Design tokens

Source files: `resources/css/app.css` (theme variables, palettes, theme utilities), `Composables/useTheme.js` (runtime injection of the primary colour), `resources/views/app.blade.php` (fonts, boot colours), Tailwind v4 default palette (as used in the POS markup). The default theme is **dark** (`App.vue:110`, `<html class="dark">`). Dark mode is class-based (`@custom-variant dark (&:where(.dark, .dark *))`). Tailwind v4 defines its palette in OKLCH; the hex values below are the standard sRGB equivalents of those Tailwind names.

### Colour tokens

| Token / role | Variable or utility used today | Light value | Dark value | Notes |
|---|---|---|---|---|
| Brand primary | `--color-primary` (`.bg-theme-primary`, `.text-theme-primary`) | `#f59e0b` (amber default) | `#f59e0b` (same) | Tenant-selectable: emerald `#10b981`, blue `#3b82f6`, purple `#a855f7`, rose `#f43f5e`, orange `#f97316`, teal `#14b8a6`, indigo `#6366f1`, or any custom hex (`useTheme.js:138-146`) |
| Primary hover | `--color-primary-hover` | `#d97706` | `#d97706` | emerald `#059669`, blue `#2563eb`, purple `#9333ea`, rose `#e11d48`, orange `#ea580c`, teal `#0d9488`, indigo `#4f46e5`; custom = −15 % brightness |
| Primary RGB | `--color-primary-rgb` | `245, 158, 11` | same | Used by `.shadow-theme-primary` |
| Primary light (tint) | `--color-primary-light` (`.bg-theme-light`) | `rgba(245,158,11,0.15)` | same | Highlighted rows, selected chips |
| Primary border | `--color-primary-border` (`.border-theme-border`) | `rgba(245,158,11,0.35)` | same | |
| On-primary text | `--color-primary-text` (set, **unused**; POS hardcodes `text-slate-950`) | `#020617` for amber; `#ffffff` for the 7 other presets; YIQ-computed for custom | same | See POS-26 |
| App canvas | `bg-slate-100` (PosView root) / `bg-slate-50` (layout main) | `#f1f5f9` / `#f8fafc` | `#020617` (slate-950) | `html.dark body` forced to `#020617` in `app.css:25-28` |
| Surface (header, cards, cart, checkout, modals) | `bg-white` / `dark:bg-slate-900` | `#ffffff` | `#0f172a` | Modals in dark use `slate-950` `#020617` |
| Sunken / inset (summary box, search input, payment box) | `bg-slate-50` / `dark:bg-slate-950` | `#f8fafc` | `#020617` | |
| Muted fill (secondary buttons, chips, badges) | `bg-slate-100` / `dark:bg-slate-800` | `#f1f5f9` | `#1e293b` | Hover: `slate-200` `#e2e8f0` / `slate-700` `#334155` |
| Border default | `border-slate-200` / `dark:border-slate-800` | `#e2e8f0` | `#1e293b` | |
| Border strong (inputs) | `border-slate-300` / `dark:border-slate-700` | `#cbd5e1` | `#334155` | |
| Text primary | `text-slate-900` / `text-slate-950` / `dark:text-white` | `#0f172a` / `#020617` | `#ffffff` (body `#f8fafc`) | |
| Text secondary | `text-slate-600` / `dark:text-slate-400` | `#475569` | `#94a3b8` | |
| Text muted | `text-slate-500` / `dark:text-slate-400` | `#64748b` | `#94a3b8` | |
| Text faint / placeholder | `text-slate-400` / `dark:text-slate-500` | `#94a3b8` | `#64748b` | Fails contrast on white for text (POS-25) |
| Success (money, net, cash, in-stock) | `emerald-600` / `dark:emerald-400`, base `emerald-500` | `#059669` text, `#10b981` fill | `#34d399` text | Tints: `emerald-500/10`, `emerald-50` `#ecfdf5` / `emerald-500/10` |
| Danger (credit, delete, out-of-stock, discount amount) | `rose-500` / `rose-600`, `dark:rose-400` | `#f43f5e` / `#e11d48` | `#fb7185` | Tints: `rose-500/10`, `rose-50` `#fff1f2` / `rose-950/50` (`#4c0519` @ 50 %) |
| Warning (partial pay, low stock, favorites) | `amber-600` / `dark:amber-400`, dot `amber-500` | `#d97706` | `#fbbf24` | Favorites active bg hardcoded `#fef3c7` (amber-100) / `amber-500/20` |
| Accent-2 (wholesale tier, split payment) | `purple-500` / `purple-600`, `dark:purple-400` | `#a855f7` / `#9333ea` | `#c084fc` | |
| Info (in-stock tab) | `sky-500` | `#0ea5e9` | `#0ea5e9` | |
| Category colour | `categories.color` / `color_light` (DB) | per category (fallback `#64748b` / `#f1f5f9`) | same (no dark variant) | Used for the tile's start border and price colour |
| Inconsistent one-offs | `green-600/500`, `red-500`, `bg-primary text-white` (split modal), `dark:text-cyan-400` (store select), Swal `#e11d48` | `#16a34a` / `#22c55e`, `#ef4444`, `#22d3ee` | | To be replaced by the semantic tokens above |
| Overlay | `bg-slate-900/50`–`/60` + `backdrop-blur` / `dark:bg-slate-950/80` | `rgba(15,23,42,.5–.6)` | `rgba(2,6,23,.8)` | |
| Selection | `::selection` | `var(--color-primary)` bg, `#ffffff` text | same | |
| Scrollbar thumb | `app.css:285-294` | `rgba(100,116,139,.35)`, hover `var(--color-primary)` | same | Electron: `rgba(148,163,184,.25)` |

### Typography, shape, elevation, spacing, icons

| Token | Value | Source / usage |
|---|---|---|
| Font family (sans, default) | `'Cairo', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif` | `@theme --font-sans`, `body` |
| Font family (POS) | `font-tajawal` → `'Tajawal', 'Cairo', sans-serif` | Every POS component |
| Font family (numbers) | `font-mono` → Tailwind default `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace` | Prices, codes, qty |
| Font loading | Google Fonts CDN: Cairo 300–900, Tajawal 400–900, `display=swap` | `app.blade.php:63-66` (not offline-safe) |
| Weights in use | 900 `font-black` (dominant), 700 `font-bold`, 500 `font-medium` (split modal) | |
| Type scale in POS | 9px, 10px, 11px (arbitrary), 12px `text-xs` (most UI), 14px `text-sm`, 16px `text-base`, 20px `text-xl` (net total), 36px `text-4xl` (split modal net) | Too much at 9–11px |
| Radius | 4px `rounded` (badges), 6px `rounded-md`, 8px `rounded-lg` (small inputs/chips), **12px `rounded-xl` (buttons, tiles, chips; the default)**, **16px `rounded-2xl` (cards, search, dropdowns, modals)**, 24px `rounded-3xl` (empty-cart icon), `rounded-full` (pills, dots) | |
| Shadows | `shadow-2xs` `0 1px rgb(0 0 0/.05)`; `shadow-xs` `0 1px 2px 0 rgb(0 0 0/.05)`; `shadow-md` `0 4px 6px -1px rgb(0 0 0/.1), 0 2px 4px -2px rgb(0 0 0/.1)`; `shadow-xl`/`shadow-2xl` `0 25px 50px -12px rgb(0 0 0/.25)` (dropdowns, modals); `.shadow-theme-primary` `0 10px 20px -3px rgba(var(--color-primary-rgb), .35)` | |
| Spacing base | 4px (`--spacing: 0.25rem`) | |
| Spacing conventions | Panels `p-2.5`–`p-3.5` (10–14px); header `px-4 py-2.5`; gaps `gap-1.5`/`gap-2`/`gap-3`/`gap-3.5`; grid gap 14px | |
| Control heights | Search 48px (`h-12`); primary CTA 44px (`h-11`); header buttons `min-h-[44px]`; many secondary controls 20–36px (POS-13) | |
| Touch helpers | `@custom-variant coarse (@media (pointer: coarse))`; `.btn-native-tap`, `.card-native-tap` (scale on `:active`); 44px rule for `.data-table-actions` | `app.css:4, 260-273, 556-570` |
| Motion | Page: 250 ms enter / 160 ms leave, `cubic-bezier(0.16,1,0.3,1)`; modal zoom 220–240 ms; fade 200 ms; `active:scale-95/0.98` on buttons; skeleton `animate-pulse` + 2 s shimmer | |
| Skeleton primitive | `Components/Common/Skeletons/Skeleton.vue`: `bg-slate-200/80 dark:bg-slate-800/80` + shimmer `via-white/20` (`dark:via-white/5`) | |
| Icon set | `lucide-vue-next` (16px `w-4 h-4` default in controls, 20px in headers, 12–14px in badges); category icons through `Common/DynamicIcon.vue` (emoji or lucide names from DB) | Emoji still used in POS (POS-23) |
| App chrome on POS | DesktopSidebar mini `w-20` (80px); Electron titlebar `h-9` (36px, `#020617`); no top navbar; `MobileBottomNav` on `< md` (≈ 64px + safe area) | `SpaLayout.vue:283, 289`, `DesktopSidebar.vue:4` |
| Breakpoints | Tailwind defaults: sm 640, md 768, lg 1024, xl 1280, 2xl 1536 | POS switches to columns at `lg` |

Token hygiene note: `@theme { --color-primary: var(--color-primary) }` (`app.css:15-18`) is self-referential. It works only because the unlayered `:root` palette block and the inline style from `useTheme` override it. Prefer `@theme inline { --color-primary: var(--brand-primary) }` with the palettes writing `--brand-*`, and add `--color-on-primary`.

---

## Proposed POS layout

Principles: same identity (Tajawal/Cairo, slate surfaces, amber-by-default tenant primary via CSS variables, 12/16px radii, font-black numerals in mono, emerald/rose/amber/purple semantics). The changes are about **what is always on screen**:
- the scan field
- the ticket total
- one big Pay button

and about removing modal hops from the money path.

### Desktop and tablet landscape (≥ 1024px)

```
┌──┬──────────────────────────────────────────────────────────────────────────────────────┐
│S │ TOP BAR 56px                                                                          │
│i │ [←] [Branch ▾ · ● Shift #12]  [ ⌕ Scan or search…                    F2 ] [●Online] [👤 Customer · bal] [⋯] │
│d ├───────────────┬─────────────────────────────────────────────┬────────────────────────┤
│e │ CATEGORY RAIL │ CATALOG                                      │ TICKET  400px (360–440)│
│b │ 96px (xl)     │ Quick-keys pages ▸ [Page 1][Page 2] (POSB-6) │ Orders: [#1 · 3][#2 ·…][+] │
│a │ icon + label  │ ┌────┐┌────┐┌────┐┌────┐┌────┐               │ Customer row (chip)    │
│r │ Favorites     │ │tile││tile││tile││tile││tile│  auto-fill     │ ───────────────────── │
│  │ Quick keys    │ └────┘└────┘└────┘└────┘└────┘  minmax(136px)  │ Line rows 56px         │
│80│ Categories…   │  infinite scroll + skeleton tiles              │  name · qty×price · tot│
│px│               │                                                │  (tap → line editor)   │
│  │               │                                                │ ───────────────────── │
│  │               │                                                │ Subtotal / Discount ✎ / Extras │
│  │               │                                                │ NET  text-3xl emerald  │
│  │               │                                                │ [Hold F7][Disc F6][⋯]  │
│  │               │                                                │ [   PAY  1,250.00  F9 ] 56px │
└──┴───────────────┴─────────────────────────────────────────────┴────────────────────────┘
```

- **Placement**: in RTL the ticket keeps today's side (inline-start = right, next to the mini sidebar) to preserve muscle memory. Catalog in the middle, category rail at inline-end. Implement with a CSS grid on logical order, so LTR mirrors automatically: `grid-template-columns: [ticket] minmax(360px, 420px) [catalog] 1fr [rail] 96px` (rail 72px icon-only at 1024–1279).
- **Two modes** (replaces the disabled "coming soon" button and F10):
  - *Catalog mode* (default for touch/coffee): the layout above.
  - *Scan mode* (default for scanner-heavy stores, persisted per device): catalog and rail collapse; the ticket expands to full width and shows today's desktop table columns (#, item/code, qty stepper, price, line total, remove).
- **Top bar (56px)**:
  - back
  - branch + shift pill (opens the Shift menu: X-report, cash in/out, expenses, close shift → Z-report, switch branch with confirmation)
  - the scan/search field (h-11, flex-1, max 640px, combobox dropdown with 52px single-line rows, exact-code row pinned first)
  - connectivity pill
  - customer chip (name, balance in rose if > 0, tier badge; F3)
  - ⋯ menu (Recent sales F8, Returns, Shortcuts ?, Catalog/Scan mode, Reprint last, About/version)
- **Catalog tile** (min 136×112px, 48px+ hit area): name (2 lines, 14px bold), price (16px mono black, category colour on light / `-400` tone on dark), stock dot + qty (11px badge), weighted badge (`Scale` icon) when `is_weighted`, optional image thumb at ≥ 1280. Tap = add (weighted → weight sheet). Long-press = item info (stock in other branches, last price for the customer via `/pos/last-price`).
- **Ticket line (56px)**: name + code/unit (12px), `qty × price` (mono), line total (mono, end-aligned), stock-warning chip if applicable. The selected line is highlighted with `--color-primary-light`. Tap opens the **Line editor** (popover on desktop, bottom sheet on touch): qty stepper (weight-aware) + numpad, price (permission, min-price warning), line discount, note, remove. Keyboard: ↑/↓ select, +/− qty, Del remove.
- **Totals block**: subtotal, discount (tap → Discount popover: %/amount, presets from settings, cap, permission), extras (opens the existing Expenses modal), **Net** 30px mono emerald. The Pay button is `bg-theme-primary text-on-primary`, 56px tall, showing the net, with F9.
- **Payment panel**: slides over the catalog area (the ticket stays visible), so no modal:
  - Method tiles 64px: Cash, Card, Instapay, Wallet, Bank, **Credit** (disabled until a named customer is set), **Split** (embeds today's split editor rows)
  - Tender display (text-4xl mono) + `POSNumpad` (64px keys)
  - Quick bills: Exact, then the next round-ups (e.g. 1,300 / 1,500 / 2,000)
  - Change in a large emerald block
  - For Partial: "Paid now" + "Remaining on account" in rose
  - Print toggle (remembered), Complete (Enter/F9)
  - Esc returns to the catalog
- **After completion**: the ticket resets and a 48px **completion bar** appears at the top of the ticket ("Invoice #123 · Change 50.00 · Reprint · Return"), auto-dismissed by the next scan or tap. Drawer kick and print follow store settings.
- **Shift gate**: if no shift is open, a centred card over the workspace offers "Open shift" (opening float numpad) or, if allowed, "Continue without shift".
- **Loading**: the skeleton mirrors this grid (rail chips, tile grid, ticket rows, totals). Category change → skeleton tiles; search → three skeleton rows; submit → ticket overlay with "Saving… safe to retry" after 8 s.

### Tablet portrait (768–1023px)

- Top bar as above (customer chip collapses to an avatar plus balance).
- Category chips row (44px, horizontal scroll) under the top bar; no rail.
- Catalog full width, 4–5 columns.
- The ticket is a **docked end panel** (360px) toggled by a persistent bottom **ticket bar** (64px: item count · net · Pay). It opens as an overlay drawer, not a page scroll.

### Phone portrait (360–767px)

```
┌──────────────────────────────┐
│ [←] [ ⌕ Scan/search   ] [👤] │  56px + safe-area top
│ [Fav][Quick][Coffee][Tea]…  →│  44px chips
├──────────────────────────────┤
│ ┌──────┐ ┌──────┐            │
│ │ tile │ │ tile │  2 cols    │  (3 cols ≥ 480px)
│ └──────┘ └──────┘            │  tiles min-h 104px
│ ┌──────┐ ┌──────┐            │
│ …   infinite scroll          │
├──────────────────────────────┤
│ (3) 🧾  1,250.00   [  PAY  ] │  64px + safe-area bottom (sticky)
└──────────────────────────────┘
```

- `MobileBottomNav` is hidden on `/pos` (the POS has its own bottom bar; the back arrow returns to the app). This fixes the overlap in POS-12.
- **Ticket sheet** (tap the count/total area): a 90vh bottom sheet using AppModal's drag-to-close.
  - Header: order switcher chip "Order #2 ▾" (held orders list, new order, rename/note) and the customer chip.
  - Line cards: swipe left to delete with an undo toast, tap to open the line editor sheet.
  - Footer: totals + Discount / Hold / Pay.
- **Pay**: full-screen payment sheet with the same contents as desktop, numpad 64px keys, quick bills in a 3-column grid.
- **Weight sheet** for weighted items (reuse `POSWeightPickerModal`: ⅛ ¼ ½ 1 kg + custom).
- Scan: the search field accepts Bluetooth wedge scanners. A camera-scan button appears only when a Capacitor barcode plugin is detected through `useNativeBridge`; no new package without approval.

### What changes vs. today

| Area | Today | Proposed |
|---|---|---|
| Money path | Cart + always-open checkout panel (20–32px controls) + 3 modals | One 56px Pay button → in-place Payment panel/sheet with numpad and quick bills |
| Catalog | 350–440px column, 3 fixed columns, 24/page client paging over ≤ 300 items | Flexible area, auto-fill tiles, server-paged infinite scroll, quick keys (POSB-6) |
| Categories | 48–192px vertical list; 56px strip at the bottom on phone | 96/72px rail on desktop; 44px chip row on tablet/phone |
| Scan | Substring search + debounced remote; Enter takes the first match | Exact lookup pipeline + scale labels + "not found" feedback |
| Weight | Not supported | Weight sheet + weight-aware stepper |
| Customer | Header button; credit allowed for walk-in; remote pick shows wrong name | Chip with balance and tier; credit gated; snapshot stored on the order |
| Shift | 10px badge | Shift pill + gate + menu (X/Z, cash in/out, close) |
| After sale | Blocking modal (or nothing) | Non-blocking completion bar with change / reprint / return |
| Phone | Stacked page scroll, bottom nav covers pay | App-like: sticky ticket bar, sheets, no global bottom nav |
| Modes | Disabled "coming soon" + hidden F10 | Catalog mode / Scan mode toggle, persisted per device |

### Proposed component tree (refactor target)

```
views/POS/PosView.vue                      ≤ 90 lines: session/composables, error/loading, grid by mode/breakpoint
Composables/
  usePosSession.js      bootstrap: store, shift, pos_settings, quick keys, categories, default customer; error + retry
  usePosCatalog.js      paged items per category/search, cache, skeleton state
  usePosScanner.js      wedge detection, exact lookup, scale labels, qty multiplier "n*"
  usePosOrders.js       (exists) key by tenant+store+user, translated titles, debounced persistence
  usePosCart.js         line ops (posCartLines.js), tier pricing, repricing, weight steps
  usePosCheckout.js     (exists) + all displayed figures as decimal strings, tender/quick bills
  usePosCustomer.js     search (debounce/abort), snapshot, quick-create
  usePosShortcuts.js    scoped keymap
  usePosReceipt.js      receipt render, print fallback, drawer
Components/POS/
  PosTopBar.vue, PosScanField.vue, PosSearchResults.vue, PosShiftPill.vue, PosShiftMenu.vue, PosShiftGate.vue
  PosCategoryRail.vue, PosCategoryChips.vue, PosQuickKeys.vue (from POSQuickPinnedItems)
  PosProductGrid.vue, PosProductTile.vue, PosProductGridSkeleton.vue
  PosTicketPanel.vue, POSOrderTabs.vue (exists), PosCustomerChip.vue, PosTicketLine.vue,
  PosLineEditor.vue, PosTicketTotals.vue, PosDiscountPopover.vue
  PosPaymentPanel.vue, POSNumpad.vue (exists), PosQuickBills.vue, PosPaymentMethodTiles.vue,
  PosSplitEditor.vue (body of POSMultiPaymentModal)
  PosCompletionBar.vue, PosRecentSalesDrawer.vue
  PosMobileTicketBar.vue, PosMobileTicketSheet.vue
  POSWeightPickerModal.vue (exists), POSCustomerModal.vue (exists), POSExpensesModal.vue (exists → AppModal), POSSkeleton.vue (update)
Delete: POSCategoryBar, POSCheckoutSummary, POSCustomerBar, POSCustomerPickerModal, POSItemCard,
        POSQuickCustomerModal, usePOSCart.js, usePOSCategoryColors.js, Services/posService.js
```

Suggested delivery order:
1. POS-01, POS-02, POS-09, POS-03 (correctness, small).
2. Session/catalog composables on `/pos/bootstrap` plus backend gaps 1–2 (POS-04/05).
3. Weight sheet (POS-06).
4. Payment panel + discount + permissions (POS-07/08/31).
5. Shift gate/menu + recent sales (POS-10/11).
6. Phone/tablet layout (POS-12/13).
7. Cleanup (POS-14, 23–27, 33–38).
