# 07: POS cashier UX research: fast price edits, multi-price display, keyboard-first flow, card grid

**Date:** 2026-11-09 · **Author:** product-researcher · **Status:** research input for the CTO (no decisions made here)

**Scope:** what the owner asked for:
1. The cashier can change a line's price quickly.
2. Several prices of a product are visible at once: retail, wholesale, minimum allowed price, cost (by permission), and the last price sold to this customer.
3. As few steps as possible. Keyboard-first with Tab / Enter, and after an item is picked, focus lands straight on the price (or qty) field.
4. A product card grid with 2 or 3 cards per row.

**Decisions honoured (not re-opened):** from `docs/01-overview/product-overview.md` and `docs/04-ux-ui/pos-competitive-study.md` §8–§9:
- Generic retail / wholesale / by-weight positioning.
- RTL-first. Western digits.
- Money in DECIMAL(12,3) with bcmath.
- One POS checkout contract.
- Six POS permissions, including `pos.price_override`, with a manager PIN on the same device (`approvals[]`, POSB-4).
- `pos.price.floor_policy` allow/warn/block, default `warn` (settings catalog, SETG-9).
- Browser uses Ctrl+H / Ctrl+Space instead of F5 / F12.
- Alt+1..5 picks the payment method on the payment screen.
- The approved F-key map in the competitive study §6.3.
- Price lists (`pricing.price_lists.enabled`) are Phase 2.

Where the evidence pushes against one of these, it is raised as a **question** in §11, not changed.

**Source labels:**
- **[official]**: the vendor's own help center, docs, changelog or release notes.
- **[official, via search index]**: an official page that blocked direct fetching (403/404 to the fetcher). Its content was read from the search engine's index of that page.
- **[unofficial]**: community forums, third-party blogs, or marketplace add-ons.
- **(غير مؤكد)**: could not be confirmed.

---

## 1. What the current code already has (read-only inventory)

| Capability | Status | Evidence (paths) | Notes |
|---|---|---|---|
| Editable unit-price input on each cart line | **exists** | `backend/resources/js/Components/POS/POSCartTable.vue` (mobile card ≈ l.133–140, desktop row ≈ l.262–270) → `helpers/posCartLines.js` `updateLinePrice()` | Mouse/touch only. There is no keyboard path to reach it, no Enter/Esc semantics, no validation beyond `>= 0`, and the value is a `parseFloat` number. |
| Retail / "wholesale" price chips on a cart line (click to apply) | **partial** | `POSCartTable.vue` l.44–84 and l.188–228 | "Wholesale" chip is really `min_selling_price` (see next rows). It has a hardcoded emoji. It shows no last-customer price and no floor marker. |
| Retail / wholesale tier toggle | **partial** | `Components/POS/POSHeader.vue` l.286–312. Per-order state at `views/POS/PosView.vue` l.272–276 and `Composables/usePosOrders.js` l.20 | The tier is applied **only when a line is added** (`PosView.vue` `addToCart` l.574–591). Switching tier does not reprice existing lines. Picking a customer does not apply their tier. |
| Customer price tier | **partial** | `database/migrations/tenant/2026_08_08_160002_create_customers_table.php` l.17 (`price_tier` default `retail`) | Read only by **unused** code: `Composables/usePOSCart.js` l.54 is imported nowhere, and `POSQuickCustomerModal.vue` is unused. |
| Separate wholesale price column | **missing** | `app/Models/Item.php` l.73–76 (`getPriceWholesaleAttribute` returns `min_selling_price`); `app/Actions/POS/GetPOSBootstrapDataAction.php` l.95; `app/Http/Resources/POSItemResource.php` l.33; `Components/Items/ItemsTable.vue` l.182 labels `min_selling_price` as "wholesale price" | **Wholesale price and minimum price are the same field.** Selecting "wholesale" therefore sells at the floor. The search dropdown labels the same number "min selling price" (`POSHeader.vue` l.175–178). |
| Minimum price (floor) | **partial** | Column added in `database/migrations/tenant/2026_08_22_200000_add_min_selling_price_to_items_table.php` | **Not enforced server-side.** `app/Http/Requests/StorePOSInvoiceRequest.php` l.88 only checks `numeric|min:0`. `app/Services/InvoiceService.php` l.134 trusts the client's `unit_price`. |
| `pos.price_override` permission + floor policy + manager PIN | **missing** | Decided in competitive study §8 and the settings catalog (`pos.price.floor_policy`, `pos.price_override.enabled`). Planned as SETG-9 / POSB-3 / POSB-4 in `docs/05-planning/phase-1-plan.md` | Today any user with `pos.access` can sell at any price ≥ 0, through the UI or the API. |
| Cost visible by permission | **partial** | `GetPOSBootstrapDataAction.php` l.83–99 and `POSItemResource.php` send `cost_price` only with `items.view_cost` (`app/Support/CostVisibility.php`) | Never displayed in the POS. **Latent leak:** `app/DTOs/Items/ItemDTO.php` l.32 defaults `min_selling_price` to `cost_price` when the key is missing (imports, API callers, the populate command). That cost then reaches cashiers as both "wholesale price" and "min price". The UI form sends `0` (`views/Items/ItemsView.vue` l.115), so manual creates are not affected. |
| Last price sold to this customer | **partial** | Backend: `app/Services/CustomerPricingHelper.php` l.16, `app/Actions/Customers/GetCustomerLastSoldPriceAction.php`, and route `GET /api/v1/pos/last-price` (`routes/api.php` l.162). Frontend: `Services/posService.js` l.13 and `Components/POS/POSCartItem.vue` l.203–222 | Frontend is **dead code**. `posService.js` calls the wrong URL (`/pos/customer-last-price`), and `POSCartItem.vue` is not mounted anywhere. The endpoint is one item per call (N+1) and returns a hardcoded Arabic store name (`CustomerPricingHelper.php` l.52). |
| Branch-specific selling price | **partial** | `store_stocks.custom_selling_price` (`2026_08_11_180000_create_stores_and_stocks_tables.php` l.37), `Item.php` l.174, `StoreStock.php` l.58–63 | POS bootstrap ignores it. `price_retail = $it->selling_price` (`GetPOSBootstrapDataAction.php` l.92), so the POS shows the master price, not the branch price. |
| Keyboard shortcuts | **partial** | `PosView.vue` `handleGlobalKeydown` l.920–941: F2 search, F4 new order, F9 / Ctrl+Enter submit, F10 catalog, Enter closes the success modal. Header search: ↑/↓/Enter/Esc (`POSHeader.vue` l.80–83) | No line-level keys (qty / price / discount / delete). No `n*` multiplier and no cheat-sheet. Keys match on `e.key`, which breaks letter shortcuts on an Arabic keyboard layout (§4.6). `Composables/useKeyboardShortcuts.js` exists but is generic and unused by the POS. |
| Focus after adding an item | **partial** | `PosView.vue` `addItemFromDropdown` l.596–602 always returns focus to search | Fine for scanner retail. There is no option to land on qty or price. |
| Weighted items | **partial** | Backend has `is_weighted` and a scale-label parser (`app/Services/Pos/ScaleBarcodeParser.php`, `StorePosSetting`). Frontend `POSWeightPickerModal.vue` is **unused**, and `addToCart` ignores `is_weighted`. | No "sell by amount" (type money, compute weight). |
| Numpad component | **partial** | `Components/POS/POSNumpad.vue` | Unused. |
| Product grid | **partial** | `Components/POS/POSProductGrid.vue` l.35–37: `grid-cols-1 sm:grid-cols-2 md:grid-cols-3` by **viewport**, 24 per page (l.29). `POSProductButton.vue` has min-h 96px. | Caps at 3 columns. Breakpoints follow the viewport, not the catalog pane width. No density setting. Tiles are not keyboard-navigable as a grid. One price only. |
| Multi-unit (carton/piece) | **missing** | No tenant `units` / conversion tables. Units are platform settings (`database/migrations/2026_10_10_400200_move_legacy_global_system_units_to_platform_settings.php`) | Needed for "carton vs piece" pricing (SETG-11). |
| Money math on the client | **partial** | `PosView.vue` l.501–512 uses `parseFloat`. `usePosCheckout` holds the exact scale-3 math. | Display-only today. Any new price/floor logic must use the bcmath-equivalent helpers in `usePosCheckout` / `helpers/posCheckout.js`, not floats. |

