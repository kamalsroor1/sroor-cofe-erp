---
paths:
  - "backend/app/**/*.php"
  - "backend/routes/**/*.php"
---

# Backend architecture rules

## Request lifecycle (the only accepted shape)

```
Route (/api/v1, auth:sanctum + tenancy + store middleware)
  → Controller method (thin)
      → FormRequest            validation + authorize()
      → DTO::fromArray($request->validated())
      → Action->execute($dto)  business logic, transactions
      → Resource / JsonResponse
```

## Controllers — `app/Http/Controllers/Api/`
- `declare(strict_types=1);`, `final class`, constructor-injected `private readonly` Actions.
- A method is ~5–15 lines. No `$request->validate()`, no `DB::` calls, no bcmath, no loops over business data.
- List endpoints: eager-load (`with`, `withCount`), `select()` only needed columns, always `paginate()` with `per_page` clamped (existing convention: `max(1, min(200, …))`).
- Multi-criteria filtering goes through `Illuminate\Pipeline\Pipeline` with classes in `app/Filters/<Domain>/` (see `app/Filters/Tenants/SearchFilter.php`: constructor takes `Request`, `handle(Builder $query, Closure $next)`).
- Response envelope is consistent: `{ success: bool, message?: string, data?: … }`. Errors use translated messages: `__('auth.unauthorized')`, never literals.
- Correct status codes: 200/201, 401 unauthenticated, 403 forbidden, 404, 422 validation, 409 business conflict (e.g. insufficient stock).

## Form Requests — `app/Http/Requests/`
- One per write endpoint: `Store<Noun>Request`, `Update<Noun>Request`, `<Verb><Noun>Request`.
- `authorize()` checks the permission (`$this->user()->can('customers.manage')`) — don't leave it `return true` when a permission exists.
- Numeric money/qty fields: `numeric`, `min:0` (or explicit allowed range), and `decimal:0,3` where precision matters.
- Use `Rule::unique()->ignore()` on updates; `exists:` rules resolve against the tenant connection automatically — don't hardcode a connection.
- Custom messages/attributes come from `lang/*/validation.php`, not inline strings.

## DTOs — `app/DTOs/<Domain>/`
- `final class`, constructor-promoted `public readonly` typed props, static `fromArray(array $data): self`, `toArray(): array`.
- **Money and quantity props are `string`** (e.g. `'0.000'`), never `float`. Cast at the boundary: `(string) $data['amount']`.
- Normalize empty strings to `null` in `fromArray()` (see `CustomerDTO`).

## Actions — `app/Actions/<Domain>/`
- Naming: `<Verb><Noun>Action` — `CreateCustomerAction`, `CancelPurchaseAction`.
- Exactly one public method: `execute(...)`, typed params and return type. Helpers are `private`.
- The Action owns the `DB::transaction()` boundary. Actions may call Services and other Actions; they never touch `Request`, `auth()` helpers beyond a passed-in user, or return HTTP responses.
- Throw domain exceptions (or `ValidationException::withMessages`) with translated messages; let the controller/handler map them to status codes.

## Services — `app/Services/`
- Existing engines (`StockService`, `InvoiceService`, `TreasuryService`, `ProfitService`, `CustomerBalanceService`…) stay as the shared core. Reuse them; **don't duplicate stock or balance math inside an Action**.
- Don't add new catch-all services. New operation → new Action. New *shared calculation* used by 2+ Actions → a focused service method.

## Models — `app/Models/`
- Only: `$fillable`/`$guarded`, `casts()`, relationships, scopes, tiny accessors. No business workflows.
- Money/qty casts: `'decimal:3'`. Never `'float'`.
- Never `$guarded = []` on models that accept request data.
- Central-DB models must implement `getConnectionName()` — see `multi-tenancy.md`.
- Soft deletes are system-wide (central recycle bin in `TrashController`); new tenant business models use `SoftDeletes` unless there's a reason not to.

## Side effects
- Audit/activity logging goes through `ActivityLogService` / `AuditLogService` or an Observer — not sprinkled ad hoc.
- Slow or external work (Telegram, backups, exports) → queued Job in `app/Jobs/`, dispatched **after commit** (`->afterCommit()` or `DB::afterCommit`).

## Style
- PSR-12 via Pint (`./vendor/bin/pint --dirty`). `declare(strict_types=1);` on every new file. Typed everything. Enums in `app/Enums/` instead of magic strings for statuses/types.
- Match the surrounding file's conventions before introducing a new one.

## Static analysis & CI gates
- New or changed PHP must pass **Larastan level 5** (`composer analyse`) **without adding entries to `phpstan-baseline.neon`**. The baseline only freezes legacy debt: fix the error (add relation return types, real types, null checks) instead of baselining it. Regenerating the baseline (`composer analyse:baseline`) is allowed only to *remove* fixed entries, and the diff must show no new ones.
- No `@phpstan-ignore*` comments to silence a new error unless the reviewer agrees it is a Larastan false positive, with a one-line reason.
- CI (`.github/workflows/ci.yml`) runs `pint --test` on every PHP file you touch — a touched legacy file must be fully Pint-clean, so run `./vendor/bin/pint --dirty` before committing.
