# 04 — Frontend (SPA, excluding POS) and Mobile/Desktop shells: launch-readiness audit

Scope: `backend/resources/js` (Vue 3 SPA, all tenant pages except `/pos`), `backend/android` + `capacitor.config.json` (Capacitor 8), `desktop/` (Electron).
Mode: read-only. Nothing in the repo was edited. All paths are relative to the repo root `/home/kamalsroor/projects/sroor`.

What I checked:
- ESLint (`npx eslint resources/js`): **0 errors, 9 warnings**.
- Prettier `--check resources/js`: clean.
- Production build: `vite build` into the scratchpad, without `lang:export` and without touching `public/build`. Passed in 3.2 s. The chunk-size warning fired.
- Visual: headless Chromium against the running dev server (`127.0.0.1:8000`), `/connect` and `/login` only, at 360 px and 768 px. No tenant credentials were used, so no authenticated page was checked visually. Everything else comes from reading the code.

---

## Executive summary

The SPA is mostly well built. Lint and Prettier are clean, dark mode is applied almost everywhere (3,846 `dark:` variants), `DataTable` switches to cards on phones, the base inputs are 44 px and use `inputmode`, and the Android and Electron updaters are carefully engineered (SHA-256 plus signer checks). **It is not launch-ready for a multi-tenant SaaS, though.** Most problems are product-correctness and store-readiness issues, not code style.

**Launch blockers (P0)**
1. **Every tenant sees fake notifications.** They are hardcoded Arabic demo alerts with an invented customer and amount ("فاتورة … بن الأصيل بقيمة 4,495 ج.م"), and the badge always shows "3" (`SpaLayout.vue:656`).
2. **The Android app cannot go on Google Play as it stands:**
   - it still uses the default Capacitor icon and splash;
   - `targetSdk 34` is below Play's current minimum;
   - the APK updates itself through `REQUEST_INSTALL_PACKAGES`, which Play prohibits for apps distributed on Play;
   - there is no release signing, AAB build or R8;
   - there is no privacy policy or account-deletion URL;
   - the permission list declares things the app never uses (camera, Bluetooth scan/connect, storage).
3. **The APK is hard-wired to one customer's host** (`server.url = https://2m.baraa-solutions.com`) with `cleartext: true`. The package id `com.sroor.cofe.erp` (note the "cofe" typo) becomes immutable after the first Play upload.

**High-impact P1 themes**
- **Permissions and plans are not reflected in the UI at all.**
  - Navigation, the dashboard launcher and row actions ignore permissions. Only 3 permission checks exist outside Settings.
  - `FeatureGate.vue` exists but is imported nowhere, and `useModules` reads a static JSON file baked into the bundle.
  - A cashier sees every module. Clicking one silently bounces them to the dashboard, and action buttons return 403 toasts.
- **English and RTL/LTR are effectively unsupported.**
  - `dir="rtl"` is hardcoded on `<html>`, `App.vue`, `SpaLayout.vue` and 24 other components.
  - There is no language switcher.
  - Navigation titles come from hardcoded Arabic strings; `titleKey` is defined but never used.
  - 28 files contain hardcoded Arabic.
- **Money and dates are shown wrongly for non-Egypt tenants and late-night shifts.**
  - The currency label is the static translation `common.currency` (EGP / ج.م) in 203 places. The tenant's currency setting (SAR, AED, KWD…) is ignored.
  - In 32 places, default and preset dates use `toISOString()` (UTC). Between 00:00 and about 03:00 local time, "today" becomes yesterday.
- **Lists are silently capped.** The create screens for purchases, returns and stock transfers, and the coffee blender, load only the first 100–200 items, customers or suppliers. Tenants with bigger catalogues cannot pick the rest.
- **New tenants have no onboarding.** There is no first-run wizard or "set up your store" checklist. A new tenant lands on a dashboard of zeros, and the workspace-connect screen publicly shows a real customer code (`2M`) as a "quick fill" chip.
- **The bundle is heavy for low-end Android.**
  - The shared chunk is 686 kB (204 kB gzip). Most of it is both full translation dictionaries (~600 kB source), which are also inlined into the HTML and downloaded again from `/system/context`.
  - `/system/context` is called twice on every authenticated boot.
  - Google Fonts loads 13 weights from a CDN.
- **Several flows break inside the Android WebView.** Invoice printing (`window.open` + `window.print()`), CSV export (`blob:` downloads) and the Bluetooth printer path (`window.BluetoothPrinter`, which is never defined) do not work there.
- **Tablets and phones cannot switch branch or see the shift status.** Both controls are `hidden lg:flex`, and the mobile drawer has no store switcher.

**Code health (P2/P3)**
- About 4,400 lines are dead: 30+ unused components, including a full duplicate set of 8 `Report*Tab.vue` files, two `FilterDrawer.vue` files, `ThemeTab.vue` and `POSCartItem.vue`.
- There are duplicate primitives: `DatePicker` vs `BaseDatePicker`, `SearchableSelect` vs `BaseSelect`, `useMoney` vs `useFormatters`.
- Views break the thin-view rule: Login 398, Invoices 396, Items 312, InvoicePrint 299, Stores 291 lines.
- 14 tenant views have no error state, so a failed load shows zeros or a blank page.
- Accessibility is weak: zoom is disabled, `AppModal` has no `role="dialog"` or focus trap, there are 23 `aria-label`s for 363 buttons, and 288 uses of 9–10 px text.

**Positives worth keeping**
- `DataTable` has responsive cards and skeletons.
- `ErrorState` / `InlineErrorBar` and `OfflineBanner` exist and work well where they are used.
- The router splits tenant and central routes cleanly.
- The Android `AppUpdaterPlugin` is solid (https-only, SHA-256, same-signer check, package check). It just cannot ship in a Play build.
- The Electron shell is hardened: `contextIsolation`, `sandbox`, a navigation allow-list and a URL policy, all with tests. Its printing (silent thermal print, ESC/POS drawer kick, fallback dialog) is good.

---

## Biggest views (thin-view rule: 50–80 lines; legacy POS excluded)