**Bottom line from the code:** the owner's four asks are **partially present but disconnected**:
- A price input exists, but there is no keyboard path to it and no guard on it.
- Two price chips exist, but one of them is the floor mislabelled as wholesale.
- The last-customer price exists on the backend, but the frontend is dead.
- The grid exists, but it is fixed to the viewport.

The **money-critical gap** is server-side. The server accepts any client price. There is no floor check, no override permission check, and no record of the list price that was overridden.

---

## 2. Competitor findings

### 2.1 Odoo POS
- **Numpad modes.** A line is edited by selecting it, then choosing the Qty, % Disc or Price mode on the numpad and typing the value. [official] https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale.html
- **Pricelists in the POS:**
  - The cashier picks a pricelist from a button that only appears when several are available.
  - A customer's pricelist is selected automatically when that customer is set on the order.
  - A pricelist whose conditions are unmet (min qty, dates) does not change the price.
  - [official] https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/pricing/pricelists.html
- **"Price Control" (restrict price changes to managers / advanced access).** Described by third-party tutorials as a native PoS setting. [unofficial] https://www.cybrosys.com/blog/how-to-restrict-price-modification-to-managers-in-odoo-18-pos. Not found in odoo.com docs **(غير مؤكد)**.
- **Marketplace add-ons fill the gaps.** These are a signal of demand, not core features:
  - Default numpad mode set to Price on add [unofficial] https://apps.odoo.com/apps/modules/18.0/pos_default_price_mode
  - Alt-key shortcuts to switch numpad mode (Alt+Q / Alt+D / Alt+R per a search summary) [unofficial] https://apps.odoo.com/apps/modules/18.0/dev_pos_keyborad_shortcut. Exact key list **(غير مؤكد)**.
  - Min/max price per product [unofficial] https://apps.odoo.com/apps/modules/19.0/sh_pos_min_max_price
  - Manager PIN to approve a price change [unofficial] https://apps.odoo.com/apps/modules/17.0/pos_manager_control
- **Takeaway:** Odoo's mode-based numpad (select line → choose mode → type) is the reference "line editor" model. Core Odoo does not show several prices side by side. Floor and PIN come from add-ons.

