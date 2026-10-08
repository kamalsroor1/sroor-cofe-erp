---
paths:
  - "backend/app/**/*.php"
  - "backend/routes/**/*.php"
  - "backend/config/tenancy.php"
  - "backend/database/**/*.php"
---

# Multi-tenancy rules (stancl/tenancy v3, database-per-tenant)

## Mental model
- **Central DB**: `tenants`, `domains`, `plans`, `plan_features`, `subscriptions`, `app_versions`, central users. Migrations in `database/migrations/`.
- **Tenant DB** (one per tenant): everything operational — users, roles, stores, items, stock, invoices, treasury… Migrations in `database/migrations/tenant/`.
- When tenancy is initialized, the **default connection is the tenant DB**. Any model without a pinned connection reads/writes tenant data.

## Tenant resolution (see `app/Http/Middleware/ResolveApiTenancy.php`)
Order: already initialized by domain → `X-Tenant` header / `tenant` param → request host (domain record, then subdomain slug). Central routes (`api/v1/central/*`) bypass tenancy. Central hosts are listed in `config/tenancy.php` → `central_domains`.

## Hard rules
1. **Central models pin their connection.** Any model stored in the central DB that can be queried while a tenant is active must define:
   ```php
   public function getConnectionName(): ?string
   {
       return config('tenancy.database.central_connection', config('database.default'));
   }
   ```
   `AppVersion` and `CentralUser` already do; `Tenant`/`Domain` get it from stancl's base classes (`CentralConnection`). `Plan`, `PlanFeature`, `Subscription` currently do **not** — they are only safe while queried from central context; add the method before using them anywhere a tenant may be initialized. A central model without this = "table not found" in production.
2. **Never query tenant tables from central context** without `tenancy()->initialize($tenant)` or `$tenant->run(fn () => …)`. Always `tenancy()->end()` / use `run()` so context can't leak into the next operation.
3. **Never accept a tenant identifier from the client for authorization decisions.** `X-Tenant` selects the database; the Sanctum token must belong to a user *in that* database. Don't add endpoints that let a token from tenant A act on tenant B.
4. **No cross-tenant joins, caches, or files.** Cache keys, queue jobs, storage paths, and uploaded files must be tenant-scoped (stancl bootstrappers handle this when enabled — don't bypass them with hardcoded paths or a manually built cache key lacking the tenant id).
   **Cache scope (`App\Support\TenantCache`):** `key()/version()/bump()` derive the scope from the current context — use them only for data read and invalidated inside the same request context. Any invalidation of tenant data from a central context (super-admin, subscription change, central job) uses `keyFor/versionFor/bumpFor($tenantId, …)`. Any platform data read inside a tenant request (branding, plans) uses `centralKey/centralVersion/centralBump`, which are identical with tenancy initialized or ended.
5. **Queued jobs** that touch tenant data must be tenant-aware (dispatched from tenant context so stancl serializes the tenant, or explicitly `$tenant->run()` inside `handle()`).
6. **Super-admin endpoints** live under the central route group, are guarded by the super-admin gate, and iterate tenants with `Tenant::cursor()` + `$tenant->run()` — never by switching connections manually.
7. **Plan/feature gating**: check features through `TenantFeatureManager` (backend) and `FeatureGate.vue` / `useModules` (frontend). Don't hardcode plan names.
8. **Provisioning** (create DB, migrate, seed roles/permissions, first admin, main store) goes through `TenantProvisionerService` — keep it the single path so every tenant is born identical.

## Store (branch) scoping — second isolation layer inside a tenant
- The SPA sends `X-Store-Id`; `StoreScope` / `StoreAccess` middleware validate the user may access that store.
- Every operational query (stock, invoices, shifts, treasury, reports) filters by the active store unless the endpoint is explicitly cross-store and permission-gated.
- A user without access to a store must get 403 — not an empty list built from someone else's data.

## Testing tenancy
- The suite runs on sqlite `:memory:`; `Tests\TestCase::setUp()` runs the tenant migrations onto the default connection so tenant tables exist. Tests authenticate with Sanctum tokens (`createToken()->plainTextToken`) and seed `PermissionsSeeder`.
- Any new endpoint needs at least one isolation assertion: data created under another store/tenant context is **not** returned and **not** mutable.