| Lines | View | Notes |
|---|---|---|
| 398 | `views/Auth/LoginView.vue` | workspace switching, biometrics, quick login and the form all in one file |
| 396 | `views/Invoices/InvoicesView.vue` | 4 API calls, CSV builder, date presets, bulk cancel, Swal dialogs |
| 312 | `views/Items/ItemsView.vue` | 5 API calls, CRUD + stock adjustment + delete inline |
| 299 | `views/Invoices/InvoicePrintView.vue` | receipt markup inline |
| 291 | `views/Stores/StoresView.vue` | 6 API calls |
| 189 | `views/StockTransfers/StockTransfersView.vue` | 4 API calls |
| 174 | `views/Items/CategoriesView.vue` | 4 API calls |
| 167 | `views/Settings/SettingsView.vue` | |
| 150 | `views/DailyJournal/DailyJournalView.vue` | |
| 139 | `views/Auth/WorkspaceConnectView.vue` | |
| 125 / 119 | `Reports/ReportsView.vue`, `Customers/CustomersView.vue` | |

Non-view giants: `Layouts/SpaLayout.vue` 757 lines (header, notifications, user menu, mobile drawer, clock), `Components/Common/DataTable.vue` 574, `Components/Navigation/DesktopSidebar.vue` 527.

## Bundle snapshot (vite build, this branch)

| Chunk | min | gzip | Content |
|---|---|---|---|
| `centralApi-*.js` (shared, loaded on first paint) | 686 kB | 204 kB | both `defaultTranslations` dictionaries (ar+en, ~600 kB source), axios, sweetalert2 |
| `app-*.js` | 231 kB | 68 kB | vue, router, pinia, layouts, lucide |
| `app-*.css` | 255 kB | 31 kB | Tailwind + flatpickr |
| `PosView` | 110 kB | — | |
| `SettingsView` | 107 kB | — | |
| `BaseDatePicker` (flatpickr) | 55 kB | — | |

Routes are lazy-loaded, which is good. The first paint costs about 272 kB of gzip JS plus about 31 kB of CSS plus Google Fonts. On top of that, the HTML already contains `window.spaTranslations` and `/system/context` returns the translations again.

---

## Findings: Frontend SPA (FE)

### FE-01 Fake hardcoded notifications shown to every tenant
- **Severity:** P0
- **Evidence:** `backend/resources/js/Layouts/SpaLayout.vue:656-660`:
  ```js
  const notificationsList = computed(() => [
    { icon: '📦', title: 'تنبيه نواقص المخزون', desc: 'صنف جيهان أخضر وصل للحد الأدنى (5 كجم)' },
    { icon: '🧾', title: 'فاتورة مبيعات جديدة', desc: 'تم اعتماد فاتورة للعميل بن الأصيل بقيمة 4,495 ج.م' },
    ...
  ```
  The bell badge (`SpaLayout.vue:149-153`) pulses with "3" forever. Meanwhile `stores/appConfig.js:57` already stores `data.notifications` from `/system/context`, but nothing reads it.
- **Problem:** Every customer sees invented stock alerts and invoices with fake names and amounts. The text is hardcoded Arabic and ignores locale. This destroys trust on day one.
- **Fix:** Bind the list to `appConfigStore.notifications` (or `/notifications`). Use an `EmptyState` when the list is empty, hide the badge at zero, and translate through keys. Until there is a backend source, remove the bell.
- **Effort:** S (hide) / M (real feed)

### FE-02 UI ignores permissions (nav, dashboard launcher, row actions)
- **Severity:** P1
- **Evidence:**
  - `Composables/useNavigation.js:268-292` filters only by `isModuleEnabled`, never by permission.
  - `Components/Dashboard/DashboardAppMenuHub.vue` (tiles for purchases, returns, users and so on) has no checks.
  - `SpaLayout.vue:74-97`: the user menu always shows Users, Roles and Activity log.
  - `Components/Invoices/InvoicesTable.vue:164-168`: "Cancel invoice" is shown to everyone, and `InvoicesView.vue:353` bulk-cancels for everyone.
  - `grep hasPermission|.can(` outside Settings and SuperAdmin finds 3 hits in total.
  - `router/index.js:486-488`: on denial the user is silently redirected to the dashboard.
- **Problem:** A cashier sees the whole ERP. Each click either bounces back to the dashboard with no explanation or ends in a 403 toast. That looks broken and leaks the information architecture.
- **Fix:**
  - Add a `permission` field to each nav item and launcher tile, and filter with `authStore.hasPermission`. Pull the permission from route `meta` so there is one source of truth.
  - Add a `v-can` directive or `<Can>` component and use it for row actions and bulk actions.
  - On a guard denial, show a translated toast ("no access") before redirecting.
- **Effort:** M

### FE-03 Plan or module gating is not wired to the tenant
- **Severity:** P1
- **Evidence:**
  - `Composables/useModules.js:1-8` reads `config/modules.json`, a static file baked into the bundle with every module `enabled: true`.
  - `Components/FeatureGate.vue` (tenant plan/feature logic) is imported nowhere.
  - The router guard (`router/index.js:453-494`) has no module or plan check.
- **Problem:** Every tenant sees every module, whatever plan it is on, so plan upsell is impossible. When the backend blocks a feature, the UI shows a generic error instead of an upgrade prompt.
- **Fix:**
  - Make `useModules` read `appConfigStore.tenant.plan.features` / `enabled_features` (served by `/system/context`).
  - Add `meta.module` to routes and enforce it in the guard.
  - Use `FeatureGate` with a translated "available on plan X" fallback.
- **Effort:** M

### FE-04 English/LTR is not actually supported (hardcoded RTL, no language switch, Arabic-only nav)
- **Severity:** P1
- **Evidence:**
  - Hardcoded direction:
    - `resources/views/app.blade.php:2` has `<html lang="ar" dir="rtl">`.
    - `App.vue:4`, `Layouts/SpaLayout.vue:4` and `:313`, plus 24 more components, set `dir="rtl"`.
    - `Components/Common/FilterSidebar.vue:16` and `Components/FilterDrawer.vue:14` use `$i18n?.locale`, but vue-i18n is not installed, so they are always `rtl`.
  - No working language switch:
    - `LoginView.vue:332` forces `fetchTranslations('ar')`.
    - There is no language switcher in the tenant UI.
  - Arabic-only text:
    - `useNavigation.js:35-260` holds hardcoded Arabic `title`/`subtitle` strings. `titleKey` is defined but never rendered: `DesktopSidebar.vue:124,183,254` and `SpaLayout.vue` render `section.title` and `item.title`.
    - 97 physical utilities (`ml-/mr-/pl-/pr-/left-/right-/text-left/right`), for example `SpaLayout.vue:50,162,312` and `LoginView.vue:7`.
- **Problem:** An English user gets a mirrored RTL layout with Arabic menus. The `lang/en` investment is wasted, and the product cannot be sold outside Arabic-only markets.
- **Fix:**
  1. Add a single `useLocale()` that sets `document.documentElement.lang/dir` from `appConfigStore.locale`, and remove every hardcoded `dir`.
  2. Add a language switcher (user menu and login).
  3. Render nav from `trans(titleKey)` and add subtitle keys.
  4. Sweep physical classes to logical ones (`ms-/me-/ps-/pe-/start-/end-/text-start`).
  5. Check every page at `en`.
