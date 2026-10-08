---
paths:
  - "backend/app/Actions/**/*.php"
  - "backend/app/Services/**/*.php"
  - "backend/app/DTOs/**/*.php"
  - "backend/app/Models/**/*.php"
  - "backend/app/Observers/**/*.php"
  - "backend/database/migrations/**/*.php"
---

# Financial & inventory integrity rules

This is an accounting system. A rounding error or a race condition is a **money bug**, not a style issue. These rules are non-negotiable.

## 1. Number representation
- DB columns for money, price, cost, quantity, weight, discount, balance: **`$table->decimal('col', 12, 3)`**. Never `float()`, `double()`, `unsignedInteger` for fractional quantities.
- PHP: values are **numeric strings**. Arithmetic only through bcmath with explicit scale 3:
  ```php
  $lineTotal = bcmul($dto->quantity, $dto->unitPrice, 3);
  $net       = bcsub($lineTotal, $discount, 3);
  if (bccomp($available, $requested, 3) < 0) { /* insufficient stock */ }
  ```
- Forbidden on money/qty: `+ - * /`, `round()`, `floatval()`, `(float)`, `number_format()` for storage, `==` / `<` comparisons (use `bccomp`).
- Percentages: compute as `bcdiv(bcmul($amount, $percent, 6), '100', 3)` — use a higher intermediate scale, then settle at 3.
- Eloquent casts: `'decimal:3'`. Fractional/weight selling (grams, kg) is a first-class feature — never assume integer quantities.
- Frontend may format with `Number()` for **display only** (`useMoney`, `useFormatters`). Authoritative totals always come from the server.

## 2. Transactions
- Anything that writes to stock, invoices, purchases, returns, payments, customer/supplier balances, treasury, or shifts runs inside **one** `DB::transaction()` that covers *all* related writes (document + items + stock movements + balance + treasury).
- A failure at any step must roll back everything. No partial invoices.
- No external I/O (HTTP, Telegram, file export) inside the transaction. Dispatch after commit.

## 3. Row locking
- Before decrementing/incrementing a balance you read, lock it:
  ```php
  $stock = StoreStock::where('store_id', $storeId)->where('item_id', $itemId)->lockForUpdate()->first();
  ```
- Applies to: `StoreStock`, customer/supplier `current_balance`, treasury/cash balances, shift totals, document number sequences.
- Lock rows in a **consistent order** (e.g. sort item ids ascending) when locking several, to avoid deadlocks.
- `lockForUpdate()` outside a transaction does nothing — it must be inside.

## 4. Stock is per store
- Stock lives in `StoreStock` (store × item), not on `Item`. Every movement records a `StockMovement` row (type, qty, store, reference document).
- Always resolve the active store from the request context (`X-Store-Id` via `StoreScope`/`StoreAccess`), never default silently to store 1.
- Transfers between stores: decrement source + increment destination + both movements in one transaction.
- Costing/profit logic (FIFO reconcile, landed costs, `total_cost` on invoices) lives in `StockService` / `ProfitService` / `PurchaseService`. Reuse; don't reimplement.

## 5. Documents
- Invoice/purchase/return numbers are unique **per branch**; generate them inside the transaction under a lock.
- Cancelling or editing a posted document must **reverse** its stock, balance, and treasury effects, then re-apply — never just overwrite totals.
- Deletion is soft delete; restoring re-validates stock availability.
- Zero/negative stock sale is blocked at the POS unless a setting explicitly allows it.

## 6. Migrations
- Central schema → `database/migrations/`. Tenant schema → `database/migrations/tenant/`. Putting a tenant table in the central folder (or vice versa) is a bug.
- **Never edit a migration that has shipped.** Add a new one. Production tenants already ran it.
- Every `up()` has a working `down()`. Add indexes for foreign keys and for columns used in report filters (dates, store_id, status).
- Tenant migrations run against *every* tenant DB on deploy — keep them idempotent-safe (`Schema::hasColumn` guards when altering) and fast.

## 7. Required tests for any change here
Happy path totals, fractional quantity (e.g. `0.250`), insufficient stock → rollback leaves stock untouched, cancel/restore reverses exactly, and — for anything touching locking — a concurrency test (see `tests/Feature/ConcurrencyTest.php`).