### 2.2 Square (Retail POS)
- **Changing a line's price:** tap the line in the cart, toggle "Price adjustment", type the new price and save. Toggling it off restores the catalog price. This needs the checkout permission. [official] https://squareup.com/help/us/en/article/8238
- **Location scoping of price overrides.** Release notes say team members can only update price overrides etc. for locations they can access. [official] https://squareup.com/ie/en/release-notes/release-notes-april-23rd
- **Variable-price items.** Leave the item price blank and the POS asks for a price every time it is added. [unofficial] https://community.squareup.com/t5/Archived-Discussions-Read-Only/How-do-I-set-up-my-items-for-a-store-with-price-negotiations/m-p/68843
- **Grid density.** "Tile size" is either Tall (the default compact layout, for dense catalogs where speed matters) or Wide (fewer columns, more room for text). [official product-update post on Square's community board] https://community.squareup.com/t5/Product-Updates/You-Asked-We-Built-It-New-Item-Details-Settings-At-Checkout/bc-p/844441. The exact column counts (4 vs 2) appeared only in a search summary **(غير مؤكد)**.
- A cashier-side "quantity × multiplier key" has been requested by merchants. [unofficial] https://www.sellercommunity.com/t5/Questions-How-To/Multiplication-Key-Feature-Will-We-See-It-in-a-Square-POS-app/m-p/36284

### 2.3 Loyverse
- **Tablets default to a multi-column grid; phones default to a list** (to fit item names). Both are switchable under Settings > General > Home screen item layout. Custom pages stay as grids. [official] https://help.loyverse.com/help/home-sale-screen-layouts
- **Custom grid pages** (press and hold an empty square to edit) sync to the other tablets in the same store. [official] https://help.loyverse.com/help/how-arrange-sale-screen-loyverse
- **Price change at sale / variable price:** not found in Loyverse docs **(غير مؤكد)**.

### 2.4 Lightspeed (X-Series = ex-Vend, and R-Series)
- **X-Series desktop sell screen (Alt on Windows, Option on Mac):**
  - Holding the modifier shows key tooltips; Alt+/ lists all shortcuts.
  - Alt+S search, Alt+C customer, **Alt+L edit last added item**, Alt+P pay.
  - Tab next element; Esc back / exit; F5 park; F6 sales history; F7 close register.
  - [official, via search index] https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534134339867-Sell-screen-keyboard-shortcuts-on-desktop
- **R-Series:**
  - Register shortcuts: Alt+I add-item box (scan/search), Alt+C customer, Alt+D pay, Alt+R refund, Alt+1 / Alt+2 current / new sale, Alt+X dismiss pop-ups. Payment screen: Alt+C cash box, Alt+S finish. [official] https://retail-support.lightspeedhq.com/hc/en-us/articles/228839547-Keyboard-shortcuts
  - Line edit: type the qty in the Qty field and the price in the Price field, then save. [official] https://retail-support.lightspeedhq.com/hc/en-us/articles/229130628-Making-sales-in-Retail-POS
- **"Sales – Change Prices" permission.** It is separate from line discount and whole-sale discount permissions. With inventory access the change can persist to the item; without it, the change applies to that sale only. [official] https://retail-support.lightspeedhq.com/hc/articles/360036516153
- **Price books (X-Series)** apply automatically on the sell screen for the customer's group and outlet. [official] https://x-series-support.lightspeedhq.com/hc/articles/201379170-Running-Sales-using-Price-Books and https://x-series-support.lightspeedhq.com/hc/en-us/articles/25533893858971-Price-book-is-not-applying-on-the-Sell-Screen
- **Takeaway:**
  - "Edit last added item" is a single accelerator, and the most-used line edit.
  - Price change is its own permission, separate from discounts.
  - Customer-group pricing applies on its own.

### 2.5 Shopify POS
- **Hardware keyboard on iPad / Android tablets:**
  - Cmd+K search; hold Cmd to see all shortcuts.
  - Arrow keys move through lists; Enter selects; Cmd+1..9 pick the first nine results.
  - Cmd+Enter checkout; Esc exits flows.
  - Smart Grid tiles are **not** keyboard-navigable.
  - [official] https://changelog.shopify.com/posts/keyboard-shortcuts-and-navigation-in-shopify-pos
- **Line price changes run through the "Apply custom discounts" permission** (line-item discounts and price overrides). Staff can approve restricted actions with a **manager PIN** when the role is set to "approval required". [official] https://help.shopify.com/manual/sell-in-person/setup/staff-permissions
- **Merchants report** that the classic "Price override" (decrease-only) was removed or reworked. [unofficial] https://community.shopify.com/t/why-is-shopify-removing-price-override/242703
- Any HID scanner works as keyboard input. [official] https://changelog.shopify.com/posts/use-any-hid-barcode-scanner-with-shopify-pos
- You cannot change Smart Grid tile font size or style. [official] https://help.shopify.com/en/manual/sell-in-person/getting-started/smart-grid

### 2.6 Foodics
- **Open-price products:** the cashier is prompted for the price before the product is added. [official] https://foodics.helpjuice.com/557710-how-to-make-orders-in-foodics-cashier
- **App roles** include authorities such as Apply Discount, Void, Return and Open Cash Drawer. [official] https://helpv3.foodics.com/-LWkgnasE2aHw_rYeQD5/dashboard/settings/roles-and-authorities. A dedicated "change price" authority or a manager-approval flow was not found **(غير مؤكد)**.

### 2.7 Daftra / Enerpize (Egypt / KSA)
- **Minimum selling price per product:**
  - Turned on through "Advanced Pricing Options". The floor is tax-inclusive.
  - It applies to additional users only; the account owner is exempt.
  - Max discount can be set per employee.
  - Block vs warn behaviour, and POS behaviour, are not stated **(غير مؤكد)**.
  - [official] https://docs.daftra.com/en/?p=4762
- **"Minimum and Last Selling Price Per Item Display" setting** (invoice screen) has three options: hide, show (across all clients), or show for the selected client. It displays both the minimum price and the last selling price. [official] https://docs.daftra.com/en/?p=9521
  - Enerpize equivalent: the price appears under the item field. Client-specific mode needs a page reload after changing the client. [official] https://docs.enerpize.com/user_manual/display-last-selling-price-for-products-in-invoice-creation-screen/
  - This is the closest regional precedent for the owner's "several prices at once" ask, but it is documented for the **invoice** screen, not the POS **(غير مؤكد for POS)**.
- **POS keyboard shortcuts exist** as an in-app list ("Keyboard Shortcuts in the Point of Sale"), but the key list is not published. [official] https://docs.enerpize.com/tutorial/keyboard-shortcuts-in-pos/

### 2.8 Rewaa (KSA)
- **"Edit Price/Discount"** is a per-user POS permission, and it is off in the Cashier preset. [official, via search index] https://intercom.help/rewaa/en/articles/8715565-price-adjustment-discount-permission-in-the-sales-screen and https://intercom.help/rewaa/en/articles/8645767-assigning-cashier-permissions
- **Retail vs wholesale:**
  - One wholesale price field per product.
  - The cashier picks the price type at the top of the sales screen.
  - **Retail and wholesale cannot be mixed in one invoice.**
  - A separate "Sell At Wholesale" permission controls access.
  - [official, via search index] https://intercom.help/rewaa/en/articles/8630718-selling-at-wholesale-or-retail-prices and https://intercom.help/rewaa/en/articles/8717309-wholesale-selling-permission
- **Product data viewing permission** shows product details, including retail and wholesale prices, inside the sales screen. [official, via search index] https://intercom.help/rewaa/en/articles/8720732-product-data-viewing-permission-in-the-sales-screen
- **Selling below cost:**
  - A pop-up appears when the price is not above cost.
  - A global "Sell when price < cost" setting removes the warning.
  - Per-user control comes through the Edit Price/Discount and Sales Settings permissions.
  - [official, via search index] https://intercom.help/rewaa/en/articles/8630738-selling-below-cost-price
- **Keyboard shortcuts** are Windows-only, fixed, and listed in an in-app menu. [official, via search index] https://intercom.help/rewaa/en/articles/8630724-keyboard-shortcuts-in-the-sales-screen

### 2.9 Qoyod (KSA)
- **Below-average-cost warning** in the cart, plus a **new permission** that controls who may complete such sales (Web POS 2.14.1). [official] https://www.qoyod.com/releases/web-pos-v2-14-1/
- **No per-customer price lists.** Agreed prices are typed on the invoice line. [official] https://www.qoyod.com/en/knowledge-base/price-lists-and-custom-pricing-per-customer-in-qoyod/
- **Cart lines** open to edit qty, unit price and tax type. [official, Arabic guide] https://www.qoyod.com/knowledge-base/%d8%b4%d8%b1%d8%ad-%d8%aa%d8%b7%d8%a8%d9%8a%d9%82-%d9%86%d9%82%d8%a7%d8%b7-%d8%a7%d9%84%d8%a8%d9%8a%d8%b9-pos/
- **Shortcuts:** no comprehensive list. Only Shift+A (add a row in documents). [official] https://academy.qoyod.com/en/knowledge-base/keyboard-shortcuts-in-qoyod/

### 2.10 Zoho POS
- F10 focuses search.
- To change a line's price, select the line, enter the price in the **Rate** field, then apply. Qty and discount work the same way.
- [official] https://www.zoho.com/en-ae/pos/resources/help/process-sale-pos.html
- Price lists exist. [official, previously cited in our settings catalog] https://www.zoho.com/en-in/pos/resources/help/pricelist.html

### 2.11 Dynamics 365 Commerce (enterprise reference)
- **Price override** is its own POS operation. It works only for products configured to allow overrides. **Price check** is a separate operation. Line and total discounts respect per-permission-group maximums; going over the total-discount max blocks the action with no override flow. [official] https://learn.microsoft.com/en-us/dynamics365/commerce/pos-pricing-functions
- **Product-level settings:**
  - "Key in price": a mandatory price prompt on add, with value restrictions.
  - "Zero price valid".
  - In the call-center channel, overrides need a **reason code**, are capped by a markup %, and anything above the cap goes to an approval workflow.
  - The old "allow price adjustments to increase price" switch was removed because adjustments are now two-way.
  - [official] https://learn.microsoft.com/en-us/dynamics365/commerce/price-settings

### 2.12 Egyptian / Arab desktop "برنامج كاشير" habits
- **Dexef (Egypt)** markets "8 prices per item with formulas between them" (supermarket edition), plus price levels linked to a customer and editing the price inside the invoice. These are marketing pages, not documentation. [unofficial] https://dexef.com/apps/supermarket-management-program and https://dexef.com/apps/spareparts/spare-parts-customer-accounts. F-key lists were not found **(غير مؤكد)**.
- **Edara:** the POS page mentions line qty / discount / price and barcode, but no multi-price or floor features were found **(غير مؤكد)**. https://static.edara.io/products/Points-of-sale.html
- **"Last rate charged to this customer"** is a standard feature in South Asian / Arab desktop ERPs:
  - Logic ERP shows the last rate plus the bill no and date. [official] https://kb.logicerp.com/logic-erp-in-action-a-feature-overview/distribution/billing/last-rate-charged-during-the-billing
  - The Munim shows the last 5 rates per customer. [official] https://themunim.com/helpdesk/the-last-given-rate-to-the-customer-show-in-the-sales-invoice/
- The common Egyptian counter habit of quoting "جملة / نصف جملة / قطاعي" levels is supported by the Dexef marketing above. A per-customer "last price" in local POS products could not be confirmed from official docs **(غير مؤكد)**.

### 2.13 Quantity-multiplier (`n*`) and scanner conventions
- **Type the qty, then `*`, then scan:**
  - Rite POS: "2*" then scan. [official] https://rite.freshdesk.com/support/solutions/articles/67000669060-changing-setting-quantities-at-the-pos
  - Beat POS: a number followed by `*` replaces the default qty of 1. [official] https://docs.gws.ms/en-US/beat/core/features/POS/scanitems.html
  - IT Retail: a Quantity key shows "n*" in the scan field, behind a permission. [official] https://knowledge.itretail.com/article/working-with-products-in-transactions
  - SambaPOS uses `barcode x qty` with a configurable separator. [unofficial forum] https://forum.sambapos.com/t/entering-qty-of-the-item-in-order/16270
- **HID scanners type like a keyboard**, and an **Enter suffix is a configurable scanner parameter** (Zebra "Enter Key" param). [official] https://docs.zebra.com/us/en/scanners/general/ds4-series/ds4678-prg/user-preferences-and-miscellaneous-options/miscellaneous-scanner-parameters/enter-key.html. "Enter = commit" is the de-facto contract between scanner and POS.

### 2.14 Summary matrix

| Product | Line price edit | Separate price permission | Floor / below-cost guard | Manager PIN | Several prices visible at once | Last price per customer | Customer tier auto-applies | Published keyboard map |
|---|---|---|---|---|---|---|---|---|
| Odoo | ✔ numpad Price mode | ~ "Price control" (unofficial) | add-on only | add-on only | ✘ | ✘ (core) | ✔ pricelist | ~ add-ons |
| Square | ✔ "Price adjustment" | ~ checkout perm | ✘ found | ✘ found | ✘ | ✘ | ~ | ✘ |
| Loyverse | (غير مؤكد) | (غير مؤكد) | ✘ found | ✘ found | ✘ | ✘ | ✘ | ✘ |
| Lightspeed | ✔ Price field / Alt+L | ✔ "Change Prices" | ✘ found | (غير مؤكد) | ✘ | ✘ | ✔ price books | ✔ |
| Shopify | ✔ via custom discount | ✔ "Apply custom discounts" | ✘ | ✔ approval PIN | ✘ | ✘ | ~ | ✔ (Cmd) |
| Foodics | open-price prompt | (غير مؤكد) | ✘ found | (غير مؤكد) | ✘ | ✘ | ✘ | ✘ |
| Daftra | ✔ (invoice) | ~ owner exempt | ✔ min price | ✘ found | ✔ min + last (invoice) | ✔ (invoice) | (غير مؤكد) | in-app only |
| Rewaa | ✔ | ✔ "Edit Price/Discount" | ✔ below-cost pop-up | ✘ found | ✔ retail + wholesale view perm | ✘ found | manual type picker | in-app, Windows |
| Qoyod | ✔ | ~ POS user perms | ✔ below avg cost + perm | ✘ found | ✘ | ✘ | ✘ (no price lists) | ✘ |
| Zoho | ✔ Rate field | (غير مؤكد) | ✘ found | ✘ found | ✘ | ✘ | ✔ price lists | F10 only |
| D365 | ✔ Price override op | ✔ per product + perms | ✔ caps + workflow | ✔ approval flow | ✔ "Price check" op | ✘ | ✔ | operation-based |
| **Sroor today** | ✔ input (no keys) | ✘ | ✘ server | ✘ | ~ 2 chips (one mislabelled) | backend only | ✘ | partial F-keys |

**Gap and opportunity:** no single competitor combines all of the following in the POS:
- a keyboard line editor,
- the floor guard with a manager PIN,
- several prices on one strip, including the last price to this customer.

Daftra comes closest, but only on the invoice screen. Rewaa comes closest on permissions. That combination is exactly the owner's ask, and it fits the wholesale template.

---

## 3. UX guidance that applies

| Topic | Guidance | Source |
|---|---|---|
| Accelerators | Keep shortcuts hidden from novices but visible enough to learn: show the key next to the command so repeated exposure teaches it. | [official] https://www.nngroup.com/articles/flexibility-efficiency-heuristic/ |
| Single-letter shortcuts | Single-character shortcuts (Q, P, W…) must be turn-off-able, remappable, **or only active while the component has focus** (WCAG 2.1.4, Level A). The focus-only route fits our cart-row keys. | [official] https://www.w3.org/WAI/WCAG21/Understanding/character-key-shortcuts |
| Touch targets | NN/g: at least 1 cm × 1 cm. WCAG 2.5.8 (AA): 24×24 CSS px or enough spacing. WCAG 2.5.5 (AAA) and Apple: 44. Android: 48 dp. | [official] https://www.nngroup.com/articles/touch-target-size/ and https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum |
| Layout breakpoints | Width classes: compact < 600 dp (phones), medium 600–839 dp (portrait tablets), expanded ≥ 840 dp (landscape tablets). Size by window, not device. | [official] https://developer.android.com/develop/ui/compose/layouts/adaptive/window-size-classes |
| Grid vs list | List views suit spec-driven catalogs, grids suit visual ones. Loyverse defaults phones to a list for readable names. | [official] https://baymard.com/blog/use-product-tables-for-desktop-product-listings and the Loyverse link above |
| Scanner contract | Scanner = keyboard input + Enter suffix. The input that owns focus must accept a full code followed by Enter at any moment. | Zebra link above, Shopify HID changelog |

---

## 4. Recommended interaction model

### 4.1 One focus home, one active line
- **Focus home** = the search / scan box. Every action ends by returning focus there. This keeps the approved study's rule, and it is what the current code does.
- **Active line** = the line that keyboard line-edits apply to.
  - It is set automatically to the line just added or merged.
  - From the search box, the cashier can move it with ↑/↓ when the query is empty and the dropdown is closed.
  - It is highlighted with a brand-colour start-border (RTL: right edge) and `aria-current="true"`.
- **Line editor** = the qty / price / line-discount fields of the active line. Reach it with an accelerator (§6), or automatically via the entry sequence (§4.2).

### 4.2 Entry sequence: "focus lands on price or qty after picking"
New setting **`pos.entry_sequence`**. Where focus goes after an item is added, and where each Enter goes next:

| Value | After add | Enter in qty | Enter in price | Fits |
|---|---|---|---|---|
| `scan` (default for the retail template) | stays in search | → search | → search | supermarket / scanner retail |
| `qty` (default for the by-weight template; weighted items always) | qty field, value selected | → search | → search | counters selling loose goods |
| `qty_price` (default for the wholesale template) | qty field | → price | → search | wholesale, negotiated prices |
| `price` | price field | → search | → search | open-price / negotiation shops |

Rules:
- **Tab** always walks forward through qty → price → line discount → search. Shift+Tab walks back.
- **Enter** follows the sequence. **Esc** reverts the field being edited to its value before the edit and returns to search.
- A scanned item **never** pulls focus away from search, even in `qty` / `price` mode, unless the item is weighted with no scale label. Scanning streams must not be interrupted.
- An item with no price (price 0, an "open price" item) always opens the price field. This matches Foodics open-price and D365 "Key in price".
- The field value is pre-selected on focus, so typing replaces it. No backspacing.

### 4.3 Price field = a price combobox
- On a desktop, the price field is an ARIA combobox.
- On focus, its listbox shows the suggested prices (§7) in a fixed order. ↓/↑ moves through them, Enter accepts the highlighted suggestion, and typing digits replaces the value with a manual price.
- No new global keys are needed. Arrows inside an open listbox are standard and WCAG-safe.
- On touch, tapping the price opens a bottom sheet with the same chips and a numeric keypad (reuse `POSNumpad.vue`).

### 4.4 Guarded price override (client UX; the server is the authority)
When a price is committed (Enter / Tab / chip pick), the client classifies it:
1. **Equals a tier price or the last-customer price** → accepted. Recorded as `price_source=tier|last_customer`.
2. **Manual price ≥ floor:**
   - Requires `pos.price_override`. Without it, the manager PIN dialog opens.
   - The line gets an "معدّل" badge (amber, icon + text). The original list price stays visible struck-through.
3. **Manual price < floor:**
   - `floor_policy=warn` with the permission: an inline amber notice "أقل من الحد المسموح". A **second Enter** confirms.
   - `floor_policy=block`, or no permission: the manager PIN dialog (POSB-4 `approvals[]`) opens.
   - The notice never shows the cost number to users without `items.view_cost`. This is already decided in the settings catalog.
4. **Manual price < cost** (if the setting distinguishes it): always handled as block → PIN. This follows the Qoyod and Rewaa precedents.

The PIN dialog:
- Focus starts in the PIN field. Enter submits; Esc cancels and reverts the price.
- Its time-to-live is bound to the token and the branch (already decided).

At checkout, the server repeats every check. The client classification is advisory only.

### 4.5 Customer tier and repricing
- Picking a customer (F3) applies `customers.price_tier` to the order.
- Lines without a manual override are **repriced** to the new tier. Manual or approved lines keep their price, and a toast offers "إعادة تسعير الكل" (Enter = yes).
- This follows Odoo's customer pricelist and Lightspeed's price books. Rewaa forbids mixing tiers in one invoice; we would **allow** a per-line tier choice through the combobox. That is a CTO question (§11).

### 4.6 Implementation notes that are specific to Egypt, RTL and Electron
- **Arabic keyboard layout.** Match shortcuts on `KeyboardEvent.code` (`KeyP`, `KeyQ`), not `e.key`. With the Arabic layout active, `e.key` returns Arabic letters, and today's `e.key === 'F2'` style only works because F-keys are layout-independent.
- **Normalize digits.** Map Arabic-Indic (٠–٩) and Persian digits to Western digits in every numeric field. Western digits are a product decision.
- **Electron menu bar.** `desktop/main.js` l.168 sets `autoHideMenuBar: true`. On Windows and Linux, pressing Alt alone toggles that menu bar. This collides with the approved "hold Alt to show key badges" idea and may collide with Alt+letter menu mnemonics in `desktop/src/menu/appMenu.js` **(غير مؤكد; needs a test)**.
- **Browser back.** In the browser, Alt+← / Alt+→ are history back/forward and must be `preventDefault`-ed on the POS route. Some Alt+letter combos are browser menus (Chrome Alt+D / Alt+F / Alt+E; Firefox Alt+F / E / V / S / B / T / H). The map in §6 avoids these. Whether pages can always override them varies **(غير مؤكد)**, so the Playwright desktop project should cover it.
- **Repeat guard.** Ignore `e.repeat` on commit keys (Enter, F9, F12), as the study already notes for F9.

---

## 5. Keystroke-by-keystroke flows

Notation: `[scan]` = scanner input ending in Enter. Electron keys come first; browser equivalents follow in brackets.

### Flow A: piece items, scanner, retail walk-in (`entry_sequence=scan`)
1. POS opens with focus in search (already true today).
2. `[scan]` → line 1, qty 1, retail price, beep. Focus stays in search.
3. `3` `*` `[scan]` → line 2, qty 3.
4. Typo, needed 4: `*` (empty search) → the active line's qty field is selected → `4` `Enter` → back to search.
5. `F12` [`Ctrl+Space`] → exact cash + print → new empty order, focus in search.

**Keys:** 3 scans + `3*` + `*4↵` + 1. The best case is scans + **1 key**.

### Flow B: piece item by name, wholesale customer, negotiated price (`entry_sequence=qty_price`)
1. `F3` → customer search → type `حسن` → `↓` `Enter`.
   - The tier switches to جملة. The customer chip shows balance and credit limit (POSB-1).
   - Focus returns to search.
2. Type `سكر` → each result row shows retail, wholesale, stock and unit → `↓` `Enter`.
   - A line is added at the wholesale price. Focus moves to qty with its value selected.
3. `20` `Enter` → focus moves to the price combobox. The listbox shows:
   `جملة 32.000 · قطاعي 35.000 · آخر سعر لحسن 31.500 (2026-10-12) · الحد الأدنى 30.000`.
4. Either:
   - `Enter` to keep 32.000, or
   - `↓` `↓` `Enter` to take the last price 31.500 (`price_source=last_customer`, no override needed), or
   - type `31` `Enter` (a manual price ≥ floor; needs `pos.price_override`).
5. Focus is back in search. Next item.
6. `F9` [`F9` / `Ctrl+Enter`] → payment screen → `Alt+5` (آجل as a tender) → `Enter`.

**Keys per wholesale line:** search text + `↓↵` + qty `↵` + `↵`. That is about 5 keys beyond typing.

### Flow C1: weighted item with a scale label
1. `[scan]` of a 2x-prefix label → the per-branch parser returns PLU + weight (or price) → a **new line** (weighted lines never merge) of e.g. 1.250 kg at the tier price per kg. Focus stays in search.

**Keys:** one scan, 0 keys. Backend parser exists; frontend wiring is missing.

### Flow C2: weighted item, manual weight (`qty` sequence is forced for weighted items)
1. Type `بن` → `Enter` → the weight field opens with the kg unit shown (reuses `POSWeightPickerModal.vue` inline, or as a small popover).
2. `0.75` `Enter` → line added → back to search.
   - Alternatively, `Tab` moves to "المبلغ". Typing `50` computes weight = 50 ÷ price per kg, at scale 3 with half-up rounding (the decided rounding). The server recomputes it.
   - "Sell by amount" is a common counter habit. **No competitor doc was found for it (غير مؤكد).**

**Keys:** 3 to 4.

### Flow D: price override below the floor, with a manager PIN
1. The line is active (just added, or selected with `↑/↓` from an empty search).
2. `Alt+P` (or numpad `/` in an empty search) → price combobox selected → `28` `Enter`.
3. The floor is 30.000.
   - **warn + cashier has `pos.price_override`:** an amber inline notice appears → a second `Enter` confirms → "معدّل" badge → back to search.
   - **block, or no permission:** the PIN dialog opens → the manager types the PIN → `Enter` → the approval is attached to the line → back to search. `Esc` at any point reverts to the previous price.
4. At checkout, the server re-validates the floor, the permission and the approval token. It stores `list_unit_price`, `unit_price`, `price_source`, `approved_by`.

**Keys:** 4 (warn) or 4 + PIN (block).

### Flow E: mixed tiers in one order
1. A retail walk-in order. The customer buys a full carton of one item.
2. With that line active: `Alt+P` → `↓` to `جملة` → `Enter`. Only that line changes tier (`price_source=tier:wholesale`). Whether a cashier may pick wholesale is governed by permission (cf. Rewaa's "Sell At Wholesale").
3. To switch the whole order: `Alt+W` toggles the order tier → toast "إعادة تسعير السطور؟" → `Enter` reprices the non-overridden lines.
4. **Carton as a unit** (`Alt+U` cycles the line unit piece → carton, with the carton price) depends on multi-unit support, which is **missing** (§9 R16).

### Flow F: price check without selling
1. `Alt+I` → item lookup → shows all prices, stock in this branch and other branches, and the last 3 sales to the current customer. `Esc` closes it. This follows the D365 "Price check" operation.

---

## 6. Recommended shortcut map

This merges the **approved** map (study §6.3, §8, §9) with **new** line-editing keys. New rows are marked **NEW**. Scopes:
- **Global**: anywhere on the POS.
- **Empty search**: focus in search with an empty query.
- **Line focus**: DOM focus on a cart row (WCAG 2.1.4 "active only on focus").
- **Price box**: inside the price combobox.

| Key (Electron) | Browser | Scope | Action | Status |
|---|---|---|---|---|
| F1 or `?` | same | global | Shortcut cheat-sheet | approved, missing |
| F2 | same | global | Focus search / scan | exists |
| Ctrl+K | same | global | Command palette | approved, missing |
| F3 | same | global | Pick customer (Esc = cash walk-in) | approved, missing |
| F4 | same | global | New order tab | exists |
| Ctrl+Tab / Ctrl+1..9 | same | global | Switch order tab | approved, missing |
| F5 | **Ctrl+H** | global | Park / hold with a name | approved, missing |
| F6 | same | global | Sales history / reprint | approved, missing |
| F7 | same | global | Invoice discount (above limit → PIN) | approved, missing |
| F8 | same | global | Return | approved, missing |
| F9 / Ctrl+Enter | same | global | Open payment | exists |
| F10 | same | global | Show / hide catalog | exists |
| F11 | same (preventDefault on fullscreen) | global | Cash in / out | approved, missing |
| F12 | **Ctrl+Space** | global | Exact cash + print | approved, missing |
| `n` then `*` then [scan] / Enter | same | search | Qty multiplier for the next item | approved, missing |
| **`*`** | same | empty search | Edit the active line's **qty** | **NEW** |
| **`/`** (numpad) | same | empty search | Edit the active line's **price** | **NEW** |
| `+` / `-` | same | empty search or line focus | Increase / decrease the active line's qty (weighted: open the weight field) | approved (scope widened) |
| ↑ / ↓ | same | empty search (dropdown closed) or line focus | Move the active line | approved (scope widened) |
| Delete | same | empty search or line focus | Remove the active line (undo toast) | approved, missing |
| **Alt+Q** | same | global | Edit active line qty | **NEW** |
| **Alt+P** | same | global | Edit active line price (price combobox) | **NEW** |
| **Alt+K** (خصم) | same | global | Edit active line discount (cap → PIN) | **NEW** (avoids Chrome Alt+D) |
| **Alt+L** | same | global | Make the last added line active and edit it | **NEW** (Lightspeed parity) |
| **Alt+W** | same | global | Toggle order tier retail ↔ wholesale (reprice prompt) | **NEW** |
| **Alt+U** | same | global | Cycle the line unit (piece / carton) | **NEW**, blocked on multi-unit |
| **Alt+I** | same | global | Price check / item info | **NEW** |
| Q / D / P / W | same | **line focus only** | Qty / discount / price / read scale | approved |
| Tab / Shift+Tab | same | line editor | qty → price → discount → search | **NEW** semantics |
| Enter | same | line editor | Commit and follow `entry_sequence` | **NEW** semantics |
| ↓ / ↑ then Enter | same | price box | Pick a suggested price (tier / last / …) | **NEW** |
| Esc | same | any field / modal | Revert the field, or close, then back to search | approved (revert is new) |
| Alt+1..5 | same | **payment screen only** | Payment method | approved |
| Hold modifier → key badges | — | global | Show key hints on buttons | approved as "hold Alt"; **flag:** Electron autoHideMenuBar (§4.6). Suggest **hold Ctrl** as an alternative. |

Rules for all keys:
- Match on `e.code`.
- Ignore `e.repeat` for commit keys.
- Don't steal digits from the payment amount field (approved in §9).

---

## 7. "Multi-price display" spec

### 7.1 Price set per line, in fixed order (every place uses the same order and colours)

| # | Label (ar) | Source | Who sees it | Visual |
|---|---|---|---|---|
| 1 | السعر (editable) | line `unit_price` | everyone | big, tabular-nums, the field itself |
| 2 | قطاعي | branch price (`store_stocks.custom_selling_price`), else `items.selling_price` | everyone | neutral chip; ✓ when it equals #1 |
| 3 | جملة | **new** `items.wholesale_price`. Later, price-list rows (P2). | everyone, or only with "sell at wholesale" permission (decision) | neutral chip |
| 4 | آخر سعر لهذا العميل | last confirmed invoice line for (customer, item). Same branch first, then any branch. Shows date + qty. | everyone when a named customer is set (Daftra-style setting: off / all clients / this client) | brand-colour chip with a date |
| 5 | الحد الأدنى | `items.min_selling_price` | only users with `pos.price_override`, or when the setting `pos.show_floor_to_cashier`=true; otherwise hidden. The client still gets a pass/fail from the server. | amber outline chip; red + icon + text when #1 < #5 |
| 6 | التكلفة | `items.cost_price` (WAC) | only with `items.view_cost`. **Masked by default ("•••")**, revealed while the eye button is held (the screen often faces the customer). | slate chip |
| 7 | الهامش | (#1 − #6) ÷ #1 | only with `items.view_cost` | small text next to #6 |

### 7.2 Where the prices appear
- **Search dropdown row:** name, code, unit, stock, then #2 and #3 side by side. The active tier is shown bold.
  - Today the dropdown labels the wholesale / floor value "min selling price" (`POSHeader.vue` l.175–178). Fix the label when the field is split.
- **Product card:** the active-tier price, large. In 2-column density, the other tier is shown small underneath. Cost and floor never appear on cards.
- **Active cart line (desktop):** a one-row "price strip" directly under the active line, with chips #2–#7 that can be clicked. It is the same data as the combobox listbox. Inactive lines show only #1 and a badge if overridden.
- **Tablet / phone:** tapping the price opens a bottom sheet with the same chips and a numeric keypad.
- **Price check (`Alt+I`):** all of the above, plus stock per branch and the last 3 sales to this customer.

### 7.3 Data contract
- **POS bootstrap / item search** returns, per item: `price_retail` (branch-aware), `price_wholesale` (the real column), `unit`, `is_weighted`.
  - `min_selling_price` is returned only to users allowed to see it.
  - `cost_price` is returned only with `items.view_cost` (as today).
  - Prices are sent as **strings** (scale 3), not floats.
- **New batch endpoint** `GET /api/v1/pos/customer-prices?customer_id=&item_ids[]=`:
  - Returns the last price, date, qty and invoice no per item.
  - Called once when the customer is chosen (for the lines already in the cart), and once per newly added item, with a per-order cache.
  - It replaces the N+1 `/pos/last-price` usage and the dead `posService.js` call.
  - It needs `pos.access`, store scoping and tenant isolation tests, and no hardcoded store-name fallback.
- **New validation endpoint**, optional and only if the floor must stay hidden: `POST /api/v1/pos/price-check` `{item_id, unit_price, customer_id}` → `{status: ok|warn|needs_approval}`, with no numbers returned. This trades latency, and later offline, against secrecy. It is a CTO question.
- **Checkout** (`StorePOSInvoiceRequest` / `InvoiceService`):
  - Resolve the list price server-side.
  - Enforce `pos.price_override` and `floor_policy`.
  - Accept `approvals[]`.
  - Persist `list_unit_price`, `price_source`, and the approver.
  - Every amount uses bcmath strings.

---

## 8. Card grid recommendation (2 vs 3 columns)

**Recommendation:**
- The default is **auto**: 2 or 3 columns, chosen by the **catalog pane width** using CSS container queries, not the viewport. The pane width changes with F10, the cart width, and Electron window size.
- A per-device override is available.
- 4 columns is offered only as an opt-in "compact" density for the full-catalog mode.

| Columns | Use when | Why (evidence) |
|---|---|---|
| **2** | phones (< 600 dp, compact width class) • catalog pane < ~480 px • long Arabic names • "show two prices on card" is on • weight-heavy counters where the card shows the price per kg | Material compact class guidance. Loyverse defaults phones away from dense grids to keep names readable. Square's "Wide" tile trades columns for text room. Arabic names run long, and 2-line clamps at narrow 3-column widths truncate them. |
| **3** (the default on tablets and desktop) | tablet landscape side pane, or a desktop catalog pane of ~480–900 px, with the cart dominant (our layout) • retail with short names • images off | Approved tablet mockup (`docs/04-ux-ui/pos-mockups/tablet-sell.html` l.140: `grid-cols-3`). Square's default "Tall" tiles are the dense, speed-first option. |
| 4 (opt-in "compact") | full-screen catalog (F10) on ≥ 900 px panes | Approved mockup `@4xl:grid-cols-4`. Fits big catalogs. |

**Card spec:**
- The whole card is the target. Minimum 88–96 px tall and ≥ 44 px wide (well above NN/g's 1 cm and WCAG 2.5.8), with a gap of at least 8 px.
- Contents:
  - Name: 2-line clamp, `dir="auto"`.
  - Active-tier price: large, tabular-nums.
  - Optional second price (2-column mode only).
  - Unit badge (قطعة / كجم / متر / كرتونة).
  - Weighted icon.
  - Stock dot with text: colour is never the only signal.
- No hover-only information. Dark and light themes via CSS variables. Skeleton shimmer (`POSSkeleton.vue` exists).

**Keyboard:**
- The grid uses a roving `tabindex`. Arrow keys are **RTL-aware**: ArrowLeft moves to the next tile visually.
- Enter / Space adds the item. Home / End and PageUp / PageDown move between pages.
- Shopify states its Smart Grid tiles are *not* keyboard-navigable, so supporting this is a cheap differentiator.

**Paging:**
- Keep pages for keyboard users.
- On touch, prefer continuous scroll with virtualisation. Today it is fixed at 24 per page (`POSProductGrid.vue` l.29).

---

## 9. Recommendations

Priority: **Must** = needed for the first sale. **Should** = needed soon after. **Could** = later.

| # | Name | What it controls | Scope | Type / default | Competitors | Our code | Plan | Priority | Phase | Risk |
|---|---|---|---|---|---|---|---|---|---|---|
| R1 | Server-side price authority | Server resolves the list price, enforces `pos.price_override` and `pos.price.floor_policy`, accepts `approvals[]`, and stores `list_unit_price` / `price_source` / approver | tenant (+store for policy) | policy enum `allow\|warn\|block` = `warn` (decided) | Lightspeed, Shopify, Rewaa, Qoyod, D365, Daftra (links §2) | **missing**: `StorePOSInvoiceRequest.php` l.88, `InvoiceService.php` l.134 | all | **Must** | 1a (SETG-9 / POSB-3 / POSB-4) | 💰 Today any price ≥ 0 is accepted, including via the API |
| R2 | Split wholesale price from minimum price | New `items.wholesale_price` DECIMAL(12,3). Data migration keeps today's numbers. The floor becomes the floor only. | tenant (item) | decimal, nullable → falls back to retail | Rewaa (one wholesale field), Daftra (separate min price) | **missing**: conflated in `Item.php` l.73–76, bootstrap l.95, `POSItemResource.php` l.33, `ItemsTable.vue` l.182 | all | **Must** | 1a | 💰 Wholesale sells at the floor today |
| R3 | Stop the `ItemDTO` cost → min-price default | `min_selling_price` must default to 0 / null, not `cost_price` | tenant | — | — | **bug**: `ItemDTO.php` l.32 | all | **Must** | 1a (SEC-1 follow-up) | 🔒 cost leaks to cashiers; 💰 wholesale at cost |
| R4 | Active line + keyboard line editor | `*` and `/` in an empty search; Alt+Q / P / K / L; Enter / Tab / Esc semantics; `e.code` matching; Arabic-digit normalisation | device / UI | — | Odoo modes, Lightspeed Alt+L, Zoho Rate, Shopify Esc | **partial**: inputs exist, no keys (`PosView.vue` l.920–941) | all | **Must** | 2 (new POS UI). A minimal version can land on the current POS. | low |
| R5 | `pos.entry_sequence` | Where focus goes after an add, and the Enter chain | store (+device override) | enum `scan\|qty\|qty_price\|price`; default from template (retail `scan`, wholesale `qty_price`, weight `qty`) | Foodics open price, D365 Key in price, Odoo default-mode add-on [unofficial] | **missing**: always returns to search (`PosView.vue` l.596–602) | all | **Must** | 2 (setting in 1a SETG-12 templates) | low |
| R6 | Price combobox + price strip | Shows prices #1–#7 (§7) and picks them with arrows or a click | user (visibility by permission) | — | Daftra min + last display, Rewaa view-prices permission, D365 Price check | **partial**: 2 chips in `POSCartTable.vue` l.44–84 / 188–228 | all | **Must** | 2 | 🔒 cost / floor visibility |
| R7 | Customer tier auto-apply + reprice | F3 applies `customers.price_tier`. Non-overridden lines are repriced. `Alt+W` toggles the tier. | order | — | Odoo customer pricelist, Lightspeed price books | **partial**: column exists; POS ignores it | all | **Must** (wholesale template) | 1a backend / 2 UI | 💰 wrong tier |
| R8 | Last price to this customer | Batch endpoint + chip + setting `pos.last_price.display` | tenant | enum `off\|all_clients\|this_client` = `this_client` | Daftra / Enerpize (invoice), Logic ERP, The Munim | **partial**: backend single-item endpoint; frontend dead (`posService.js` l.13 wrong URL; `POSCartItem.vue` orphan) | all | **Should** (Must for wholesale) | 1a endpoint / 2 UI | perf: N+1; i18n: hardcoded name |
| R9 | Branch price in the POS | Bootstrap `price_retail` uses `custom_selling_price` when set | store | — | Odoo (per-POS pricelist) | **partial**: `GetPOSBootstrapDataAction.php` l.92 ignores it | all | **Should** | 1a | 💰 wrong price at branches |
| R10 | Qty multiplier `n*` | Qty prefix for the next scan / Enter | device | — | Rite, Beat, IT Retail | **missing** | all | **Should** | 2 | low |
| R11 | Shortcut discoverability | F1 / `?` cheat-sheet; key badges on buttons; hold-modifier overlay | device | — | Lightspeed, Shopify, NN/g | **missing** | all | **Should** | 2 | Electron Alt conflict (§4.6) |
| R12 | Grid density | `pos.catalog.columns` auto / 2 / 3 / 4 by container width; keyboard grid nav | device (store default) | enum = `auto` | Square Tall / Wide, Loyverse grid / list | **partial**: fixed viewport breakpoints (`POSProductGrid.vue` l.35–37) | all | **Should** | 2 | low |
| R13 | Override audit report | "Price overrides by cashier / item / approver" report | tenant | — | D365 reason codes, Qoyod below-cost permission | **missing** | all (advanced view → `reports.advanced`) | **Should** | 2 | 💰 shrinkage visibility |
| R14 | Override reason code | Requires a reason on below-floor approvals; optional on others | tenant | enum `never\|below_floor\|always` = `below_floor` | D365 call-center reason code | **missing** | all | **Could** | 2 | — |
| R15 | Sell by amount (weighted) | Type money → compute weight (scale 3, half-up, server recompute) | store | bool = true for the weight template | no competitor doc found **(غير مؤكد)** | **missing**: weight modal unused | all | **Should** (weight template) | 2 | 💰 rounding |
| R16 | Multi-unit price (carton / piece) | Units per item with a factor and price; `Alt+U` | tenant (item) | — | Dexef multi-price [unofficial], Odoo UoM | **missing** | all | **Should** | 2 (SETG-11 dependency) | 📦 stock conversion |
| R17 | Cost masking on screen | Cost / margin shown masked, revealed while held | user | bool = masked | Rewaa view-data permission (by permission, not masking) | **partial**: data sent, not displayed | all | **Could** | 2 | 🔒 customer-facing screen |
| R18 | Price check `Alt+I` | Item info: prices, stock per branch, last 3 sales | user | — | D365 Price check | **missing** | all | **Could** | 2 | 🔒 cost per permission |

**Counts:** Must 7 (R1–R7) · Should 8 (R8–R13, R15, R16) · Could 3 (R14, R17, R18).

---

## 10. Risk notes
- **💰 Money:**
  - The server trusts the client price (R1).
  - "Wholesale" means the floor (R2).
  - The client still computes subtotals with `parseFloat`. Any new floor or margin math in the UI must use the scale-3 helpers, and the server stays authoritative.
- **🔒 Security:**
  - Cost can reach cashiers through the `ItemDTO` default (R3).
  - The floor value is visible in the payload to anyone who opens devtools, unless the server only returns pass/fail.
  - PIN approvals must stay bound to the token and the branch, are blocked during impersonation (IDEN-2.9, decided), and are rate-limited (POSB-4).
- **🏢 Tenancy / branch:**
  - The last-price lookup must be tenant-DB only, and must respect the store access of the user who asks.
  - The cross-branch fallback in `CustomerPricingHelper.php` l.32–41 should only run if the user may see the other branches.
- **📦 Stock:**
  - Weighted lines must not merge.
  - Multi-unit needs a conversion factor before any carton pricing.
- **UX:**
  - Auto-focus to qty / price must never swallow a scan.
  - Weighted items with a scale label stay zero-touch.

---

## 11. Questions for the CTO
1. **Split wholesale from minimum price now (R2), before price lists (P2)?** A single `wholesale_price` column is small. Without it, the wholesale template sells at the floor.
2. **May one invoice mix retail and wholesale lines?** Rewaa forbids it; Odoo and Lightspeed allow per-line pricing. The recommendation is to allow it, gated by a "sell at wholesale" permission.
3. **Should cashiers see the floor value?**
   - (a) Show it (Daftra-style).
   - (b) Hide it; the client gets pass/fail from a `/pos/price-check` endpoint (secret, but online-only).
   - (c) Send it only to users with `pos.price_override`.
4. **Does raising a price above list need `pos.price_override`?** D365 treats overrides as two-way. Some Odoo add-ons allow increases freely.
5. **Default `pos.entry_sequence` per template:** is retail = `scan`, wholesale = `qty_price`, weight = `qty` acceptable?
6. **Key-badge overlay modifier:** the approved idea is "hold Alt", but Electron's `autoHideMenuBar` toggles the menu on Alt. Switch to hold Ctrl, or remove the menu bar in the POS window?
7. **Should last-customer-price default to on (`this_client`) for every template, or only wholesale?** Also, should the cross-branch fallback stay?
8. **Do the numpad `*` / `/` accelerators (empty search only) fit the shops' keyboards?** Many Egyptian counters use full keyboards with a numpad. **(غير مؤكد; a field check with the live shop is recommended.)**