- **Effort:** M–L

### FE-05 Currency label hardcoded to EGP; tenant currency setting ignored
- **Severity:** P1
- **Evidence:**
  - `$t('common.currency')` appears in 203 call sites. `lang/ar/common.php:5` maps it to `'ج.م'` and `lang/en/common.php:5` to `'EGP'`.
  - Settings offer other currencies (`Components/Settings/General/GeneralLocalizationSection.vue:71,237`), and `useFormatters` reads only `currency_decimals` (`Composables/useFormatters.js:25`).
  - `Components/SuperAdmin/TenantStatsGrid.vue:63` has a literal `ج.م`.
  - `Components/Common/ProgressDistributionList.vue:53` defaults `currency` to `'ج.م'`.
- **Problem:** A Saudi or Emirati tenant that picks SAR or AED still sees ج.م on every screen, receipt preview and KPI. That is a visible correctness bug.
- **Fix:** Add `formatCurrency(amount)` / `currencyLabel` to `useFormatters`, driven by `system.currency` (symbol and position per locale). Replace `$t('common.currency')` everywhere with a codemod.
- **Effort:** M

### FE-06 Dates computed in UTC (`toISOString`) — "today" is wrong after midnight local
- **Severity:** P1
- **Evidence:** 32 occurrences in 13 files, for example:
  - `Composables/useCreatePurchase.js:18` (`purchase_date: new Date().toISOString().split('T')[0]`);
  - `useExpenses.js:46,118`;
  - `useDailyJournal.js:9`;
  - `useReports.js:77-91` (today, yesterday and week presets);
  - `views/Invoices/InvoicesView.vue:229-247`;
  - `Components/Dashboard/DashboardAnalyticsRow.vue`.
- **Problem:** Cairo is UTC+2/+3 and Riyadh is UTC+3. A coffee shop working past midnight records purchases and expenses on the previous day, the "today" filters show yesterday, and the daily journal opens on the wrong date. Meanwhile the formatters already know the tenant timezone (`readDisplayContext().timeZone`).
- **Fix:** Add `todayInTenantTz()` / `toTenantDateString(date)` to `helpers/formatters.js` using `Intl.DateTimeFormat(..., { timeZone })`, ban `toISOString().split` with an ESLint `no-restricted-syntax` rule, and replace all 32 sites.
- **Effort:** S–M

### FE-07 Silent truncation of pick-lists (100–300 records)
- **Severity:** P1
- **Evidence:**
  - `Composables/useCreatePurchase.js:67-68` (`suppliers per_page 100`, `items per_page 200`);
  - `useCreateReturn.js:40-42` (customers, suppliers and items, 100 each);
  - `useCreateStockTransfer.js:50` (items 100);
  - `useCoffeeBlender.js:113-114`.
  - The return screen then uses a native `<select>` over that list (`Components/Returns/ReturnItemsTable.vue:14-23`).
- **Problem:** Any tenant with more than 100 items, customers or suppliers cannot select the missing ones in purchases, returns or transfers. There is no warning.
- **Fix:** Replace the preloaded lists with the async search mode of `BaseSelect` (server-side `?search=` with a debounce). Use the same item-search API as POS.
- **Effort:** M

### FE-08 No onboarding for a new tenant
- **Severity:** P1
- **Evidence:**
  - No onboarding, wizard or checklist code exists (`grep -ri onboard|wizard|first_run` returns nothing).
  - `views/DashboardView.vue` always renders KPIs, which show 0 for a new tenant.
  - There is no in-app sign-up or trial (`routes/api.php` has no register route).
  - The workspace screen exposes a real customer code: `Components/Auth/WorkspaceStepInput.vue:66` (hardcoded `2M` quick-fill chip) and `lang/ar/auth.php:49` (`'مثال: 2M أو wadi-elbon'`).
  - In the screenshot (`/connect` at 360 px and 768 px) the placeholder renders as broken bidi ("WADI-ELBON أو Mمثال: 2"), and the field label mixes English and Arabic ("Workspace Code / كود المؤسسة").
- **Problem:** A new store owner has no guidance on the order to work in: company info, then branch, then categories and items, then users, then opening a shift, then the first sale. The connect screen leaks a customer's code and looks unpolished.
- **Fix:**
  - Add a dismissible "Getting started" checklist card on the dashboard. Drive it from a `/system/context` flag (`setup_progress`) or from counts (items = 0, users = 1, no shift).
  - Give the dashboard, items and customers pages empty states with call-to-action buttons.
  - Remove the `2M` chip, use a neutral placeholder, and wrap Latin examples in `<bdi>` / `dir="ltr"`.
- **Effort:** M

### FE-09 Missing error states (failures look like "zero data")
- **Severity:** P1 for the dashboard, P2 for the other views
- **Evidence:**
  - Views with no `ErrorState`/`InlineErrorBar`: Dashboard, Items, Categories, Expenses, Suppliers, SupplierStatement, Roles, Stores, CoffeeBlender, CreatePurchase, CreateReturn, CreateStockTransfer, InvoicePrint, and InvoiceShow (which shows a bare red text block).
  - `views/DashboardView.vue:78-79` and `views/Items/ItemsView.vue:153-154` only `console.error`.
  - `Composables/useSuppliers.js:83` sets `errorMessage`, but the view never renders it.
- **Problem:** When the API fails, the dashboard shows "0 sales today" and the items page is empty. Managers then make decisions on false zeros.
- **Fix:**
  - Standardise on `{ isLoading, error, retry }` from every composable.
  - Render `ErrorState` when there is no data and `InlineErrorBar` when stale data is still on screen.
  - Make the dashboard keep the skeleton or show an error card, never zeros.
- **Effort:** M

### FE-10 Invoices page correctness issues
- **Severity:** P1
- **Evidence:** `views/Invoices/InvoicesView.vue`:
  - `:171-174`: the branch filter is hardcoded to `[{all}, {value:'1', label: main_branch}]`, and real stores are ignored.
  - `:381-391` exports **only the current page (15 rows)** under the label "Export Excel/CSV".
  - `:340-341,384-385` build CSV by string interpolation with no escaping. A `"` in a customer name breaks the row, and values starting with `=`/`+`/`-`/`@` allow formula injection.
  - `:296` sends a hardcoded cancel reason, so the user is never asked.
  - `:363-371` bulk-cancels with N sequential requests, then reports "success" even when some fail.
  - `views/DashboardView.vue:71` navigates to `/invoices?view=ID`, but `InvoicesView` never reads `route.query.view`, so the dashboard "preview" link does nothing.
