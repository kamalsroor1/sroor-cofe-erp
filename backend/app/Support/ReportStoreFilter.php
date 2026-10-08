<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the `store_id` filter used by the Blade print /
 * report routes (daily journal, item movements, reports).
 *
 * The value returned here is the exact value the caller must filter by, so
 * the access check is performed on what is actually used — not on the raw
 * input (which `StoreAccess` middleware only partially inspects).
 *
 *  - Admin: missing / "all" => null (all stores); positive integer => that store.
 *  - Non-admin: missing => current session store, then default store;
 *    "all" => 403; positive integer => that store if the user may access it.
 *  - Anything else (arrays, "1abc", "0", negative) => 422.
 */
final class ReportStoreFilter
{
    public static function resolve(Request $request, ?User $user): ?int
    {
        if ($user === null) {
            abort(401);
        }

        $isAdmin = $user->hasRole('admin');
        $raw = $request->query('store_id');

        if ($raw !== null && ! is_string($raw)) {
            self::invalid();
        }

        $raw = $raw === null ? '' : trim($raw);

        if ($raw === '' || $raw === 'all') {
            if ($isAdmin) {
                return null;
            }

            if ($raw === 'all') {
                abort(403, __('common.store_access_denied'));
            }

            $fallback = session('current_store_id') ?: $user->default_store_id;
            $storeId = self::toPositiveInt($fallback);

            if ($storeId === null) {
                abort(403, __('common.store_access_denied'));
            }
        } else {
            $storeId = self::toPositiveInt($raw);

            if ($storeId === null) {
                self::invalid();
            }
        }

        if (! $isAdmin && ! self::canAccess($user, $storeId)) {
            abort(403, __('common.store_access_denied'));
        }

        return $storeId;
    }

    private static function canAccess(User $user, int $storeId): bool
    {
        return (int) $user->default_store_id === $storeId
            || $user->stores()->where('stores.id', $storeId)->exists();
    }

    private static function toPositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages([
            'store_id' => __('validation.integer', ['attribute' => 'store_id']),
        ]);
    }
}
