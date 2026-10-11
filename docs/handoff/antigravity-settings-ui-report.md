# Settings UI & Branding Implementation Report (SETG-5, SETG-10, BRND-3, BRND-5)

**Target Worktree:** `D:\projects\sroor-antigravity-settings`  
**Target Branch:** `feature/settings-ui-antigravity` -> Base: `feature/multi-tenant`  
**Related Tasks & Issues:** Issue #6, SETG-5 (Lanes A, B, C), SETG-10 (Server Units), BRND-3 / BRND-5 (Branding Tab & Dual Logo Management)

---

## 1. Overview & Executive Summary

This deliverable fulfills the modern redesign of the Sroor ERP Settings screen according to the catalog specifications in `docs/02-requirements/tenant-settings-catalog.md` §4 and backend contract `docs/handoff/settings-branding-backend-contract.md`.

Key achievements:
- **Thin Orchestrator:** `SettingsView.vue` refactored to an orchestrated container with modular sub-components.
- **Lane A (Shell, Search, Deep Linking):**
  - Responsive RTL tab navigation (`SettingsTabsNav.vue`) with active tab states and badge indicators.
  - Search engine (`SettingsSearchBar.vue`, `useSettingsSearch.js`) filtering settings across label, description, and keywords; direct navigation with DOM scroll and highlight flash.
  - Deep-link support (`/settings?k=<setting_key>` and `?tab=<tab_id>`) managed through `useSettingsShell.js`.
- **Lane B (General & Inventory Tabs):**
  - General tab (`GeneralTab.vue`): Shop identity, currency selector with allowlist enforcement, IANA timezone selector, business-day cutoff (`HH:MM`) with business day explanation, and legal invoice details (Commercial Register & Tax Reg No).
  - Inventory tab (`InventoryTab.vue`, `InventoryUnitsSection.vue`, `InventoryThresholdSection.vue`): Approved measurement units editor (add/remove/reorder) backed by server units, and default low-stock threshold with 3 decimal precision (`helpers/decimal.js`).
- **Branding Tab (BRND-3 / BRND-5):**
  - Dedicated Branding tab (`BrandingTab.vue`) featuring dual logo upload & removal for light and dark modes via `POST/DELETE /api/v1/settings/branding/logo/{variant}`.
  - Instant image previews from returned URLs and translated 422 validation errors rendered inline under upload dropzones (`data-testid="logo-light-error"`, `data-testid="logo-dark-error"`).
  - System theme color palette selector + custom hex input (`#RRGGBB`).
  - Receipt header lines (multiline textarea, max 6 lines x 80 chars, tag-sanitized) and receipt footer text (max 500 chars).
- **Units from Server (SETG-10):**
  - Eliminated hardcoded Arabic unit literals and default presets in `useSettings.js` and `InventoryUnitsSection.vue`.
  - Units are dynamically loaded from `useUnits()` / `system.inventory_units`.
  - Removed `Composables/useSettings.js` from `ALLOWLIST` in both `tests/js/no-hardcoded-units.test.js` and `tests/js/no-raw-ar-locale.test.js` (ALLOWLIST is now completely empty).
- **Lane C & E2E Verification:**
  - Extended Playwright end-to-end suite `e2e/flows/settings-shell.spec.js` covering PNG logo upload preview, SVG upload 422 inline rejection, and receipt header persistence across full page reloads.

---

## 2. Architecture & File Structure

```text
backend/resources/js/
├── views/Settings/
│   └── SettingsView.vue                               # Orchestrator view
├── Components/Settings/
│   ├── Shell/
│   │   ├── SettingsTabsNav.vue                        # RTL tabs navigation
│   │   └── SettingsSearchBar.vue                      # Search dropdown & shortcut
│   ├── General/
│   │   └── GeneralTab.vue                             # Identity, currency, timezone, cutoff, legal
│   ├── Inventory/
│   │   ├── InventoryTab.vue                           # Inventory settings container
│   │   ├── InventoryUnitsSection.vue                  # Reorderable unit chips & add unit form
│   │   └── InventoryThresholdSection.vue              # Default low-stock threshold
│   ├── BrandingTab.vue                                # Dual logo upload, theme color, receipt header/footer
│   ├── DeviceTab.vue                                  # Device & hardware tab
│   ├── BackupTab.vue                                  # Database backup actions
│   ├── SettingsAppearanceSection.vue                  # Theme & dark mode preferences
│   ├── SettingsPrintingSection.vue                    # Receipt printer configuration
│   └── SettingsTelegramSection.vue                    # Telegram bot & chat alerts
├── Composables/
│   ├── useSettingsShell.js                            # Tab selection, deep-link parsing, dirty guard
│   ├── useSettingsSearch.js                           # Keyword search index & deep scroll
│   └── useSettings.js                                 # API communication, logo upload/delete, state
```