- **Problem:** The data in these features is wrong or misleading, and there is an injection risk in exported files.
- **Fix:**
  - Load branches from `authStore.stores`.
  - Export server-side (`/invoices/export?filters`, streamed CSV/XLSX), escaping quotes and prefixing formula characters with `'`.
  - Add a cancel-reason prompt.
  - Use a bulk endpoint or `Promise.allSettled` with a real success/failure count.
  - Handle `?view=`.
- **Effort:** M

### FE-11 Branch switcher and shift badge hidden on tablets/phones
- **Severity:** P1
- **Evidence:** `Layouts/SpaLayout.vue:203` (`<div class="hidden lg:flex …">` wraps the store `<select>` and the shift badge). The mobile drawer (`SpaLayout.vue:305-545`) has no store switcher.
- **Problem:** Below 1024 px (every tablet in portrait, every phone) a multi-branch user cannot change branch, and a cashier cannot see whether a shift is open.
- **Fix:** Move the store switcher and shift status into the mobile drawer header and into a compact header chip at `md`.
- **Effort:** S

### FE-12 Hardcoded user-facing strings (rule 6)
- **Severity:** P1
- **Evidence:** 28 files outside `defaultTranslations` contain Arabic literals:
  - `Components/Dashboard/DashboardAppMenuHub.vue:133-300` (43 lines: tab labels and tile titles/subtitles);
  - `Composables/useNavigation.js` (34);
  - `Components/Common/DesktopTitlebar.vue:33,36,58,62,71`;
  - `Components/Common/DesktopShortcutsModal.vue:2-30`;
  - `Composables/useTheme.js:9-41` (theme names);
  - `Components/Form/BaseSelect.vue:33,63,86,94` and `BaseInput.vue:68,80` (Arabic fallback placeholders and titles inside generic primitives);
  - `Components/Invoices/Show/InvoiceShowStoreCard.vue:22`;
  - `Composables/useCoffeeBlender.js:14-19` stores Arabic values as data (`roastType = 'وسط'`) and sends them to the API;
  - `Layouts/SpaLayout.vue:438,540` use emoji as icons;
  - 94 files use emoji glyphs in UI.
- **Problem:** These break English, break i18n parity, and make the emoji render differently on each OS.
- **Fix:** Run `i18n-guardian` over these files. Use enum codes (`light/medium/dark`) as data in the blender. Replace emoji with lucide icons.
- **Effort:** M

### FE-13 Bundle and boot performance
- **Severity:** P1
- **Evidence:**
  - Build output: the shared chunk is 686 kB min / 204 kB gzip. It contains `helpers/defaultTranslations.js` (600 kB source, ar+en), imported by `helpers/trans.js:1`.
  - The same dictionary is inlined into the HTML (`resources/views/app.blade.php:76-84`) and returned again by `/system/context` (`stores/appConfig.js:59`).
  - Two context calls on boot: `router/index.js:463` and `App.vue:115`.
  - `app.blade.php:63-66` loads Google Fonts Cairo and Tajawal, 13 weights, from a CDN (render-blocking, fails offline, sends visitor IPs to Google).
  - `app.css` is 255 kB, which includes flatpickr CSS that only some pages need.
  - `@vuepic/vue-datepicker` and `@capacitor/camera` are dependencies with zero imports.
- **Problem:** A slow first load on low-end Android and 3G, around 3× the translation payload, and an extra round-trip on every reload.
- **Fix:**
  - Drop `defaultTranslations` from the bundle, or dynamic-import only the active locale as a fallback. Rely on the inlined `window.spaTranslations`, and stop sending translations in `/system/context` unless the locale changed.
  - Guard `App.vue` with `if (!appConfigStore.isLoaded)`.
  - Self-host 2 fonts × 3–4 weights as woff2 with `font-display: swap`.
  - Remove the unused dependencies.
  - Add a `build.rolldownOptions` manual chunk for vendor code.
- **Effort:** M

### FE-14 Dead code and duplicate components (~4,400 lines)
- **Severity:** P2
- **Evidence:**
  - Imported nowhere:
    - `Components/Reports/Report{Customers,Expenses,FilterBar,Inventory,Items,Sales,Stores,Treasury}Tab.vue` (8 files, a duplicate of the live `Reports*Tab.vue` set);
    - `Components/FilterDrawer.vue` **and** `Components/Common/FilterDrawer.vue`;
    - `Components/Settings/{ThemeTab(375),SystemTab,TelegramTab}.vue`;
    - `Components/Common/{InvoiceFinancialSummary,InvoiceLineItemsTable,FilterToggleButton}.vue`;
    - `Components/Form/{BaseFileUpload,BaseRadioGroup}.vue`;
    - `Components/Dashboard/DashboardAnalytics.vue`;
    - `Components/FeatureGate.vue`;
    - 10 POS components (`POSCartItem`, `POSCheckoutSummary`, `POSNumpad`, …).
  - `Components/DatePicker.vue` and `Components/SearchableSelect.vue` are used only by dead files.
  - `Composables/useMoney.js` duplicates `useFormatters.formatMoney`.
  - Raw `type="number"` inputs (37) bypass `BaseNumberInput`.
- **Problem:** Developers and AI agents edit the wrong copy, the bundle carries the risk, and the review surface is larger than it needs to be.
- **Fix:** Delete the dead files (git keeps history), merge `useMoney` into `useFormatters`, and add an unused-file check (e.g. `knip`) to CI.
- **Effort:** S

### FE-15 Thin-view rule violations
- **Severity:** P2
- **Evidence:** see the table above. Views that call the API directly: `InvoicesView` (4), `ItemsView` (5), `StoresView` (6), `CategoriesView` (4), `StockTransfersView` (4), `DashboardView` (1).
- **Problem:** Logic is untestable and duplicated (CSV, date presets, Swal flows), and these are the views most likely to regress.
- **Fix:** Extract `useInvoices`, `useItems`, `useStores`, `useCategories`, `useStockTransfers`, `useDashboard`. Move the CSV logic to a helper or the server. Split `SpaLayout` into `AppHeader`, `UserMenu`, `NotificationsPopover` and `MobileDrawer`.
- **Effort:** M–L

