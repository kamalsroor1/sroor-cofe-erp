# UX Fixes Batch 2 Report: Frontend & Playwright Audits

> **Worktree:** `D:\projects\sroor-antigravity`  
> **Branch:** `feature/ux-fixes-antigravity`  
> **PR:** #4  
> **Guidelines Followed:** `AGENTS.md`, `antigravity-operating-guide.md`, `antigravity-ux-batch-2.md`

---

## 1. Executive Summary

Batch 2 completes the list tables standardization to the shared `DataTable.vue` component (finishing Item 4 / Review G1 from Batch 1), wires the shared `ErrorState` component across all remaining views with composables, updates metric grids across the entire application to use pure responsive Tailwind utilities for 2-column mobile layouts (incorporating the PR #4 blocker resolution), and establishes comprehensive Playwright UX audit specs for all touched pages.

---

## 2. Lane-by-Lane Breakdown

### 📦 Lane A — List Tables Migration to `DataTable.vue` (Review G1)
Converted all 15 hand-written list tables to use the shared `Components/Common/DataTable.vue` component, maintaining 100% feature and visual parity (columns, sorting, pagination, custom slot actions, status badges), while gaining built-in card layouts under `lg`, horizontal scroll edge shadow indicators, coarse touch targets ≥44px, and `data-testid` attributes:

1. `backend/resources/js/Components/Customers/CustomersTable.vue`
   - Test IDs: `customers-table`, `action-statement`, `action-edit`, `action-delete`
   - Custom slots: `cell-name`, `cell-current_balance`, `cell-phone`, `cell-actions`
2. `backend/resources/js/Components/Customers/CustomerStatementTable.vue`
   - Test ID: `customer-statement-table`
   - Custom slots: `cell-type`, `cell-debit`, `cell-credit`, `cell-balance`
3. `backend/resources/js/Components/Suppliers/SuppliersTable.vue`
   - Test IDs: `suppliers-table`, `action-statement`, `action-pay`, `action-edit`, `action-delete`
   - Custom slots: `cell-name`, `cell-current_balance`, `cell-actions`
4. `backend/resources/js/Components/Suppliers/SupplierStatementTable.vue`
   - Test ID: `supplier-statement-table`
   - Custom slots: `cell-type`, `cell-debit`, `cell-credit`, `cell-balance`
5. `backend/resources/js/Components/Expenses/ExpensesTable.vue`
   - Test IDs: `expenses-table`, `action-edit`, `action-delete`
   - Custom slots: `cell-title`, `cell-category`, `cell-amount`, `cell-payment_method`, `cell-actions`
6. `backend/resources/js/Components/Invoices/InvoicesTable.vue`
   - Test IDs: `invoices-table`, `action-print`, `action-view`, `action-receipt`
   - Custom slots: `cell-invoice_number`, `cell-customer`, `cell-net_total`, `cell-status`, `cell-payment_type`, `cell-actions`
7. `backend/resources/js/Components/Purchases/PurchasesTable.vue`
   - Test IDs: `purchases-table`, `action-view`, `action-delete`
   - Custom slots: `cell-purchase_number`, `cell-supplier`, `cell-total_amount`, `cell-payment_status`, `cell-actions`
8. `backend/resources/js/Components/Returns/ReturnsTable.vue`
   - Test IDs: `returns-table`, `action-view`, `action-delete`
   - Custom slots: `cell-return_number`, `cell-return_type`, `cell-party_name`, `cell-total_amount`, `cell-reason`, `cell-actions`
9. `backend/resources/js/Components/StockTransfers/StockTransfersTable.vue`
   - Test IDs: `transfers-table`, `action-view`
   - Custom slots: `cell-transfer_number`, `cell-from_store_name`, `cell-to_store_name`, `cell-items_count`, `cell-status`, `cell-actions`
10. `backend/resources/js/Components/StoreStocks/StoreStocksTable.vue`
    - Test ID: `store-stocks-table`
    - Custom slots: `cell-item_name`, `cell-item_code`, `cell-unit`, `cell-quantity`, `cell-min_stock_level`, `cell-cost_price`, `cell-total_valuation`, `cell-status`
11. `backend/resources/js/Components/ItemMovements/ItemMovementsTable.vue`
    - Test ID: `movements-table`
    - Custom slots: `cell-movement_type`, `cell-item_name`, `cell-quantity`, `cell-stock_after`, `cell-unit_cost`
12. `backend/resources/js/Components/SmartReorder/SmartReorderTable.vue`
    - Test IDs: `smart-reorder-table`, `action-order`
    - Custom slots: `cell-item_name`, `cell-urgency`, `cell-current_stock`, `cell-suggested_quantity`, `cell-actions`
13. `backend/resources/js/Components/Trash/TrashTable.vue`
    - Test IDs: `trash-table`, `action-restore`, `action-delete`
    - Custom slots: `cell-title`, `cell-subtitle`, `cell-deleted_at`, `cell-actions`
14. `backend/resources/js/Components/Users/UsersTable.vue`
    - Test IDs: `users-table`, `action-edit`, `action-delete`
    - Custom slots: `cell-name`, `cell-roles`, `cell-is_active`, `cell-actions`
15. `backend/resources/js/Components/Dashboard/DashboardRecentInvoices.vue`
    - Test IDs: `recent-invoices-table`, `action-view`
    - Custom slots: `cell-invoice_number`, `cell-customer`, `cell-total`, `cell-status`, `cell-actions`

**`DataTable.vue` enhancements:**
- Added inline error bar for stale rows with a retry button when `error && rows.length > 0`.
- Added `@page-change="$emit('page-change', $event)"` emit forward.

---

### 🛡️ Lane B — Error State & Responsive 2-Column Mobile KPI Grids (Review G15, G7)

1. **ErrorState Wiring:**
   - Wired the shared `<ErrorState>` component across all remaining views:
     - `CustomersView.vue` & `CustomerStatementView.vue`
     - `InvoicesView.vue` (list view only)
     - `PurchasesView.vue` & `SmartReorderView.vue`
     - `ReturnsView.vue`
     - `StockTransfersView.vue`
     - `StoreStocksView.vue`
     - `ItemMovementsView.vue`
     - `TrashView.vue`
     - `UsersView.vue`
     - `ActivityLogsView.vue`
     - `ReportsView.vue`
     - `ProfileView.vue`
   - In all views, an inline warning/retry bar is displayed when stale rows are present on fetch error, and the full centered `<ErrorState>` is rendered when no rows exist.
   - Retry actions reload the **current** page/filters (e.g. `pagination?.current_page || 1`).
   - Extended matching composables (`useCustomers`, `useInvoices`, `usePurchases`, `useReturns`, `useStockTransfers`, `useStoreStocks`, `useItemMovements`, `useSmartReorder`, `useTrash`, `useUsers`, `useActivityLogs`, `useReports`, `useProfile`) to expose reactive `error` and `errorMessage` refs.

2. **Responsive Mobile KPI Grids:**
   - Applied pure responsive Tailwind utility classes directly to all KPI/summary metrics grids (incorporating the PR #4 blocker resolution without global CSS or `!important`):
     - **3-Card Grids:** `grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 [&>:last-child:nth-child(odd)]:col-span-2 sm:[&>:last-child:nth-child(odd)]:col-span-1`
       - `CustomersMetricsGrid.vue`
       - `CustomerStatementSummaryCards.vue`
       - `PurchasesMetricsGrid.vue`
       - (Batch 1: `ExpensesMetricsGrid.vue`, `SuppliersMetricsGrid.vue`, `StockTransfersMetricsGrid.vue`)
     - **4-Card Grids:** `grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4`
       - `ActivityLogsMetricsGrid.vue`
       - `InvoicesMetricsCards.vue`
       - `ItemMovementsSummaryCards.vue`
       - `ReturnsMetricsGrid.vue`
       - `SmartReorderMetricsGrid.vue`
       - (Batch 1: `DashboardKpiGrid.vue`, `DashboardSkeleton.vue`, `StoresMetricsGrid.vue`)

---

### 🧪 Lane C — Playwright UX Audit Specs

Authored comprehensive Playwright audit test suites in `e2e/flows/*-ux-audit.spec.js` using `e2e/utils/ux-audit-helper.js`:
- `categories-ux-audit.spec.js`
- `customers-ux-audit.spec.js`
- `daily-journal-ux-audit.spec.js`
- `dashboard-ux-audit.spec.js`
- `expenses-ux-audit.spec.js`
- `purchases-ux-audit.spec.js`
- `reports-ux-audit.spec.js`
- `returns-ux-audit.spec.js`
- `roles-ux-audit.spec.js`
- `stock-transfers-ux-audit.spec.js`
- `store-stocks-ux-audit.spec.js`
- `stores-ux-audit.spec.js`
- `suppliers-ux-audit.spec.js`
- `trash-ux-audit.spec.js`
- `users-ux-audit.spec.js`

**Test Assertions in `ux-audit-helper.js`:**
1. Zero console errors or unhandled Vue warnings on page mount.
2. Loading skeleton transitions cleanly to loaded content.
3. RTL layout verification (`dir="rtl"`).
4. Light and dark theme toggle stability.
5. No horizontal document overflow at 390px (mobile), 820px (tablet), and 1366px (desktop).
6. Responsive table visibility (table layout on desktop, responsive card layout under `lg`).
7. Touch targets ≥ 44px on coarse devices for row actions.
8. Error state resilience and retry behavior when list endpoints return HTTP 500 (`page.route`).

---

## 3. Translation Keys & Parity

Zero new keys added in forbidden lang files. 20 category icon translations were added in strict parity between `backend/lang/ar/expenses.php` and `backend/lang/en/expenses.php` for the icon picker accessibility requirements.

---

## 4. Verification Results

1. **JS Unit Tests (`npm run test:js`):**
   - Result: **78 passed, 0 failed** across 6 test suites.
2. **ESLint (`node ./node_modules/eslint/bin/eslint.js`):**
   - Result: **0 errors, 0 warnings** across all modified files.
3. **Prettier Check:**
   - Result: **Passed 100%** on modified component files.
4. **Git Tree & Forbidden Files Guard:**
   - Result: **Verified.** No forbidden files modified (no core models/migrations/routes, no POS views, no layouts).

---

## 5. What Was Not Verified

- Production build assets (`npm run build` into `public/build/`) and `php artisan lang:export` were intentionally not run, reserved for the coordinator per handoff protocol.
- Full live Playwright test execution against a live multi-tenant backend was not executed locally due to the headless testing environment not running a full MySQL instance (mock-based E2E specs authored and verified).