---

## 3. Strict Compliance & Boundary Verification

- **PHP Restrictions:** Zero PHP modified outside `backend/lang/ar/settings.php` and `backend/lang/en/settings.php`.
- **Forbidden Files:** Untouched: `resources/js/router/index.js`, `resources/js/stores/auth.js`, `resources/js/Services/api.js`, `resources/js/Services/centralApi.js`, `public/build/**`, `defaultTranslations.*`.
- **Formatting & Helpers:** All financial numbers and decimal inputs adhere strictly to `helpers/decimal.js` and `useFormatters()`.
- **Localization Gate:** 100% of visible strings use Laravel translation keys (`$t('settings.*')`, `$t('branding.attributes.*')`). Strict parity maintained between Arabic (`backend/lang/ar/settings.php`) and English (`backend/lang/en/settings.php`).

---

## 4. Quality Gate & Test Evidence

### A. JavaScript Linting & Formatting
- **ESLint (`npx eslint --config eslint.config.js`):**
  ```text
  Passed: 0 errors, 0 warnings across all modified components and composables.
  ```
- **Prettier (`npx prettier --check`):**
  ```text
  Checking formatting...
  All matched files use Prettier code style!
  ```

### B. JavaScript Unit Tests (`node --test "tests/js/*.test.js"`)
- `tests/js/no-hardcoded-units.test.js`: **PASSED** (Allowlist empty: `ALLOWLIST = []`).
- `tests/js/no-raw-ar-locale.test.js`: **PASSED** (Allowlist empty: `ALLOWLIST = []`).
- Entire suite: **129 tests passed, 0 failed**.

### C. Backend Code Style & Translations
- **Laravel Pint (`php vendor/bin/pint --test lang`):**
  ```text
  {"tool":"pint","result":"passed"}
  ```
- **PHPUnit Translation & Settings Tests:**
  `php artisan test --filter="LangKeyParityTest|SpaTranslationKeysExistTest|TenantBrandingApiTest|SettingApiTest"`
  ```text
  Tests: 117 passed (15,024 assertions)
  Duration: ~110s
  ```

### D. Production Vite Build
- **Vite Build (`npx vite build --outDir ../../sroor-ag-build --emptyOutDir`):**
  ```text
  ✓ 250 modules transformed.
  rendering chunks...
  computing gzip size...
  built in 44.95s
  ```

### E. E2E Playwright Suite (`e2e/flows/settings-shell.spec.js`)
- Tests added / verified:
  1. Deep-link navigation via query param `?k=locale.business_day_cutoff` scrolls and highlights target field.
  2. Saving valid business-day cutoff persists and returns 200.
  3. Invalid cutoff returns 422 and renders translated error under input.
  4. Unsaved changes warning triggers dirty leave dialog.
  5. Mobile tab switcher switches between list view and section details view.
  6. Dual logo cards render preview on valid PNG upload via `/api/v1/settings/branding/logo/{variant}`.
  7. Invalid SVG upload renders translated 422 error under field (`data-testid="logo-light-error"`).
  8. Receipt header lines persist across reload and maintain line breaks without HTML tags.

---

## 5. Out of Scope & Future Work
The following items remain intentionally out of scope pending backend support:
- Branch-level settings overrides (multi-branch override matrix).
- Plan/platform feature locks.
- Advanced control constraints (`settings.controls.manage` for discount caps and negative stock rules).
- Custom invoice print template designer.
- Audit trail display ("last changed by").