### FE-16 Feedback primitives bypassed
- **Severity:** P3
- **Evidence:**
  - 24 files import `sweetalert2` directly, against 14 that use `helpers/alert.js`.
  - `useDeleteHandler` is used by 1 file.
  - Examples: `views/Items/ItemsView.vue:78,193-300` and `views/Invoices/InvoicesView.vue:276-379`, which hardcode colours such as `confirmButtonColor: '#f43f5e'`.
- **Problem:** Dialog styling and dark mode are inconsistent, as are haptics and the double-toast behaviour.
- **Fix:** Route all dialogs through `helpers/alert.js` and all deletes through `useDeleteHandler`. Lint `no-restricted-imports` for `sweetalert2` outside `helpers/`.
- **Effort:** S–M

### FE-17 Accessibility gaps
- **Severity:** P2
- **Evidence:**
  - `app.blade.php:5` has `maximum-scale=1, user-scalable=no`, which disables pinch-zoom (WCAG 1.4.4).
  - `Components/Common/AppModal.vue:118-125` has no `role="dialog"`, `aria-modal`, `aria-labelledby` or focus trap/restore, and puts `select-none` on the whole modal. Only `AppUpdateModal.vue:61` has `role="dialog"`.
  - 23 `aria-label` for 363 `<button>`; icon-only header buttons rely on `title`.
  - 288 uses of `text-[9px]` / `text-[10px]`.
  - The `BaseNumberInput` steppers are 24×24 px (`Components/Form/BaseNumberInput.vue:71,80`).
  - Header icon buttons are 38 px (`SpaLayout.vue:122,148,259`), below 44 px.
  - Sidebar tooltips are hover-only (`DesktopSidebar.vue:101,269`).
- **Fix:**
  - Allow zoom.
  - Add dialog semantics and a focus trap to `AppModal` (it is reused everywhere).
  - Make `aria-label` mandatory on icon-only `BaseButton`.
  - Set a minimum text size of 11–12 px and 44 px tap targets.
- **Effort:** M

### FE-18 Raw tables without mobile handling
- **Severity:** P2
- **Evidence:**
  - `Components/Returns/ReturnItemsTable.vue` (raw `<table>`, no `overflow-x`, no card mode);
  - `Components/Reports/ReportsSalesTab.vue`, `ReportsStoresTab.vue`, `ReportsCustomersTab.vue` (raw tables, `overflow-x` only);
  - `Components/Items/ItemsTable.vue` (its own table instead of `DataTable`, 307 lines).
  - `DataTable` (19 users) does have a card mode (`Components/Common/DataTable.vue:418-455`).
- **Problem:** At 360 px, return lines and report tables overflow or need sideways scrolling, and long Arabic text gets clipped.
- **Fix:** Migrate them to `DataTable` with `#mobile-card` slots.
- **Effort:** M

### FE-19 Router/meta inconsistencies
- **Severity:** P2
- **Evidence:**
  - `router/index.js:52-59`: `/stores/stocks` has no `permission`.
  - `:266-273`: `stock_transfers.create` needs `stores.manage`, while the index needs `transfers.view`.
  - `:378-381`: unknown routes silently redirect to `/` with no 404 page.
  - `App.vue:85-104`: layout detection mixes `route.meta` with `window.location.pathname` string checks and dead route names (`marketing.brochure`).
- **Fix:** Align the permissions with the backend policies, add a `NotFoundView`, and decide layout from `meta.layout` only.
- **Effort:** S

### FE-20 Per-row native listeners leak
- **Severity:** P2
- **Evidence:** `Components/ActionMenu.vue:36` calls `useNativeBridge()` in each row's action menu. `Composables/useNativeBridge.js:20-38` registers a `Network.addListener` or `window` online/offline listener on every mount and never removes it.
- **Problem:** Large tables add hundreds of listeners, which leaks memory on long POS and tablet sessions.
- **Fix:** Make the connectivity part a module singleton (it already exists as `useConnectivity`), keep `triggerHaptic` as a plain function, and remove listeners in `onUnmounted`.
- **Effort:** S

### FE-21 Legacy service worker still served
- **Severity:** P3
- **Evidence:**
  - `backend/public/sw.js` (references `/livewire/`, cache name `…20260822`) is served by `routes/web.php:120` but registered nowhere in the SPA.
  - `public/app.apk` and `public/sroor-cofe-erp-2m.apk` (87 MB each, untracked) sit in the web root.
- **Problem:** Old clients keep a fetch-intercepting service worker. The APK files are a public download of an old build.
- **Fix:** Ship a kill-switch `sw.js` that unregisters itself, and drop the route later. Move APKs to the app-versions storage. If offline is wanted later, design a real PWA strategy.
- **Effort:** S

### FE-22 Login screen leftovers
- **Severity:** P2
- **Evidence:**
  - `views/Auth/LoginView.vue:297` hardcodes the Electron `serverUrl: 'https://baraa-solutions.com/connect'`.
  - `:199` has a fallback version `'1.0.1'`.
  - `:116-142` binds the same `errorMessage` to both inputs **and** the banner, so the error appears three times.
  - There is no "forgot password" for tenant users.
  - The biometric vault stores the account password; this is acknowledged by a `TODO(CTO)` in `Composables/useBiometricAuth.js:9-10`.
- **Fix:**
  - Take the hub URL from server config.
  - Show field errors from the 422 `errors` bag and the banner for auth failures only.
  - Add a tenant password-reset flow (backend dependency).
  - Swap the stored password for a revocable device token (backend dependency, APP-5).
- **Effort:** S (UI) / M (with backend)

---

## Findings: Mobile (Capacitor/Android) and Desktop (Electron) (MOB)

### MOB-01 Default Capacitor launcher icon and splash
- **Severity:** P0
- **Evidence:**
  - `backend/android/app/src/main/res/mipmap-*/ic_launcher*.png` and `drawable*/splash.png` are the stock Capacitor blue "X" on white. I viewed them.
  - The `capacitor.config.json` `SplashScreen` block is ignored because `@capacitor/splash-screen` is not installed (`package.json`).
  - The splash background (white) does not match the dark `#020617` shell.
- **Problem:** The app looks unbranded and gets rejected for store listing quality.
- **Fix:** Generate adaptive icons and an Android 12+ splash (`@capacitor/assets` or Android Studio Image Asset), set `windowSplashScreenBackground`, and install the splash-screen plugin or delete the dead config.
- **Effort:** S

