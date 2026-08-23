---
name: laravel-patterns
description: Comprehensive architectural enforcement guide for Laravel SOLID principles, Single Action Pattern, Pipeline Filters, DTOs, Form Requests, Observers, and Lean Models.
---

# 🏛️ Laravel Clean Architecture & Design Patterns Playbook

This skill defines the non-negotiable architectural standards, design patterns, and code structure rules for this Laravel codebase. All AI agents, contributors, and sessions MUST strictly follow these patterns when designing, writing, or refactoring code.

---

## 1. 🎯 Single Responsibility Principle & Single Action Pattern (`app/Actions/`)

### Rule:
* **NO Monolithic Service Classes:** Do not create fat service classes with 10+ methods (e.g. `UserService` with create, update, delete, activate, resetPassword, etc.).
* **One Action = One Class:** Every business operation, mutation, checkout, status change, or tenant provisioning MUST be encapsulated in a dedicated Action class in `app/Actions/{Domain}/`.
* **Single Entry Method:** The Action class must have only **ONE** public method: `execute(...)` or `__invoke(...)`.

### Example:
```php
namespace App\Actions\Tenants;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Models\Tenant;

class ProvisionTenantAction
{
    public function __construct(
        protected TenantProvisionerInterface $provisioner
    ) {}

    public function execute(CreateTenantDTO $dto): Tenant
    {
        return $this->provisioner->provision($dto);
    }
}
```

---

## 2. 🛡️ Form Request Pattern (`app/Http/Requests/`)

### Rule:
* **Zero `$request->validate()` in Controllers:** Controllers MUST NOT perform direct validation.
* Every HTTP mutation (`POST`, `PUT`, `PATCH`, `DELETE`) must use a dedicated `FormRequest` class in `app/Http/Requests/`.
* Validation rules, custom error messages, and authorization checks belong inside the Form Request.

---

## 3. 📦 Data Transfer Objects (`app/DTOs/`)

### Rule:
* **Strongly Typed Data:** Pass typed DTOs between Controllers, Form Requests, and Actions instead of loose associative arrays.
* DTOs use `public readonly` properties and provide a static `fromArray(array $data): self` or `fromRequest(FormRequest $request): self` factory method.

### Example:
```php
namespace App\DTOs;

class POSInvoiceDTO
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $storeId,
        public readonly string $paymentType,
        public readonly float $paidAmount,
        public readonly array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: (int)$data['customer_id'],
            storeId: (int)$data['store_id'],
            paymentType: $data['payment_type'] ?? 'cash',
            paidAmount: (float)($data['paid_amount'] ?? 0),
            items: $data['items'],
        );
    }
}
```

---

## 4. 🔍 Pipeline & Query Filter Pattern (`app/Filters/` + `Illuminate\Pipeline\Pipeline`)

### Rule:
* **Eliminate `if` Ladders:** When filtering queries by search, status, plan, date range, store, or category, pass the Eloquent Query Builder through Laravel's `Pipeline`.
* Each filter is an isolated invokable class with `handle(Builder $query, Closure $next)`.

### Controller Usage:
```php
$tenants = app(Pipeline::class)
    ->send(Tenant::query()->with(['plan', 'domains']))
    ->through([
        \App\Filters\Tenants\SearchFilter::class,
        \App\Filters\Tenants\StatusFilter::class,
        \App\Filters\Tenants\PlanFilter::class,
    ])
    ->thenReturn()
    ->latest()
    ->paginate(15);
```

---

## 5. 👁️ Observers Pattern (`app/Observers/`)

### Rule:
* **Automatic Side-Effects:** Logging, audit records, event broadcasting, cache invalidation, and status change alerts must be handled inside Model Observers.
* Observers are registered centrally in `AppServiceProvider::boot()`.

---

## 6. 🧼 Lean Models (`app/Models/`)

### Rule:
* Models must remain clean and lean.
* Models are strictly reserved for:
  1. Table relationships (`belongsTo`, `hasMany`, etc.)
  2. Attribute Casts (`casts()`)
  3. Local Scopes (`scopeActive`, `scopeInStore`)
  4. Accessors & Mutators.
* **Never** place heavy business logic or external API calls inside Eloquent Models.

---

## 7. 🔄 Dependency Inversion Principle (DIP & Contracts in `app/Contracts/`)

### Rule:
* Controllers and Actions depend on **Abstractions (Interfaces/Contracts)**, not concrete implementations.
* Bindings are registered in `AppServiceProvider::register()` or domain-specific Service Providers.

```php
$this->app->bind(
    \App\Contracts\TenantProvisionerInterface::class,
    \App\Services\TenantProvisionerService::class
);
```

---

## 8. 🌐 Zero Hardcoded Strings & Mandatory Localization Gate (`lang/`)

### Rule:
* **Strict Gate - NO Static/Hardcoded Text:** It is strictly forbidden to commit, create, or modify any file with hardcoded strings (whether in PHP actions, controllers, API responses, flash alerts, validation messages, Vue components, or Blade views).
* **Mandatory Localization:** Every newly introduced string MUST be immediately registered in both language files (`lang/ar/` and `lang/en/`).
* In PHP: Use `__('file.key')` or `trans('file.key')`.
* In Vue 3: Use `$t('file.key')`, `trans('file.key')`, or `useTrans()`.

---

## 9. 🛡️ Data Transformers / API Resources Pattern (`app/Http/Resources/`)

### Rule:
* **Never Expose Raw Eloquent Models:** All data passed to Inertia views or returned from APIs must be transformed via dedicated `JsonResource` classes.
* Clean and format dates, mask sensitive fields, compute human-readable values, and append authorization flags (`can_edit`, `can_delete`).

### Example:
```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateString(),
            'can_manage' => $request->user()?->can('tenants.manage'),
        ];
    }
}
```

---

## 10. 🧩 Vue 3 Composables Pattern (`resources/js/Composables/`)

### Rule:
* **Extract Reusable UI Logic:** Extract common reactive logic into standalone Composables rather than duplicating reactive state in components.
* Standard Composables:
  * `useMoney.js`: Financial currency formatting.
  * `usePOSCart.js`: Cashier reactive cart calculations.
  * `useTheme.js`: Dark/Light mode switcher and persistent sync.
  * `useDeleteHandler.js`: Safe deletion confirmation and loading states.

---

## 11. ⏳ Inertia Lazy Props Pattern (`Inertia::lazy()`)

### Rule:
* **Asynchronous Heavy Data:** Heavy queries, chart telemetry, and secondary logs must be declared via `Inertia::lazy(fn() => ...)` so initial page load renders instantly.
* In Vue 3: Reload deferred props on demand using `router.reload({ only: ['heavyData'] })`.

---

## 12. 🌐 Single Source of Truth Translation Protocol (`php artisan lang:export`)

### Rule:
* **Strict Prohibition of Manual JS Translations:** Never edit translations inside JavaScript/Vue files manually.
* **Single Source of Truth:** All translation keys MUST be added/modified exclusively in Laravel PHP language files:
  - Arabic: `backend/lang/ar/{group}.php`
  - English: `backend/lang/en/{group}.php`
* **Automated Frontend Synchronization:**
  - Execute `php artisan lang:export` (or run `npm run build` which triggers it automatically via `prebuild`).
  - This generates `resources/js/helpers/defaultTranslations.json` and `resources/js/helpers/defaultTranslations.js` automatically.
* **Zero Fallback Strings:** In Vue templates and scripts, always use `$t('group.key')` or `const { t } = useTrans(); t('group.key')` with ZERO hardcoded text or fallback operators (`|| 'نص'`).

