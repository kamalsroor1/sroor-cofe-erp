<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Container\Attributes\Scoped;

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