### MOB-02 targetSdk 34 is below Google Play's minimum
- **Severity:** P0
- **Evidence:** `backend/android/variables.gradle:4` sets `targetSdkVersion = 34` (with `compileSdk 36`, `minSdk 24`).
- **Problem:** Play has required API 35 for new apps and updates since 31 Aug 2025. The yearly bump to API 36 applies from about Aug 2026; verify the exact current date in Play Console. Uploads below the minimum are refused. API 35+ also enforces edge-to-edge (see MOB-10).
- **Fix:** Set `targetSdkVersion = 36`, test the edge-to-edge insets, the new back-gesture behaviour and the `BLUETOOTH_*` runtime prompts.
- **Effort:** S–M

### MOB-03 Self-update via sideloaded APK conflicts with Play policy
- **Severity:** P0 for Play; OK for direct distribution
- **Evidence:**
  - `AndroidManifest.xml` declares `REQUEST_INSTALL_PACKAGES`.
  - `android/app/src/main/java/com/sroor/cofe/erp/AppUpdaterPlugin.java` downloads and installs APKs.
  - `Composables/useAppUpdate.js:13,64-71` drives it.
- **Problem:** Play's Device and Network Abuse policy forbids Play-distributed apps from updating themselves outside Play. `REQUEST_INSTALL_PACKAGES` is a restricted permission that needs a declaration, and self-updating is not an accepted use. The app would be rejected or removed.
- **Fix:**
  - Create two product flavours: `play` (no AppUpdater, no permission; use the Play In-App Updates API if needed) and `direct` (the current flow).
  - Feature-detect in `useAppUpdate` (already done via `isPluginAvailable('AppUpdater')`).
  - Add `platform=android_play` to `/app/check-update` so the server stops offering APKs to Play installs.
- **Effort:** M

### MOB-04 No release signing / AAB / shrinking pipeline
- **Severity:** P0
- **Evidence:**
  - `android/app/build.gradle` has no `signingConfigs`, and `release { minifyEnabled false }`.
  - No keystore handling exists in `backend/scripts` or `.github`.
  - `versionCode 3 / versionName 1.0.2` is maintained by hand and unrelated to the web `version.json` (1.0.135).
  - The 87 MB APKs in `public/` suggest a debug build with stray assets.
- **Problem:** Play needs a signed AAB (upload key plus Play App Signing). The signer-check updater (MOB-03) also needs one stable release key. Losing that key bricks updates.
- **Fix:**
  - Add a `signingConfigs.release` that reads keystore path and passwords from env or `~/.gradle/gradle.properties` (never commit them).
  - Run `bundleRelease` in CI, enable R8 with Capacitor keep rules, and derive `versionCode` from CI.
  - Document key custody, and back the key up offline.
- **Effort:** M

### MOB-05 Store-listing compliance: privacy policy, data safety, account deletion
- **Severity:** P0
- **Evidence:** No privacy-policy route or page exists in the SPA or `routes/web.php` (`grep -i privacy` finds nothing). The app handles phone numbers, customer PII, biometrics and the camera permission.
- **Problem:** Play requires a public privacy-policy URL, a Data safety form, and (for apps with user accounts) an account-deletion request path reachable on the web.
- **Fix:** Add public `/privacy` and `/account-deletion` pages (static, ar and en) on the central host, link them from login and settings, and fill in Data safety.
- **Effort:** S–M

### MOB-06 App hard-wired to one customer host; package id typo; tenant-branded name
- **Severity:** P1, and P0 for the applicationId decision before the first upload
- **Evidence:**
  - `backend/capacitor.config.json` and `android/app/src/main/assets/capacitor.config.json` set `server.url: "https://2m.baraa-solutions.com"`.
  - `appId com.sroor.cofe.erp` ("cofe").
  - `appName "منظومة سرور كوفي ERP"`, while the web shell has moved to neutral platform branding (`app.blade.php` BRND-1 comment).
- **Problem:**
  - Every tenant's app traffic depends on one customer's subdomain. Tenancy works through the `X-Tenant` header, but that customer's DNS or SSL becomes a single point of failure for everyone.
  - The package id cannot be changed once it is on Play.
  - The name is a tenant brand, not the product brand.
- **Fix:** Point `server.url` at the central app host (for example `app.<platform-domain>`), pick the final applicationId and display name now, and make them per-flavour if white-label builds are planned.
- **Effort:** S

### MOB-07 Cleartext traffic enabled, backup enabled with bearer token in WebView storage
- **Severity:** P1
- **Evidence:**
  - `capacitor.config.json` sets `"cleartext": true`.
  - `AndroidManifest.xml` sets `android:allowBackup="true"`.
  - The token lives in `localStorage.auth_token` (`Services/api.js:31`).
  - `res/xml/file_paths.xml` exposes `<external-path path=".">` (the whole external storage root).
- **Problem:**
  - HTTP is allowed to any host.
  - ADB or cloud backups can carry a live API token to another device.
  - The FileProvider scope is wider than the update cache needs.
- **Fix:**
  - Set `cleartext: false` and add a network security config (debug-only cleartext for localhost).
  - Set `allowBackup="false"`, or add `dataExtractionRules` that exclude WebView storage.
  - Limit `file_paths.xml` to `<cache-path name="updates" path="app-updates/"/>`.
- **Effort:** S

### MOB-08 No offline / no-network launch experience
- **Severity:** P1
- **Evidence:**
  - The app is a remote-URL WebView (`server.url`). `capacitor-www/index.html` is unused, and its fallback redirects to `http://localhost:8000`.
  - No `server.errorPath` is set, no service worker is registered (FE-21), and there is no offline data layer.
  - `Components/Layout/OfflineBanner.vue` only works after the SPA has loaded.
- **Problem:** Opening the app with no or poor network shows Chromium's "ERR_INTERNET_DISCONNECTED" page. POS cannot sell during internet drops, which is common in retail. Play's "minimum functionality / webview wrapper" review risk also rises.
- **Fix:**
  - Short term: bundle a branded offline/retry page and set it as `server.errorPath` (Capacitor 6+), and precache the app shell with a real service worker.
  - Product decision: offline POS queue (IndexedDB plus sync with idempotency keys). Coordinate with the POS audit.
- **Effort:** S (error page) / L (offline POS)

### MOB-09 Declared-but-unused permissions and plugins; no barcode camera
- **Severity:** P1
- **Evidence:**
  - The manifest declares `CAMERA`, `BLUETOOTH_CONNECT`, `BLUETOOTH_SCAN` (without `android:usesPermissionFlags="neverForLocation"`), legacy `BLUETOOTH*`, and `READ/WRITE_EXTERNAL_STORAGE`.
  - `@capacitor/camera` is installed but has 0 imports.
  - No barcode scanner plugin exists.
  - `useNativeBridge.printThermalBluetooth` calls `window.BluetoothPrinter`, which is never defined (`Composables/useNativeBridge.js:114-120`).
  - `@capacitor/status-bar` is installed but `setStatusBar` is never called.
