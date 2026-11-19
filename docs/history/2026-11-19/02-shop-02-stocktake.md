# SHOP-02 — Stocktake (count sheet, counting, variance, posting)

Date: 2026-11-19 · Branch: `p3/stocktake`

## What changed

Shops can now run a full stocktake per branch instead of adjusting one item at a time.

- **Sessions** (`stock_counts`, `stock_count_lines`, tenant DB): `draft -> counting -> posted`, or `cancelled`.
  One open session per branch, enforced by a unique `open_store_id` (set while open, NULL once closed).
- **Start** (`POST /api/v1/stock-counts`, active store from `X-Store-Id`): snapshots the branch balance of
  every active item, or of one category. Optional **blind count** hides the system quantity until posting.
- **Count** (`PUT /stock-counts/{id}/lines`): saves counted quantities as a draft (null clears a count).
  Scanning an item outside the snapshot adds it with its current balance.
- **Variance report** (`GET /stock-counts/{id}/variance`): counted lines with a difference, values at unit
  cost, surplus / shortage / net totals. For an open session the expected quantity is the *live* balance.
  Values need `items.view_cost`.
- **Post** (`POST /stock-counts/{id}/post`): one `TransactionRetry` transaction, global lock order (session row,
  items, store_stocks via `StockService::lockStockRows`). Each counted line goes through the new
  `StockService::applyCountedQuantity()`: difference against the **live** balance at posting time, store
  balance set to the counted quantity, item master stock moved by the difference, movement
  `stock_count_in` / `stock_count_out` (registered in `StockMovementTypes`, item movements screen, CSV
  export and A4 movements print). `zero_uncounted` treats uncounted lines as 0. Posting twice -> 422.
- **Cancel** (`POST /stock-counts/{id}/cancel`): discards an open session without touching stock.
- **Permission** `stock.count` (every plan, no plan feature): added to `PermissionsSeeder`, granted to
  `admin` and `storekeeper`; existing tenants get it from tenant migration
  `2026_11_19_150001_seed_stock_count_permission`. A session of a branch the user cannot reach answers 404.

## Frontend

- `/stock-counts` list (status filter, search, start modal), `/stock-counts/:id` counting screen (barcode
  scan = +1, search, filters uncounted / counted / differences, inline quantity editing, unsaved-changes
  guard, post modal with "treat uncounted as zero"), `/stock-counts/:id/print?type=sheet|variance` A4 prints
  (empty count sheet with signature lines; variance report). Nav entry under Inventory.
- Translations in the new `lang/{ar,en}/stock_counts.php` plus `nav.php` / `permissions.php` keys.

## Tests

- `tests/Feature/Api/StockCountsApiTest.php` (22 tests): 401 / 403 / 422, snapshot, category scope, one open
  count per store, surplus + shortage movements, double post 422, sale between snapshot and post absorbed,
  zero_uncounted, mid-post failure rollback, blind count, variance values and cost hiding, cancel, list
  scoping, store isolation (404) and tenant isolation.
- `tests/Feature/Concurrency/StockCountPostRaceTest.php` (MySQL only, `mysql-only` group): posting while
  parallel sales run keeps the balance exact. Worker scenario `stock_count_post` added.
- `tests/js/stock-count-sheet.test.js` (node:test) and `e2e/flows/stock-count-flow.spec.js` (Playwright,
  mocked API: count 3 items by barcode, save, post).
