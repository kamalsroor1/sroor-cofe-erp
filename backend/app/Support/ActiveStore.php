<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * STOR-1: the store (branch) the current request operates on, resolved and access-checked
 * once by ResolveActiveStore from the `X-Store-Id` header (never from the body/query).
 *
 * Scoped: one instance per request (Octane-safe), so nothing leaks into the next request.
 *
 * Access rules are the single source of truth for store access (they mirror StorePolicy::view):
 *  - a specific store: admin role / `stores.manage`, or the user's default store, or a store
 *    assigned through `store_user`;
 *  - every store (`X-Store-Id: all`): admin role / `stores.manage` / `stores.view_all`
 *    (`stores.view_all` is seeded by PermissionsSeeder; per CTO D5 only the admin role holds it by default).
 */
#[Scoped]
final class ActiveStore
{
    public const ALL = 'all';

    /** error_code of the 403 a single-store operation returns for `X-Store-Id: all` (STOR-2). */
    public const CONCRETE_REQUIRED_CODE = 'concrete_store_required';

    private ?Store $store = null;

    private bool $allStores = false;

    private bool $resolved = false;

    public function set(Store $store): void
    {
        $this->store = $store;
        $this->allStores = false;
        $this->resolved = true;
    }

    public function setAll(): void
    {
        $this->store = null;
        $this->allStores = true;
        $this->resolved = true;
    }

    /** True once ResolveActiveStore ran for this request. */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /** True when the request explicitly targets every store (`X-Store-Id: all`). */
    public function isAll(): bool
    {
        return $this->allStores;
    }

    public function store(): ?Store
    {
        return $this->store;
    }

    /** The active store id, or null when unresolved or in all-stores mode. */
    public function id(): ?int
    {
        return $this->store?->id;
    }

    /**
     * STOR-2: the concrete store of a single-store operation (checkout, invoice, shift, transfer).
     * Translated 403 when the request runs on every store (`all`) or was never resolved, so a
     * write can never fall back to some other branch.
     */
    public function requireStore(): Store
    {
        if ($this->store instanceof Store) {
            return $this->store;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __($this->allStores ? 'store_scope.concrete_store_required' : 'store_scope.store_not_resolved'),
            'error_code' => self::CONCRETE_REQUIRED_CODE,
        ], 403));
    }

    /**
     * STOR-2: store filter of a list/report read. A client `store_id` (query/body) is an explicit
     * branch filter, access-checked by ClientStoreGuard (403); `all` there needs canViewAll. Without
     * it the active store applies. Null means every store, only reachable through a verified `all`.
     */
    public function readFilter(Request $request): ?int
    {
        $client = ClientStoreGuard::verified($request);

        if (is_int($client)) {
            return $client;
        }

        if ($client === self::ALL || $this->allStores) {
            return null;
        }

        return (int) $this->requireStore()->id;
    }

    /**
     * STOR-2: the one store of a single-store read (current shift, daily journal, treasury). The
     * active store wins; a client `store_id` is still access-checked (403). In `all` mode an
     * accessible client store id is used, else 422 on store_id: `all` never becomes "store 0".
     */
    public function singleStoreId(Request $request): int
    {
        $client = ClientStoreGuard::verified($request);

        if ($this->store instanceof Store) {
            return (int) $this->store->id;
        }

        if (is_int($client)) {
            return $client;
        }

        if ($this->allStores) {
            throw ValidationException::withMessages([
                'store_id' => __('validation.integer', ['attribute' => 'store_id']),
            ]);
        }

        return (int) $this->requireStore()->id;
    }

    /**
     * STOR-2 (CTO Q13): may $user READ a record of store $storeId? Users who may see every store,
     * or who may access that store. Callers answer a record outside this rule as "not found" (404).
     */
    public static function canRead(User $user, ?int $storeId): bool
    {
        if (self::canViewAll($user)) {
            return true;
        }

        return $storeId !== null && self::canAccess($user, $storeId);
    }

    public static function canViewAll(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage')
            || $user->can('stores.view_all');
    }

    public static function canAccess(User $user, int $storeId): bool
    {
        if ($user->hasRole('admin') || $user->can('stores.manage')) {
            return true;
        }

        return (int) $user->default_store_id === $storeId
            || $user->stores()->where('stores.id', $storeId)->exists();
    }

    /**
     * The store a request without `X-Store-Id` falls back to: the user's default store, then the
     * first assigned store, then (only for users allowed to see every store) the main store.
     * Inactive stores are skipped. Null when the user has no usable store at all.
     */
    public static function defaultFor(User $user): ?Store
    {
        if ($user->default_store_id !== null) {
            $default = Store::query()->whereKey($user->default_store_id)->where('is_active', true)->first();
            if ($default instanceof Store) {
                return $default;
            }
        }

        $assigned = $user->stores()->where('stores.is_active', true)->orderBy('stores.id')->first();
        if ($assigned instanceof Store) {
            return $assigned;
        }

        if (self::canViewAll($user)) {
            $main = Store::getMainStore();

            return $main instanceof Store ? $main : null;
        }

        return null;
    }

    /**
     * Parses a raw `X-Store-Id` value: a positive integer id, the literal `all`, or null for
     * anything else (empty, "0", "abc", "1.5", "2abc", arrays…).
     */
    public static function parseHeader(mixed $raw): int|string|null
    {
        if (! is_string($raw)) {
            return null;
        }

        $raw = trim($raw);

        if ($raw === self::ALL) {
            return self::ALL;
        }

        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