- **Problem:** Play reviewers and users see permissions with no visible feature, and `BLUETOOTH_SCAN` without `neverForLocation` implies location access. The phone cannot scan barcodes with the camera, a core retail expectation on Android.
- **Fix:**
  - Remove the unused permissions now.
  - When the features land, add `@capacitor-mlkit/barcode-scanning` (or `BarcodeDetector` with a fallback) behind `useNativeBridge.scanBarcode()`.
  - Add a real ESC/POS Bluetooth or USB printer plugin before declaring the Bluetooth permissions.
- **Effort:** S (cleanup) / M (scanner) / M–L (BT printing)

### MOB-10 Printing, downloads and sharing break inside the Android WebView
- **Severity:** P1, and P0 if Android tablets are sold as POS terminals
- **Evidence:**
  - Print: `views/Invoices/InvoicesView.vue:271,330` (`window.open('/invoices/:id/print', '_blank')`), `views/Invoices/InvoicePrintView.vue:237,266` (`window.print()`), and the `useNativeBridge.js:120` fallback. Android WebView has no print implementation for `window.print()`, and Capacitor does not open `_blank` popups as app windows.
  - Downloads: CSV export uses `blob:` URLs (`InvoicesView.vue:342-347,386-390`). `MainActivity.java` sends downloads to the system browser, which can neither read a `blob:` URL nor carry the bearer token.
  - Sharing: there is no native share sheet; `wa.me` links only (`Composables/useInvoiceShow.js:136-138`).
- **Problem:** Managers on Android cannot print receipts or A4 invoices, export CSV, or share a PDF.
- **Fix:**
  - Add a small native `Printer` bridge (Android `PrintManager` over a WebView of the receipt HTML) or an ESC/POS plugin, exposed through `useNativeBridge.print(html)`.
  - Generate exports server-side and save them with `@capacitor/filesystem`, then `@capacitor/share`.
  - Use `@capacitor/share` for invoice links and PDFs.
- **Effort:** M

### MOB-11 Edge-to-edge, safe areas and status bar
- **Severity:** P1, because it becomes mandatory with MOB-02
- **Evidence:**
  - Insets are handled only in `.mobile-safe-header` (`resources/css/app.css:32-42`), `MobileBottomNav.vue:5`, `AppModal.vue:127` and `ActionMenu.vue:254`.
  - The POS route hides the header, so it has no top inset.
  - `capacitor.config.json` fixes the `StatusBar` style to DARK with background `#020617` regardless of light theme, and the StatusBar plugin is never called (`useNativeBridge.setStatusBar` is unused).
  - `App.vue:3` uses `h-screen` (100vh), not `dvh`.
- **Problem:** On Android 15+ (targetSdk 35+) content draws under the status and navigation bars. Light theme shows a dark status bar, and the 100vh plus fixed bottom nav jumps when the browser UI changes.
- **Fix:**
  - Apply `env(safe-area-inset-*)` at the shell level (top, bottom and both sides) for every layout, including POS and the print view.
  - Call `StatusBar.setStyle/setBackgroundColor` from `appConfigStore.setTheme`.
  - Use `h-dvh`.
- **Effort:** S–M

### MOB-12 Keyboard overlap
- **Severity:** P2
- **Evidence:**
  - No `@capacitor/keyboard` is installed.
  - `MainActivity` in `AndroidManifest.xml` has no `android:windowSoftInputMode`.
  - The layout is `h-screen overflow-hidden` with a fixed bottom nav (`MobileBottomNav.vue:5`) and modals capped at `max-h-[90vh]` (`AppModal.vue:127`).
- **Problem:** On phones the bottom nav rides up over the keyboard, and fields near the bottom of sheet modals are hidden behind it.
- **Fix:**
  - Set `adjustResize`, add `@capacitor/keyboard` (`resize: 'body'`), and hide `MobileBottomNav` while the keyboard is open.
  - Use `max-h-[90dvh]` and `scrollIntoView` on focus inside `AppModal`.
- **Effort:** S

### MOB-13 Hardware back button ignores open overlays
- **Severity:** P2
- **Evidence:**
  - `resources/js/app.js:20-36` either navigates back or minimises.
  - `MainActivity.java:31-41` overrides the deprecated `onBackPressed`.
  - Modals (`AppModal`), the mobile drawer (`SpaLayout.vue:305`), `ActionMenu` sheets and the filter sidebars are not consulted.
