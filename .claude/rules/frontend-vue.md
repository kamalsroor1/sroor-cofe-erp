---
paths:
  - "backend/resources/js/**"
  - "backend/resources/css/**"
  - "backend/vite.config.js"
---

# Frontend rules (pure Vue 3 SPA)

Vue 3 `<script setup>` + Composition API, Pinia, Vue Router, Tailwind v4, axios. Plain JavaScript (no TypeScript). **No Inertia, no Livewire, no Blade pages, no Options API, no jQuery.**

## Layering

| Folder | Responsibility | Must NOT |
|---|---|---|
| `views/<Feature>/<Feature>View.vue` | thin orchestrator: call composable/store, hold `isLoading`/`error`, lay out the page grid, pass props, handle emitted events | contain tables, long forms, charts, formatting functions |
| `Components/<Feature>/` | single-responsibility pieces of that page (table, filters, form modal, KPI grid…) | call the API directly when the view already owns the data |
| `Components/Common/`, `Components/Form/` | generic reusable UI (`DataTable`, `AppModal`, `EmptyState`, `MetricCard`, `BaseInput`, `BaseSelect`, skeletons) | know about any specific feature |
| `Composables/use<Feature>.js` | data fetching, state, business-ish UI logic for a feature | render anything |
| `Services/api.js` (tenant) / `Services/centralApi.js` (platform console) | the **only** two axios instances | mix: tenant code never imports `centralApi`, super-admin code never imports `api` |
| `stores/` (Pinia) | truly global state only: `auth`, `appConfig`, `tabs`, `centralAuth` (platform console) | become a dumping ground for page state |

- **View size target: 50–80 lines.** If a view grows past ~100, extract. Existing oversized views (`PosView.vue`, `LoginView.vue`, `ItemsView.vue`…) are legacy — shrink them when you touch them *for that purpose*, not as a drive-by.
- Before creating a component, search `Components/Common/` and `Components/Form/` — reuse first. Don't build a second modal, table, select, or date picker.

## API access
- Always `import api from '@/Services/api'` (or the relative path used in neighbouring files); super-admin views/composables use `Services/centralApi.js` instead (own token, no `X-Tenant`/`X-Store-Id`, step-up retry). Never another `axios.create`, never raw `fetch` for app APIs.
- The interceptor already attaches `Authorization`, `X-Store-Id`, `X-Tenant`, `X-Locale`, and handles 401 redirect + global error toasts. Don't re-implement those per call and don't double-toast an error the interceptor already showed.
- Base URL is `/api/v1`. Backend envelope: `{ success, message, data }`; paginated lists carry `meta`.

## Money on the frontend
- Display via `useMoney()` / `useFormatters()` (`formatMoney`, `formatQty`, `formatPercent`). Don't hand-roll `toFixed`.
- The server is the source of truth for totals, tax, discounts, balances. Client-side math is only for live previews (POS cart) and must be re-validated by the server response.
- Send money/qty to the API as strings or plain numbers with ≤ 3 decimals; never send a locale-formatted string (`"1,250.50"`).

## UI standards (every screen)
1. **RTL-first.** Use logical utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`, `text-start`, `text-end`, `start-0`, `end-0`), not `ml/mr/left/right`. Verify in both `ar` and `en`.
2. **Dark + light.** Every color choice has a `dark:` counterpart. No hardcoded hex for brand color — use `var(--color-primary)`, `var(--color-primary-light)`, `var(--color-primary-border)` so tenant theming works.
3. **Skeleton shimmer loaders** shaped like the real content for every page, table, and card grid. Never a blank screen; a lone spinner is only OK inside a button.
4. **Empty & error states** via `EmptyState` — a list is never just blank.
5. **Touch-friendly**: tap targets ≥ 44px, no hover-only actions, works on POS touch screens. Check the 5 widths: 360, 768, 1024, 1280 and tablet landscape.
6. **POS** forces the sidebar into mini mode (`w-20`) — keep that behaviour.
7. **Layouts are separated**: `SpaLayout.vue` (tenant/POS), `SuperAdminLayout.vue` (platform), guest views (no sidebar). Don't mix super-admin UI into tenant layout.
   - **No `v-html` with tenant data on super-admin screens** (IDEN-1.9). Tenant-supplied values (store name, email, settings, notes…) render through `{{ }}` interpolation only. Server-generated markup such as the 2FA QR SVG is shown as an `<img>` with a data URL, never with `v-html`.
8. **Permissions in UI**: hide/disable actions the user can't perform (auth store permissions), and gate plan features with `FeatureGate.vue` / `useModules`. UI hiding is cosmetic — the backend still enforces.
9. **Feedback**: toasts/confirmations through `helpers/alert.js` (SweetAlert2); destructive actions via `useDeleteHandler`.
10. **Icons**: `lucide-vue-next` only. No emoji as icons in UI.
11. **Accessibility basics**: labels on inputs, `aria-label` on icon-only buttons, focus visible, modals trap focus and close on Esc.

## Component conventions
- `<script setup>` first, then `<template>`, then (rarely) `<style scoped>`. Tailwind classes over custom CSS.
- `defineProps` with types + defaults; `defineEmits` declared explicitly. Every declared prop is used, every passed prop is declared (no Vue prop warnings — the console must be clean).
- Single root element in views used inside `<Transition>`/`<KeepAlive>` (see the PosView fix).
- Names: `PascalCase.vue`, feature-prefixed (`CustomersTable.vue`, `CustomerFormModal.vue`); composables `useXxx.js` returning refs + functions.
- No `console.log` left behind. No dead/commented-out blocks. Don't create `*.legacy.*` copies — git is the backup.

## Native shells
- Capacitor (Android) and Electron (desktop) features go through `useNativeBridge`, `useDesktopHardware`, `useBiometricAuth`, `useAppUpdater`. Always feature-detect; the same bundle runs in a plain browser.

## Done means
`npm run build` (from `backend/`) passes with zero errors, `npm run lint:dirty` adds no new errors and `npm run format:dirty` has formatted only the files you touched, the browser console has no warnings on the touched page, all text goes through `$t()`/`t()`, and the page works in ar+en, dark+light, mobile+desktop.