- **Problem:** Back with a sheet or drawer open navigates away from the page, so unsaved form data is lost. Users expect back to close the overlay. The deprecated override also conflicts with predictive back on targetSdk 33+.
- **Fix:**
  - Add a small `useBackHandler` overlay stack: overlays push a close handler and the back listener pops it first. Optionally use `history.pushState` when an overlay opens.
  - Remove the `onBackPressed` override (Capacitor's App plugin already handles it).
  - Ask for confirmation before leaving dirty forms.
- **Effort:** S–M

### MOB-14 No push notifications, no Android deep links
- **Severity:** P2
- **Evidence:**
  - No `@capacitor/push-notifications` and no `google-services.json` (`android/app/build.gradle` tail logs "Push Notifications won't work").
  - No `<intent-filter>` for App Links or a custom scheme in `AndroidManifest.xml`; `custom_url_scheme` is set in `strings.xml` but unused.
  - Electron, by contrast, registers `sroor://` (`desktop/main.js:27-40`).
- **Problem:** There are no low-stock, shift or approval alerts to managers' phones, and workspace-invite links cannot open the app.
- **Fix:** Add FCM push through a backend notifications service, and Android App Links (`assetlinks.json` on the central host) for `/connect?tenant=` and invoice links.
- **Effort:** M

### MOB-15 iOS absent
- **Severity:** P2 (business decision)
- **Evidence:** There is no `backend/ios/` and no `@capacitor/ios`. The comments in `useNativeBridge.js` mention iOS.
- **Problem:** iPad is a common POS device in GCC cafes, so this is a missing market.
- **Fix:** Decide on scope. If yes, add `@capacitor/ios`, a signing team, ATS, App Store privacy labels and an iPad layout pass. Apple rejects thin remote-URL wrappers more often, so bundle the web assets (`webDir`) for iOS.
- **Effort:** L

### MOB-16 Electron packaging: no icon, unsigned, Windows-only
- **Severity:** P1
- **Evidence:**
  - `desktop/package.json` `build.win.icon: "build/icon.ico"`, but `desktop/build/` does not exist.
  - No code-signing config.
  - `desktop/src/config/updatePolicy.js:18` has `requireAuthenticode: false` (deliberate, pending the certificate).
  - Only `win` targets (nsis x64/ia32, portable).
  - The `appId` (`com.baraasolutions.sroorerp`) differs from Android's.
- **Problem:**
  - Installers carry the default Electron icon.
  - SmartScreen shows "Unknown publisher", so every update re-prompts and store owners hesitate to install.
  - The update chain relies on SHA-256 only until a certificate is bought.
- **Fix:** Add `build/icon.ico` (256 px multi-size), buy an EV or OV code-signing certificate (or use Azure Trusted Signing), then flip `requireAuthenticode` and pin the thumbprint. Decide on macOS: if needed, add `dmg` plus notarization.
- **Effort:** S (icon) / M (signing)

### MOB-17 Desktop UI strings and hub URL hardcoded
- **Severity:** P2
- **Evidence:**
  - `Components/Common/DesktopTitlebar.vue:33-71` and `Components/Common/DesktopShortcutsModal.vue:2-30` contain Arabic literals.
  - `views/Auth/LoginView.vue:297` hardcodes `https://baraa-solutions.com/connect`.
  - `App.vue:132-137` binds F12 to the cash drawer, which overrides DevTools expectations.
- **Fix:** Translate these strings and read the hub URL from Electron settings or server config. F12 is fine for kiosks, but document it in the shortcuts modal.
- **Effort:** S

### MOB-18 Version numbering is split across three schemes
- **Severity:** P3
- **Evidence:**
  - Android `versionCode 3 / versionName 1.0.2` (`android/app/build.gradle`);
  - web `resources/js/version.json` `1.0.135 / 135`;
  - desktop `package.json` `1.0.0`;
  - the server `app_versions` compares `semverCode`.
- **Problem:** Support cannot tell which shell and which web build a user is running. The updater compares mismatched numbers.
- **Fix:** Use one release version from CI, stamped into all three (Gradle from env, electron-builder `extraMetadata.version`, `version.json`), and show "shell vX / web vY" in Profile.
- **Effort:** S

---

## Compact table

| ID | Severity | Title | Effort |
|---|---|---|---|
| FE-01 | P0 | Fake hardcoded notifications shown to every tenant | S |
| MOB-01 | P0 | Default Capacitor launcher icon and splash | S |
| MOB-02 | P0 | targetSdk 34 below Play minimum | S–M |
| MOB-03 | P0 (Play) | Self-updating APK + REQUEST_INSTALL_PACKAGES violates Play policy | M |
| MOB-04 | P0 | No release signing / AAB / R8 / versioning pipeline | M |
| MOB-05 | P0 | No privacy policy, data-safety or account-deletion URL | S–M |
| MOB-06 | P1 (P0 decision) | App wired to one customer host; immutable package id typo; tenant brand | S |
| FE-02 | P1 | UI ignores permissions (nav, launcher, row actions) | M |
| FE-03 | P1 | Plan/module gating not wired (static modules.json, FeatureGate unused) | M |
| FE-04 | P1 | English/LTR unsupported (hardcoded RTL, Arabic nav, no switcher) | M–L |
| FE-05 | P1 | Currency label hardcoded EGP in 203 places | M |
| FE-06 | P1 | Dates computed in UTC (32 sites) | S–M |
| FE-07 | P1 | Pick-lists silently capped at 100–300 records | M |
| FE-08 | P1 | No new-tenant onboarding; connect screen leaks a customer code | M |
| FE-09 | P1/P2 | Missing error states (failures render as zeros) | M |
| FE-10 | P1 | Invoices page: fake branch filter, page-only CSV, CSV injection, dead deep link | M |
| FE-11 | P1 | Branch switcher and shift badge hidden below 1024 px | S |
| FE-12 | P1 | Hardcoded strings in 28 files + emoji icons | M |
| FE-13 | P1 | 686 kB shared chunk (translations ×3), double boot request, CDN fonts | M |
| MOB-07 | P1 | Cleartext allowed, allowBackup with token, broad FileProvider | S |
| MOB-08 | P1 | No offline / no-network launch page | S / L |
| MOB-09 | P1 | Unused permissions and plugins; no barcode camera | S / M |
| MOB-10 | P1 (P0 for Android POS) | Print, download and share broken in Android WebView | M |
| MOB-11 | P1 | Edge-to-edge, safe areas, status bar theme | S–M |
| MOB-16 | P1 | Electron: missing icon, unsigned, Windows-only | S / M |
| FE-14 | P2 | ~4,400 lines dead code and duplicate components | S |
| FE-15 | P2 | Thin-view violations (Login, Invoices, Items, Stores…) | M–L |
| FE-17 | P2 | Accessibility (zoom disabled, modal semantics, small targets/text) | M |
| FE-18 | P2 | Raw tables without mobile handling | M |
| FE-19 | P2 | Router meta inconsistencies, no 404 | S |
| FE-20 | P2 | Per-row native network listeners leak | S |
| FE-22 | P2 | Login leftovers (hardcoded hub URL, triple error, no reset) | S / M |
| MOB-12 | P2 | Keyboard overlaps bottom nav and sheets | S |
| MOB-13 | P2 | Back button ignores open overlays | S–M |
| MOB-14 | P2 | No push notifications, no Android deep links | M |
| MOB-15 | P2 | iOS absent | L |
| MOB-17 | P2 | Desktop strings and hub URL hardcoded | S |
| FE-16 | P3 | Direct Swal usage bypasses helpers/alert and useDeleteHandler | S–M |
| FE-21 | P3 | Legacy service worker and 87 MB APKs in web root | S |
| MOB-18 | P3 | Three unrelated version schemes | S |

## Backend dependencies surfaced (for `backend-architect`)
- `/system/context`:
  - a real `notifications` feed (FE-01);
  - `system.currency` code and symbol (FE-05);
  - `tenant.plan.features` / `enabled_features` (FE-03);
  - `setup_progress` counts (FE-08);
  - an option to omit `translations` when the client already has the current locale hash (FE-13).
- Exports and bulk actions:
  - a server-side invoices export endpoint with filters (FE-10, MOB-10);
  - a bulk-cancel endpoint with per-id results (FE-10).
- Auth:
  - a tenant-user password reset (FE-22);
  - a revocable biometric device token (FE-22, APP-5).
- App updates: an `android_play` platform in `/app/check-update` (MOB-03).
- Public pages and links:
  - `/privacy` and `/account-deletion` pages (MOB-05);
  - `assetlinks.json` (MOB-14).
